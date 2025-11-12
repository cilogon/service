<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

Util::cilogonInit();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
$submit = Util::getCsrf()->verifyCookieAndGetSubmit();
Util::unsetSessionVar('submit');
Util::unsetSessionVar('storeattributes'); // Used only by /testidp/

if (strlen($submit) > 0) {
    $log = new Loggit();
    $log->info('submit="' . $submit . '"');
}

// Depending on the value of the clicked 'submit' button or the
// equivalent PHP session variable, take action or print out HTML.
switch ($submit) {
    case 'Enter User Code':
        if (!verifyUserCodeParam()) { // user_code was not valid
            printUserCodePage();
        } else { // Redirect to 'Select an IdP' page or bypass it if configured
            Content::handleNoSubmitButtonClicked();
        }
        break; // End case 'Enter User Code'

    case 'Log On': // Check for OpenID or InCommon usage
    case 'Continue': // For OOI
        // Need to check for 'max_age' OIDC parameter. If elapsed time
        // since last user authentication is greater than max_age, then
        // set 'forceauthn' session variable to force the user to
        // (re)authenticate.
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        if (isset($clientparams['max_age'])) {
            $max_age = (int)$clientparams['max_age'];
            if (strlen(Util::getSessionVar('authntime')) > 0) {
                $authntime = (int)Util::getSessionVar('authntime');
                $currtime = time();
                if (
                    ($authtime > $currtime) || // Weird error!!!
                    (($currtime - $authtime) > $max_age)
                ) {
                    Util::setSessionVar('forceauthn', '1');
                }
            } else { // No authntime - assume no user authentication
                Util::setSessionVar('forceauthn', '1');
            }
        }
        Content::handleLogOnButtonClicked();
        break; // End case 'Log On'

    case 'gotuser': // Return from the getuser script
        Content::handleGotUser();
        break; // End case 'gotuser'

    case 'Proceed': // Proceed after Error page
        Util::verifySessionAndCall('printLogonPage');
        break; // End case 'Proceed'

    case 'Cancel': // User denies release of attributes
        Util::setSessionVar('user_code_denied', '1');
        printMainPage();
        break; // End case 'Cancel'

    // A language was chosen from the language dropdown menu
    // E.g., en_US (2 lowercase, underscore, 2 uppercase)
    case (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $submit) ? true : false):
        Util::setSessionVar('lang', $submit);
        verifyUserCodeParam(); // Need to scan for query parameters
        Content::handleNoSubmitButtonClicked();
        break;

    default: // No submit button clicked nor PHP session submit variable set
        verifyUserCodeParam(); // Need to scan for query parameters
        Content::handleNoSubmitButtonClicked();
        break; // End default case
} // End switch ($submit)
