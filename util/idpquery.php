<?php

set_include_path(
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/config' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/vendor/cilogon/service-lib/src/Service' . PATH_SEPARATOR . 
    '.'
);

require_once 'IdpList.php';

use CILogon\Service\IdpList;

if ($argc >= 2) {
    $regbyincommon = null;
    $incommonrands = null;
    $refedsrands = null;
    $sirtfi = null;
    $rands = null;
    $bronze = null;
    $silver = null;

    // Scan command line for attributes to match
    for ($v = 1; $v < $argc; $v++) {
        $param = strtolower($argv[$v]);
        if (preg_match('/^registeredbyincommon=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $regbyincommon = 0;
            } else {
                $regbyincommon = 1;
            }
        } elseif (preg_match('/^incommonrands=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $incommonrands = 0;
            } else {
                $incommonrands = 1;
            }
        } elseif (preg_match('/^refedsrands=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $refedsrands = 0;
            } else {
                $refedsrands = 1;
            }
        } elseif (preg_match('/^sirtfi=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $sirtfi = 0;
            } else {
                $sirtfi = 1;
            }
        } elseif (preg_match('/^rands=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $rands = 0;
            } else {
                $rands = 1;
            }
        } elseif (preg_match('/^bronze=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $bronze = 0;
            } else {
                $bronze = 1;
            }
        } elseif (preg_match('/^silver=?([01]?)$/', $param, $matches)) {
            if ((isset($matches[1])) && ($matches[1] == '0')) {
                $silver = 0;
            } else {
                $silver = 1;
            }
        } else {
            echo "ERROR: Unknown query attribute '$param'.\n";
            exit;
        }
    }

    $idplist = new IdpList();
    $allidps = $idplist->getEntityIDs();
    $idps = array();

    // Loop through all IdPs and try to match command line parameters
    foreach ($allidps as $idp) {
        if (!is_null($regbyincommon)) {
            $param = $idplist->isRegisteredByInCommon($idp);
            if ($regbyincommon xor $param) {
                continue;
            }
        }
        if (!is_null($incommonrands)) {
            $param = $idplist->isInCommonRandS($idp);
            if ($incommonrands xor $param) {
                continue;
            }
        }
        if (!is_null($refedsrands)) {
            $param = $idplist->isREFEDSRandS($idp);
            if ($refedsrands xor $param) {
                continue;
            }
        }
        if (!is_null($sirtfi)) {
            $param = $idplist->isSIRTFI($idp);
            if ($sirtfi xor $param) {
                continue;
            }
        }
        if (!is_null($rands)) {
            $param = $idplist->isRandS($idp);
            if ($rands xor $param) {
                continue;
            }
        }
        if (!is_null($bronze)) {
            $param = $idplist->isBronze($idp);
            if ($bronze xor $param) {
                continue;
            }
        }
        if (!is_null($silver)) {
            $param = $idplist->isSilver($idp);
            if ($silver xor $param) {
                continue;
            }
        }
        // If we made it this far, add the idp to the list of matched idps.
        $idps[] = $idp;
    }

    foreach ($idps as $idp) {
        echo $idp . "\t" . $idplist->getOrganizationName($idp) . "\n";
    }
    echo count($idps) . " IdPs found matching these conditions.\n";
} else {
    printUsage();
}

function printUsage()
{
    echo "Usage: php idpquery.php <RegisteredByInCommon=0|1> <InCommonRandS=0|1>\n";
    echo "                        <REFEDSRandS=0|1> <SIRTFI=0|1> <RandS=0|1>\n";
    echo "                        <Bronze=0|1> <Silver=0|1>\n\n";
    echo "This script allows you to query the idplist.xml file for IdPs with attributes\n";
    echo "that satisfy a certain set of conditions. The conditions you specify can be\n";
    echo "either 'must have' (=1) or 'must not have' (=0). These are joined together\n";
    echo "with a logical 'AND'. For example, to get a list of all eduGAIN IdPs that\n";
    echo "are SIRTFI but not REFEDSRandS:\n";
    echo "    php idpquery.php RegisteredByInCommon=0 SIRTFI=1 REFEDSRandS=0\n";
    echo "The attributes are case-insensitive. If you neglect to specify =0 or =1,\n";
    echo "=1 is assumed (i.e., 'RandS' is equivalent to 'RandS=1').\n";
}
