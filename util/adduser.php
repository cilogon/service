<?php

require_once __DIR__ . '/../include/DBService.php';

use CILogon\Service\DBService;

if ($argc >= 7) {
    $remoteuser = $argv[1];
    $idp = $argv[2];
    $idpname = $argv[3];
    $firstname = $argv[4];
    $lastname = $argv[5];
    $emailaddr = $argv[6];
    $displayname = '';
    $eppn = '';
    $eptid = '';
    $open_id = '';
    $oidc = '';
    $affiliation = '';
    $ou = '';
    if ($argc >= 8) {
        $displayname = $argv[7];
    }
    if ($argc >= 9) {
        $eppn = $argv[8];
    }
    if ($argc >= 10) {
        $eptid = $argv[9];
    }
    if ($argc >= 11) {
        $open_id = $argv[10];
    }
    if ($argc >= 12) {
        $oidc = $argv[11];
    }
    if ($argc >= 13) {
        $affiliation = $argv[12];
    }
    if ($argc >= 14) {
        $ou = $argv[13];
    }

    if ((strlen($remoteuser) > 0) &&
        (strlen($idp) > 0) &&
        (strlen($idpname) > 0) &&
        (strlen($firstname) > 0) &&
        (strlen($lastname) > 0) &&
        (strlen($emailaddr) > 0)) {
        $dbs = new DBService();
        $dbs->getUser(
            $remoteuser,
            $idp,
            $idpname,
            $firstname,
            $lastname,
            $displayname,
            $emailaddr,
            $eppn,
            $eptid,
            $open_id,
            $oidc,
            $affiliation,
            $ou
        );

        printInfo($dbs);

        if ($dbs->status == DBService::$STATUS['STATUS_USER_UPDATED']) {
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

function printUsage()
{
    echo "Usage: adduser.php REMOTEUSER IDP IDPNAME FIRSTNAME LASTNAME EMAIL DISPLAYNAME EPPN EPTID OPENID OIDC AFFILIATION OU\n";
    echo "Note: The first six parameters must be specified for both InCommon and OpenID.\n";
    echo "      The rest are optional.\n";
}

function printInfo($dbs)
{
    echo "uid = $dbs->user_uid\n";
    $status = $dbs->status;
    echo "status = $status = " . array_search($status, DBService::$STATUS)."\n";
    echo "dn = $dbs->distinguished_name\n";
}
