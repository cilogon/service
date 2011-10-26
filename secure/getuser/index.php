<?php

require_once('../../include/util.php');
require_once('../../include/autoloader.php');
require_once('../../include/content.php');
require_once('../../include/shib.php');
require_once('../../include/myproxy.php');

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();
unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = getSessionVar('responseurl');

if (($submit == 'getuser') && (strlen($responseurl) > 0)) {
    getUserAndRespond($responseurl);
} elseif ($submit == 'pkcs12') {
    getPKCS12();
} elseif ($submit == 'certreq') {
    getCert();
} else {
    $location = 'https://' . HOSTNAME;
    if (strlen($responseurl) > 0) {
        $location = $responseurl;
    }
    header('Location: ' . $responseurl);
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
 * username to be passed to MyProxy ('dn'), etc.  If there is some sort *
 * of error with the database call, an email is sent showing which      *
 * SAML attributes were missing.                                        *
 ************************************************************************/
function getUID() {
    $dbs = new dbservice();
    $shibarray = getShibInfo();

    /* If either firstname or lastname is empty but displayName *
     * is okay, extract first/last name from the displayName.   */
    $firstname   = $shibarray['First Name'];
    $lastname    = $shibarray['Last Name'];
    $displayname = $shibarray['Display Name'];
    if (((strlen($firstname) == 0) || (strlen($lastname) == 0)) &&
         (strlen($displayname) > 0)) {
        if (preg_match('/^([^\s]+)\s+/',$displayname,$matches)) {
            $firstname = $matches[1];
        }
        if (preg_match('/\s+([^\s]+)$/',$displayname,$matches)) {
            $lastname = $matches[1];
        }
    }
    /* Save firstname and lastname for later use by other functions. */
    setSessionVar('firstname',$firstname);
    setSessionVar('lastname',$lastname);

    $validator = new EmailAddressValidator();

    /* Temporary hack for IdP at boingo.ncsa.uiuc.edu */
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
        setSessionVar('uid',$dbs->user_uid);
        setSessionVar('dn',$dbs->distinguished_name);
        setSessionVar('status',$dbs->status);
    } else {  // Missing one or more SAML attributes
        unsetSessionVar('uid');
        unsetSessionVar('dn');
        setSessionVar('status',
            dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
    }

    // If 'status' is not STATUS_OK*, then send an error email
    if (getSessionVar('status') & 1) { // Bad status codes are odd-numbered
        sendErrorAlert('Failure in /secure/getuser/',
            'Remote_User   = ' . 
                ((strlen($i = $shibarray['User Identifier']) > 0) ?
                    $i :' <MISSING>')."\n".
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
                    getSessionVar('status'),dbservice::$STATUS)) > 0) ? 
                        $i : '<MISSING>')
        );
        unsetSessionVar('firstname');
        unsetSessionVar('lastname');
        unsetSessionVar('dn');
        unsetSessionVar('loa');
        unsetSessionVar('idp');
        unsetSessionVar('idpname');
    } else {
        // Set additional session variables needed by the calling script
        setSessionVar('loa',$shibarray['Level of Assurance']);
        setSessionVar('idp',$shibarray['Identity Provider']);
        setSessionVar('idpname',$shibarray['Organization Name']);
    }
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

    setSessionVar('submit',getSessionVar('responsesubmit'));
    unsetSessionVar('responsesubmit');

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
    getUID(); // Get the user's database user ID, put info in PHP session
    checkForceSkin(getSessionVar('idp')); // Do we force a skin to be used?

    // If 'status' is not STATUS_OK*, then return error message
    if (getSessionVar('status') & 1) { // Bad status codes are odd-numbered
        outputError(array_search(getSessionVar('status'),dbservice::$STATUS));
        return; // ERROR means no further processing is necessary
    }

    generateP12();  // Try to create the PKCS12 credential file on disk

    /* Look for the p12error PHP session variable. If set, return it. */
    $p12error = getSessionVar('p12error');
    if (strlen($p12error) > 0) {
        outputError($p12error);
    } else { // Try to read the .p12 file from disk and return it
        $p12 = getSessionVar('p12');
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
            header('Content-type: application/x-pkcs12');
            echo $p12file;
        } else {
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

    /* Verify that a non-empty certreq <form> variable was posted */
    $certreq = getPostVar('certreq');
    if (strlen($certreq) == 0) {
        outputError('Missing certificate request.');
        return; // ERROR means no further processing is necessary
    }

    getUID(); // Get the user's database user ID, put info in PHP session
    checkForceSkin(getSessionVar('idp')); // Do we force a skin to be used?

    // If 'status' is not STATUS_OK*, then return error message
    if (getSessionVar('status') & 1) { // Bad status codes are odd-numbered
        outputError(array_search(getSessionVar('status'),dbservice::$STATUS));
        return; // ERROR means no further processing is necessary
    }

    /* Set the port based on the Level of Assurance */
    $port = 7512;
    $loa = getSessionVar('loa');
    if ($loa == 'http://incommonfederation.org/assurance/silver') {
        $port = 7514;
    } elseif ($loa == 'openid') {
        $port = 7516;
    }

    /* Get the certificate lifetime. Set to a default value if not set. */
    $certlifetime = (int)(getPostVar('certlifetime'));
    if ($certlifetime == 0) {  // If not specified, set to default value
        $defaultlifetime = $skin->getConfigOption('ecp','defaultlifetime');
        if (($defaultlifetime !== null) && ((int)$defaultlifetime > 0)) {
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

    /* Hack to use the newest version of myproxy-logon */
    define('MYPROXY_LOGON','/usr/local/myproxy-20110516/bin/myproxy-logon');
    $env = "DYLD_LIBRARY_PATH=/usr/local/myproxy-20110516/lib GLOBUS_LOCATION=/usr/local/myproxy-20110516 GLOBUS_PATH=/usr/local/myproxy-20110516 LD_LIBRARY_PATH=/usr/local/myproxy-20110516/lib LIBPATH=/usr/local/myproxy-20110516/lib:/usr/lib:/lib PERL5LIB=/usr/local/myproxy-20110516/lib/perl SHLIB_PATH=/usr/local/myproxy-20110516/lib";

    /* Make sure that the user's MyProxy username is available. */
    $dn = getSessionVar('dn');
    if (strlen($dn) > 0) {
        /* Append extra info, such as 'skin', to be processed by MyProxy. */
        $myproxyinfo = getSessionVar('myproxyinfo');
        if (strlen($myproxyinfo) > 0) {
            $dn .= " $myproxyinfo";
        }
        /* Attempt to fetch a credential from the MyProxy server */
        $cert = getMyProxyCredential($dn,'','myproxy.cilogon.org',$port,
            $certlifetime,'/var/www/config/hostcred.pem','',$certreq,$env);

        if (strlen($cert) > 0) { // Successfully got a certificate!
            header('Content-type: text/plain');
            echo $cert;
        } else { // The myproxy-logon command failed - shouldn't happen!
            outputError('Error! MyProxy unable to create certificate.');
        }
    } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
        outputError('Missing username. Please enable cookies.');
    }
}

/************************************************************************
 * Function   : outputError                                             *
 * Parameter  : (Optional) The error string to print in the text/plain  *
 *              return body.                                            *
 * This function sets the HTTP return type to 'text/plain' and also     *
 * sets the HTTP return code to 400, meaning there was an error of      *
 * some sort.  If there is also a passed in errstr, that is output as   *
 * the body of the HTTP return.                                         *
 ************************************************************************/
function outputError($errstr='') {
    header('Content-type: text/plain',true,400);
    if (strlen($errstr) > 0) {
        echo $errstr;
    }
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
