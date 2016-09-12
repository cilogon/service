<?php

require_once('../include/idplist.php');
require_once('../include/util.php');
require_once('../include/whitelist.php');

if ($argc == 2) {
    $idpfile = $argv[1];

    // Attempt to read in an existing idplist.xml file
    $idplist = new idplist($idpfile,idplist::defaultInCommonFilename,false);
    if (!$idplist->read()) {
        echo "Error! The file '$idpfile' could not be read.\n";
    } else {
        $whitelist = new whitelist(); // Read whitelisted IdPs (from database)
        $newrands = array(); // Keep track of new R&S IdPs for email alert
        $idps = $idplist->getInCommonIdPs(); // List of all IdPs from idplist.xml
        foreach ($idps as $entityId => $displayName) {
            // Find any R&S IdPs which are not whitelisted
            if (!$idplist->isWhitelisted($entityId)) {
                // CIL-327 - All InCommon and eduGAIN IdPs should
                // be 'whitelisted'. 
                $regincommon = $idplist->isRegisteredByInCommon($entityId);
                $incrands    = $idplist->isInCommonRandS($entityId);
                $refedsrands = $idplist->isREFEDSRandS($entityId);
                $sirtfi      = $idplist->isSIRTFI($entityId);
                // Add to whitelist? Need to save to database.
                if ($whitelist->add($entityId)) {
                    // Keep track for email alert
                    $newrands[$entityId] = array(
                        'Organization Name     ' => $displayName,
                        'Registered by InCommon' => ($regincommon?'Yes':''),
                        'InCommon R & S        ' => ($incrands?'Yes':''),
                        'REFEDS R & S          ' => ($refedsrands?'Yes':''),
                        'SIRTFI                ' => ($sirtfi?'Yes':''),
                    );
                }
            }
        }

        // Found new R&S IdPs? Save whitelist, regenerate idplist.xml, email alert
        if (count($newrands) > 0) {
            $whitelist->write();
            $idplist->create();
            $idplist->write();
            sendNotificationEmail($newrands);
        }
    }

} else {
    printUsage();
}

function printUsage() {
    echo "Usage: randsidps.php IDPFILE\n";
    echo "     IDPFILE is the full path name of an existing idplist.xml file\n";
    echo "This function reads in the passed-in IDPFILE and makes sure that\n";
    echo "all IdPs are whitelisted. (Previously only Research and\n";
    echo "Scholarship IdPs were to be marked as whitelisted, which is why\n";
    echo "the script is named randsidps.php.) The process is a little\n";
    echo "tricky since the authoritative list of whitelisted IdPs\n";
    echo "is maintained separately (either in the database or in another\n";
    echo "file). If an IdP is found NOT to be whitelisted, the list of \n";
    echo "whitelisted IdPs is updated AND the IDPLIST is re-created to \n";
    echo "reflect the new whitelisted IdP(s).\n";
}

function sendNotificationEmail($rands) {
    $plural = (count($rands) > 1);
    $mailto   = 'alerts@cilogon.org,idp-updates@cilogon.org';
    $mailfrom = 'From: alerts@cilogon.org' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
    $mailsubj = 'CILogon Service on ' . php_uname('n') . ' - ' .
                'New IdP' . ($plural ? 's' : '') . ' Automatically Whitelisted';
    $mailmsg  = "\n" . ($plural ? 'New' : 'A new') .
' Identity Provider' . ($plural ? 's were' : ' was') . ' found in metadata
and added to the list of available IdPs.
--------------------------------------------------------------

';

    foreach ($rands as $entityId => $attrib) {
        $mailmsg .= "EntityId               = $entityId\n";
        foreach ($attrib as $key => $val) {
            if (strlen($val) > 0) {
                $mailmsg .= "$key = $val\n";
            }
        }
        $mailmsg .= "\n";
    }

    mail($mailto,$mailsubj,$mailmsg,$mailfrom);
}

?>
