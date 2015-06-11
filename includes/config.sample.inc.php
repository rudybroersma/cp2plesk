<?php
define("VERSION", 2);

/* Prior to running migration this script sets the password policy to low. Define your default setting here.
 * after all migration commands have ran it will set the password policy to the value defined here.
 *
 * Available options:
 * very_weak|weak|medium|strong|very_strong
 */

define("PW_POLICY", "medium");

define("IPv4", "83.137.145.8");
define("IPv6", "2a01:1b0:7999:402::8");

/*
 * Here you can define an API call (which is ran using CURL) to update the DNS
 * servers for migrated domains. This can be for example a call to WHMCS
 * API system or a API call to your domain registry.
 * 
 * NS_API_DOUPDATE: Set TRUE to do API calls. False to not do any CURL requests.
 * NS_API_UP: HTTP Basic Auth username/password devided by colon
 * NS_API_DATA: HTTP POST data to send
 * NS_API_URL: HTTP URL to use.
 * 
 * NS_OUR_CONTROL: Domains matching these regexps as DNS are changed
 *  * 
 * The NS_API_DATA accepts the following parameters:
 * #DOMAIN# - Is replaced with the domain name.
 */
define("NS_API_DOUPDATE", TRUE);
define("NS_API_UP", "username:password");
define("NS_API_PASS", "password");
define("NS_API_DATA", "domain=#DOMAIN#&ns1=ns1.example.com&ns2=ns2.example.com");
define("NS_API_URL", "http://myregistry.example.com/api/changens");
define("NS_OUR_CONTROL", serialize(array('/example.com/', '/myisp.eu/')));

/*
 * Show debugging output
 */
define("DEBUG", FALSE);

//hardcoded service plan is now removed in favor of passing the service plan number as parameter.
//define("SERVICE_PLAN", "Name of your service plan");

define("BACKUP_PATH", "/var/www/vhosts/knutsel.tozz.nl/httpdocs/cp2plesk/unpack/");
define("IMAPSYNC_PATH", "/opt/imapsync/imapsync");

// You can grab a copy of imapsync from https://github.com/imapsync/imapsync
// Debian deps: apt-get install libmail-imapclient-perl

define("MAIL_FROM_ADDR", "Your e-mail address");
define("MAIL_FROM_NAME", "Your name");
define("SEND_MAIL", false); // Do not send any email, use this for testing.

/* Constants cannot be arrays, so we serialize them */
define("IGNORE_DB_NAMES", serialize(array("db_collation", "mysql", "psa", "da", "horde", "squirrelmail"))); // Databases to ignore.
define("IGNORE_DB_USERS", serialize(array("admin", "root", "da_admin", "db_collation"))); // Database usernames to ignore.

define("IGNORE_SITES", serialize(array("default", "sharedip", "suspended")));

/* Valid fields for mail_body:
 * #USERNAME#
 * #PASSWORD#
 * #DOMAIN#
 * #MAIL_FROM_NAME#
 */

define("MAIL_SUBJECT", "New login details");

define("MAIL_BODY", 
        "Hello,
            
Your domain has been migrated to a new server. As a result of this migration your login details have changed. We hereby send you your new credentials:

Control Panel: http://#DOMAIN#:8880/
Username: #USERNAME#
Password: #PASSWORD#

FTP: ftp://ftp.#DOMAIN#/
Username: #USERNAME#
Password: #PASSWORD#

Please let us know if you experience any difficulties.

Regards,
#MAIL_FROM_NAME#
            ");
?>
