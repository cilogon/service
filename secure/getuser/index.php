<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Loggit;

Util::startPHPSession();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
// $submit = Util::getCsrf()->verifyCookieAndGetSubmit();
// CIL-1247 Ignore CSRF cookie check to avoid problems with Chrome
$submit = Util::getPostVar('submit'); // Check form submission
if (strlen($submit) == 0) {
    $submit = Util::getPostVar('SUBMIT'); // Hack for Duo Security - probably not needed
}
if (strlen($submit) == 0) { // Check PHP session
    $submit = Util::getSessionVar('submit');
}
Util::unsetSessionVar('submit');

// Get the URL to reply to after database query.
$responseurl = Util::getSessionVar('responseurl');

$log = new Loggit();
$log->info('In Shibboleth /getuser/ - submit="' . $submit . '" responseurl="' . $responseurl . '"');

if (($submit == 'getuser') && (strlen($responseurl) > 0)) {
    getUserAndRespond($responseurl);
} elseif ($submit == 'pkcs12') {
    getPKCS12();
} elseif ($submit == 'certreq') {
    getCert();
} else {
    // If the REQUEST_URI was '/secure/getcert' then it was ECP.
    // Respond with an error message rather than a redirect.
    if (preg_match('%/secure/getcert%', Util::getServerVar('REQUEST_URI'))) {
        $log->info('"/secure/getcert" error: Either CSRF check ' .
                   'failed, or invalid "submit" command issued.');
        outputError('Unable to complete ECP transaction. Either CSRF ' .
                    'check failed, or invalid "submit" command issued.');
    } else { // CIL-1252 Try to recover any flow in progress
        // If responseurl is empty, redirect to main site, or one of the flows (device/OIDC)
        if (strlen($responseurl) == 0) {
            $responseurl = 'https://' . Util::getHN() . '/';
            $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
            if (!is_null($clientparams)) {
                if (isset($clientparams['user_code'])) {
                    $responseurl .= 'device/';
                } elseif (isset($clientparams['redirect_uri'])) {
                    $responseurl .= 'authorize/';
                }
            }
        }

        Util::setSessionVar('submit', 'gotuser');
        Util::getCsrf()->setCookieAndSession();
        $log->info('In Shibboleth /getuser/ - redirecting to ' . $responseurl);
        header('Location: ' . $responseurl);
        exit; // No further processing necessary
    }
}
