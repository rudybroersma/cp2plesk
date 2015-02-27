<?php
include("includes/read.php");
include("includes/config.inc.php");
include("includes/plesk.class.php");
include("includes/generic.class.php");

$cp = new CPanel(BACKUP_PATH);
$g = new Generic();

if (VERSION != 1) {
    die("Version mismatch. You need to update your configuration file\n");
};

if (isset($argv[1])) {
  $serviceplan = $argv[1];
} else {
  $serviceplan = 0;
};

$plesk = new Plesk();
$sp = $plesk->getServicePlans();

if (array_key_exists($serviceplan, $sp)) {
  $valid_serviceplan = true;
} else {
  $valid_serviceplan = false;
};

if ($valid_serviceplan === false) {
  echo "Invalid serviceplan given. Please pass the serviceplan number as parameter (eg. php index.php 5): \n\n";
  foreach($sp as $plan) {
    echo $plan['id'] . ": " . $plan['name'] . "\n";
  };
 
  exit;
} else {
  $serviceplan_name = $sp[$serviceplan]['name'];
};

$username = $cp->userdata["USER"];
$password = $g->generatePassword();
$acctemail = $cp->userdata["CONTACTEMAIL"];
$domain = $cp->mainDomain;

# CREATE CUSTOMER AND SUBSCRIPTION #
echo "# Control Panel: http://www." . $domain . ":8880\n";
echo "# Username: " . $username . "\n";
echo "# Password: " . $password . "\n";
echo "#\n";
echo "# FTP: ftp://ftp." . $domain . "/\n";
echo "# Username: " . $username . "\n";
echo "# Password: " . $password . "\n";
echo "\n";
echo "/opt/psa/bin/server_pref -u -min_password_strength very_weak\n";
echo "/opt/psa/bin/customer -c $username -email $acctemail -name $username -passwd $password\n";
echo "/opt/psa/bin/subscription -c $domain -owner $username -service-plan \"$serviceplan_name\" -ip " . IPv4 . "," . IPv6 . " -login $username -passwd $password -seo-redirect none\n";
echo "\n";
echo "/usr/bin/find " . $cp->base . "/homedir/public_html/ -type f -print | xargs -I {} sed -i \"s@" . $cp->homedir . "/public_html@/var/www/vhosts/" . $domain . "/httpdocs@g\" {}\n";
echo "/usr/bin/find " . $cp->base . "/homedir/public_html/ -type f -print | xargs -I {} sed -i \"s@" . $cp->homedir . "/www@/var/www/vhosts/" . $domain . "/httpdocs@g\" {}\n";
echo "mkdir " . $cp->base . "/homedir/public_html/webmail/\n";

echo "echo \"Redirect 301 /webmail http://webmail." . $domain . "/\" > " . $cp->base . "/homedir/public_html/webmail/.htaccess\n";
echo "cd " . $cp->base . "/homedir/public_html && /usr/bin/lftp --no-symlinks -c 'set ftp:ssl-allow false && open ftp://$username:$password@localhost && cd httpdocs && mirror -R .'\n";

foreach($cp->parkedDomains as $alias => $value) {
# Do not use domalias, as we cannot create mail addresses under aliases.
#    echo "/opt/psa/bin/domalias -c $alias -domain $domain\n";
    echo "/opt/psa/bin/site -c $alias -hosting true -hst_type phys -webspace-name $domain -www-root httpdocs -seo-redirect none\n";
}

foreach($cp->addOnDomains as $key => $value) {
    #echo $key;
    $dest = $cp->subDomains[$value];
    $dest = ereg_replace("^public_html/", "httpdocs/", $dest);

    echo "/opt/psa/bin/site -c $key -hosting true -hst_type phys -webspace-name $domain -www-root $dest -seo-redirect none\n";
    echo "mkdir " . $cp->base . "/" . $dest . "/webmail/\n";
    echo "echo \"Redirect 301 /webmail http://webmail." . $key . "/\" > " . $cp->base . "/" . $dest . "/webmail/.htaccess\n";
    echo "chgrp psaserv /var/www/vhosts/" . $domain . "/" . $dest;
};

/* addOnDomains must be ran first, as subdomains will create subs under addondomains */
foreach($cp->subDomains as $key  => $value) {
    $split = explode("_", $key);
    $subdomain = $split[0];
    $domain = $split[1];
    $value = ereg_replace("^public_html/", "httpdocs/", $value);
    /* Create real domains instead of subdomains. cPanel allows e-mail accounts within subdomains. Plesk does not. Thus, create domain instead of subdomain */
#    echo "/opt/psa/bin/subdomain -c " . $subdomain . " -domain " . $domain . " -www-root " . $value . " -php true\n";
    echo "/opt/psa/bin/site -c " . $subdomain . "." . $domain . " -hosting true -hst_type phys -webspace-name $domain -www-root " . $value . " -seo-redirect none\n";
    echo "chgrp psaserv /var/www/vhosts/" . $domain . "/" . $value;
}
$mailDomains = array_merge(
                array_keys($cp->addOnDomains), 
                array_keys($cp->parkedDomains)
                );

$mailDomains[] = $cp->mainDomain;

$createdAccounts = array();

foreach ($cp->mailAccounts as $domain => $value) {
    if ((array_search($domain, $mailDomains)) !== FALSE) { /* array_search can return 0 which matches false if we dont do type checking. Therefor !== IS MANDATORY */
        foreach ($cp->mailAccounts[$domain]["forwards"] as $mailbox => $forward) {
            if ($mailbox == "*") {
                if (strpos($forward, ":blackhole")) { $forward = "reject"; };
                if (strpos($forward, ":fail")) { $forward = "reject"; };
                echo "/opt/psa/bin/domain_pref -u " . $domain . " -no_usr " . $forward . "\n";
            } else {
                $forward = preg_replace('/\s+/', '', $forward); // remove all spaces
                $forward = preg_replace('/,$/', '', $forward); // remove all commas
                
                if (!in_array($mailbox, $createdAccounts)) { 
                    echo "/opt/psa/bin/mail -c " . $mailbox . " -mailbox false -forwarding true -forwarding-addresses add:" . $forward . "\n";
                    echo "/opt/psa/bin/spamassassin -u " . $mailbox . " -status true -hits 5 -action del\n";
                } else {
                    echo "/opt/psa/bin/mail -u " . $mailbox . " -forwarding true -forwarding-addresses add:" . $forward . "\n";
                }
                array_push($createdAccounts, $mailbox);
            };
        }
        foreach ($cp->mailAccounts[$domain]["accounts"] as $mailbox => $data) {
            if (isset($data['crypt'])) {
                $crypt = $data['crypt'];
            } else {
                $crypt = $password;
            }
            
            if (!in_array($mailbox . "@" . $domain, $createdAccounts)) { 
                echo "/opt/psa/bin/mail -c " . $mailbox . "@" . $domain . " -mailbox true -passwd '" . $crypt . "' -passwd_type encrypted\n";
                echo "/opt/psa/bin/spamassassin -u " . $mailbox . "@" . $domain . " -status true -hits 5 -action del\n";
                echo "cp -R " . $cp->base . "/homedir/mail/" . $domain . "/" . $mailbox .  "/. /var/qmail/mailnames/" . $domain . "/" . $mailbox . "/Maildir/\n";
                echo "chown -R popuser:popuser /var/qmail/mailnames/" . $domain . "/" . $mailbox . "\n";
            } else {
                echo "/opt/psa/bin/mail -u " . $mailbox . "@" . $domain . " -mailbox true -passwd '" . $crypt . "' -passwd_type encrypted\n";
            }
            array_push($createdAccounts, $mailbox . "@" . $domain);
        }
    }
}

#var_dump($cp->databases);

foreach ($cp->databases["MYSQL"]["dbs"] as $db => $value) {
    echo "/opt/psa/bin/database -c " . $db . " -domain " . $cp->mainDomain . " -type mysql\n";
    echo "/bin/sed -i \"s@/home/" . $username . "/public_html@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" " . $cp->base . "/mysql/" . $db . ".sql\n";
    echo "/bin/sed -i \"s@/home/" . $username . "/www@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" " . $cp->base . "/mysql/" . $db . ".sql\n";
    echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` " . $db . " < " . $cp->base . "/mysql/" . $db . ".sql\n";
}

foreach ($cp->databases["MYSQL"]["dbusers"] as $user => $value) {
    if ($value['pw'] == "") { $value['pw'] = $password; };
    echo "/opt/psa/bin/database --create-dbuser " . $user . " -domain " . $cp->mainDomain . " -passwd '" . $value['pw'] . "' -type mysql -any-database\n";
    echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"UPDATE mysql.user SET Password = '" . $value['pw'] . "' WHERE User = '" . $user . "'\"\n";
    echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"FLUSH PRIVILEGES\"\n";

}

echo "/opt/psa/bin/server_pref -u -min_password_strength " . PW_POLICY . "\n";

echo "/bin/sed -i \"s@/home/" . $username . "/public_html@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" " . $cp->base . "/cron/" . $username . "\n";
echo "/bin/sed -i \"/^SHELL.*/d\" " . $cp->base . "/cron/" . $username . "\n";
echo "crontab -u $username " . $cp->base . "/cron/" . $username . "\n";
exit;


// Send mail to customer
//$other->sendMail($domain, $username, $password, $backup->getEmail());
#$other->sendMail($domain, $username, $password, "tozz@kijkt.tv");

// DO NOT FORGET TO DO SOME DNS MAGIC!


