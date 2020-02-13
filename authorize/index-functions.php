<?php

/**
 * This file contains functions called by index-site.php. The index-site.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;
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

    Content::printHeader(
        'Welcome To The CILogon OpenID Connect Authorization Service'
    );

    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);

    // If the <hideportalinfo> option is set, do not show the portal info
    // if the OIDC redirect_uri or client_id is in the portal list.
    $showportalinfo = true;
    $skin = Util::getSkin();
    if (
        ((int)$skin->getConfigOption('portallistaction', 'hideportalinfo') == 1) &&
        (
            ($skin->inPortalList($clientparams['redirect_uri'])) ||
            ($skin->inPortalList($clientparams['client_id']))
        )
    ) {
        $showportalinfo = false;
    }

    if ($showportalinfo) {
        printOIDCConsent();
    }
    Content::printWAYF();
    Content::printFooter();
}

/**
 * printOIDCErrorPage
 *
 * This function prints out the HTML for the page when the the various
 * OIDC parameters sent by the client are missing or bad.
 */
function printOIDCErrorPage()
{
    $log = new Loggit();
    $log->warn('Missing or invalid OIDC parameters.');

    Content::printHeader('CILogon Authorization Endpoint');
    Content::printCollapseBegin('oidcdefault', 'CILogon OIDC Authorization Endpoint', false);

    echo '
        <div class="card-body px-5">
          <div class="card-text my-2">
            You have reached the CILogon OAuth2/OpenID Connect (OIDC) 
            Authorization Endpoint. This service is for use by OAuth2/OIDC 
            Relying Parties (RPs) to authorize users of the CILogon Service. 
            End users should not normally see this page.
          </div> <!-- end row -->
    ';

    $client_error_msg = Util::getSessionVar('client_error_msg');
    Util::unsetSessionVar('client_error_msg');
    if (strlen($client_error_msg) > 0) {
        echo '<div class="alert alert-danger" role="alert">', $client_error_msg, '</div>';
    } else {
        echo '
          <div class="card-text my-2">
            Possible reasons for seeing this page include:
          </div> <!-- end row -->
          <div class="card-text my-2">
            <ul>
              <li>You navigated directly to this page.</li>
              <li>You clicked your browser\'s "Back" button.</li>
              <li>There was a problem with the OpenID Connect client.</li>
            </ul>
          </div> <!-- end row -->
        ';
    }

    echo '
          <div class="card-text my-2">
            For assistance, please contact us at the email address at the
            bottom of the page.
          </div>
          <div class="card-text my-2">
            <strong>Note:</strong> You must enable cookies in your web 
            browser to use this site.
          </div>
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
    Content::printFooter();
}

/**
 * printMainPage
 *
 * This function is poorly named for the OIDC case, but is called by
 * gotUserSucces, so the name stays. This function is called once the
 * user has successfully logged on at the selected IdP. In the OIDC
 * case, the user's UID is then paired with the OIDC 'code' and
 * 'authntime' in the datastore so that it can be fetched later when
 * the OIDC client wants to get userinfo or a certificate. There
 * really isn't anything 'printed' to the user here. Control is
 * simply redirected to the OIDC client with appropriate success or
 * error response.
 */
function printMainPage()
{
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    $redirect = 'Location: ' . $clientparams['redirect_url'];

    $log = new Loggit();
    $log->info('Calling setTransactionState dbService method...');

    $dbs = new DBService();
    if (
        ($dbs->setTransactionState(
            $clientparams['code'],
            Util::getSessionVar('uid'),
            Util::getSessionVar('authntime'),
            Util::getLOA(),
            Util::getSessionVar('myproxyinfo')
        )) && (!($dbs->status & 1))
    ) { // STATUS_OK codes are even
        // CIL-360 - Check for Response Mode
        // http://openid.net/specs/oauth-v2-multiple-response-types-1_0.html#ResponseModes
        if (isset($clientparams['response_mode'])) {
            $responsemode = $clientparams['response_mode'];
            if ($responsemode == 'query') {
                // This is the default mode for 'code' response
            } elseif ($responsemode == 'fragment') {
                // Replace '?' with '#'
                $redirect = str_replace('?', '#', $redirect);
            } elseif ($responsemode == 'form_post') {
                // https://openid.net/specs/oauth-v2-form-post-response-mode-1_0.html
                // At this point, $clientparams['redirect_url'] contains
                // both the callback uri and all query string parameters
                // that should be passed to the callback uri. We need
                // to separate the two so we can put the query parameters
                // into hidden <input> fields in the output form.
                $orig_redirect_uri = $clientparams['redirect_uri'];
                $full_redirect_url = $clientparams['redirect_url'];
                $queryparams = str_replace(
                    $orig_redirect_uri . '?',
                    '',
                    $full_redirect_url
                );
                Util::unsetClientSessionVars();
                // Util::unsetAllUserSessionVars();
                // Get the components of the response (split by '&')
                $comps = explode('&', $queryparams);
                $outform = '<html>
  <head><title>Submit This Form</title></head>
  <body onload="javascript:document.forms[0].submit()">
    <form method="post" action="' . $orig_redirect_uri . '">
    ';
                foreach ($comps as $value) {
                    $params = explode('=', $value);
                    $outform .= '<input type="hidden" name="' . $params[0] .
                         '" value="' . html_entity_decode($params[1]) . '"/>';
                }
                $outform .= '
    </form>
  </body>
</html>';
                $log->info(
                    'response_mode=form_post; outputting form' . "\n" .
                    $outform
                );
                echo $outform;
                exit; // No further processing necessary
            }
        }
        $log->info('setTransactionState succeeded, redirect to ' . $redirect);
        // CIL-507 Special log message for XSEDE
        $log->info('USAGE email="' . Util::getSessionVar('emailaddr') .
                   '" client="' . $clientparams['client_name'] . '"');
    } else { // dbservice error
        $errstr = '';
        if (!is_null($dbs->status)) {
            $errstr = array_search($dbs->status, DBService::$STATUS);
        }
        $redirect = 'Location: ' . $clientparams['redirect_uri'] .
            (preg_match('/\?/', $clientparams['redirect_uri']) ? '&' : '?') .
            'error=server_error&error_description=' .
            'Unable%20to%20associate%20user%20UID%20with%20OIDC%20code' .
            ((isset($clientparams['state'])) ?
                '&state=' . $clientparams['state'] : '');
        $log->info("setTransactionState failed $errstr, redirect to $redirect");
        Util::sendErrorAlert(
            'dbService Error',
            'Error calling dbservice action "setTransactionState" in ' .
            'OIDC authorization endpoint\'s printMainPage() method. ' .
            $errstr . ' Redirected to ' . $redirect
        );
        Util::unsetUserSessionVars();
    }

    Util::unsetClientSessionVars();
    // Util::unsetAllUserSessionVars();
    header($redirect);
    exit; // No further processing necessary
}

/**
 * printOIDCConsent
 *
 * This function prints out the block showing the scopes requested by the
 * OIDC client.
 */
function printOIDCConsent()
{
    // Look in the 'scope' OIDC parameter to see which attributes are
    // being requested. The values we care about are 'email', 'profile'
    // (for first/last name), and 'edu.uiuc.ncsa.myproxy.getcert'
    // (which gives a certificate containing first/last name AND email).
    // Anything else should just be output as-is.
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    $scopes = preg_split("/[\s\+]+/", $clientparams['scope']);
    $scopes = array_unique($scopes); // Remove any duplicates

    Content::printCollapseBegin('oidcconsent', 'Consent to Attribute Release', false);

    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    echo '
        <div class="card-body px-5">
          <div class="card-text my-2">
            <a target="_blank" href="' ,
            htmlspecialchars($clientparams['client_home_url']) , '">',
            htmlspecialchars($clientparams['client_name']) , '</a>' ,
            ' requests access to the following information.
            If you do not approve this request, do not proceed.
          </div> <!-- end row -->
          <ul>
    ';

    if (in_array('openid', $scopes)) {
        echo '<li>Your CILogon user identifier</li>';
        $scopes = array_diff($scopes, ['openid']);
    }
    if (
        (in_array('profile', $scopes)) ||
        (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes))
    ) {
        echo '<li>Your name</li>';
        $scopes = array_diff($scopes, ['profile']);
    }
    if (
        (in_array('email', $scopes)) ||
        (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes))
    ) {
        echo '<li>Your email address</li>';
        $scopes = array_diff($scopes, ['email']);
    }
    if (in_array('org.cilogon.userinfo', $scopes)) {
        echo '<li>Your username and affiliation from your identity provider</li>';
        $scopes = array_diff($scopes, ['org.cilogon.userinfo']);
    }
    if (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes)) {
        echo '<li>A certificate that allows "' ,
        htmlspecialchars($clientparams['client_name']) ,
        '" to act on your behalf</li>';
        $scopes = array_diff($scopes, ['edu.uiuc.ncsa.myproxy.getcert']);
    }
    // Output any remaining scopes as-is
    foreach ($scopes as $value) {
        echo '<li>', $value , '</li>';
    }
    echo '</ul>
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
}

/**
 * verifyOIDCParams
 *
 * This function verifies that all of the various OIDC parameters are
 * set in the PHP session. First, the function checks if an OIDC
 * client has passed appropriate parameters to the authorization
 * endpoint. If so, we call the 'real' OA4MP OIDC authorization
 * endpoint and let it verify the client parameters. Upon successful
 * return, we call the getClient() function of the dbService to get
 * the OIDC client name and homepage for display to the user. All
 * client parameters (including the ones passed in) are saved to the
 * 'clientparams' PHP session variable, which is encoded as a JSON
 * token to preserve arrays. If there are any errors, false is returned
 * and an email is sent. In some cases the session variable
 * 'client_error_msg' is set so it can be displayed by the
 * printOIDCErrorPage() function.
 *
 * @return bool True if the various parameters related to the OIDC
 *         session are present. False otherwise.
 */
function verifyOIDCParams()
{
    $retval = false; // Assume OIDC session info is not valid

    // Combine the $_GET and $_POST arrays into a single array which can be
    // stored in the 'clientparams' session variable as a JSON object.
    $clientparams = array();
    foreach ($_GET as $key => $value) {
        $clientparams[$key] = $value;
    }
    foreach ($_POST as $key => $value) {
        $clientparams[$key] = $value;
    }

    // If the 'redirect_uri' parameter was passed in then let the 'real'
    // OA4MP OIDC authz endpoint handle parse the request since it might be
    // possible to return an error code to the client.
    if (isset($clientparams['redirect_uri'])) {
        $ch = curl_init();
        if ($ch !== false) {
            $url = OAUTH2_CREATE_TRANSACTION_URL;
            if (count($_GET) > 0) {
                // CIL-658 Look for double-encoded spaces in 'scope'
                foreach ($_GET as $key => $value) {
                    if ($key == 'scope') {
                        $_GET[$key] = preg_replace('/\+/', ' ', $value);
                    }
                }
                $url .= (preg_match('/\?/', $url) ? '&' : '?') .
                    http_build_query($_GET);
            }
            if (count($_POST) > 0) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CUROPT_POSTFIELDS, http_build_query($_POST));
            }
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Catch redirects
            $output = curl_exec($ch);
            if (curl_errno($ch)) { // Send alert on curl errors
                Util::sendErrorAlert(
                    'cUrl Error',
                    'cUrl Error    = ' . curl_error($ch) . "\n" .
                    "URL Accessed  = $url" .
                    "\n\n" .
                    'clientparams = ' . print_r($clientparams, true)
                );
                $clientparams = array();
            } else {
                $info = curl_getinfo($ch);
                if ($info !== false) {
                    if (
                        (isset($info['http_code'])) &&
                        ($info['http_code'] == 200)
                    ) {
                        // The OA4MP OIDC authz endpoint responded with 200
                        // (success). The body of the message should be a
                        // JSON token containing the appropriate parameters
                        // such as the 'code'.
                        $json = json_decode($output, true);
                        if (isset($json['code'])) {
                            // Got 'code' - save to session and call
                            // dbService 'getClient' to get info about
                            // OIDC client to display to user
                            $clientparams['redirect_url'] =
                                $clientparams['redirect_uri'] .
                                (preg_match('/\?/', $clientparams['redirect_uri']) ? '&' : '?') .
                                http_build_query($json);
                            $clientparams['code'] = $json['code'];
                            // CIL-618 Read OIDC client info from database
                            if (!Util::getOIDCClientParams($clientparams)) {
                                Util::sendErrorAlert(
                                    'getOIDCClientParams Error',
                                    'Error getting OIDC client parameters ' .
                                    'in verifyOIDCParams() function for ' .
                                    'client_id="' .
                                    $clientparams['client_id'] . '".'
                                );
                                $clientparams = array();
                            }
                        } else {
                            // Either the output returned was not a valid
                            // JSON token, or there was no 'code' found in
                            // the returned JSON token.
                            // CIL-575 Check for a "status=..." line in the
                            // returned $output to print a useful error
                            // message to the user (and in the error email).
                            $errortxt = '';
                            if (
                                preg_match(
                                    '/status=(\d+)/',
                                    $output,
                                    $matches
                                )
                            ) {
                                $errornum = $matches[1];
                                $errstr = array_search(
                                    $errornum,
                                    DBService::$STATUS
                                );
                                $errortxt = @DBService::$STATUS_TEXT[$errstr];
                            }

                            Util::sendErrorAlert(
                                'OA4MP OIDC authz endpoint error',
                                (!empty($errortxt) ? $errortxt :
                                'The OA4MP OIDC authorization endpoint ' .
                                'returned an HTTP response 200, but either ' .
                                'the output was not a valid JSON token, or ' .
                                'there was no "code" in the JSON token. ' .
                                ((strlen($output) > 0) ?
                                    "\n\nReturned output =\n$output" : '')) .
                                "\n\n" .
                                'curl_getinfo = ' . print_r($info, true) . "\n\n" .
                                'clientparams = ' . print_r($clientparams, true) .
                                "\n"
                            );
                            Util::setSessionVar(
                                'client_error_msg',
                                'There was an unrecoverable error during the transaction. ' .
                                'CILogon system administrators have been notified. ' .
                                (!empty($errortxt) ? "<p><b>Error message: $errortxt</b><p>" : '')
                            );
                            $clientparams = array();
                        }
                    } elseif (
                        (isset($info['http_code'])) &&
                        ($info['http_code'] == 302)
                    ) {
                        // The OA4MP OIDC authz endpoint responded with 302
                        // (redirect) which indicates an OIDC error was
                        // detected. We need to check the response for an
                        // 'error' and simply redirect error to OIDC client.
                        $redirect_url = '';
                        if (isset($info['redirect_url'])) {
                            $redirect_url = $info['redirect_url'];
                            $clientparams['redirect_url'] = $redirect_url;
                            // CIL-407 - In case of two question marks '?'
                            // in redirect_url (caused by OIDC authz endpoint
                            // blindly appending "?error=..."), change all
                            // but the first '?' to '&'.
                            // https://stackoverflow.com/a/37150213
                            if (substr_count($redirect_url, '?') > 1) {
                                $arr = explode('?', $redirect_url, 2);
                                $arr[1] = str_replace('?', '&', $arr[1]);
                                $redirect_url = implode('?', $arr);
                            }
                        }
                        // Get components of redirect_url - need 'query'
                        $comps = parse_url($redirect_url);
                        if ($comps !== false) {
                            // Look for 'error' in query
                            $query = '';
                            if (isset($comps['query'])) {
                                $query = $comps['query'];
                                $query = html_entity_decode($query);
                            }
                            $queries = explode('&', $query);
                            $params = array();
                            foreach ($queries as $value) {
                                $x = explode('=', $value);
                                $params[$x[0]] = $x[1];
                            }
                            if (isset($params['error'])) {
                                // Got 'error' - simply return to OIDC client
                                Util::unsetAllUserSessionVars();
                                header("Location: $redirect_url");
                                exit; // No further processing necessary
                            } else { // Weird params - Should never get here!
                                Util::sendErrorAlert(
                                    'OA4MP OIDC 302 Error',
                                    'The OA4MP OIDC authz endpoint ' .
                                    'returned a 302 redirect (error) ' .
                                    'response, but there was no "error" ' .
                                    "query parameter.\n\n" .
                                    "redirect_url = $redirect_url\n\n" .
                                    'clientparams = ' .
                                    print_r($clientparams, true) .
                                    "\n"
                                );
                                $clientparams = array();
                            }
                        } else { // parse_url($redirect_url) gave error
                            Util::sendErrorAlert(
                                'parse_url(redirect_url) error',
                                'There was an error when attempting to ' .
                                'parse the redirect_url. This should never ' .
                                "happen.\n\n" .
                                "redirect_url = $redirect_url\n\n" .
                                'clientparams = ' . print_r($clientparams, true) .
                                "\n"
                            );
                            $clientparams = array();
                        }
                    } else {
                        // An HTTP return code other than 200 (success) or
                        // 302 (redirect) means that the OA4MP OIDC authz
                        // endpoint tried to handle an unrecoverable error,
                        // possibly by outputting HTML. If so, then we
                        // ignore it and output our own error message to the
                        // user.
                        Util::sendErrorAlert(
                            'OA4MP OIDC authz endpoint error',
                            'The OA4MP OIDC authorization endpoint returned ' .
                            'an HTTP response other than 200 or 302. ' .
                            ((strlen($output) > 0) ?
                                "\n\nReturned output =\n$output" : '') .
                            "\n\n" .
                            'curl_getinfo = ' . print_r($info, true) . "\n\n" .
                            'clientparams = ' . print_r($clientparams, true) .
                            "\n"
                        );
                        // CIL-423 Better end-user error output for errors.
                        // Scan output for ServletException message.
                        $errstr = '';
                        if (
                            preg_match(
                                '/javax.servlet.ServletException:\s?(.*)/',
                                $output,
                                $matches
                            )
                        ) {
                            $output = '';
                            $errstr = '
                            <div>
                            <p>Error Message: <b>' .
                            $matches[1] . '</b>.</p>
                            <ul>
                            <li>Did you <b>register</b> your OAuth2/OIDC client? If not, go
                            <b><a target="_blank" href="https://' .
                            Util::getHN()
                            . '/oauth2/register">here</a></b> to do so.</li>
                            <li>Did you receive confirmation that your OAuth2/OIDC client
                            was <b>approved</b>? If not, please wait up to 48 hours for an
                            approval email from CILogon administrators.</li>
                            <li>Did you configure your OAuth2/OIDC client with the
                            registered <b>client ID and secret</b>?</li>
                            </ul>
                            </div>';
                        }
                        Util::setSessionVar(
                            'client_error_msg',
                            'There was an unrecoverable error during the transaction. ' .
                            'CILogon system administrators have been notified.' .
                            ((strlen($errstr) > 0) ? $errstr : '') .
                            ((strlen($output) > 0) ?
                            '<br/><pre>' .
                            preg_replace('/\+/', ' ', $output) .
                            '</pre>' : '')
                        );
                        $clientparams = array();
                    }
                } else { // curl_getinfo() returned false - should not happen
                    Util::sendErrorAlert(
                        'curl_getinfo error',
                        'When attempting to talk to the OA4MP OIDC ' .
                        'authorization endpoint, curl_getinfo() returned ' .
                        "false. This should never happen.\n\n" .
                        'clientparams = ' . print_r($clientparams, true) . "\n"
                    );
                    $clientparams = array();
                }
            }
            curl_close($ch);
        } else { // curl_init() returned false - should not happen
            Util::sendErrorAlert(
                'curl_init error',
                'When attempting to talk to the OA4MP OIDC authorization ' .
                'endpoint, curl_init() returned false. This should never ' .
                "happen.\n\n" .
                'clientparams = ' . print_r($clientparams, true) . "\n"
            );
            $clientparams = array();
        }

    // If redirect_uri was not passed in, but one of the other required OIDC
    // parameters WAS passed in, then assume that this was an attempt by an
    // OIDC client to use the authz endpoint, and display an error message
    // that at least one parameter (redirect_uri) was missing from the
    // request. Note that since we don't have a redirect_uri, we cannot
    // return code flow back to the OIDC client.
    } elseif (
        (isset($clientparams['scope'])) ||
        (isset($clientparams['response_type'])) ||
        (isset($clientparams['client_id']))
    ) {
        Util::sendErrorAlert(
            'CILogon OIDC authz endpoint error',
            'The CILogon OIDC authorization endpoint received a request ' .
            'from an OIDC client, but at least one of the required ' .
            'parameters (redirect_uri) was missing. ' .
            "\n\n" .
            'clientparams = ' . print_r($clientparams, true) .
            "\n"
        );
        Util::setSessionVar(
            'client_error_msg',
            'It appears that an OpenID Connect client attempted to ' .
            'initiate a session with the CILogon Service, but at least ' .
            'one of the requried parameters was missing. CILogon ' .
            'system administrators have been notified.'
        );
        $clientparams = array();

    // If none of the required OIDC authz endpoint parameters were passed
    // in, then this might be a later step in the authz process. So check
    // the session variable array 'clientparams' for the required
    // information.
    } else {
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    }

    // Now check to verify all variables have data
    if (
        (isset($clientparams['redirect_uri'])) &&
        (isset($clientparams['scope'])) &&
        (isset($clientparams['response_type'])) &&
        (isset($clientparams['client_id'])) &&
        (isset($clientparams['code'])) &&
        (isset($clientparams['client_name'])) &&
        (isset($clientparams['client_home_url'])) &&
        (isset($clientparams['client_callback_uri'])) &&
        (isset($clientparams['redirect_url'])) &&
        (isset($clientparams['clientstatus'])) &&
        (!($clientparams['clientstatus'] & 1))
    ) { // STATUS_OK* are even
        $retval = true;
        Util::setSessionVar('clientparams', json_encode($clientparams));
    }

    return $retval;
}
