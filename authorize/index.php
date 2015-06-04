<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');

/* The full URL of the 'oauth2/init' OAuth 2.0 (OIDC) script */
define('AUTHORIZE_URL','http://localhost:8080/oauth2/init');

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  *
 * Note: replace CR/LF with space for "Show/Hide Help" buttons.      */
$retchars = array("\r\n","\n","\r");
$submit = str_replace($retchars," ",$csrf->verifyCookieAndGetSubmit());
util::unsetSessionVar('submit');

$log->info('submit="' . $submit . '"');

/* First, check to see if the info related to the OIDC client exists   *
 * in the current PHP session. If so, continue processing based on the *
 * 'submit' value. Otherwise, print out error message about bad or     *
 * missing OpenID Connect parameters.                                  */
if (verifyOIDCParams()) {
    // Get the OIDC client parameters from the PHP session.
    $clientparams = json_decode(util::getSessionVar('clientparams'),true);

    /* Depending on the value of the clicked "submit" button or the    *
     * equivalent PHP session variable, take action or print out HTML. */
    switch ($submit) {

        case 'Log On': // Check for OpenID or InCommon usage.
        case 'Continue': // For OOI
            // Need to check for 'max_age' OIDC parameeter. If elapsed time
            // since last user authentication is greater than max_age, then
            // set 'forceauthn' session variable to force the user to 
            // (re)authenticate.
            if (isset($clientparams['max_age'])) {
                $max_age = (int)$clientparams['max_age'];
                if (strlen(util::getSessionVar('authntime')) > 0) {
                    $authntime = (int)util::getSessionVar('authntime');
                    $currtime = time();
                    if (($authtime > $currtime) || // Weird error!!!
                        (($currtime - $authtime) > $max_age)) {
                        util::setSessionVar('forceauthn','1');
                        }
                } else { // No authntime - assume no user authentication
                    util::setSessionVar('forceauthn','1');
                }
            }
            handleLogOnButtonClicked();
        break; // End case 'Log On'

        case 'gotuser': // Return from the getuser script
            handleGotUser();
        break; // End case 'gotuser'

        case 'Proceed': // Proceed after 'User Changed' or Error page
        case 'Done with Two-Factor':
            verifySessionAndCall('printMainPage');
        break; // End case 'Proceed'

        case 'Cancel': // User denies release of attributes
            // If user clicked the 'Cancel' button, return to the
            // OIDC client with an error message.
            $redirect = 'Location: ' . $clientparams['redirect_uri'] .
                '?error=access_denied&error_description=' . 
                'User%20denied%20authorization%20request' .
                ((isset($clientparams['state'])) ? 
                    '&state='.$clientparams['state'] : '');
            util::unsetSessionVar('clientparams');
            util::unsetSessionVar('cilogon_skin');
            unsetGetUserSessionVars();
            header($redirect);
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

        case 'EnterDuo': // Verify Duo Security login
            verifySessionAndCall('handleDuoSecurityLogin');
        break; // End case 'EnterDuo'

        case 'Show  Help ': // Toggle showing of help text on and off
        case 'Hide  Help ':
            handleHelpButtonClicked();
        break; // End case 'Show Help' / 'Hide Help'

        default: // No submit button clicked nor PHP session submit variable set
            handleNoSubmitButtonClicked();
        break; // End default case

    } // End switch ($submit)
} else { // Failed to verify OIDC client parameters in PHP session
    printOIDCErrorPage();
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

    $clientparams = json_decode(util::getSessionVar('clientparams'),true);

    $log->info('Welcome page hit.');

    util::setSessionVar('stage','logon'); // For Show/Hide Help button clicks

    printHeader('Welcome To The CILogon OpenID Connect Authorization Service');

    echo '
    <div class="boxed">
    ';

    printHelpButton();

    echo '
      <br />
    ';

    // If the <hideportalinfo> option is set, do not show the portal info if
    // the OIDC redirect_uri is in the portal list.
    $showportalinfo = true;
    if (((int)$skin->getConfigOption('portallistaction','hideportalinfo')==1) &&
        ($skin->inPortalList($clientparams['redirect_uri']))) {
        $showportalinfo = false; 
    }

    if ($showportalinfo) {
        // Look in the 'scope' OIDC parameter to see which attributes are
        // being requested. The values we care about are "email", "profile"
        // (for first/last name), and "edu.uiuc.ncsa.myproxy.getcert"
        // (which gives a certificate containing first/last name AND email).
        $attrs = array();
        $scope = $clientparams['scope'];
        if (preg_match('/email/',$scope)){
            $attrs['email'] = true;
        }
        if (preg_match('/profile/',$scope)) {
            $attrs['name'] = true;
        }
        if (preg_match('/edu.uiuc.ncsa.myproxy.getcert/',$scope)) {
            $attrs['email'] = true;
            $attrs['name'] = true;
            $attrs['cert'] = true;
        }
        $attrscount = count($attrs);

        echo '
          <br/>
          <p>"' , 
          htmlspecialchars($clientparams['client_name']) , 
          '" requests that you select an Identity Provider and click "' ,
          getLogOnButtonText() ,
          '". If you do not approve this request, do not proceed.
          </p>
          ';

        if ($attrscount > 0) {
            echo '<p><em>By proceeding you agree to share your ';
            if ($attrs['name']) {
                echo 'name';
                if ($attrscount == 2) {
                    echo ' and ';
                } elseif ($attrscount == 3) {
                    echo ', ';
                }
                $attrscount--;
            }
            if ($attrs['email']) {
                echo 'email address';
                if ($attrscount == 2) {
                    echo ' and ';
                }
            }
            if ($attrs['cert']) {
                echo 'X.509 certificate';
            }
            echo ' with "' , 
              htmlspecialchars($clientparams['client_name']) ,
              '"</em>.</p>
            ';
        }

        printPortalInfo('1');
    }

    printWAYF();

    echo '
    </div> <!-- End boxed -->
    ';

    printFooter();
}

/************************************************************************
 * Function   : printOIDCErrorPage                                      *
 * This function prints out the HTML for the page when the the various  *
 * OIDC parameters sent by the client are missing or bad.               *
 ************************************************************************/
function printOIDCErrorPage() {
    global $log;

    $log->warn('Missing or invalid OIDC parameters.');

    printHeader('CILogon Authorization Endpoint');

    echo '
    <div class="boxed">
      <br class="clear"/>
      <p>
      You have reached the CILogon OpenID Connect Authorization Endpoint.
      This service is for use by OpenID Connect Relying Parties (RPs) to
      authorize users of the CILogon Sevice. End users should not normally
      see this page.
      </p>
    ';

    $client_error_msg = util::getSessionVar('client_error_msg');
    util::unsetSessionVar('client_error_msg');
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
      Please return to the previous site and try again. If the error persists,
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
 * This function is poorly named for the OIDC case, but is called by    *
 * gotUserSucces, so the name stays. This function is called once the   *
 * user has successfully logged on at the selected IdP. In the OIDC     *
 * case, the user's UID is then paired with the OIDC "code" and         *
 * "authntime" in the datastore so that it can be fetched later when    *
 * the OIDC client wants to get userinfo or a certificate. There        *
 * really isn't anything "printed" to the user here. Control is         *
 * simply redirected to the OIDC client with appropriate success or     *
 * error response.                                                      *
 ************************************************************************/
function printMainPage() {
    global $log;
    global $skin;

    $clientparams = json_decode(util::getSessionVar('clientparams'),true);
    $redirect = '';

    $log->info('Calling setTransactionState dbService method...');

    $dbs = new dbservice();
    if (($dbs->setTransactionState($clientparams['code'],
        util::getSessionVar('uid'),util::getSessionVar('authntime'))) &&
        (!($dbs->status & 1))) { // STATUS_OK codes are even
        $redirect = 'Location: ' . $clientparams['redirect_url'];
        $log->info('setTransactionState succeeded, redirect to ' . $redirect);
    } else { // dbservice error
        $errstr = '';
        if (!is_null($dbs->status)) {
            $errstr = array_search($dbs->status,dbservice::$STATUS);
        }
        $redirect = 'Location: ' . $clientparams['redirect_uri'] . '?' .
            'error=server_error&error_description=' . 
            'Unable%20to%20associate%20user%20UID%20with%20OIDC%20code' .
            ((isset($clientparams['state'])) ? 
                '&state='.$clientparams['state'] : '');
        $log->info("setTransactionState failed $errstr, redirect to $redirect");
        util::sendErrorAlert('dbService Error',
            'Error calling dbservice action "setTransactionState" in ' .
            'OIDC authorization endpoint\'s printMainPage() method. ' .
            $errstr . ' Redirected to ' . $redirect);
    }

    util::unsetSessionVar('clientparams');
    util::unsetSessionVar('cilogon_skin');
    unsetGetUserSessionVars();
    header($redirect);
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
    $clientparams = json_decode(util::getSessionVar('clientparams'),true);
    $showhelp = util::getSessionVar('showhelp');
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

/************************************************************************
 * Function   : verifyOIDCParams                                        *
 * Returns    : True if the various parameters related to the OIDC      *
 *              session are present. False otherwise.                   *
 * This function verifies that all of the various OIDC parameters are   *
 * set in the PHP session. First, the function checks if an OIDC        *
 * client has passed appropriate parameters to the authorization        *
 * endpoint. If so, we call the "real" OA4MP OIDC authorization         *
 * endpoint and let it verify the client parameters. Upon successful    *
 * return, we call the getClient() function of the dbService to get     *
 * the OIDC client name and homepage for display to the user. All       *
 * client parameters (including the ones passed in) are saved to the    *
 * 'clientparams' PHP session variable, which is encoded as a JSON      *
 * token to preserve arrays. If there are any errors, false is returned *
 * and an email is sent. In some cases the session variable             *
 * 'client_error_msg' is set so it can be displayed by the              *
 * printOIDCErrorPage() functin.                                        *
 ************************************************************************/
function verifyOIDCParams() {
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

    // If the 'redirect_uri' parameter was passed in then let the "real"
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
                curl_setopt($ch,CURLOPT_POST,true);
                curl_setopt($ch,CUROPT_POSTFIELDS,http_build_query($_POST));
            }
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_TIMEOUT,30);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false); // Catch redirects
            $output = curl_exec($ch);
            if (curl_errno($ch)) { // Send alert on curl errors
                util::sendErrorAlert('cUrl Error',
                    'cUrl Error    = ' . curl_error($ch) . "\n" . 
                    "URL Accessed  = $url" .
                    "\n\n" .
                    'clientparams = ' . print_r($clientparams,true));
                $clientparams = array();
            } else {
                $info = curl_getinfo($ch);
                if ($info !== false) {
                    if ((isset($info['http_code'])) &&
                        ($info['http_code'] == 302)) {
                        // The OA4MP OIDC server responded with a redirect to
                        // the OIDC client. We need to check if the response
                        // contains a "code" or an "error". If "code" then save
                        // to session and authenticate the user. If "error",
                        // then simply redirect error to OIDC client.
                        $redirect_url = '';
                        if (isset($info['redirect_url'])) {
                            $redirect_url = $info['redirect_url'];
                            $clientparmas['redirect_url'] = $redirect_url;
                        }
                        // Get components of redirect_url - need "query"
                        $comps = parse_url($redirect_url);
                        if ($comps !== false) {
                            // Look for "code" or "error" in query
                            $query = '';
                            if (isset($comps['query'])) {
                                $query = $comps['query'];
                                $query = html_entity_decode($query);
                            }
                            $queries = explode('&',$query);
                            $params = array();
                            foreach ($queries as $value) {
                                $x = explode('=',$value);
                                $params[$x[0]] = $x[1];
                            }
                            if (isset($params['error'])) {
                                // Got "error" - simply return to OIDC client
                                $clientparams = array();
                                util::unsetSessionVar('clientparams');
                                util::unsetSessionVar('cilogon_skin');
                                unsetGetUserSessionVars();
                                header("Location: $redirect_url");
                                exit; // No further processing necessary
                            } elseif (isset($params['code'])) {
                                // Got "code" - save to session and call 
                                // dbService "getClient" to get info about
                                // OIDC client to display to user
                                $clientparams['code'] = $params['code'];
                                $dbs = new dbservice();
                                if (($dbs->getClient(
                                    $clientparams['client_id'])) &&
                                    (!($dbs->status &1))){ // STATUS_OK is even
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
                                        $errstr = '';
                                        if (!is_null($dbs->status)) {
                                            $errstr = array_search(
                                                $dbs->status,
                                                dbservice::$STATUS);
                                        }
                                        util::sendErrorAlert('dbService Error',
                                            'Error calling dbservice ' . 
                                            ' action "getClient" in ' . 
                                            'verifyOIDCParams() method. ' . 
                                            $errstr);
                                        $clientparams = array();
                                    }
                                }
                            } else { // Weird params - Should never get here!
                                util::sendErrorAlert('OA4MP OIDC 302 Error',
                                    'The OA4MP OIDC authorization endpoint '.
                                    'returned a 302 redirect, but there ' .
                                    'was no "code" or "error" query ' .
                                    "parameter.\n\n" .
                                    "redirect_url = $redirect_url\n\n" .
                                    'clientparams = ' .
                                    print_r($clientparams,true) . 
                                    "\n");
                                $clientparams = array();
                            }
                        } else { // parse_url($redirect_url) gave error
                            util::sendErrorAlert('parse_url(redirect_url) error',
                                'There was an error when attempting to ' .
                                'parse the redirect_url. This should never ' .
                                "happen.\n\n" .
                                "redirect_url = $redirect_url\n\n" .
                                'clientparams = '.print_r($clientparams,true) . 
                                "\n");
                            $clientparams = array();
                        }
                    } else {
                        // An HTTP return code other than 302 (redirect) means
                        // that the OA4MP OIDC server tried to handle an
                        // unrecoverable error, possibly by outputting HTML.
                        // If so, then we ignore it and output our own error
                        // message to the user.
                        util::sendErrorAlert('OA4MP OIDC authz endpoint error',
                            'The OA4MP OIDC authorization endpoint returned ' . 
                            'an HTTP response other than 302. ' .
                            ((strlen($output) > 0) ? 
                                "\n\nReturned output =\n$output" : '') .
                            "\n\n" .
                            'curl_getinfo = ' . print_r($info,true) . "\n\n" .
                            'clientparams = ' . print_r($clientparams,true) . 
                            "\n");
                        util::setSessionVar('client_error_msg',
                            'There was an unrecoverable error during the ' .
                            'OpenID Connect transaction. This may be a ' .
                            'temporary issue. CILogon system ' .
                            'administrators have been notified.');
                        $clientparams = array();
                    }
                } else { // curl_getinfo() returned false - should not happen
                    util::sendErrorAlert('curl_getinfo error',
                        'When attempting to talk to the OA4MP OIDC ' .
                        'authorization endpoint, curl_getinfo() returned ' . 
                        "false. This should never happen.\n\n" .
                        'clientparams = ' . print_r($clientparams,true) . "\n");
                    $clientparams = array();
                }
            }
            curl_close($ch);
        } else { // curl_init() returned false - should not happen
            util::sendErrorAlert('curl_init error',
                'When attempting to talk to the OA4MP OIDC authorization '.
                'endpoint, curl_init() returned false. This should never ' .
                "happen.\n\n" .
                'clientparams = ' . print_r($clientparams,true) . "\n");
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

        util::sendErrorAlert('CILogon OIDC authz endpoint error',
            'The CILogon OIDC authorization endpoint received a request ' .
            'from an OIDC client, but at least one of the required ' . 
            'parameters (redirect_uri) was missing. ' .
            "\n\n" .
            'clientparams = ' . print_r($clientparams,true) . 
            "\n");
        util::setSessionVar('client_error_msg',
            'It appears that an OpenID Connect client attempted to ' .
            'initiate a session with the CILogon Service, but at least ' .
            'one of the requried parameters was missing. CILogon ' .
            'system administrators have been notified.');
        $clientparams = array();

    // If none of the required OIDC authz endpoint parameters were passed
    // in, then this might be a later step in the authz process. So check
    // the session variable array 'clientparams' for the required
    // information.
    } else {
        $clientparams = json_decode(util::getSessionVar('clientparams'),true);
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
        util::setSessionVar('clientparams',json_encode($clientparams));
    }

    return $retval;
}

?>
