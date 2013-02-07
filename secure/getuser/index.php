<?php

require_once('../../include/util.php');
require_once('../../include/autoloader.php');
require_once('../../include/content.php');
require_once('../../include/myproxy.php');

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();
util::unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = util::getSessionVar('responseurl');

if (($submit == 'getuser') && (strlen($responseurl) > 0)) {
    getUserAndRespond($responseurl);
} elseif ($submit == 'pkcs12') {
    getPKCS12();
} elseif ($submit == 'certreq') {
    getCert();
} else {
    // If the REQUEST_URI was "/secure/getcert" then it was ECP.
    // Respond with an error message rather than a redirect.
    if (preg_match('%/secure/getcert%',util::getServerVar('REQUEST_URI'))) {
        $log->info('"/secure/getcert" error: Either CSRF check failed, ' . 
                   'or invalid "submit" command issued.');
        outputError('Unable to complete ECP transaction. Either CSRF ' . 
                    'check failed, or invalid "submit" command issued.');
    } else { // Redirect to $responseurl or main homepage
        $location = 'https://' . HOSTNAME;
        if (strlen($responseurl) > 0) {
            $location = $responseurl;
        }
        header('Location: ' . $location);
    }
}

/************************************************************************
 * Function   : getUID                                                  *
 * Returns    : Nothing. All 'returned' variables are stored in various *
 *              PHP session variables (e.g. 'uid', 'dn', 'status').     *
 * This function takes all of the various required SAML attributes (as  *
 * set in the current Shibboleth session) and makes a call to the       *
 * database (via the dbservice) to get the userid assoicated with       *
 * those attributes.  It sets several PHP session variables such as the *
 * status code returned by the dbservice, the uid (if found), the       *
 * username to be passed to MyProxy ('dn'), etc.  If there is some kind *
 * of error with the database call, an email is sent showing which      *
 * SAML attributes were missing.                                        *
 ************************************************************************/
function getUID() {
    $dbs = new dbservice();
    $idplist = new idplist();
    $shibarray = $idplist->getShibInfo();

    /* If either firstname or lastname is empty but displayName *
     * is okay, extract first/last name from the displayName.   */
    $firstname   = $shibarray['First Name'];
    $lastname    = $shibarray['Last Name'];
    $displayname = $shibarray['Display Name'];
    if (strlen($displayname) > 0) {
        if ((strlen($firstname) == 0) && (strlen($lastname) == 0)) {
            if (preg_match('/^([^\s]+)\s*(.*)$/',$displayname,$matches)) {
                $firstname = $matches[1];
                $lastname = $matches[2];
                // If only a single name, duplicate first and last name
                if (strlen($lastname) == 0) { 
                    $lastname = $firstname;
                }
            }
        } elseif (strlen($firstname) == 0) {
            if (preg_match('/^([^\s]+)\s+/',$displayname,$matches)) {
                $firstname = $matches[1];
            }
        } elseif (strlen($lastname) == 0) {
            if (preg_match('/\s+([^\s]+)$/',$displayname,$matches)) {
                $lastname = $matches[1];
            }
        }
    }

    // If only a single name, copy first name <=> last name
    if (strlen($lastname) == 0) { 
        $lastname = $firstname;
    }
    if (strlen($firstname) == 0) {
        $firstname = $lastname;
    }

    $validator = new EmailAddressValidator();

    /* Hack for test IdP at boingo.ncsa.uiuc.edu */
    if (strlen($shibarray['Organization Name']) == 0) {
        $shibarray['Organization Name'] = 'Unspecified';
    }

    /* If all required attributes are available, get the database  *
     * user id and the status code returned by the database query. */
    if ((strlen($shibarray['User Identifier']) > 0) &&
        (strlen($shibarray['Identity Provider']) > 0) &&
        (strlen($shibarray['Organization Name']) > 0) &&
        (strlen($firstname) > 0) &&
        (strlen($lastname) > 0) &&
        (strlen($shibarray['Email Address']) > 0) && 
        ($validator->check_email_address($shibarray['Email Address']))) {
        $dbs->getUser($shibarray['User Identifier'],
                      $shibarray['Identity Provider'],
                      $shibarray['Organization Name'],
                      $firstname,
                      $lastname,
                      $shibarray['Email Address']
                     );
        util::setSessionVar('uid',$dbs->user_uid);
        util::setSessionVar('dn',$dbs->distinguished_name);
        util::setSessionVar('twofactor',$dbs->two_factor);
        util::setSessionVar('status',$dbs->status);
    } else {  // Missing one or more SAML attributes
        util::unsetSessionVar('uid');
        util::unsetSessionVar('dn');
        util::unsetSessionVar('twofactor');
        util::setSessionVar('status',
            dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
    }

    // If 'status' is not STATUS_OK*, then send an error email
    if (util::getSessionVar('status') & 1) { // Bad status codes are odd
        util::sendErrorAlert('Failure in /secure/getuser/',
            'Remote_User   = ' . 
                ((strlen($i = $shibarray['User Identifier']) > 0) ?
                    $i :'<MISSING>')."\n".
            'IdP           = ' .
                ((strlen($i = $shibarray['Identity Provider']) > 0) ?
                    $i : '<MISSING>')."\n".
            'Organization  = ' .
                ((strlen($i = $shibarray['Organization Name']) > 0) ?
                    $i : '<MISSING>')."\n".
            'First Name    = ' .
                ((strlen($firstname) > 0) ?
                    $firstname : '<MISSING>')."\n".
            'Last Name     = ' .
                ((strlen($lastname) > 0) ?
                    $lastname : '<MISSING>')."\n".
            'Email Address = ' .
                ((strlen($i = $shibarray['Email Address']) > 0) ?
                    $i : '<MISSING>')."\n".
            'Database UID  = ' .
                ((strlen($dbs->user_uid) > 0) ?
                    $dbs->user_uid : '<MISSING>')."\n".
            'Status Code   = ' .
                ((strlen($i = array_search(
                    util::getSessionVar('status'),dbservice::$STATUS)) > 0) ? 
                        $i : '<MISSING>')
        );
        util::unsetSessionVar('firstname');
        util::unsetSessionVar('lastname');
        util::unsetSessionVar('loa');
        util::unsetSessionVar('idp');
        util::unsetSessionVar('idpname');
        util::unsetSessionVar('ePPN');
        util::unsetSessionVar('ePTID');
    } else {
        // Set additional session variables needed by the calling script
        if (preg_match('%http://id.incommon.org/assurance/silver%',
                       util::getServerVar('Shib-AuthnContext-Class'))) {
            $shibarray['Level of Assurance'] = 
                'http://incommonfederation.org/assurance/silver';
        }
        util::setSessionVar('firstname',$firstname);
        util::setSessionVar('lastname',$lastname);
        util::setSessionVar('loa',$shibarray['Level of Assurance']);
        util::setSessionVar('idp',$shibarray['Identity Provider']);
        util::setSessionVar('idpname',$shibarray['Organization Name']);
        util::setSessionVar('ePPN',$shibarray['ePPN']);
        util::setSessionVar('ePTID',$shibarray['ePTID']);
    }
    util::unsetSessionVar('requestsilver');
    util::unsetSessionVar('openidID');
}

/************************************************************************
 * Function   : getUserAndRespond                                       *
 * Parameter  : The full URL to redirect to after getting the userid.   *
 * This function gets the user's database UID puts several variables    *
 * in the current PHP session, and responds by redirecting to the       *
 * responseurl in the passed-in parameter.  If there are any issues     *
 * with the database call, an email is sent to the CILogon admins.      *
 ************************************************************************/
function getUserAndRespond($responseurl) {
    global $csrf;

    getUID(); // Get the user's database user ID, put info in PHP session

    util::setSessionVar('submit',util::getSessionVar('responsesubmit'));
    util::unsetSessionVar('responsesubmit');

    $csrf->setTheCookie();
    $csrf->setTheSession();

    /* Finally, redirect to the calling script. */
    header('Location: ' . $responseurl);
}

/************************************************************************
 * Function   : getPKCS12                                               *
 * This function is called when an ECP client wants to get a PKCS12     *
 * credential.  It first attempts to get the user's database UID.  If   *
 * successful, it tries to create a PKCS12 file on disk by calling      *
 * MyProxy.  If successful, it returns the PKCS12 file by setting the   *
 * HTTP Content-type.  If there is an error, it returns a plain text    *
 * file and sets the HTTP response code to an error code.               *
 ************************************************************************/
function getPKCS12() {
    global $log;

    getUID(); // Get the user's database user ID, put info in PHP session
    checkForceSkin(util::getSessionVar('idp')); // Force a skin to be used?

    // If 'status' is not STATUS_OK*, then return error message
    if (util::getSessionVar('status') & 1) { // Bad status codes are odd
        $errstr=array_search(util::getSessionVar('status'),dbservice::$STATUS);
        $log->info("ECP PKCS12 error: $errstr.");
        outputError($errstr);
        return; // ERROR means no further processing is necessary
    }

    if (!twofactor::ecpCheck()) {
        $log->info("ECP PKCS12 error: Two-factor check failed.");
        return; // ERROR means no further processing is necessary
    }

    generateP12();  // Try to create the PKCS12 credential file on disk

    /* Look for the p12error PHP session variable. If set, return it. */
    $p12error = util::getSessionVar('p12error');
    if (strlen($p12error) > 0) {
        $log->info("ECP PKCS12 error: $p12error");
        outputError($p12error);
    } else { // Try to read the .p12 file from disk and return it
        $p12 = util::getSessionVar('p12');
        $p12expire = '';
        $p12link = '';
        $p12file = '';
        if (preg_match('/([^\s]*)\s(.*)/',$p12,$match)) {
            $p12expire = $match[1];
            $p12link = $match[2];
        }
        if ((strlen($p12link) > 0) && (strlen($p12expire) > 0)) {
            $p12file = file_get_contents($p12link);
        } 
        
        if (strlen($p12file) > 0) {
            $log->info("ECP PKCS12 success!");
            header('Content-type: application/x-pkcs12');
            echo $p12file;
        } else {
            $log->info("ECP PKCS12 error: Missing or empty PKCS12 file.");
            outputError('Missing or empty PKCS12 file.');
        }
    }
}

/************************************************************************
 * Function   : getCert                                                 *
 * This function is called when an ECP client wants to get a PEM-       *
 * formatted X.509 certificate by inputting a certificate request       *
 * generated by 'openssl req'.  It first attempts to get the user's     *
 * database UID. If successful, it calls out to myproxy-logon to get    *
 * a certificate. If successful, it returns the certificate by setting  *
 * the HTTP Content-type to 'text/plain'.  If there is an error, it     *
 * returns a plain text file and sets the HTTP response code to an      *
 * error code.                                                          *
 ************************************************************************/
function getCert() {
    global $skin;
    global $log;

    /* Verify that a non-empty certreq <form> variable was posted */
    $certreq = util::getPostVar('certreq');
    if (strlen($certreq) == 0) {
        $log->info("ECP certreq error: Missing certificate request.");
        outputError('Missing certificate request.');
        return; // ERROR means no further processing is necessary
    }

    getUID(); // Get the user's database user ID, put info in PHP session
    checkForceSkin(util::getSessionVar('idp')); // Force a skin to be used?

    // If 'status' is not STATUS_OK*, then return error message
    if (util::getSessionVar('status') & 1) { // Bad status codes are odd
        $errstr=array_search(util::getSessionVar('status'),dbservice::$STATUS);
        $log->info("ECP certreq error: $errstr.");
        outputError($errstr);
        return; // ERROR means no further processing is necessary
    }

    if (!twofactor::ecpCheck()) {
        $log->info("ECP certreq error: Two-factor check failed.");
        return; // ERROR means no further processing is necessary
    }

    /* Set the port based on the Level of Assurance */
    $port = 7512;
    $loa = util::getSessionVar('loa');
    if ($loa == 'http://incommonfederation.org/assurance/silver') {
        $port = 7514;
    } elseif ($loa == 'openid') {
        $port = 7516;
    }
    /* Special hack for OSG - use SHA-1 version of MyProxy servers */
    if (strcasecmp(util::getSessionVar('cilogon_skin'),'OSG') == 0) {
        $port--;
    }

    /* Get the certificate lifetime. Set to a default value if not set. */
    $certlifetime = (int)(util::getPostVar('certlifetime'));
    if ($certlifetime == 0) {  // If not specified, set to default value
        $defaultlifetime = $skin->getConfigOption('ecp','defaultlifetime');
        if ((!is_null($defaultlifetime)) && ((int)$defaultlifetime > 0)) {
            $certlifetime = (int)$defaultlifetime;
        } else {
            $certlifetime = MYPROXY_LIFETIME;
        }
    }

    // Make sure lifetime is within acceptable range. 277 hrs = 1000000 secs.
    list($minlifetime,$maxlifetime) = getMinMaxLifetimes('ecp',277);
    if ($certlifetime < $minlifetime) {
        $certlifetime = $minlifetime;
    } elseif ($certlifetime > $maxlifetime) { 
        $certlifetime = $maxlifetime;  
    }

    /* Make sure that the user's MyProxy username is available. */
    $dn = util::getSessionVar('dn');
    if (strlen($dn) > 0) {
        /* Append extra info, such as 'skin', to be processed by MyProxy. */
        $myproxyinfo = util::getSessionVar('myproxyinfo');
        if (strlen($myproxyinfo) > 0) {
            $dn .= " $myproxyinfo";
        }
        /* Attempt to fetch a credential from the MyProxy server */
        $cert = getMyProxyCredential($dn,'','myproxy.cilogon.org',$port,
            $certlifetime,'/var/www/config/hostcred.pem','',$certreq);

        if (strlen($cert) > 0) { // Successfully got a certificate!
            $log->info("ECP getcert success!");
            header('Content-type: text/plain');
            echo $cert;
        } else { // The myproxy-logon command failed - shouldn't happen!
            $log->info("ECP certreq error: MyProxy unable to create certificate.");
            outputError('Error! MyProxy unable to create certificate.');
        }
    } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
        $log->info("ECP certreq error: Missing 'dn' session value.");
        outputError('Missing username. Please enable cookies.');
    }
}

/************************************************************************
 * Function   : outputError                                             *
 * Parameter  : (Optional) The error string to print in the text/plain  *
 *              return body.                                            *
 * This function sets the HTTP return type to 'text/plain' and also     *
 * sets the HTTP return code to 400, meaning there was an error of      *
 * some kind.  If there is also a passed in errstr, that is output as   *
 * the body of the HTTP return.                                         *
 ************************************************************************/
function outputError($errstr='') {
    header('Content-type: text/plain',true,400);
    if (strlen($errstr) > 0) {
        echo $errstr;
    }
}

?>
