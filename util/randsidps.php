<?php

require_once('../include/util.php');
require_once('../include/idplist.php');
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
            if (($idplist->isRandS($entityId)) && 
                (!$idplist->isWhitelisted($entityId))) {
                if ($whitelist->add($entityId)) { // Add to whitelist? Need to save
                    $newrands[$entityId] = $displayName;  // Keep track for email alert
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
    echo "all 'research-and-scholarship' IdPs are also whitelisted. This\n";
    echo "is a little tricky since the authoritative list of whitelisted IdPs\n";
    echo "is maintained separately (either in the database or in another\n";
    echo "file). If a 'research-and-scholarship' IdP is found NOT to be\n";
    echo "whitelisted, the list of whitelisted IdPs is updated AND the\n";
    echo "IDPLIST is re-created to reflect the new whitelisted IdP(s).\n";
}

function sendNotificationEmail($rands) {
    $plural = (count($rands) > 1);
    $mailto   = 'alerts@cilogon.org,idp-updates@cilogon.org';
    $mailfrom = 'From: alerts@cilogon.org' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
    $mailsubj = 'CILogon Service on ' . php_uname('n') . ' - ' .
                'New R&S IdP' . ($plural ? 's' : '') . ' Added To Whitelist';
    $mailmsg  = "\n" . ($plural ? 'New' : 'A new') .
' Research And Scholarship Identity Provider' . ($plural ? 's were' : ' was') . ' found in
the InCommon Metadata and added to the list of available IdPs.
--------------------------------------------------------------

';

    foreach ($rands as $entityId => $displayName) {
        $mailmsg .= "Organization = $displayName\n";
        $mailmsg .= "(EntityId    = $entityId)\n\n";
    }

    mail($mailto,$mailsubj,$mailmsg,$mailfrom);
}

?>
