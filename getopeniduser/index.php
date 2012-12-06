<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('../include/dbservice.php');
require_once('Auth/OpenID/Consumer.php');
require_once("Auth/OpenID/SReg.php");
require_once("Auth/OpenID/AX.php");


/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();
util::unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = util::getSessionVar('responseurl');

if (($submit == 'getuser') && (strlen($responseurl) > 0)) {
    getUserAndRespond($responseurl);
} else {
    $location = 'https://' . HOSTNAME;
    if (strlen($responseurl) > 0) {
        $location = $responseurl;
    }
    header('Location: ' . $location);
}

/************************************************************************
 * Function   : getUserAndRespond                                       *
 * Parameter  : The full URL to redirect to after getting the userid.   *
 * This function checks the URL for a $_GET variable 'openid.identity'  *
 * that gets set by an OpenID provider after successful authentication. *
 * It then makes a call to the database to get a userid and puts        *
 * several variables into the current PHP session.  It then responds    *
 * by redirecting to the resopnseurl in the passed-in parameter.  If    *
 * there are any issues with the database call, the userid is set to    *
 * the empty string and an error code is put in the PHP session.  Also, *
 * an email is sent out to let CILogon admins know of the problem.      *
 ************************************************************************/
function getUserAndRespond($responseurl) {
    global $csrf;

    $dbs = new dbservice();
    $openid = new openid();
    $openidid = '';
    $firstname = '';
    $lastname = '';
    $fullname = '';
    $emailaddr = '';

    util::unsetSessionVar('openiderror');
    $datastore = $openid->getStorage();
    if (is_null($datastore)) {
        util::setSessionVar('openiderror',
            'Internal OpenID error. Please contact <a href="mailto:help@cilogon.org">help@cilogon.org</a> or select a different identity provider.');
    } else {
        $consumer = new Auth_OpenID_Consumer($datastore);
        $response = $consumer->complete(util::getScriptDir(true));

        // Check the response status.
        if ($response->status == Auth_OpenID_CANCEL) {
            // This means the authentication was canceled.
            util::setSessionVar('openiderror',
                'OpenID logon canceled. Please try again.');
        } elseif ($response->status == Auth_OpenID_FAILURE) {
            // Authentication failed; display an error message.
            util::setSessionVar('openiderror',
                'OpenID authentication failed: ' . 
                $response->message . '. Please try again.');
        } elseif ($response->status == Auth_OpenID_SUCCESS) {
            // This means the authentication succeeded; extract the identity.
            $openidid = htmlentities($response->getDisplayIdentifier());

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
                $emailaddr = htmlentities(@$sreg['email']);
            } elseif (@$data['http://axschema.org/contact/email'][0]) {
                $emailaddr = htmlentities(
                    @$data['http://axschema.org/contact/email'][0]);
            }

            // Look for fullname attribute, or firstname+lastname
            if (@$sreg['fullname']) {
                $fullname = htmlentities(@$sreg['fullname']);
            } elseif (@$data['http://axschema.org/namePerson'][0]) {
                $fullname = htmlentities(
                    @$data['http://axschema.org/namePerson'][0]);
            } elseif ((@$data['http://axschema.org/namePerson/first'][0]) &&
                      (@$data['http://axschema.org/namePerson/last'][0])) {
                $fullname = htmlentities(
                    @$data['http://axschema.org/namePerson/first'][0]) .  ' ' . 
                        htmlentities(
                        @$data['http://axschema.org/namePerson/last'][0]);
            }

            // If found fullname, split into firstname and lastname
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

        } else {
            util::setSessionVar('openiderror',
                'OpenID logon error. Please try again.');
        }

        $openid->disconnect();
    }

    /* Make sure no OpenID error was reported */
    if (strlen(util::getSessionVar('openiderror')) == 0) {
        /* If all required attributes are available, get the       *
         * database user id and status code of the database query. */
        $providerId = util::getCookieVar('providerId');
        $providerName = openid::getProviderName($providerId);
        /* In the database, keep a consistent ProviderId format:   *
         * only allow "http" (not "https") and remove any "www."   *
         * prefix (for Google).                                    */
        $databaseProviderId = 
            preg_replace('%^https://(www\.)?%','http://',$providerId);
        $validator = new EmailAddressValidator();

        if ((strlen($openidid) > 0) && 
            (strlen($providerId) > 0) &&
            (strlen($providerName) > 0)  &&
            (strlen($firstname) > 0) &&
            (strlen($lastname) > 0) &&
            (strlen($emailaddr) > 0) &&
            ($validator->check_email_address($emailaddr))) {
            $dbs->getUser($openidid,
                          $databaseProviderId,
                          $providerName,
                          $firstname,
                          $lastname,
                          $emailaddr);
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
        } else {
            util::setSessionVar('firstname',$firstname);
            util::setSessionVar('lastname',$lastname);
            util::setSessionVar('loa','openid');
            util::setSessionVar('idp',$providerId);
            util::setSessionVar('openidID',$openidid);
        }

        util::setSessionVar('idpname',$providerName); // Enable check for Google
        util::setSessionVar('submit',util::getSessionVar('responsesubmit'));

        $csrf->setTheCookie();
        $csrf->setTheSession();
    } else {
        util::unsetSessionVar('submit');
    }

    util::unsetSessionVar('responsesubmit');
    util::unsetSessionVar('ePPN');
    util::unsetSessionVar('ePTID');

    /* Finally, redirect to the calling script. */
    header('Location: ' . $responseurl);
}

?>
