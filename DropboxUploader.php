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
 * @author Jaka Jancar [jaka@kubje.org] [http://jaka.kubje.org/]
 * @version 1.1.9
 */
class DropboxUploader {
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
    protected $email;
    protected $password;
    protected $caCertSourceType = self::CACERT_SOURCE_SYSTEM;
    protected $caCertSource;
    protected $loggedIn = FALSE;
    protected $cookies = array();

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
            throw new Exception('DropboxUploader requires the cURL extension.');

        if (empty($email) || empty($password)) {
            throw new Exception(empty($email) ? 'Email' : 'Password' . ' must not be empty.');
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
            throw new Exception("File '$source' does not exist or is not readable.");

        $filesize = filesize($source);
        if ($filesize < 0 or $filesize > self::DROPBOX_UPLOAD_LIMIT_IN_BYTES) {
            throw new Exception("File '$source' too large ($filesize bytes).");
        }

        if (!is_string($remoteDir))
            throw new Exception("Remote directory must be a string, is " . gettype($remoteDir) . " instead.");

        if (is_null($remoteName)) {
            # intentionally left blank
        } else if (!is_string($remoteName)) {
            throw new Exception("Remote filename must be a string, is " . gettype($remoteDir) . " instead.");
        } else {
            $source .= ';filename=' . $remoteName;
        }

        if (!$this->loggedIn)
            $this->login();

        $data  = $this->request(self::HTTPS_DROPBOX_COM_HOME);
        $token = $this->extractToken($data, self::HTTPS_DROPBOX_COM_UPLOAD);

        $postData = array(
            'plain' => 'yes',
            'file'  => '@' . $source,
            'dest'  => $remoteDir,
            't'     => $token
        );
        $data     = $this->request(self::HTTPS_DROPBOX_COM_UPLOAD, $postData);
        if (strpos($data, 'HTTP/1.1 302 FOUND') === FALSE)
            throw new Exception('Upload failed!');
    }

    public function uploadString($string, $remoteName, $remoteDir = '/') {
        $exception = NULL;

        $file = tempnam(sys_get_temp_dir(), 'DBUploadString');
        if (!is_file($file))
            throw new Exception("Can not create temporary file.");

        $bytes = file_put_contents($file, $string);
        if ($bytes === FALSE) {
            unlink($file);
            throw new Exception("Can not write to temporary file '$file'.");
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

    protected function login() {
        $data  = $this->request(self::HTTPS_DROPBOX_COM_LOGIN);
        $token = $this->extractTokenFromLoginForm($data);

        $postData = array(
            'login_email'    => (string) $this->email,
            'login_password' => (string) $this->password,
            't'              => $token
        );
        $data     = $this->request(self::HTTPS_DROPBOX_COM_LOGIN, $postData);

        if (stripos($data, 'location: /home') === FALSE)
            throw new Exception('Login unsuccessful.');

        $this->loggedIn = TRUE;
    }

    protected function request($url, array $postData = NULL) {
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
            throw new Exception($error);
        }

        // Store received cookies
        preg_match_all('/Set-Cookie: ([^=]+)=(.*?);/i', $data, $matches, PREG_SET_ORDER);
        foreach ($matches as $match)
            $this->cookies[$match[1]] = $match[2];

        return $data;
    }

    protected function extractToken($html, $formAction) {
        $quot    = preg_quote($formAction, '/');
        $pattern = '/<form [^>]*' . $quot . '[^>]*>.*?(?:<input [^>]*name="t" [^>]*value="(.*?)"[^>]*>).*?<\/form>/is';
        if (!preg_match($pattern, $html, $matches))
            throw new Exception("Cannot extract token! (form action is '$formAction')");
        return $matches[1];
    }

    protected function extractTokenFromLoginForm($html) {
        // <input type="hidden" name="t" value="UJygzfv9DLLCS-is7cLwgG7z" />
        if (!preg_match('#<input type="hidden" name="t" value="([A-Za-z0-9_-]+)" />#', $html, $matches))
            throw new Exception('Cannot extract login CSRF token.');
        return $matches[1];
    }

}
