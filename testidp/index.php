<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\ShibError;

Util::startPHPSession();

// Check for a Shibboleth error and handle it
$shiberror = new ShibError();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
// Note: replace CR/LF with space for 'Show/Hide Help' buttons.
$retchars = array("\r\n","\n","\r");
$submit = str_replace(
    $retchars,
    ' ',
    Util::getCsrf()->verifyCookieAndGetSubmit()
);
Util::unsetSessionVar('submit');

// Depending on the value of the clicked 'submit' button or the
// equivalent PHP session variable, take action or print out HTML.
switch ($submit) {
    case 'Log On': // Check for OpenID or InCommon usage.
    case 'Continue': // For OOI
        $providerIdPost = Util::getPostVar('providerId');
        if (Util::getIdpList()->exists($providerIdPost)) {
            // Use InCommon authn
            Util::setCookieVar('providerId', $providerIdPost);
            redirectToTestIdP($providerIdPost);
        } else { // Either providerId not set or not in whitelist
            Util::unsetCookieVar('providerId');
            printLogonPage();
        }
        break; // End case 'Log On'

    case 'Cancel': // Cancel button on WAYF page - go to Google
        header('Location: https://www.google.com/');
        exit; // No further processing necessary
        break;

    case 'Show  Help ': // Toggle showing of help text on and off
    case 'Hide  Help ':
        if (Util::getSessionVar('showhelp') == 'on') {
            Util::unsetSessionVar('showhelp');
        } else {
            Util::setSessionVar('showhelp', 'on');
        }
        printLogonPage();
        break; // End case 'Show Help' / 'Hide Help'

    default: // No submit button clicked nor PHP session submit variable set
        printLogonPage();
        break; // End default case
} // End switch($submit)
