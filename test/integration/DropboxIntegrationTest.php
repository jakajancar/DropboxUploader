<?php
/**
 * This file is part of Dropbox Uploader Phpunit testsuite
 *
 * Copyright (c) 2014 hakre <http://hakre.wordpress.com/>
 *
 * See COPYING
 */

/**
 * Class LoginTest
 */
class DropboxIntegrationTest extends BaseTestCase
{
    /**
     * @var DropboxUploader
     */
    private $subject;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp() {
        $this->subject = $this->createDefaultUploader();
    }

    /**
     * @test
     * @requires PHP 5.3.2
     */
    public function testLogin() {
        $uploader = $this->subject;

        $loginMethod = new ReflectionMethod('DropboxUploader', 'login');
        $loginMethod->setAccessible(true);
        $loginMethod->invoke($uploader); // $uploader->login();

        return $uploader;
    }

    /**
     * @test
     * @depends testLogin
     * @param DropboxUploader $uploader
     * @throws Exception
     * @throws null
     */
    public function testUploadStringAsFile(DropboxUploader $uploader) {
        list($usec, $sec) = explode(' ', microtime());
        $timestamp = date("r", $sec) . sprintf(" (%s Microseconds)", $usec * 1000000);
        $string    = 'This file is a test: ' . $timestamp . "\n";

        $this->addToAssertionCount(1);
        $uploader->uploadString($string, 'test.txt', 'test/integration');
    }

    /**
     * @test
     * @link https://github.com/jakajancar/DropboxUploader/issues/18
     * @depends testLogin
     * @param DropboxUploader $uploader
     * @throws Exception
     */
    public function testUploadFileWithQuote(DropboxUploader $uploader) {
        $path = __DIR__ . '/../fixtures/file\'with-quote.ext';

        $this->addToAssertionCount(1);
        $uploader->upload($path, 'test/integration');
    }

    /**
     * @test
     * @depends testLogin
     * @param DropboxUploader $uploader
     */
    public function testInvalidCertpathException(DropboxUploader $uploader) {
        $thrown = FALSE;
        // clone working uploader and provoke SSL error by using an existing but invalid certificate dir
        $broken = clone $uploader;
        $broken->setCaCertificateDir(dirname(__FILE__));

        try {
            unset($broken);
        } catch (Exception $e) {
            $expected = "Curl error: (#60) SSL certificate problem";
            $this->assertStringStartsWith($expected, $e->getMessage());
            $thrown = TRUE;
        }
        $this->assertSame(TRUE, $thrown);
    }

    /**
     * @test
     * @depends testLogin
     * @param DropboxUploader $uploader
     */
    public function testInvalidCertfileException(DropboxUploader $uploader) {
        $thrown = FALSE;
        // clone working uploader and provoke SSL error by using an existing but invalid certificate file
        $broken = clone $uploader;
        $broken->setCaCertificateFile(__FILE__);

        try {
            unset($broken);
        } catch (Exception $e) {
            $expected = "Curl error: (#77) error setting certificate verify locations";
            $this->assertStringStartsWith($expected, $e->getMessage());
            $thrown = TRUE;
        }
        $this->assertSame(TRUE, $thrown);
    }
}
