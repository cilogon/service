<?php

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');

/* This array contains the cookies that we do not want to show to the *
 * user because we don't want these cookies to be deleted. The cookie *
 * names are then excluded from the cookie counts, as well as the     *
 * list of cookies which could be deleted.                            */
$hide = array(
    'CSRF',
    'CSRFProtection',
    'PHPSESSID',
    'lastaccess',
    'myproxyinfo',
);

/* Get the value of the "submit" input element. */
$submit = util::getPostVar('submit');
util::unsetSessionVar('submit');

/* Depending on the value of the clicked "submit" button, *
 * take action and print out HTML.                        */
switch ($submit) {

    case 'Delete Checked':
        deleteChecked();
    break; // End case 'Delete Checked'

    case 'Delete Browser Cookies':
        deleteBrowserCookies();
    break; // End case 'Delete Browser Cookies'

    case 'Delete Session Variables':
        deleteSessionVariables();
    break; // End case 'Delete Session Variables'

    case 'Delete ALL':
        deleteBrowserCookies();
        deleteSessionVariables();
    break; // End case 'Delete ALL'

} // End switch($submit)

printMainCookiesPage();

// END MAIN PROGRAM

/************************************************************************
 * Function   : printMainCookiesPage                                    *
 * This function prints out the main "Manage Cookies" page on each      *
 * reload. It calls other functions to print out the subsections of     *
 * the page. Note that all subsections are enclosed in a <form> so      *
 * we need only a single <form> group.                                  *
 ************************************************************************/
function printMainCookiesPage() {
    $browsercount = countBrowserCookies();
    $sessioncount = countSessionVariables();

    printHeader('Manage CILogon Cookies','',false); // Don't set CSRF
    printPageHeader('Manage CILogon Cookies');

    printFormHead();

    printAboutThisPage($browsercount,$sessioncount);
    printBrowserCookies($browsercount);
    printSessionVariables($sessioncount);
    printEnvironmentVars();

    echo '</form>';

    printFooter();
}

/************************************************************************
 * Function   : printAboutThisPage                                      *
 * Parameters : (1) The number of deletable browser cookies.            *
 *              (2) The number of deletable session variables.          *
 * This function prints the "About This Page" subsection, which also    *
 * contains several "submit buttons (such as "Delete Browser Cookies"   *
 * and "Reload Page").                                                  *
 ************************************************************************/
function printAboutThisPage($browsercount,$sessioncount) {
    echo '
    <div class="boxed">
      <div class="boxheader">
      About This Page
      </div>
    <p>
    This page allows you to view and (potentially) delete various cookies
    associated with the <a target="_blank" href="..">CILogon Service</a>.
    There are three sections below.
    </p>

    <ol>
    <li><b>Browser Cookies</b> - These are &quot;cookies&quot; 
    which are stored in your browser. They are used as preferences for the
    CILogon Service.
    </li>
    <li><b>Session Variables</b> - These are &quot;short-lived&quot;
    values related to your current CILogon session. Deleting any of these
    values may require you to re-logon.
    </li>
    <li><b>Environment Variables</b> - These are values set by the
    interaction between your browser and the web server. These are displayed
    mainly for information purposes.
    </li>
    </ol>
    ';

    /* If there are brower cookies or session variables which can be    *
     * deleted, output the appropriate "Delete ..." button(s).          */
    if (($browsercount > 0) || ($sessioncount > 0)) {
        echo '
        <p>
        You can delete cookies individually by checking the associated
        checkbox(es) and clicking the &quot;Delete Checked&quot; button. 
        You can also delete groups of cookies by clicking ';
        if ($browsercount > 0) {
            echo 'the &quot;Delete Browser Cookies&quot; button';
        }
        if ($sessioncount > 0) {
            if ($browsercount > 0) {
                echo ", ";
            }
            echo 'the &quot;Delete Session Variables&quot; button';
            if ($browsercount > 0) {
                echo ', or the &quot;Delete ALL&quot; button';
            }
        }
        echo '.';
    }

    echo '
    </p>

    <p class="centered">
    ';
    
    if ($browsercount > 0) {
        echo '<input type="submit" name="submit" class="submit"
               value="Delete Browser Cookies" /> ';
    }
    if ($sessioncount > 0) {
        echo '<input type="submit" name="submit" class="submit"
               value="Delete Session Variables" /> ';
    }
    if (($browsercount > 0) && ($sessioncount > 0)) {
        echo '<input type="submit" name="submit" class="submit"
               value="Delete ALL" /> ';
    }
    echo '
    <input type="submit" name="submit" class="submit" value="Reload Page" />
    </p>
    </div>
    ';
}

/************************************************************************
 * Function   : printBrowserCookies                                     *
 * Parameter  : The number of deletable browser cookies.                *
 * This function prints the "Browser Cookies" section, with checkboxes  *
 * next to the cookies to allow for deletion. If there are no browser   *
 * cookies, then simply output "none found" message.                    *
 ************************************************************************/
function printBrowserCookies($browsercount) {
    global $hide;

    echo '
    <p> </p>
    <div class="boxed">
      <div class="boxheader">
        Browser Cookies
      </div>
    ';

    if ($browsercount > 0) {
        echo '
          <table rules="rows" width="100%">
        ';
        
        ksort($_COOKIE);
        foreach ($_COOKIE as $key => $value) {
            if (!in_array($key,$hide)) {
                echo '<tr title="' , getTitleText($key) , '">' ,
                     '<td><input type="checkbox" name="del_browser[]" ',
                     'value="', $key , '"/></td>' ,
                     '<td style="padding-right:2em"><tt>' ,
                     util::htmlent($key) ,
                     '</tt></td><td><tt>';
                // Special handling of portalparams cookie
                if ($key == portalcookie::cookiename) {
                    $pc = new portalcookie();
                    echo util::htmlent($pc->toString());
                } else {
                    echo util::htmlent($value);
                }
                echo '</tt></td></tr>';
            }
        }

        echo '
          </table>

          <p class="centered">
          <input type="submit" name="submit" class="submit"
           value="Delete Checked" />
          </p>
        ';
    } else {
        echo '<p>No browser cookies found.</p>';
    }

    echo '
    </div>
    ';
}

/************************************************************************
 * Function   : printSessionVariables                                   *
 * Parameter  : The number of deletable session variables.              *
 * This function prints the "Session Variables" section, with           *
 * checkboxes  next to the variables to allow for deletion. If there    *
 * are no session variables, then simply output "none found" message.   *
 ************************************************************************/
function printSessionVariables($sessioncount) {
    global $hide;

    echo '
    <p> </p>
    <div class="boxed">
      <div class="boxheader">
        Session Variables
      </div>
    ';

    if ($sessioncount > 0) {
        echo '
          <table rules="rows" width="100%">
        ';
        
        ksort($_SESSION);
        foreach ($_SESSION as $key => $value) {
            if (!in_array($key,$hide)) {
                echo '<tr title="' , getTitleText($key) , '">' ,
                     '<td><input type="checkbox" name="del_session[]" ',
                     'value="', $key , '"/></td>' ,
                     '<td style="padding-right:2em"><tt>' ,
                     util::htmlent($key) ,
                     '</tt></td><td><tt>' ,
                     util::htmlent($value) ,
                     '</tt></td></tr>';
            }
        }

        echo '
          </table>

          <p class="centered">
          <input type="submit" name="submit" class="submit"
          value="Delete Checked" />
          </p>
        ';
    } else {
        echo '<p>No session variables found.</p>';
    }

    echo '
    </div>
    ';
}

/************************************************************************
 * Function   : printEnvironmentVars                                    *
 * This function prints out the display-only web environment variables  *
 * (e.g. the $_SERVER array).                                           *
 ************************************************************************/
function printEnvironmentVars() {
    echo '
    <p> </p>
    <div class="boxed">
      <div class="boxheader">
        Environment Variables
      </div>

      <table rules="rows" width="100%">
    ';
    
    ksort($_SERVER);
    foreach ($_SERVER as $key => $value) {
        echo '<tr><td style="padding-right:2em"><tt>' ,
             util::htmlent($key) ,
             '</tt></td><td><tt>' ,
             util::htmlent($value) ,
             '</tt></td></tr>';
    }

    echo '
      </table>
    </div>
    ';
}

/************************************************************************
 * Function   : countBrowserCookies                                     *
 * Return     : The number of deletable browser cookies.                *
 * This function counts the number of elements in the $_COOKIE array,   *
 * minus those elements in the global $hide array.                      *
 ************************************************************************/
function countBrowserCookies() {
    global $hide;

    $retval = count($_COOKIE);

    foreach ($hide as $h) {
        if (isset($_COOKIE[$h])) {
            $retval--;
        }
    }

    if ($retval < 0) {
        $retval = 0;
    }

    return $retval;
}

/************************************************************************
 * Function   : countSessionVariables                                   *
 * Return     : The number of deletable session variables.              *
 * This function counts the number of elements in the $_SESSION array,  *
 * minus those elements in the global $hide array.                      *
 ************************************************************************/
function countSessionVariables() {
    global $hide;

    $retval = count($_SESSION);

    foreach ($hide as $h) {
        if (isset($_SESSION[$h])) {
            $retval--;
        }
    }

    if ($retval < 0) {
        $retval = 0;
    }

    return $retval;
}

/************************************************************************
 * Function   : deleteChecked                                           *
 * This function is called when the "Delete Checked" button is clicked. *
 * It iterates through all of the checked boxes for the "Browser        *
 * Cookies" and "Session Variables" sections and deletes the            *
 * corresponding cookie or session variable.                            *
 ************************************************************************/
function deleteChecked() {
    $del_browser = util::getPostVar('del_browser');
    if (is_array($del_browser)) {
        foreach ($del_browser as $value) {
            util::unsetCookieVar($value);
        }
    }

    $del_session = util::getPostVar('del_session');
    if (is_array($del_session)) {
        foreach ($del_session as $value) {
            util::unsetSessionVar($value);
        }
    }
}

/************************************************************************
 * Function   : deleteBrowserCookies                                    *
 * This function is called when the "Delete Browser Cookies" button     *
 * or the "Delete ALL" button is pressed. It deletes all elements in    *
 * the $_COOKIE array except for those in the global $hide array.       *
 ************************************************************************/
function deleteBrowserCookies() {
    global $hide;

    foreach ($_COOKIE as $key => $value) {
        if (!in_array($key,$hide)) {
            util::unsetCookieVar($key);
        }
    }
}

/************************************************************************
 * Function   : deleteSessionVariables                                  *
 * This function is called when the "Delete Session Variables" button   *
 * or the "Delete ALL" button is pressed. It deletes all elements in    *
 * the $_SESSION array except for those in the global $hide array.      *
 ************************************************************************/
function deleteSessionVariables() {
    global $hide;

    foreach ($_SESSION as $key => $value) {
        if (!in_array($key,$hide)) {
            util::unsetSessionVar($key);
        }
    }
}

/************************************************************************
 * Function   : getTitleText                                            *
 * Parameter  : The name of a browser cookie or session variable.       *
 * Return     : A string explaining the cookie/variable in question.    *
 *              Empty string if no such cookie/variable found.          *
 * This function takes in a browser cookie or session variable and      *
 * returns an "explanation" string which is used in the "title=..."     *
 * attribute. This text is displayed in the user's browser when the     *
 * mouse cursor hovers over the row containing the cookie/variable      *
 * and corresponding value. The function simply looks in the $explain   *
 * array for the $cookie key, and returns the value (if any).           *
 ************************************************************************/
function getTitleText($cookie) {
    $retval = '';

    /* Keys are brower cookies / session variables. Values are          *
     * explanation string to be shown in "title=..." attributes.        *
     * NOTE: the array is searched using "preg_match" to allow for      *
     * substring matches (which is important in the case of             *
     * _shibsession...). Thus, it is important that longer strings      *
     * appear before shorter strings with the same prefix, e.g.         *
     * "p12 error" appears before "p12".                                */
    $explain = array(
        "activation" => "The expiration time and activation code for use by CILogon-enabled applications." ,
        "affiliation" => "A list of attributes describing your affiliations at your Identity Provider." ,
        "authntime" => "The Unix timestamp of the last successful user authentication." ,
        "callbackuri" => "The URL of the callback servlet used by portals connecting to the CILogon Delegate service." ,
        "certlifetime" => "This multiplied by the certmultipler gives the lifetime of the GridShib-CA certificate in seconds." ,
        "certmultiplier" => "This multiplied by the certlifetime gives the lifetime of the GridShib-CA certificate in seconds." ,
        "cilogon_skin" => "The skin affects the look-and-feel and functionality of the CILogon Service. It is typically specified by a portal." ,
        "clientparams" => "A set of cookies for each portal you have used with CILogon." ,
        "displayname" => "Your full name set by your Identity Provider." ,
        "dn" => "A quasi distinguished name for the certificate issued by a MyProxy server to the CILogon Service." ,
        "emailaddr" => "Your email address given by your Identity Provider." ,
        "ePPN" => "'eduPerson Principal Name' - a SAML attribute set by your Identity Provider." ,
        "ePTID" => "'eduPerson Targeted Identifier' - a SAML attribute set by your Identity Provider" ,
        "failureuri" => "A URL used by portals in case the CILogon Service is unable to issue a certificate on your behalf. " ,
        "firstname" => "Your given name set by your Identity Provider." ,
        "idpname" => "The display name of your chosen Identity Provider." ,
        "idp" => "The authentication URI of your chosen Identity Provider." ,
        "keepidp" => "Remember if you checked the 'Remember this selection' checkbox when you selected and Identity Provider." ,
        "lastname" => "Your surname set by your Identity Provider." ,
        "loa" => "Level of Assurance set by your Identity Provider." ,
        "logonerror" => "A text message of the reason for the last authentication error." ,
        "oidcID" => "Your user identifier set by the OpenID Connect Identity Provider." ,
        "openidID" => "Your user identifier set by the OpenID Identity Provider." ,
        "ou" => "Your organizational unit set by your Identity Provider." ,
        "p12error" => "A text message of the reason why the PKCS12 certificate could not be created." ,
        "p12lifetime" => "This multiplied by the p12multipler gives the lifetime of the PKCS12 certificate in hours." ,
        "p12multiplier" => "This multiplied by the p12lifetime gives the lifetime of the PKCS12 certificate in hours." ,
        "p12" => "The expiration time and URL to download a PKCS12 certificate file." ,
        "portalcookie" => "Contains certificate lifetimes for all portals you have used with the CILogon Delegate service." ,
        "portalname" => "The display name of the portal connected to the CILogon Delegate service. " ,
        "portalparams" => "For portals previously using the CILogon Delegate service, this is the saved lifetime of the delegated certificate." ,
        "portalstatus" => "An internal return code when fetching portal parameters from the datastore." ,
        "providerId" => "The previously selected Identity Provider." ,
        "requestsilver" => "Set to 1 if attempting to get a 'silver' Level of Assurance from your chosen Identity Provider." ,
        "responsesubmit" => "The name of the 'stage' to return to after authentication at your chosen Identity Provider." ,
        "responseurl" => "The URL to return to after authentication at your chosen Identity Provider." ,
        "_shibsession" => "A shibboleth session token set by an InCommon Identity Provider." ,
        "showhelp" => "Whether to show help text or not." ,
        "stage" => "The current page displayed." ,
        "status" => "An internal return code when fetching user data from the datastore." ,
        "submit" => "The name of the 'submit' button clicked." ,
        "successuri" => "A URL used by portals for redirection after successful issuance of a certificate." ,
        "tempcred" => "An OAUTH identifier used to track portal sessions." ,
        "twofactor" => "The types of two-factor authentication configured for your account, ga for Google Authenticator, duo for Duo Security." ,
        "uid" => "The datastore user identifier." ,
    );

    foreach ($explain as $key => $value) {
        if (preg_match("/$key/",$cookie)) {
            $retval = $value;
            break;
        }
    }

    return $retval;
}

?>
