<?php
/**
 * Dropbox Uploader
 *
 * Copyright (c) 2009 Jaka Jancar
 * Copyright (c) 2012 KOLANICH
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
 * @author Jaka Jancar [jaka@kubje.org] [http://jaka.kubje.org/],
 * @author KOLANICH [https://github.com/KOLANICH/]
 * @version 1.1.5
 */
 
class DropboxUploaderException extends Exception{
}

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
    const HTTPS_DROPBOX_COM             = 'https://www.dropbox.com/';
    const HTTPS_DROPBOX_COM_HOME        = 'https://www.dropbox.com/home';
    const HTTPS_DROPBOX_COM_LOGIN       = 'https://www.dropbox.com/login';
    const HTTPS_DROPBOX_COM_LOGOUT      = 'https://www.dropbox.com/logout';
    const HTTPS_DROPBOX_COM_UPLOAD      = 'https://dl-web.dropbox.com/upload';
    const HTTPS_DROPBOX_COM_DELETE      = 'https://www.dropbox.com/cmd/delete';
    /**
     * DropboxUploader Error Flags and Codes
     */
    const FLAG_DROPBOX_GENERIC         = 0x10000000;
    const FLAG_LOCAL_FILE_IO           = 0x10010000;
    const CODE_FILE_READ_ERROR         = 0x10010101;
    const CODE_TEMP_FILE_CREATE_ERROR  = 0x10010102;
    const CODE_TEMP_FILE_WRITE_ERROR   = 0x10010103;
    const FLAG_PARAMETER_INVALID       = 0x10020000;
    const CODE_PARAMETER_TYPE_ERROR    = 0x10020101;
    const CODE_FILESIZE_TOO_LARGE      = 0x10020201;
    const FLAG_REMOTE                  = 0x10040000;
    const CODE_CURL_ERROR              = 0x10040101;
    const CODE_LOGIN_ERROR             = 0x10040201;
    const CODE_UPLOAD_ERROR            = 0x10040401;
    const CODE_SCRAPING_FORM           = 0x10040801;
    const CODE_SCRAPING_LOGIN          = 0x10040801;
    const CODE_CURL_EXTENSION_MISSING  = 0x10080101;

    protected $email;
    protected $password;
    protected $caCertSourceType = self::CACERT_SOURCE_SYSTEM;
    protected $caCertSource;
    protected $loggedIn = FALSE;
    protected $ch;
    protected $token;
    static $cookieFileNameTemplate;
    static $defaultCertFile;

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
            throw new DropboxUploaderException('DropboxUploader requires the cURL extension.', self::CODE_CURL_EXTENSION_MISSING);
        if (empty($email)) {
            throw new DropboxUploaderException('Email'.(empty($password) ? ' and password' : '' ). ' must not be empty.', self::CODE_PARAMETER_TYPE_ERROR);
        }
        if (empty($password)) {
            throw new DropboxUploaderException('Password must not be empty.', self::CODE_PARAMETER_TYPE_ERROR);
        }

        $this->email    = $email;
        $this->password = $password;
        static::initCh();
        static::setCaCertificateFile(static::$defaultCertFile);
    }

    protected function initCh(){
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; rv:20.0) Gecko/20121202 Firefox/20.0');
    }

    protected function setCookieFile($id=null){
        $cfile=is_null($id)?"":str_replace("%ID%",$id,static::$cookieFileNameTemplate);
        new dBug($cfile);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE,$cfile);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR,$cfile);
    }

    function _destruct(){
        curl_close($this->ch);
    }
    
    public function setCaCertificate($path){
        if(is_dir($path))
            static::setCaCertificateDir($path);
        else
            setCaCertificateFile($path);
    }
    
    public function setCaCertificateDir($dir){
        $this->caCertSourceType = self::CACERT_SOURCE_DIR;
        curl_setopt($this->ch, CURLOPT_CAPATH, $dir);
    }

    public function setCaCertificateFile($file){
        $this->caCertSourceType = self::CACERT_SOURCE_FILE;
        curl_setopt($this->ch, CURLOPT_CAINFO, $file);
    }

    public function upload($source, $remoteDir = '/', $remoteName = NULL) {

        if (is_null($remoteName)) {
            $remoteName = $source;
        } else if (!is_string($remoteName)) {
            throw new DropboxUploaderException("Remote filename must be a string, is " . gettype($remoteName) . " instead.", self::CODE_PARAMETER_TYPE_ERROR);
        }

        if (!is_string($remoteDir))
            throw new DropboxUploaderException("Remote directory must be a string, is " . gettype($remoteDir) . " instead.", self::CODE_PARAMETER_TYPE_ERROR);

        if (!is_file($source) or !is_readable($source))
            throw new DropboxUploaderException("File '$source' does not exist or is not readable.", self::CODE_FILE_READ_ERROR);

        $filesize = filesize($source);
        if ($filesize < 0 or $filesize > self::DROPBOX_UPLOAD_LIMIT_IN_BYTES) {
            throw new DropboxUploaderException("File '$source' too large ($filesize bytes).", self::CODE_FILESIZE_TOO_LARGE);
        }

        if (!$this->loggedIn)
            $this->login();

        $postData = array(
            'plain'=>'yes',
            'file'=>'@'.$source.';filename='.$remoteName,
            'dest'=>$remoteDir,
        );

        curl_setopt($this->ch, CURLOPT_REFERER, HTTPS_DROPBOX_COM_HOME);
        $this->request(self::HTTPS_DROPBOX_COM_UPLOAD, $postData);
        if (curl_getinfo($this->ch,CURLINFO_HTTP_CODE)!==200)
            throw new DropboxUploaderException('Upload failed!', self::CODE_UPLOAD_ERROR);
    }



    public function uploadString($string, $remoteName, $remoteDir = '/') {
        $exception = NULL;

        $file = tempnam(sys_get_temp_dir(), 'DBUploadString');
        if (!is_file($file))
            throw new DropboxUploaderException("Can not create temporary file.", self::CODE_TEMP_FILE_CREATE_ERROR);

        $bytes = file_put_contents($file, $string);
        if ($bytes === FALSE) {
            unlink($file);
            throw new DropboxUploaderException("Can not write to temporary file '$file'.", self::CODE_TEMP_FILE_WRITE_ERROR);
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

    public function uploadBuffer($source, $remoteDir='/', $remoteName=null) {
        if (!is_string($remoteName)) {
            throw new DropboxUploaderException("Remote filename must be a string, is " . gettype($remoteName) . " instead.", self::CODE_PARAMETER_TYPE_ERROR);
        }

        if (!is_string($remoteDir))
            throw new DropboxUploaderException("Remote directory must be a string, is " . gettype($remoteDir) . " instead.", self::CODE_PARAMETER_TYPE_ERROR);

        if (!$this->loggedIn)
            $this->login();

        $postData = array(
            'plain'=>'yes',
            'file'=>$source,
            'filename'=>$remoteName,
            'dest'=>$remoteDir
        );


        curl_setopt($this->ch, CURLOPT_REFERER, HTTPS_DROPBOX_COM_HOME);
        $res = $this->request(self::HTTPS_DROPBOX_COM_UPLOAD, $postData);

        if (curl_getinfo($this->ch,CURLINFO_HTTP_CODE)!==200)
            throw new DropboxUploaderException('Upload failed!', self::CODE_UPLOAD_ERROR);
    }

    public function login($cacheAuth=FALSE) {
        static::setCookieFile($cacheAuth?preg_replace('/[^\w\.@-]/i','_',$this->email):null);
        curl_setopt($this->ch, CURLOPT_REFERER, self::HTTPS_DROPBOX_COM);
        $res  = $this->request(self::HTTPS_DROPBOX_COM);
        if(!curl_getinfo($this->ch,CURLINFO_REDIRECT_COUNT)){
            $res = $this->request(self::HTTPS_DROPBOX_COM_LOGIN, array(
                'login_email'    =>$this->email,
                'login_password'=>$this->password,
                'remember_me'    =>'on',
                //'cont'        =>'/home',
            ));
            if (!curl_getinfo($this->ch,CURLINFO_REDIRECT_COUNT))
                throw new DropboxUploaderException('Login unsuccessful.: CURL error: '.curl_error($this->ch).' Code '.curl_errno($this->ch),self::CODE_LOGIN_ERROR);
        }

        $this->loggedIn = true;
    }

    protected function request($url, $postData=null) {
        curl_setopt($this->ch, CURLOPT_URL, $url);

        if ($postData) {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            if(isset($this->token))$postData['t']=$this->token;
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);
        }else{
            curl_setopt($this->ch,CURLOPT_HTTPGET,1);
        }

        $data=curl_exec($this->ch);

        if ($data === FALSE)
            throw new DropboxUploaderException('Cannot execute request: '.curl_error($this->ch));

        static::naivelyExtractToken($data);
        return $data;
    }

    protected function naivelyExtractToken($html) {
        if (!preg_match('/<input[^><]+name=([\'"])t\1[^><]value=([\'"])([\w-]+)\2[^><]*>/i', $html, $matches))
            throw new DropboxUploaderException('Cannot naively extract login CSRF token.', self::CODE_SCRAPING_FORM);
        $this->token=$matches[3];
    }

}
DropboxUploader::$cookieFileNameTemplate=__DIR__.'/cookie_%ID%_.txt';
DropboxUploader::$defaultCertFile=__DIR__.'/dropbox.crt';
