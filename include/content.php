<?php

require_once("util.php");
require_once("autoloader.php");
require_once("Config.php");

/* If needed, set the "Notification" banner text to a non-empty value   */
/* and uncomment the "define" statement in order to display a           */
/* notification box at the top of each page.                            */
/*
define('BANNER_TEXT','The CILogon Service may be unavailable for short periods
    on Sunday November 21 between 5am and 8am Central Time
    due to University of Illinois network maintenance.');
*/

/* The full URL of the Shibboleth-protected and OpenID getuser scripts. */
define('GETUSER_URL','https://' . HOSTNAME . '/secure/getuser/');
define('GETOPENIDUSER_URL','https://' . HOSTNAME . '/getopeniduser/');

/* The csrf token object to set the CSRF cookie and print the hidden */
/* CSRF form element.  Be sure to do "global $csrf" to use it.       */
$csrf = new csrf();


/************************************************************************
 * Function   : printHeader                                             *
 * Parameter  : (1) The text in the window's titlebar                   *
 *              (2) Optional extra text to go in the <head> block       *
 * This function should be called to print out the main HTML header     *
 * block for each web page.  This gives a consistent look to the site.  *
 * Any style changes should go in the cilogon.css file.                 *
 ************************************************************************/
function printHeader($title='',$extra='')
{
    global $csrf;       // Initialized above
    $csrf->setTheCookie();
    // Set the CSRF cookie used by GridShib-CA
    setcookie('CSRFProtection',$csrf->getTokenValue(),0,'/','',true);

    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head><title>' , $title , '</title> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-XRDS-Location" 
          content="https://' , HOSTNAME , '/cilogon.xrds"/>
    <link rel="stylesheet" type="text/css" href="/include/cilogon.css" />
    ';

    printSkin();

    echo '<script type="text/javascript" src="/include/secutil.js"></script>
    <script type="text/javascript" src="/include/deployJava.js"></script>

    <!--[if IE]>
    <style type="text/css">
      body { behavior: url(/include/csshover3.htc); }
      .openiddrop ul li div { right: 0px; }
    </style>
    <![endif]-->
    ';

    if (strlen($extra) > 0) {
        echo $extra;
    }

    echo '
    </head>

    <body onload="init();">
    ';

    $skinvar = getSessionVar('cilogon_skin');
    if ((strlen($skinvar) > 0) &&
        (is_readable($_SERVER{'DOCUMENT_ROOT'} . "/skin/$skinvar/skin.css"))) {
        echo '
        <div class="skincilogonlogo">
        <a target="_blank" href="http://www.cilogon.org/faq/"><img
        src="/images/poweredbycilogon.png" alt="CILogon" 
        title="CILogon Service" /></a>
        </div>
        ';
    }

    echo '
    <div class="logoheader">
       <h1><span>[CILogon Service]</span></h1>
    </div>
    <div class="pagecontent">
     ';

    if ((defined('BANNER_TEXT')) && (strlen(BANNER_TEXT) > 0)) {
        echo '
        <div class="noticebanner">' , BANNER_TEXT , '</div>
        ';
    }
}

/************************************************************************
 * Function   : printFooter                                             *
 * Parameter  : (1) Optional extra text to be output before the closing *
 *                  footer div.                                         *
 * This function should be called to print out the closing HTML block   *
 * for each web page.                                                   *
 ************************************************************************/
function printFooter($footer='') 
{
    if (strlen($footer) > 0) {
        echo $footer;
    }

    echo '
    <br class="clear" />
    <div class="footer">
    <a target="_blank" href="http://www.cilogon.org/faq"><img
    src="/images/questionIcon.png" class="floatrightclear" 
    width="40" height="40" alt="CILogon FAQ" title="CILogon FAQ" /></a>
    <p>For questions about this site, please see the <a target="_blank"
    href="http://www.cilogon.org/faq">FAQs</a> or send email to <a
    href="mailto:help@cilogon.org">help&nbsp;@&nbsp;cilogon.org</a>.</p>
    <p>Know <a target="_blank"
    href="http://ca.cilogon.org/responsibilities">your responsibilities</a>
    for using the CILogon Service.</p>
    <p>This material is based upon work supported by
    the <a target="_blank" href="http://www.nsf.gov/">National Science
    Foundation</a> under grant number <a target="_blank"
    href="http://www.nsf.gov/awardsearch/showAward.do?AwardNumber=0943633">0943633</a>.</p>
    <p>Any opinions, findings and conclusions or recommendations expressed
    in this material are those of the author(s) and do not necessarily
    reflect the views of the National Science Foundation.</p>
    </div> <!-- Close "footer" div -->
    </div> <!-- Close "pagecontent" div -->
    </body>
    </html>
    ';
}

/************************************************************************
 * Function  : printPageHeader                                          *
 * Parameter : The text string to appear in the titlebox.               *
 * This function prints a fancy formatted box with a single line of     *
 * text, suitable for a titlebox on each web page (to appear just below *
 * the page banner at the very top).  It prints a gradent border around *
 * the four edges of the box and then outlines the inner box.           *
 ************************************************************************/
function printPageHeader($text) {
    echo '
    <div class="titlebox">' , $text , '
    </div>
    ';
}

/************************************************************************
 * Function   : printFormHead                                           *
 * Parameters : (1) The value of the form's "action" parameter.         *
 *              (2) (Optional) True if extra hidden tags should be      *
 *                  output for the GridShib-CA client application.      *
 *                  Defaults to false.                                  *
 * This function prints out the opening <form> tag for displaying       *
 * submit buttons.  The first parameter is used for the "action" value  *
 * of the <form>.  This function outputs a hidden csrf field in the     *
 * form block.  If the second parameter is given and set to true, then  *
 * additional hidden input elements are also output to be used when the *
 * the GridShib-CA client launches.                                     *
 ************************************************************************/
function printFormHead($action,$gsca=false) {
    global $csrf;

    echo '
    <form action="' , $action , '" method="post">
    ';
    echo $csrf->getHiddenFormElement();

    if ($gsca) {
        /* Output hidden form element for GridShib-CA */
        echo '
        <input type="hidden" name="CSRFProtection" value="' .
        $csrf->getTokenValue() . '" />
        ';
    }
}

/************************************************************************
 * Function   : printSkin                                               *
 * This function looks for a GET variable named either "skin" or        *
 * "cilogon_skin", or a PHP session variable named "cilogon_skin".  If  *
 * any of these is found, it checks to see if there is a skin           *
 * subdirectory of the same value containing a skin.css file.  If so,   *
 * it outputs the necessary <link> tag to include the stylesheet.  This *
 * function is called by the printHeader() function.                    *
 ************************************************************************/
function printSkin() 
{
    /* First, attempt to read the URL parameter for either 'skin=' or *
     * 'cilogon_skin='.  If we find either one, then set the PHP      *
     * session variable 'cilogon_skin' and continue processing.       */
    $skinvar = getGetVar('skin');
    if (strlen($skinvar) == 0) {
        $skinvar = getGetVar('cilogon_skin');
    }
    if (strlen($skinvar) > 0) {
        setOrUnsetSessionVar('cilogon_skin',$skinvar);
    }

    /* Check for the PHP session variable 'cilogon_skin'.  If found   *
     * verify that it points to a valid skin subdirectory containing  *
     * a skin.css file.  Then print out the corresponding <link> tag. */
    $skinvar = getSessionVar('cilogon_skin');
    if (strlen($skinvar) > 0) {
        if (is_readable($_SERVER{'DOCUMENT_ROOT'} . 
                        "/skin/$skinvar/skin.css")) {
            echo '
            <link rel="stylesheet" type="text/css" 
             href="/skin/' , $skinvar , '/skin.css" />
            ';
        } else { // Problem reading skin.css - delete PHP session variable
            unsetSessionVar('cilogon_skin');
        }
    }
}

/************************************************************************
 * Function   : printWAYF                                               *
 * This function prints the whitelisted IdPs in a <select> form element *
 * which can be printed on the main login page to allow the user to     *
 * select "Where Are You From?".  This function checks to see if a      *
 * cookie for the 'providerId' had been set previously, so that the     *
 * last used IdP is selected in the list.                               *
 ************************************************************************/
function printWAYF() 
{
    global $csrf;

    $whiteidpsfile = '/var/www/html/include/whiteidps.txt';
    $helptext = "Check this box to bypass the welcome page on subsequent visits and proceed directly to the selected identity provider. You will need to clear your browser's cookies to return here."; 

    $keepidp     = getCookieVar('keepidp');
    $providerId  = getCookieVar('providerId');
    if (strlen($providerId) == 0) {
        $providerId = openid::getProviderUrl('Google');
    }

    /* Try to read in a file containing a list of IdPs mapped to their */
    /* display names.  If the file is empty, read in the list of       */
    /* whitelisted IdPs (from file) and write out the mapping file.    */
    $idps = readArrayFromFile($whiteidpsfile);
    if (count($idps) == 0) {
        $incommon    = new incommon();
        $whitelist   = new whitelist();
        $idps        = $incommon->getOnlyWhitelist($whitelist);
        writeArrayToFile($whiteidpsfile,$idps);
    }

    /* Add the list of OpenID providers into the $idps array so as to  */
    /* have a single selection list.  Keys are the IdP identifiers,    */
    /* values are the provider display names, sorted by names.         */
    foreach (openid::$providerUrls as $url => $name) {
        $idps[$url] = $name;
    }
    natcasesort($idps);

    echo '
    <div class="actionbox">

      <form action="' , getScriptDir() , '" method="post">
      <fieldset>

      <p>
      Select An Identity Provider:<a target="_blank" 
          style="text-decoration:none"
          href="http://www.cilogon.org/selectidp">';

      printIcon('info','Help Me Choose');

      echo '</a>
      </p>

      <p>
      <select name="providerId" id="providerId">
    ';

    foreach ($idps as $entityId => $idpName) {
        echo '    <option value="' , $entityId , '"';
        if ($entityId == $providerId) {
            echo ' selected="selected"';
        }
        echo '>' , htmlentities($idpName) , '</option>' , "\n    ";
    }

    echo '  </select>
      </p>
    ';


    echo '
    <p>
    <label for="keepidp" title="' , $helptext , 
    '" class="helpcursor">Remember this selection:</label>
    <input type="checkbox" name="keepidp" id="keepidp" ' , 
    (($keepidp == 'checked') ? 'checked="checked" ' : '') ,
    'title="' , $helptext , '" class="helpcursor" />
    </p>

    <p>';

    echo $csrf->getHiddenFormElement();

    echo '
    <input type="submit" name="submit" class="submit helpcursor" 
    title="Proceed to the selected identity provider."
    value="Log On" />
    </p>
    ';

    $openiderror = getSessionVar('openiderror');
    if (strlen($openiderror) > 0) {
        echo "<p class=\"openiderror\">$openiderror</p>";
        unsetSessionVar('openiderror');
    }

    echo '
    </fieldset>

    <p class="privacypolicy">
    By selecting "Log On", you agree to our <a target="_blank" 
    href="http://ca.cilogon.org/policy/privacy">privacy policy</a>.
    </p>

    </form>
  </div>
  ';
}

/************************************************************************
 * Function  : printIcon                                                *
 * Parameters: (1) The prefix of the "...Icon.png" image to be shown.   *
 *                 E.g. to show "errorIcon.png", pass in "error".       *
 *             (2) The popup "title" text to be displayed when the      *
 *                 mouse cursor hovers over the icon.  Defaults to "".  *
 * This function prints out the HTML for the little icons which can     *
 * appear inline with other information.  This is accomplished via the  *
 * use of wrapping the image in a <span> tag.                           *
 ************************************************************************/
function printIcon($icon,$popuptext='')
{
    echo '<span';
    if (strlen($popuptext) > 0) {
        echo ' class="helpcursor" title="' , $popuptext , '"';
    }
    echo '>&nbsp;<img src="/images/' , $icon , 'Icon.png" 
          alt="&laquo; ' , ucfirst($icon) , '"
          width="14" height="14" /></span>';
}

/************************************************************************
 * Function   : verifyCurrentSession                                    *
 * Parameter  : (Optional) The user-selected Identity Provider          *
 * Returns    : True if the contents of the PHP session ar valid,       *
 *              False otherwise.                                        *
 * This function verifies the contents of the PHP session.  It checks   *
 * the following:                                                       *
 * (1) The persistent store 'uid', the Identity Provider 'idp', the     *
 *     IdP Display Name 'idpname', and the 'status' (of getUser()) are  *
 *     all non-empty strings.                                           *
 * (2) The 'status' (of getUser()) is even (i.e. STATUS_OK).            *
 * (3) If $providerId is passed-in, it must match 'idp'.                *
 * If all checks are good, then this function returns true.             *
 ************************************************************************/
function verifyCurrentSession($providerId='') 
{
    $retval = false;

    $uid     = getSessionVar('uid');
    $idp     = getSessionVar('idp');
    $idpname = getSessionVar('idpname');
    $status  = getSessionVar('status');
    $dn      = getSessionVar('dn');
    if ((strlen($uid) > 0) && (strlen($idp) > 0) && 
        (strlen($idpname) > 0) && (strlen($status) > 0) &&
        (strlen($dn) > 0) &&
        (!($status & 1))) {  // All STATUS_OK codes are even
        if ((strlen($providerId) == 0) || ($providerId == $idp)) {
            $retval = true;
        }
    }

    return $retval;
}

/************************************************************************
 * Function   : redirectToGetUser                                       *
 * Parameters : (1) An entityID of the authenticating IdP.  If not      *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 *              (2) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 * If the first parameter (a whitelisted entityID) is not specified,    *
 * we check to see if either the providerId PHP session variable or the *
 * providerId cookie is set (in that order) and use one if available.   *
 * The function then checks to see if there is a valid PHP session      *
 * and if the providerId matches the 'idp' in the session.  If so, then *
 * we don't need to redirect to "/secure/getuser/" and instead we       *
 * we display the main page.  However, if the PHP session is not valid, *
 * then this function redirects to the "/secure/getuser/" script so as  *
 * to do a Shibboleth authentication via the InCommon WAYF.  When the   *
 * providerId is non-empty, the WAYF will automatically go to that IdP  *
 * (i.e. without stopping at the WAYF).  This function also sets        *
 * several PHP session variables that are needed by the getuser script, *
 * including the 'responsesubmit' variable which is set as the return   *
 * 'submit' variable in the 'getuser' script.                           *
 ************************************************************************/
function redirectToGetUser($providerId='',$responsesubmit='gotuser')
{
    global $csrf;
    global $log;

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = getCookieVar('providerId');
        }
    }

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the main page.
    if (verifyCurrentSession($providerId)) {
        printMainPage();
    } else { // Otherwise, redirect to the getuser script
        // Set PHP session varilables needed by the getuser script
        setOrUnsetSessionVar('responseurl',getScriptDir(true));
        setOrUnsetSessionVar('submit','getuser');
        setOrUnsetSessionVar('responsesubmit',$responsesubmit);
        $csrf->setTheCookie();
        $csrf->setTheSession();

        // Set up the "header" string for redirection thru InCommon WAYF
        $redirect = 
            'Location: https://' . HOSTNAME . '/Shibboleth.sso/WAYF/InCommon?' .
            'target=' . urlencode(GETUSER_URL);
        if (strlen($providerId) > 0) {
            $redirect .= '&providerId=' . urlencode($providerId);
        }

        $log->info('Shibboleth Login="' . $redirect . '"');
        header($redirect);
    }
}

/************************************************************************
 * Function   : redirectToGetOpenIDUser                                 *
 * Parameters : (1) An OpenID provider name. See the $providerarray in  *
 *                  the openid.php class for a full list. If not        *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 *              (2) (Optional) The username to replace the string       *
 *                  'username' in the OpenID URL (if necessary).        *
 *                  Defaults to 'username'.                             *
 *              (3) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 * This method redirects control flow to the getopeniduser script for   *
 * when the user logs in via OpenID.  It first checks to see if we have *
 * a valid session.  If so, we don't need to redirect and instead       *
 * simply show the Get Certificate page.  Otherwise, we start an OpenID *
 * logon by using the PHP / OpenID library.  First, connect to the      *
 * PostgreSQL database to store temporary tokens used by OpenID upon    *
 * successful authentication.  Next, create a new OpenID consumer and   *
 * attempt to redirect to the appropriate OpenID provider.  Upon any    *
 * error, set the 'openiderror' PHP session variable and redisplay the  *
 * main logon screen.                                                   *
 ************************************************************************/
function redirectToGetOpenIDUser($providerId='',$responsesubmit='gotuser') 
{
    global $csrf;
    global $log;

    $openiderrorstr = 'Internal OpenID error. Please try logging in with Shibboleth.';

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = getCookieVar('providerId');
        }
    }

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the 'Download Certificate' button page.
    if (verifyCurrentSession($providerId)) {
        printMainPage();
    } else { // Otherwise, redirect to the getopeniduser script
        // Set PHP session varilables needed by the getopeniduser script
        unsetSessionVar('openiderror');
        setOrUnsetSessionVar('responseurl',getScriptDir(true));
        setOrUnsetSessionVar('submit','getuser');
        setOrUnsetSessionVar('responsesubmit',$responsesubmit);
        $csrf->setTheCookie();
        $csrf->setTheSession();

        $auth_request = null;
        $openid = new openid();
        $datastore = $openid->getStorage();

        if ($datastore == null) {
            setOrUnsetSessionVar('openiderror',$openiderrorstr);
        } else {
            $consumer = new Auth_OpenID_Consumer($datastore);
            $auth_request = $consumer->begin($providerId);

            if (!$auth_request) {
                setOrUnsetSessionVar('openiderror',$openiderrorstr);
            } else {
                if ($auth_request->shouldSendRedirect()) {
                    $redirect_url = $auth_request->redirectURL(
                        'https://' . HOSTNAME . '/',
                        GETOPENIDUSER_URL);
                    if (Auth_OpenID::isFailure($redirect_url)) {
                        setOrUnsetSessionVar('openiderror',$openiderrorstr);
                    } else {
                        $log->info('OpenID Login="' . $providerId . '"');
                        header("Location: " . $redirect_url);
                    }
                } else {
                    $form_id = 'openid_message';
                    $form_html = $auth_request->htmlMarkup(
                        'https://' . HOSTNAME . '/',
                        GETOPENIDUSER_URL,
                        false, array('id' => $form_id));
                    if (Auth_OpenID::isFailure($form_html)) {
                        setOrUnsetSessionVar('openiderror',$openiderrorstr);
                    } else {
                        $log->info('OpenID Login="' . $providerId . '"');
                        print $form_html;
                    }
                }

                $openid->disconnect();
            }
        }

        if (strlen(getSessionVar('openiderror')) > 0) {
            printLogonPage();
        }
    }
}

/************************************************************************
 * Function   : printErrorBox                                           *
 * Parameter  : HTML error text to be output.                           *
 * This function prints out a bordered box with an error icon and any   *
 * passed-in error HTML text.  The error icon and text are output to    *
 * a <table> so as to keep the icon to the left of the error text.      *
 ************************************************************************/
function printErrorBox($errortext) 
{
    echo '
    <div class="errorbox">
    <table cellpadding="5">
    <tr>
    <td>
    ';
    printIcon('error');
    echo '&nbsp;
    </td>
    <td> ' , $errortext , '
    </td>
    </tr>
    </table>
    </div>
    ';
}

/************************************************************************
 * Function   : unsetGetUserSessionVars                                 *
 * This function removes all of the PHP session variables related to    *
 * the getuser scripts.  This will force the user to log on (again)     *
 * with their IdP and call the 'getuser' script to repopulate the PHP   *
 * session.                                                             *
 ************************************************************************/
function unsetGetUserSessionVars()
{
    unsetSessionVar('submit');
    unsetSessionVar('uid');
    unsetSessionVar('status');
    unsetSessionVar('loa');
    unsetSessionVar('idp');
    unsetSessionVar('idpname');
    unsetSessionVar('dn');
    unsetSessionVar('tokenvalue');
    unsetSessionVar('tokenexpire');
    unsetSessionVar('activation');
    unsetSessionVar('pkcs12');
}

/************************************************************************
 * Function   : unsetPortalSessionVars                                  *
 * This function removes all of the PHP session variables related to    *
 * portal delegation.                                                   *
 ************************************************************************/
function unsetPortalSessionVars()
{
    unsetSessionVar('portalstatus');
    unsetSessionVar('callbackuri');
    unsetSessionVar('successuri');
    unsetSessionVar('failureuri');
    unsetSessionVar('portalname');
    unsetSessionVar('tempcred');
    unsetSessionVar('dn');
}

/************************************************************************
 * Function   : handleGotUser                                           *
 * This function is called upon return from one of the getuser scripts  *
 * which should have set the 'uid' and 'status' PHP session variables.  *
 * It verifies that the status return is one of STATUS_OK (even         *
 * values).  If the return is STATUS_OK then it checks if we have a     *
 * new or changed user and prints that page as appropriate.  Otherwise  *
 * it continues to the MainPage.                                        *
 ************************************************************************/
function handleGotUser()
{
    global $log;

    $uid = getSessionVar('uid');
    $status = getSessionVar('status');
    # If empty 'uid' or 'status' or odd-numbered status code, error!
    if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
        $log->error('Failed to getuser.');

        unsetGetUserSessionVars();
        printHeader('Error Logging On');

        echo '
        <div class="boxed">
        ';
        printErrorBox('An internal error has occurred.  System
            administrators have been notified.  This may be a temporary
            error.  Please try again later, or contact us at the the email
            address at the bottom of the page.');

        echo '
        <div>
        ';
        printFormHead(getScriptDir());
        echo '
        <input type="submit" name="submit" class="submit" value="Continue" />
        </form>
        </div>
        </div>
        ';
        printFooter();
    } else { // Got one of the STATUS_OK status codes
        // If the user got a new DN due to changed SAML attributes,
        // print out a notification page.
        if ($status == dbservice::$STATUS['STATUS_NEW_USER']) {
            printNewUserPage();
        } elseif ($status == dbservice::$STATUS['STATUS_USER_UPDATED']) {
            printUserChangedPage();
        } else { // STATUS_OK
            printMainPage();
        }
    }
}

/************************************************************************
 * Function   : printNewUserPage                                        *
 * This function prints out a notification page to new users showing    *
 * that this is the first time they have logged in with a particular    *
 * identity provider.                                                   *
 ************************************************************************/
function printNewUserPage()
{
    global $log;

    $log->info('New User page.');

    printHeader('New User');

    echo '
    <div class="boxed">
    <br class="clear"/>
    <p>
    Welcome! Your new certificate subject is as follows. 
    </p>
    <p>
    <blockquote><tt>' , getSessionVar('dn') , '</tt></blockquote>
    </p>
    <p>
    You may need to register this certificate subject with relying parties.
    </p>
    <p>
    You will not see this page again unless the CILogon Service assigns you
    a new certificate subject.  This may occur in the following situations:
    </p>
    <ul>
    <li>You log on to the CILogon Service using an identity provider other
    than ' , getSessionVar('idpname') , '.
    </li>
    <li>You log on using a different ' , getSessionVar('idpname') , '
    identity.
    </li>
    <li>The CILogon Service has experienced an internal error.
    </li>
    </ul>
    <p>
    Click the "Proceed" button to continue.  If you have any questions,
    please contact us at the email address at the bottom of the page.
    </p>
    <div>
    ';
    printFormHead(getScriptDir());
    echo '
    <p class="centered">
    <input type="submit" name="submit" class="submit" value="Proceed" />
    </p>
    </form>
    </div>
    </div>
    ';
    printFooter();
}

/************************************************************************
 * Function   : printUserChangedPage                                    *
 * This function prints out a notification page informing the user that *
 * some of their attributes have changed, which will affect the         *
 * contents of future issued certificates.  This page shows which       *
 * attributes are different (displaying both old and new values) and    *
 * what portions of the certificate are affected.                       *
 ************************************************************************/
function printUserChangedPage()
{
    global $log;

    $log->info('User IdP attributes changed.');

    $uid = getSessionVar('uid');
    $dbs = new dbservice();
    $dbs->getUser($uid);
    if (!($dbs->status & 1)) {  // STATUS_OK codes are even
        $idpname = $dbs->idp_display_name;
        $first   = $dbs->first_name;
        $last    = $dbs->last_name;
        $email   = $dbs->email;
        $dn      = $dbs->distinguished_name;
        $dn      = preg_replace('/\s+email=.+$/','',$dn);
        $dbs->getLastArchivedUser($uid);
        if (!($dbs->status & 1)) {  // STATUS_OK codes are even
            $previdpname = $dbs->idp_display_name;
            $prevfirst   = $dbs->first_name;
            $prevlast    = $dbs->last_name;
            $prevemail   = $dbs->email;
            $prevdn      = $dbs->distinguished_name;
            $prevdn      = preg_replace('/\s+email=.+$/','',$prevdn);

            $tablerowodd = true;

            printHeader('Certificate Information Changed');

            echo '
            <div class="boxed">
            <br class="clear"/>
            <p>
            One or more of the attributes released by your organization has
            changed since the last time you logged on to the CILogon
            Service.  This will affect your certificates as described below.
            </p>

            <div class="userchanged">
            <table cellpadding="5">
              <tr class="headings">
                <th>Attribute</th>
                <th>Previous Value</th>
                <th>Current Value</th>
              </tr>
            ';

            if ($idpname != $previdpname) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Organization Name:</th>
                  <td>'.$previdpname.'</td>
                  <td>'.$idpname.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($first != $prevfirst) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>First Name:</th>
                  <td>'.$prevfirst.'</td>
                  <td>'.$first.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($last != $prevlast) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Last Name:</th>
                  <td>'.$prevlast.'</td>
                  <td>'.$last.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($email != $prevemail) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Email Address:</th>
                  <td>'.$prevemail.'</td>
                  <td>'.$email.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            echo '
            </table>
            </div>
            ';

            if (($idpname != $previdpname) ||
                ($first != $prevfirst) ||
                ($last != $prevlast)) {
                echo '
                <p>
                The above changes to your attributes will cause your
                <strong>certificate subject</strong> to change.  You may be
                required to re-register with relying parties using this new
                certificate subject.
                </p>
                <p>
                <blockquote>
                <table cellspacing="0">
                  <tr>
                    <td>Previous Subject DN:</td>
                    <td>' , $prevdn , '</td>
                  </tr>
                  <tr>
                    <td>Current Subject DN:</td>
                    <td>' , $dn , '</td>
                  </tr>
                </table>
                </blockquote>
                </p>
                ';
            }

            if ($email != $prevemail) {
                echo '
                <p>
                Your new certificate will contain your <strong>updated email
                address</strong>.
                This may change how your certificate may be used in email
                clients.  Possible problems which may occur include:
                </p>
                <ul>
                <li>If your "from" address does not match what is contained in
                    the certificate, recipients may fail to verify your signed
                    email messages.</li>
                <li>If the email address in the certificate does not match the
                    destination address, senders may have difficulty encrypting
                    email addressed to you.</li>
                </ul>
                ';
            }

            echo '
            <p>
            If you have any questions, please contact us at the email
            address at the bottom of the page.
            </p>
            <div>
            ';
            printFormHead(getScriptDir());
            echo '
            <p class="centered">
            <input type="submit" name="submit" class="submit" value="Proceed" />
            </p>
            </form>
            </div>
            </div>
            ';
            printFooter();

            
        } else {  // Database error, should never happen
            $log->error('Database error reading previous user attributes.');
            unsetGetUserSessionVars();
            printLogonPage();
        }
    } else {  // Database error, should never happen
        $log->error('Database error reading current user attributes.');
        unsetGetUserSessionVars();
        printLogonPage();
    }
}

?>
