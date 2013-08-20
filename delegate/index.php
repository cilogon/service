<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('Auth/OpenID/Consumer.php');

/* The full URL of the 'delegation/authorized' OAuth script. */
define('AUTHORIZED_URL','http://localhost:8080/delegation/authorized');

/* Loggit object for logging info to syslog. */
$log = new loggit();

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  *
 * Note: replace CR/LF with space for "Show/Hide Help" buttons.      */
$retchars = array("\r\n","\n","\r");
$submit = str_replace($retchars," ",csrf::verifyCookieAndGetSubmit());
util::unsetSessionVar('submit');

$log->info('submit="' . $submit . '"');

/* First, check to see if the info related to the 'oauth_token' passed *
 * from the Community Portal exists in the current PHP session.  If    *
 * so, then continue processing based on 'submit' value.  Otherwise,   *
 * print out error message about bad or missing oauth_token info.      */
if (verifyOAuthToken(util::getGetVar('oauth_token'))) {
    /* Depending on the value of the clicked "submit" button or the    *
     * equivalent PHP session variable, take action or print out HTML. */
    switch ($submit) {

        case 'Log On': // Check for OpenID or InCommon usage.
        case 'Continue': // For OOI
            handleLogOnButtonClicked();
        break; // End case 'Log On'

        case 'gotuser': // Return from the getuser script
            handleGotUser();
        break; // End case 'gotuser'

        case 'Proceed': // Proceed after 'User Changed' or Error page
        case 'Done with Two-Factor':
            verifySessionAndCall('printMainPage');
        break; // End case 'Proceed'

        case 'OK':  // User allows delegation of certificate
            handleAllowDelegation(strlen(util::getPostVar('rememberok')) > 0);
        break; // End case 'OK'

        case 'Cancel': // User denies delegation of certificate
            // If user clicked 'Cancel' on the WAYF page, return to the
            // portal's failure URL (or Google if failure URL not set).
            if (util::getPostVar('previouspage') == 'WAYF') {
                $failureuri = util::getSessionVar('failureuri');
                $location = 'http://www.google.com/';
                if (strlen($failureuri) > 0) {
                    $location = $failureuri . "?reason=cancel";
                }
                header('Location: ' . $location);
            } else { // 'Cancel' button on certificate delegate page clicked
                printCancelPage();
            }
        break; // End case 'Cancel'

        case 'Manage Two-Factor':
            verifySessionAndCall('printTwoFactorPage');
        break; // End case 'Manage Two-Factor'

        case 'Enable':   // Enable / Disable two-factor authentication
        case 'Disable':
        case 'Verify':   // Log in with Google Authenticator
        case 'Disable Two-Factor':
            $enable = !preg_match('/^Disable/',$submit);
            verifySessionAndCall('handleEnableDisableTwoFactor',array($enable));
        break; // End case 'Enable' / 'Disable'

        case 'I Lost My Phone': 
            verifySessionAndCall('handleILostMyPhone');
        break; // End case 'I Lost My Phone'

        case 'Enter': // Verify Google Authenticator one time password
            verifySessionAndCall('handleGoogleAuthenticatorLogin');
        break; // End case 'Enter'

        case 'Show Help': // Toggle showing of help text on and off
        case 'Hide Help':
            handleHelpButtonClicked();
        break; // End case 'Show Help' / 'Hide Help'

        default: // No submit button clicked nor PHP session submit variable set
            handleNoSubmitButtonClicked();
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
function printLogonPage() {
    global $log;
    global $skin;

    $log->info('Welcome page hit.');

    util::setSessionVar('stage','logon'); // For Show/Hide Help button clicks

    /* Check if this is the first time the user has visited the site from
     * the current portal.  We do this by checking the portal cookie's
     * lifetime for a positive value.  If the portal cookie has NOT YET been
     * set, then check the skin config to see if either initialremember or
     * initiallifetime has been set.  We do this here because these two
     * values set the portal cookie, which needs to be done before we go
     * to the next page (where the cookie is actually read).
     */
    $portal = new portalcookie();
    $portallifetime = 
        $portal->getPortalLifetime(util::getSessionVar('callbackuri'));
    if ((strlen($portallifetime) == 0) || ($portallifetime == 0)) {
        $needtosetcookie = 0;

        // Try to read the skin's initiallifetime
        $initiallifetime = $skin->getConfigOption('delegate','initiallifetime');
        if ((!is_null($initiallifetime)) && ((int)$initiallifetime > 0)) {
            $needtosetcookie = 1;
            $initiallifetime = (int)$initiallifetime;
        } else { // Set a default lifetime value in case initialremember is set
            $initiallifetime = 12;
        }

        // Make sure initiallifetime is within [minlifetime..maxlifetime] 
        list($minlifetime,$maxlifetime) = getMinMaxLifetimes('delegate',240);
        if ($initiallifetime < $minlifetime) {
            $needtosetcookie = 1;
            $initiallifetime = $minlifetime;
        } elseif ($initiallifetime > $maxlifetime) {
            $needtosetcookie = 1;
            $initiallifetime = $maxlifetime;
        }

        // Next, try to read the skin's initialremember
        $initialremember = $skin->getConfigOption('delegate','initialremember');
        if ((!is_null($initialremember)) && ((int)$initialremember > 0)) {
            $needtosetcookie = 1;
            $initialremember = (int)$initialremember;
        } else { // Set a default remember value in case initiallifetime is set
            $initialremember = 0;
        }

        if ($needtosetcookie) {
            setPortalCookie($initialremember,$initiallifetime);
        }
    }

    printHeader('Welcome To The CILogon Delegation Service');

    echo '
    <div class="boxed">
    ';

    printHelpButton();

    echo '
      <br />
    ';

    // If the <hideportalinfo> option is set, do not show the portal info if
    // the callback uri is in the portal list.
    $showportalinfo = true;
    if (((int)$skin->getConfigOption('portallistaction','hideportalinfo')==1) &&
         ($skin->inPortalList(util::getSessionVar('callbackuri')))) {
        $showportalinfo = false; 
    }

    if ($showportalinfo) {
        echo '
          <br/>
          <p>"' , 
          htmlspecialchars(util::getSessionVar('portalname')) , 
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
function printBadOAuthTokenPage() {
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
      <p>
      Possible reasons for seeing this page include:
      </p>
      <ul>
      <li>You navigated directly to this page.</li>
      <li>You clicked your browser\'s "Back" button.</li>
      <li>There was a problem with the delegation process.</li>
      </ul>
      <p>
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
function printMainPage() {
    global $log;
    global $skin;

    $log->info('Allow Or Deny Delegation page hit.');

    util::setSessionVar('stage','main'); // For Show/Hide Help button clicks

    $remember = 0;   // Default value for remember checkbox is unchecked
    $lifetime = 12;  // Default value for lifetime is 12 hours

    // Check the skin for forceremember and forcelifetime
    $forceremember = $skin->getConfigOption('delegate','forceremember');
    if ((!is_null($forceremember)) && ((int)$forceremember == 1)) {
        $forceremember = 1;
    } else {
        $forceremember = 0;
    }
    $forcelifetime = $skin->getConfigOption('delegate','forcelifetime');
    if ((!is_null($forcelifetime)) && ((int)$forcelifetime > 0)) {
        $forcelifetime = (int)$forcelifetime;
    } else {
        $forcelifetime = 0;
    }

    // Try to read the portal coookie for the remember and lifetime values.
    $portal = new portalcookie();
    $portalremember = 
        $portal->getPortalRemember(util::getSessionVar('callbackuri'));
    $portallifetime = 
        $portal->getPortalLifetime(util::getSessionVar('callbackuri'));

    // If skin's forceremember or portal cookie's remember is set,
    // then we bypass the Allow/Deny delegate page.
    if (($forceremember == 1) || ($portalremember == 1)) {
        $remember = 1;
    }

    // If skin's forcelifetime or portal cookie's lifetime is set,
    // set lifetime accordingly and make sure value is between the
    // configured minlifetime and maxlifetime.
    if ($forcelifetime > 0) {
        $lifetime = $forcelifetime;
    } elseif ($portallifetime > 0) {
        $lifetime = $portallifetime;
    }
    list($minlifetime,$maxlifetime) = getMinMaxLifetimes('delegate',240);
    if ($lifetime < $minlifetime) {
        $lifetime = $minlifetime;
    } elseif ($lifetime > $maxlifetime) {
        $lifetime = $maxlifetime;
    }

    // If 'remember' is set, then auto-click the 'OK' button for the user.
    if ($remember == 1) {
        handleAllowDelegation(true);
    } else {
        // User did not check 'Remember OK' before, so show the
        // HTML to prompt user for OK or Cancel delegation.

        $lifetimetext = "Specify the lifetime of the certificate to be issued. Acceptable range is between $minlifetime and $maxlifetime hours.";
        $remembertext ="Check this box to automatically approve certificate issuance to the site on future visits. The certificate lifetime will be remembered. You will need to clear your browser's cookies to return here.";

        printHeader('Confirm Allow Delegation');

        echo '
        <div class="boxed">
        ';

        printHelpButton();

        echo '
        <br />
        <p>"' , 
        htmlspecialchars(util::getSessionVar('portalname')) , 
        '" is requesting a certificate for you. 
        If you approve, then "OK" the request.
        Otherwise, "Cancel" the request or navigate away from this page.
        </p>
        ';

        printPortalInfo('2');

        echo '
        <div class="actionbox"';

        if (util::getSessionVar('showhelp') == 'on') {
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
        $lifetimetext , '" size="3" maxlength="3" value="' , 
        $lifetime , '" ' , 
        (($forcelifetime>0) ? 'disabled="disabled" ' : 'class="helpcursor" ') ,
        '/>
<!--[if IE]><input type="text" style="display:none;" disabled="disabled" size="1"/><![endif]-->
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

        if (util::getSessionVar('showhelp') == 'on') {
            echo '
            <td class="helpcell">
            <div>
            <p>
            Please enter the lifetime of the certificate to be issued.
            Acceptable range is between ' , $minlifetime, ' and ' ,
            $maxlifetime , ' hours. 
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
        ';

        printTwoFactorBox();

        echo '
        </div> <!-- boxed -->
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
    $showhelp = util::getSessionVar('showhelp');
    $helptext = "The Site Name is provided by the site to CILogon and has not been vetted.";

    echo '
    <table class="portalinfo' , $suffix , '">
    <tr class="inforow">
      <th title="' , $helptext ,'">Site&nbsp;Name:</th>
      <td title="' , $helptext ,'">' ,
      htmlspecialchars(util::getSessionVar('portalname')) , '</td>
    ';

    if ($showhelp == 'on') {
        echo ' <td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Site URL is the location to which the site requests you to return upon completion."; 

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Site&nbsp;URL:</th> 
      <td title="' , $helptext , '">' , 
          htmlspecialchars(util::getSessionVar('successuri')) , '</td>
    ';

    if ($showhelp == 'on') {
        echo '<td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Service URL is the location to which CILogon will send a certificate containing your identity information."; 

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Service&nbsp;URL:</th>
      <td title="' , $helptext , '">' , 
          htmlspecialchars(util::getSessionVar('callbackuri')) , '</td>
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
    $portalname = util::getSessionVar('portalname');

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
      <a href="' , util::getSessionVar('failureuri') , '">Return to ' ,
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
function handleAllowDelegation($always=false) {
    global $log;
    global $skin;

    // The 'authorized' servlet may return a response URL to be used
    // instead of the success / failure URLs.
    $responseurl = '';

    $log->info('Attempting to delegate a certificate to a portal...');

    $lifetime = 0;
    // Check the skin's forcelifetime and use it if it is configured.
    $forcelifetime = $skin->getConfigOption('delegate','forcelifetime');
    if ((!is_null($forcelifetime)) && ((int)$forcelifetime > 0)) {
        $lifetime = (int)$forcelifetime;
    }

    // Next, try to get the certificate lifetime from a submitted <form>
    if ($lifetime == 0) {
        $lifetime = (int)(trim(util::getPostVar('lifetime')));
    }

    // If we couldn't get lifetime from the <form>, try the cookie
    if ($lifetime == 0) {
        $portal = new portalcookie();
        $lifetime = (int)($portal->getPortalLifetime(
            util::getSessionVar('callbackuri')));
    }

    // Default lifetime to 12 hours. And then make sure lifetime is in
    // acceptable range.
    if ($lifetime == 0) {
        $lifetime = 12;
    }
    list($minlifetime,$maxlifetime) = getMinMaxLifetimes('delegate',240);
    if ($lifetime < $minlifetime) {
        $lifetime = $minlifetime;
    } elseif ($lifetime > $maxlifetime) {
        $lifetime = $maxlifetime;
    }

    setPortalCookie((int)$always,$lifetime);

    $success = false;  // Assume delegation of certificate failed
    $certtext = '';    // Output of 'openssl x509 -noout -text -in cert.pem'
    $myproxyinfo = util::getSessionVar('myproxyinfo');

    // Now call out to the "delegation/authorized" servlet to execute
    // the delegation the credential to the portal.
    $ch = curl_init();
    if ($ch !== false) {
        $authurl = AUTHORIZED_URL;
        $tempcred = util::getSessionVar('tempcred');
        // Change 'delegation' to 'oauth' if present in tempcred
        if (preg_match('/delegation2/',$tempcred)) {
            $authurl = 
                preg_replace('%/delegation[^/]*/%','/oauth/',$authurl);
        }
        $url = $authurl . '?' .
               'oauth_token=' . urlencode($tempcred) . '&' .
               'cilogon_lifetime=' . $lifetime . '&' .
               'cilogon_loa=' . urlencode(util::getSessionVar('loa')) . '&' .
               'cilogon_uid=' . urlencode(util::getSessionVar('uid')) . 
               ((strlen($myproxyinfo) > 0) ? 
                   ('&cilogon_info=' . urlencode($myproxyinfo)) : '');
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,35);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        $output = curl_exec($ch);
        if (curl_errno($ch)) { // Send alert on curl errors
            util::sendErrorAlert('cUrl Error',
                'cUrl Error    = ' . curl_error($ch) . "\n" . 
                "URL Accessed  = $url");
        }
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
                // Check for an alternate response URL to be used 
                // in place of success / failure URLs.
                if (preg_match('/cilogon_response_url=([^\s]+)/',
                               $output,$matches)) {
                    $responseurl = $matches[1];
                }
            }
        }
        curl_close($ch);
    }

    $log->info('Delegation of certificate to portal ' .
               ($success ? 'succeeded.' : 'failed.'));

    // Depending on the result (success or failure), output appropriate
    // HTML to allow the user to return to the portal, or if $always
    // was set, then automatically return the user to the successuri, 
    // failureuri, or cilogon_reponse_url if supplied by authorized servlet.
    if ($always) {
        $log->info("Automatically returning to portal's " .
                   ($success ? 'success' : 'failure') . ' url.');
        $location = 'Location: ' . ((strlen($responseurl) > 0) ? $responseurl :
            (util::getSessionVar($success ? 'successuri' : 'failureuri')));
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
            htmlspecialchars(util::getSessionVar('portalname')) , '".  
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
            htmlspecialchars(util::getSessionVar('portalname')) , '".  
            Below is a link to return to the site.  
            </p>
            ';
        }
        echo '
        <div class="returnlink">
          <a href="' , 
          ((strlen($responseurl) > 0) ? $responseurl :
           (util::getSessionVar($success ? 'successuri' : 'failureuri'))) , 
          '">Return to ' ,
          htmlspecialchars(util::getSessionVar('portalname')) , '</a>
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
    $portal->setPortalRemember(util::getSessionVar('callbackuri'),
                               (int)$remember);
    $portal->setPortalLifetime(util::getSessionVar('callbackuri'),$lifetime);
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
function verifyOAuthToken($token='') {
    $retval = false; // Assume OAuth session info is not valid

    // If passing in the OAuth $token, try to get the associated info
    // from the persistent store and put it into the PHP session.
    if (strlen($token) > 0) {
        $dbs = new dbservice();
        $dbs->getPortalParameters($token);
        $status = $dbs->status;
        util::setSessionVar('portalstatus',$status);
        if (!($status & 1)) {  // STATUS_OK* codes are even-numbered
            util::setSessionVar('callbackuri',$dbs->cilogon_callback);
            util::setSessionVar('failureuri',$dbs->cilogon_failure);
            util::setSessionVar('successuri',$dbs->cilogon_success);
            util::setSessionVar('portalname',$dbs->cilogon_portal_name);
            util::setSessionVar('tempcred',$dbs->oauth_token);
        }
    }

    // Now check to verify all session variables have data
    if ((strlen(util::getSessionVar('callbackuri')) > 0) &&
        (strlen(util::getSessionVar('failureuri')) > 0) &&
        (strlen(util::getSessionVar('successuri')) > 0) &&
        (strlen(util::getSessionVar('portalname')) > 0) &&
        (strlen(util::getSessionVar('tempcred')) > 0) &&
        (!(util::getSessionVar('portalstatus') & 1))) { // STATUS_OK* are even
        $retval = true;
    }

    return $retval;
}

?>
