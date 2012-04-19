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
unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = getSessionVar('responseurl');

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

    unsetSessionVar('openiderror');
    $datastore = $openid->getStorage();
    if ($datastore == null) {
        setSessionVar('openiderror',
            'Internal OpenID error. Please contact <a href="mailto:help@cilogon.org">help@cilogon.org</a> or select a different identity provider.');
    } else {
        $consumer = new Auth_OpenID_Consumer($datastore);
        $response = $consumer->complete(getScriptDir(true));

        // Check the response status.
        if ($response->status == Auth_OpenID_CANCEL) {
            // This means the authentication was canceled.
            setSessionVar('openiderror',
                'OpenID logon canceled. Please try again.');
        } elseif ($response->status == Auth_OpenID_FAILURE) {
            // Authentication failed; display an error message.
            setSessionVar('openiderror',
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
                // If only a single name, duplicate first and last name
                if (strlen($lastname) == 0) { 
                    $lastname = $firstname;
                }
            }

        } else {
            setSessionVar('openiderror',
                'OpenID logon error. Please try again.');
        }

        $openid->disconnect();
    }

    /* Make sure no OpenID error was reported */
    if (strlen(getSessionVar('openiderror')) == 0) {
        /* If all required attributes are available, get the       *
         * database user id and status code of the database query. */
        $providerId = getCookieVar('providerId');
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
            setSessionVar('uid',$dbs->user_uid);
            setSessionVar('dn',$dbs->distinguished_name);
            setSessionVar('status',$dbs->status);
        } else { // Missing one or more required attributes
            unsetSessionVar('uid');
            unsetSessionVar('dn');
            setSessionVar('status',
                dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
        }

        // If 'status' is not STATUS_OK*, then send an error email
        if (getSessionVar('status') & 1) { // Bad status codes are odd-numbered
            sendErrorAlert('Failure in /getopeniduser/',
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
                'Database UID  = ' . ((strlen($i=getSessionVar('uid')) > 0) ? 
                    $i : '<MISSING>') . "\n" .
                'Status Code   = ' . ((strlen($i = array_search(
                    getSessionVar('status'),dbservice::$STATUS)) > 0) ? 
                        $i : '<MISSING>')
            );
            unsetSessionVar('firstname');
            unsetSessionVar('lastname');
            unsetSessionVar('loa');
            unsetSessionVar('idp');
            unsetSessionVar('idpname');
            unsetSessionVar('openidID');
        } else {
            setSessionVar('firstname',$firstname);
            setSessionVar('lastname',$lastname);
            setSessionVar('loa','openid');
            setSessionVar('idp',$providerId);
            setSessionVar('idpname',$providerName);
            setSessionVar('openidID',$openidid);
        }

        setSessionVar('submit',getSessionVar('responsesubmit'));

        $csrf->setTheCookie();
        $csrf->setTheSession();
    } else {
        unsetSessionVar('submit');
    }

    unsetSessionVar('responsesubmit');
    unsetSessionVar('ePPN');
    unsetSessionVar('ePTID');

    /* Finally, redirect to the calling script. */
    header('Location: ' . $responseurl);
}

?>
