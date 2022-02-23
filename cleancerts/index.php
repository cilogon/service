<?php

/**
 * /cleancerts/
 *
 * The '/cleancerts/' endpoint scans the DEFAULT_PKCS12_DIR for certificate
 * directories/files that are older than 10 minutes and removes them.
 */

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;

Util::cleanCerts();
