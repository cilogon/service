<?php

/**
 * /idplist/
 *
 * The '/idplist/' endpoint prints out the list of available IdPs as a
 * JSON object. The endpoint supports the 'skin=..." URL query string
 * parameter so that the greenlit/redlit IdPs are returned as
 * appropriate. Note that if there is a problem reading the idplist.xml
 * file, the returned JSON is simply an empty array '[]'.
 */

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::startPHPSession();

$idparray = array(); // Array of IdP objects to be converted to JSON
$idplist = Util::getIdpList();
if ($idplist !== false) { // Verify we read in idplist.xml file
    $idps = Content::getCompositeIdPList(); // Using the 'skin'
    $randsidps = $idplist->getRandSIdPs();

    // CIL-1632 Get list of "hidden" IdPs
    $skin = Util::getSkin();
    $hiddenidps = $skin->getHiddenIdPs();

    // CIL-978 Check for 'idphint' query parameter
    $idphintlist = Content::getIdphintList($idps);
    if (!empty($idphintlist)) {
        $newidps = array();
        // Update the IdP selection list to show just the idphintlist.
        foreach ($idphintlist as $value) {
            $newidps[$value] = $idps[$value];
            // Also, remove from the $hiddenidps array
            if (($key = array_search($value, $hiddenidps)) !== false) {
                unset($hiddenidps[$key]);
            }
        }
        $idps = $newidps;
        // Re-sort the $idps by Display_Name for correct alphabetization.
        uasort($idps, function ($a, $b) {
            return strcasecmp(
                $a['Display_Name'],
                $b['Display_Name']
            );
        });
    }

    foreach ($idps as $entityId => $names) {
        // CIL-1080 If the entityId is in the HIDE_IDP_ARRAY, skip it.
        if (
            (defined('HIDE_IDP_ARRAY')) &&
            (in_array($entityId, HIDE_IDP_ARRAY))
        ) {
            continue;
        }
        $tmparray = array(
            'EntityID' => $entityId,
            'OrganizationName' => $names['Organization_Name'],
            'DisplayName' => $names['Display_Name'],
            'RandS' => array_key_exists($entityId, $randsidps)
        );
        // CIL-1632 Add "hidden" tag for IdPs hidden by skin
        if ((!empty($hiddenidps)) && (in_array($entityId, $hiddenidps))) {
            $tmparray['Hidden'] = true;
        }
        $idparray[] = $tmparray;
    }
}

header('Content-Type:application/json;charset=utf-8');

// Don't escape '/' or unicode characters
echo json_encode(
    $idparray,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
