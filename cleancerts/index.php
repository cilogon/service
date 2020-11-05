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

Util::startPHPSession();

// Declare a few configuration constants
$check_timeout = 300; // in seconds
$check_filename = '.last_checked';

// Load the '.last_checked' file and find the last time the endpoint
// was hit. If the file doesn't exist, then this is the first time.
// If the last time checked is less than a timeout, do nothing.
$lastcheck = file_get_contents(DEFAULT_PKCS12_DIR . $check_filename);
$difftime = abs(time() - (int)$lastcheck);
if ($difftime < $check_timeout) {
    echo "<p>Please wait " . $check_timeout - $difftime . " seconds.</p>";
    return;
}

$numdel = Util::cleanupPKCS12();
echo "<p>$numdel certificate(s) cleaned up.</p>";

// Final clean up. Write the current time to .last_checked.
file_put_contents(DEFAULT_PKCS12_DIR . $check_filename, time());
