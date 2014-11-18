<?php

class CPanel {
    private $ssh;
    private $username;

    private $usersconfig;
    private $userdata;
    
    private $contact;
    private $homedir;
    
    private $addOnDomains;
    private $parkedDomains;
    private $subDomains;
    private $mainDomain;
            
    public function __construct($ssh, $username) {
        $this->ssh = $ssh;
        $this->username = $username;
        
        $this->setPrimaryDomain();
        $this->setHomeDir();
        $this->setContactEmail();
        $this->setDomainList();
        
        var_dump($this->userdata);
        var_dump($this->usersconfig);
        
    }

    private function setPrimaryDomain() {
        $userconfig = $this->ssh->run("cat /var/cpanel/users/" . $this->username);
        
        foreach($userconfig as $line) {
            $line = explode("=", $line);
            if (isset($line[1])) {
              $config[$line[0]] = $line[1];
            };
        }
        
        $this->usersconfig = $config;
        $this->primary = $config['DNS'];
        
    }
    
    private function setHomeDir() {
        $homedir = $this->ssh->run("cat /var/cpanel/userdata/" . $this->username . "/" . $this->getPrimaryDomain());

        foreach($homedir as $line) {
            $line = explode(": ", $line);
            if (isset($line[1])) {
              $config[$line[0]] = $line[1];
            };
        }

        $this->userdata = $config;
        $this->homedir = $config['homedir'];
    }
    
    private function setContactEmail() {
        $contact = $this->ssh->run("cat " . $this->homedir . "/.contactemail");
        $this->contact = $contact;
    }
    
    public function getPrimaryDomain() {
        return $this->primary;
    }
    
    public function getContactEmail() {
        return $this->contact;
    }
    
    private function setDomainList() {
        $addon = array();
        $parked = array();
        $sub = array();
        
        $read = $this->ssh->run("cat /var/cpanel/userdata/" . $this->username . "/main");
        foreach($read as $line) {
            if (strstr($line, "addon_domains: ")) {
                $active = "addon";
            }
            if ($line == "parked_domains: ") {
                $active = "parked";
            }
            if ($line == "sub_domains: ") {
                $active = "sub";
            }
            if (strstr($line, "main_domain: ")) {
                $main = substr($line, 13, strlen($line));
            }
            
            if (isset($active) && $active == "addon" && substr($line, 0, 2) == "  ") {
                echo "addon";
                $split = explode(": ", $line);
                $split[0] = trim($split[0]);
                $temp[$split[0]] = trim($split[1]);
                array_push($$active, $temp);
            }
            
            if (substr($line, 0, 4) == "  - ") {
                array_push($$active, substr($line, 4, strlen($line)));
            }
        }

        $this->addOnDomains = $addon;
        $this->parkedDomains = $parked;
        $this->subDomains = $sub;
        $this->mainDomain = $main;
    }
}