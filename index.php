<?php

/* index.php - cp2plesk */

include("includes/cpanel.class.php");
include("includes/config.inc.php");
include("includes/color.class.php");
include("includes/other.class.php");
include("includes/dns.class.php");
include("includes/plesk.class.php");
include("includes/generic.class.php");

$cp = new CPanel(BACKUP_PATH);
$g = new Generic();
$dns = new DNS(NS_API_DOUPDATE, NS_API_UP, NS_API_DATA, NS_API_URL, unserialize(NS_OUR_CONTROL), IPv4, IPv6, DEBUG);
$other = new Other(MAIL_FROM_ADDR, MAIL_FROM_NAME, SEND_MAIL, DEBUG);
$plesk = new Plesk();

if (VERSION != 2) {
    die("Version mismatch. You need to update your configuration file\n");
};

$arguments = $other->parseArguments($argv);

if (array_key_exists("reseller", $arguments)) {
    $reseller = $arguments['reseller'];
    if (!$plesk->isValidReseller($reseller)) {
        echo "Invalid reseller username given. Exiting...\n";
        exit;
    }
} else {
    $reseller = FALSE;
};

$sp = $plesk->getServicePlans($reseller);

if (array_key_exists("list-serviceplans", $arguments)) {
    foreach ($sp as $plan) {
        echo $plan['id'] . ": " . $plan['name'] . "\n";
    };

    exit;
}

if (array_key_exists("list-resellers", $arguments)) {
    echo "login\t\t\tname\t\tcompany\n\n";
    foreach ($plesk->getResellers() as $resellerName) {
        echo $resellerName['login'] . ":\t\tname: " . $resellerName['pname'] . "\tcompany: " . $resellerName['cname'] . "\n";
    };
    exit;
}

if (array_key_exists("list-username", $arguments)) {
    echo $cp->userdata["USER"] . "\n";
    exit;
}

if (array_key_exists("generate-password", $arguments)) {
    echo $g->generatePassword() . "\n";
    exit;
}

if (array_key_exists("list-email", $arguments)) {
    echo $acctemail = $cp->userdata["CONTACTEMAIL"] . "\n";
    exit;
}

if (array_key_exists("username", $arguments)) {
    $username = $arguments['username'];
} else {
    $username = $cp->userdata["USER"];
};

if (array_key_exists("password", $arguments)) {
    $password = $arguments['password'];
} else {
    $password = $g->generatePassword();
};

if (array_key_exists("list-domains", $arguments)) {
    # output a list of all domains in this backup
    echo $cp->mainDomain . "\n";
    foreach ($cp->addOnDomains as $domain) {
        echo $domain . "\n";
    }
    exit;
}

// pointers do not exists in cp
if (array_key_exists("list-pointers", $arguments)) {
    exit;
}

if (array_key_exists("list-aliases", $arguments)) {
    # output a list of all domains in this backup
    foreach ($cp->parkedDomains as $domain) {
        echo $domain . "\n";
    }
    exit;
}

if (array_key_exists("serviceplan", $arguments) && array_key_exists($arguments['serviceplan'], $sp)) {
    $valid_serviceplan = true;
    $serviceplan = $arguments['serviceplan'];
    $serviceplan_name = $sp[$serviceplan]['name'];
} else {
    echo "Invalid serviceplan given. Please pass the serviceplan number as parameter (eg. php index.php --serviceplan=5): \n\n";
    exit;
};


$acctemail = $cp->userdata["CONTACTEMAIL"];
$domain = $cp->mainDomain;

if (strlen(trim($acctemail)) == 0) {
    $acctemail = $username . "@" . $domain;
};

# CREATE CUSTOMER AND SUBSCRIPTION #
echo "# Control Panel: http://www." . $cp->mainDomain . ":8880\n";
echo "# Username: " . $username . "\n";
echo "# Password: " . $password . "\n";
echo "#\n";
echo "# FTP: ftp://ftp." . $cp->mainDomain . "/\n";
echo "# Username: " . $username . "\n";
echo "# Password: " . $password . "\n";
echo "\n";
echo "/opt/psa/bin/server_pref -u -min_password_strength very_weak\n";

if ($reseller == FALSE) {
  echo "/opt/psa/bin/customer -c $username -email $acctemail -name $username -passwd \"$password\"\n";
} else {
  echo "/opt/psa/bin/customer -c $username -email $acctemail -name $username -passwd \"$password\" -owner $reseller\n";
}

echo "/opt/psa/bin/subscription -c " . $cp->mainDomain . " -owner $username -service-plan \"$serviceplan_name\" -ip " . IPv4 . "," . IPv6 . " -login $username -passwd \"$password\" -seo-redirect none\n";
echo "\n";
echo "/usr/bin/find " . $cp->base . "/homedir/public_html/ -type f -print | xargs -I {} sed -i \"s@" . $cp->homedir . "/public_html@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" {}\n";
echo "/usr/bin/find " . $cp->base . "/homedir/public_html/ -type f -print | xargs -I {} sed -i \"s@" . $cp->homedir . "/www@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" {}\n";
echo "mkdir " . $cp->base . "/homedir/public_html/webmail/\n";
echo "chmod -R o+x /var/www/vhosts/" . $cp->mainDomain . "/httpdocs/\n";

echo "echo \"Redirect 301 /webmail http://webmail." . $cp->mainDomain . "/\" > " . $cp->base . "/homedir/public_html/webmail/.htaccess\n";
echo "cd " . $cp->base . "/homedir/public_html && /usr/bin/lftp -c 'set ftp:ssl-allow false && open ftp://$username:\"$password\"@localhost && cd httpdocs && mirror --no-symlinks -p -R .'\n";

foreach ($cp->parkedDomains as $alias => $value) {
# Do not use domalias, as we cannot create mail addresses under aliases.
#    echo "/opt/psa/bin/domalias -c $alias -domain $domain\n";
    echo "/opt/psa/bin/site -c $alias -hosting true -hst_type phys -webspace-name " . $cp->mainDomain . " -www-root httpdocs -seo-redirect none\n";

    foreach ($dns->getDNSChanges($cp->base . "/dnszones/" . $alias . ".db", $cp->userdata['IP']) as $dnschange) {
        echo $dnschange . "\n";
    }
}

foreach ($cp->addOnDomains as $key => $value) {
    $dest = $cp->subDomains[$value];
    $dest = preg_replace("/^public_html\//", "httpdocs/", $dest);

    echo "/opt/psa/bin/site -c $key -hosting true -hst_type phys -webspace-name " . $cp->mainDomain . " -www-root $dest -seo-redirect none\n";
    echo "mkdir " . $cp->base . "/" . $dest . "/webmail/\n";
    echo "echo \"Redirect 301 /webmail http://webmail." . $key . "/\" > " . $cp->base . "/" . $dest . "/webmail/.htaccess\n";
    echo "chgrp psaserv /var/www/vhosts/" . $cp->mainDomain . "/" . $dest . "\n";

    foreach ($dns->getDNSChanges($cp->base . "/dnszones/" . $key . ".db", $cp->userdata['IP']) as $dnschange) {
        echo $dnschange . "\n";
    }
};

/* addOnDomains must be ran first, as subdomains will create subs under addondomains */
foreach ($cp->subDomains as $key => $value) {
    $split = explode("_", $key);
    $subdomain = $split[0];
    $domain = isset($split[1]) ? $split[1] : "";
    $value = ereg_replace("^public_html/", "httpdocs/", $value);

    $createSubdomain = preg_replace("/\.$/", "", $subdomain . "." . $domain);

    /* Create real domains instead of subdomains. cPanel allows e-mail accounts within subdomains. Plesk does not. Thus, create domain instead of subdomain */
#    echo "/opt/psa/bin/subdomain -c " . $subdomain . " -domain " . $domain . " -www-root " . $value . " -php true\n";
    echo "/opt/psa/bin/site -c " . $createSubdomain . " -hosting true -hst_type phys -webspace-name " . $cp->mainDomain . " -www-root " . $value . " -seo-redirect none\n";
    echo "chgrp psaserv /var/www/vhosts/" . $domain . "/" . $value . "\n";
}
$mailDomains = array_merge(
        array_keys($cp->addOnDomains), array_keys($cp->parkedDomains)
);

$mailDomains[] = $cp->mainDomain;

$createdAccounts = array();

// create system account.
echo "/opt/psa/bin/mail -c " . $cp->userdata["USER"] . "@" . $cp->mainDomain . " -mailbox true -passwd '" . $password . "' -passwd_type plain\n";

foreach ($cp->mailAccounts as $domain => $value) {
    if ((array_search($domain, $mailDomains)) !== FALSE) { /* array_search can return 0 which matches false if we dont do type checking. Therefor !== IS MANDATORY */
        foreach ($cp->mailAccounts[$domain]["forwards"] as $mailbox => $forward) {

            if (strpos($forward, ":fail") > -1) {
                $forward = "reject";
            };
            if (strpos($forward, ":fail") > -1) {
                $forward = "reject";
            };
            if (strpos($forward, "@") == FALSE) {
                $forward = $forward . "@" . $cp->mainDomain;
            };

            if ($mailbox == "*") {
                echo "/opt/psa/bin/domain_pref -u " . $domain . " -no_usr " . $forward . "\n";
            } else {
                $forward = preg_replace('/\s+/', '', $forward); // remove all spaces
                $forward = preg_replace('/,$/', '', $forward); // remove all commas

                if (!in_array($mailbox, $createdAccounts)) {
                    echo "/opt/psa/bin/mail -c '" . $mailbox . "' -mailbox false -forwarding true -forwarding-addresses add:" . $forward . "\n";
                    echo "/opt/psa/bin/spamassassin -u '" . $mailbox . "' -status true -hits 5 -action del\n";
                } else {
                    echo "/opt/psa/bin/mail -u '" . $mailbox . "' -forwarding true -forwarding-addresses add:" . $forward . "\n";
                }
                array_push($createdAccounts, $mailbox);
            };
        }

        if (isset($cp->mailAccounts[$domain]["accounts"])) {
            foreach ($cp->mailAccounts[$domain]["accounts"] as $mailbox => $data) {
                if (isset($data['crypt'])) {
                    $crypt = $data['crypt'];
                } else {
                    $crypt = $password;
                }

                if (!in_array($mailbox . "@" . $domain, $createdAccounts)) {
                    echo "/opt/psa/bin/mail -c '" . $mailbox . "@" . $domain . "' -mailbox true -passwd '" . $crypt . "' -passwd_type encrypted\n";
                    echo "/opt/psa/bin/spamassassin -u '" . $mailbox . "@" . $domain . "' -status true -hits 5 -action del\n";
                    echo "cp -R '" . $cp->base . "/homedir/mail/" . $domain . "/" . $mailbox . "/.' '/var/qmail/mailnames/" . $domain . "/" . $mailbox . "/Maildir/'\n";
                    echo "chown -R popuser:popuser '/var/qmail/mailnames/" . $domain . "/" . $mailbox . "'\n";
                } else {
                    echo "/opt/psa/bin/mail -u '" . $mailbox . "@" . $domain . "' -mailbox true -passwd '" . $crypt . "' -passwd_type encrypted\n";
                }
                array_push($createdAccounts, $mailbox . "@" . $domain);
            }
        }
    }
}

if (isset($cp->databases["MYSQL"]["dbs"])) {
    foreach ($cp->databases["MYSQL"]["dbs"] as $db => $value) {
        echo "/opt/psa/bin/database -c " . $db . " -domain " . $cp->mainDomain . " -type mysql\n";
        echo "/bin/sed -i \"s@/home/" . $username . "/public_html@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" " . $cp->base . "/mysql/" . $db . ".sql\n";
        echo "/bin/sed -i \"s@/home/" . $username . "/www@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" " . $cp->base . "/mysql/" . $db . ".sql\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` " . $db . " < " . $cp->base . "/mysql/" . $db . ".sql\n";
    }

    foreach ($cp->databases["MYSQL"]["dbusers"] as $user => $value) {
        if ($value['pw'] == "") {
            $value['pw'] = $password;
        };
        echo "/opt/psa/bin/database --create-dbuser " . $user . " -domain " . $cp->mainDomain . " -passwd '" . $value['pw'] . "' -type mysql -any-database\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"UPDATE mysql.user SET Password = '" . $value['pw'] . "' WHERE User = '" . $user . "'\"\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"FLUSH PRIVILEGES\"\n";
    }
}

// Perform DNS changes for main domain
foreach ($dns->getDNSChanges($cp->base . "/dnszones/" . $cp->mainDomain . ".db", $cp->userdata['IP']) as $dnschange) {
    echo $dnschange . "\n";
}

echo "/opt/psa/bin/server_pref -u -min_password_strength " . PW_POLICY . "\n";

echo "/bin/sed -i \"s@/home/" . $username . "/public_html@/var/www/vhosts/" . $cp->mainDomain . "/httpdocs@g\" " . $cp->base . "/cron/" . $username . "\n";
echo "/bin/sed -i \"/^SHELL.*/d\" " . $cp->base . "/cron/" . $username . "\n";
echo "crontab -u $username " . $cp->base . "/cron/" . $username . "\n";
exit;


// Send mail to customer
//$other->sendMail($cp->mainDomain, $username, $password, $backup->getEmail());
#$other->sendMail($cp->mainDomain, $username, $password, "tozz@kijkt.tv");

// DO NOT FORGET TO DO SOME DNS MAGIC!


