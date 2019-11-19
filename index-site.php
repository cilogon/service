<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\ShibError;
use CILogon\Service\Loggit;

Util::startPHPSession();

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

$log = new Loggit();
$log->info('submit="' . $submit . '"');

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

    case 'Cancel': // Cancel button on WAYF page - go to Google
        header('Location: https://www.google.com/');
        exit; // No further processing necessary
        break;

    case 'Get New Certificate':
        if (Util::verifySessionAndCall('CILogon\\Service\\Content::generateP12')) {
            printMainPage();
        }
        break; // End case 'Get New Certificate'

    case 'Show  Help ': // Toggle showing of help text on and off
    case 'Hide  Help ':
        Content::handleHelpButtonClicked();
        break; // End case 'Show Help' / 'Hide Help'

    default: // No submit button clicked nor PHP session submit variable set
        Content::handleNoSubmitButtonClicked();
        break; // End default case
} // End switch($submit)

// Util::$timeit->printTime('MAIN Program END...  ');
