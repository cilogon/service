<?php

require_once("../include/autoloader.php");
require_once("../include/util.php");

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
    <head><title>' . $title . '</title> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-XRDS-Location" 
          content="https://cilogon.org/cilogon.xrds"/>
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
    <p>The <a target="_blank"
    href="http://www.cilogon.org/service">CILogon Service</a> is funded by
    the <a target="_blank" href="http://www.nsf.gov/">National Science
    Foundation</a> under grant number <a target="_blank"
    href="http://www.nsf.gov/awardsearch/showAward.do?AwardNumber=0943633">0943633</a>.</p>
    <p>This site uses software from the <a target="_blank"
    href="http://myproxy.ncsa.uiuc.edu/">MyProxy</a> and <a target="_blank"
    href="http://gridshib.globus.org/">GridShib</a> projects.</p>
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
    echo '
    <div class="t">
    <div class="b">
    <div class="l">
    <div class="r">
    <div class="titlebox">' . $text . '</div>
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

    $incommon   = new incommon();
    $whitelist  = new whitelist();
    $idps       = $incommon->getOnlyWhitelist($whitelist);
    $providerId = getCookieVar('providerId');
    $keepidp    = getCookieVar('keepidp');
    $useopenid  = getCookieVar('useopenid');
    $username   = getCookieVar('username');
    if (strlen($username) == 0) {
        $username = 'username';
    }

    $helptext = "By checking this box, you can bypass the welcome page on subsequent visits and proceed directly to your organization's authentication site. You will need to clear your brower's cookies to return here."; 
    $insteadtext = "By clicking this link, you change the type of authentication used for the CILogon Service. You can select either InCommon or OpenID authentication.";

    echo '
    <div class="wayf">
      <div class="boxheader">
        Start Here
      </div>

      <form action="' . getScriptDir() . '" method="post">
      <fieldset>

      <div id="starthere1" style="display:' . 
      (($useopenid == '1') ? 'none' : 'inline') . 
      '">
      <p>
      Select An InCommon Organization:
      </p>
      <div class="providerselection">
      <select name="providerId" id="providerId">
    ';

    foreach ($idps as $entityId => $idpName) {
        echo '<option value="' . $entityId . '"';
        if ($entityId == $providerId) {
            echo ' selected="selected"';
        }
        echo '>' . $idpName . '</option>' . "\n";
    }

    echo '
      </select>
      </div>
      </div>

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
      </div>

      <div id="starthere2" style="display:' . 
      (($useopenid == '1') ? 'inline' : 'none') . 
      '">
      <p>
      Select An OpenID Provider:
      </p>
      <div class="providerselection">
      <table class="openidtable">
        <col width="85%" />
        <col width="15%" />
        <tr>
        <th id="openidurl">
        ';

        if ($useopenid == '1') {
            $openid = new openid($providerId,$username);
            echo $openid->getInputTextURL();
        } else {
            echo '          http://<input type="text" name="username" 
              size="9" value="username" id="openidusername"
              onfocus="setInterval(\'boxExpand()\',1)" />';
        }

        echo '
        </th>
        <td class="openiddrop">
        <ul>
          <li><h3><img id="currentopenidicon" src=" ' . 
               (($useopenid == '1') ? '/images/' . 
                                      strtolower($providerId) . '.png' : 
                                      '/images/openid.png') . 
               '" width="16" height="16" alt="' . 
               (($useopenid == '1') ? $providerId : 'OpenID') . 
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
            </tr>
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

      <p>
      <label for="keepidp" title="' . $helptext . 
      '" class="helpcursor">Remember this selection:</label>
      <input type="checkbox" name="keepidp" id="keepidp" ' . 
      (($keepidp == 'checked') ? 'checked="checked" ' : '') .
      'title="' .  $helptext . '" class="helpcursor" />
      </p>
      <p>
      ' .  $csrf->getHiddenFormElement() . '
      <input type="hidden" name="useopenid" id="useopenid" value="' . 
      (($useopenid == '1') ? '1' : '0') . '"/>
      <input type="hidden" name="hiddenopenid" id="hiddenopenid" value="' .
      (($useopenid == '1') ? $providerId: 'OpenID') .  '"/>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="Click to proceed to your selected organization\'s login page."
      value="Log On" />
      </p>

      <div id="starthere3" style="display:' . 
      (($useopenid == '1') ? 'none' : 'inline') . 
      '">
      <p>
      <a title="'.$insteadtext.'" class="smaller"
        href="javascript:showHideDiv(\'starthere\',-1);
        document.getElementById(\'openidusername\').focus();
        document.getElementById(\'openidusername\').select();
        useOpenID(\'1\')">Use OpenID instead</a>
      </p>

      <noscript>
      <div class="nojs">
      Javascript is disabled.  In order to log on with OpenID, please
      enable Javascript in your browser.
      </div>
      </noscript>
      </div>

      <div id="starthere4" style="display:'.
      (($useopenid == '1') ? 'inline' : 'none') . 
      '">
      <p>
      <a title="'.$insteadtext.'" class="smaller"
        href="javascript:showHideDiv(\'starthere\',-1);
        useOpenID(\'0\')">Use InCommon instead</a>
      </p>
      </div>

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
    echo '><img src="/images/' . $icon . 'Icon.png" 
          alt="&laquo; ' . ucfirst($icon) . '" ';
    if (strlen($popuptext) > 0) {
        echo 'title="'. $popuptext . '" ';
    }
    echo 'width="14" height="14" /></span>';
}

?>