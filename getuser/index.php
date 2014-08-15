<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('../include/dbservice.php');
require_once('Google/Client.php');
require_once('Google/Service/Plus.php');

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = $csrf->verifyCookieAndGetSubmit();
util::unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = util::getSessionVar('responseurl');


if (($submit == 'getuser') && 
    (strlen($responseurl) > 0) &&
    (strlen(util::getGetVar('state')) > 0)) {
        getUserAndRespond2();
} else {
    // If responseurl is empty, simply redirect to main site
    if (strlen($responseurl) == 0) {
        $responseurl = 'https://' . HOSTNAME;
    }
}

/* Finally, redirect to the calling script. */
header('Location: ' . $responseurl);


/************************************************************************
 * Function   : getUserAndRespond                                       *
 * This function specifically handles the new Google OIDC login API.    *
 * The function reads developer and client keys from a local            *
 * configuration file, and then uses the Google OIDC PHP API            *
 * (https://github.com/google/google-api-php-client)                    *
 * to extract user parameters from the HTTP response.                   *
 ************************************************************************/
function getUserAndRespond2() {
    global $csrf;

    $firstname = '';
    $lastname = '';
    $fullname = '';
    $emailaddr = '';
    $openidid = '';
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
            $client->setRedirectUri(GETOIDCUSER_URL);
            $client->setDeveloperKey($devkey);
            $plus = new Google_Service_Plus($client);
           
            try {
                // Make sure returned data is valid
                $client->authenticate($code);
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
                    list($firstname,$lastname) = util::getFirstAndLastName(
                        @$me['displayName'],
                        @$me['name']['givenName'],
                        @$me['name']['familyName']);
                }
            }
        }
    }


    /* If no error reported, save user data to datastore */
    if (strlen(util::getSessionVar('logonerror')) == 0) {
        $providerId = util::getCookieVar('providerId');
        $providerName = 'Google';
        util::saveUserToDataStore($openidid,$providerId,$providerName,
                                  $firstname,$lastname,$emailaddr,'openid',
                                  '','',$openidid,$oidcid);
    } else {
        util::unsetSessionVar('submit');
    }
}

?>
