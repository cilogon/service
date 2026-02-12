<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
include_once __DIR__ . '/config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\ShibError;
use CILogon\Service\Loggit;

Util::cilogonInit();

// Util::startTiming();
// Util::$timeit->printTime('MAIN Program START...');

// Check for a Shibboleth error and handle it
$shiberror = new ShibError();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
// Note: replace CR/LF with space for 'Show/Hide Help' buttons.
$retchars = array("\r\n","\n","\r");
$submit = str_replace(
    $retchars,
    " ",
    Util::getCsrf()->verifyCookieAndGetSubmit()
);
Util::unsetSessionVar('submit');
Util::unsetSessionVar('storeattributes'); // Used only by /testidp/

// Get the 'stage' (used when changing languages)
$stage = Util::getSessionVar('stage');
Util::unsetSessionVar('stage');

if (strlen($submit) > 0) {
    $log = new Loggit();
    $log->info('submit="' . $submit . '"');
}

// Depending on the value of the clicked 'submit' button or the
// equivalent PHP session variable, take action or print out HTML.
switch ($submit) {
    case 'Log On': // Check for OpenID or InCommon usage.
    case 'Continue': // For OOI
        Content::handleLogOnButtonClicked();
        break; // End case 'Log On'

    case 'Log Off':   // Click the 'Log Off' button
        printLogonPage(true);
        break; // End case 'Log Off'

    case 'gotuser': // Return from the getuser script
        Content::handleGotUser();
        break; // End case 'gotuser'

    case 'Go Back': // Return to the Main page
    case 'Proceed': // Proceed after Error page
        Util::verifySessionAndCall('printMainPage');
        break; // End case 'Go Back' / 'Proceed'

    case 'Cancel': // Cancel button on WAYF page - go to CILogon Info Page
        header('Location: https://www.cilogon.org');
        exit; // No further processing necessary
        break;

    // A language was chosen from the language dropdown menu
    // E.g., en_US (2 lowercase, underscore, 2 uppercase)
    case (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $submit) ? true : false):
        Util::changeLanguage($submit);
        if ($stage == 'MainPage') {
            Util::verifySessionAndCall('printMainPage');
        } else {
            Content::handleNoSubmitButtonClicked();
        }
        break;

    default: // No submit button clicked nor PHP session submit variable set
        Content::handleNoSubmitButtonClicked();
        break; // End default case
} // End switch($submit)

// Util::$timeit->printTime('MAIN Program END...  ');
