<?php

/**
 * This file contains functions called by index-site.php. The index-site.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\PortalCookie;
use CILogon\Service\DBService;
use CILogon\Service\Loggit;

/**
 * printLogonPage
 *
 * This function prints out the HTML for the main cilogon.org page.
 * Explanatory text is shown as well as a button to log in to an IdP
 * and get rerouted to the Shibboleth protected getuser script.
 */
function printLogonPage()
{
    $log = new Loggit();
    $log->info('Welcome page hit.');

    Util::setSessionVar('stage', 'logon'); // For Show/Hide Help button clicks

    // Check if this is the first time the user has visited the site from
    // the current portal.  We do this by checking the portal cookie's
    // lifetime for a positive value.  If the portal cookie has NOT YET been
    // set, then check the skin config to see if either initialremember or
    // initiallifetime has been set.  We do this here because these two
    // values set the portal cookie, which needs to be done before we go
    // to the next page (where the cookie is actually read).
    $skin = Util::getSkin();
    $pc = new PortalCookie();
    $portallifetime = $pc->get('lifetime');

    if ((empty($portallifetime)) || ($portallifetime == 0)) {
        $needtosetcookie = 0;

        // Try to read the skin's initiallifetime
        $initiallifetime = $skin->getConfigOption(
            'delegate',
            'initiallifetime'
        );
        if ((!is_null($initiallifetime)) && ((int)$initiallifetime > 0)) {
            $needtosetcookie = 1;
            $initiallifetime = (int)$initiallifetime;
        } else { // Set a default lifetime value in case initialremember is set
            $initiallifetime = 12;
        }

        // Make sure initiallifetime is within [minlifetime..maxlifetime]
        list($minlifetime, $maxlifetime) =
            Content::getMinMaxLifetimes('delegate', 240);
        if ($initiallifetime < $minlifetime) {
            $needtosetcookie = 1;
            $initiallifetime = $minlifetime;
        } elseif ($initiallifetime > $maxlifetime) {
            $needtosetcookie = 1;
            $initiallifetime = $maxlifetime;
        }

        // Next, try to read the skin's initialremember
        $initialremember = $skin->getConfigOption(
            'delegate',
            'initialremember'
        );
        if ((!is_null($initialremember)) && ((int)$initialremember > 0)) {
            $needtosetcookie = 1;
            $initialremember = (int)$initialremember;
        } else { // Set a default remember value in case initiallifetime is set
            $initialremember = 0;
        }

        if ($needtosetcookie) {
            $pc->set('remember', $initialremember);
            $pc->set('lifetime', $initiallifetime);
            $pc->write();
        }
    }

    Content::printHeader('Welcome To The CILogon Delegation Service');

    echo '
    <div class="boxed">
    ';

    Content::printHelpButton();

    echo '
      <br />
    ';

    // If the <hideportalinfo> option is set, do not show the portal info if
    // the callback uri is in the portal list.
    $showportalinfo = true;
    if (
        ((int)$skin->getConfigOption(
            'portallistaction',
            'hideportalinfo'
        ) == 1) &&
        ($skin->inPortalList(Util::getSessionVar('callbackuri')))
    ) {
        $showportalinfo = false;
    }

    if ($showportalinfo) {
        echo '
          <br/>
          <p>"' ,
          htmlspecialchars(Util::getSessionVar('portalname')) ,
          '" requests that you select an Identity Provider and click "' ,
          Content::getLogOnButtonText() ,
          '". If you do not approve this request, do not proceed.
          </p>
          <p><em>By proceeding you agree to share your name and
          email address with "' ,
          htmlspecialchars(Util::getSessionVar('portalname')) ,
          '"</em>.</p>
        ';

        printPortalInfo('1');
    }

    Content::printWAYF();

    echo '
    </div> <!-- End boxed -->
    ';

    Content::printFooter();
}

/**
 * printBadOAuthTokenPage
 *
 * This function prints out the HTML for the page when the oauth_token
 * (tempcred) or associated OAuth information is missing, bad, or expired.
 */
function printBadOAuthTokenPage()
{
    $log = new Loggit();
    $log->warn('Missing or invalid oauth_token.');

    Content::printHeader('CILogon Delegation Service');

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
      href="https://' , Util::getHN() , '/">CILogon Service</a>.
      </p>
      <p>
      <strong>Note:</strong> You must enable cookies in your web browser to
      use this site.
      </p>
    </div>
    ';

    Content::printFooter();
}

/**
 * printMainPage
 *
 * This function prints out the HTML for the main page where the user
 * is presented with the portal information and asked to either allow
 * or deny delegation of a certificate to the portal.  We first check
 * to see if the 'remember' cookie has been set for this portal. If
 * so, then we automatically always approve delegation.  Otherwise,
 * we print out the HTML for the <form> buttons.
 */
function printMainPage()
{
    $log = new Loggit();
    $log->info('Allow Or Deny Delegation page hit.');

    Util::setSessionVar('stage', 'main'); // For Show/Hide Help button clicks

    $remember = 0;   // Default value for remember checkbox is unchecked
    $lifetime = 12;  // Default value for lifetime is 12 hours

    // Check the skin for forceremember and forcelifetime
    $skin = Util::getSkin();
    $forceremember = $skin->getConfigOption(
        'delegate',
        'forceremember'
    );
    if ((!is_null($forceremember)) && ((int)$forceremember == 1)) {
        $forceremember = 1;
    } else {
        $forceremember = 0;
    }
    $forcelifetime = $skin->getConfigOption(
        'delegate',
        'forcelifetime'
    );
    if ((!is_null($forcelifetime)) && ((int)$forcelifetime > 0)) {
        $forcelifetime = (int)$forcelifetime;
    } else {
        $forcelifetime = 0;
    }

    // Try to read the portal coookie for the remember and lifetime values.
    $pc = new PortalCookie();
    $portalremember = $pc->get('remember');
    $portallifetime = $pc->get('lifetime');

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
    list($minlifetime, $maxlifetime) =
        Content::getMinMaxLifetimes('delegate', 240);
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

        $lifetimetext = "Specify the lifetime of the certificate to " .
            "be issued. Acceptable range is between $minlifetime and " .
            "$maxlifetime hours.";
        $remembertext = "Check this box to automatically approve " .
            "certificate issuance to the site on future visits. " .
            "The certificate lifetime will be remembered. You will " .
            "need to clear your browser's cookies to return here.";

        Content::printHeader('Confirm Allow Delegation');

        echo '
        <div class="boxed">
        ';

        Content::printHelpButton();

        echo '
        <br />
        <p>"' ,
        htmlspecialchars(Util::getSessionVar('portalname')) ,
        '" is requesting a certificate for you.
        If you approve, then "OK" the request.
        Otherwise, "Cancel" the request or navigate away from this page.
        </p>
        ';

        printPortalInfo('2');

        echo '
        <div class="actionbox"';

        if (Util::getSessionVar('showhelp') == 'on') {
            echo ' style="width:92%;"';
        }

        echo '>
        <table class="helptable">
        <tr>
        <td class="actioncell">
        ';

        Content::printFormHead();

        echo '
        <fieldset>
        <p>
        <label for="lifetime" title="' , $lifetimetext , '"
        class="helpcursor">Certificate Lifetime (in hours):</label>
        <input type="text" name="lifetime" id="lifetime" title="' ,
        $lifetimetext , '" size="3" maxlength="3" value="' ,
        $lifetime , '" ' ,
        (($forcelifetime > 0) ? 'disabled="disabled" ' : 'class="helpcursor" ') ,
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

        if (Util::getSessionVar('showhelp') == 'on') {
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

        echo '
        </div> <!-- boxed -->
        ';
        Content::printFooter();
    }
}

/**
 * printPortalInfo
 *
 * This function prints out the portal information table at the top of
 * of the page.  The optional parameter $suffix allows you to append
 * a number (for example) to differentiate the portalinfo table on the
 * log in page from the one on the main page.
 *
 * @param string $suffix An optional suffix to append to the 'portalinfo'
 *        table class name.
 */
function printPortalInfo($suffix = '')
{
    $showhelp = Util::getSessionVar('showhelp');
    $helptext = "The Site Name is provided by the site to CILogon and has not been vetted.";

    echo '
    <table class="portalinfo' , $suffix , '">
    <tr class="inforow">
      <th title="' , $helptext ,'">Site&nbsp;Name:</th>
      <td title="' , $helptext ,'">' ,
      htmlspecialchars(Util::getSessionVar('portalname')) , '</td>
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
          htmlspecialchars(Util::getSessionVar('successuri')) , '</td>
    ';

    if ($showhelp == 'on') {
        echo '<td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Service URL is the location to which CILogon " .
        "will send a certificate containing your identity information.";

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Service&nbsp;URL:</th>
      <td title="' , $helptext , '">' ,
          htmlspecialchars(Util::getSessionVar('callbackuri')) , '</td>
      ';

    if ($showhelp == 'on') {
        echo '<td class="helpcell">' , $helptext , '</td>';
    }

    echo '
    </tr>
    </table>
    ';
}

/**
 * printCancelPage
 *
 * This function prints out the HTML for when the user clicked the
 * 'Cancel' button on the 'Allow Delegation' page.  It gives the user a
 * link back to the portal via the 'failure URL'.
 */
function printCancelPage()
{
    $portalname = Util::getSessionVar('portalname');

    Content::printHeader('Delegation Denied');

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
      <a href="' , Util::getSessionVar('failureuri') , '">Return to ' ,
      htmlspecialchars($portalname) , '</a>
    </div>
    </div>
    ';
    Content::printFooter();
}

/**
 * handleAllowDelegation
 *
 * This fuction is called when the user clicks the 'OK' button on the
 * main page, or when the user had previously checked the 'Remember
 * my OK for this portal' checkbox which saved the 'remember' cookie
 * for the current portal. It first reads the cookie for the portal and
 * updates the 'lifetime' and 'remember' parameters, then (re)saves
 * the cookie.  Then it calls out to the 'oauth/authorized' servlet
 * in order to do the back-end certificate delegation process. If the
 * $always parameter is true, then the user is automatically returned
 * to the portal's successuri or failureuri.  Otherwise, the user is
 * presented with a page showing the result of the attempted
 * certificate delegation as well as a link to 'return to your portal'.
 *
 * @param bool True if the user selected to always allow delegation.
 */
function handleAllowDelegation($always = false)
{
    // The 'authorized' servlet may return a response URL to be used
    // instead of the success / failure URLs.
    $responseurl = '';

    $log = new Loggit();
    $log->info('Attempting to delegate a certificate to a portal...');

    $lifetime = 0;
    // Check the skin's forcelifetime and use it if it is configured.
    $forcelifetime = Util::getSkin()->getConfigOption(
        'delegate',
        'forcelifetime'
    );
    if ((!is_null($forcelifetime)) && ((int)$forcelifetime > 0)) {
        $lifetime = (int)$forcelifetime;
    }

    // Next, try to get the certificate lifetime from a submitted <form>
    if ($lifetime == 0) {
        $lifetime = (int)(trim(Util::getPostVar('lifetime')));
    }

    // If we couldn't get lifetime from the <form>, try the cookie
    $pc = new PortalCookie();
    if ($lifetime == 0) {
        $lifetime = (int)($pc->get('lifetime'));
    }

    // Default lifetime to 12 hours. And then make sure lifetime is in
    // acceptable range.
    if ($lifetime == 0) {
        $lifetime = 12;
    }
    list($minlifetime, $maxlifetime) =
        Content::getMinMaxLifetimes('delegate', 240);
    if ($lifetime < $minlifetime) {
        $lifetime = $minlifetime;
    } elseif ($lifetime > $maxlifetime) {
        $lifetime = $maxlifetime;
    }

    $pc->set('remember', (int)$always);
    $pc->set('lifetime', $lifetime);
    $pc->write();

    $success = false;  // Assume delegation of certificate failed
    $certtext = '';    // Output of 'openssl x509 -noout -text -in cert.pem'
    $myproxyinfo = Util::getSessionVar('myproxyinfo');

    // Now call out to the 'oauth/authorized' servlet to execute
    // the delegation the credential to the portal.
    $ch = curl_init();
    if ($ch !== false) {
        $tempcred = Util::getSessionVar('tempcred');
        $url = OAUTH1_AUTHORIZED_URL . '?' .
               'oauth_token=' . urlencode($tempcred) . '&' .
               'cilogon_lifetime=' . $lifetime . '&' .
               'cilogon_loa=' . urlencode(Util::getSessionVar('loa')) . '&' .
               'cilogon_uid=' . urlencode(Util::getSessionVar('uid')) .
               ((empty($myproxyinfo)) ?
                   '' : ('&cilogon_info=' . urlencode($myproxyinfo)));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $output = curl_exec($ch);
        if (curl_errno($ch)) { // Send alert on curl errors
            Util::sendErrorAlert(
                'cUrl Error',
                'cUrl Error    = ' . curl_error($ch) . "\n" .
                "URL Accessed  = $url"
            );
        }
        if (!empty($output)) {
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode == 200) {
                // Check body of curl query for cilogon_status=ok
                if (preg_match('/cilogon_status=ok/', $output)) {
                    $success = true;
                    // Also check if the cert was returned as base64
                    // encoded PEM certificate.  If so, get info about it.
                    if (
                        preg_match(
                            '/cilogon_cert=([^\s]+)/',
                            $output,
                            $matches
                        )
                    ) {
                        $b64cert = $matches[1];
                        $cert = base64_decode($b64cert);
                        if ($cert !== false) {
                            // Run 'openssl x509' command for cert info
                            exec(
                                '/bin/env RANDFILE=/tmp/.rnd ' .
                                '/usr/bin/openssl x509 -text ' .
                                '<<< ' . escapeshellarg($cert) . ' 2>&1',
                                $x509out,
                                $retcode
                            );
                            if ($retcode == 0) {
                                $certtext = implode("\n", $x509out);
                            } else {
                                $certtext = $cert;
                            }
                        }
                    }
                }
                // Check for an alternate response URL to be used
                // in place of success / failure URLs.
                if (
                    preg_match(
                        '/cilogon_response_url=([^\s]+)/',
                        $output,
                        $matches
                    )
                ) {
                    $responseurl = $matches[1];
                }
            }
        }
        curl_close($ch);
    }

    $log = new Loggit();
    $log->info('Delegation of certificate to portal ' .
               ($success ? 'succeeded.' : 'failed.'));
    //CIL-507 Special log message for XSEDE
    $log->info('USAGE email="' . Util::getSessionVar('emailaddr') .
               '" client="' . Util::getSessionVar('portalname') . '"');


    // Depending on the result (success or failure), output appropriate
    // HTML to allow the user to return to the portal, or if $always
    // was set, then automatically return the user to the successuri,
    // failureuri, or cilogon_reponse_url if supplied by authorized servlet.
    if ($always) {
        $log->info("Automatically returning to portal's " .
            ($success ? 'success' : 'failure') . ' url.');
        $location = 'Location: ' . ((!empty($responseurl)) ? $responseurl :
            (Util::getSessionVar($success ? 'successuri' : 'failureuri')));
        if ($success) {
            Util::unsetClientSessionVars();
            /// Util::unsetAllUserSessionVars();
        } else {
            Util::unsetAllUserSessionVars();
        }
        header($location);
        exit; // No further processing necessary
    } else {
        Content::printHeader('Delegation ' .
            ($success ? 'Successful' : 'Failed'));

        echo '
        <div class="boxed">
        <div>
        <div class="icon">
        ';
        Content::printIcon(($success ? 'okay' : 'error'));
        echo '
        </div>
        <h2>' , ($success ? 'Success!' : 'Failure!') , '</h2>
        </div>
        ';
        if ($success) {
            echo '
            <p>
            The CILogon Service has issued a certificate to "' ,
            htmlspecialchars(Util::getSessionVar('portalname')) , '".
            Below is a link to return to
            the site to use the issued certificate.
            </p>
            ';
            // If we got the cert from the 'oauth/authorized' script,
            // output it in an expandable/scrollable <div> for user info.
            if (!empty($certtext)) {
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
            htmlspecialchars(Util::getSessionVar('portalname')) , '".
            Below is a link to return to the site.
            </p>
            ';
        }
        echo '
        <div class="returnlink">
          <a href="' ,
          ((!empty($responseurl)) ? $responseurl :
           (Util::getSessionVar($success ? 'successuri' : 'failureuri'))) ,
          '">Return to ' ,
          htmlspecialchars(Util::getSessionVar('portalname')) , '</a>
        </div>
        </div>
        ';
        Content::printFooter();
        if ($success) {
            Util::unsetClientSessionVars();
            // Util::unsetAllUserSessionVars();
        } else {
            Util::unsetAllUserSessionVars();
        }
    }
}

/**
 * verifyOAuthToken
 *
 * This function verifies that all of the various PortalParameters
 * have been set in the PHP session.  If the first parameter is passed
 * in, it first attempts to call CILogon::getPortalParameters() and
 * populates the PHP session with the associated values.
 *
 * @param string $token (Optional) The temporary credential passed from a
 *        Community Portal to the 'delegate' script as 'oauth_token' in the
 *        URL (as a $_GET variable). Defaults to empty string.
 * @return bool True if the various parameters related to the OAuth
 *         token (callbackuri, failureuri, successuri, portalname,
 *         and tempcred) are in the PHP session, false otherwise.
 */
function verifyOAuthToken($token = '')
{
    $retval = false; // Assume OAuth session info is not valid

    // If passing in the OAuth $token, try to get the associated info
    // from the persistent store and put it into the PHP session.
    if (!empty($token)) {
        $dbs = new DBService();
        $dbs->getPortalParameters($token);
        $status = $dbs->status;
        Util::setSessionVar('portalstatus', $status);
        if (!($status & 1)) {  // STATUS_OK* codes are even-numbered
            Util::setSessionVar('callbackuri', $dbs->cilogon_callback);
            Util::setSessionVar('failureuri', $dbs->cilogon_failure);
            Util::setSessionVar('successuri', $dbs->cilogon_success);
            Util::setSessionVar('portalname', $dbs->cilogon_portal_name);
            Util::setSessionVar('tempcred', $dbs->oauth_token);
        }
    }

    // Now check to verify all session variables have data
    if (
        (!empty(Util::getSessionVar('callbackuri'))) &&
        (!empty(Util::getSessionVar('failureuri'))) &&
        (!empty(Util::getSessionVar('successuri'))) &&
        (!empty(Util::getSessionVar('portalname'))) &&
        (!empty(Util::getSessionVar('tempcred'))) &&
        (!(Util::getSessionVar('portalstatus') & 1))
    ) { // STATUS_OK* are even
        $retval = true;
    }

    // As a final check, see if this portal requires a forced skin
    if ($retval) {
        Util::getSkin()->init();
    }

    return $retval;
}
