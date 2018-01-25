<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\DBService;
use CILogon\Service\Loggit;

Util::startPHPSession();

// The full URL of the 'oauth2/init' OAuth 2.0 (OIDC) script
define('AUTHORIZE_URL', 'http://localhost:8080/oauth2/init');

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
// Note: replace CR/LF with space for 'Show/Hide Help' buttons.
$retchars = array("\r\n","\n","\r");
$submit = str_replace(
    $retchars,
    " ",
    Util::getCsrf()->verifyCookieAndGetSubmit()
);
Util::unsetSessionVar('submit');

$log = new Loggit();
$log->info('submit="' . $submit . '"');

// First, check to see if the info related to the OIDC client exists
// in the current PHP session. If so, continue processing based on the
// 'submit' value. Otherwise, print out error message about bad or
// missing OpenID Connect parameters.
if (verifyOIDCParams()) {
    // Get the OIDC client parameters from the PHP session.
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);

    // Depending on the value of the clicked 'submit' button or the
    // equivalent PHP session variable, take action or print out HTML.
    switch ($submit) {
        case 'Log On': // Check for OpenID or InCommon usage.
        case 'Continue': // For OOI
            // Need to check for 'max_age' OIDC parameeter. If elapsed time
            // since last user authentication is greater than max_age, then
            // set 'forceauthn' session variable to force the user to
            // (re)authenticate.
            if (isset($clientparams['max_age'])) {
                $max_age = (int)$clientparams['max_age'];
                if (strlen(Util::getSessionVar('authntime')) > 0) {
                    $authntime = (int)Util::getSessionVar('authntime');
                    $currtime = time();
                    if (($authtime > $currtime) || // Weird error!!!
                        (($currtime - $authtime) > $max_age)) {
                        Util::setSessionVar('forceauthn', '1');
                    }
                } else { // No authntime - assume no user authentication
                    Util::setSessionVar('forceauthn', '1');
                }
            }
            Content::handleLogOnButtonClicked();
            break; // End case 'Log On'

        case 'gotuser': // Return from the getuser script
            Content::handleGotUser();
            break; // End case 'gotuser'

        case 'Proceed': // Proceed after 'User Changed' or Error page
        case 'Done with Two-Factor':
            Util::verifySessionAndCall('printMainPage');
            break; // End case 'Proceed'

        case 'Cancel': // User denies release of attributes
            // If user clicked the 'Cancel' button, return to the
            // OIDC client with an error message.
            $redirect = 'Location: ' . $clientparams['redirect_uri'] .
                (preg_match('/\?/', $clientparams['redirect_uri'])?'&':'?') .
                'error=access_denied&error_description=' .
                'User%20denied%20authorization%20request' .
                ((isset($clientparams['state'])) ?
                    '&state='.$clientparams['state'] : '');
            Util::unsetAllUserSessionVars();
            header($redirect);
            exit; // No further processing necessary
            break; // End case 'Cancel'

        case 'Manage Two-Factor':
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::printTwoFactorPage'
            );
            break; // End case 'Manage Two-Factor'

        case 'Enable':   // Enable / Disable two-factor authentication
        case 'Disable':
        case 'Verify':   // Log in with Google Authenticator
        case 'Disable Two-Factor':
            $enable = !preg_match('/^Disable/', $submit);
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleEnableDisableTwoFactor',
                array($enable)
            );
            break; // End case 'Enable' / 'Disable'

        case 'I Lost My Phone':
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleILostMyPhone'
            );
            break; // End case 'I Lost My Phone'

        case 'Enter': // Verify Google Authenticator one time password
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleGoogleAuthenticatorLogin'
            );
            break; // End case 'Enter'

        case 'EnterDuo': // Verify Duo Security login
            Util::verifySessionAndCall(
                'CILogon\\Service\\Content::handleDuoSecurityLogin'
            );
            break; // End case 'EnterDuo'

        case 'Show  Help ': // Toggle showing of help text on and off
        case 'Hide  Help ':
            Content::handleHelpButtonClicked();
            break; // End case 'Show Help' / 'Hide Help'

        default: // No submit button clicked nor PHP session submit variable set
            Content::handleNoSubmitButtonClicked();

            break; // End default case
    } // End switch ($submit)
} else { // Failed to verify OIDC client parameters in PHP session
    printOIDCErrorPage();
}

/**
 * printLogonPage
 *
 * This function prints out the HTML for the main cilogon.org page.
 * Explanatory text is shown as well as a button to log in to an IdP
 * and get rerouted to the Shibboleth protected getuser script.
 */
function printLogonPage()
{
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);

    $log = new Loggit();
    $log->info('Welcome page hit.');

    Util::setSessionVar('stage', 'logon'); // For Show/Hide Help button clicks

    Content::printHeader(
        'Welcome To The CILogon OpenID Connect Authorization Service'
    );

    echo '
    <div class="boxed">
    ';

    // If the <hideportalinfo> option is set, do not show the portal info if
    // the OIDC redirect_uri is in the portal list.
    $showportalinfo = true;
    $skin = Util::getSkin();
    if (((int)$skin->getConfigOption('portallistaction', 'hideportalinfo') == 1) &&
        ($skin->inPortalList($clientparams['redirect_uri']))) {
        $showportalinfo = false;
    }

    if ($showportalinfo) {
        // Look in the 'scope' OIDC parameter to see which attributes are
        // being requested. The values we care about are 'email', 'profile'
        // (for first/last name), and 'edu.uiuc.ncsa.myproxy.getcert'
        // (which gives a certificate containing first/last name AND email).
        $attrs = array();
        $scope = $clientparams['scope'];
        if (preg_match('/openid/', $scope)) {
            $attrs['openid'] = true;
        }
        if (preg_match('/email/', $scope)) {
            $attrs['email'] = true;
        }
        if (preg_match('/profile/', $scope)) {
            $attrs['name'] = true;
        }
        if (preg_match('/org.cilogon.userinfo/', $scope)) {
            $attrs['cilogon'] = true;
        }
        if (preg_match('/edu.uiuc.ncsa.myproxy.getcert/', $scope)) {
            $attrs['email'] = true;
            $attrs['name'] = true;
            $attrs['cert'] = true;
        }

        echo '
          <br/>
          <p style="text-align:center"> <a target="_blank" href="' ,
          htmlspecialchars($clientparams['client_home_uri']) , '">',
          htmlspecialchars($clientparams['client_name']) , '</a>' ,
          ' requests access to the following information.
          If you do not approve this request, do not proceed.
          </p>
          ';

        echo '<ul style="max-width:660px;margin:0 auto">
          ';
        if (isset($attrs['openid'])) {
            echo '<li>Your CILogon username</li>';
        }
        if (isset($attrs['name'])) {
            echo '<li>Your name</li>';
        }
        if (isset($attrs['email'])) {
            echo '<li>Your email address</li>';
        }
        if (isset($attrs['cilogon'])) {
            echo '<li>Your username and affiliation from your identity provider</li>';
        }
        if (isset($attrs['cert'])) {
            echo '<li>A certificate that allows "' ,
            htmlspecialchars($clientparams['client_name']) ,
            '" to act on your behalf</li>';
        }
        echo '</ul>
        ';
    }

    Content::printWAYF(true, false);

    echo '
    </div> <!-- End boxed -->
    ';

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

    echo '
    <div class="boxed">
      <br class="clear"/>
      <p>
      You have reached the CILogon OAuth2/OpenID Connect (OIDC) Authorization 
      Endpoint. This service is for use by OAuth2/OIDC Relying Parties (RPs)
      to authorize users of the CILogon Service. End users should not normally
      see this page.
      </p>
    ';

    $client_error_msg = Util::getSessionVar('client_error_msg');
    Util::unsetSessionVar('client_error_msg');
    if (strlen($client_error_msg) > 0) {
        echo "<p>$client_error_msg</p>";
    } else {
        echo '
          <p>
          Possible reasons for seeing this page include:
          </p>
          <ul>
          <li>You navigated directly to this page.</li>
          <li>You clicked your browser\'s "Back" button.</li>
          <li>There was a problem with the OpenID Connect client.</li>
          </ul>
        ';
    }

    echo '
      <p>
      For assistance, please contact us at the email address at the
      bottom of the page.
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
    $redirect = '';

    $log = new Loggit();
    $log->info('Calling setTransactionState dbService method...');

    $dbs = new DBService();
    if (($dbs->setTransactionState(
        $clientparams['code'],
        Util::getSessionVar('uid'),
        Util::getSessionVar('authntime'),
        Util::getSessionVar('loa'),
        Util::getSessionVar('myproxyinfo')
    )) && (!($dbs->status & 1))) { // STATUS_OK codes are even
        $redirect = 'Location: ' . $clientparams['redirect_url'];
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
                '&state='.$clientparams['state'] : '');
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
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    $showhelp = Util::getSessionVar('showhelp');
    $helptext = "The Client Name is provided by the site to CILogon and has not been vetted.";

    echo '
    <table class="portalinfo' , $suffix , '">
    <tr class="inforow">
      <th title="' , $helptext ,'">Client&nbsp;Name:</th>
      <td title="' , $helptext ,'">' ,
      htmlspecialchars($clientparams['client_name']) , '</td>
    ';

    if ($showhelp == 'on') {
        echo ' <td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Client Home is the home page of the client and is provided for your information.";

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Client&nbsp;Home:</th>
      <td title="' , $helptext , '">' ,
          htmlspecialchars($clientparams['client_home_uri']) , '</td>
    ';

    if ($showhelp == 'on') {
        echo '<td class="helpcell">' , $helptext , '</td>';
    }

    $helptext = "The Redirect URL is the location to which CILogon will send OpenID Connect response messages.";

    echo '
    </tr>
    <tr class="inforow">
      <th title="' , $helptext , '">Redirect&nbsp;URL:</th>
      <td title="' , $helptext , '">' ,
          htmlspecialchars($clientparams['redirect_uri']) , '</td>
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
            $url = AUTHORIZE_URL;
            if (count($_GET) > 0) {
                $url .= '?' . http_build_query($_GET);
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
                    if ((isset($info['http_code'])) &&
                        ($info['http_code'] == 200)) {
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
                                (preg_match('/\?/', $clientparams['redirect_uri'])?'&':'?') .
                                http_build_query($json);
                            $clientparams['code'] = $json['code'];
                            $dbs = new DBService();
                            if (($dbs->getClient(
                                $clientparams['client_id']
                            )) && (!($dbs->status &1))) {
                                // STATUS_OK is even
                                $status = $dbs->status;
                                $clientparams['clientstatus'] = $status;
                                // STATUS_OK* codes are even-numbered
                                if (!($status & 1)) {
                                    $clientparams['client_name'] =
                                        $dbs->client_name;
                                    $clientparams['client_home_uri'] =
                                        $dbs->client_home_uri;
                                    $clientparams['client_callback_uris'] =
                                        $dbs->client_callback_uris;
                                } else { // dbservice error
                                    $errstr = 'Unknown error.';
                                    if (!is_null($dbs->status)) {
                                        $errstr = array_search(
                                            $dbs->status,
                                            DBService::$STATUS
                                        );
                                    }
                                    Util::sendErrorAlert(
                                        'dbService Error',
                                        'Error calling dbservice ' .
                                        'action "getClient" in ' .
                                        'verifyOIDCParams() method. ' .
                                        $errstr
                                    );
                                    $clientparams = array();
                                }
                            } else {
                                Util::sendErrorAlert(
                                    'dbService Error',
                                    'Error calling dbservice ' .
                                    'action "getClient" in ' .
                                    'verifyOIDCParams() method.'
                                );
                                $clientparams = array();
                            }
                        } else {
                            // Either the output returned was not a valid
                            // JSON token, or there was no 'code' found in
                            // the returned JSON token.
                            Util::sendErrorAlert(
                                'OA4MP OIDC authz endpoint error',
                                'The OA4MP OIDC authorization endpoint ' .
                                'returned an HTTP response 200, but either ' .
                                'the output was not a valid JSON token, or ' .
                                'there was no "code" in the JSON token. ' .
                                ((strlen($output) > 0) ?
                                    "\n\nReturned output =\n$output" : '') .
                                "\n\n" .
                                'curl_getinfo = ' . print_r($info, true) . "\n\n" .
                                'clientparams = ' . print_r($clientparams, true) .
                                "\n"
                            );
                            Util::setSessionVar(
                                'client_error_msg',
                                'There was an unrecoverable error during the transaction. ' .
                                'CILogon system administrators have been notified.'
                            );
                            $clientparams = array();
                        }
                    } elseif ((isset($info['http_code'])) &&
                        ($info['http_code'] == 302)) {
                        // The OA4MP OIDC authz endpoint responded with 302
                        // (redirect) which indicates an OIDC error was
                        // detected. We need to check the response for an
                        // 'error' and simply redirect error to OIDC client.
                        $redirect_url = '';
                        if (isset($info['redirect_url'])) {
                            $redirect_url = $info['redirect_url'];
                            $clientparmas['redirect_url'] = $redirect_url;
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
                                $clientparams = array();
                                Util::unsetAllUserSessionVars();
                                header("Location: $redirect_url");
                                exit; // No further processing necessary
                            } else { // Weird params - Should never get here!
                                Util::sendErrorAlert(
                                    'OA4MP OIDC 302 Error',
                                    'The OA4MP OIDC authz endpoint '.
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
                                'clientparams = '.print_r($clientparams, true) .
                                "\n"
                            );
                            $clientparams = array();
                        }
                    } else {
                        // An HTTP return code than 200 (success) or 302
                        // (redirect) means that the OA4MP OIDC authz
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
                        if (preg_match(
                            '/javax.servlet.ServletException:\s?(.*)/',
                            $output,
                            $matches
                        )) {
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
                'When attempting to talk to the OA4MP OIDC authorization '.
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
    } elseif ((isset($clientparams['scope'])) ||
              (isset($clientparams['response_type'])) ||
              (isset($clientparams['client_id']))) {
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
    if ((isset($clientparams['redirect_uri'])) &&
        (isset($clientparams['scope'])) &&
        (isset($clientparams['response_type'])) &&
        (isset($clientparams['client_id'])) &&
        (isset($clientparams['code'])) &&
        (isset($clientparams['client_name'])) &&
        (isset($clientparams['client_home_uri'])) &&
        (isset($clientparams['client_callback_uris'])) &&
        (isset($clientparams['redirect_url'])) &&
        (isset($clientparams['clientstatus'])) &&
        (!($clientparams['clientstatus'] & 1))) { // STATUS_OK* are even
        $retval = true;
        Util::setSessionVar('clientparams', json_encode($clientparams));
    }

    return $retval;
}
