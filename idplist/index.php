<?php
/**
 * idplist.php
 *
 * The '/idplist/' endpoint prints out the list of available IdPs as a
 * JSON object. The endpoint supports the 'skin=..." URL query string
 * parameter so that the blacklisted/whitelisted IdPs are returned as
 * appropriate. Note that if there is a problem reading the idplist.xml
 * file, the returned JSON is simply an empty array '[]'.
 */

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::startPHPSession();

$idparray = array(); // Array of IdP objects to be converted to JSON
$idplist = Util::getIdpList();
if ($idplist !== false) { // Verify we read in idplist.xml file
    $idps = Content::getCompositeIdPList(); // Using the 'skin'
    $randsidps = $idplist->getRandSIdPs();

    foreach ($idps as $entityId => $idpName) {
        $idparray[] = array(
            'EntityID' => $entityId,
            'OrganizationName' => $idpName,
            'RandS' => array_key_exists($entityId, $randsidps)
        );
    }
}

header('Content-Type:application/json;charset=utf-8');

// Don't escape '/' or unicode characters
echo json_encode(
    $idparray,
    JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
);
