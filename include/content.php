<?php

require_once("util.php");
require_once("autoloader.php");

/* The full URL of the Shibboleth-protected and OpenID getuser scripts. */
define('GETUSER_URL','https://' . HOSTNAME . '/secure/getuser/');
define('GETOPENIDUSER_URL','https://' . HOSTNAME . '/getopeniduser/');

/* The csrf token object to set the CSRF cookie and print the hidden */
/* CSRF form element.  Be sure to do "global $csrf" to use it.       */
$csrf = new csrf();

/* Do GridShibCA perl stuff first so we can set the cookie (which    */
/* must be done before any HTML can be output) and eventually print  */
/* the CSRF value to a hidden form element.  Be sure to do           */
/* "global $perl_config" / "global $perl_csrf" if using the          */
/* variables from within a function.                                 */
$perl = new Perl();
$perl->eval("BEGIN {unshift(@INC,'/usr/local/gridshib-ca-2.0.0/perl');}");
$perl->eval('use GridShibCA::Config;');
$perl_config = new Perl('GridShibCA::Config');
$perl_csrf = $perl_config->getCSRF();


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
    global $perl_csrf; 
    $csrf->setTheCookie();
    setcookie($perl_csrf->TokenName,$perl_csrf->Token,0,'/','',true);

    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head><title>' , $title , '</title> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-XRDS-Location" 
          content="https://' , HOSTNAME , '/cilogon.xrds"/>
    <link rel="stylesheet" type="text/css" href="/include/cilogon.css" />
    <script type="text/javascript" src="/include/secutil.js"></script>
    <script type="text/javascript" src="/include/openid.js"></script>

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

    <body>
    <div class="logoheader">
       <h1><span>[Icon]</span></h1>
       <h2><span>CILogon Service</span><span 
           class="raised">CILogon Service</span></h2>
    </div>
    <div class="pagecontent">
     ';
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
    <p>Know <a target="_blank"
    href="http://ca.cilogon.org/responsibilities">your responsibilities</a>
    for using the CILogon Service.</p>
    <p>This material is based upon work supported by
    the <a target="_blank" href="http://www.nsf.gov/">National Science
    Foundation</a> under grant number <a target="_blank"
    href="http://www.nsf.gov/awardsearch/showAward.do?AwardNumber=0943633">0943633</a>.</p>
    <p>Any opinions, findings and conclusions or recomendations expressed
    in this material are those of the author(s) and do not necessarily
    reflect the views of the National Science Foundation.</p>
    <p>Please send any questions or comments about this
    site to <a
    href="mailto:help@cilogon.org">help&nbsp;@&nbsp;cilogon.org</a>.</p>
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
/*
    echo '
    <div class="noticebanner">
    The CILogon Service is in beta testing.
    Unscheduled service outages may occur.
    The next scheduled outage is <em>Friday, July 23</em>.
    </div>
    ';
*/

    echo '
    <div class="t">
    <div class="b">
    <div class="l">
    <div class="r">
    <div class="titlebox">' , $text , '</div>
    </div>
    </div>
    </div>
    </div>
    ';
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

    $incommon    = new incommon();
    $whitelist   = new whitelist();
    $idps        = $incommon->getOnlyWhitelist($whitelist);
    $providerId  = getCookieVar('providerId');
    $keepidp     = getCookieVar('keepidp');
    $useopenid   = getCookieVar('useopenid');
    $username    = getCookieVar('username');
    if (strlen($username) == 0) {
        $username = 'username';
    }
    $openid = new openid();
    if ($useopenid == '1') {
        $openid->setProvider($providerId);
        $openid->setUsername($username);
    }

    $helptext = "By checking this box, you can bypass the welcome page on subsequent visits and proceed directly to your organization's authentication site. You will need to clear your brower's cookies to return here."; 
    $insteadtext = "By clicking this link, you change the type of authentication used for the CILogon Service. You can select either InCommon or OpenID authentication.";

    echo '
    <div class="wayf">
      <div class="boxheader">
        Start Here
      </div>

      <form action="' , getScriptDir() , '" method="post">
      <fieldset>

      <div id="starthere1" style="display:';

      if ($useopenid == '1') {
          echo 'none">';
      } else {
          echo 'inline">';
      }

      echo '
      <p>
      Select An InCommon Organization:
      </p>
      <div class="providerselection">
      <select name="providerId" id="providerId">
    ';

    foreach ($idps as $entityId => $idpName) {
        echo '<option value="' , $entityId , '"';
        if ($entityId == $providerId) {
            echo ' selected="selected"';
        }
        echo '>' , $idpName , '</option>' , "\n";
    }

    echo '
      </select>
      </div>
      </div>
      ';

    echo '
      <!-- Preload all OpenID icons -->
      <div class="zeroheight">
        <div class="aolicon"></div>
        <div class="hyvesicon"></div>
        <div class="netlogicon"></div>
        <div class="bloggericon"></div>
        <div class="launchpadicon"></div>
        <div class="oneloginicon"></div>
        <div class="certificaicon"></div>
        <div class="liquididicon"></div>
        <div class="openidicon"></div>
        <div class="chimpicon"></div>
        <div class="livejournalicon"></div>
        <div class="verisignicon"></div>
        <div class="clavidicon"></div>
        <div class="myidicon"></div>
        <div class="voxicon"></div>
        <div class="flickricon"></div>
        <div class="myopenidicon"></div>
        <div class="wordpressicon"></div>
        <div class="getopenidicon"></div>
        <div class="myspaceicon"></div>
        <div class="yahooicon"></div>
        <div class="googleicon"></div>
        <div class="myvidoopicon"></div>
        <div class="yiidicon"></div>
      </div>';

      echo '
      <div id="starthere2" style="display:';

      if ($useopenid == '1') {
          echo 'inline">';
      } else {
          echo 'none">';
      }

      echo '
      <p>
      Select An OpenID Provider:
      </p>
      <div class="providerselection">
      <table class="openidtable">
        <col width="85%" />
        <col width="15%" />
        <tr>
        <th id="openidurl">';

      echo $openid->getInputTextURL();

      echo '
        </th>
        <td class="openiddrop">
        <ul>
          <li><h3><img id="currentopenidicon" src=" ' , 
               '/images/' , strtolower($openid->getProvider()) , '.png' ,
               '" width="16" height="16" alt="' , $openid->getProvider() ,
               '"/><img src="/images/droparrow.png" 
               width="8" height="16" alt="&dArr;"/></h3>
          <table class="providertable">
            <tr>
              <td class="aolicon"><a 
                href="javascript:selectOID(\'AOL\')">AOL</a></td>
              <td class="hyvesicon"><a 
                href="javascript:selectOID(\'Hyves\')">Hyves</a></td>
              <td class="netlogicon"><a 
                href="javascript:selectOID(\'NetLog\')">NetLog</a></td>
            </tr>
            <tr>
              <td class="bloggericon"><a 
                href="javascript:selectOID(\'Blogger\')">Blogger</a></td>
              <td class="launchpadicon"><a 
                href="javascript:selectOID(\'LaunchPad\')">LaunchPad</a></td>
              <td class="oneloginicon"><a 
                href="javascript:selectOID(\'OneLogin\')">OneLogin</a></td>
            </tr>
            <tr>
              <td class="certificaicon"><a 
                href="javascript:selectOID(\'certifi.ca\')">certifi.ca</a></td>
              <td class="liquididicon"><a 
                href="javascript:selectOID(\'LiquidID\')">LiquidID</a></td>
              <td class="openidicon"><a 
                href="javascript:selectOID(\'OpenID\')">OpenID</a></td>
            </tr>';

      echo '
            <tr>
              <td class="chimpicon"><a 
                href="javascript:selectOID(\'Chi.mp\')">Chi.mp</a></td>
              <td class="livejournalicon"><a 
                href="javascript:selectOID(\'LiveJournal\')">LiveJournal</a></td>
              <td class="verisignicon"><a 
                href="javascript:selectOID(\'Verisign\')">Verisign</a></td>
            </tr>
            <tr>
              <td class="clavidicon"><a 
                href="javascript:selectOID(\'clavid\')">clavid</a></td>
              <td class="myidicon"><a 
                href="javascript:selectOID(\'myID\')">myID</a></td>
              <td class="voxicon"><a 
                href="javascript:selectOID(\'Vox\')">Vox</a></td>
            </tr>
            <tr>
              <td class="flickricon"><a 
                href="javascript:selectOID(\'Flickr\')">Flickr</a></td>
              <td class="myopenidicon"><a 
                href="javascript:selectOID(\'myOpenID\')">myOpenID</a></td>
              <td class="wordpressicon"><a 
                href="javascript:selectOID(\'WordPress\')">WordPress</a></td>
            </tr>
            <tr>
              <td class="getopenidicon"><a 
                href="javascript:selectOID(\'GetOpenID\')">GetOpenID</a></td>
              <td class="myspaceicon"><a 
                href="javascript:selectOID(\'MySpace\')">MySpace</a></td>
              <td class="yahooicon"><a 
                href="javascript:selectOID(\'Yahoo\')">Yahoo</a></td>
            </tr>
            <tr>
              <td class="googleicon"><a 
                href="javascript:selectOID(\'Google\')">Google</a></td>
              <td class="myvidoopicon"><a 
                href="javascript:selectOID(\'myVidoop\')">myVidoop</a></td>
              <td class="yiidicon"><a 
                href="javascript:selectOID(\'Yiid\')">Yiid</a></td>
            </tr>
            <tr>
              <td colspan="3" class="centered"><a 
                target="_blank"
                href="https://www.myopenid.com/signup">Don\'t have an
                OpenID? Get one!</a></td>
            </tr>
          </table>
          </li>
        </ul>
        </td>
        </tr>
      </table>
      </div>
      </div>
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
      <input type="hidden" name="useopenid" id="useopenid" value="' , 
      (($useopenid == '1') ? '1' : '0') , '"/>
      <input type="hidden" name="hiddenopenid" id="hiddenopenid" value="' ,
      $openid->getProvider() , '"/>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="Click to proceed to your selected organization\'s login page."
      value="Log On" />
      </p>

      <div id="starthere3" style="display:';

      if ($useopenid == '1') {
          echo 'none">';
      } else {
          echo 'inline">';
      }

      echo '
      <p>
      ';

      echo '
      <a title="'.$insteadtext.'" class="smaller"
        href="javascript:showHideDiv(\'starthere\',-1); useOpenID(\'1\')">Use OpenID instead</a>
      ';

      echo '
      </p>
      </div>

      <div id="starthere4" style="display:';

      if ($useopenid == '1') {
          echo 'inline">';
      } else {
          echo 'none">';
      }

      echo '
      <p>
      ';

      echo '
      <a title="'.$insteadtext.'" class="smaller"
        href="javascript:showHideDiv(\'starthere\',-1); useOpenID(\'0\')">Use InCommon instead</a>
      ';

      echo '
      </p>
      </div>

      <noscript>
      <div class="nojs">
      Javascript is disabled.  OpenID authentication requires that
      Javascript be enabled in your browser.
      </div>
      </noscript>
      ';

      $openiderror = getSessionVar('openiderror');
      if (strlen($openiderror) > 0) {
          echo "<div class=\"openiderror\">$openiderror</div>";
          unsetSessionVar('openiderror');
      }

      echo '
      </fieldset>
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
    echo '&nbsp;<span';
    if (strlen($popuptext) > 0) {
        echo ' class="helpcursor"';
    }
    echo '><img src="/images/' , $icon , 'Icon.png" 
          alt="&laquo; ' , ucfirst($icon) , '" ';
    if (strlen($popuptext) > 0) {
        echo 'title="' , $popuptext , '" ';
    }
    echo 'width="14" height="14" /></span>';
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
 * (2) The 'status' (of getUser()) is even (i.e. STATUS_OK_*).          *
 * (3) If $providerId is passed-in, it must match 'idp'.                *
 * If all checks are good, then this function returns true.             *
 ************************************************************************/
function verifyCurrentSession($providerId='') 
{
    $retval = false;

    $uid = getSessionVar('uid');
    $idp = getSessionVar('idp');
    $idpname = getSessionVar('idpname');
    $status = getSessionVar('status');
    if ((strlen($uid) > 0) && (strlen($idp) > 0) && 
        (strlen($idpname) > 0) && (strlen($status) > 0) &&
        (!($status & 1))) {  // All STATUS_OK_* codes are even
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
        $_SESSION['responseurl'] = getScriptDir(true);
        $_SESSION['submit'] = 'getuser';
        $_SESSION['responsesubmit'] = $responsesubmit;
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
function redirectToGetOpenIDUser($providerId='',$username='username',
                                 $responsesubmit='gotuser') 
{
    global $csrf;
    global $log;
    global $openid;

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
        $_SESSION['responseurl'] = getScriptDir(true);
        $_SESSION['submit'] = 'getuser';
        $_SESSION['responsesubmit'] = $responsesubmit;
        $csrf->setTheCookie();
        $csrf->setTheSession();

        $auth_request = null;
        $openid->setProvider($providerId);
        $openid->setUsername($username);
        $datastore = $openid->getStorage();

        if ($datastore == null) {
            $_SESSION['openiderror'] = $openiderrorstr;
        } else {
            $consumer = new Auth_OpenID_Consumer($datastore);
            $auth_request = $consumer->begin($openid->getURL());
        }

        if (!$auth_request) {
            $_SESSION['openiderror'] = $openiderrorstr;
        } else {
            if ($auth_request->shouldSendRedirect()) {
                $redirect_url = $auth_request->redirectURL(
                    'https://' . HOSTNAME . '/',
                    'https://' . HOSTNAME . '/getopeniduser/');
                if (Auth_OpenID::isFailure($redirect_url)) {
                    $_SESSION['openiderror'] = $openiderrorstr;
                } else {
                    $log->info('OpenID Login="' . $providerId . '"');
                    header("Location: " . $redirect_url);
                }
            } else {
                $form_id = 'openid_message';
                $form_html = $auth_request->htmlMarkup(
                    'https://' . HOSTNAME . '/',
                    'https://' . HOSTNAME . '/getopeniduser/',
                    false, array('id' => $form_id));
                if (Auth_OpenID::isFailure($form_html)) {
                    $_SESSION['openiderror'] = $openiderrorstr;
                } else {
                    $log->info('OpenID Login="' . $providerId . '"');
                    print $form_html;
                }
            }

            $openid->disconnect();
        }

        if (strlen(getSessionVar('openiderror')) > 0) {
            printLogonPage();
        }
    }
}


?>
