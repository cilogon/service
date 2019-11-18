<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;

Util::startPHPSession();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
$submit = Util::getCsrf()->verifyCookieAndGetSubmit();
Util::unsetSessionVar('submit');

// Get the URL to reply to after database query.
$responseurl = Util::getSessionVar('responseurl');


if (
    ($submit == 'getuser') &&
    (strlen($responseurl) > 0) &&
    (strlen(Util::getGetVar('state')) > 0)
) {
    getUserAndRespond();
} else {
    // If responseurl is empty, simply redirect to main site
    if (strlen($responseurl) == 0) {
        $responseurl = 'https://' . Util::getHN();
    }
}

// Finally, redirect to the calling script.
header('Location: ' . $responseurl);
exit; // No further processing necessary
