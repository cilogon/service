<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';

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


/**
 * printLogonPage
 *
 * This function prints out the HTML for the IdP Selector page.
 * Explanatory text is shown as well as a button to log in to an IdP
 * and get rerouted to the Shibboleth protected testidp script.
 */
function printLogonPage()
{
    Content::printHeader('Test Your Identity Provider With CILogon');

    echo '
    <div class="boxed">
    ';

    Content::printHelpButton();

    echo '
      <br />
      <p>
      To test that your identity provider works with CILogon, please select
      it from the list below and Log On.
      </p>
    ';

    Content::printWAYF(false, true);

    echo '
    </div> <!-- End boxed -->
    ';
    Content::printFooter();
}

/**
 * redirectToTestIdP
 *
 * If the first parameter (a whitelisted entityId) is not specified,
 * we check to see if either the providerId PHP session variable or the
 * providerId cookie is set (in that order) and use one if available.
 * Then this function redirects to the "/secure/testidp/" script so as
 * to do a Shibboleth authentication via mod_shib.  When the providerId
 * is non-empty, the SessionInitiator will automatically go to that IdP
 * (i.e. without stopping at a WAYF).
 *
 * @param string $providerId (Optionals) An entityId of the authenticating
 *        IdP. If not specified (or set to the empty string), we check
 *        providerId PHP session variable and providerId cookie (in that
 *        order) for non-empty values.
 */
function redirectToTestIdP($providerId = '')
{
    // If providerId not set, try the cookie value
    if (strlen($providerId) == 0) {
        $providerId = Util::getCookieVar('providerId');
    }

    // Set up the "header" string for redirection thru mod_shib
    $testidp_url = 'https://' . Util::getHN() . '/secure/testidp/';
    $redirect =
        'Location: https://' . Util::getHN() . '/Shibboleth.sso/Login?' .
        'target=' . urlencode($testidp_url);
    if (strlen($providerId) > 0) {
        $redirect .= '&providerId=' . urlencode($providerId);
    }

    header($redirect);
    exit; // No further processing necessary
}
