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

// First, check to see if the info related to the OIDC client exists
// in the current PHP session. If so, continue processing based on the
// 'submit' value. Otherwise, print out error message about bad or
// missing OpenID Connect parameters.
if (verifyOIDCParams()) {
    // Get the OIDC client parameters from the PHP session.
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);

    // Depending on the value of the clicked 'submit' button or the
    // equivalent PHP session variable, take action or print out HTML.
    switch ($submit) {
        case 'Log On': // Check for OpenID or InCommon usage.
        case 'Continue': // For OOI
            // Need to check for 'max_age' OIDC parameter. If elapsed time
            // since last user authentication is greater than max_age, then
            // set 'forceauthn' session variable to force the user to
            // (re)authenticate.
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
            Util::verifySessionAndCall('printMainPage');
            break; // End case 'Proceed'

        case 'Cancel': // User denies release of attributes
            // If user clicked the 'Cancel' button, return to the
            // OIDC client with an error message.
            $redirect = 'https://www.cilogon.org'; // If no redirect_uri
            $redirect_uri = @$clientparams['redirect_uri'];
            if (strlen($redirect_uri) > 0) {
                $redirect = 'Location: ' . $redirect_uri .
                    (preg_match('/\?/', $redirect_uri) ? '&' : '?') .
                    'error=access_denied&error_description=' .
                    'User%20denied%20authorization%20request' .
                    ((isset($clientparams['state'])) ?
                        '&state=' . $clientparams['state'] : '');
            }
            Util::unsetAllUserSessionVars();
            header($redirect);
            exit; // No further processing necessary
            break; // End case 'Cancel'

        default: // No submit button clicked nor PHP session submit variable set
            Content::handleNoSubmitButtonClicked();
            break; // End default case
    } // End switch ($submit)
} else { // Failed to verify OIDC client parameters in PHP session
    printOIDCErrorPage();
}
