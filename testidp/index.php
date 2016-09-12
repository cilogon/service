<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');

// Check for a Shibboleth error and handle it
$shiberror = new shiberror();

// $idplist initialized in util.php

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  *
 * Note: replace CR/LF with space for "Show/Hide Help" buttons.      */
$retchars = array("\r\n","\n","\r");
$submit = str_replace($retchars," ",$csrf->verifyCookieAndGetSubmit());
util::unsetSessionVar('submit');

/* Depending on the value of the clicked "submit" button or the    *
 * equivalent PHP session variable, take action or print out HTML. */
switch ($submit) {

    case 'Log On': // Check for OpenID or InCommon usage.
    case 'Continue': // For OOI
        $providerIdPost = util::getPostVar('providerId');
        if ($idplist->exists($providerIdPost)) { // Use InCommon authn
            util::setCookieVar('providerId',$providerIdPost);
            redirectToTestIdP($providerIdPost);
        } else { // Either providerId not set or not in whitelist
            util::unsetCookieVar('providerId');
            printLogonPage();
        }
    break; // End case 'Log On'

    case 'Cancel': // Cancel button on WAYF page - go to Google
        header('Location: http://www.google.com/');
    break;

    case 'Show  Help ': // Toggle showing of help text on and off
    case 'Hide  Help ':
        if (util::getSessionVar('showhelp') == 'on') {
            util::unsetSessionVar('showhelp');
        } else {
            util::setSessionVar('showhelp','on');
        }
        printLogonPage();
    break; // End case 'Show Help' / 'Hide Help'

    default: // No submit button clicked nor PHP session submit variable set
        printLogonPage();
    break; // End default case

} // End switch($submit)


/************************************************************************
 * Function   : printLogonPage                                          *
 * This function prints out the HTML for the IdP Selector page.         *
 * Explanatory text is shown as well as a button to log in to an IdP    *
 * and get rerouted to the Shibboleth protected testidp script.         *
 ************************************************************************/
function printLogonPage() {
    printHeader('Test Your Identity Provider With CILogon');

    echo '
    <div class="boxed">
    ';

    printHelpButton();

    echo '
      <br />
      <p>
      To test that your identity provider works with CILogon, please select
      it from the list below and Log On.
      </p>
    ';
    
    printWAYF(false,true);

    echo '
    </div> <!-- End boxed -->
    ';
    printFooter();
}

/************************************************************************
 * Function   : redirectToTestIdP                                       *
 * Parameters : (1) An entityId of the authenticating IdP.  If not      *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 * If the first parameter (a whitelisted entityId) is not specified,    *
 * we check to see if either the providerId PHP session variable or the *
 * providerId cookie is set (in that order) and use one if available.   *
 * Then this function redirects to the "/secure/testidp/" script so as  *
 * to do a Shibboleth authentication via mod_shib.  When the providerId *
 * is non-empty, the SessionInitiator will automatically go to that IdP *
 * (i.e. without stopping at a WAYF).                                   *
 ************************************************************************/
function redirectToTestIdP($providerId='') {
    // If providerId not set, try the cookie value
    if (strlen($providerId) == 0) {
        $providerId = util::getCookieVar('providerId');
    }
    
    // Set up the "header" string for redirection thru mod_shib
    $testidp_url = 'https://' . HOSTNAME . '/secure/testidp/';
    $redirect = 
        'Location: https://' . HOSTNAME . '/Shibboleth.sso/Login?' .
        'target=' . urlencode($testidp_url);
    if (strlen($providerId) > 0) {
        $redirect .= '&providerId=' . urlencode($providerId);
    }

    header($redirect);
}

?>
