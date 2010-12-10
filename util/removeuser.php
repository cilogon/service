<?php

require_once('../include/autoloader.php');
require_once('../include/util.php');

if ($argc == 2) {

    $uid = $argv[1];

    if (strlen($uid) > 0) {
        $dbs = new dbservice();
        $dbs->removeUser($uid);
        $status = $dbs->status;
        echo "status = $status = " . array_search($status,dbservice::$STATUS) .
            "\n";
    }
} else {
    echo "Usage: " . $argv[0] . " UID\n";
    echo "    where UID is a database user identifier\n";
}

?>
