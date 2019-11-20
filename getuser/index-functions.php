<?php

/**
 * This file contains functions called by index.php. The index.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\OAuth2Provider;
use League\OAuth2\Client\Token\AccessToken;

/**
 * getUserAndRespond
 *
 * This function specifically handles the redirect from OAuth2 providers.
 * The function reads client keys/secrets from a local configuration file,
 * and then uses the PHP League OAuth2 client library to extract user
 * parameters from the HTTP response.
 */
function getUserAndRespond()
{
    $firstname = '';
    $lastname = '';
    $displayname = '';
    $fullname = '';
    $emailaddr = '';
    $openidid = '';
    $oidcid = '';

    Util::unsetSessionVar('logonerror');

    $state = Util::getGetVar('state');  // 'state' must match last CSRF value
    $code = Util::getGetVar('code');    // 'code' must not be empty
    $lastcsrf = Util::getCsrf()->getTheCookie();
    if ($state != $lastcsrf) {
        // Verify that response's 'state' equals the last CSRF token
        Util::setSessionVar('logonerror', 'Invalid state parameter.');
    } elseif (empty($code)) {
        // Make sure the response has a non-empty 'code'
        $error = Util::getGetVar('error');
        $error_description = Util::getGetVar('error_description');
        if ((!empty($error)) && (!empty($error_description))) {
            Util::setSessionVar('logonerror', $error_description . '. Please try again.');
        } else {
            Util::setSessionVar('logonerror', 'Empty code parameter. Please try again.');
        }
    } else {
        // When using OAuth or OIDC, check portalcookie for providerId
        $providerId = Util::getPortalOrNormalCookieVar('providerId');
        $providerName = Util::getAuthzIdP($providerId);
        $prov = strtolower($providerName); // IdP name all lowercase

        // Get the client id/secret for the OAuth2 IdP
        $clientid     = constant(strtoupper($prov) . '_OAUTH2_CLIENT_ID');
        $clientsecret = constant(strtoupper($prov) . '_OAUTH2_CLIENT_SECRET');
        if ((!empty($clientid)) && (!empty($clientsecret))) {
            $oauth2 = new OAuth2Provider($providerName);
            try {
                $token = $oauth2->provider->getAccessToken(
                    'authorization_code',
                    [ 'code' => $code ]
                );
                $user = $oauth2->provider->getResourceOwner($token);
                $oidcid = $user->getId();
                $emailaddr = $user->getEmail();
                // GitHub email may require special handling
                if ((empty($emailaddr)) && ($prov == 'github')) {
                    $emailaddr = getGitHubEmail($oauth2, $token);
                }
                $name = $user->getName();
                $first = '';
                $last = '';
                if ($prov != 'github') { // No first/last for GitHub
                    $first = $user->getFirstName();
                    $last = $user->getLastName();
                }
                list($firstname, $lastname) =
                    Util::getFirstAndLastName($name, $first, $last);
            } catch (Exception $e) {
                Util::setSessionVar('logonerror', $e->getMessage());
            }
        } else {
            Util::setSessionVar(
                'logonerror',
                'Missing OAuth2 client configuration values.'
            );
        }
    }

    // If no error reported, check for session var 'storeattributes'
    // which indicates to simply store the user attributes in the
    // PHP session. If not set, then by default save the user
    // attributes to the database (which also stores the user
    // attributes in the PHP session).
    if (empty(Util::getSessionVar('logonerror'))) {
        $func = 'CILogon\Service\Util::saveUserToDataStore';
        if (!empty(Util::getSessionVar('storeattributes'))) {
            $func = 'CILogon\Service\Util::setUserAttributeSessionVars';
            Util::unsetSessionVar('storeattributes');
        }
        $func(
            $openidid,
            $providerId,
            $providerName,
            $firstname,
            $lastname,
            $displayname,
            $emailaddr,
            'openid',
            '', // ePPN
            '', // ePTID
            $openidid,
            $oidcid
        );
    } else {
        Util::unsetSessionVar('submit');
    }
}

/**
 * getGitHubEmail
 *
 * This function gets a GitHub user email address from the special
 * user email API endpoint. It returns an email address that is marked
 * as 'primary' by GitHub.
 *
 * @param OAuth2Provider An exsiting OAuth2Provider object
 * @param League\OAuth2\Client\Token\AccessToken An oauth2 token
 * @return string A GitHub user's primary email address, or empty string
 *         if no such email address exists.
 */
function getGitHubEmail($oauth2, $token)
{
    $oauth2_email = '';

    $request = $oauth2->provider->getAuthenticatedRequest(
        'GET',
        'https://api.github.com/user/emails',
        $token
    );
    $github_emails = json_decode(
        $oauth2->provider->getResponse($request)->getBody()
    );

    foreach ($github_emails as $email) {
        if ($email->primary == 1) {
            $oauth2_email = $email->email;
            break;
        }
    }

    return $oauth2_email;
}
