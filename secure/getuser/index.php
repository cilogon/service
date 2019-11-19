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
$submit = Util::getCsrf()->verifyCookieAndGetSubmit();
Util::unsetSessionVar('submit');

// Get the URL to reply to after database query.
$responseurl = Util::getSessionVar('responseurl');

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
        $log = new Loggit();
        $log->info('"/secure/getcert" error: Either CSRF check ' .
                   'failed, or invalid "submit" command issued.');
        outputError('Unable to complete ECP transaction. Either CSRF ' .
                    'check failed, or invalid "submit" command issued.');
    } else { // Redirect to $responseurl or main homepage
        if (strlen($responseurl) == 0) {
            $responseurl = 'https://' . Util::getHN();
        }
        header('Location: ' . $responseurl);
        exit; // No further processing necessary
    }
}
