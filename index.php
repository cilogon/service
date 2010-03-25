<?php

require_once('include/autoloader.php');
require_once('include/session.php');
require_once('include/content.php');
require_once('include/util.php');


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

    echo '
    <div id="pageHeader">
      <h1><span>Welcome to the CILogon Service</span></h1>
      <h2><span>Authenticate with Your Organization and Retrieve a
      Credential</span></h2>
    </div>

    <div id="summaryDiv">
      <p class="p1"><span>The CILogon Service allows users to authenticate
      with their organization\'s Identity Provider (<acronym 
      title="Identity Provider">IdP</acronym>) and obtain an X.509
      credential for secure access to cyberinfrastructure (<acronym
      title="CyberInfrastructure">CI</acronym>).</span></p>

      <div id="buttonDiv">
        <form action="' . basename(__FILE__) . '" method="post">
          <input class="submit" type="submit" name="submit"
                 value="' . LOGIN_BUTTON_TEXT . '" />
          <p></p>
          Go directly to your <acronym 
          title="Identity Provider">IdP</acronym>:&nbsp;<select
          name="godirect">
            <option value="0" selected="selected">Never</option>
            <option value="7">For One Week</option>
            <option value="31">For One Month</option>
            <option value="365">For One Year</option>
          </select>&nbsp;<a class="tooltip" href=""><img
          src="images/infoIcon.png" alt="Help"
          width="14" height="14" /><b><em
          class="outer"></em><em class="inner"></em>If you select a value
          other than "Never", you can bypass this page and proceed directly
          to the last used IdP\'s authentication page. You will need to
          clear your browser\'s cookies to return here.</b></a>
        </form>
      </div>
    </div>
    ';

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
