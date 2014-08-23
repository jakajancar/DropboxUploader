<?php
/**
 * This file is part of Dropbox Uploader Phpunit testsuite
 *
 * Copyright (c) 2014 hakre <http://hakre.wordpress.com/>
 *
 * See COPYING
 */

/**
 * Class DropboxUploaderTest
 */
class DropboxUploaderTest extends BaseTestCase
{
    /**
     * @test
     */
    public function testConstructor() {
        $this->addToAssertionCount(1);
        $uploader = new DropboxUploader('email', 'pass');
        $this->assertInstanceOf('DropboxUploader', $uploader);


        $exception = false;
        try {
            $this->addToAssertionCount(1);
            $uploader = new DropboxUploader('', '');
        } catch (Exception $e) {
            $code = $uploader::CODE_PARAMETER_TYPE_ERROR;
            $this->assertSame($code, $e->getCode());
            $exception = true;
        }
        $this->assertSame(true, $exception);

        $exception = false;

        try {
            $this->addToAssertionCount(1);
            new DropboxUploader('email', '');
        } catch (Exception $e) {
            $code = $uploader::CODE_PARAMETER_TYPE_ERROR;
            $this->assertSame($code, $e->getCode());
            $exception = true;
        }
        $this->assertSame(true, $exception);
    }

    /**
     * @test
     */
    public function testSetters() {
        $uploader = new DropboxUploader('email', 'pass');

        $this->addToAssertionCount(1);
        $uploader->setCaCertificateDir('');

        $this->addToAssertionCount(1);
        $uploader->setCaCertificateFile('');
    }
}
