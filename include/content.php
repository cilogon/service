<?php

require_once("autoloader.php");
require_once("util.php");

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
    global $csrf;
    $csrf->setTheCookie();

    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head><title>' . $title . '</title> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="/include/cilogon.css" />
    <script type="text/javascript" src="/include/secutil.js"></script>
    ';

    if (strlen($extra) > 0) {
        echo $extra;
    }

    echo '
    </head>

    <body id="cilogon-org">
    <div id="containter">
    <div id="logoHeader">
       <h1><span>[Icon]</span></h1>
       <h2><span>CILogon Service</span><span class="raised">CILogon Service</span></h2>
    </div>
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
    <div class="footer">
    <p class="p1">The <a target="_blank"
    href="http://www.cilogon.org/service">CILogon Service</a> is funded by
    the <a target="_blank" href="http://www.nsf.gov/">National Science
    Foundation</a> under grant numbers <a target="_blank"
    href="http://www.nsf.gov/awardsearch/showAward.do?AwardNumber=0850557">0850557</a>
    and <a target="_blank"
    href="http://www.nsf.gov/awardsearch/showAward.do?AwardNumber=0943633">0943633</a>.</p>
    <p class="p2">This site uses software from the <a target="_blank"
    href="http://myproxy.teragrid.org/">MyProxy</a> and <a target="_blank"
    href="http://gridshib.globus.org/">GridShib</a> projects.</p>
    <p class="p3">Please send any questions or comments about this
    site to <a
    href="mailto:help@teragrid.org">help&nbsp;@&nbsp;cilogon.org</a>.</p>
    </div> <!-- Close "footer" div    -->
    </div> <!-- Close "container" div -->
    </body>
    </html>
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
    $incommon = new incommon();
    $whitelist = new whitelist();
    $idps = $incommon->getOnlyWhitelist($whitelist);
    $providerId = getCookieVar('providerId');

    echo '
    <div id="wayf">
      <div id="boxheader">
        Select Your Organization
      </div>
      <form action="' . getServerVar('SCRIPT_NAME') . 
      '" method="post" class="wayfForm">
      <fieldset>
      <select name="providerId" id="selectIdP">
    ';

    foreach ($idps as $entityId => $idpName) {
        echo '<option value="' . $entityId . '"';
        if ($entityId == $providerId) {
            echo ' selected';
        }
        echo '>' . $idpName . '</option>' . "\n";
    }

    echo '
      </select>
      <label for="keepidp" title="If you check this box, you can bypass this welcome page and proceed directly to your organization\'s authentication page. You will need to clear your brower\'s cookies to return here.">Remember this selection:</label>
      <input type="checkbox" name="keepidp" id="keepidp" 
          title="If you check this box, you can bypass this welcome page and proceed directly to your organization\'s authentication page. You will need to clear your brower\'s cookies to return here." />
      <a href="" class="tip" onclick="return false;"><img 
          src="/images/infoIcon.png" width="14" height="14" 
          alt="Help" /><span>If you check this box, you can bypass this
          welcome page and proceed directly to your Organization\'s
          authentication page. You will need to clear your browser\'s
          cookies to return here.</span></a>
      <input type="submit" name="submit" class="submit" 
      value="Logon" />
      </fieldset>
      </form>
    </div>
    ';
}

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

?>
