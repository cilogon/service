<?php

require_once('../../include/util.php');
require_once('../../include/autoloader.php');
require_once('../../include/content.php');
require_once('../../include/shib.php');

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();
unsetSessionVar('submit');

/* Get the URL to reply to after database query. */
$responseurl = getSessionVar('responseurl');
unsetSessionVar('responseurl');

if (($submit == 'getuser') && (strlen($responseurl) > 0)) {
    getUserAndRespond($responseurl);
} else {
    printServerVars();
}

/************************************************************************
 * Function   : getUserAndRespond                                       *
 * Parameter  : The full URL to redirect to after getting the userid.   *
 * This function takes all of the various required SAML attributes (as  *
 * set in the current Shibboleth session), makes a call to the database *
 * to get the userid assoicated with those attributes, puts several     *
 * variables in the current PHP session, and responds by redirecting to *
 * the responseurl in the passed-in parameter.  If there are any issues *
 * with the database call, the userid is set to the empty string and    *
 * an error code is put in the PHP session before responding.           *
 ************************************************************************/
function getUserAndRespond($responseurl) {
    global $csrf;

    $shibarray = getShibInfo();
    $dbs = new dbservice();

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

    /* If all required attributes are available, get the       *
     * database user id and status code of the database query. */
    if ((strlen($shibarray['User Identifier']) > 0) &&
        (strlen($shibarray['Identity Provider']) > 0) &&
        (strlen($shibarray['Organization Name']) > 0) &&
        (strlen($firstname) > 0) &&
        (strlen($lastname) > 0) &&
        (strlen($shibarray['Email Address']) > 0)) {
        $dbs->getUser($shibarray['User Identifier'],
                      $shibarray['Identity Provider'],
                      $shibarray['Organization Name'],
                      $firstname,
                      $lastname,
                      $shibarray['Email Address']
                     );
        setSessionVar('uid',$dbs->user_uid);
        setSessionVar('status',$dbs->status);
    } else {
        setSessionVar('uid');
        setSessionVar('status',
            dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
    }

    // If 'status' is not STATUS_OK*, then send an error email
    if (getSessionVar('status') & 1) { // Bad status codes are odd-numbered
        sendErrorEmail($shibarray['User Identifier'],
                       $shibarray['Identity Provider'],
                       $shibarray['Organization Name'],
                       $firstname, 
                       $lastname,
                       $shibarray['Email Address'],
                       getSessionVar('uid'),
                       array_search(getSessionVar('status'),dbservice::$STATUS)
                      );
    } else {
        // Set additional session variables needed by the calling script
        setSessionVar('dn',$dbs->distinguished_name);
        setSessionVar('loa',$shibarray['Level of Assurance']);
        setSessionVar('idp',$shibarray['Identity Provider']);
        setSessionVar('idpname',$shibarray['Organization Name']);
    }

    setSessionVar('submit',getSessionVar('responsesubmit'));
    unsetSessionVar('responsesubmit');

    $csrf->setTheCookie();
    $csrf->setTheSession();

    /* Finally, redirect to the calling script. */
    header('Location: ' . $responseurl);
}

/************************************************************************
 * Function   : sendErrorEmail                                          *
 * Parameters : (1) The REMOTE_USER                                     *
 *              (2) EntityId of the Identity Provider                   *
 *              (3) IdP Display Name                                    *
 *              (4) User's first name                                   *
 *              (5) User's last name                                    *
 *              (6) User's email address                                *
 *              (7) Persistent store user identifier                    *
 *              (8) String value of the status of getUser() call        *
 * This function sends an email to help@cilogon.org when there is a     *
 * problem getting the user.  This can happen when there are missing    *
 * SAML attributes in the Shibboleth session, or when the persistent    *
 * store getUser() call returns a bad status code.                      *
 ************************************************************************/
function sendErrorEmail($remote_user,$idp,$idpname,$firstname,$lastname,
                        $emailaddr,$uid,$statuscode) 
{
    $mailto   = 'help@cilogon.org';
    $mailfrom = 'From: help@cilogon.org' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
    $mailsubj = 'CILogon Service - Failure in getuser script';
    $mailmsg  = '
CILogon Service - Failure in /secure/getuser/
---------------------------------------------
Remote_User   = ' . 
    ((strlen($remote_user) > 0) ? $remote_user : '<MISSING>') . '
IdP           = ' .
    ((strlen($idp) > 0) ? $idp : '<MISSING>') . '
Organization  = ' .
    ((strlen($idpname) > 0) ? $idpname : '<MISSING>') . '
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
