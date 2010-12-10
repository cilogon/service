<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');

if (($argc == 2) || ($argc == 3)) {

    $uid = $argv[1];
    $last = false;
    if ($argc == 3) {
        $last = true;
    }
    
    if (strlen($uid) > 0) {
        $dbs = new dbservice();
        if ($last) {
            $dbs->getLastArchivedUser($uid);
        } else {
            $dbs->getUser($uid);
        }
        $status = $dbs->status;
        echo "status = " . $status . " = " . 
            array_search($status,dbservice::$STATUS) . "\n";
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
} else {
    echo "Usage: " . $argv[0] . "  UID [last]\n";
    echo "    where UID is a database user identifier\n";
    echo "          [last] if set to 1 returns 'last archived' user info\n";
}

?>
