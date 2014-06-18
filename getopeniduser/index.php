<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('../include/dbservice.php');
require_once('Auth/OpenID/Consumer.php');
require_once('Auth/OpenID/SReg.php');
require_once('Auth/OpenID/AX.php');
require_once('Google/Google_Client.php');
require_once('Google/contrib/Google_PlusService.php');


/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = $csrf->verifyCookieAndGetSubmit();
util::unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = util::getSessionVar('responseurl');

if (($submit == 'getuser') && (strlen($responseurl) > 0)) {
    // Look for "state" GET parameter, which implies Google OAuth 2.0 login
    if (strlen(util::getGetVar('state')) > 0) {
        getGoogleOAuth2AndRespond();
    } else {
        getUserAndRespond();
    }
} else {
    // If responseurl is empty, simply redirect to main site
    if (strlen($responseurl) == 0) {
        $responseurl = 'https://' . HOSTNAME;
    }
}

/* Finally, redirect to the calling script. */
header('Location: ' . $responseurl);


/************************************************************************
 * Function   : getGoogleOAuth2AndRespond                               *
 * This function specifically handles the new Google OAuth 2.0 login    *
 * API. The function reads developer and client keys from a local       *
 * configuration file, and then uses the Google OAuth 2.0 PHP API       *
 * (https://code.google.com/p/google-api-php-client/wiki/OAuth2) to     *
 * extract user parameters from the HTTP response.                      *
 ************************************************************************/
function getGoogleOAuth2AndRespond() {
    global $csrf;

    $openidid = '';
    $firstname = '';
    $lastname = '';
    $fullname = '';
    $emailaddr = '';
    $oidcid = '';

    util::unsetSessionVar('logonerror');
    
    $state = util::getGetVar('state');  // 'state' must match last CSRF value
    $code = util::getGetVar('code');    // 'code' must not be empty
    $lastcsrf = $csrf->getTheCookie();
    if ($state != $lastcsrf) {
        // Verify that response's "state" equals the last CSRF token
        util::setSessionVar('logonerror','Invalid state parameter.');
    } elseif (strlen($code) == 0) {
        // Make sure the response has a non-empty "code" 
        util::setSessionVar('logonerror','Empty code parameter.');
    } else {
        // Read the developer/client secret keys from local config file
        if ((is_array(util::$ini_array)) &&
            (array_key_exists('googleoauth2.applicationname',
                              util::$ini_array)) &&
            (array_key_exists('googleoauth2.clientid',util::$ini_array)) &&
            (array_key_exists('googleoauth2.clientsecret',util::$ini_array)) &&
            (array_key_exists('googleoauth2.developerkey',util::$ini_array))) {
            $appname      = util::$ini_array['googleoauth2.applicationname'];
            $clientid     = util::$ini_array['googleoauth2.clientid'];
            $clientsecret = util::$ini_array['googleoauth2.clientsecret'];
            $devkey       = util::$ini_array['googleoauth2.developerkey'];

            // Call the Google OAuth2 API to extract user data
            $client = new Google_Client();
            $client->setApplicationName($appname);
            $client->setClientID($clientid);
            $client->setClientSecret($clientsecret);
            $client->setRedirectUri(GETOPENIDUSER_URL);
            $client->setDeveloperKey($devkey);
            $plus = new Google_PlusService($client);
           
            try {
                // Make sure returned data is valid
                $client->authenticate();
            } catch (Exception $e) {
                util::setSessionVar('logonerror',$e->getMessage());
                $client = null;
            }

            if (!is_null($client)) {
                try {
                    // Try to get the token data for further processing
                    $token_data = $client->verifyIdToken()->getAttributes();
                } catch (Exception $e) {
                    util::setSessionVar('logonerror',$e->getMessage());
                    $token_data = null;
                }
                if (!is_null($token_data)) {
                    // Get the OpenID identifier and email
                    $openidid = @$token_data['payload']['openid_id'];
                    $emailaddr = @$token_data['payload']['email'];
                    $oidcid = @$token_data['payload']['sub'];
                }

                try {
                    // Try to get the "me" data for first/last name
                    $me = $plus->people->get('me');
                } catch (Exception $e) {
                    util::setSessionVar('logonerror',$e->getMessage());
                    $me = null;
                }
                if (!is_null($me)) {
                    list($firstname,$lastname) = 
                        getFirstAndLastName(@$me['displayName']);
                    // If no displayName, try givenName and familyName
                    if (strlen($firstname) == 0) {
                        $firstname = @$me['name']['givenName'];
                    }
                    if (strlen($lastname) == 0) {
                        $lastname = @$me['name']['familyName'];
                    }
                }
            }
        }
    }

    /* If no error reported, save user data to datastore */
    if (strlen(util::getSessionVar('logonerror')) == 0) {
        $providerId = util::getCookieVar('providerId');
        $providerName = openid::getProviderName($providerId);
        saveToDataStore($openidid,$providerId,$providerName,
                        $firstname,$lastname,$emailaddr,$oidcid);
    } else {
        util::unsetSessionVar('submit');
    }
}

/************************************************************************
 * Function   : getUserAndRespond                                       *
 * This function checks the URL for a $_GET variable 'openid.identity'  *
 * that gets set by an OpenID provider after successful authentication. *
 * It then makes a call to the database to get a userid and puts        *
 * several variables into the current PHP session.  It then responds    *
 * by redirecting to the resopnseurl in the passed-in parameter.  If    *
 * there are any issues with the database call, the userid is set to    *
 * the empty string and an error code is put in the PHP session.  Also, *
 * an email is sent out to let CILogon admins know of the problem.      *
 ************************************************************************/
function getUserAndRespond() {
    global $csrf;

    $openid = new openid();
    $openidid = '';
    $firstname = '';
    $lastname = '';
    $fullname = '';
    $emailaddr = '';

    util::unsetSessionVar('logonerror');
    $datastore = $openid->getStorage();
    if (is_null($datastore)) {
        util::setSessionVar('logonerror',
            'Internal logon error. Please contact <a href="mailto:help@cilogon.org">help@cilogon.org</a> or select a different identity provider.');
    } else {
        $consumer = new Auth_OpenID_Consumer($datastore);
        $response = $consumer->complete(util::getScriptDir(true));

        // Check the response status.
        if ($response->status == Auth_OpenID_CANCEL) {
            // This means the authentication was canceled.
            util::setSessionVar('logonerror',
                'Logon was canceled. Please try again.');
        } elseif ($response->status == Auth_OpenID_FAILURE) {
            // Authentication failed; display an error message.
            util::setSessionVar('logonerror',
                'Authentication failed: ' . 
                $response->message . '. Please try again.');
        } elseif ($response->status == Auth_OpenID_SUCCESS) {
            // This means the authentication succeeded; extract the identity.
            $openidid = $response->getDisplayIdentifier();

            // Get attributes from Verisign
            $sreg = null;
            $sreg_resp =
                Auth_OpenID_SRegResponse::fromSuccessResponse($response);
            if ($sreg_resp) {
                $sreg = $sreg_resp->contents();
            }

            // Get attributes from Google and Yahoo
            $ax = new Auth_OpenID_AX_FetchResponse();
            $data = @$ax->fromSuccessResponse($response)->data;

            // Look for email attribute
            if (@$sreg['email']) {
                $emailaddr = @$sreg['email'];
            } elseif (@$data['http://axschema.org/contact/email'][0]) {
                $emailaddr = @$data['http://axschema.org/contact/email'][0];
            }

            // Look for fullname attribute, or first+last
            if (@$sreg['fullname']) {
                $fullname = @$sreg['fullname'];
            } elseif (@$data['http://axschema.org/namePerson'][0]) {
                $fullname = @$data['http://axschema.org/namePerson'][0];
            } elseif ((@$data['http://axschema.org/namePerson/first'][0]) &&
                      (@$data['http://axschema.org/namePerson/last'][0])) {
                $fullname = @$data['http://axschema.org/namePerson/first'][0] .
                     ' ' .  @$data['http://axschema.org/namePerson/last'][0];
            }

            list($firstname,$lastname) = getFirstAndLastName($fullname);

        } else {
            util::setSessionVar('logonerror',
                'Logon error. Please try again.');
        }

        $openid->disconnect();
    }

    /* If no OpenID error reported, save user data to datastore */
    if (strlen(util::getSessionVar('logonerror')) == 0) {
        $providerId = util::getCookieVar('providerId');
        $providerName = openid::getProviderName($providerId);
        saveToDataStore($openidid,$providerId,$providerName,
                        $firstname,$lastname,$emailaddr);
    } else {
        util::unsetSessionVar('submit');
    }
}

/************************************************************************
 * Function   : saveToDataStore                                         *
 * Parameters : (1) The OpenID identifier for the user                  *
 *              (2) The endpoint URL for the OpenID provider            *
 *              (3) The pretty-print name of the OpenID provider        *
 *              (4) The first name of the user                          *
 *              (5) The last name of the user                           *
 *              (6) The email address of the user                       *
 *              (7) (Optional) The OIDC identifier from Google          *
 * This function saves the user logon information in the datastore.     *
 * It verifies that all parameters are not empty strings. It then       *
 * calls the "getUser" function of the dbservice to save the data.      *
 * It also sets PHP session variables upon success or failure. If       *
 * there was a problem, an email alert is sent out.                     *
 ************************************************************************/
function saveToDataStore($openidid,$providerId,$providerName,
                         $firstname,$lastname,$emailaddr,$oidcid='') {
    global $csrf;

    $dbs = new dbservice();
    $validator = new EmailAddressValidator();

    // Make sure all parameters are not empty strings, and email is valid
    if ((strlen($openidid) > 0) && 
        (strlen($providerId) > 0) &&
        (strlen($providerName) > 0)  &&
        (strlen($firstname) > 0) &&
        (strlen($lastname) > 0) &&
        (strlen($emailaddr) > 0) &&
        ($validator->check_email_address($emailaddr))) {

        // Keep original values of providerName and providerId
        $databaseProviderName = $providerName;
        $databaseProviderId   = $providerId;

        /* For the new Google OAuth 2.0 endpoint, we want to keep the   *
         * old Google OpenID endpoint URL in the database (so user does *
         * not get a new certificate subject DN). Change the providerId *
         * and providerName to the old Google OpenID values.            */
        if ($databaseProviderName == 'Google+') {
            $databaseProviderName = 'Google';
            $databaseProviderId = openid::getProviderURL('Google');
        }

        /* In the database, keep a consistent ProviderId format: only   *
         * allow "http" (not "https") and remove any "www." prefix      *
         * (for Google).                                                */
        $databaseProviderId = 
            preg_replace('%^https://(www\.)?%','http://',$databaseProviderId);

        $dbs->getUser($openidid,
                      $databaseProviderId,
                      $databaseProviderName,
                      $firstname,
                      $lastname,
                      $emailaddr,
                      $openidid,
                      '',
                      $oidcid);
        util::setSessionVar('uid',$dbs->user_uid);
        util::setSessionVar('dn',$dbs->distinguished_name);
        util::setSessionVar('twofactor',$dbs->two_factor);
        util::setSessionVar('status',$dbs->status);
    } else { // Missing one or more required attributes
        util::unsetSessionVar('uid');
        util::unsetSessionVar('dn');
        util::unsetSessionVar('twofactor');
        util::setSessionVar('status',
            dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
    }

    // If 'status' is not STATUS_OK*, then send an error email
    if (util::getSessionVar('status') & 1) { // Bad status codes are odd
        util::sendErrorAlert('Failure in /getopeniduser/',
            'OpenId ID     = ' . ((strlen($openidid) > 0) ? 
                $openidid : '<MISSING>') . "\n" .
            'OIDC ID       = ' . ((strlen($oidcid) > 0) ? 
                $oidcid : '<MISSING>') . "\n" .
            'Provider URL  = ' . ((strlen($providerId) > 0) ? 
                $providerId : '<MISSING>') . "\n" .
            'Provider Name = ' . ((strlen($providerName) > 0) ? 
                $providerName : '<MISSING>') . "\n" .
            'First Name    = ' . ((strlen($firstname) > 0) ? 
                $firstname : '<MISSING>') . "\n" .
            'Last Name     = ' . ((strlen($lastname) > 0) ? 
                $lastname : '<MISSING>') . "\n" .
            'Email Address = ' . ((strlen($emailaddr) > 0) ? 
                $emailaddr : '<MISSING>') . "\n" .
            'Database UID  = ' . ((strlen(
                $i=util::getSessionVar('uid')) > 0) ? 
                    $i : '<MISSING>') . "\n" .
            'Status Code   = ' . ((strlen($i = array_search(
                util::getSessionVar('status'),dbservice::$STATUS)) > 0) ? 
                    $i : '<MISSING>')
        );
        util::unsetSessionVar('firstname');
        util::unsetSessionVar('lastname');
        util::unsetSessionVar('loa');
        util::unsetSessionVar('idp');
        util::unsetSessionVar('openidID');
        util::unsetSessionVar('oidcID');
    } else {
        util::setSessionVar('firstname',$firstname);
        util::setSessionVar('lastname',$lastname);
        util::setSessionVar('loa','openid');
        util::setSessionVar('idp',$providerId);
        util::setSessionVar('openidID',$openidid);
        util::setSessionVar('oidcID',$oidcid);
    }

    util::setSessionVar('idpname',$providerName); // Enable check for Google
    util::setSessionVar('submit',util::getSessionVar('responsesubmit'));

    $csrf->setCookieAndSession();

    util::unsetSessionVar('responsesubmit');
    util::unsetSessionVar('ePPN');
    util::unsetSessionVar('ePTID');
}

/************************************************************************
 * Function   : getFirstAndLastName                                     *
 * Parameter  : The "full name" of a user.                              *
 * This function takes in a "full name" of a user and returns a two     *
 * element array consisting of the first and last names. The function   *
 * attemps to split the full name at the first space. If there is only  *
 * one name in the "full name", then the first and last name are set    *
 * to be the same. Note that if there are three names in the "full      *
 * name", the last name gets set to the second to names.
 ************************************************************************/
function getFirstAndLastName($fullname) {
    $firstname = '';
    $lastname = '';

    if (strlen($fullname) > 0) {
        $names = preg_split('/ /',$fullname,2);
        $firstname = @$names[0];
        $lastname =  @$names[1];
    }

    // If only a single name, copy first name <=> last name
    if (strlen($lastname) == 0) { 
        $lastname = $firstname;
    }
    if (strlen($firstname) == 0) {
        $firstname = $lastname;
    }

    return array($firstname,$lastname);
}

?>
