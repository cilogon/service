<?php

require_once('../include/autoloader.php');
require_once('../include/util.php');

if (($argc == 2) || ($argc == 3)) {

    $uid = $argv[1];
    $last = false;
    if ($argc == 3) {
        $last = true;
    }
    
    if (strlen($uid) > 0) {
        $store = new store();
        if ($last) {
            $store->getLastUserObj($uid);
        } else {
            $store->getUserObj($uid);
        }
        $status = $store->getUserSub('status');
        echo "status = " . $status . " = " . 
            array_search($status,$store->STATUS) . "\n";
        echo "uid = " . $store->getUserSub('uid') . "\n";
        echo "firstName = " . $store->getUserSub('firstName') . "\n";
        echo "lastName = " . $store->getUserSub('lastName') . "\n";
        echo "remoteUser = " . $store->getUserSub('remoteUser') . "\n";
        echo "idp = " . $store->getUserSub('idp') . "\n";
        echo "idpDisplayName = " . $store->getUserSub('idpDisplayName') . "\n";
        echo "email = " . $store->getUserSub('email') . "\n";
        echo "dn = " . $store->getUserSub('getDN') . "\n";
        echo "serialString = " . $store->getUserSub('serialString') . "\n";
    }
} else {
    echo "Usage: " . $argv[0] . "  UID [last]\n";
    echo "    where UID is a persistent store user identifier\n";
    echo "          [last] if set to 1 returns 'last archived' user info\n";
}

?>
