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

Util::updateIdPList();
