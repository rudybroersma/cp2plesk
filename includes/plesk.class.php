<?php
/* plesk.class.php: cp2plesk */

class Plesk {
    
    private function getUsername() {
        return "admin";
    }
    
    private function getPassword() {
        $password = exec("cat /etc/psa/.psa.shadow");
        return $password;
    }
    
    public function isValidReseller($login) {
        $plesk_db = mysqli_connect("localhost", $this->getUsername(), $this->getPassword());
        mysqli_select_db("psa", $plesk_db);

        $plesk_query = "SELECT * FROM clients WHERE type = 'reseller' and login = '" . mysqli_real_escape_string($login) . "'";
        $plesk_result = mysqli_query($plesk_query);
        
        if (mysqli_num_rows($plesk_result) == 1) {
            return TRUE;
        } else {
            return FALSE;
        };
    }
    
    public function getResellers() {
        $resellers = [];
        
        $plesk_db = mysqli_connect("localhost", $this->getUsername(), $this->getPassword());
        mysqli_select_db("psa", $plesk_db);

        $plesk_query = "SELECT * FROM clients WHERE type = 'reseller'";
        
        $plesk_result = mysqli_query($plesk_query);

        while($row = mysqli_fetch_assoc($plesk_result)) {
          $resellers[$row['login']] = $row;
        }

        return $resellers;
    }
    
    public function getServicePlans($reseller = FALSE) {
        $plesk_db = mysqli_connect("localhost", $this->getUsername(), $this->getPassword());
        #mysqli_select_db("psa", $plesk_db);
        mysqli_select_db($plesk_db, "psa");

        if ($reseller == FALSE) {
          $plesk_query = "SELECT id, name FROM Templates WHERE type = 'domain' AND owner_id = 1 AND name != 'Admin Simple'";
        } else {
          $plesk_query = "SELECT id, name FROM Templates WHERE type = 'domain' AND owner_id = (SELECT id FROM clients WHERE type = 'reseller' AND login = '" . mysqli_real_escape_string($reseller) . "')";
        }
        
        $plesk_result = mysqli_query($plesk_db, $plesk_query);

        $sp = array();
        while($row = mysqli_fetch_assoc($plesk_result)) {
          $sp[$row['id']] = $row;
        }

        return $sp;
    }
}
?>
