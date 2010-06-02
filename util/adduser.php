<?php

require_once('../include/autoloader.php');
require_once('../include/util.php');

if ($argc == 7) {

    $remoteuser = $argv[1];
    $idp = $argv[2];
    $idpname = $argv[3];
    $firstname = $argv[4];
    $lastname = $argv[5];
    $emailaddr = $argv[6];

    if ((strlen($remoteuser) > 0) &&
        (strlen($idp) > 0) &&
        (strlen($idpname) > 0) &&
        (strlen($firstname) > 0) &&
        (strlen($lastname) > 0) &&
        (strlen($emailaddr) > 0)) {

        $store = new store();
        $store->getUserObj($remoteuser, $idp, $idpname, 
                           $firstname, $lastname, $emailaddr);

        printInfo($store);

        if ($store->getUserSub('status') == 
            $store->STATUS['STATUS_OK_USER_CHANGED']) {
            echo "\n----- USER CHANGED -----\n\n";
            $uid = $store->getUserSub('uid');
            $store->getLastUserObj($uid);
            printInfo($store);
        }
    } else {
        printUsage();
    }

} elseif ($argc == 3) {

    $remoteuser = $argv[1];
    $idp = $argv[2];

    if ((strlen($remoteuser) > 0) &&
        (strlen($idp) > 0)) {

        $store = new store();
        $store->getUserObj($remoteuser, $idp);

        printInfo($store);
    } else {
        printUsage();
    }

} else {
    printUsage();
}

function printUsage() {
    echo "Usage: adduser.php REMOTEUSER IDP\n";
    echo "  OR   adduser.php REMOTEUSER IDP IDPNAME FIRSTNAME LASTNAME EMAIL\n";
    echo "Note: The first usage is for OpenID.  The second usage is for InCommon.\n";
}

function printInfo($store)
{
    $uid = $store->getUserSub('uid');
    echo "uid = $uid\n";
    $status = $store->getUserSub('status');
    echo "status = $status = " . array_search($status,$store->STATUS) . "\n";
    $dn = $store->getUserSub('getDN');
    echo "dn = $dn\n";
}

?>
