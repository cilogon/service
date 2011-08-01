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
            'Internal OpenID error. Please try logging in with Shibboleth.');
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
            $data = $ax->fromSuccessResponse($response)->data;

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
        setSessionVar('idpname',$providerName);  // Save for later use
        $validator = new EmailAddressValidator();

        if ((strlen($openidid) > 0) && 
            (strlen($providerId) > 0) &&
            (strlen($providerName) > 0)  &&
            (strlen($firstname) > 0) &&
            (strlen($lastname) > 0) &&
            (strlen($emailaddr) > 0) &&
            ($validator->check_email_address($emailaddr))) {
            $dbs->getUser($openidid,
                          $providerId,
                          $providerName,
                          $firstname,
                          $lastname,
                          $emailaddr);
            setSessionVar('uid',$dbs->user_uid);
            setSessionVar('status',$dbs->status);
        } else {
            setSessionVar('uid');
            setSessionVar('status',
                dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
        }

        // If 'status' is not STATUS_OK*, then send an error email
        if (getSessionVar('status') & 1) { // Bad status codes are odd-numbered
            sendErrorEmail($openidid,
                           $providerId,
                           $providerName,
                           $firstname,
                           $lastname,
                           $emailaddr,
                           getSessionVar('uid'),
                           array_search(getSessionVar('status'),
                               dbservice::$STATUS)
                          );
        } else {
            setSessionVar('dn',$dbs->distinguished_name);
            setSessionVar('loa','openid');
            setSessionVar('idp',$providerId);
        }

        setSessionVar('submit',getSessionVar('responsesubmit'));

        $csrf->setTheCookie();
        $csrf->setTheSession();
    } else {
        unsetSessionVar('submit');
    }

    unsetSessionVar('responsesubmit');

    /* Finally, redirect to the calling script. */
    header('Location: ' . $responseurl);
}

/************************************************************************
 * Function   : sendErrorEmail                                          *
 * Parameters : (1) The user's OpenID                                   *
 *              (2) The OpenID Provider                                 *
 *              (3) The OpenID Provider display name                    *
 *              (4) The user's first name                               *
 *              (5) The user's last name                                *
 *              (6) The user's email address                            *
 *              (7) Persistent store user identifier                    *
 *              (8) String value of the status of getUser() call        *
 * This function sends an email to help@cilogon.org when there is a     *
 * problem getting the user.  This can happen when there are missing    *
 * SAML attributes in the OpenID session, or when the persistent        *
 * store getUser() call returns a bad status code.                      *
 ************************************************************************/
function sendErrorEmail($openidid,$providerId,$providerName,
                        $firstname,$lastname,$emailaddr,$uid,$statuscode) 
{
    $mailto   = 'help@cilogon.org';
    $mailfrom = 'From: help@cilogon.org' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
    $mailsubj = 'CILogon Service on ' . HOSTNAME . 
                ' - Failure in getopeniduser script for ' . $providerName;
    $mailmsg  = '
CILogon Service - Failure in /getopeniduser/
--------------------------------------------
Server Host   = ' . HOSTNAME . '
Remote Address= ' . getServerVar('REMOTE_ADDR') . '
OpenId ID     = ' . 
    ((strlen($openidid) > 0) ? $openidid : '<MISSING>') . '
Provider URL  = ' .
    ((strlen($providerId) > 0) ? $providerId : '<MISSING>') . '
Provider Name = ' .
    ((strlen($providerName) > 0) ? $providerName : '<MISSING>') . '
First Name    = ' .
    ((strlen($firstname) > 0) ? $firstname : '<MISSING>') . '
Last Name     = ' .
    ((strlen($lastname) > 0) ? $lastname : '<MISSING>') . '
Email Address = ' .
    ((strlen($emailaddr) > 0) ? $emailaddr : '<MISSING>') . '
Database UID  = ' .
    ((strlen($uid) > 0) ? $uid : '<MISSING>') . '
Status Code   = ' .
    ((strlen($statuscode) > 0) ? $statuscode : '<MISSING>') . '
';
    mail($mailto,$mailsubj,$mailmsg,$mailfrom);
}

/************************************************************************
 * Function   : printServerVars                                         *
 * This function prints out the various server variable arrays and the  *
 * shibboleth variables in a pretty print format.                       *
 ************************************************************************/
function printServerVars() {
    printHeader('Display Server Variables',
    '<script language="JavaScript" type="text/JavaScript">
    <!--
      function decodeAttributeResponse() {
        var textarea = document.getElementById("attributeResponseArea");
        var base64str = textarea.value;
        var decodedMessage = decode64(base64str);
        textarea.value = tidyXml(decodedMessage);
        textarea.rows = 15;
        document.getElementById("decodeButtonBlock").style.display=\'none\';
      }

      function tidyXml(xmlMessage) {
        //put newline before closing tags of values inside xml blocks
        xmlMessage = xmlMessage.replace(/([^>])</g,"$1\n<");
        //put newline after every tag
        xmlMessage = xmlMessage.replace(/>/g,">\n");
        var xmlMessageArray = xmlMessage.split("\n");
        xmlMessage="";
        var nestedLevel=0;
        for (var n=0; n < xmlMessageArray.length; n++) {
          if ( xmlMessageArray[n].search(/<\//) > -1 ) {
            nestedLevel--;
          }
          for (i=0; i<nestedLevel; i++) {
            xmlMessage+="  ";
          }
          xmlMessage+=xmlMessageArray[n]+"\n";
          if ( xmlMessageArray[n].search(/\/>/) > -1 ) {
            //level status the same
          }
          else if ((xmlMessageArray[n].search(/<\//) < 0) && 
                   (xmlMessageArray[n].search(/</) > -1)) {
            //only increment if this was a tag, not if it is a value
            nestedLevel++;
          }
        }
        return xmlMessage;
      }

      function decode64(encodedString) {
        var base64Key = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
        var decodedMessage = "";
        var char1, char2, char3;
        var enc1, enc2, enc3, enc4;
        var i = 0;
      
        //remove all characters that are not A-Z, a-z, 0-9, +, /, or =
        encodedString = encodedString.replace(/[^A-Za-z0-9\+\/\=]/g, "");
        do {
          enc1 = base64Key.indexOf(encodedString.charAt(i++));
          enc2 = base64Key.indexOf(encodedString.charAt(i++));
          enc3 = base64Key.indexOf(encodedString.charAt(i++));
          enc4 = base64Key.indexOf(encodedString.charAt(i++));

          char1 = (enc1 << 2) | (enc2 >> 4);
          char2 = ((enc2 & 15) << 4) | (enc3 >> 2);
          char3 = ((enc3 & 3) << 6) | enc4;

          decodedMessage = decodedMessage + String.fromCharCode(char1);
          if (enc3 != 64) {
            decodedMessage = decodedMessage + String.fromCharCode(char2);
          }
          if (enc4 != 64) {
            decodedMessage = decodedMessage + String.fromCharCode(char3);
          }
        } while (i < encodedString.length);
        return decodedMessage;
      }
    // -->
    </script>
    ');

    echo '
    <b>-all SHIB headers-</b> (<code>HTTP_SHIB_ATTRIBUTES</code> 
    is not shown in this list)
    <table>';

    foreach ($_SERVER as $key => $value)
    {
        $fkey='_'.$key;
        if ( strpos($fkey,'SHIB')>1 && $key!="HTTP_SHIB_ATTRIBUTES") {
            echo '<tr><td>',$key,'</td><td>',$value,'</td></tr>';
        }
    }
    echo '
    <tr><td>(REMOTE_USER)</td><td>',$_SERVER['REMOTE_USER'],'</td></tr>
    <tr><td>(HTTP_REMOTE_USER)</td><td>',$_SERVER['HTTP_REMOTE_USER'],
    '</td></tr>
    </table>
    <br/>

    attribute response from the IdP (<code>HTTP_SHIB_ATTRIBUTES</code>):<br/>
    <textarea id="attributeResponseArea" onclick="select()" rows="1"
    cols="130">',$_SERVER["HTTP_SHIB_ATTRIBUTES"],'</textarea><br/>
    <span id="decodeButtonBlock"><input type="button" id="decodeButton"
    value="decode base64 encoded attribute response using JavaScript"
    onClick="decodeAttributeResponse();"><br/></span>

    <br/>

    <small>
    notes:<br/>
    The AAP throws away invalid values (eg an unscopedAffiliation of value
    "myBoss@&lt;yourdomain&gt;" or a value with an invalid scope which scope
    is checked)<br/>
    The raw attribute response (<code>HTTP_SHIB_ATTRIBUTES</code>) is NOT
    filtered by the AAP and should therefore be disabled for most
    applications (<code>exportAssertion=false</code>).<br/>
    </small>
    ';

    printVarTable($_SERVER);
    printVarTable($_SESSION);
    printVarTable($_COOKIE);
    printPostTable();
    printVarTable($_REQUEST);
    printVarTable($_ENV);

    echo '
    <br/>
    <hr/>
    <br/>
    ';

    printFooter();
}

/************************************************************************
 * Function   : printVarTable                                           *
 * Parameter  : An array (like $_SERVER) to print in an HTML table.     *
 * This function prints out an array in an HTML table.  This can be     *
 * useful for printing out $_SERVER, $_COOKIE, $_REQUEST, etc.          *
 ************************************************************************/
function printVarTable(&$vararray) {
    echo '<br/><hr/><br/><b>$' , var_name($vararray) , '</b><table>
    ';

    foreach ($vararray as $key => $value) {
        echo '<tr><td>',$key,'</td><td>',$value,'</td></tr>';
    }

    echo '</table>
    ';
}

/************************************************************************
 * Function   : printPostTable                                          *
 * This function prints out the $_POST array in an HTML table.          * 
 ************************************************************************/
function printPostTable() {
    echo '<br/><hr/><br/><b>$_POST</b><table>
    ';

    foreach ($_POST as $key => $value) {
        echo '<tr><td>',$key,'</td><td>',$value,'</td></tr>';
    }

    echo '</table>
    ';
}

/************************************************************************
 * Function   : var_name                                                *
 * Parameters : (1) A php variable.                                     *
 *              (2) (Optional) The "scope" of the variable.  Defaults   *
 *                  to global scope.  Use "get_defined_vars()" for      *
 *                  local scope, or an object instance for looking at   *
 *                  members variables of the object.                    *
 * Returns the name of a php variable as a string.  Taken from:         *
 * http://www.php.net/manual/en/language.variables.php#76245            *
 ************************************************************************/
function var_name(&$var,$scope=0) {
    $old = $var;
    if (($key = array_search($var = 'unique'.rand().'value', 
                             !$scope ? $GLOBALS : $scope)) && 
         ($var = $old)) {
        return $key; 
    }
}

?>
