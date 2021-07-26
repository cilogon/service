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

    // Check if this is the first time the user has visited the site from
    // the current portal.  We do this by checking the portal cookie's
    // lifetime for a positive value.  If the portal cookie has NOT YET been
    // set, then check the skin config to see if either initialremember or
    // initiallifetime has been set.  We do this here because these two
    // values set the portal cookie, which needs to be done before we go
    // to the next page (where the cookie is actually read).
    $skin = Util::getSkin();
    $pc = new PortalCookie();
    $portallife = $pc->get('lifetime');

    if ((strlen($portallife) == 0) || ($portallife == 0)) {
        $needtosetcookie = 0;

        // Try to read the skin's initiallifetime
        $initlife = $skin->getConfigOption('delegate', 'initiallifetime');
        if ((!is_null($initlife)) && ((int)$initlife > 0)) {
            $needtosetcookie = 1;
            $initlife = (int)$initlife;
        } else { // Set a default lifetime value in case initialremember is set
            $initlife = 12;
        }

        // Make sure initiallifetime is within [minlifetime..maxlifetime]
        list($minlife, $maxlife) = Util::getMinMaxLifetimes('delegate', 240);
        if ($initlife < $minlife) {
            $needtosetcookie = 1;
            $initlife = $minlife;
        } elseif ($initlife > $maxlife) {
            $needtosetcookie = 1;
            $initlife = $maxlife;
        }

        // Next, try to read the skin's initialremember
        $initialremember = $skin->getConfigOption('delegate', 'initialremember');
        if ((!is_null($initialremember)) && ((int)$initialremember > 0)) {
            $needtosetcookie = 1;
            $initialremember = (int)$initialremember;
        } else { // Set a default remember value in case initiallifetime is set
            $initialremember = 0;
        }

        if ($needtosetcookie) {
            $pc->set('remember', $initialremember);
            $pc->set('lifetime', $initlife);
            $pc->write();
        }
    }

    Content::printHeader('Welcome To The CILogon Delegation Service');

    // If the <hideportalinfo> option is set, do not show the portal info if
    // the callback uri is in the portal list.
    $showportalinfo = true;
    if (
        ((int)$skin->getConfigOption('portallistaction', 'hideportalinfo') == 1) &&
        ($skin->inPortalList(Util::getSessionVar('callbackuri')))
    ) {
        $showportalinfo = false;
    }

    if ($showportalinfo) {
        printOAuth1Consent();
    }

    Content::printWAYF();
    Content::printFooter();
}

/**
 * printOAuth1BadTokenPage
 *
 * This function prints out the HTML for the page when the oauth_token
 * (tempcred) or associated OAuth information is missing, bad, or expired.
 */
function printOAuth1ErrorPage()
{
    $log = new Loggit();

    Content::printHeader('CILogon Delegation Service');
    Content::printCollapseBegin(
        'oauth1default',
        'CILogon OAuth1 Delegation Endpoint',
        false
    );

    // CIL-1045 - Add OAuth1 Retirement banner
    echo '
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
      This service will be retired on October 1. Please migrate to
      <a target="_blank" href="https://www.cilogon.org/oidc">CILogon OIDC</a>.
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>';

    // CIL-624 If X509 certs are disabled, show a suitable error message
    // to the end user.
    if ((defined('DISABLE_X509')) && (DISABLE_X509 === true)) {
        $log->warn('OAuth1 transaction failed due to DISABLE_X509 set.');
        echo '
        <div class="card-body px-5">
          <div class="card-text my-2">
            You have reached the CILogon Delegation Service. This service is
            for use by third parties to obtain certificates for their users.
            <strong>However, downloading X.509 certificates has been
            disabled.</strong>
          </div>
          <div class="card-text my-2">
            Please direct questions to the email address at the bottom of
            the page.
          </div>
        </div> <!-- end card-body -->
        ';
    } else {
        $log->warn('Missing or invalid oauth_token.');
        echo '
        <div class="card-body px-5">
          <div class="card-text my-2">
            You have reached the CILogon Delegation Service. This service is
            for use by third parties to obtain certificates for their users.
            End users should not normally see this page.
          </div>
          <div class="card-text my-2">
          Possible reasons for seeing this page include:
          </div>
          <div class="card-text my-2">
            <ul>
              <li>You navigated directly to this page.</li>
              <li>You clicked your browser\'s "Back" button.</li>
              <li>There was a problem with the delegation process.</li>
            </ul>
          </div>
          <div class="card-text my-2">
            Please return to the previous site and try again. If the error
            persists, please contact us at the email address at the bottom of
            the page.
          </div>
          <div class="card-text my-2">
            If you are an individual wishing to download a certificate to your
            local computer, please try the <a target="_blank"
            href="https://' , Util::getHN() , '/">CILogon Service</a>.
          </div>
          <div class="card-text my-2">
            <strong>Note:</strong> You must enable cookies in your web
            browser to use this site.
          </div>
        </div> <!-- end card-body -->
        ';
    }

    Content::printCollapseEnd();
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

    $remember = 0;   // Default value for remember checkbox is unchecked
    $life = 12;      // Default value for lifetime is 12 hours

    // Check the skin for forceremember and forcelifetime
    $skin = Util::getSkin();
    $forceremember = $skin->getConfigOption('delegate', 'forceremember');
    if ((!is_null($forceremember)) && ((int)$forceremember == 1)) {
        $forceremember = 1;
    } else {
        $forceremember = 0;
    }
    $forcelife = $skin->getConfigOption('delegate', 'forcelifetime');
    if ((!is_null($forcelife)) && ((int)$forcelife > 0)) {
        $forcelife = (int)$forcelife;
    } else {
        $forcelife = 0;
    }

    // Try to read the portal coookie for the remember and lifetime values.
    $pc = new PortalCookie();
    $portalremember = $pc->get('remember');
    $portallife = $pc->get('lifetime');

    // If skin's forceremember or portal cookie's remember is set,
    // then we bypass the Allow/Deny delegate page.
    if (($forceremember == 1) || ($portalremember == 1)) {
        $remember = 1;
    }

    // If skin's forcelifetime or portal cookie's lifetime is set,
    // set lifetime accordingly and make sure value is between the
    // configured minlifetime and maxlifetime.
    if ($forcelife > 0) {
        $life = $forcelife;
    } elseif ($portallife > 0) {
        $life = $portallife;
    }
    list($minlife, $maxlife) = Util::getMinMaxLifetimes('delegate', 240);
    if ($life < $minlife) {
        $life = $minlife;
    } elseif ($life > $maxlife) {
        $life = $maxlife;
    }

    // If 'remember' is set, then auto-click the 'OK' button for the user.
    if ($remember == 1) {
        handleAllowDelegation(true);
    } else {
        // User did not check 'Remember OK' before, so show the
        // HTML to prompt user for OK or Cancel delegation.
        Content::printHeader('Confirm Allow Delegation');
        printOAuth1Certificate($life, $minlife, $maxlife, $forcelife);
        Content::printFooter();
    }
}

/**
 * printOAuth1Consent
 *
 * This function prints out the 'consent' block showing the portal name and
 * callback uris just above the 'Select an Identity Provider' block.
 */
function printOAuth1Consent()
{
    Content::printCollapseBegin(
        'oauth2consent',
        'Consent to Attribute Release',
        false
    );

    // CIL-1045 - Add OAuth1 Retirement banner
    echo '
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
      This service will be retired on October 1. Please migrate to
      <a target="_blank" href="https://www.cilogon.org/oidc">CILogon OIDC</a>.
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>';

    echo '
        <div class="card-body px-5">
          <div class="card-row">
            "', htmlspecialchars(Util::getSessionVar('portalname')), '"
            requests that you select an Identity Provider and click
            "', Content::getLogOnButtonText(), '".
            If you do not approve this request, do not proceed.
          </div>
          <div class="card-row">
            By proceeding you agree to share your <em>name</em> and
            <em>email address</em> with
            "', htmlspecialchars(Util::getSessionVar('portalname')), '".
          </div>
    ';

    printOAuth1PortalInfo();

    echo '
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
}

/**
 * printOAuth1Certificate
 *
 * This function prints out the block showing the portal name and callback
 * uris, as well as a text input for the user to set the lifetime of the
 * certificate to be delegated to the OAuth1 client.
 *
 * @param int $life The lifetime (in hours) for the delegated certificate.
 * @param int $minlife The minimum lifetime for the cert.
 * @param int $maxlife The maximum lifetime for the cert.
 * @param int $force The forced-set lifetime for the cert.
 */
function printOAuth1Certificate($life, $minlife, $maxlife, $force)
{
    $lifehelp = 'Certificate lifetime range is between ' .
        $minlife . ' and ' . $maxlife . ' hours.';
    $rememberhelp = "Check this box to automatically approve " .
        "certificate issuance to the site on future visits. " .
        "The certificate lifetime will be remembered. You will " .
        "need to clear your browser's cookies to return here.";

    Content::printCollapseBegin(
        'oauth1cert',
        'Confirm Certificate Delegation',
        false
    );

    echo '
        <div class="card-body px-5">
          <div class="row mb-3">
            "', htmlspecialchars(Util::getSessionVar('portalname')) , '"
            is requesting a certificate for you. If you approve, then
            "OK" the request. Otherwise, "Cancel" the request or
            navigate away from this page.
          </div>
    ';

    printOAuth1PortalInfo();

    Content::printFormHead();

    echo '
          <div class="container col-lg-6 offset-lg-3
          col-md-8 offset-md-2 col-sm-10 offset-sm-1">
            <div class="form-group">
              <div class="form-row">
                <label for="lifetime">Certificate Lifetime (in hours):</label>
                <input type="number" name="lifetime" id="lifetime"
                min="', $minlife, '"
                max="', $maxlife, '"
                value="', $life , '" ' ,
                (($force > 0) ? 'disabled="disabled" ' : ' ') , '
                class="form-control" required="required"
                aria-describedby="lifetime1help" />
                <small id="lifetime1help" class="form-text text-muted">',
                $lifehelp, '
                </small>
<!--[if IE]><input type="text" style="display:none;" disabled="disabled" size="1"/><![endif]-->
              </div> <!-- end form-row -->
            </div> <!-- end form-group -->

            <div class="form-group">
              <div class="form-row align-items-center justify-content-center">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox"
                  name="rememberok" id="rememberok" />
                  <label class="form-check-label"
                  for="rememberok">Remember my OK for the site</label>
                  <a href="#" tabindex="0" data-trigger="hover click"
                  class="helpcursor"
                  data-toggle="popover" data-html="true"
                  data-content="', $rememberhelp, '"><i class="fa
                  fa-question-circle"></i></a>
                </div> <!-- end form-check -->
              </div> <!-- end form-row -->
            </div> <!-- end form-group -->

            <div class="form-group">
              <div class="form-row align-items-center justify-content-center">
                <div class="col-auto">
                  <input type="submit" name="submit"
                  class="btn btn-primary submit form-control" value="OK" />
                </div>
                <div class="col-auto">
                  <input type="submit" name="submit"
                  class="btn btn-primary submit form-control" value="Cancel" />
                </div>
              </div> <!-- end form-row align-items-center -->
            </div> <!-- end form-group -->
          </div> <!-- end container -->

        </form>
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
}

/**
 * printOAuth1PortalInfo
 *
 * This function prints the portal name, success uri (i.e., the uri to
 * redirect to upon successful delegation of the certificate), and the
 * callback uri used by the OAuth1 protocol.
 */
function printOAuth1PortalInfo()
{
    echo '
    <table class="table table-striped table-sm">
    <tbody>
      <tr>
        <th>Site Name:</th>
        <td></td>
        <td>', htmlspecialchars(Util::getSessionVar('portalname')), '</td>';

    $helptext = "The location where you will be redirected upon completion.";

    echo '
      </tr>
      <tr>
        <th>Site URL:</th>
        <td><a href="#" tabindex="0" data-trigger="hover click"
          class="helpcursor" data-toggle="popover" data-html="true"
          data-content="', $helptext, '"><i class="fa
          fa-question-circle"></i></a></td>
        <td>', htmlspecialchars(Util::getSessionVar('successuri')), '</td>';

    $helptext = "The location where CILogon " .
        "will send a certificate containing your identity information.";

    echo '
      </tr>
      <tr>
        <th>Service URL:</th>
        <td><a href="#" tabindex="0" data-trigger="hover click"
          class="helpcursor" data-toggle="popover" data-html="true"
          data-content="', $helptext, '"><i class="fa
          fa-question-circle"></i></a></td>
        <td>', htmlspecialchars(Util::getSessionVar('callbackuri')), '</td>
      </tr>
    </tbody>
    </table>
    ';
}

/**
 * printOAuth1DelegationDone
 *
 * This function prints out the block after generation of the certificate.
 * The $success parameter indicates if the cert was generated successfully
 * or not. If so, the $responseurl will contain the link to redirect the
 * user to, and the $certtext will contain the contents of the cert.
 *
 * @param bool $success True if the certificate was successfully generated
 *        and delegated to the OAuth1 client.
 * @param string $responseurl The url to redirect the user to. If this is
 *        empty, then the PHP session success/failure uris will be used as
 *        determined by the $success value.
 * @param string $certtext The contents of the generated cert.
 */
function printOAuth1DelegationDone($success, $responseurl, $certtext)
{
    Content::printCollapseBegin(
        'oauth1done',
        'Certificate Delegation ' . ($success ? 'Success' : 'Failure'),
        false
    );

    echo '
        <div class="card-body px-5">
    ';

    if ($success) {
        echo '
          <div class="card-text my-2">
            The CILogon Service has issued a certificate to "' ,
            htmlspecialchars(Util::getSessionVar('portalname')) , '".
            Below is a link to return to
            the site to use the issued certificate.
          </div>
          ';
        Content::printCollapseBegin('certdetails', 'Certificate Details');
        echo '<div class="card-body px-5">
                <pre>', htmlspecialchars($certtext), '</pre>
              </div>
        ';
        Content::printCollapseEnd();
    } else {
        echo '
          <div class="card-text my-2">
            We were unable to issue a certificate to "' ,
            htmlspecialchars(Util::getSessionVar('portalname')) , '".
            Below is a link to return to the site.
          </div> <!-- end card-text -->';
    }

    echo '
          <div class="card-text my-2 text-center">
            <a class="btn btn-primary"
            href="' , ((strlen($responseurl) > 0) ? $responseurl :
            (Util::getSessionVar($success ? 'successuri' : 'failureuri'))),
            '">Return to ' ,
            htmlspecialchars(Util::getSessionVar('portalname')) , '</a>
          </div> <!-- end card-text -->
        </div> <!-- end card-body -->';

    Content::printCollapseEnd();
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
    Content::printCollapseBegin(
        'oauth1cancel',
        'Certificate Delegation Cancelled',
        false
    );

    echo '
        <div class="card-body px-5">
          <div class="card-text my-2">
            You have canceled delegation of a certificate to "' ,
            htmlspecialchars($portalname) , '".
            Below is a link to return to the portal.
            This link has been provided by the portal to be used when
            delegation of a certificate fails.
          </div>
          <div class="card-text my-2">
            <strong>Note:</strong> If you do not trust the information
            provided by the portal, <strong>do not</strong> click on the
            link below.  Instead, please contact your portal administrators
            or contact us at the email address at the bottom of the page.
          </div>
          <div class="card-text my-2 text-center">
            <a class="btn btn-primary"
            href="' , Util::getSessionVar('failureuri') , '">Return to ' ,
            htmlspecialchars($portalname) , '</a>
          </div>
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
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
 * @param bool $always True if the user selected to always allow delegation.
 */
function handleAllowDelegation($always = false)
{
    // The 'authorized' servlet may return a response URL to be used
    // instead of the success / failure URLs.
    $responseurl = '';

    $log = new Loggit();
    $log->info('Attempting to delegate a certificate to a portal...');

    $life = 0;
    // Check the skin's forcelifetime and use it if it is configured.
    $forcelife = Util::getSkin()->getConfigOption('delegate', 'forcelifetime');
    if ((!is_null($forcelife)) && ((int)$forcelife > 0)) {
        $life = (int)$forcelife;
    }

    // Next, try to get the certificate lifetime from a submitted <form>
    if ($life == 0) {
        $life = (int)(trim(Util::getPostVar('lifetime')));
    }

    // If we couldn't get lifetime from the <form>, try the cookie
    $pc = new PortalCookie();
    if ($life == 0) {
        $life = (int)($pc->get('lifetime'));
    }

    // Default lifetime to 12 hours. And then make sure lifetime is in
    // acceptable range.
    if ($life == 0) {
        $life = 12;
    }
    list($minlife, $maxlife) = Util::getMinMaxLifetimes('delegate', 240);
    if ($life < $minlife) {
        $life = $minlife;
    } elseif ($life > $maxlife) {
        $life = $maxlife;
    }

    $pc->set('remember', (int)$always);
    $pc->set('lifetime', $life);
    $pc->write();

    $success = false;  // Assume delegation of certificate failed
    $certtext = '';    // Output of 'openssl x509 -noout -text -in cert.pem'
    $myproxyinfo = Util::getSessionVar('myproxyinfo');

    // Now call out to the 'oauth/authorized' servlet to execute
    // the delegation the credential to the portal.
    $log = new Loggit();
    $ch = curl_init();
    if (!defined('OAUTH1_AUTHORIZED_URL')) {
        $log->info(
            'OAUTH1_AUTHORIZED_URL is not defined. ' .
            'This should never happen. Check config.php.'
        );
    } elseif ($ch !== false) {
        $tempcred = Util::getSessionVar('tempcred');
        $url = OAUTH1_AUTHORIZED_URL . '?' .
               'oauth_token=' . urlencode($tempcred) . '&' .
               'cilogon_lifetime=' . $life . '&' .
               'cilogon_loa=' . urlencode(Util::getLOA()) . '&' .
               'cilogon_uid=' . urlencode(Util::getSessionVar('user_uid')) .
               ((strlen($myproxyinfo) > 0) ?
                   ('&cilogon_info=' . urlencode($myproxyinfo)) : '');
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
                            if ($retcode === 0) {
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

    $log->info('Delegation of certificate to portal ' .
               ($success ? 'succeeded.' : 'failed.'));
    //CIL-507 Special log message for XSEDE
    $email = Util::getSessionVar('email');
    $clientname = Util::getSessionVar('portalname');
    $log->info("USAGE email=\"$email\" client=\"$clientname\"");
    Util::logXSEDEUsage($clientname, $email);

    // Depending on the result (success or failure), output appropriate
    // HTML to allow the user to return to the portal, or if $always
    // was set, then automatically return the user to the successuri,
    // failureuri, or cilogon_reponse_url if supplied by authorized servlet.
    if ($always) {
        $log->info("Automatically returning to portal's " .
            ($success ? 'success' : 'failure') . ' url.');
        $location = 'Location: ' . ((strlen($responseurl) > 0) ? $responseurl :
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
        Content::printHeader('Delegation ' . ($success ? 'Successful' : 'Failed'));
        printOAuth1DelegationDone($success, $responseurl, $certtext);
        Content::printFooter();
        if ($success) {
            Util::unsetClientSessionVars();
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

    // CIL-624 If X509 certs are disabled, prevent the OAuth1 endpoint
    // from running since OAuth1 always generates an X.509 cert.
    if ((defined('DISABLE_X509')) && (DISABLE_X509 === true)) {
        return false;
    }

    // If passing in the OAuth $token, try to get the associated info
    // from the persistent store and put it into the PHP session.
    if (strlen($token) > 0) {
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
        (strlen(Util::getSessionVar('callbackuri')) > 0) &&
        (strlen(Util::getSessionVar('failureuri')) > 0) &&
        (strlen(Util::getSessionVar('successuri')) > 0) &&
        (strlen(Util::getSessionVar('portalname')) > 0) &&
        (strlen(Util::getSessionVar('tempcred')) > 0) &&
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
