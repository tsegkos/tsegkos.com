<?php

    require_once(dirname(__FILE__) . '/ConnectionBase.php');

    function normalizeFTPSysType($sysTypeName) {
        if (stripos($sysTypeName, 'unix') !== false || stripos($sysTypeName, 'macos'))
            return FTP_SYS_TYPE_UNIX;

        if (stripos($sysTypeName, 'windows') !== false)
            return FTP_SYS_TYPE_WINDOWS;

        throw new UnexpectedValueException(sprintf("Unknown FTP system type \"%s\".", $sysTypeName));
    }

    abstract class FTPConnectionBase extends ConnectionBase {
        /**
         * @var integer
         * This is lazy loaded
         */
        protected $sysType;

        protected $protocolName = 'FTP';

        abstract protected function rawGetSysType();

        abstract protected function handleChangeDirectory($newDirectory);

        abstract protected function handleGetCurrentDirectory();

        abstract protected function handlePassiveModeSet($passive);

        abstract protected function handleRawDirectoryList($listArgs);

        abstract protected function configureUTF8();

        public function getSysType() {
            if (!$this->isConnected())
                throw new FileSourceConnectionException("Attempting to get system type before connection.",
                    LocalizableExceptionDefinition::$GET_SYSTEM_TYPE_BEFORE_CONNECTION_ERROR);

            if ($this->sysType !== null)
                return $this->sysType;

            $sysTypeName = $this->rawGetSysType();

            if ($sysTypeName === false)
                throw new FileSourceConnectionException("Failed to retrieve system type",
                    LocalizableExceptionDefinition::$GET_SYSTEM_TYPE_FAILED_ERROR);

            $this->sysType = normalizeFTPSysType($sysTypeName);
            return $this->sysType;
        }

        public function changeDirectory($newDirectory) {
            $this->ensureConnectedAndAuthenticated('DIRECTORY_CHANGE_OPERATION');

            if (!PathOperations::directoriesMatch($newDirectory, $this->getCurrentDirectory())) {
                if (!$this->handleChangeDirectory($newDirectory))
                    $this->handleOperationError('DIRECTORY_CHANGE_OPERATION', $newDirectory, $this->getLastError());

                $this->syncCurrentDirectory();
            }
        }

        protected function syncCurrentDirectory() {
            $this->ensureConnectedAndAuthenticated('GET_CWD_OPERATION');

            $this->currentDirectory = $this->handleGetCurrentDirectory();
        }

        protected function postAuthentication() {
            $this->configureUTF8();
            $this->configurePassiveMode();
            $this->syncCurrentDirectory();
        }

        public function configurePassiveMode() {
            if (!$this->isAuthenticated())
                throw new FileSourceConnectionException("Can't configure passive mode before authentication.",
                    LocalizableExceptionDefinition::$PASSIVE_MODE_BEFORE_AUTHENTICATION_ERROR);

            if (!$this->handlePassiveModeSet($this->configuration->isPassiveMode())) {
                $passiveModeBoolName = $this->configuration->isPassiveMode() ? "true" : "false";

                throw new FileSourceConnectionException(sprintf("Failed to set passive mode to %s.",
                    $passiveModeBoolName), LocalizableExceptionDefinition::$FAILED_TO_SET_PASSIVE_MODE_ERROR,
                    array('is_passive_mode' => $passiveModeBoolName));
            }

        }

        protected function handleListDirectory($path, $showHidden) {
            if (!PathOperations::directoriesMatch($path, $this->getCurrentDirectory())) {
                $this->changeDirectory($path);
            }

            $listArgs = $showHidden ? '-a' : null;

            $dirList = $this->handleRawDirectoryList($listArgs);

            if ($dirList === false)
                throw new FileSourceOperationException(sprintf("Failed to list directory \"%s\"", $path),
                    LocalizableExceptionDefinition::$LIST_DIRECTORY_FAILED_ERROR,
                    array(
                        'path' => $path,
                    ));

            return new FTPListParser($dirList, $showHidden, $this->getSysType());
        }

        protected function handleCopy($source, $destination) {
            /* FTP does not provide built in copy functionality, so we copy file down to local and re-upload */
            $tempPath = tempnam(monstaGetTempDirectory(), 'ftp-temp');
            try {
                $this->downloadFile(new FTPTransferOperation($tempPath, $source, FTP_BINARY));
                $this->uploadFile(new FTPTransferOperation($tempPath, $destination, FTP_BINARY));
            } catch (Exception $e) {
                @unlink($tempPath);
                throw $e;
                // this should be done in a finally to avoid repeated code but we need to support PHP < 5.5
            }

            @unlink($tempPath);
        }

        public function supportsPermissionChange() {
            return $this->getSysType() == FTP_SYS_TYPE_UNIX;
        }

        protected function handleGetFileInfo($remotePath) {
            $remoteDirectory = dirname($remotePath);
            $fileName = basename($remoteDirectory);

            $dirList = $this->listDirectory($remoteDirectory, true);

            foreach ($dirList as $item) {
                if ($item->getName() == $fileName) {
                    return $item;
                }
            }

            return null;
        }
    }