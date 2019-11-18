<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

Util::startPHPSession();

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

        case 'Proceed': // Proceed after 'User Changed' or Error page
        case 'Done with Two-Factor':
            Util::verifySessionAndCall('printMainPage');
            break; // End case 'Proceed'

        case 'OK':  // User allows delegation of certificate
            handleAllowDelegation(strlen(Util::getPostVar('rememberok')) > 0);
            break; // End case 'OK'

        case 'Cancel': // User denies delegation of certificate
            // If user clicked 'Cancel' on the WAYF page, return to the
            // portal's failure URL (or Google if failure URL not set).
            if (Util::getPostVar('previouspage') == 'WAYF') {
                $failureuri = Util::getSessionVar('failureuri');
                $location = 'https://www.google.com/';
                if (strlen($failureuri) > 0) {
                    $location = $failureuri . "?reason=cancel";
                }
                Util::unsetAllUserSessionVars();
                header('Location: ' . $location);
                exit; // No further processing necessary
            } else { // 'Cancel' button on certificate delegate page clicked
                printCancelPage();
            }
            break; // End case 'Cancel'

        case 'Manage Two-Factor':
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::printTwoFactorPage'
            );
            break; // End case 'Manage Two-Factor'

        case 'Enable':   // Enable / Disable two-factor authentication
        case 'Disable':
        case 'Verify':   // Log in with Google Authenticator
        case 'Disable Two-Factor':
            $enable = !preg_match('/^Disable/', $submit);
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleEnableDisableTwoFactor',
                array($enable)
            );
            break; // End case 'Enable' / 'Disable'

        case 'I Lost My Phone':
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleILostMyPhone'
            );
            break; // End case 'I Lost My Phone'

        case 'Enter': // Verify Google Authenticator one time password
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleGoogleAuthenticatorLogin'
            );
            break; // End case 'Enter'

        case 'EnterDuo': // Verify Duo Security login
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleDuoSecurityLogin'
            );
            break; // End case 'EnterDuo'

        case 'Show  Help ': // Toggle showing of help text on and off
        case 'Hide  Help ':
            Content::handleHelpButtonClicked();
            break; // End case 'Show Help' / 'Hide Help'

        default: // No submit button clicked nor PHP session submit variable set
            Content::handleNoSubmitButtonClicked();
            break; // End default case
    } // End switch ($submit)
} else { // Failed to verify oauth_token info in PHP session
    printBadOAuthTokenPage();
}
