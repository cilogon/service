<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
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
// CIL-410 Don't attempt to save the user to the database. Instead save
// attributes to the PHP session. This variable should be unset by other
// flows at the top-level index.php file.
Util::setSessionVar('storeattributes', '1'); // Used only by /testidp/

// Depending on the value of the clicked 'submit' button or the
// equivalent PHP session variable, take action or print out HTML.
switch ($submit) {
    case 'Log On': // Check for OpenID or InCommon usage.
    case 'Continue': // For OOI
        $providerId = Util::normalizeOAuth2IdP(Util::getPostVar('providerId'));
        $providerName = Util::getOAuth2IdP($providerId); // For OAuth2
        if (Util::getIdpList()->exists($providerId)) {
            // Use SAML authn
            Util::setCookieVar('providerId', $providerId);
            Content::redirectToGetShibUser($providerId);
        } elseif (array_key_exists($providerName, Util::$oauth2idps)) {
            // Use OAuth2 authn
            Util::setCookieVar('providerId', $providerId);
            Content::redirectToGetOAuth2User($providerId);
        } else { // Either providerId not set or not greenlit
            Util::unsetCookieVar('providerId');
            printLogonPage();
        }
        break; // End case 'Log On'

    case 'gotuser': // Return from the getuser script
        Content::handleGotUser();
        break; // End case 'gotuser'

    case 'Go Back': // Return to the Main page
    case 'Proceed': // Proceed after Error page
        printMainPage();
        break; // End case 'Go Back' / 'Proceed'

    case 'Cancel': // Cancel button on WAYF page - go to www.cilogon.org
        header('Location: https://www.cilogon.org/');
        exit; // No further processing necessary
        break;

    default: // No submit button clicked nor PHP session submit variable set
        printLogonPage();
        break; // End default case
} // End switch($submit)
