<?php

require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('../include/shib.php');
require_once('../include/util.php');

startPHPSession();

/* The full URL of the Shibboleth-protected getuser script. */
define('GETUSER_URL','https://cilogon.org/secure/getuser/');

/* Read in the whitelist of currently available IdPs. */
$white = new whitelist();

/* Loggit object for logging info to syslog. */
$log = new loggit();

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();
unsetSessionVar('submit');

$log->info('submit="' . $submit . '"');

/* First, check to see if the info related to the 'oauth_token' passed *
 * from the Community Portal exists in the current PHP session.  If    *
 * so, then continue processing based on 'submit' value.  Otherwise,   *
 * print out error message about bad or missing oauth_token info.      */
if (verifyOAuthToken(getGetVar('oauth_token'))) {
    /* Depending on the value of the clicked "submit" button or the    *
     * equivalent PHP session variable, take action or print out HTML. */
    switch ($submit) {

        case 'Log On': // User selected an IdP - go to getuser script
            // Verify that providerId is set and is in the whitelist
            $providerIdPost = getPostVar('providerId');
            if ((strlen($providerIdPost) > 0) &&
                ($white->exists($providerIdPost))) {
                setcookie('providerId',$providerIdPost,
                          time()+60*60*24*365,'/','',true);
                // Set the cookie for keepidp if the checkbox was checked
                if (strlen(getPostVar('keepidp')) > 0) {
                    setcookie('keepidp','checked',time()+60*60*24*365,'/','',true);
                } else {
                    setcookie('keepidp','',time()-3600,'/','',true);
                }
                redirectToGetuser($providerIdPost);
            } else { // Either providerId not set or not in whitelist
                printLogonPage();
            }
        break; // End case 'Log On'

        case 'gotuser': // Return from the getuser script
            handleGotUser();
        break; // End case 'gotuser'

        case 'Proceed': // Proceed after 'User Changed' paged
            // Verify the PHP session contains valid info
            if (verifyCurrentSession()) {
                printAllowDelegationPage();
            } else { // Otherwise, redirect to the 'Welcome' page
                removeShibCookies();
                unsetGetUserSessionVars();
                printLogonPage();
            }
        break; // End case 'Proceed'

        default: // No submit button clicked nor PHP session submit variable set
            /* If both the "keepidp" and the "providerId" cookies were set 
             * (and the providerId is a whitelisted IdP) then skip the 
             * Logon page and proceed to the getuser script.  */
            $providerIdCookie = urldecode(getCookieVar('providerId'));
            if ((strlen($providerIdCookie) > 0) && 
                (strlen(getCookieVar('keepidp')) > 0) &&
                ($white->exists($providerIdCookie))) {
                redirectToGetuser($providerIdCookie);
            } else { // One of the cookies for providerId or keepidp was not set
                printLogonPage();
            }
        break; // End default case

    } // End switch ($submit)
} else { // Failed to verify oauth_token info in PHP session
    printBadOAuthTokenPage();
}

/************************************************************************
 * Function   : printLogonPage                                          *
 * This function prints out the HTML for the main cilogon.org page.     *
 * Explanatory text is shown as well as a button to log in to an IdP    *
 * and get rerouted to the Shibboleth protected service script.         *
 ************************************************************************/
function printLogonPage()
{
    printHeader('Welcome To The CILogon Delegation Service');
    printPageHeader('Welcome To The CILogon Delegation Service');

    echo '
    <div class="welcome">
      <div class="boxheader">
        About The CILogon Delegation Service
      </div>
      <h2>What Is The CILogon Delegation Service?</h2>
      <p>
      The CILogon Delegation Service allows community portals to get a
      certificate on behalf of a user.  Once a user authenticates
      with their home organization, the CILogon Delegation Service 
      delegates a certficate back to the user\'s community portal.
      </p>
      <p>
      Below is the community portal requesting a certificate to be delegated
      on your behalf.  If this information does not look correct, do not
      proceed.
      </p>
      <div class="portalinfo">
      <xmp> Portal Name: ' . getSessionVar('portalname') . '
' .       ' Portal URL : ' . getSessionVar('successuri') . 
      '</xmp>
      </div>
      <h2>How Does The CILogon Delegation Service Work?</h2>
      <p>
      The CILogon Service is a member of <a target="_blank"
      href="http://www.incommonfederation.org/">InCommon</a>, a formal
      federation of over 200 universities, agencies, and organizations.
      Many of these organizations maintain an authentication service to
      provide their users with web single sign-on.  An InCommon organization
      can partner with the CILogon Service to provide user information for
      the purpose of issuing certificates.  These certificates can then be
      delegated to community portals for use by their users.
      </p>
      <h2>How Do I Use The CILogon Delegation Service?</h2>
      <p>
      Select your organization from the drop-down list, then click the
      &quot;Log On&quot; button.  You will be redirected to your
      organization\'s login page.  After you authenticate with your
      organization as you typically would, you will be redirected back to
      the CILogon Delegation Service.  Then you will be able to delegate a
      certificate to your community portal.  
      </p>
      <h2>What If I Don\'t See My Organization Listed?</h2>
      <p>
      If you don\'t have an account with any of the organizations listed in
      the drop-down list in the &quot;Start Here&quot; menu, you can
      register for a free user account at <a target="_blank"
      href="http://www.protectnetwork.org/">ProtectNetwork</a> for use with
      the CILogon Delegation Service.  Alternatively, you can <a target="_blank"
      href="/requestidp/">make a request for your organization</a> to appear
      in the list of available organizations.
      </p>
      <p class="note">
      <strong>Note:</strong> You must enable cookies in your web browser to
      use this site.
      </p>
    </div>
    ';

    printWAYF();

    printFooter();
}

/************************************************************************
 * Function   : printBadOAuthTokenPage                                  *
 * This function prints out the HTML for the page when the oauth_token  *
 * (tempcred) or associated OAuth information is missing, bad, or       *
 * expired.                                                             *
 ************************************************************************/
function printBadOAuthTokenPage()
{
    printHeader('CILogon Delegation Service');
    printPageHeader('This Is The CILogon Delegation Service');

    echo '
    <div class="boxed">
      <div class="boxheader">
        The CILogon Delegation Service Is For Delegating Certificates To 
        Portals
      </div>
      <p>
      You have reached the CILogon Delegation Service.  This service is for
      use by Community Portals to obtain certificates for their users.  
      End users should not normally see this page.
      </p>
      <p>If you arrived at this page from a community portal, there was a
      problem with the delegation process.
      Please return to your portal and try again.  If the error persists,
      please contact us at the email address at the bottom of the page.
      </p>
      <p>
      If you are an individual wishing to download a certificate to your
      local computer, please try the <a target="_blank"
      href="https://cilogon.org/">CILogon Service</a>.
      </p>
      <p class="note">
      <strong>Note:</strong> You must enable cookies in your web browser to
      use this site.
      </p>
    </div>
    ';

    printFooter();
}

/************************************************************************
 * Function   : printAllowDelegationPage                                *
 * This function prints out the HTML for the main page where the user   *
 *  
 ************************************************************************/
function printAllowDelegationPage()
{
    // FIXME!!!
    $scriptdir = getScriptDir();

    $lifetimetext = "Enter the lifetime of the certificate to be delegated to the portal. Time is in hours. Valid entries are between 1 and 240 hours (inclusive).";
    $remembertext = "By checking this box, you permit future delegations to this portal without your explicit approval (i.e., you will automatically bypass this page for this portal). The parameters related to the delegation (e.g., certificate lifetime) will be remembered. You will need to clear your browser's cookies to return here."; 

    printHeader('Confirm Allow Delegation');
    printPageHeader('Welcome ' . getSessionVar('idpname') . ' User');

    echo '
    <div class="boxed">
      <div class="boxheader">
        Confirm That You Want To Delegate A Certificate
      </div>
    <p>
    You are logged on to the CILogon Delegation Service.  The portal below is
    requesting a delegated certificate for use on your behalf.  You must now
    allow (or deny) this delegation to occur.  Please look at the
    information provided by the portal below.  If this information appears
    correct, then allow the delegation to occur.  Otherwise, deny the
    request, or navigate away from this page.
    </p>

    <div class="portalinfo">
      <xmp> Portal Name   : ' . getSessionVar('portalname') . '
' .       ' Portal URL    : ' . getSessionVar('successuri') . '
' .       ' Delegation URL: ' . getSessionVar('callbackuri') . 
    '</xmp>
    </div>

    <div class="allowdiv">
    <table>
    <tr>
    <td>
    ';

    printFormHead($scriptdir);

    echo '
    <p>
    <label for="lifetime" title="'.$lifetimetext.'" 
    class="helpcursor">Certificate Lifetime (in hours):</label>
    <input type="text" name="lifetime" id="lifetime" title="'.
    $lifetimetext.'" class="helpcursor" size="3" maxlength="3" 
    value="12" />
    </p>
    <p>
    <input type="submit" name="submit" class="submit" value="Allow" />
    </p>
    <p>
    <input type="submit" name="submit" class="submit helpcursor" 
    title="'.$remembertext.'"
    value="Always Allow" />
    </p>
    </form>
    </td>
    <td>
    ';

    printFormHead($scriptdir);

    echo '
    <p>
    <input type="submit" name="submit" class="submit" value="Deny" />
    </p>
    </form>
    </td>
    </tr>
    </table>
    </div>
    </div>
    ';
    printFooter();
}

/************************************************************************
 * Function   : printFormHead                                           *
 * Parameters : (1) The value of the form's "action" parameter.         *
 *              (2) (Optional) True if extra hidden tags should be      *
 *                  output for the GridShib-CA client application.      *
 *                  Defaults to false.                                  *
 * This function prints out the opening <form> tag for displaying       *
 * submit buttons.  The first parameter is used for the "action" value  *
 * of the <form>.  This function outputs a hidden csrf field in the     *
 * form block.  If the second parameter is given and set to true, then  *
 * additional hidden input elements are also output to be used when the *
 * the GridShib-CA client launches.                                     *
 ************************************************************************/
function printFormHead($action) {
    global $csrf;

    echo '
    <form action="' . $action . '" method="post">
    ';
    echo $csrf->getHiddenFormElement();
}

/************************************************************************
 * Function   : handleGotUser                                           *
 * This function is called upon return from the "secure/getuser" script *
 * which should have set the 'uid' and 'status' PHP session variables.  *
 * It verifies that the status return is one of STATUS_OK_* (even       *
 * values).  If the return is STATUS_OK_
 ************************************************************************/
function handleGotUser()
{
    $uid = getSessionVar('uid');
    $status = getSessionVar('status');
    # If empty 'uid' or 'status' or odd-numbered status code, error!
    if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
        unsetGetUserSessionVars();
        printHeader('Error Logging On');
        printPageHeader('ERROR Logging On');

        echo '
        <div class="boxed">
          <div class="boxheader">
            Unable To Log On
          </div>
        ';
        printErrorBox('An internal error has occurred.  System
            administrators have been notified.  This may be a temporary
            error.  Please try again later, or contact us at the the email
            address at the bottom of the page.');

        echo '
        <div>
        ';
        printFormHead(getScriptDir());
        echo '
        <input type="submit" name="submit" class="submit" value="Proceed" />
        </form>
        </div>
        </div>
        ';
        printFooter();
    } else { // Got one of the STATUS_OK* status codes
        // If the user got a new DN due to changed SAML attributes,
        // print out a notification page.
        $store = new store();
        if ($status == $store->STATUS['STATUS_OK_USER_CHANGED']) {
            printUserChangedPage();
        } else { // STATUS_OK or STATUS_OK_NEW_USER
            printAllowDelegationPage();
        }
    }
}

/************************************************************************
 * Function   : printUserChangedPage                                    *
 * This function prints out a notification page informing the user that *
 * some of their attributes have changed, which will affect the         *
 * contents of future issued certificates.  This page shows which       *
 * attributes are different (displaying both old and new values) and    *
 * what portions of the certificate are affected.                       *
 ************************************************************************/
function printUserChangedPage()
{
    $uid = getSessionVar('uid');
    $store = new store();
    $store->getUserObj($uid);
    if (!($store->getUserSub('status') & 1)) {  // STATUS_OK codes are even
        $idpname = $store->getUserSub('idpDisplayName');
        $first   = $store->getUserSub('firstName');
        $last    = $store->getUserSub('lastName');
        $email   = $store->getUserSub('email');
        $dn      = $store->getUserSub('getDN');
        $dn      = preg_replace('/\s+email=.+$/','',$dn);
        $store->getLastUserObj($uid);
        if (!($store->getUserSub('status') & 1)) {  // STATUS_OK codes are even
            $previdpname = $store->getUserSub('idpDisplayName');
            $prevfirst   = $store->getUserSub('firstName');
            $prevlast    = $store->getUserSub('lastName');
            $prevemail   = $store->getUserSub('email');
            $prevdn      = $store->getUserSub('getDN');
            $prevdn      = preg_replace('/\s+email=.+$/','',$prevdn);

            $tablerowodd = true;

            printHeader('Certificate Information Changed');
            printPageHeader('Notice: User Information Changed');

            echo '
            <div class="boxed">
              <div class="boxheader">
                Some Of Your Information Has Changed
              </div>
            <p>
            One or more of the attributes released by your organization has
            changed since the last time you logged on to the CILogon
            Delegation Service.  This will affect your certificates as
            described below.
            </p>

            <div class="userchanged">
            <table cellpadding="5">
              <tr class="headings">
                <th>Attribute</th>
                <th>Previous Value</th>
                <th>Current Value</th>
              </tr>
            ';

            if ($idpname != $previdpname) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>Organization Name:</th>
                  <td>'.$previdpname.'</td>
                  <td>'.$idpname.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($first != $prevfirst) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>First Name:</th>
                  <td>'.$prevfirst.'</td>
                  <td>'.$first.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($last != $prevlast) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>Last Name:</th>
                  <td>'.$prevlast.'</td>
                  <td>'.$last.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($email != $prevemail) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>Email Address:</th>
                  <td>'.$prevemail.'</td>
                  <td>'.$email.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            echo '
            </table>
            </div>
            ';

            if (($idpname != $previdpname) ||
                ($first != $prevfirst) ||
                ($last != $prevlast)) {
                echo '
                <p>
                The above changes to your attributes will cause your
                <strong>certificate subject</strong> to change.  You may be
                required to re-register with relying parties using this new
                certificate subject.
                </p>
                <p>
                <blockquote>
                <table cellspacing="0">
                  <tr>
                    <td>Previous Subject DN:</td>
                    <td>' . $prevdn . '</td>
                  </tr>
                  <tr>
                    <td>Current Subject DN:</td>
                    <td>' . $dn . '</td>
                  </tr>
                </table>
                </blockquote>
                </p>
                ';
            }

            if ($email != $prevemail) {
                echo '
                <p>
                Your new certificate will contain your <strong>updated email
                address</strong>.
                This may change how your certificate may be used in email
                clients.  Possible problems which may occur include:
                </p>
                <ul>
                <li>If your "from" address does not match what is contained in
                    the certificate, recipients may fail to verify your signed
                    email messages.</li>
                <li>If the email address in the certificate does not match the
                    destination address, senders may have difficulty encrypting
                    email addressed to you.</li>
                </ul>
                ';
            }

            echo '
            <p>
            If you have any questions, please contact us at the email
            address at the bottom of the page.
            </p>
            <div>
            ';
            printFormHead(getScriptDir());
            echo '
            <input type="submit" name="submit" class="submit" 
             value="Proceed" />
            </form>
            </div>
            </div>
            ';
            printFooter();

            
        } else {  // Database error, should never happen
            unsetGetUserSessionVars();
            printLogonPage();
        }
    } else {  // Database error, should never happen
        unsetGetUserSessionVars();
        printLogonPage();
    }
    
}

/************************************************************************
 * Function   : unsetGetUserSessionVars                                    *
 * This function removes all of the PHP session variables related to    *
 * the 'secure/getuser' script.  This will force the user to log on     *
 * (again) with their IdP and call the 'getuser' script to repopulate   *
 * the PHP session.                                                     *
 ************************************************************************/
function unsetGetUserSessionVars()
{
    unsetSessionVar('submit');
    unsetSessionVar('uid');
    unsetSessionVar('status');
    unsetSessionVar('loa');
    unsetSessionVar('idp');
    unsetSessionVar('idpname');
}

/************************************************************************
 * Function   : verifyOAuthToken                                        *
 * Parameter  : (Optional) The temporary credential passed from a       *
 *              Community Portal to the 'delegate' script as            *
 *              "oauth_token" in the URL (as a $_GET variable).         *
 *              Defaults to empty string.                               *
 * Returns    : True if the various parameters related to the OAuth     *
 *              token (callbackuri, failureuri, successuri, portalname, *
 *              and tempcred) are in the PHP session, false otherwise.  *
 * This function verifies that all of the various PortalParameters      *
 * have been set in the PHP session.  If the first parameter is passed  *
 * in, it first attempts to call CILogon::getPortalParameters() and     *
 * populates the PHP session with the associated values.                *
 ************************************************************************/
function verifyOAuthToken($token='')
{
    $retval = false; // Assume OAuth session info is not valid

    // If passing in the OAuth $token, try to get the associated info
    // from the persistent store and put it into the PHP session.
    if (strlen($token) > 0) {
        $store = new store();
        $store->getPortalObj($token);
        $status = $store->getPortalSub('status');
        setOrUnsetSessionVar('portalstatus',$status);
        if (!($status & 1)) {  // STATUS_OK* codes are even-numbered
            setOrUnsetSessionVar('callbackuri',
                $store->getPortalSub('callbackUri'));
            setOrUnsetSessionVar('failureuri',
                $store->getPortalSub('failureUri'));
            setOrUnsetSessionVar('successuri',
                $store->getPortalSub('successUri'));
            setOrUnsetSessionVar('portalname',
                $store->getPortalSub('name'));
            setOrUnsetSessionVar('tempcred',
                $store->getPortalSub('tempCred'));
        }
    }

    // Now check to verify all session variables have data
    if ((strlen(getSessionVar('callbackuri')) > 0) &&
        (strlen(getSessionVar('failureuri')) > 0) &&
        (strlen(getSessionVar('successuri')) > 0) &&
        (strlen(getSessionVar('portalname')) > 0) &&
        (strlen(getSessionVar('tempcred')) > 0) &&
        (!(getSessionVar('portalstatus') & 1))) { // STATUS_OK* are even
        $retval = true;
    }

    return $retval;
}

/************************************************************************
 * Function   : verifyCurrentSession                                    *
 * Parameter  : (Optional) The user-selected Identity Provider          *
 * Returns    : True if the contents of the PHP session ar valid,       *
 *              False otherwise.                                        *
 * This function verifies the contents of the PHP session.  It checks   *
 * the following:                                                       *
 * (1) The persistent store 'uid', the Identity Provider 'idp', the     *
 *     IdP Display Name 'idpname', and the 'status' (of getUser()) are  *
 *     all non-empty strings.                                           *
 * (2) The 'status' (of getUser()) is even (i.e., STATUS_OK_*).         *
 * (3) If $providerId is passed-in, it must match 'idp'.                *
 * If all checks are good, then this function returns true.             *
 ************************************************************************/
function verifyCurrentSession($providerId='') 
{
    $retval = false;

    $uid = getSessionVar('uid');
    $idp = getSessionVar('idp');
    $idpname = getSessionVar('idpname');
    $status = getSessionVar('status');
    if ((strlen($uid) > 0) && (strlen($idp) > 0) && 
        (strlen($idpname) > 0) && (strlen($status) > 0) &&
        (!($status & 1))) {  // All STATUS_OK_* codes are even
        if ((strlen($providerId) == 0) || ($providerId == $idp)) {
            $retval = true;
        }
    }

    return $retval;
}

/************************************************************************
 * Function   : redirectToGetuser                                       *
 * Parameters : (1) An entityID of the authenticating IdP.  If not      *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 *              (2) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 * If the first parameter (a whitelisted entityID) is not specified,    *
 * we check to see if either the providerId PHP session variable or the *
 * providerId cookie is set (in that order) and use one if available.   *
 * The function then checks to see if there is a valid PHP session      *
 * and if the providerId matches the 'idp' in the session.  If so, then *
 * we don't need to redirect to "/secure/getuser/" and instead we       *
 * we display the main "Allow Delegation" page.  However, if the        *
 * PHP session is not valid, then this function redirects to the        *
 * "/secure/getuser/" script so as to do a Shibboleth authentication    *
 * via the InCommon WAYF.  When the providerId is non-empty, the WAYF   *
 * will automatically go to that IdP (i.e., without stopping at the     *
 * WAYF).  This function also sets several PHP session variables that   *
 * are needed by the getuser script, including the 'responsesubmit'     *
 * variable which is set as the return 'submit' variable in the         *
 * 'getuser' script.                                                    *
 ************************************************************************/
function redirectToGetuser($providerId='',$responsesubmit='gotuser')
{
    global $csrf;

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = getCookieVar('providerId');
        }
    }

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the 'Allow Delegation' page.
    if (verifyCurrentSession($providerId)) {
        printAllowDelegationPage();
    } else { // Otherwise, redirect to the getuser script
        // Set PHP session varilables needed by the getuser script
        $_SESSION['responseurl'] = getScriptDir(true);
        $_SESSION['submit'] = 'getuser';
        $_SESSION['responsesubmit'] = $responsesubmit;
        $csrf->setTheCookie();
        $csrf->setTheSession();

        // Set up the "header" string for redirection thru InCommon WAYF
        $redirect = 
            'Location: https://cilogon.org/Shibboleth.sso/WAYF/InCommon?' .
            'target=' . urlencode(GETUSER_URL);
        if (strlen($providerId) > 0) {
            $redirect .= '&providerId=' . urlencode($providerId);
        }
        header($redirect);
    }
}

?>
