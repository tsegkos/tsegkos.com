<?php
    require_once(dirname(__FILE__) . "/../constants.php");
    require_once(dirname(__FILE__) . '/MonstaLicenseV2.php');

    class MonstaLicenseV3 extends MonstaLicenseV2 {
        // the V3 refers to the license version, not the application version

        /**
         * @var int
         */
        protected $productEdition;

        public function __construct($email, $purchaseDate, $expiryDate, $version, $isTrial, $productEdition) {
            if ($productEdition !== MONSTA_PRODUCT_EDITION_PROFESSIONAL &&
                $productEdition !== MONSTA_PRODUCT_EDITION_ENTERPRISE
            )
                throw new InvalidArgumentException("product edition must be 0 or 1");

            parent::__construct($email, $purchaseDate, $expiryDate, $version, $isTrial);
            $this->productEdition = $productEdition;
        }

        public function getLicenseVersion() {
            return 3;
        }

        public function toArray() {
            $arr = parent::toArray();
            $arr['licenseVersion'] = $this->getLicenseVersion();
            $arr['productEdition'] = $this->productEdition;
            return $arr;
        }

        public function isMonstaProfessionalEdition() {
            return $this->productEdition == MONSTA_PRODUCT_EDITION_PROFESSIONAL;
        }

        public function isMonstaEnterpriseEdition() {
            return $this->productEdition == MONSTA_PRODUCT_EDITION_ENTERPRISE;
        }
    }

