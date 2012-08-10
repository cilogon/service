<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');

// Check for a Shibboleth error and handle it
$shiberror = new shiberror();

/* Read in the list of currently available IdPs. */
$idplist = new idplist();

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  *
 * Note: replace CR/LF with space for "Show/Hide Help" buttons.      */
$retchars = array("\r\n","\n","\r");
$submit = str_replace($retchars," ",csrf::verifyCookieAndGetSubmit());
unsetSessionVar('submit');

/* Depending on the value of the clicked "submit" button or the    *
 * equivalent PHP session variable, take action or print out HTML. */
switch ($submit) {

    case 'Log On': // Check for OpenID or InCommon usage.
    case 'Continue': // For OOI
        $providerIdPost = getPostVar('providerId');
        if ($idplist->exists($providerIdPost)) { // Use InCommon authn
            setCookieVar('providerId',$providerIdPost);
            redirectToTestIdP($providerIdPost);
        } else { // Either providerId not set or not in whitelist
            unsetCookieVar('providerId');
            printLogonPage();
        }
    break; // End case 'Log On'

    case 'Cancel': // Cancel button on WAYF page - go to Google
        header('Location: http://www.google.com/');
    break;

    case "Show Help": // Toggle showing of help text on and off
    case "Hide Help":
        if (getSessionVar('showhelp') == 'on') {
            unsetSessionVar('showhelp');
        } else {
            setSessionVar('showhelp','on');
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
    printHeader('Test Your Identity Provider With CILogon',
                '<style type="text/css">' .
                'div.logoheader h1 {background: transparent ' .
                'url("/images/cilogon-header.png") no-repeat top left;' . 
                'width:273px;height:64px;}' .
                '</style>');

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
    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = getCookieVar('providerId');
        }
    }
    
    // Set up the "header" string for redirection thru mod_shib
    /*
    $hostname = getMachineHostname();
    $testidp_url = "https://" . $hostname . "/secure/testidp/";
    $redirect = 
        "Location: https://" . $hostname . "/Shibboleth.sso/Login?" .
        'target=' . urlencode($testidp_url);
    */
    $testidp_url = 'https://' . HOSTNAME . '/secure/testidp/';
    $redirect = 
        'Location: https://' . HOSTNAME . '/Shibboleth.sso/Login?' .
        'target=' . urlencode($testidp_url);
    if (strlen($providerId) > 0) {
        $redirect .= '&providerId=' . urlencode($providerId);

        // For Silver IdPs, send extra parameter
        if (strlen(getPostVar('silveridp')) > 0) {
            $redirect .= '&authnContextClassRef=' . 
                urlencode('http://id.incommon.org/assurance/silver-test');
        }
    }

    header($redirect);
}

?>