<?php

require_once("autoloader.php");
require_once("util.php");

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

    $incommon = new incommon();
    $whitelist = new whitelist();
    $idps = $incommon->getOnlyWhitelist($whitelist);
    $providerId = getCookieVar('providerId');
    $keepidp = getCookieVar('keepidp');

    $helptext = "By checking this box, you can bypass the welcome page on subsequent visits and proceed directly to your organization's authentication site. You will need to clear your brower's cookies to return here."; 

    echo '
    <div class="wayf">
      <div class="boxheader">
        Start Here
      </div>
      <form action="' . getScriptDir() . 
      '" method="post" class="wayfForm">
      <fieldset>
      <p>
      <label for="providerId" class="ontop">Select An Organization:</label>
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
      </p>
      <p>
      <label for="keepidp" title="' . $helptext . 
      '" class="helpcursor">Remember this selection:</label>
      <input type="checkbox" name="keepidp" id="keepidp" ' . 
      (($keepidp == 'checked') ? 'checked="checked" ' : '') .
      'title="' .  $helptext . '" class="helpcursor" />
      </p>
      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="Click to proceed to your selected organization\'s login page."
      value="Log On" />
      </p>
      </fieldset>
      ' .  $csrf->getHiddenFormElement() . '
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
