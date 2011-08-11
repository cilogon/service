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

        $dbs = new dbservice();
        $dbs->getUser($remoteuser, $idp, $idpname, 
                      $firstname, $lastname, $emailaddr);

        printInfo($dbs);

        if ($dbs->status == dbservice::$STATUS['STATUS_USER_UPDATED']) {
            echo "-------------- USER UPDATED --------------\n";
            echo "----- Last Archived User Information -----\n";
            $uid = $dbs->user_uid;
            $dbs->getLastArchivedUser($uid);
            printInfo($dbs);
        }
    } else {
        printUsage();
    }

} else {
    printUsage();
}

function printUsage() {
    echo "Usage: adduser.php REMOTEUSER IDP IDPNAME FIRSTNAME LASTNAME EMAIL\n";
    echo "Note: All parameters must be specified for both InCommon and OpenID.\n";
}

function printInfo($dbs)
{
    echo "uid = $dbs->user_uid\n";
    $status = $dbs->status;
    echo "status = $status = " . array_search($status,dbservice::$STATUS)."\n";
    echo "dn = $dbs->distinguished_name\n";
}

?>
