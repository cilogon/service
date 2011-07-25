<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('../include/shib.php');
require_once('Auth/OpenID/Consumer.php');

/* The full URL of the 'delegation/authorized' OAuth script. */
define('AUTHORIZED_URL','http://localhost:8080/delegation/authorized');

/* Read in the whitelist of currently available IdPs. */
$white = new whitelist();

/* Loggit object for logging info to syslog. */
$log = new loggit();

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  *
 * Note: replace CR/LF with space for "Show/Hide Help" buttons.      */
$submit = str_replace("\r\n"," ",csrf::verifyCookieAndGetSubmit());
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

        case 'Log On': // Check for OpenID or InCommon usage.
        case 'Continue': // For OOI
            // Set the cookie for keepidp if the checkbox was checked
            if (strlen(getPostVar('keepidp')) > 0) {
                setcookie('keepidp','checked',time()+60*60*24*365,'/','',true);
            } else {
                setcookie('keepidp','',time()-3600,'/','',true);
            }
            $providerIdPost = getPostVar('providerId');
            if (openid::urlExists($providerIdPost)) { // Use OpenID authn
                setcookie('providerId',$providerIdPost,
                          time()+60*60*24*365,'/','',true);
                redirectToGetOpenIDUser($providerIdPost);
            } elseif ($white->exists($providerIdPost)) { // Use InCommon authn
                setcookie('providerId',$providerIdPost,
                          time()+60*60*24*365,'/','',true);
                redirectToGetUser($providerIdPost);
            } else { // Either providerId not set or not in whitelist
                setcookie('providerId','',time()-3600,'/','',true);
                printLogonPage();
            }
        break; // End case 'Log On'

        case 'gotuser': // Return from the getuser script
            handleGotUser();
        break; // End case 'gotuser'

        case 'Proceed': // Proceed after 'User Changed' or Error page
            // Verify the PHP session contains valid info
            if (verifyCurrentSession()) {
                printMainPage();
            } else { // Otherwise, redirect to the 'Welcome' page
                removeShibCookies();
                unsetGetUserSessionVars();
                printLogonPage();
            }
        break; // End case 'Proceed'

        case 'OK':  // User allows delegation of certificate
            handleAllowDelegation(strlen(getPostVar('rememberok')) > 0);
        break; // End case 'OK'

        case 'Cancel': // User denies delegation of certificate
            // If user clicked 'Cancel' on the WAYF page, return to the
            // portal's failure URL (or Google if failure URL not set).
            if (getPostVar('previouspage') == 'WAYF') {
                $failureuri = getSessionVar('failureuri');
                $location = 'http://www.google.com/';
                if (strlen($failureuri) > 0) {
                    $location = $failureuri . "?reason=cancel";
                }
                header('Location: ' . $location);
            } else { // 'Cancel' button on certificate delegate page clicked
                printCancelPage();
            }
        break; // End case 'Cancel'

        case "Show Help": // Toggle showing of help text on and off
        case "Hide Help":
            if (getSessionVar('showhelp') == 'on') {
                unsetSessionVar('showhelp');
            } else {
                setSessionVar('showhelp','on');
            }

            $stage = getSessionVar('stage');
            if (($stage == 'main') && (verifyCurrentSession())) {
                printMainPage();
            } else {
                printLogonPage();
            }
        break; // End case 'Show Help' / 'Hide Help'

        default: // No submit button clicked nor PHP session submit variable set
            /* If both the "keepidp" and the "providerId" cookies were set *
             * (and the providerId is a whitelisted IdP or valid OpenID    *
             * provider) then skip the Logon page and proceed to the       *
             * appropriate getuser script.                                 */
            $providerIdCookie = getCookieVar('providerId');
            if ((strlen($providerIdCookie) > 0) && 
                (strlen(getCookieVar('keepidp')) > 0)) {
                if (openid::urlExists($providerIdCookie)) { // Use OpenID authn
                    redirectToGetOpenIDUser($providerIdCookie);
                } elseif ($white->exists($providerIdCookie)) { // Use InCommon
                    redirectToGetUser($providerIdCookie);
                } else { // $providerIdCookie not in whitelist
                    setcookie('providerId','',time()-3600,'/','',true);
                    printLogonPage();
                }
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
 * and get rerouted to the Shibboleth protected getuser script.         *
 ************************************************************************/
function printLogonPage()
{
    global $log;
    global $skin;

    $log->info('Welcome page hit.');

    setSessionVar('stage','logon'); // For Show/Hide Help button clicks

    /* Check the skin config to see if we should virtually click the
     * "Remember my OK for this portal" checkbox.  We need to do this here
     * because we want to set the portal cookie before we go to the next
     * page (so the page can read the cookie).
     */
    $skinremember = $skin->getConfigOption('delegate','remember');
    if (($skinremember !== null) && ((int)$skinremember == 1)) {
        $lifetime = 12; // Default to 12 hours, but check config option
        $skinlifetime = $skin->getConfigOption('delegate','initiallifetime');
        if (($skinlifetime !== null) && ((int)$skinlifetime > 0)) {
            $lifetime = (int)$skinlifetime;
        }
        setPortalCookie((int)$skinremember,$lifetime);
    }

    printHeader('Welcome To The CILogon Delegation Service');

    echo '
    <div class="boxed">
    ';

    printHelpButton();

    echo '
      <br />
    ';

    // If the skin has a <portallist>, and <hideportalinfo> is set, check
    // to see if the callback URL matches one of the regular expressions in
    // the <portallist>. If so, we do NOT want to show the portal info.
    $showportalinfo = true;
    if ($skin->hasPortalList()) {
        $hpi = $skin->getConfigOption('portallistaction','hideportalinfo');
        if (($hpi !== null) && ((int)$hpi == 1) &&
            ($skin->portalListed(getSessionVar('callbackuri')))) {
            $showportalinfo = false; 
        }
    }

    if ($showportalinfo) {
        echo '
          <p>"' , 
          htmlspecialchars(getSessionVar('portalname')) , 
          '" requests that you select an Identity Provider and click "' ,
          getLogOnButtonText() ,
          '". If you do not approve this request, do not proceed.
          </p>
        ';

        printPortalInfo('1');
    }

    printWAYF();

    echo '
    </div> <!-- End boxed -->
    ';

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
    global $log;

    $log->warn('Missing or invalid oauth_token.');

    printHeader('CILogon Delegation Service');

    echo '
    <div class="boxed">
      <br class="clear"/>
      <p>
      You have reached the CILogon Delegation Service.  This service is for
      use by third parties to obtain certificates for their users.  
      End users should not normally see this page.
      </p>
      <p>If you are seeing this page, there was a
      problem with the delegation process.
      Please return to the previous site and try again.  If the error persists,
      please contact us at the email address at the bottom of the page.
      </p>
      <p>
      If you are an individual wishing to download a certificate to your
      local computer, please try the <a target="_blank"
      href="https://' , HOSTNAME , '/">CILogon Service</a>.
      </p>
      <p>
      <strong>Note:</strong> You must enable cookies in your web browser to
      use this site.
      </p>
    </div>
    ';

    printFooter();
}

/************************************************************************
 * Function   : printMainPage                                           *
 * This function prints out the HTML for the main page where the user   *
 * is presented with the portal information and asked to either allow   *
 * or deny delegation of a certificate to the portal.  We first check   *
 * to see if the "remember" cookie has been set for this portal.  If    *
 * so, then we automatically always approve delegation.  Otherwise,     *
 * we print out the HTML for the <form> buttons.                        *
 ************************************************************************/
function printMainPage()
{
    global $log;
    global $skin;

    setSessionVar('stage','main'); // For Show/Hide Help button clicks

    // Read the cookie containing portal 'lifetime' and 'remember' settings
    $portal = new portalcookie();
    $remember = $portal->getPortalRemember(getSessionVar('callbackuri'));
    $lifetime = $portal->getPortalLifetime(getSessionVar('callbackuri'));
    // If no 'lifetime' cookie, default to 12 hours.  Otherwise,
    // make sure lifetime is between 1 and 240 hours (inclusive).
    if ((strlen($lifetime) == 0) || ($lifetime == 0)) {
        // See if the skin specified an initial value
        $skinlife = $skin->getConfigOption('delegate','initiallifetime');
        if (($skinlife !== null) && ((int)$skinlife > 0)) {
            $lifetime = (int)$skinlife;
        } else {
            $lifetime = 12;
        }
    } elseif ($lifetime < 1) {
        $lifetime = 1;
    } elseif ($lifetime > 240) {
        $lifetime = 240;
    }

    // If 'remember' is set for the current portal, then automatically
    // click the 'OK' button for the user.
    if ($remember == 1) {
        handleAllowDelegation(true);
    } else {
        // User did not check 'Remember OK' before, so show the
        // HTML to prompt user for OK or Cancel delegation.

        $log->info('Allow Or Deny Delegation page hit.');

        $lifetimetext = "Specify the lifetime of the certificate to be issued. Maximum value is 240 hours.";
        $remembertext ="Check this box to automatically approve certificate issuance to the site on future visits. The certificate lifetime will be remembered. You will need to clear your browser's cookies to return here.";

        printHeader('Confirm Allow Delegation');

        echo '
        <div class="boxed">
        ';

        printHelpButton();

        echo '
        <br />
        <p>"' , 
        htmlspecialchars(getSessionVar('portalname')) , 
        '" is requesting a certificate for you. 
        If you approve, then "OK" the request.
        Otherwise, "Cancel" the request or navigate away from this page.
        </p>
        ';

        printPortalInfo('2');

        echo '
        <div class="actionbox"';

        if (getSessionVar('showhelp') == 'on') {
            echo ' style="width:92%;"';
        }

        echo '>
        <table class="helptable">
        <tr>
        <td class="actioncell">
        ';

        printFormHead();

        echo '
        <fieldset>
        <p>
        <label for="lifetime" title="' , $lifetimetext , '" 
        class="helpcursor">Certificate Lifetime (in hours):</label>
        <input type="text" name="lifetime" id="lifetime" title="' ,
        $lifetimetext , '" class="helpcursor" size="3" maxlength="3" 
        value="' , $lifetime , '" />
        </p>
        <p>
        <label for="rememberok" title="', $remembertext , '"
        class="helpcursor">Remember my OK for the site:</label>
        <input type="checkbox" name="rememberok" id="rememberok"
        title="', $remembertext, '" class="helpcursor" />
        </p>
        <p>
        <input type="submit" name="submit" class="submit" value="OK" />
        &nbsp;
        <input type="submit" name="submit" class="submit" value="Cancel" />
        </p>
        </fieldset>
        </form>
        </td>
        ';

        if (getSessionVar('showhelp') == 'on') {
            echo '
            <td class="helpcell">
            <div>
            <p>
            Please enter the lifetime of the certificate to be issued.
            Maximum value is 240 hours. 
            </p>
            <p>
            If you check the "Remember my OK for the site" checkbox,
            certificates will be issued automatically to this site on future
            visits, using the lifetime you specify here.  You will need to
            clear your browser\'s cookies to return to see this page again.
            </p>
            </div>
            </td>
            ';
        }



        echo '
        </tr>
        </table>
        </div> <!-- actionbox -->
        </div>
        ';
        printFooter();
    }
}

/************************************************************************
 * Function   : printPortalInfo                                         *
 * Parameter  : An optional suffix to append to the "portalinfo" table  *
 *              class name.                                             *
 * This function prints out the portal information table at the top of  *
 * of the page.  The optional parameter "$suffix" allows you to append  *
 * a number (for example) to differentiate the portalinfo table on the  *
 * log in page from the one on the main page.                           *
 ************************************************************************/
function printPortalInfo($suffix='') {
    $showhelp = getSessionVar('showhelp');
    $helptext = "The Site Name is provided by the site to CILogon and has not been vetted.";

    echo '
    <table class="portalinfo' , $suffix , '">
    <tr class="inforow">
      <th title="' , $helptext ,'">Site&nbsp;Name:</th>
      <td title="' , $helptext ,'">' ,
      htmlspecialchars(getSessionVar('portalname')) , '</td>
    ';

    if ($showhelp == 'on') {
        echo ' <td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Site URL is the location to which the site requests you to return upon completion."; 

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Site&nbsp;URL:</th> 
      <td title="' , $helptext , '">' , htmlspecialchars(getSessionVar('successuri')) , '</td>
    ';

    if ($showhelp == 'on') {
        echo '<td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Service URL is the location to which CILogon will send a certificate containing your identity information."; 

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Service&nbsp;URL:</th>
      <td title="' , $helptext , '">' , htmlspecialchars(getSessionVar('callbackuri')) , '</td>
      ';

    if ($showhelp == 'on') {
        echo '<td class="helpcell">' , $helptext , '</td>';
    }

    echo '
    </tr>
    </table>
    ';
}

/************************************************************************
 * Function   : printCancelPage                                         *
 * This function prints out the HTML for when the user clicked the      *
 * "Cancel" button on the "Allow Delegation" page.  It gives the user a *
 * link back to the portal via the "failure URL".                       *
 ************************************************************************/
function printCancelPage() {
    $portalname = getSessionVar('portalname');

    printHeader('Delegation Denied');

    echo '
    <div class="boxed">
    <br class="clear"/>
    <p>
    You have canceled delegation of a certificate to "' ,
    htmlspecialchars($portalname) , '".  
    Below is a link to return to the
    portal.  This link has been provided by the portal to be used when
    delegation of a certificate fails.
    </p>
    <p>
    <strong>Note:</strong> If you do not trust the information provided by
    the portal, <strong>do not</strong> click on the link below.  Instead,
    please contact your portal administrators or contact us at the email
    address at the bottom of the page.
    </p>

    <div class="returnlink">
      <a href="' , getSessionVar('failureuri') , '">Return to ' ,
      htmlspecialchars($portalname) , '</a>
    </div>
    </div>
    ';
    printFooter();
}

/************************************************************************
 * Function   : handleAllowDelegation                                   *
 * Parameters : True if the user selected to always allow delegation.   *
 * This fuction is called when the user clicks the 'OK' button on the   *
 * main page, or when the user had previously checked the 'Remember     *
 * my OK for this portal' checkbox which saved the 'remember' cookie    *
 * for the current portal. It first reads the cookie for the portal and *
 * updates the 'lifetime' and 'remember' parameters, then (re)saves     *
 * the cookie.  Then it calls out to the 'delegation/authorized'        *
 * servlet in order to do the back-end certificate delegation process.  *
 * If the $always parameter is true, then the user is automatically     *
 * returned to the portal's successuri or failureuri.  Otherwise, the   *
 * user is presented with a page showing the result of the attempted    *
 * certificate delegation as well as a link to "return to your portal". *
 ************************************************************************/
function handleAllowDelegation($always=false)
{
    global $log;

    $log->info('Attempting to delegate a certificate to a portal...');

    // Try to get the certificate lifetime from a submitted <form>
    $lifetime = trim(getPostVar('lifetime'));

    // If we couldn't get lifetime from the <form>, try the cookie
    $portal = new portalcookie();
    if (strlen($lifetime) == 0) {
        $lifetime = $portal->getPortalLifetime(getSessionVar('callbackuri'));
    }

    // Convert lifetime to integer.  Empty string and alpha chars --> 0
    $lifetime = (int)$lifetime;  
    // Verify that lifetime is in the range [1,240].  Default to 12 hours.
    if ($lifetime == 0) {
        $lifetime = 12;
    } elseif ($lifetime < 1) {
        $lifetime = 1;
    } elseif ($lifetime > 240) {
        $lifetime = 240;
    }

    setPortalCookie((int)$always,$lifetime);

    $success = false;  // Assume delegation of certificate failed
    $certtext = '';    // Output of 'openssl x509 -noout -text -in cert.pem'
    $myproxyinfo = getSessionVar('myproxyinfo');

    // Now call out to the "delegation/authorized" servlet to execute
    // the delegation the credential to the portal.
    $ch = curl_init();
    if ($ch !== false) {
        $url = AUTHORIZED_URL . '?' .
               'oauth_token=' . urlencode(getSessionVar('tempcred')) . '&' .
               'cilogon_lifetime=' . $lifetime . '&' .
               'cilogon_loa=' . urlencode(getSessionVar('loa')) . '&' .
               'cilogon_uid=' . urlencode(getSessionVar('uid')) . 
               ((strlen($myproxyinfo) > 0) ? 
                   ('&cilogon_info=' . urlencode($myproxyinfo)) : '');
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,30);
        /* Following two options are needed by polo-staging */
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,1);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        $output = curl_exec($ch);
        if (!empty($output)) { 
            $httpcode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if ($httpcode == 200) {
                // Check body of curl query for cilogon_status=ok
                if (preg_match('/cilogon_status=ok/',$output)) {
                    $success = true;
                    // Also check if the cert was returned as base64 
                    // encoded PEM certificate.  If so, get info about it.
                    if (preg_match('/cilogon_cert=([^\s]+)/',
                                   $output,$matches)) {
                        $b64cert = $matches[1];
                        $cert = base64_decode($b64cert);
                        if ($cert !== false) {
                            // Run "openssl x509" command for cert info
                            exec('/bin/env /usr/bin/openssl x509 -text '.
                                 '<<< ' . escapeshellarg($cert) . ' 2>&1',
                                 $x509out,$retcode);
                            if ($retcode == 0) {
                                $certtext = implode("\n",$x509out);
                            } else {
                                $certtext = $cert;
                            }
                        }
                    }
                }
            }
        }
        curl_close($ch);
    }

    $log->info('Delegation of certificate to portal ' .
               ($success ? 'succeeded.' : 'failed.'));

    // Depending on the result (success or failure), output appropriate
    // HTML to allow the user to return to the portal, or if $always
    // was set, then automatically return the user to the successuri
    // or failureuri.
    if ($always) {
        $log->info("Automatically returning to portal's " .
                   ($success ? 'success' : 'failure') . ' url.');
        $location = 'Location: ' .
            getSessionVar($success ? 'successuri' : 'failureuri');
        unsetGetUserSessionVars();
        unsetPortalSessionVars();
        header($location);
    } else {
        printHeader('Delegation ' . ($success ? 'Successful' : 'Failed'));

        echo '
        <div class="boxed">
        <div>
        <div class="icon">
        ';
        printIcon(($success ? 'okay' : 'error'));
        echo '
        </div>
        <h2>' , ($success ? 'Success!' : 'Failure!') , '</h2>
        </div>
        ';
        if ($success) {
            echo '
            <p>
            The CILogon Service has issued a certificate to "' ,
            htmlspecialchars(getSessionVar('portalname')) , '".  
            Below is a link to return to
            the site to use the issued certificate.
            </p>
            ';
            // If we got the cert from the 'delegation/authorized' script,
            // output it in an expandable/scrollable <div> for user info.
            if (strlen($certtext) > 0) {
                echo '
                <noscript>
                <div class="nojs">
                Javascript is disabled. In order to expand the "Certificate
                Details" section below, please enable Javascript in your
                browser.
                </div>
                </noscript>
                
                <div class="summary">
                <div id="certtext1" style="display:inline"><span 
                class="expander"><a 
                href="javascript:showHideDiv(\'certtext\',-1)"><img
                src="/images/triright.gif" alt="&rArr;" width="14" 
                height="14" />
                Certificate Details</a></span>
                </div>
                <div id="certtext2" style="display:none"><span
                class="expander"><a
                href="javascript:showHideDiv(\'certtext\',-1)"><img 
                src="/images/tridown.gif" alt="&dArr;" width="14" 
                height="14" /> 
                Certificate Details</a></span>
                </div>
                <br class="clear" />
                <div id="certtext3" style="display:none">
                  <div class="portalinfo">
                  <pre>' , htmlspecialchars($certtext) , '</pre>
                  </div>
                </div>
                </div>
                ';
            }
        } else {
            echo '
            <p>
            We were unable to issue a certificate to "' ,
            htmlspecialchars(getSessionVar('portalname')) , '".  
            Below is a link to return to the site.  
            </p>
            ';
        }
        echo '
        <div class="returnlink">
          <a href="' , 
          getSessionVar($success ? 'successuri' : 'failureuri') , 
          '">Return to ' ,
          htmlspecialchars(getSessionVar('portalname')) , '</a>
        </div>
        </div>
        ';
        printFooter();
        unsetGetUserSessionVars();
        unsetPortalSessionVars();
    }
}

/************************************************************************
 * Function   : setPortalCookie                                         *
 * Parameters : (1) 1 if the "Remember my OK for this portal" checkbox  *
 *                  has been checked.  0 otherwise.                     *
 *              (2) The lifetime (in hours) entered in the "Certificate *
 *                  Lifetime input box.                                 *
 * This function is a convenience funtion to set the cookie for the     *
 * current portal (using the callbackuri) to remember the certificate   *
 * lifetime value and if the user checked the "Remember my OK for this  *
 * portal" checkbox.                                                    *
 ************************************************************************/
function setPortalCookie($remember,$lifetime) {
    $portal = new portalcookie();
    $portal->setPortalRemember(getSessionVar('callbackuri'),(int)$remember);
    $portal->setPortalLifetime(getSessionVar('callbackuri'),$lifetime);
    $portal->write();  // Save the cookie with the updated values
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
        $dbs = new dbservice();
        $dbs->getPortalParameters($token);
        $status = $dbs->status;
        setSessionVar('portalstatus',$status);
        if (!($status & 1)) {  // STATUS_OK* codes are even-numbered
            setSessionVar('callbackuri',$dbs->cilogon_callback);
            setSessionVar('failureuri',$dbs->cilogon_failure);
            setSessionVar('successuri',$dbs->cilogon_success);
            setSessionVar('portalname',$dbs->cilogon_portal_name);
            setSessionVar('tempcred',$dbs->oauth_token);
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

?>
