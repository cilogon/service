<?php

require_once('include/autoloader.php');
require_once('include/content.php');
require_once('include/util.php');

startPHPSession();

/* Check the csrf cookie against either a hidden <form> element or a   *
 * PHP session variable, and get the value of the "submit" element.    */
$submit = csrf::verifyCookieAndGetSubmit();

/* The full URL of the Shibboleth-protected getuser script.            */
define('GETUSER_URL','https://cilogon.org/secure/getuser/');

/* "providerId" and "keepidp" can be set in cookies and/or by a form   *
 * submit.  "providerId" corresonds to the user-selected Idp.          *
 * "keepidp" corresponds to the "Remember this selection" checkbox and *
 * allows the user to bypass the Welcome page on subsequent visits.    */
$providerIdCookie = urldecode(getCookieVar('providerId'));
$providerIdPost = getPostVar('providerId');
$keepidpCookie = getCookieVar('keepidp');
$keepidpPost = getPostVar('keepidp');

/* Read in the whitelist of currently available IdPs.                  */
$white = new whitelist();

/* If both the "keepidp" and the "providerId" cookies were set (and    *
 * the providerId is a whitelisted IdP) then skip the Welcome page and *
 * proceed to the getuser script.                                      */
if ((strlen($providerIdCookie) > 0) && 
    (strlen($keepidpCookie) > 0) &&
    ($white->exists($providerIdCookie))) {
    redirectToGetuser($providerIdCookie);

/* Else, if the user clicked the WAYF "Log On" button on the Welcome    *
 * page and the selected IdP is in the whitelist, then set cookies for *
 * "providerId" and "keepidp" (if the checkbox was checked).  Then     *
 * proceed to the getuser script.                                      */
} elseif (($submit == "Log On") &&
          (strlen($providerIdPost) > 0) &&
          ($white->exists($providerIdPost))) {
    setcookie('providerId',$providerIdPost,time()+60*60*24*365,'/','',true);
    if (strlen($keepidpPost) > 0) {
        setcookie('keepidp','checked',time()+60*60*24*365,'/','',true);
    } else {
        setcookie('keepidp','',time()-3600,'/','',true);
    }
    redirectToGetuser($providerIdPost);
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
      &quot;Log On&quot; button.  You will be redirected to your
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
      href="/requestidp/">make a request for your organization</a> to appear
      in the list of available organizations.
      </p>
    </div>
    ';

    printWAYF();

    printFooter();
}

/************************************************************************
 * Function   : redirectToGetuser                                       *
 * Parameter  : (Optional) An entityID of the authenticating IdP.       *
 * This function redirects to the "/secure/getuser/" script so as to    *
 * do a Shibboleth authentication via the InCommon WAYF.  If the        *
 * optional parameter (a whitelisted entityID) is specified, the WAYF   *
 * will automatically go to that IdP (i.e. without stopping at the      *
 * WAYF).  This function also sets several PHP session variables that   *
 * are needed by the getuser script.                                    *
 ************************************************************************/
function redirectToGetuser($providerId='')
{
    // Set PHP session varilables needed by the getuser script
    $_SESSION[csrf::tokenname] = csrf::getTheCookie();
    $_SESSION['responseurl'] = getScriptDir(true);
    $_SESSION['submit'] = 'getuser';

    // Set up the "header" string for redirection thru InCommon WAYF
    $redirect = 'Location: https://cilogon.org/Shibboleth.sso/WAYF/InCommon?' .
        'target=' . urlencode(GETUSER_URL);
    if (strlen($providerId) > 0) {
        $redirect .= '&providerId=' . urlencode($providerId);
    }
    header($redirect);
}

?>
