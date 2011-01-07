<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');

if ($argc == 2) {

    $uid = $argv[1];
    
    if (strlen($uid) > 0) {
        $dbs = new dbservice();

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

function printStatus($status) {
    echo "status = " . $status . " = " . 
        array_search($status,dbservice::$STATUS) . "\n";
}

function printUser($dbs) {
    echo "uid = $dbs->user_uid\n";
    echo "first_name = $dbs->first_name\n";
    echo "last_name = $dbs->last_name\n";
    echo "remote_user = $dbs->remote_user\n";
    echo "idp = $dbs->idp\n";
    echo "idp_display_name = $dbs->idp_display_name\n";
    echo "email = $dbs->email\n";
    echo "distinguished_name = $dbs->distinguished_name\n";
    echo "serial_string = $dbs->serial_string\n";
}

?>
