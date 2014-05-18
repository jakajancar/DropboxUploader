<?php
/**
 * Dropbox Uploader
 *
 * Copyright (c) 2009 Jaka Jancar
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author Jaka Jancar <jaka@kubje.org> <http://jaka.kubje.org/>
 * @version 1.1.17
 * @license MIT <http://spdx.org/licenses/MIT>
 */
final class DropboxUploader {
    /**
     * Certificate Authority Certificate source types
     */
    const CACERT_SOURCE_SYSTEM = 0;
    const CACERT_SOURCE_FILE   = 1;
    const CACERT_SOURCE_DIR    = 2;
    /**
     * Dropbox configuration
     */
    const DROPBOX_UPLOAD_LIMIT_IN_BYTES = 314572800;
    const HTTPS_DROPBOX_COM_HOME        = 'https://www.dropbox.com/home';
    const HTTPS_DROPBOX_COM_LOGIN       = 'https://www.dropbox.com/login';
    const HTTPS_DROPBOX_COM_UPLOAD      = 'https://dl-web.dropbox.com/upload';
    /**
     * DropboxUploader Error Flags and Codes
     */
    const FLAG_DROPBOX_GENERIC        = 0x10000000;
    const FLAG_LOCAL_FILE_IO          = 0x10010000;
    const CODE_FILE_READ_ERROR        = 0x10010101;
    const CODE_TEMP_FILE_CREATE_ERROR = 0x10010102;
    const CODE_TEMP_FILE_WRITE_ERROR  = 0x10010103;
    const FLAG_PARAMETER_INVALID      = 0x10020000;
    const CODE_PARAMETER_TYPE_ERROR   = 0x10020101;
    const CODE_FILESIZE_TOO_LARGE     = 0x10020201;
    const FLAG_REMOTE                 = 0x10040000;
    const CODE_CURL_ERROR             = 0x10040101;
    const CODE_LOGIN_ERROR            = 0x10040201;
    const CODE_UPLOAD_ERROR           = 0x10040401;
    const CODE_SCRAPING_FORM          = 0x10040801;
    const CODE_SCRAPING_LOGIN         = 0x10040802;
    const CODE_CURL_EXTENSION_MISSING = 0x10080101;
    private $email;
    private $password;
    private $caCertSourceType = self::CACERT_SOURCE_SYSTEM;
    private $caCertSource;
    private $loggedIn = FALSE;
    private $cookies = array();

    /**
     * Constructor
     *
     * @param string $email
     * @param string $password
     * @throws Exception
     */
    public function __construct($email, $password) {
        // Check requirements
        if (!extension_loaded('curl'))
            throw new Exception('DropboxUploader requires the cURL extension.', self::CODE_CURL_EXTENSION_MISSING);

        if (empty($email) || empty($password)) {
            throw new Exception((empty($email) ? 'Email' : 'Password') . ' must not be empty.', self::CODE_PARAMETER_TYPE_ERROR);
        }

        $this->email    = $email;
        $this->password = $password;
    }

    public function setCaCertificateDir($dir) {
        $this->caCertSourceType = self::CACERT_SOURCE_DIR;
        $this->caCertSource     = $dir;
    }

    public function setCaCertificateFile($file) {
        $this->caCertSourceType = self::CACERT_SOURCE_FILE;
        $this->caCertSource     = $file;
    }

    public function upload($source, $remoteDir = '/', $remoteName = NULL) {
        if (!is_file($source) or !is_readable($source))
            throw new Exception("File '$source' does not exist or is not readable.", self::CODE_FILE_READ_ERROR);

        $filesize = filesize($source);
        if ($filesize < 0 or $filesize > self::DROPBOX_UPLOAD_LIMIT_IN_BYTES) {
            throw new Exception("File '$source' too large ($filesize bytes).", self::CODE_FILESIZE_TOO_LARGE);
        }

        if (!is_string($remoteDir))
            throw new Exception("Remote directory must be a string, is " . gettype($remoteDir) . " instead.", self::CODE_PARAMETER_TYPE_ERROR);

        if (is_null($remoteName)) {
            # intentionally left blank
        } else if (!is_string($remoteName)) {
            throw new Exception("Remote filename must be a string, is " . gettype($remoteDir) . " instead.", self::CODE_PARAMETER_TYPE_ERROR);
        }

        if (!$this->loggedIn)
            $this->login();

        $data       = $this->request(self::HTTPS_DROPBOX_COM_HOME);
        $file       = $this->curlFileCreate($source, $remoteName);
        $token      = $this->extractFormValue($data, 't');
        $subjectUid = $this->extractFormValue($data, '_subject_uid');

        $postData = array(
            'plain'        => 'yes',
            'file'         => $file,
            'dest'         => $remoteDir,
            't'            => $token,
            '_subject_uid' => $subjectUid,
        );

        $data     = $this->request(self::HTTPS_DROPBOX_COM_UPLOAD, $postData);
        if (strpos($data, 'HTTP/1.1 302 FOUND') === FALSE)
            throw new Exception('Upload failed!', self::CODE_UPLOAD_ERROR);
    }

    private function curlFileCreate($source, $remoteName) {
        if (function_exists('curl_file_create')) {
            return curl_file_create($source, NULL, $remoteName);
        }

        if ($remoteName !== NULL) {
            $source .= ';filename=' . $remoteName;
        }

        return '@' . $source;
    }

    public function uploadString($string, $remoteName, $remoteDir = '/') {
        $exception = NULL;

        $file = tempnam(sys_get_temp_dir(), 'DBUploadString');
        if (!is_file($file))
            throw new Exception("Can not create temporary file.", self::CODE_TEMP_FILE_CREATE_ERROR);

        $bytes = file_put_contents($file, $string);
        if ($bytes === FALSE) {
            unlink($file);
            throw new Exception("Can not write to temporary file '$file'.", self::CODE_TEMP_FILE_WRITE_ERROR);
        }

        try {
            $this->upload($file, $remoteDir, $remoteName);
        } catch (Exception $exception) {
            # intentionally left blank
        }

        unlink($file);

        if ($exception)
            throw $exception;
    }

    private function login() {
        $data  = $this->request(self::HTTPS_DROPBOX_COM_LOGIN);
        $token = $this->extractTokenFromLoginForm($data);

        $postData = array(
            'login_email'    => (string) $this->email,
            'login_password' => (string) $this->password,
            't'              => $token
        );
        $data     = $this->request(self::HTTPS_DROPBOX_COM_LOGIN, http_build_query($postData));

        if (stripos($data, 'location: /home') === FALSE)
            throw new Exception('Login unsuccessful.', self::CODE_LOGIN_ERROR);

        $this->loggedIn = TRUE;
    }

    private function request($url, $postData = NULL) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, (string) $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        switch ($this->caCertSourceType) {
            case self::CACERT_SOURCE_FILE:
                curl_setopt($ch, CURLOPT_CAINFO, (string) $this->caCertSource);
                break;
            case self::CACERT_SOURCE_DIR:
                curl_setopt($ch, CURLOPT_CAPATH, (string) $this->caCertSource);
                break;
        }
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (NULL !== $postData) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        // Send cookies
        $rawCookies = array();
        foreach ($this->cookies as $k => $v)
            $rawCookies[] = "$k=$v";
        $rawCookies = implode(';', $rawCookies);
        curl_setopt($ch, CURLOPT_COOKIE, $rawCookies);

        $data  = curl_exec($ch);
        $error = sprintf('Curl error: (#%d) %s', curl_errno($ch), curl_error($ch));
        curl_close($ch);

        if ($data === FALSE) {
            throw new Exception($error, self::CODE_CURL_ERROR);
        }

        // Store received cookies
        preg_match_all('/Set-Cookie: ([^=]+)=(.*?);/i', $data, $matches, PREG_SET_ORDER);
        foreach ($matches as $match)
            $this->cookies[$match[1]] = $match[2];

        return $data;
    }

    private function extractFormValue($html, $name) {
        $action  = self::HTTPS_DROPBOX_COM_UPLOAD;
        $pattern = sprintf(
            '/<form [^>]*%s[^>]*>.*?(?:<input [^>]*name="%s" [^>]*value="(.*?)"[^>]*>).*?<\/form>/is'
            , preg_quote($action, '/')
            , preg_quote($name, '/')
        );
        if (!preg_match($pattern, $html, $matches))
            throw new Exception(sprintf("Cannot extract '%s'! (form action is '%s')", $name, $action), self::CODE_SCRAPING_FORM);
        return $matches[1];
    }

    private function extractTokenFromLoginForm($html) {
        // , "TOKEN": "gCvxU6JVukrW0CUndRPruFvY",
        if (!preg_match('#, "TOKEN": "([A-Za-z0-9_-]+)", #', $html, $matches))
            throw new Exception('Cannot extract login CSRF token.', self::CODE_SCRAPING_LOGIN);
        return $matches[1];
    }

}
