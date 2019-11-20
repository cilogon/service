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
use CILogon\Service\MyProxy;
use CILogon\Service\Loggit;

/**
 * getUID
 *
 * This function takes all of the various required SAML attributes (as
 * set in the current Shibboleth session) and makes a call to the
 * database (via the dbservice) to get the userid assoicated with
 * those attributes.  It sets several PHP session variables such as the
 * status code returned by the dbservice, the uid (if found), the
 * username to be passed to MyProxy ('dn'), etc.  If there is some kind
 * of error with the database call, an email is sent showing which
 * SAML attributes were missing.
 *
 * All 'returned' variables are stored in various  PHP session variables
 * (e.g. 'uid', 'dn', 'status').
 */
function getUID()
{
    $shibarray = Util::getIdpList()->getShibInfo();

    $firstname = @$shibarray['First Name'];
    $lastname = @$shibarray['Last Name'];
    if ((strlen($firstname) == 0) || (strlen($lastname) == 0)) {
        list($firstname, $lastname) = Util::getFirstAndLastName(
            @$shibarray['Display Name'],
            $firstname,
            $lastname
        );
    }

    // Hack for test IdP at boingo.ncsa.uiuc.edu
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
        Util::unsetSessionVar('storeattributes');
    }
    $func(
        @$shibarray['User Identifier'],
        @$shibarray['Identity Provider'],
        @$shibarray['Organization Name'],
        $firstname,
        $lastname,
        @$shibarray['Display Name'],
        @$shibarray['Email Address'],
        @$shibarray['Level of Assurance'],
        @$shibarray['ePPN'],
        @$shibarray['ePTID'],
        '', // OpenID 2.0 ID
        '', // OpenID Connect ID
        @$shibarray['Affiliation'],
        @$shibarray['OU'],
        @$shibarray['Member'],
        @$shibarray['Authn Context'],
        @$shibarray['Entitlement'],
        @$shibarray['iTrustUIN']
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
 * getPKCS12
 *
 * This function is called when an ECP client wants to get a PKCS12
 * credential.  It first attempts to get the user's database UID. If
 * successful, it tries to create a PKCS12 file on disk by calling
 * MyProxy.  If successful, it returns the PKCS12 file by setting the
 * HTTP Content-type.  If there is an error, it returns a plain text
 * file and sets the HTTP response code to an error code.
 */
function getPKCS12()
{
    $log = new Loggit();

    getUID(); // Get the user's database user ID, put info in PHP session

    $skin = Util::getSkin();
    $skin->init(); // Check for forced skin

    // If 'status' is not STATUS_OK*, then return error message
    if (Util::getSessionVar('status') & 1) { // Bad status codes are odd
        $errstr = array_search(Util::getSessionVar('status'), DBService::$STATUS);
        $log->info('ECP PKCS12 error: ' . $errstr . '.');
        outputError($errstr);
        Util::unsetAllUserSessionVars();
        return; // ERROR means no further processing is necessary
    }

    $shibarray = Util::getIdpList()->getShibInfo();
    if (Util::isEduGAINAndGetCert(@$shibarray['Identity Provider'], @$shibarray['Organization Name'])) {
        $log->info('ECP PKCS12 error: Failed to get cert due to eduGAIN IdP restriction.');
        outputError('Failed to get cert due to eduGAIN IdP restriction.');
        return; // ERROR means no further processing is necessary
    }

    $skin->setMyProxyInfo();
    Content::generateP12();  // Try to create the PKCS12 credential file on disk

    // Look for the p12error PHP session variable. If set, return it.
    $p12error = Util::getSessionVar('p12error');
    if (strlen($p12error) > 0) {
        $log->info('ECP PKCS12 error: ' . $p12error);
        outputError($p12error);
    } else { // Try to read the .p12 file from disk and return it
        $p12 = Util::getSessionVar('p12');
        $p12expire = '';
        $p12link = '';
        $p12file = '';
        if (preg_match('/([^\s]*)\s(.*)/', $p12, $match)) {
            $p12expire = $match[1];
            $p12link = $match[2];
        }
        if ((strlen($p12link) > 0) && (strlen($p12expire) > 0)) {
            $p12file = file_get_contents($p12link);
        }

        if (strlen($p12file) > 0) {
            $log->info('ECP PKCS12 success!');
            // CIL-507 Special log message for XSEDE
            $log->info('USAGE email="' . Util::getSessionVar('emailaddr') .
                       '" client="ECP"');
            header('Content-type: application/x-pkcs12');
            echo $p12file;
        } else {
            $log->info('ECP PKCS12 error: Missing or empty PKCS12 file.');
            outputError('Missing or empty PKCS12 file.');
        }
    }
}

/**
 * getCert
 *
 * This function is called when an ECP client wants to get a PEM-
 * formatted X.509 certificate by inputting a certificate request
 * generated by 'openssl req'.  It first attempts to get the user's
 * database UID. If successful, it calls out to myproxy-logon to get
 * a certificate. If successful, it returns the certificate by setting
 * the HTTP Content-type to 'text/plain'.  If there is an error, it
 * returns a plain text file and sets the HTTP response code to an
 * error code.
 */
function getCert()
{
    $log = new Loggit();

    // Verify that a non-empty certreq <form> variable was posted
    $certreq = Util::getPostVar('certreq');
    if (strlen($certreq) == 0) {
        $log->info('ECP certreq error: Missing certificate request.');
        outputError('Missing certificate request.');
        return; // ERROR means no further processing is necessary
    }

    getUID(); // Get the user's database user ID, put info in PHP session

    $skin = Util::getSkin();
    $skin->init(); // Check for forced skin

    // If 'status' is not STATUS_OK*, then return error message
    if (Util::getSessionVar('status') & 1) { // Bad status codes are odd
        $errstr = array_search(Util::getSessionVar('status'), DBService::$STATUS);
        $log->info('ECP certreq error: ' . $errstr . '.');
        outputError($errstr);
        Util::unsetAllUserSessionVars();
        return; // ERROR means no further processing is necessary
    }

    $shibarray = Util::getIdpList()->getShibInfo();
    if (Util::isEduGAINAndGetCert(@$shibarray['Identity Provider'], @$shibarray['Organization Name'])) {
        $log->info('ECP certreq error: Failed to get cert due to eduGAIN IdP restriction.');
        outputError('Failed to get cert due to eduGAIN IdP restriction.');
        return; // ERROR means no further processing is necessary
    }

    // Set the port based on the Level of Assurance
    $port = 7512;
    $loa = Util::getSessionVar('loa');
    if ($loa == 'http://incommonfederation.org/assurance/silver') {
        $port = 7514;
    } elseif ($loa == 'openid') {
        $port = 7516;
    }

    // Get the certificate lifetime. Set to a default value if not set.
    $certlifetime = (int)(Util::getPostVar('certlifetime'));
    if ($certlifetime == 0) {  // If not specified, set to default value
        $defaultlifetime = $skin->getConfigOption('ecp', 'defaultlifetime');
        if ((!is_null($defaultlifetime)) && ((int)$defaultlifetime > 0)) {
            $certlifetime = (int)$defaultlifetime;
        } else {
            $certlifetime = MyProxy::getDefaultLifetime();
        }
    }

    // Make sure lifetime is within acceptable range. 277 hrs = 1000000 secs.
    list($minlifetime, $maxlifetime) = Content::getMinMaxLifetimes('ecp', 277);
    if ($certlifetime < $minlifetime) {
        $certlifetime = $minlifetime;
    } elseif ($certlifetime > $maxlifetime) {
        $certlifetime = $maxlifetime;
    }

    // Make sure that the user's MyProxy username is available.
    $dn = Util::getSessionVar('dn');
    if (strlen($dn) > 0) {
        // Append extra info, such as 'skin', to be processed by MyProxy.
        $skin->setMyProxyInfo();
        $myproxyinfo = Util::getSessionVar('myproxyinfo');
        if (strlen($myproxyinfo) > 0) {
            $dn .= " $myproxyinfo";
        }
        // Attempt to fetch a credential from the MyProxy server
        $cert = MyProxy::getMyProxyCredential(
            $dn,
            '',
            MYPROXY_HOST,
            $port,
            $certlifetime,
            '/var/www/config/hostcred.pem',
            '',
            $certreq
        );

        if (strlen($cert) > 0) { // Successfully got a certificate!
            $log->info('ECP getcert success!');
            // CIL-507 Special log message for XSEDE
            $log->info('USAGE email="' . Util::getSessionVar('emailaddr') .
                       '" client="ECP"');
            header('Content-type: text/plain');
            echo $cert;
        } else { // The myproxy-logon command failed - shouldn't happen!
            $log->info('ECP certreq error: MyProxy unable to create certificate.');
            outputError('Error! MyProxy unable to create certificate.');
        }
    } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
        $log->info('ECP certreq error: Missing \'dn\' session value.');
        outputError('Missing username. Please enable cookies.');
    }
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
