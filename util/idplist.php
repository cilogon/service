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
require_once 'Util.php';

use CILogon\Service\IdpList;

if (($argc >= 2) && ($argc <= 4)) {
    $idpfile = $argv[1];
    $filetype = 'json';
    if ($argc >= 3) {
        $filetype = strtolower($argv[2]);
    }
    $checkfornew = 0;
    if ($argc >= 4) {
        $checkfornew = 1;
    }

    $oldEntityIdList = array();

    // If checkfornew, attempt to read in the already existing
    // /var/www/html/include/idplist.{json,xml} file so we can use
    // that as the list of current IdPs. This will allow us to find
    // out if any new IdPs have been added to the InCommon metadata.
    if ($checkfornew) {
        // First, try reading /var/www/html/include/idplist.json
        $oldidplist = new IdpList(IdpList::DEFAULTIDPFILENAME, '', false, 'json');
        $oldEntityIDList = $oldidplist->getEntityIDs();
        if (empty($oldEntityIDList)) {
            // Next, try /var/www/html/include/idplist.xml
            $filename = preg_replace(
                '/\.json$/',
                '.xml',
                IdpList::DEFAULTIDPFILENAME
            );
            $oldidplist = new IdpList($filename, '', false, 'xml');
            $oldEntityIDList = $oldidplist->getEntityIDs();
        }
        // If we couldn't read in an exiting idplist, print warning message.
        if (empty($oldEntityIDList)) {
            fwrite(
                STDERR,
                "Warning: Unable to read an existing idplist file,\n",
                "         so unable to check for new InCommon IdPs.\n"
            );
        }
    }

    // Now, create a new idplist from the InCommon Metadata
    $idplist = new IdpList(
        $idpfile,
        IdpList::DEFAULTINCOMMONFILENAME,
        false,
        $filetype
    );
    $idplist->create();
    if (!$idplist->write($filetype)) {
        fwrite(
            STDERR,
            "Error! There was a problem writing to the file '" .
            $idpfile . "'\n"
        );
        exit(1);
    }

    // If we successfully read in a 'good' idplist.{json.xml} file from
    // /var/www/html/include, use that as the list of currently
    // 'whitelisted' IdPs and check to see if any new IdP were added to
    // the InCommon metadata.
    $newIdPList = array();
    if (!empty($oldEntityIDList)) {
        $entityIDList = $idplist->getEntityIDs();
        foreach ($entityIDList as $value) {
            if (!in_array($value, $oldEntityIDList)) {
                $newIdPList[$value] = 1;
            }
        }
    }

    // Found some new InCommon metadata entries. Print them to STDOUT.
    if (!empty($newIdPList)) {
        $plural = (count($newIdPList) > 1);
        echo($plural ? 'New' : 'A new') , ' Identity Provider',
             ($plural ? 's were' : ' was') , ' found in metadata ',
             "and added to the \nlist of available IdPs.\n",
             '--------------------------------------------------------------',
             "\n\n";
        foreach ($newIdPList as $entityID => $value) {
            echo "EntityId               = $entityID\n";
            echo "Organization Name      = " .
                $idplist->getOrganizationName($entityID) . "\n";
            echo "Display Name           = " .
                $idplist->getDisplayName($entityID) . "\n";
            if ($idplist->isRegisteredByInCommon($entityID)) {
                echo "Registered by InCommon = Yes\n";
            }
            if ($idplist->isInCommonRandS($entityID)) {
                echo "InCommon R & S         = Yes\n";
            }
            if ($idplist->isREFEDSRandS($entityID)) {
                echo "REFEDS R & S           = Yes\n";
            }
            if ($idplist->isSIRTFI($entityID)) {
                echo "SIRTFI                 = Yes\n";
            }
            echo "\n";
        }
    }
} else {
    printUsage();
}

function printUsage()
{
    echo "Usage: idplist.php IDPFILE {FILETYPE} <CHECK>\n";
    echo "     IDPFILE  is the full path name of the idplist file.\n";
    echo "     FILETYPE is either 'xml' or 'json'. Defaults to 'json.'\n";
    echo "     CHECK    means see if new IdPs added to InCommon metadata.\n";
    echo "This function reads the InCommon metadata and writes out the\n";
    echo "IDPFILE, which contains the list of all IdPs along with\n";
    echo "their attributes needed by the CILogon Service.\n";
    echo "If CHECK (optional) is specified, it attempts to read in an\n";
    echo "existing /var/www/html/include,{json,xml} file as the 'current'\n";
    echo "list of IdPs so it can check if any new IdPs have beenn added\n";
    echo "to InCommon metadata. If so, the new IdPs are printed to STDOUT.\n";
}
