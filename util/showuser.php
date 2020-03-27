<?php

set_include_path(
    '/var/www/html' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/config' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/vendor/cilogon/service-lib/src/Service' . PATH_SEPARATOR .
    '.'
);

require_once 'config.php';
include_once 'config.secrets.php';
require_once 'DBService.php';
require_once 'Util.php';

use CILogon\Service\DBService;

if ($argc == 2) {
    $user_uid = $argv[1];

    if (strlen($user_uid) > 0) {
        $dbs = new DBService();

        $dbs->getUser($user_uid);
        $status = $dbs->status;
        printStatus($status);
        if (!($status & 1)) { // STATUS_OK codes are even
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
    echo "user_uid = $dbs->user_uid\n";
    foreach (DBService::$user_attrs as $value) {
        echo "$value = " . $dbs->$value . "\n";
    }
    echo "distinguished_name = $dbs->distinguished_name\n";
    echo "serial_string = $dbs->serial_string\n";
    echo "create_time = $dbs->create_time\n";
}
