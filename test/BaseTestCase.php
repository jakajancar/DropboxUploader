<?php
/**
 * This file is part of Dropbox Uploader Phpunit testsuite
 *
 * Copyright (c) 2014 hakre <http://hakre.wordpress.com/>
 *
 * See COPYING
 */

/**
 * Class BaseTestCase
 */
class BaseTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * create DropboxUploader with default test configuration
     *
     * @return DropboxUploader
     */
    protected function createDefaultUploader() {
        $configuration = $this->getDefaultConfiguration();
        $uploader      = $this->createUploaderByConfiguration($configuration);

        return $uploader;
    }

    /**
     * create an uploader configured by array
     *
     * @param array $configuration
     * @return DropboxUploader
     */
    private function createUploaderByConfiguration(array $configuration) {
        $email = $configuration['Dropbox_Credential_Email'];
        $pass  = $configuration['Dropbox_Credential_Password'];

        $uploader = new DropboxUploader($email, $pass);

        $this->configureUploader($uploader, $configuration);

        return $uploader;
    }

    private function configureUploader(DropboxUploader $uploader, array $configuration) {

        if ($caCertificateDir = $configuration['Dropbox_CaCertificateDir']) {
            $uploader->setCaCertificateDir($caCertificateDir);
        }

        if ($caCertificateFile = $configuration['Dropbox_CaCertificateFile']) {
            $uploader->setCaCertificateFile($caCertificateFile);
        }
    }

    /**
     * read configuration from globals and environment with default values set.
     *
     * @return array
     */
    private function getDefaultConfiguration() {
        $defaults = array(
            'Dropbox_CaCertificateDir'    => NULL,
            'Dropbox_CaCertificateFile'   => NULL,
            'Dropbox_Credential_Email'    => NULL,
            'Dropbox_Credential_Password' => NULL,
        );

        $fromGlobals = array_intersect_key($GLOBALS, $defaults);
        $fromEnv     = array_intersect_key($_ENV, $defaults);

        $configuration = $fromEnv + $fromGlobals + $defaults;

        return $configuration;
    }
}
