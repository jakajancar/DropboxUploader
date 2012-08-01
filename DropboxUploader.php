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
 * @author Jaka Jancar [jaka@kubje.org] [http://jaka.kubje.org/], improved and fixed by KOLANICH
 * @version 1.1.5
 */
 
/*function getFileNumberByHandle($fh){
	$txt="";
	$txt.=$fh;
	if(preg_match("%Resource id #(\d+)%i",$txt,$r)){
		return $r[1];
	};
}*/
class DropboxUploader {
    protected $email;
    protected $password;
    protected $caCertSourceType = self::CACERT_SOURCE_SYSTEM;
    const CACERT_SOURCE_SYSTEM = 0;
    const CACERT_SOURCE_FILE = 1;
    const CACERT_SOURCE_DIR = 2;
    protected $caCertSource;
    protected $loggedIn = false;
    protected $ch;
    static $cookieFile;
    static $certFile;
    /**
     * Constructor
     *
     * @param string $email
     * @param string|null $password
     */
    public function __construct($email, $password) {
        // Check requirements
        if (!extension_loaded('curl'))
            throw new Exception('DropboxUploader requires the cURL extension.');
		if(empty($email)||empty($password))throw new Exception('Some info needed to log in missed');
		$this->email = $email;
        $this->password = $password;
		static::initCh();
		static::setCaCertificateFile(static::$certFile);
		
        
    }
    
	function _destruct(){
		curl_close($this->ch);
	}
	
	function initCh(){
		$this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 1);
		/*curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->ch, CURLOPT_PROXY, '127.0.0.1:8888');*/
		$cfile=str_replace("%MAIL%",$this->email,static::$cookieFile);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE,$cfile);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR,$cfile);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:14.0) Gecko/20100101 Firefox/14.0.1');
	}
	
    public function setCaCertificateFile($file)
    {
        $this->caCertSourceType = self::CACERT_SOURCE_FILE;
        $this->caCertSource = $file;
		curl_setopt($this->ch, CURLOPT_CAINFO, $this->caCertSource);
    }
    
    public function setCaCertificateDir($dir)
    {
        $this->caCertSourceType = self::CACERT_SOURCE_DIR;
        $this->caCertSource = $dir;
		curl_setopt($this->ch, CURLOPT_CAPATH, $this->caCertSource);
    }

    public function uploadBuffer($source, $remoteDir='/', $remoteName=null) {
       $fh=fopen('php://memory',"w");
		fwrite($fh,$source);
		fflush($fh);
        return static::upload('php://memory', $remoteDir, $remoteName);
		fclose($fh);
    }
	
	public function upload($source, $remoteDir='/', $remoteName) {
		if (!file_exists($source) or !is_file($source) or !is_readable($source))
            throw new Exception("File '$source' does not exist or is not readable.");
		if (is_null($remoteName)) {
            $remoteName = $source;
        }
		if (!is_string($remoteName)) {
            throw new Exception("Remote filename must be a string, is ".gettype($remoteDir)." instead.");
        }
        if (!is_string($remoteDir))
          throw new Exception("Remote directory must be a string, is ".gettype($remoteDir)." instead.");
        
        if (!$this->loggedIn)
            $this->login();
        
        $res = $this->request('https://www.dropbox.com/home');
        $token = $this->extractToken($res->data, 'https://dl-web.dropbox.com/upload');

		
        $postdata = array('plain'=>'yes', 'file'=>'@'.$source.';filename='.$remoteName, 'dest'=>$remoteDir, 't'=>$token);
        //$postdata = array('plain'=>'yes', 'file'=>array('name' => $remoteName, 'file' =>$source), 'dest'=>$remoteDir, 't'=>$token);
		curl_setopt($this->ch, CURLOPT_REFERER, 'https://www.dropbox.com/home');
        $res = $this->request('https://dl-web.dropbox.com/upload', $postdata);
       if (strpos($res->data, 'HTTP/1.1 302 FOUND') === false)
            throw new Exception('Upload failed!');
		/*if ($res->code!=302)
            throw new Exception('Upload failed!');*/
    }
    
    public function login() {
		curl_setopt($this->ch, CURLOPT_REFERER, 'https://www.dropbox.com/');
        ///$res = $this->request('https://www.dropbox.com/login');
        $res = $this->request('https://www.dropbox.com/login', array(
			'login_email'=>$this->email,
			'login_password'=>$this->password,
			'remember_me'=>'on',
			//'cont'=>'/home'
		));
        if (stripos($res->data, 'location: /home') === false)
            throw new Exception('Login unsuccessful.: CURL error: '.$res->error.' Code '.$res->code);
        
        $this->loggedIn = true;
    }
	
    protected function request($url, $postData=null) {
		curl_setopt($this->ch, CURLOPT_URL, $url);
		//var_dump($postData);
		if ($postData) {
			curl_setopt($this->ch, CURLOPT_POST, 1);
			//curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($postData));
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postData);
		}else{
			curl_setopt($this->ch,CURLOPT_HTTPGET,1);
		}
		if ($resobj->data === false)
			throw new Exception('Cannot execute request: '.curl_error($this->ch));


		$resobj=new StdClass;
		$resobj->data=curl_exec($this->ch);
		$resobj->error=curl_error($this->ch);
		$resobj->code=curl_errno($this->ch);
		return $resobj;
    }

    protected function extractToken($html, $formAction) {
        if (!preg_match('/<form [^>]*'.preg_quote($formAction, '/').'[^>]*>.*?(<input [^>]*name="t" [^>]*value="(.*?)"[^>]*>).*?<\/form>/is', $html, $matches) || !isset($matches[2]))
            throw new Exception("Cannot extract token! (form action=$formAction)");
        return $matches[2];
    }

}
DropboxUploader::$cookieFile=__DIR__.'/cookie_%MAIL%_.txt';
DropboxUploader::$certFile=__DIR__.'/dropbox.crt';
//var_dump(DropboxUploader::$cookieFile);
