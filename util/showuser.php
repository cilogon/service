<?php

set_include_path(
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/config' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/include' . PATH_SEPARATOR . 
    '.'
);

require_once 'DBService.php';

use CILogon\Service\DBService;

if ($argc == 2) {
    $uid = $argv[1];

    if (strlen($uid) > 0) {
        $dbs = new DBService();

        $dbs->getUser($uid);
        $status = $dbs->status;
        printStatus($status);
        if (!($status & 1)) { // STATUS_OK codes are even
            printUser($dbs);
        }

        $dbs->clear();
        $dbs->getLastArchivedUser($uid);
        $status = $dbs->status;
        if (!($status & 1)) { // STATUS_OK codes are even
            echo "----- Last Archived User Information -----\n";
            printStatus($status);
            printUser($dbs);
        }
    }
} else {
    echo "Usage: " . $argv[0] . "  UID\n";
    echo "    where UID is a database user identifier\n";
}

function printStatus($status)
{
    echo "status = " . $status . " = " .
        array_search($status, DBService::$STATUS) . "\n";
}

function printUser($dbs)
{
    echo "uid = $dbs->user_uid\n";
    echo "first_name = $dbs->first_name\n";
    echo "last_name = $dbs->last_name\n";
    echo "display_name = $dbs->display_name\n";
    echo "remote_user = $dbs->remote_user\n";
    echo "idp = $dbs->idp\n";
    echo "idp_display_name = $dbs->idp_display_name\n";
    echo "email = $dbs->email\n";
    echo "eppn = $dbs->eppn\n";
    echo "eptid = $dbs->eptid\n";
    echo "open_id = $dbs->open_id\n";
    echo "oidc = $dbs->oidc\n";
    echo "distinguished_name = $dbs->distinguished_name\n";
    echo "serial_string = $dbs->serial_string\n";
    echo "two_factor = $dbs->two_factor\n";
    echo "affiliation = $dbs->affiliation\n";
    echo "ou = $dbs->ou\n";
}
