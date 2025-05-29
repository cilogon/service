<?php

/**
 * This file contains functions called by index.php. The index.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\DBService;
use CILogon\Service\Loggit;

/**
 * getUID
 *
 * This function takes all of the various required SAML attributes (as
 * set in the current Shibboleth session) and makes a call to the
 * database (via the dbservice) to get the userid assoicated with
 * those attributes.  It sets several PHP session variables such as the
 * status code returned by the dbservice, the uid (if found), etc. If
 * there is some kind of error with the database call, an email is
 * sent showing which SAML attributes were missing.
 *
 * All 'returned' variables are stored in various  PHP session variables
 * (e.g. 'user_uid', 'distinguished_name', 'status').
 */
function getUID()
{
    $shibarray = Util::getIdpList()->getShibInfo();

    // Don't allow Organization Name to be empty
    if (strlen(@$shibarray['Organization Name']) == 0) {
        $shibarray['Organization Name'] = 'Unspecified';
    }

    // Extract Silver Level of Assurance from Shib-AuthnContext-Class
    if (
        preg_match(
            '%http://id.incommon.org/assurance/silver%',
            Util::getServerVar('Shib-AuthnContext-Class')
        )
    ) {
        $shibarray['Level of Assurance'] =
            'http://incommonfederation.org/assurance/silver';
    }

    // Check for session var 'storeattributes' which indicates to
    // simply store the user attributes in the PHP session.
    // If not set, then by default save the user attributes to
    // the database (which also stores the user attributes in
    // the PHP session).
    $func = 'CILogon\Service\Util::saveUserToDataStore';
    if (!empty(Util::getSessionVar('storeattributes'))) {
        $func = 'CILogon\Service\Util::setUserAttributeSessionVars';
    }

    // CIL-793 - Calculate missing first/last name for OAuth1
    $first_name = @$shibarray['First Name'];
    $last_name = @$shibarray['Last Name'];
    $display_name = @$shibarray['Display Name'];
    $callbackuri = Util::getSessionVar('callbackuri'); // OAuth 1.0a
    if (
        (strlen($callbackuri) > 0) &&
        ((strlen($first_name) == 0) ||
         (strlen($last_name) == 0))
    ) {
        list($first, $last) = Util::getFirstAndLastName(
            $display_name,
            $first_name,
            $last_name
        );
        $first_name = $first;
        $last_name = $last;
    }

    $func(
        @$shibarray['User Identifier'],
        @$shibarray['Identity Provider'],
        @$shibarray['Organization Name'],
        $first_name,
        $last_name,
        $display_name,
        @$shibarray['Email Address'],
        @$shibarray['Level of Assurance'],
        @$shibarray['ePPN'],
        @$shibarray['ePTID'],
        '', // OpenID 2.0 ID
        '', // OpenID Connect ID
        @$shibarray['Subject ID'],
        @$shibarray['Pairwise ID'],
        @$shibarray['Affiliation'],
        @$shibarray['OU'],
        @$shibarray['Member'],
        @$shibarray['Authn Context'],
        '', // ORCID AMR
        '', // preferred_username (GitHub 'login')
        @$shibarray['Entitlement'],
        @$shibarray['iTrustUIN'],
        @$shibarray['eduPersonOrcid'],
        @$shibarray['uidNumber']
    );
}

/**
 * getUserAndRespond
 *
 * This function gets the user's database UID puts several variables
 * in the current PHP session, and responds by redirecting to the
 * responseurl in the passed-in parameter.  If there are any issues
 * with the database call, an email is sent to the CILogon admins.
 *
 * @param string $responseurl The full URL to redirect to after getting
 *        the userid.
 */
function getUserAndRespond($responseurl)
{
    getUID(); // Get the user's database user ID, put info in PHP session

    // Finally, redirect to the calling script.
    header('Location: ' . $responseurl);
    exit; // No further processing necessary
}

/**
 * outputError
 *
 * This function sets the HTTP return type to 'text/plain' and also
 * sets the HTTP return code to 400, meaning there was an error of
 * some kind.  If there is also a passed in errstr, that is output as
 * the body of the HTTP return.
 * @param string $errstr (Optional) The error string to print in the
 *        text/plain return body.
 */
function outputError($errstr = '')
{
    header('Content-type: text/plain', true, 400);
    if (strlen($errstr) > 0) {
        echo $errstr;
    }
}
