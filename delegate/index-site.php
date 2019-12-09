<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

Util::startPHPSession();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
$submit = Util::getCsrf()->verifyCookieAndGetSubmit();
Util::unsetSessionVar('submit');
Util::unsetSessionVar('storeattributes'); // Used only by /testidp/

$log = new Loggit();
$log->info('submit="' . $submit . '"');

// First, check to see if the info related to the 'oauth_token' passed
// from the Community Portal exists in the current PHP session.  If
// so, then continue processing based on 'submit' value.  Otherwise,
// print out error message about bad or missing oauth_token info.
if (verifyOAuthToken(Util::getGetVar('oauth_token'))) {
    // Depending on the value of the clicked 'submit' button or the
    // equivalent PHP session variable, take action or print out HTML.
    switch ($submit) {
        case 'Log On': // Check for OpenID or InCommon usage.
        case 'Continue': // For OOI
            Content::handleLogOnButtonClicked();
            break; // End case 'Log On'

        case 'gotuser': // Return from the getuser script
            Content::handleGotUser();
            break; // End case 'gotuser'

        case 'Proceed': // Proceed after Error page
            Util::verifySessionAndCall('printMainPage');
            break; // End case 'Proceed'

        case 'OK':  // User allows delegation of certificate
            handleAllowDelegation(strlen(Util::getPostVar('rememberok')) > 0);
            break; // End case 'OK'

        case 'Cancel': // User denies delegation of certificate
            // If user clicked 'Cancel' on the WAYF page, return to the
            // portal's failure URL (or Google if failure URL not set).
            if (Util::getPostVar('previouspage') == 'WAYF') {
                $redirect = 'https://www.cilogon.org/'; // If no failureuri
                $failureuri = Util::getSessionVar('failureuri');
                if (strlen($failureuri) > 0) {
                    $redirect = $failureuri . "?reason=cancel";
                }
                Util::unsetAllUserSessionVars();
                header('Location: ' . $redirect);
                exit; // No further processing necessary
            } else { // 'Cancel' button on certificate delegate page clicked
                printCancelPage();
            }
            break; // End case 'Cancel'

        default: // No submit button clicked nor PHP session submit variable set
            Content::handleNoSubmitButtonClicked();
            break; // End default case
    } // End switch ($submit)
} else { // Failed to verify oauth_token info in PHP session
    printOAuth1ErrorPage();
}
