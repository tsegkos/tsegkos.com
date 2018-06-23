<?php
    require_once(dirname(__FILE__) . "/../constants.php");

    class SystemVars {
        public static function getMaxFileUploadBytes() {
            if(defined("MFTP_MAX_UPLOAD_SIZE"))
                $maxFileSize = MFTP_MAX_UPLOAD_SIZE;
            else
                $maxFileSize = formattedSizeToBytes(ini_get('memory_limit'));  // legacy for old config

            return $maxFileSize;
        }

        public static function getSystemVarsArray() {
            return array(
                "maxFileUpload" => self::getMaxFileUploadBytes(),
                "version" => MONSTA_VERSION,
                "sshAgentAuthEnabled" => defined("SSH_AGENT_AUTH_ENABLED") && SSH_AGENT_AUTH_ENABLED === true,
                "sshKeyAuthEnabled" => defined("SSH_KEY_AUTH_ENABLED") && SSH_KEY_AUTH_ENABLED === true,
                "curlAvailable" => function_exists("curl_init")
            );
        }
    }