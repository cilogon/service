<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';
include_once __DIR__ . '/../../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Loggit;

Util::cilogonInit();

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
} else {
    // If the REQUEST_URI was '/secure/getcert' then it was ECP.
    // Respond with an error message rather than a redirect.
    if (preg_match('%/secure/getcert%', Util::getServerVar('REQUEST_URI'))) {
        $log->error(
            '"/secure/getcert" error: Attempt to use ECP getcert endpoint.',
            false,
            false
        );
        outputError('ECP certificates have been disabled.');
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
        $log->info('In Shibboleth /getuser/ - redirecting to ' . $responseurl, false, false);
        header('Location: ' . $responseurl);
        exit; // No further processing necessary
    }
}
