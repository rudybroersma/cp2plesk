<?php

class SSH {

    private $hostname;

    public function __construct($hostname) {
        $this->hostname = $hostname;
    }

    function run_cmd($user_name, $keyfilename, $ssh_command) {
        $ssh_host = $this->hostname;
        
        $methods = array(
            'kex' => 'diffie-hellman-group1-sha1',
            'client_to_server' => array(
                'crypt' => 'aes256-cbc',
                'comp' => 'none',
                'mac' => 'hmac-sha1'),
            'server_to_client' => array(
                'crypt' => 'aes256-cbc',
                'comp' => 'none',
                'mac' => 'hmac-sha1'));

        $output = array();
        $connection = ssh2_connect($ssh_host, 22, $methods);
        if (!$connection)
            die('Connection failed');
        if (ssh2_auth_pubkey_file($connection, $user_name, $keyfilename . ".pub", $keyfilename, 'test')) {
            #echo "Public Key Authentication Successful as user: $user_name";
        } else {
            throw new Exception('Public Key Authentication Failed');
        }

        $stream = ssh2_exec($connection, $ssh_command);
        stream_set_blocking($stream, true);
        while (!feof($stream)) {
#        $line = trim(stream_get_line($stream, 1024, "\n"));
            $line = fgets($stream);
            if ($line === FALSE)
                break;
            $output[] = trim($line, "\n");
        }
        unset($stream);

        return $output;
    }

    function run($command) {
        $user_name = "root";
        $keydir = "/root/.ssh/";
        $search_string = 'needle';
        $keyfilename = $keydir . 'id_dsa';
        $ssh_host = "himalia.onyx-ict.eu";

        return $this->run_cmd($user_name, $keyfilename, $command);
    }

// Main Code
#$ssh_command = 'ls; exit';
}
?>