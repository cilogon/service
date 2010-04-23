<?php

require_once('../include/autoloader.php');
require_once('../include/util.php');

if ($argc == 2) {

    $uid = $argv[1];

    if (strlen($uid) > 0) {
        $store = new store();
        $store->perlobj->eval(
            '$removeduid = CILogon::Store->_removeUser(\''.$uid.'\');');
        echo "Removed uid = " . $store->perlobj->removeduid . "\n";
    }
} else {
    echo "Usage: " . $argv[0] . " UID\n";
    echo "    where UID is a persistent store user identifier\n";
}

?>
