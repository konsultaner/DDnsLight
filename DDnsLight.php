<?php

/**
 * Class DDnsLight
 *
 * Version 0.0.1
 *
 * Use in your Fritz!Box like
 * Update-Url = https://example.de/proxy/?pass=<pass>&ip=<ipaddr>
 *
 * Code of this class is based on
 * http://www.axelteichmann.de/DynamicDNS/index-DynDNS-mit-Fritzbox.php
 */
class DDnsLight{
    const STORAGE_FILE="storage/dns.ini.php";
    const STORAGE_FILE_TEMPLATE_HEADER=";<?php\n;die();\n;/*\n";
    const STORAGE_FILE_TEMPLATE_BODY="[data]\npassword=\nip=\n\n[log]\nlast-error=\nlast-ip-change=\n";
    const STORAGE_FILE_TEMPLATE_FOOTER=";*/\n;?>\n";
    const STORAGE_FILE_TEMPLATE_HTACCESS="Order deny,allow\nDeny from all";

    const DYN_DNS_ACCOUNT_UPDATE = FALSE;
    const DYN_DNS_ACCOUNT_USER = "";
    const DYN_DNS_ACCOUNT_PASSWORD = "";
    const DYN_DNS_ACCOUNT_HOST = "";

    const UPDATE_STATE_GOOD = "good ";
    const UPDATE_STATE_BAD = "badauth";

    protected $storageData;

    /**
     * initialize the storage
     */
    public function __construct(){
        if(file_exists(realpath(self::STORAGE_FILE))){
            $this->storageData = parse_ini_file(self::STORAGE_FILE,true);
            if(!is_array($this->storageData) || !isset($this->storageData["data"])){
                $this->resetStorageFile();
                $this->throwError("Wrong Ini-File-Structure",true);
            }
        }else{
            $this->resetStorageFile();
            $this->throwError("Installation finished. Please set your values in ".self::STORAGE_FILE,true);
        }
    }

    /**
     * Run the fake dynamic dns behaviour
     */
    public function run(){
        if(isset($_REQUEST['pass']) && isset($_REQUEST['ip'])){
            $this->updateIP($_REQUEST['pass'],$_REQUEST['ip']);
        }else{
            $this->proxy();
        }
    }

    /**
     * proxy the the request via header location
     * if you add a '/?' to your uri you will be relocated to your IP+everything behind the the ? so even using a port is possible
     * if you do not add a '/?' you will only receive a plan text with the dynamic ip
     */
    public function proxy(){
        $uri=$_SERVER["REQUEST_URI"];
        $pos=strpos($uri, '/?');
        if ($pos === false){
            # We do not have a path to proxy to, so just return ip for manual handling
            die($this->storageData["data"]["ip"]);
        }else{
            # There is something to proxy
            $uri_split = explode("/?",$uri);
            $url = "http://".$this->storageData["data"]["ip"].$uri_split[1];
            header("Location: $url");
        }
        return true;
    }

    /**
     * updates the ip to the ini file for further comparison
     * @param string $password
     * @param string $ip
     * @return string
     */
    public function updateIP($password,$ip){
        if($password != $this->storageData['data']['password']){
            $this->throwError("Wrong Password! Updateing IP impossible");
            return false;
        }
        if(!$this->writeIniValue("data","ip",$ip)){
            echo self::UPDATE_STATE_BAD;
            return false;
        }
        if(self::DYN_DNS_ACCOUNT_UPDATE){
            $dynDnsAccountUpdateState = $this->updateDynDnsAccount($ip);
            if($dynDnsAccountUpdateState == self::UPDATE_STATE_BAD){
                $this->throwError("Updating DynDnsAccountFailed");
            }
        }
        $this->writeIniValue("log","last-ip-change",date("Y/m/d H:i:s"));
        echo self::UPDATE_STATE_GOOD.$ip;
        return true;
    }

    /**
     * @param string $ip
     * @return string
     */
    public function updateDynDnsAccount($ip){
        $path = '/nic/update?hostname='.self::DYN_DNS_ACCOUNT_HOST.'&myip='.$ip.'&wildcard=NOCHG&mx=NOCHG&backmx=NOCHG';

        $fSocketHandle = fsockopen(self::DYN_DNS_ACCOUNT_HOST, 80);
        fputs($fSocketHandle, "GET $path HTTP/1.1\r\n");
        fputs($fSocketHandle, "Host: ".self::DYN_DNS_ACCOUNT_HOST."\r\n");
        fputs($fSocketHandle, "Authorization: Basic ".base64_encode(self::DYN_DNS_ACCOUNT_USER.':'.self::DYN_DNS_ACCOUNT_PASSWORD)."\r\n");
        fputs($fSocketHandle, "User-Agent: jau.cc - FRITZ!Box Fon WLAN 7140 Annex A - 39.04.59\r\n");
        fputs($fSocketHandle, "Connection: close\r\n\r\n");

        $fileSocketResult = "";
        while(!feof($fSocketHandle)){
            $fileSocketResult .= fgets($fSocketHandle, 128);
        }
        fclose($fSocketHandle);

        if (false === strpos($fileSocketResult, 'good '.$ip)){
            return self::UPDATE_STATE_BAD;
        }else{
            return self::UPDATE_STATE_GOOD.$ip;
        }
    }

    /**
     * Creates a new ini file with all key's needed
     */
    private function resetStorageFile(){
        $newFile = self::STORAGE_FILE_TEMPLATE_HEADER.self::STORAGE_FILE_TEMPLATE_BODY.self::STORAGE_FILE_TEMPLATE_FOOTER;
        if(!file_exists(realpath($newFile))){
            $dirName = pathinfo(self::STORAGE_FILE,PATHINFO_DIRNAME);
            if(!is_dir($dirName)){
                mkdir($dirName,"640",true);
            }
        }
        if(false === @file_put_contents(self::STORAGE_FILE,$newFile)){
            die("Could not reset storage file");
        }else{
            $dirName = pathinfo(self::STORAGE_FILE,PATHINFO_DIRNAME);
            file_put_contents($dirName."/.htaccess",self::STORAGE_FILE_TEMPLATE_HTACCESS);
        }
    }

    /**
     * Throws an error and logs it into the ini as last error event
     */
    private function throwError($message = "",$die = false){
        $this->writeIniValue("log","last-error",$message," [".date("Y/m/d H:i:s")."]");
        if($die){
            die($message);
        }
    }

    /**
     * Writes a ini value to the ini file in a certain section
     * @param string $group
     * @param string $key
     * @param string $value
     * @return bool
     */
    private function writeIniValue($group,$key,$value){
        $ini = file_get_contents(self::STORAGE_FILE);
        $groupPattern = "@\[{$group}\][^\[]*@";
        if(1 === preg_match($groupPattern,$ini,$iniGroups)){
            $iniGroup = $iniGroups[0];
            $iniGroup = preg_replace("@{$key}.*?$@m",$key."=".$value,$iniGroup);
            $ini = preg_replace($groupPattern,$iniGroup,$ini);
            file_put_contents(self::STORAGE_FILE,$ini);
            return true;
        }
        return false;
    }

}