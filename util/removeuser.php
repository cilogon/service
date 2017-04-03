<?php

set_include_path(
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/config' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/vendor/cilogon/service-lib/src/Service' . PATH_SEPARATOR . 
    '.'
);

require_once 'DBService.php';

use CILogon\Service\DBService;

if ($argc == 2) {
    $uid = $argv[1];

    if (strlen($uid) > 0) {
        $dbs = new DBService();
        $dbs->removeUser($uid);
        $status = $dbs->status;
        echo "status = $status = " . array_search($status, DBService::$STATUS) .
            "\n";
    }
} else {
    echo "Usage: " . $argv[0] . " UID\n";
    echo "    where UID is a database user identifier\n";
}
