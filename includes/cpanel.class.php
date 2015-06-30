<?php

ini_set("auto_detect_line_endings", true);

class CPanel {
    /* Domains */

    public $addOnDomains;
    public $subDomains;
    public $mainDomain;
    public $parkedDomains;
    public $allDomains = array();

    /* Mail */
    public $mailAccounts;

    /* MySQL */
    public $databases;
    public $homedir;

    /* Misc */
    public $base;
    public $userdata;
    public $userconfig;

    public function __construct($dir) {
        $this->base = $dir;

        $this->fetchData();
    }

    private function readFile($file) {
        $output = "";

        $handle = @fopen($file, "r");
        if ($handle) {
            while (($buffer = fgets($handle)) !== false) {
                $output[] = trim($buffer);
            }
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        }

        if ($output == "") {
            return FALSE;
        } else {
            return $output;
        }
    }

    private function splitFile($file, $splitter = "=") {
        $output = array();
        $input = $this->readFile($file);

        if ($input != FALSE) {
            foreach ($input as $line) {
                $split = explode($splitter, $line);
                $output[trim($split[0])] = (isset($split[1]) ? trim($split[1]) : "");
            }
        } else {
            return array();
        }

        return $output;
    }

    private function readAddOnDomains($file) {
        $this->addOnDomains = $this->splitFile($file);
    }

    private function readSubDomains($file) {
        $this->subDomains = $this->splitFile($file);

        $main[$this->mainDomain] = $this->mainDomain;
        $all = array_merge($this->addOnDomains, $this->parkedDomains);
        $all = array_merge($all, $main);

        /* Here we replace _ with . in subdomains.
         * Afterwards, we replace . with _ if it's prefixed with a domain
         */
        foreach ($this->subDomains as $key => $value) {
            unset($this->subDomains[$key]);
            foreach ($all as $domain) {
                $key = str_replace("_", ".", $key);
                $key = str_replace("." . $domain, "_" . $domain, $key);
            };
            $this->subDomains[$key] = $value;
        }
    }

    private function readParkedDomains($file) {
        $this->parkedDomains = $this->splitFile($file);
    }

    private function readMainDomain($file) {
        $needle = "main_domain: ";
        $userdata = $this->readFile($file);
        foreach ($userdata as $line) {
            if (substr($line, 0, strlen($needle)) == $needle) {
                $split = explode(": ", $line);
                $this->mainDomain = trim($split[1]);
            }
        }
    }

    private function readUserConfig() {
        $files = array();

        if ($handle = opendir($this->base . "/cp/")) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $files[] = $entry;
                }
            }
            closedir($handle);
        }

        if (count($files) > 1) {
            throw new Exception("Multiple usernames found in /cp/");
        }

        $this->userdata = $this->splitFile($this->base . "/cp/" . $files[0], "=");
    }

    private function readMail($file) {

        if (file_exists($file)) {
            $output = yaml_parse_file($file);
            #var_dump($output);
            foreach (array_keys($this->allDomains) as $domain) {
                if (isset($output[$domain])) {
                    foreach ($output[$domain]['accounts'] as $mailbox => $bla) {
                        #$mailbox = key($output[$domain]["accounts"]);
                        $pw = $this->readMailPassword($mailbox, $domain);

                        $output[$domain]["accounts"][$mailbox]["crypt"] = $pw;
                    }
                };
            }
        } else {
            $output = array();
        }
        $this->mailAccounts = $output;

        $this->readForwards();
    }

    private function readMailPassword($mailbox, $domain) {
        $output = $this->readFile($this->base . "/homedir/etc/" . $domain . "/shadow");

        foreach ($output as $line) {
            $split = explode(":", $line);
            if ($split[0] == $mailbox) {
                return $split[1];
            }
        }

        return false;
    }

    /* OLD: The dbmap.yaml scheme has been removed in cPanel 10.44 */
    /*
      private function readMySQL() {
          if (file_exists($this->base . "/meta/dbmap.yaml")) {
              $output = yaml_parse_file($this->base . "/meta/dbmap.yaml");
              foreach ($output["MYSQL"]["dbusers"] as $username => $data) {
                $pw = $this->readMySQLPassword($username);
                $output["MYSQL"]["dbusers"][$username]["pw"] = $pw;
              }

              $this->databases = $output;
      
          } else {
             $this->databases = array();
          }
      }
     * 
     */

    private function readMySQL() {
        $output = [];
        
        $file = $this->base . "/mysql.sql";
        if (file_exists($file)) {
            $handle = @fopen($file, "r");
            if ($handle) {
                while (($buffer = fgets($handle, 4096)) !== false) {
                    $buffer = stripslashes($buffer);
                    if (preg_match("/^GRANT USAGE ON/", $buffer) == 1) {
                        $buffer_split = preg_split("/('|`)/", $buffer);
                        if ($buffer_split[3] == "localhost") {
                            $output["MYSQL"]["dbusers"][$buffer_split[1]]["pw"] = $buffer_split[5];
                        }
                    }
                    
                    if (preg_match("/^GRANT (ALL|SELECT)/", $buffer) == 1) {
                        $buffer_split = preg_split("/('|`)/", $buffer);
                        $output["MYSQL"]["dbs"][$buffer_split[1]] = "";
                    }
                    
                    
                    $this->databases = $output;
                }
                fclose($handle);
            }
        } else {
            $this->databases = [];
        }
    }

    private function readMySQLPassword($username) {
        $output = $this->readFile($this->base . "/mysql.sql");

        foreach ($output as $key => $value) {
            if (strpos($value, $username) && strpos($value, "IDENTIFIED BY PASSWORD")) {
                $split = explode("'", $value);
                return $split[5];
            }
        }

        return "";
    }

    private function readForwards() {
        if (count($this->allDomains) == 0) {
            throw new Exception("allDomains need to be populated");
        };
        foreach ($this->allDomains as $domain => $value) {
            $this->mailAccounts[$domain]["forwards"] = $this->splitFile($this->base . "/va/" . $domain, ": ");
        }
    }

    private function readHomeDir() {
        $this->homedir = $this->readFile($this->base . "/homedir_paths")[0];
    }

    public function fetchData() {
        /* The order of reading domains is important for readSubDomains */

        $this->readMainDomain($this->base . "/userdata/main");
        $main[$this->mainDomain] = $this->mainDomain;

        $this->readAddOnDomains($this->base . "/addons");
        $this->readParkedDomains($this->base . "/pds");
        $this->readSubDomains($this->base . "/sds2");

        $this->allDomains = array_merge($this->addOnDomains, $this->subDomains);
        $this->allDomains = array_merge($this->allDomains, $this->parkedDomains);
        $this->allDomains = array_merge($this->allDomains, $main);

        $this->readMail($this->base . "/homedir/.cpanel/email_accounts.yaml");

        $this->readMySQL();
        $this->readUserConfig();
        $this->readHomeDir();
    }

}
