<?php

set_include_path(
    '/var/www/html' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/config' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/vendor/cilogon/service-lib/src/Service' . PATH_SEPARATOR .
    '.'
);

require_once 'config.php';
include_once 'config.secrets.php';
require_once 'DBService.php';
require_once 'Util.php';
require_once 'IdpList.php';

use CILogon\Service\DBService;

if ($argc >= 7) { // Program name + 6 arguments minimum
    $idx = 0;
    foreach ($argv as $value) {
        // The 6th (display_name) and 7th (email) arguments need to be
        // swapped in the DBService::$user_attrs since display_name
        // used to be an optional parameter, and thus email is
        // listed first in the argv list.
        if ($idx == 5) {
            ${DBService::$user_attrs[6]} = $value;
        } elseif ($idx == 6) {
            ${DBService::$user_attrs[5]} = $value;
        } else {
            ${DBService::$user_attrs[$idx]} = $value;
        }
        $idx++;
    }

    if (
        ((strlen($remote_user) > 0) ||
         (strlen($eppn) > 0) ||
         (strlen($eptid) > 0) ||
         (strlen($open_id) > 0) ||
         (strlen($oidc) > 0) ||
         (strlen($subject_id) > 0) ||
         (strlen($pairwiseid) > 0)) &&
        (strlen($idp) > 0) &&
        (strlen($idp_display_name) > 0)
    ) {
        $dbs = new DBService();
        $dbs->getUser(
            $remote_user,
            $idp,
            $idp_display_name,
            $first_name,
            $last_name,
            $display_name,
            $email,
            $loa,
            $eppn,
            $eptid,
            $open_id,
            $oidc,
            $subject_id,
            $pairwise_id,
            $affiliation,
            $ou,
            $member_of,
            $acr,
            $entitlement,
            $itrustuin
        );

        printInfo($dbs);
    } else {
        printUsage();
    }
} else {
    printUsage();
}

function printUsage()
{
    echo "Usage: adduser.php REMOTEUSER IDP IDPNAME FIRSTNAME LASTNAME EMAIL" ,
         "       LOA DISPLAYNAME EPPN EPTID OPENID OIDC SUBJECTID PAIRWISEID",
         "       AFFILIATION OU MEMBER AUTHNCONTEXTCLASSREF",
         "       ENTITLEMENT ITRUSTUIN\n",
         "Note: The first six parameters must be specified for both " ,
         "InCommon and OpenID. The rest are optional.\n";
}

function printInfo($dbs)
{
    echo "user_uid = $dbs->user_uid\n";
    $status = $dbs->status;
    echo "status = $status = " . array_search($status, DBService::$STATUS) . "\n";
    echo "dn = $dbs->distinguished_name\n";
}
