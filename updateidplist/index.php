<?php

/**
 * /updateidplist/
 *
 * The '/updateidplist/' endpoint updates the CILogon idplist.xml and
 * idplist.json files. These are 'pared down' versions of the IdP-specific
 * InCommon-metadata.xml file, extracting just the useful portions of XML
 * for display on CILogon. This endpoint downloads the InCommon metadata and
 * creates both idplist.xml and idplist.json. It then looks for existing
 * idplist.{json,xml} files and sees if there are any differences. If so,
 * it prints out the differences and sends email. It also checks for newly
 * added IdPs and sends email. Finally, it copies the newly created idplist
 * files to the old location.
 */

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\IdpList;

Util::startPHPSession();

// Use a semaphore to prevent multiple processes running at the same time
$idplist_dir = dirname(DEFAULT_IDP_JSON);
$last_checked = $idplist_dir . '/.last_checked';
$key = ftok($last_checked, '1');
$semaphore = sem_get($key, 1);
if (@sem_acquire($semaphore, 1) === false) {
    echo "<p>Another process is running.</p>\n";
    return;
}

// Declare a few configuration constants
$mailto = EMAIL_ALERTS;
$mailtoidp = defined('EMAIL_IDP_UPDATES') ?
    EMAIL_ALERTS . ',' . EMAIL_IDP_UPDATES : '';
$mailfrom = 'From: ' . EMAIL_ALERTS . "\r\n" . 'X-Mailer: PHP/' . phpversion();
$check_timeout = 300; // in seconds
$httphost = Util::getHN();

// Load the '.last_checked' file and find the last time the endpoint
// was hit. If the file doesn't exist, then this is the first time.
// If the last time checked is less than a timeout, do nothing.
$lastcheck = file_get_contents($last_checked);
$difftime = abs(time() - (int)$lastcheck);
if ($difftime < $check_timeout) {
    echo "<p>Please wait " . ($check_timeout - $difftime) . " seconds.</p>\n";
    return;
}

// Download InCommon metadata to a new temporary directory in /tmp/.
// Be sure to delete the temporary directory before script exit.
$incommon_url = 'https://mdq.incommon.org/entities/idps/all';
$tmpdir = '';
$tmpincommon = '';
if (($incommon_xml = file_get_contents($incommon_url)) === false) {
    $errmsg = "Error: Unable to download InCommon-metadata.xml.";
    echo "<p>$errmsg</p>\n";
    mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
    http_response_code(500);
    return;
} else {
    $tmpdir = Util::tempDir('/tmp/');
    $tmpincommon = $tmpdir . '/InCommon-metadata.xml';
    if ((file_put_contents($tmpincommon, $incommon_xml)) === false) {
        $errmsg = "Error: Unable to save InCommon-metadata.xml to temporary directory.";
        echo "<p>$errmsg</p>\n";
        mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
        http_response_code(500);
        Util::deleteDir($tmpdir);
        return;
    }
}

// Now, create new idplist.xml and idplist.json files from the
// InCommon Metadata.
$tmpxml = $tmpdir . '/idplist.xml';
$idplist = new IdpList($tmpxml, $tmpincommon, false, 'xml');
$idplist->create();
if (!$idplist->write('xml')) {
    $errmsg = "Error: Unable to create temporary idplist.xml file.";
    echo "<p>$errmsg</p>\n";
    mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
    http_response_code(500);
    Util::deleteDir($tmpdir);
    return;
}
$tmpjson = $tmpdir . '/idplist.json';
$idplist->setFilename($tmpjson);
if (!$idplist->write('json')) {
    $errmsg = "Error: Unable to create temporary idplist.json file.";
    echo "<p>$errmsg</p>\n";
    mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
    http_response_code(500);
    Util::deleteDir($tmpdir);
    return;
}

// Try to read in an existing idplist.xml file so we can do a 'diff' later.
$idpxml_filename = preg_replace('/\.json$/', '.xml', DEFAULT_IDP_JSON);
$oldidplist = new IdpList($idpxml_filename, '', false, 'xml');

// If we successfully read in an existing idplist.xml file,
// check for differences, and also look for newly added IdPs.
$oldidplistempty = true;
$oldidplistdiff = false;
$newidpemail = '';
if (!empty($oldidplist->idparray)) {
    $oldidplistempty = false;

    // Check for differences using weird json_encode method found at
    // https://stackoverflow.com/a/42530586/12381604
    $diffarray = array_map(
        'json_decode',
        array_merge(
            array_diff(
                array_map('json_encode', $idplist->idparray),
                array_map('json_encode', $oldidplist->idparray)
            ),
            array_diff(
                array_map('json_encode', $oldidplist->idparray),
                array_map('json_encode', $idplist->idparray)
            )
        )
    );

    if (!empty($diffarray)) {
        $oldidplistdiff = true;

        // Check to see if any new IdPs were added to the InCommon metadata.
        $newIdPList = array();
        $oldEntityIDList = $oldidplist->getEntityIDs();
        if (!empty($oldEntityIDList)) {
            $entityIDList = $idplist->getEntityIDs();
            foreach ($entityIDList as $value) {
                if (!in_array($value, $oldEntityIDList)) {
                    $newIdPList[$value] = 1;
                }
            }
        }

        // If we found some new InCommon metadata entries, save them in a
        // string to be sent to idp-updates@cilogon.org.
        if (!empty($newIdPList)) {
            $plural = (count($newIdPList) > 1);
            $newidpemail .= ($plural ? 'New' : 'A new') . ' Identity Provider' .
                 ($plural ? 's were' : ' was') . ' found in metadata ' .
                 "and added to the \nlist of available IdPs.\n" .
                 '--------------------------------------------------------------' .
                 "\n\n";
            foreach ($newIdPList as $entityID => $value) {
                $newidpemail .= "EntityId               = $entityID\n";
                $newidpemail .= "Organization Name      = " .
                    $idplist->getOrganizationName($entityID) . "\n";
                $newidpemail .= "Display Name           = " .
                    $idplist->getDisplayName($entityID) . "\n";
                if ($idplist->isRegisteredByInCommon($entityID)) {
                    $newidpemail .= "Registered by InCommon = Yes\n";
                }
                if ($idplist->isInCommonRandS($entityID)) {
                    $newidpemail .= "InCommon R & S         = Yes\n";
                }
                if ($idplist->isREFEDSRandS($entityID)) {
                    $newidpemail .= "REFEDS R & S           = Yes\n";
                }
                if ($idplist->isSIRTFI($entityID)) {
                    $newidpemail .= "SIRTFI                 = Yes\n";
                }
                $newidpemail .= "\n";
            }
        }
    }
}

// If we found new IdPs, print them out and send email (if on prod).
if (strlen($newidpemail) > 0) {
    echo "<xmp>\n";
    echo $newidpemail;
    echo "</xmp>\n";

    if (strlen($mailtoidp) > 0) {
        // Send "New IdPs Added" email only from production server
        if (
            ($httphost == 'cilogon.org') ||
            ($httphost == 'polo1.cilogon.org')
        ) {
            mail(
                $mailtoidp,
                "CILogon Service on $httphost - New IdP Automatically Added",
                $newidpemail,
                $mailfrom
            );
        }
    }
}

// If other differences were found, do an actual 'diff' and send email.
if ($oldidplistdiff) {
    $idpdiff = `diff -u $idpxml_filename $tmpxml 2>&1`;
    echo "<xmp>\n\n";
    echo $idpdiff;
    echo "</xmp>\n";

    mail(
        $mailto,
        "idplist.xml changed on $httphost",
        "idplist.xml changed on $httphost\n\n" . $idpdiff,
        $mailfrom
    );
}

// Copy temporary idplist.{json,xml} files to production directory.
if ($oldidplistempty || $oldidplistdiff) {
    if (copy($tmpxml, $idplist_dir . '/idplist.xml')) {
        chmod($idpxml_filename, 0664);
        chgrp($idpxml_filename, 'apache');
    } else {
        $errmsg = "Error: Unable to copy idplist.xml to destination.";
        echo "<p>$errmsg</p>\n";
        mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
        http_response_code(500);
        Util::deleteDir($tmpdir);
        return;
    }
    if (copy($tmpjson, $idplist_dir . '/idplist.json')) {
        chmod(DEFAULT_IDP_JSON, 0664);
        chgrp(DEFAULT_IDP_JSON, 'apache');
    } else {
        $errmsg = "Error: Unable to copy idplist.json to destination.";
        echo "<p>$errmsg</p>\n";
        mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
        http_response_code(500);
        Util::deleteDir($tmpdir);
        return;
    }

    if ($oldidplistempty) {
        echo "<h3>New idplist.{json,xml} files were created.</h3>\n";
    } else {
        echo "<h3>Existing idplist.{json,xml} files were updated.</h3>\n";
    }
} else {
    echo "<p>No change detected in InCommon metadata.</p>\n";
}

// Final clean up. Delete the tempdir for the InCommon-metadata.xml and
// write the current time to .last_checked.
Util::deleteDir($tmpdir);
file_put_contents($last_checked, time());
@sem_release($semaphore);
