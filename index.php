<?php

require_once('include/autoloader.php');
require_once('include/content.php');
require_once('include/util.php');

startPHPSession();

/* The full URL of the Shibboleth protected service script.            */
$serviceurl = 'https://cilogon.org/secure/env3.php';

/* The text to be displayed on the "Log in to your IdP" button         */
DEFINE('LOGIN_BUTTON_TEXT','Log in to your IdP and Get a Credential');

/* If the user clicked a "Submit" button, get the text of the button.  */
$submit = getPostVar('submit');

/* The "providerId" cookie is set by the Shibboleth protected service  *
 * script.  It corresponds to the last authenticated IdP's entityID.   */
$providerId = getCookieVar('providerId');

/* Check if the user had previously selected the "Go directly to       *
 * your IdP" option and if so, reroute through the local WAYF to get   *
 * to the $serviceurl script.                                          */
if (strlen(getCookieVar('godirect')) > 0) {
    redirectToSecure($serviceurl,$providerId);
} elseif ($submit == LOGIN_BUTTON_TEXT) {
    /* If the user clicked the "Log in to your IdP" button, then       *
     * set the cookie for the "Go directly to your IdP" (if necessary) *
     * and then go directly to the $secureurl script.  Since the       *
     * $secureurl is in a Shibboleth protected directory, the WAYF     *
     * will be invoked if there is no Shibboleth authentication yet.   */
    $godirect = intval(getPostVar('godirect'));
    if ($godirect > 0) {
        setcookie('godirect',$godirect,time()+60*60*24*$godirect,'/','',true);
    }
    header("Location: $serviceurl");
} else { 
    /* Default action - simply print the main Login page */
    printLoginPage();
}

/************************************************************************
 * Function   : printLoginPage                                          *
 * This function prints out the HTML for the main cilogon.org page.     *
 * Explanatory text is shown as well as a button to log in to an IdP    *
 * and get rerouted to the Shibboleth protected service script.         *
 ************************************************************************/
function printLoginPage()
{
    printHeader('Welcome to the CILogon Service');

    printPageHeader('Welcome to the CILogon Service');
    echo '
    <div class="welcome">
      <div class="boxheader">
        About The CILogon Service
      </div>
      <h2>What Is The CILogon Service?</h2>
      <p>
      The CILogon Service allows users to authenticate
      with their home organization and obtain a
      certificate for secure access to <a target="_blank"
      href="http://www.nsf.gov/">NSF</a> <a target="_blank"
      href="http://www.nsf.gov/oci">CyberInfrastructure</a> (<acronym
      title="CyberInfrastructure">CI</acronym>) projects. Additional
      information can be found at <a target="_blank"
      href="http://www.cilogon.org/service">www.cilogon.org</a>.
      </p>
      <p class="equation">
      <span>CILogon + Your Organization = Secure Access to 
      <acronym title="National Science Foundation">NSF</acronym>
      <acronym title="CyberInfrastructure">CI</acronym></span>
      </p>
      <h2>How Does The CILogon Service Work?</h2>
      <p>
      The CILogon Service is a member of <a target="_blank"
      href="http://www.incommonfederation.org/">InCommon</a>, a formal
      federation of over 200 universities, agencies, and organizations.
      Many of these organizations maintain an authentication service to
      provide their users with web single sign-on.  An InCommon organization
      can partner with the CILogon Service to provide user information for
      the purpose of issuing certificates.  These certificates can then be
      used for accessing cyberinfrastructure resources.
      </p>
      <h2>How Do I Use The CILogon Service?</h2>
      <p>
      Select your organization from the drop-down list, then click the
      &quot;Logon&quot; button.  You will be redirected to your
      organization\'s login page.  After you authenticate with your
      organization as you typically would, you will be redirected back to
      the CILogon Service.  Then you will be able to fetch a
      certificate for use with cyberinfrastructure resources.  
      </p>
      <h2>What If I Don\'t See My Organization Listed?</h2>
      <p>
      If you don\'t have an account with any of the organizations listed in
      the drop-down list in the &quot;Start Here&quot; menu, you can
      register for a free user account at <a target="_blank"
      href="http://www.protectnetwork.org/">ProtectNetwork</a> for use with
      the CILogon Service.  Alternatively, you can <a target="_blank"
      href="requestidp">make a request for your organization</a> to appear
      in the list of available organizations.
      </p>
    </div>
    ';

    printWAYF();

    printFooter();
}

/************************************************************************
 * Function   : redirectToSecure                                        *
 * Parameters : (1) The full URL of the Shibboleth protected script.    *
 *              (2) (Optional) A urlencoded entityID of the             *
 *                  authenticating IdP.                                 *
 *              (3) (Optional) A string of additional "key=value"       *
 *                  pairs, separated by '&'s and urlencoded.            *
 * This function takes in the full URL of a Shibboleth protected script *
 * and redirects through the local Discovery Service WAYF so as to do a *
 * Shibboleth authentication.  If the second parameter (a urlencoded    *
 * entityID) is specified, the WAYF will automatically go to that IdP   *
 * without stopping at the WAYF.  This function verifies that the       *
 * given providerId entityID exists in the local whitelist for the WAYF *
 * so as to prevent non-verified IdPs from being used.  The third       *
 * parameter is utilized for any additional key=value pairs that should *
 * be passed to the Shibboleth protected script.  These pairs should    *
 * be separated (but not prefixed) by an ampersand (&) and urlencoded.  *
 ************************************************************************/
function redirectToSecure($target,$providerId='',$extra='')
{
    $redirect = 'Location: https://cilogon.org/Shibboleth.sso/WAYF?' .
        'target=' . urlencode($target);
    if (strlen($providerId) > 0) {
        $white = new whitelist();
        $white->read();
        if ($white->exists(urldecode($providerId))) {
            $redirect .= '&providerId=' . $providerId;
        }
    }
    if (strlen($extra) > 0) {
        $redirect .= '&' . $extra;
    }
    header($redirect);
}

?>
