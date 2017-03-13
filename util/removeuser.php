<?php

require_once __DIR__ . '/../include/DBService.php';

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
