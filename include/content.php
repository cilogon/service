<?php

require_once('util.php');
require_once('autoloader.php');
require_once('myproxy.php');

/* If needed, set the "Notification" banner text to a non-empty value   */
/* and uncomment the "define" statement in order to display a           */
/* notification box at the top of each page.                            */
/*
define('BANNER_TEXT',
       'The CILogon Service may be unavailable on Wednesday June 20
        10:00am - 11:00am Central Time due to service software upgrades.'
);
*/

/* The full URL of the Shibboleth-protected and OpenID getuser scripts. */
define('GETUSER_URL','https://' . HOSTNAME . '/secure/getuser/');
define('GETOPENIDUSER_URL','https://' . HOSTNAME . '/getopeniduser/');

/* Loggit object for logging info to syslog. */
$log = new loggit();

/* The csrf token object to set the CSRF cookie and print the hidden */
/* CSRF form element.  Be sure to do "global $csrf" to use it.       */
$csrf = new csrf();

/* The configuration for the skin, if any. */
/* Be sure to do "global $skin" to use it. */
$skin = new skin();


/************************************************************************
 * Function   : printHeader                                             *
 * Parameter  : (1) The text in the window's titlebar                   *
 *              (2) Optional extra text to go in the <head> block       *
 *              (3) Set the CSRF and CSRFProtetion cookies. Defaults    *
 *                  to true.                                            *
 * This function should be called to print out the main HTML header     *
 * block for each web page.  This gives a consistent look to the site.  *
 * Any style changes should go in the cilogon.css file.                 *
 ************************************************************************/
function printHeader($title='',$extra='',$csrfcookie=true) {
    global $csrf;       // Initialized above
    global $skin;

    if ($csrfcookie) {
        $csrf->setTheCookie();
        // Set the CSRF cookie used by GridShib-CA
        util::setCookieVar('CSRFProtection',$csrf->getTokenValue(),0);
    }

    // Find the "Powered By CILogon" image if specified by the skin
    $poweredbyimg = "/images/poweredbycilogon.png";
    $skinpoweredbyimg = (string)$skin->getConfigOption('poweredbyimg');
    if ((!is_null($skinpoweredbyimg)) && 
        (strlen($skinpoweredbyimg) > 0) &&
        (is_readable($_SERVER{'DOCUMENT_ROOT'} . $skinpoweredbyimg))) {
        $poweredbyimg = $skinpoweredbyimg;
    }

    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head><title>' , $title , '</title> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="initial-scale=0.6" />
    <meta http-equiv="X-XRDS-Location" 
          content="https://' , HOSTNAME , '/cilogon.xrds"/>
    <link rel="stylesheet" type="text/css" href="/include/cilogon.css" />
    ';

    $skin->printSkinLink();

    echo '<script type="text/javascript" src="/include/cilogon.js"></script>
    <script type="text/javascript" src="/include/deployJava.js"></script>

<!--[if IE]>
    <style type="text/css">
      body { behavior: url(/include/csshover3.htc); }
    </style>
<![endif]-->
    ';

    if (strlen($extra) > 0) {
        echo $extra;
    }

    echo '
    </head>

    <body>

    <div class="skincilogonlogo">
    <a target="_blank" href="http://www.cilogon.org/faq/"><img
    src="' , $poweredbyimg , '" alt="CILogon" 
    title="CILogon Service" /></a>
    </div>

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
function printFooter($footer='') {
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
    <p>See <a target="_blank"
    href="http://ca.cilogon.org/acknowledgements">acknowledgements</a> of
    support for this site.</p>
    </div> <!-- Close "footer" div -->
    </div> <!-- Close "pagecontent" div -->
    </body>
    </html>
    ';

    session_write_close();
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
 * Parameters : (1) (Optional) The value of the form's "action"         *
 *                  parameter. Defaults to getScriptDir().              *
 *              (2) (Optional) True if extra hidden tags should be      *
 *                  output for the GridShib-CA client application.      *
 *                  Defaults to false.                                  *
 * This function prints out the opening <form> tag for displaying       *
 * submit buttons.  The first parameter is used for the "action" value  *
 * of the <form>.  If omitted, getScriptDir() is called to get the      *
 * location of the current script.  This function outputs a hidden csrf *
 * field in the form block.  If the second parameter is given and set   *
 * to true, then an additional hidden input element is output to be     *
 * utilized by the GridShib-CA client.                                  *
 ************************************************************************/
function printFormHead($action='',$gsca=false) {
    global $csrf;
    static $formnum = 0;

    if (strlen($action) == 0) {
        $action = util::getScriptDir();
    }

    echo '
    <form action="' , $action , '" method="post" 
     autocomplete="off" id="form' , sprintf("%02d",++$formnum) , '">
    ';
    echo $csrf->hiddenFormElement();

    if ($gsca) {
        /* Output hidden form element for GridShib-CA */
        echo '
        <input type="hidden" name="CSRFProtection" value="' .
        $csrf->getTokenValue() . '" />
        ';
    }
}

/************************************************************************
 * Function   : printWAYF                                               *
 * Parameters : (1) Show the "Remember this selection" checkbox?        *
 *                  True or false. Defaults to true.                    *
 *              (2) Show all InCommon IdPs in selection list?           *
 *                  True or false. Defaults to false, which means show  *
 *                  only whitelisted IdPs.                              *
 * This function prints the list of IdPs in a <select> form element     *
 * which can be printed on the main login page to allow the user to     *
 * select "Where Are You From?".  This function checks to see if a      *
 * cookie for the 'providerId' had been set previously, so that the     *
 * last used IdP is selected in the list.                               *
 ************************************************************************/
function printWAYF($showremember=true,$incommonidps=false) {
    global $csrf;
    global $skin;

    $whiteidpsfile = '/var/www/html/include/whiteidps.txt';
    $helptext = "Check this box to bypass the welcome page on subsequent visits and proceed directly to the selected identity provider. You will need to clear your browser's cookies to return here."; 
    $searchtext = "Enter characters to search for in the list above.";

    /* Get an array of IdPs */
    $idplist = new idplist();

    /* Check if the user had previously selected an IdP from the list. */
    $keepidp     = util::getCookieVar('keepidp');
    $providerId  = util::getCookieVar('providerId');

    if ($incommonidps) { /* Get all InCommon IdPs only */
        $idps = $idplist->getInCommonIdPs();
    } else { /* Get the whitelisted InCommon IdPs, plus maybe OpenId IdPs  */
        $idps = $idplist->getWhitelistedIdPs();

        /* Add the list of OpenID providers into the $idps array so as to  */
        /* have a single selection list.  Keys are the IdP identifiers,    */
        /* values are the provider display names, sorted by names.         */
        foreach (openid::$providerUrls as $url => $name) {
            $idps[$url] = $name;
        }
        natcasesort($idps);

        /* Check to see if the skin's config.xml has a whitelist of IDPs.  */
        /* If so, go thru master IdP list and keep only those IdPs in the  */
        /* config.xml's whitelist.                                         */
        if ($skin->hasIdpWhitelist()) {
            foreach ($idps as $entityId => $displayName) {
                if (!$skin->idpWhitelisted($entityId)) {
                    unset($idps[$entityId]);
                }
            }
        }
        /* Next, check to see if the skin's config.xml has a blacklist of  */
        /* IdPs. If so, cull down the master IdP list removing 'bad' IdPs. */
        if ($skin->hasIdpBlacklist()) {
            $idpblacklist = $skin->getConfigOption('idpblacklist');
            foreach ($idpblacklist->idp as $blackidp) {
                unset($idps[(string)$blackidp]);
            }
        }

        /* Make sure previously selected IdP is in list of available IdPs. */
        if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
            $providerId = '';
        }

        /* If no previous providerId, get from skin, or default to Google. */
        if (strlen($providerId) == 0) {
            $initialidp = (string)$skin->getConfigOption('initialidp');
            if ((!is_null($initialidp)) && (isset($idps[$initialidp]))) {
                $providerId = $initialidp;
            } else {
                $providerId = openid::getProviderUrl('Google');
            }
        }
    }

    echo '
    <br />
    <div class="actionbox"';

    if (util::getSessionVar('showhelp') == 'on') {
        echo ' style="width:92%;"';
    }

    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">

      <form action="' , util::getScriptDir() , '" method="post">
      <fieldset>

      <p>Select An Identity Provider:</p>
      ';

      // See if the skin has set a size for the IdP <select> list
      $selectsize = 4;
      $ils = $skin->getConfigOption('idplistsize');
      if ((!is_null($ils)) && ((int)$ils > 0)) {
          $selectsize = (int)$ils;
      }

      echo '
      <p>
      <select name="providerId" id="providerId" size="' , $selectsize , '"
       onkeypress="enterKeySubmit(event)" ondblclick="doubleClickSubmit()">
    ';

    foreach ($idps as $entityId => $idpName) {
        echo '    <option value="' , $entityId , '"';
        if ($entityId == $providerId) {
            echo ' selected="selected"';
        }
        echo '>' , util::htmlent($idpName) , '</option>' , "\n    ";
    }

    echo '  </select>
    </p>

    <p id="listsearch" class="zeroheight">
    <label for="searchlist" class="helpcursor" title="' , 
    $searchtext , '">Search:</label>
    <input type="text" name="searchlist" id="searchlist" value=""
    size="30" onkeyup="searchOptions(this.value)" 
    title="' , $searchtext , '" />
<!--[if IE]><input type="text" style="display:none;" disabled="disabled" size="1"/><![endif]-->
    </p>
    ';

    if ($showremember) { 
        echo '
        <p>
        <label for="keepidp" title="' , $helptext , 
        '" class="helpcursor">Remember this selection:</label>
        <input type="checkbox" name="keepidp" id="keepidp" ' , 
        (($keepidp == 'checked') ? 'checked="checked" ' : '') ,
        'title="' , $helptext , '" class="helpcursor" />
        </p>
        ';
    }

    echo '
    <p class="silvercheckbox">
    <label for="silveridp">Request Silver:</label>
    <input type="checkbox" name="silveridp" id="silveridp"/>
    </p>

    <p>
    ';

    echo $csrf->hiddenFormElement();

    $lobtext = getLogOnButtonText();

    echo '
    <input type="submit" name="submit" class="submit helpcursor" 
    title="Continue to the selected identity provider."
    value="' , $lobtext , '" id="wayflogonbutton" />
    <input type="hidden" name="previouspage" value="WAYF" />
    <input type="submit" name="submit" class="submit helpcursor"
    title="Cancel authentication and navigate away from this site."
    value="Cancel" id="wayfcancelbutton" />
    </p>
    ';

    $openiderror = util::getSessionVar('openiderror');
    if (strlen($openiderror) > 0) {
        echo "<p class=\"openiderror\">$openiderror</p>";
        util::unsetSessionVar('openiderror');
    }

    echo '
    <p class="privacypolicy">
    By selecting "' , $lobtext , '", you agree to <a target="_blank"
    href="http://ca.cilogon.org/policy/privacy">CILogon\'s privacy
    policy</a>.
    </p>

    </fieldset>

    </form>
  </td>
  ';

  if (util::getSessionVar('showhelp') == 'on') {
      echo '
      <td class="helpcell">
      <div>
      ';

      if ($incommonidps) { /* InCommon IdPs only means running from /testidp/ */
          echo '
          <p>
          CILogon facilitates secure access to CyberInfrastructure (<acronym
          title="CyberInfrastructure">CI</acronym>). In order to test your identity
          provider with the CILogon Service, you must first Log On. If your preferred
          identity provider is not listed, please fill out the <a target="_blank"
          href="https://cilogon.org/requestidp/">"request a new organization"
          form</a>, and we will try to add your identity provider in the future.
          </p>
          ';
      } else { /* If not InCommon only, print help text for OpenID providers. */
          echo '
          <p>
          CILogon facilitates secure access to CyberInfrastructure (<acronym
          title="CyberInfrastructure">CI</acronym>).
          In order to use the CILogon Service, you must first select an identity
          provider. An identity provider (IdP) is an organization where you have
          an account and can log on to gain access to online services. 
          </p>
          <p>
          If you are a faculty, staff, or student member of a university or
          college, please select it for your identity
          provider.  If your school is not listed,
          please fill out the <a target="_blank"
          href="https://cilogon.org/requestidp/">"request a new organization"
          form</a>, and we will try to add your school in the future.
          </p>
          ';

          $googleavail   = $skin->idpAvailable('http://google.com/accounts/o8/id');
          $paypalavail   = $skin->idpAvailable('http://openid.paypal-ids.com');
          $verisignavail = $skin->idpAvailable('http://pip.verisignlabs.com');
          $numavail      = $googleavail + $paypalavail + $verisignavail;

          if ($numavail > 0) {
              echo '
              <p>
              If you have a ';
              
              if ($googleavail) {
                  echo '<a target="_blank"
                  href="http://google.com/profiles/me">Google</a>';
                  if ($numavail > 2) {
                      echo ', ';
                  } elseif ($numavail == 2) {
                      echo ' or ';
                  }
              }
              
              
              if ($paypalavail) {
                  echo '<a target="_blank"
                  href="https://openid.paypal-ids.com/">PayPal</a> ';
                  if ($numavail > 1) {
                      echo 'or ';
                  }
              }
              
              if ($verisignavail) {
                  echo ' <a target="_blank"
                  href="https://pip.verisignlabs.com/">VeriSign</a> ';
              }

              echo ' account, you can select one of these providers for
              authenticating to the CILogon Service.
              </p>
              ';
          }

          if ($skin->idpAvailable('urn:mace:incommon:idp.protectnetwork.org')) {
              echo '
              <p>
              Alternatively, you can <a
              target="_blank"
              href="https://www.protectnetwork.org/pnidm/registration.html">register
              for a ProtectNetwork UserID</a> and use that for authenticating to
              the CILogon Service.
              </p>
              ';
          }
      }

      echo '
      </div>
      </td>
      ';
  }
  echo '
  </tr>
  </table>
  </div>
  ';
}

/************************************************************************
 * Function   : printTwoFactorBox                                       *
 * This function prints the "Manage Two-Factor" box on the main page.   *
 ************************************************************************/
function printTwoFactorBox() {
    $managetwofactortext = 'Enable or disable two-factor authentication for your account';

    echo '
    <div class="twofactoractionbox"';

    $style = ''; // Might add extra CSS to the twofactoractionbox
    if (util::getSessionVar('showhelp') == 'on') {
        $style .= "width:92%;";
    }
    if (twofactor::getEnabled() != 'none') {
        $style .= "display:block;"; // Force display if two-factor enabled
    }
    if (strlen($style) > 0) {
        echo ' style="' , $style , '"';
    }
    
    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">
    ';
    
    printFormHead();

    $twofactorname = twofactor::getEnabledName();
    if ($twofactorname == 'Disabled') {
        $twofactorname = 'Two-Factor Authentication Disabled';
    } else {
        $twofactorname .= ' Enabled';
    }
    echo '
      <p>' , $twofactorname , '</p>

      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $managetwofactortext , '" value="Manage Two-Factor" />
      </p>
      </form>
    </td>
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <td class="helpcell">
        <div>
        <p>
        Two-factor authentication provides extra security on your account by
        using a physical device (e.g., your mobile phone) to generate a one
        time password which you enter after you log in to your selected
        Identity Provider. Click the "Manage Two-Factor" button to enable or
        disable two-factor authentication.
        </p>
        </div>
        </td>
        ';
    }
    
    echo '
    </tr>
    </table>
    </div> <!-- twofactoractionbox -->
    ';
}

/************************************************************************
 * Function  : printTwoFactorPage                                       *
 * This function prints out the Manage Two-Factor Authentication page.  *
 * Display of which two-factor types are available to the user is       *
 * controlled by CSS. From this page, the user can Enable or Disable    *
 * various two-factor authentication methods.                           *
 ************************************************************************/
function printTwoFactorPage() {
    util::setSessionVar('stage','managetwofactor'); // For Show/Hide Help button

    printHeader('Manage Two-Factor Authentication');

    $twofactorname = twofactor::getEnabledName();

    echo '
    <div class="boxed">
    ';
    printHelpButton();
    echo'
    <h2>Two-Factor Authentication</h2>
    <div class="actionbox">
    <p><b>Two-Factor Authentication:</b></p>
    <p>' , $twofactorname , '</p>
    </div> <!-- actionbox -->
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <div>
        <p>
        Multi-factor authentication requires a user to present two or more
        distinct authentication factors from the following categories:
        </p>
        <ul>
        <li>Something you <b>know</b> (e.g., username and password)</li>
        <li>Something you <b>have</b> (e.g., mobile phone or hardware 
            token)</li>
        <li>Something you <b>are</b> (e.g., fingerprint)</li>
        </ul>
        <p>
        Below you can configure a second factor using something you <b>have</b>,
        i.e., your mobile phone. You will first be prompted to register the
        second-factor device, typically by installing specific apps on your
        phone and completing a registration process. Then you will need to log
        in with the second factor to verify the registration process.
        </p>
        <p>
        Once you have successfully enabled two-factor authentication, you will
        be prompted on future CILogon Service logons for your second-factor
        authentication.  You can select the second-factor authentication to use
        (or not) by clicking the "Enable" (or "Disable") button.
        </p>
        </div>
        ';
    }

    echo '
    <table class="twofactortypes">
    <tr class="twofactorgooglerow"' , 
    (twofactor::isEnabled('ga') ? ' style="display:table-row;"' : '') , 
    '>
    <th>Google Authenticator</th>
    <td>
    ';
    printFormHead();
    echo '
    <input type="hidden" name="twofactortype" value="ga" />
    <input type="submit" name="submit" class="submit" value="' ,
    (twofactor::isEnabled('ga') ? 'Disable' : 'Enable') ,
    '" />
    </form>
    </td>
    </tr>
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <tr>
        <td colspan="4">
        Google Authenticator is an app available for Android OS, Apple
        iOS, and BlackBerry OS. The app generates one-time password tokens.
        After you have logged on to the CILogon Service with your chosen
        Identity Provider, you would be prompted to use the Google
        Authenticator app to generate a second passcode and enter it. 
        </td>
        </tr>
        ';
    }

    echo '
    <tr class="twofactorduorow"' , 
    (twofactor::isEnabled('duo') ? '
        style="display:table-row;border-top-width:1px"' : '') , 
    '>
    <th>Duo Security</th>
    <td>
    ';
    printFormHead(); 
    echo '
    <input type="hidden" name="twofactortype" value="duo" />
    <input type="submit" name="submit" class="submit" value="' ,
    (twofactor::isEnabled('duo') ? 'Disable' : 'Enable') ,
    '" />
    </form>
    </td>
    </tr>
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <tr>
        <td colspan="4">
        Duo Security is an app available for most smartphones, including
        Android OS, Apple iOS, Blackberry OS, Palm, Symbian, and Windows
        Mobile. The app can respond to "push" notifications, and can also
        generate one-time password tokens. After you have logged on to the
        CILogon Service with your chosen Identity Provider, you would be
        prompted to select a Duo log in method and then authenticate with
        your mobile phone.
        </td>
        </tr>
        ';
    }

    echo '
    </table>

    <noscript>
    <div class="nojs smaller">
    Javascript is disabled. In order to activate the link
    below, please enable Javascript in your browser.
    </div>
    </noscript>

    <p>
    <a href="javascript:showHideDiv(\'lostdevice\',-1)">Lost your phone?</a>
    </p>
    <div id="lostdevice" style="display:none">
    <p>
    If you have lost your phone, you can click on the
    "I Lost My Phone" button below to remove all two-factor methods
    from your account.  This will send an email message to the system
    adminisrator and to the email address provided by your Identity
    Provider.  You can then use the CILogon Service without
    two-factor authentication enabled. You will need to re-register your
    device with you want to use it for two-factor authentication again.
    <br/>
    ';
    printFormHead();
    echo '
    <input type="submit" name="submit" class="submit" 
    value="I Lost My Phone" />
    </p>
    </div>

    <p>
    <input type="submit" name="submit" class="submit" 
    value="Done with Two-Factor" />
    </p>
    </form>

    </div> <!-- boxed -->
    ';
    printFooter();
}

/************************************************************************
 * Function  : handleEnableDisableTwoFactor                             *
 * Parameter : True for 'enable', false for 'disable'. Default is       *
 *             false (for 'disable').                                   *
 * This function is called when the user clicks either an 'Enable' or   *
 * 'Disable' button from the Manage Two-Factor page, or when the user   *
 * clicks 'Verify' on the Google Authenticator Registration page.       *
 * The passed-in parameter tells which type of button was pressed.      *
 * If 'Disable', then simply set 'enabled=none' in the datastore and    *
 * display the Manage Two-Factor page again. If 'Enable' or 'Verify',   *
 * check the 'twofactortype' hidden form variable for which two-factor  *
 * authentication method is to be enabled. Then print out that          *
 * two-factor page. Note that twofactor::printPage() does the work of   *
 * figuring out if the user has registered the phone yet or not, and    *
 * displays the appropriate page.                                       *
 ************************************************************************/
function handleEnableDisableTwoFactor($enable=false) {
    if ($enable) {
        $twofactortype = util::getPostVar('twofactortype');
        if (strlen($twofactortype) > 0) {
            twofactor::printPage($twofactortype);
        } else {
            printLogonPage();
        }
    } else { // 'Disable' clicked
        // Check if the user clicked 'Disable Two-Factor' and send email
        if (util::getPostVar('missingphone') == '1') {
            // Make sure two-factor was enabled
            $twofactorname = twofactor::getEnabledName();
            if ($twofactorname != 'Disabled') {
                $email = getEmailFromDN(util::getSessionVar('dn'));
                if (strlen($email) > 0) { // Make sure email address exists
                    twofactor::sendPhoneAlert(
                        'Forgot Phone for Two-Factor Authentication',
'While using the CILogon Service, you (or someone using your account)
indicated that you forgot your phone by clicking the "Disable Two-Factor"
button. This disabled two-factor authentication by "' . $twofactorname . '"
using "' . util::getSessionVar('idpname') . '" as your Identity Provider.

If you did not disable two-factor authentication, please send email to
"help@cilogon.org" to report this incident.',
                        $email
                    );
                } else { // No email address is bad - send error alert
                    util::sendErrorAlert('Missing Email Address',
'When attempting to send an email notification to a user who clicked the
"Disable Two-Factor" button because of a forgotten phone, the CILogon
Service was unable to find an email address. This should never occur and
is probably due to a badly formatted "dn" string.');
                }
            }
        }

        // Finally, disable two-factor authentication
        twofactor::setDisabled();
        twofactor::write();
        printTwoFactorPage();
    }
}

/************************************************************************
 * Function  : handleILostMyPhone                                       *
 * This function is called when the user clicks the 'I Lost My Phone'   *
 * button.  It sends email to the user AND to alerts because Duo        *
 * Security requires that a sysadmin unregister the phone for the user. *
 * It then unsets the 'twofactor' session variable, and writes it to    *
 * the datastore, effectively wiping out all two-factor information for *
 * the user.                                                            *
 ************************************************************************/
function handleILostMyPhone() {
    // First, send email to user
    $email = getEmailFromDN(util::getSessionVar('dn'));
    if (strlen($email) > 0) { // Make sure email address exists
        twofactor::sendPhoneAlert(
            'Lost Phone for Two-Factor Authentication',
'While using the CILogon Service, you (or someone using your account)
indicated that you lost your phone by clicking the "I Lost My Phone"
button. This removed two-factor authentication for your account when
using "' . util::getSessionVar('idpname') . '" as your Identity Provider.

System administrators have been notified of this incident. If you require
further assistance, please send email to "help@cilogon.org".',
            $email
        );
    } else { // No email address is bad - send error alert
        util::sendErrorAlert('Missing Email Address',
'When attempting to send an email notification to a user who clicked the
"I Lost My Phone" button, the CILogon Service was unable to find an 
email address. This should never occur and is probably due to a badly
formatted "dn" string.');
    }

    // Next, send email to sysadmin
    $errortext = 'A user clicked the "I Lost My Phone" button. ';
    if (twofactor::isRegistered('duo')) {
        $duoconfig = new duoconfig();
        $errortext .= '

The user had registered "Duo Security" as one of the two-factor methods.
Since there is no way for the CILogon Service to UNregister this method
at the Duo Security servers, a system administrator will need to delete 
this user\'s registration at https://' . $duoconfig->param['host'] .
' .';
    }
    util::sendErrorAlert('Two-Factor Authentication Disabled',$errortext);

    // Finally, disable and unregister two-factor authentication
    util::unsetSessionVar('twofactor');
    twofactor::write();
    printTwoFactorPage();
}

/************************************************************************
 * Function  : handleGoogleAuthenticatorLogin                           *
 * This function is called when the user enters a one time password as  *
 * generated by the Google Authenticator app. This can occur (1) when   *
 * the user is first configuring GA two-factor and (2) when the user    *
 * logs in to the CILogon Service and GA is enabled. If the OTP is      *
 * correctly validated, the gotUserSuccess() function is called to      *
 * show output to the user.                                             *
 ************************************************************************/
function handleGoogleAuthenticatorLogin() {
    $gacode = util::getPostVar('gacode');
    if ((strlen($gacode) > 0) && (twofactor::isGACodeValid($gacode))) {
        gotUserSuccess();
    } else {
        twofactor::printPage('ga');
    }
}

/************************************************************************
 * Function  : handleDuoSecurityLogin                                   *
 * This function is called when the user authenticates with Duo         *
 * Security. If the Duo authentication is valid, then the               *
 * gotUserSuccess() function is then called to show output to the user. *
 ************************************************************************/
function handleDuoSecurityLogin() {
    $sig_response = util::getPostVar('sig_response');
    if ((strlen($sig_response) > 0) &&
        (twofactor::isDuoCodeValid($sig_response))) {
        gotUserSuccess();
    } else {
        twofactor::printPage('duo');
    }
}

/************************************************************************
 * Function  : handleLogOnButtonClicked                                 *
 * This function is called when the user clicks the "Log On" button     *
 * on the IdP selection page. It checks to see if the "Remember this    *
 * selection" checkbox was checked and sets a cookie appropriately. It  *
 * also sets a cookie 'providerId' so the last chosen IdP will be       *
 * selected the next time the user visits the site. The function then   *
 * calls the appropriate "redirectTo..." function to send the user      *
 * to the chosen IdP.                                                   *
 ************************************************************************/
function handleLogOnButtonClicked() {
    // Read in the whitelist of currently available InCommon IdPs
    $idplist = new idplist();

    // Set the cookie for keepidp if the checkbox was checked
    if (strlen(util::getPostVar('keepidp')) > 0) {
        util::setCookieVar('keepidp','checked');
    } else {
        util::unsetCookieVar('keepidp');
    }

    // Set the cookie for the last chosen IdP and redirect to it
    $providerIdPost = util::getPostVar('providerId');
    if (openid::urlExists($providerIdPost)) { // Use OpenID authn
        util::setCookieVar('providerId',$providerIdPost);
        redirectToGetOpenIDUser($providerIdPost);
    } elseif ($idplist->exists($providerIdPost)) { // Use InCommon authn
        util::setCookieVar('providerId',$providerIdPost);
        redirectToGetUser($providerIdPost);
    } else { // Either providerId not set or not in whitelist
        util::unsetCookieVar('providerId');
        printLogonPage();
    }
}
/************************************************************************
 * Function  : handleHelpButtonClicked                                  *
 * This function is called when the user clicks on the "Show Help" /    *
 * "Hide Help" button in the upper right corner of the page. It toggles *
 * the 'showhelp' session variable and redisplays the appropriate page  *
 * with help now shown or hidden.                                       *
 ************************************************************************/
function handleHelpButtonClicked() {
    if (util::getSessionVar('showhelp') == 'on') {
        util::unsetSessionVar('showhelp');
    } else {
        util::setSessionVar('showhelp','on');
    }

    $stage = util::getSessionVar('stage');
    if (verifyCurrentSession()) {
        if ($stage == 'main') {
            printMainPage();
        } elseif ($stage == 'managetwofactor') {
            printTwoFactorPage();
        } else {
            printLogonPage();
        }
    } else {
        printLogonPage(true);
    }
}

/************************************************************************
 * Function  : handleNoSubmitButtonClicked                              *
 * This function is the "default" case when no "submit" button has been *
 * clicked, or if the submit session variable is not set. It checks     *
 * to see if either the <forceinitialidp> option is set, or if the      *
 * "Remember this selection" checkbox was previously checked. If so,    *
 * then rediret to the appropriate IdP. Otherwise, print the main       *
 * Log On page.                                                         *
 ************************************************************************/
function handleNoSubmitButtonClicked() {
    global $skin;

    // Read in the whitelist of currently available InCommon IdPs
    $idplist = new idplist();

    /* If the <forceinitialidp> option is set, use the <initialidp> *
     * as the providerId and <forceinitialidp> as keepIdp.          *
     * Otherwise, get the cookies 'providerId' and 'keepidp'.       */
    $forceinitialidp = (int)$skin->getConfigOption('forceinitialidp');
    $initialidp = (string)$skin->getConfigOption('initialidp');
    if (($forceinitialidp == 1) && (strlen($initialidp) > 0)) {
        $providerId = $initialidp;
        $keepIdp = $forceinitialidp;
    } else {
        $providerId = util::getCookieVar('providerId');
        $keepIdp = util::getCookieVar('keepidp');
    }

    /* If both "keepidp" and "providerId" were set (and the         *
     * providerId is a whitelisted IdP or valid OpenID provider),   *
     * then skip the Logon page and proceed to the appropriate      *
     * getuser script.                                              */
    if ((strlen($providerId) > 0) && (strlen($keepIdp) > 0)) {
        if (openid::urlExists($providerId)) { // Use OpenID authn
            redirectToGetOpenIDUser($providerId);
        } elseif ($idplist->exists($providerId)) { // Use InCommon
            redirectToGetUser($providerId);
        } else { // $providerId not in whitelist
            util::unsetCookieVar('providerId');
            printLogonPage();
        }
    } else { // One of providerId or keepidp was not set
        printLogonPage();
    }
}

/************************************************************************
 * Function  : printIcon                                                *
 * Parameters: (1) The prefix of the "...Icon.png" image to be shown.   *
 *                 E.g. to show "errorIcon.png", pass in "error".       *
 *             (2) The popup "title" text to be displayed when the      *
 *                 mouse cursor hovers over the icon.  Defaults to "".  *
 *             (3) A CSS class for the icon. Will be appended after     *
 *                 the 'helpcursor' class.                              *
 * This function prints out the HTML for the little icons which can     *
 * appear inline with other information.  This is accomplished via the  *
 * use of wrapping the image in a <span> tag.                           *
 ************************************************************************/
function printIcon($icon,$popuptext='',$class='') {
    echo '<span';
    if (strlen($popuptext) > 0) {
        echo ' class="helpcursor ' , $class , '" title="' , $popuptext , '"';
    }
    echo '>&nbsp;<img src="/images/' , $icon , 'Icon.png" 
          alt="&laquo; ' , ucfirst($icon) , '"
          width="14" height="14" /></span>';
}

/************************************************************************
 * Function   : printHelpButton                                         *
 * This function prints the "Show Help" / "Hide Help" button in the     *
 * upper-right corner of the main box area on the page.                 *
 ************************************************************************/
function printHelpButton() {
    echo '
    <div class="helpbutton">
    ';

    printFormHead();

    echo '
      <input type="submit" name="submit" class="helpbutton" value="' , 
      (util::getSessionVar('showhelp')=='on' ? 'Hide' : 'Show') , '&#10;Help" />
      </form>
    </div>
    ';
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
function verifyCurrentSession($providerId='') {
    $retval = false;

    $uid     = util::getSessionVar('uid');
    $idp     = util::getSessionVar('idp');
    $idpname = util::getSessionVar('idpname');
    $status  = util::getSessionVar('status');
    $dn      = util::getSessionVar('dn');
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
 * Function  : verifySessionAndCall                                     *
 * Parameters: (1) The function to call if the current session is       *
 *                 successfully verified.                               *
 *             (2) An array of parameters to pass to the function.      *
 *                 Defaults to empty array, meaning zero parameters.    *
 * This function is a convenience method called by several cases in the *
 * main 'switch' call at the top of the index.php file. I noticed       *
 * a pattern where verifyCurrentSession() was called to verify the      *
 * current user session. Upon success, one or two functions were called *
 * to continue program, flow. Upon failure, cookies and session         *
 * variables were cleared, and the main Logon page was printed. This    *
 * function encapsulates that pattern. If the user's session is valid,  *
 * the passed-in $func is called, possibly with parameters passed in as *
 * an array. The function returns true if the session is verified, so   *
 * that other functions may be called upon return.                      *
 ************************************************************************/
function verifySessionAndCall($func,$params=array()) {
    $retval = false;
    if (verifyCurrentSession()) { // Verify PHP session contains valid info
        $retval = true;
        call_user_func_array($func,$params);
    } else {
        printLogonPage(true); // Clear cookies and session vars too
    }
    return $retval;
}

/************************************************************************
 * Function   : redirectToGetUser                                       *
 * Parameters : (1) An entityId of the authenticating IdP.  If not      *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 *              (2) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 *              (3) A response url for redirection after successful     *
 *                  processing at /secure/getuser/. Defaults to         *
 *                  the current script directory.                       *
 *              (4) Is it okay to request silver assurance in the       *
 *                  authnContextClassRef? If not, then ignore the       *
 *                  "Request Silver" checkbox and silver certification  *
 *                  in metadata. Defaults to true.                      *
 * If the first parameter (a whitelisted entityId) is not specified,    *
 * we check to see if either the providerId PHP session variable or the *
 * providerId cookie is set (in that order) and use one if available.   *
 * The function then checks to see if there is a valid PHP session      *
 * and if the providerId matches the 'idp' in the session.  If so, then *
 * we don't need to redirect to "/secure/getuser/" and instead we       *
 * we display the main page.  However, if the PHP session is not valid, *
 * then this function redirects to the "/secure/getuser/" script so as  *
 * to do a Shibboleth authentication via mod_shib. When the providerId  *
 * is non-empty, the SessionInitiator will automatically go to that IdP *
 * (i.e. without stopping at a WAYF).  This function also sets          *
 * several PHP session variables that are needed by the getuser script, *
 * including the 'responsesubmit' variable which is set as the return   *
 * 'submit' variable in the 'getuser' script.                           *
 ************************************************************************/
function redirectToGetUser($providerId='',$responsesubmit='gotuser',
                           $responseurl=null,$allowsilver=true) {
    global $csrf;
    global $log;
    global $skin;

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = util::getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = util::getCookieVar('providerId');
        }
    }
    
    // Check if this IdP requires the use of a particular 'skin'
    checkForceSkin($providerId);

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the main page.
    if (verifyCurrentSession($providerId)) {
        printMainPage();
    } else { // Otherwise, redirect to the getuser script
        // Set PHP session varilables needed by the getuser script
        util::setSessionVar('responseurl',
            (is_null($responseurl) ? util::getScriptDir(true) : $responseurl));
        util::setSessionVar('submit','getuser');
        util::setSessionVar('responsesubmit',$responsesubmit);
        $csrf->setTheCookie();
        $csrf->setTheSession();

        // Set up the "header" string for redirection thru mod_shib 
        $redirect = 
            'Location: https://' . HOSTNAME . '/Shibboleth.sso/Login?' .
            'target=' . urlencode(GETUSER_URL);
        /*
         * Special handling for cilogon.org - redirect to polo1.cilogon.org
         * or polo2.cilogon.org when initiating Shibboleth session, and
         * also when coming back (target=...) after authenticating at IdP.
         */
        if (HOSTNAME == 'cilogon.org') {
            $redirect = preg_replace('/cilogon.org/',getMachineHostname(),
                                     $redirect);
        }
        if (strlen($providerId) > 0) {
            $redirect .= '&providerId=' . urlencode($providerId);

            // To bypass SSO at IdP, check for skin's 'forceauthn'
            $forceauthn = $skin->getConfigOption('forceauthn');
            if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                $redirect .= '&forceAuthn=true';
            }

            // If Silver IdP or "Request Silver" checked, send extra parameter
            if ($allowsilver) {
                $idplist = new idplist();
                if (($idplist->isSilver($providerId)) ||
                    (strlen(util::getPostVar('silveridp')) > 0)) {
                    util::setSessionVar('requestsilver','1');
                    $redirect .= '&authnContextClassRef=' . 
                        urlencode('http://id.incommon.org/assurance/silver');
                }
            }
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
 * database to store temporary tokens used by OpenID upon successful    *
 * authentication.  Next, create a new OpenID consumer and attempt to   *
 * redirect to the appropriate OpenID provider.  Upon any error, set    *
 * the 'openiderror' PHP session variable and redisplay the main logon  *
 * screen.                                                              *
 ************************************************************************/
function redirectToGetOpenIDUser($providerId='',$responsesubmit='gotuser') {
    global $csrf;
    global $log;
    global $skin;

    $openiderrorstr = 'Internal OpenID error. Please contact <a href="mailto:help@cilogon.org">help@cilogon.org</a> or select a different identity provider.';

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = util::getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = util::getCookieVar('providerId');
        }
    }

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the 'Download Certificate' button page.
    if (verifyCurrentSession($providerId)) {
        printMainPage();
    } else { // Otherwise, redirect to the getopeniduser script
        // Set PHP session varilables needed by the getopeniduser script
        util::unsetSessionVar('openiderror');
        util::setSessionVar('responseurl',util::getScriptDir(true));
        util::setSessionVar('submit','getuser');
        util::setSessionVar('responsesubmit',$responsesubmit);
        $csrf->setTheCookie();
        $csrf->setTheSession();

        $auth_request = null;
        $openid = new openid();
        $datastore = $openid->getStorage();

        if (is_null($datastore)) {
            util::setSessionVar('openiderror',$openiderrorstr);
        } else {
            require_once("Auth/OpenID/Consumer.php");
            require_once("Auth/OpenID/SReg.php");
            require_once("Auth/OpenID/PAPE.php");
            require_once("Auth/OpenID/AX.php");

            $consumer = new Auth_OpenID_Consumer($datastore);
            $auth_request = $consumer->begin($providerId);

            if (!$auth_request) {
                util::setSessionVar('openiderror',$openiderrorstr);
            } else {
                // Get attributes from Verisign
                $sreg_request = Auth_OpenID_SRegRequest::build(
                    array('fullname','email'));
                if ($sreg_request) {
                    $auth_request->addExtension($sreg_request);
                }

                // Get attributes from Google and Yahoo
                $attributes = array(
                    Auth_OpenID_AX_AttrInfo::make(
                        'http://axschema.org/contact/email',1,1,'email'),
                    Auth_OpenID_AX_AttrInfo::make(
                        'http://axschema.org/namePerson/first',1,1,'firstname'),
                    Auth_OpenID_AX_AttrInfo::make(
                        'http://axschema.org/namePerson/last',1,1,'lastname'),
                    Auth_OpenID_AX_AttrInfo::make(
                        'http://axschema.org/namePerson',1,0,'fullname')
                );
                $ax = new Auth_OpenID_AX_FetchRequest;
                foreach($attributes as $attr){
                    $ax->add($attr);
                }
                $auth_request->addExtension($ax);

                // Add a PAPE extension for OpenID Trust Level 1 and also
                // possibly force user authentication every time.
                $max_auth_age = null;
                // To bypass SSO at IdP, check for skin's 'forceauthn'
                $forceauthn = $skin->getConfigOption('forceauthn');
                if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                    $max_auth_age = '0';
                }
                $pape_request = new Auth_OpenID_PAPE_Request(
                    array('http://www.idmanagement.gov/schema/2009/05/icam/openid-trust-level1.pdf'),$max_auth_age);
                if ($pape_request) {
                    $auth_request->addExtension($pape_request);
                }

                // Start the OpenID authentication request
                if ($auth_request->shouldSendRedirect()) {
                    $redirect_url = $auth_request->redirectURL(
                        'https://' . HOSTNAME . '/',
                        GETOPENIDUSER_URL);
                    if (Auth_OpenID::isFailure($redirect_url)) {
                        util::setSessionVar('openiderror',$openiderrorstr);
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
                        util::setSessionVar('openiderror',$openiderrorstr);
                    } else {
                        $log->info('OpenID Login="' . $providerId . '"');
                        print $form_html;
                    }
                }

                $openid->disconnect();
            }
        }

        if (strlen(util::getSessionVar('openiderror')) > 0) {
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
function printErrorBox($errortext) {
    echo '
    <div class="errorbox">
    <table cellpadding="5">
    <tr>
    <td valign="top">
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
function unsetGetUserSessionVars() {
    util::unsetSessionVar('submit');
    util::unsetSessionVar('uid');
    util::unsetSessionVar('status');
    util::unsetSessionVar('loa');
    util::unsetSessionVar('idp');
    util::unsetSessionVar('idpname');
    util::unsetSessionVar('firstname');
    util::unsetSessionVar('lastname');
    util::unsetSessionVar('dn');
    util::unsetSessionVar('twofactor');
    util::unsetSessionVar('activation');
    util::unsetSessionVar('p12');
    util::unsetSessionVar('p12lifetime');
    util::unsetSessionVar('p12multiplier');
}

/************************************************************************
 * Function   : unsetPortalSessionVars                                  *
 * This function removes all of the PHP session variables related to    *
 * portal delegation.                                                   *
 ************************************************************************/
function unsetPortalSessionVars() {
    util::unsetSessionVar('portalstatus');
    util::unsetSessionVar('callbackuri');
    util::unsetSessionVar('successuri');
    util::unsetSessionVar('failureuri');
    util::unsetSessionVar('portalname');
    util::unsetSessionVar('tempcred');
    util::unsetSessionVar('dn');
}

/************************************************************************
 * Function   : handleGotUser                                           *
 * This function is called upon return from one of the getuser scripts  *
 * which should have set the 'uid' and 'status' PHP session variables.  *
 * It verifies that the status return is one of STATUS_OK (even         *
 * values).  If not, we print an error message to the user.             *
 ************************************************************************/
function handleGotUser() {
    global $log;

    $uid = util::getSessionVar('uid');
    $status = util::getSessionVar('status');
    // If empty 'uid' or 'status' or odd-numbered status code, error!
    if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
        $log->error('Failed to getuser.');

        $idpname = util::getSessionVar('idpname');
        unsetGetUserSessionVars();
        printHeader('Error Logging On');

        echo '
        <div class="boxed">
        ';

        $lobtext = getLogOnButtonText();

        if ($status == dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) {

            // Check if the problem IdP was Google - probably no first/last name
            if ($idpname == 'Google') {
                printErrorBox('
                <p>
                There was a problem logging on.  It appears that you have
                attempted to use Google as your identity provider, but you
                have not yet associated a first and last name with your
                Google account. To rectify this problem, go to the <a
                target="_blank"
                href="https://www.google.com/accounts/EditUserInfo">Google
                Account Edit Personal Information page</a>, enter a First
                Name and a Last Name, and click the "Save" button.  (All
                other Google account information is optional and not
                required by the CILogon Service.)
                </p>
                <p>
                After you have updated your Google account profile, click
                the "' . $lobtext . '" button below to attempt to log on
                with your Google account again.  If you have any questions,
                please contact us at the email address at the bottom of the
                page.  </p>
                ');

                echo '
                <div>
                ';
                printFormHead();
                echo '
                <p class="centered">
                <input type="hidden" name="providerId" value="' ,
                openid::getProviderUrl('Google') , '" />
                <input type="submit" name="submit" class="submit" 
                value="' , $lobtext , '" />
                </p>
                </form>
                </div>
                ';
            } else {
                printErrorBox('There was a problem logging on. Your identity
                provider has not provided CILogon with required information
                about you (i.e., your full name and email address). This may
                be a temporary error. Please try again later, or contact us
                at the email address at the bottom of the page.');

                echo '
                <div>
                ';
                printFormHead();
                echo '
                <input type="submit" name="submit" class="submit"
                value="Proceed" />
                </form>
                </div>
                ';
            }
        } else {
            printErrorBox('An internal error has occurred.  System
                administrators have been notified.  This may be a temporary
                error.  Please try again later, or contact us at the the email
                address at the bottom of the page.');

            echo '
            <div>
            ';
            printFormHead();
            echo '
            <input type="submit" name="submit" class="submit" value="Proceed" />
            </form>
            </div>
            ';
        }

        echo '
        </div>
        ';
        printFooter();
    } else { // Got one of the STATUS_OK status codes
        // Check if two-factor authn is enabled and proceed accordingly
        if (twofactor::getEnabled() == 'none') {
            gotUserSuccess();
        } else {
            twofactor::printPage();
        }
    }
}

/************************************************************************
 * Function  : gotUserSuccess                                           *
 * This function is called after the user has been successfully         *
 * authenticated. In the case of two-factor authentication, the user    *
 * is first authenticated by the IdP, and then by the configured        *
 * two-factor authentication method.                                    *
 * If the 'status' session variable is STATUS_OK then it checks if we   *
 * have a new or changed user and prints that page as appropriate.      *
 * Otherwise it continues to the MainPage.                              *
 ************************************************************************/
function gotUserSuccess() {
    global $skin;

    $status = util::getSessionVar('status');

    // For the 'delegate' case (when there is a callbackuri in the 
    // current PHP session), if the skin has "forceremember" set, 
    // OR if the skin has "initialremember" set and there is no 
    // cookie for the current portal, then we should go to the main
    // page, skipping the New User and User Changed pages.
    $callbackuri = util::getSessionVar('callbackuri');
    if ((strlen($callbackuri) > 0) &&
        (($status == dbservice::$STATUS['STATUS_NEW_USER']) ||
         ($status == dbservice::$STATUS['STATUS_USER_UPDATED']))) {
        // Extra check for new users: see if any HTML entities
        // are in the user name. If so, send an email alert.
        $dn = util::getSessionVar('dn');
        $dn = reformatDN(preg_replace('/\s+email=.+$/','',$dn));
        $htmldn = util::htmlent($dn);
        if (strcmp($dn,$htmldn) != 0) {
            util::sendErrorAlert('New user DN contains HTML entities',
                "htmlentites(DN) = $htmldn\n");
        }

        // Check forcerememeber skin option to skip new user page
        $forceremember = $skin->getConfigOption('delegate','forceremember');
        if ((!is_null($forceremember)) && ((int)$forceremember == 1)) {
            $status = dbservice::$STATUS['STATUS_OK'];
        } else {
            $initialremember = 
                $skin->getConfigOption('delegate','initialremember');
            if ((!is_null($initialremember)) && ((int)$initialremember==1)){
                $portal = new portalcookie();
                $portallifetime = $portal->getPortalLifetime($callbackuri);
                if ((strlen($portallifetime)==0) || ($portallifetime==0)) {
                    $status = dbservice::$STATUS['STATUS_OK'];
                }
            }
        }
    }

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

/************************************************************************
 * Function   : printNewUserPage                                        *
 * This function prints out a notification page to new users showing    *
 * that this is the first time they have logged in with a particular    *
 * identity provider.                                                   *
 ************************************************************************/
function printNewUserPage() {
    global $log;

    $log->info('New User page.');

    $dn = util::getSessionVar('dn');
    $dn = reformatDN(preg_replace('/\s+email=.+$/','',$dn));

    printHeader('New User');

    echo '
    <div class="boxed">
    <br class="clear"/>
    <p>
    Welcome! Your new certificate subject is as follows. 
    </p>
    <p>
    <blockquote><tt>' , util::htmlent($dn) , '</tt></blockquote>
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
    than ' , util::getSessionVar('idpname') , '.
    </li>
    <li>You log on using a different ' , util::getSessionVar('idpname') , '
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
    printFormHead();
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
function printUserChangedPage() {
    global $log;

    $log->info('User IdP attributes changed.');

    $uid = util::getSessionVar('uid');
    $dbs = new dbservice();
    $dbs->getUser($uid);
    if (!($dbs->status & 1)) {  // STATUS_OK codes are even
        $idpname = $dbs->idp_display_name;
        $first   = $dbs->first_name;
        $last    = $dbs->last_name;
        $email   = $dbs->email;
        $dn      = $dbs->distinguished_name;
        $dn      = reformatDN(preg_replace('/\s+email=.+$/','',$dn));
        $dbs->getLastArchivedUser($uid);
        if (!($dbs->status & 1)) {  // STATUS_OK codes are even
            $previdpname = $dbs->idp_display_name;
            $prevfirst   = $dbs->first_name;
            $prevlast    = $dbs->last_name;
            $prevemail   = $dbs->email;
            $prevdn      = $dbs->distinguished_name;
            $prevdn      = reformatDN(
                               preg_replace('/\s+email=.+$/','',$prevdn));

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
                  <td>'.util::htmlent($prevfirst).'</td>
                  <td>'.util::htmlent($first).'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($last != $prevlast) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Last Name:</th>
                  <td>'.util::htmlent($prevlast).'</td>
                  <td>'.util::htmlent($last).'</td>
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
                    <td>' , util::htmlent($prevdn) , '</td>
                  </tr>
                  <tr>
                    <td>Current Subject DN:</td>
                    <td>' , util::htmlent($dn) , '</td>
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
            printFormHead();
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

/************************************************************************
 * Function   : generateP12                                             *
 * This function is called when the user clicks the "Get New            *
 * Certificate" button. It first reads in the password fields and       *
 * verifies that they are valid (i.e. they are long enough and match).  *
 * Then it gets a credential from the MyProxy server and converts that  *
 * certificate into a PKCS12 which is written to disk.  If everything   *
 * succeeds, the temporary pkcs12 directory and lifetime is saved to    *
 * the 'p12' PHP session variable, which is read later when the Main    *
 * Page HTML is shown.                                                  *
 ************************************************************************/
function generateP12() {
    global $log;

    /* Get the entered p12lifetime and p12multiplier and set the cookies. */
    list($minlifetime,$maxlifetime) = getMinMaxLifetimes('pkcs12',9516);
    $p12lifetime   = util::getPostVar('p12lifetime');
    $p12multiplier = util::getPostVar('p12multiplier');
    if (strlen($p12multiplier) == 0) {
        $p12multiplier = 1;  // For ECP, p12lifetime is in hours
    }
    $lifetime = $p12lifetime * $p12multiplier;
    if ($lifetime <= 0) { // In case user entered negative number
        $lifetime = $maxlifetime;
        $p12lifetime = $maxlifetime;
        $p12multiplier = 1;  // maxlifetime is in hours
    } elseif ($lifetime < $minlifetime) {
        $lifetime = $minlifetime;
        $p12lifetime = $minlifetime;
        $p12multiplier = 1;  // minlifetime is in hours
    } elseif ($lifetime > $maxlifetime) {
        $lifetime = $maxlifetime;
        $p12lifetime = $maxlifetime;
        $p12multiplier = 1;  // maxlifetime is in hours
    }
    util::setCookieVar('p12lifetime',$p12lifetime);
    util::setCookieVar('p12multiplier',$p12multiplier);
    util::setSessionVar('p12lifetime',$p12lifetime);
    util::setSessionVar('p12multiplier',$p12multiplier);

    /* Verify that the password is at least 12 characters long. */
    $password1 = util::getPostVar('password1');
    $password2 = util::getPostVar('password2');
    $p12password = util::getPostVar('p12password');  // For ECP clients
    if (strlen($p12password) > 0) {
        $password1 = $p12password;
        $password2 = $p12password;
    }
    if (strlen($password1) < 12) {   
        util::setSessionVar('p12error',
            'Password must have at least 12 characters.');
        return; // SHORT PASSWORD - NO FURTHER PROCESSING NEEDED!
    }

    /* Verify that the two password entry fields matched. */
    if ($password1 != $password2) {
        util::setSessionVar('p12error','Passwords did not match.');
        return; // MISMATCHED PASSWORDS - NO FURTHER PROCESSING NEEDED!
    }

    /* Set the port based on the Level of Assurance */
    $port = 7512;
    $loa = util::getSessionVar('loa');
    if ($loa == 'http://incommonfederation.org/assurance/silver') {
        $port = 7514;
    } elseif ($loa == 'openid') {
        $port = 7516;
    }
    /* Special hack for OSG - use SHA-1 version of MyProxy servers */
    if (strcasecmp(util::getSessionVar('cilogon_skin'),'OSG') == 0) {
        $port--;
    }

    $dn = util::getSessionVar('dn');
    if (strlen($dn) > 0) {
        /* Append extra info, such as 'skin', to be processed by MyProxy. */
        $myproxyinfo = util::getSessionVar('myproxyinfo');
        if (strlen($myproxyinfo) > 0) {
            $dn .= " $myproxyinfo";
        }
        /* Attempt to fetch a credential from the MyProxy server */
        $cert = getMyProxyCredential($dn,'','myproxy.cilogon.org',
            $port,$lifetime,'/var/www/config/hostcred.pem','');

        /* The 'openssl pkcs12' command is picky in that the private  *
         * key must appear BEFORE the public certificate. But MyProxy *
         * returns the private key AFTER. So swap them around.        */
        $cert2 = '';
        if (preg_match('/-----BEGIN CERTIFICATE-----([^-]+)' . 
                       '-----END CERTIFICATE-----[^-]*' . 
                       '-----BEGIN RSA PRIVATE KEY-----([^-]+)' .
                       '-----END RSA PRIVATE KEY-----/',$cert,$match)) {
            $cert2 = "-----BEGIN RSA PRIVATE KEY-----" .
                     $match[2] . "-----END RSA PRIVATE KEY-----\n".
                     "-----BEGIN CERTIFICATE-----" .
                     $match[1] . "-----END CERTIFICATE-----";
        }

        if (strlen($cert2) > 0) { // Successfully got a certificate!
            /* Create a temporary directory in /var/www/html/pkcs12/ */
            $tdirparent = util::getServerVar('DOCUMENT_ROOT') . '/pkcs12/';
            $polonum = '3';   // Prepend the polo? number to directory
            if (preg_match('/(\d+)\./',php_uname('n'),$polomatch)) {
                $polonum = $polomatch[1];
            }
            $tdir = util::tempDir($tdirparent,$polonum);
            $p12dir = str_replace($tdirparent,'',$tdir);
            $p12file = $tdir . '/usercred.p12';

            /* Call the openssl pkcs12 program to convert certificate */
            exec('/bin/env ' .
                 'CILOGON_PKCS12_PW=' . escapeshellarg($password1) . ' ' .
                 '/usr/bin/openssl pkcs12 -export ' .
                 '-passout env:CILOGON_PKCS12_PW ' .
                 "-out $p12file " .
                 '<<< ' . escapeshellarg($cert2)
                );

            /* Verify the usercred.p12 file was actually created */
            $size = @filesize($p12file);
            if (($size !== false) && ($size > 0)) {
                $p12link = 'https://' . getMachineHostname() . '/pkcs12/' .
                           $p12dir . '/usercred.p12';
                $p12 = (time()+300) . " " . $p12link;
                util::setSessionVar('p12',$p12);
                $log->info('Generated New User Certificate="'.$p12link.'"');
            } else { // Empty or missing usercred.p12 file - shouldn't happen!
                util::setSessionVar('p12error',
                    'Error creating certificate. Please try again.');
                util::deleteDir($tdir); // Remove the temporary directory
                $log->info("Error creating certificate - missing usercred.p12");
            }
        } else { // The myproxy-logon command failed - shouldn't happen!
            util::setSessionVar('p12error',
                'Error! MyProxy unable to create certificate.');
            $log->info("Error creating certificate - myproxy-logon failed");
        }
    } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
        util::setSessionVar('p12error',
            'Missing username. Please enable cookies.');
        $log->info("Error creating certificate - missing dn session variable");
    }
}

/************************************************************************
 * Function   : getMachineHostname                                      *
 * Returns    : The full machine-specific hostname of this host.        *
 * This function is utilized in the formation of the URL for the PKCS12 *
 * credential download link.  It returns a combination of the local     *
 * machine name (the first part of the 'uname') and the HTTP hostname   *
 * (as defined by HOSTNAME in the util.php file).  This usually results *
 * in something like 'polo1.cilogon.org', since polo1 is the local      *
 * machine name, and cilogon.org is the HTTP_HOST name.                 *
 ************************************************************************/
function getMachineHostname() {
    $unamesplit = preg_split('/\./',php_uname('n'));
    $hostname = @$unamesplit[0];
    $serversplit = preg_split('/\./',HOSTNAME);
    if (count($serversplit) > 2) { // Delete the first component if more than 2
        unset($serversplit[0]);
    }
    $url = $hostname . '.' . implode('.',$serversplit);
    return $url;
}

/************************************************************************
 * Function   : getLogOnButtonText                                      *
 * Returns    : The text of the "Log On" button for the WAYF, as        *
 *              configured for the skin.  Defaults to "Log On".         *
 * This function checks the current skin to see if <logonbuttontext>    *
 * has been configured.  If so, it returns that value.  Otherwise,      *
 * it returns "Log On".                                                 *
 ************************************************************************/
function getLogOnButtonText() {
    global $skin;

    $retval = 'Log On';
    $lobt = $skin->getConfigOption('logonbuttontext');
    if (!is_null($lobt))  {
        $retval = (string)$lobt;
    }
    return $retval;
}

/************************************************************************
 * Function   : getSerialStringFromDN                                   *
 * Parameter  : The certificate subject DN (typically found in the      *
 *              session 'dn' variable)                                  *
 * Returns    : The serial string extracted from the subject DN, or     *
 *              empty string if DN is empty or wrong format.            *
 * This function takes in a CILogon subject DN and returns just the     *
 * serial string part (e.g., A325). This function is needed since the   *
 * serial_string is not stored in the PHP session as a separate         *
 * variable since it is always available in the 'dn' session variable.  *
 ************************************************************************/
function getSerialStringFromDN($dn) { 
    $serial = ''; // Return empty string upon error

    // Strip off the email address, if present
    $dn = preg_replace('/\s+email=.+$/','',$dn);
    // Find the "CN=" entry
    if (preg_match('%/DC=org/DC=cilogon/C=US/O=.*/CN=(.*)%',$dn,$match)) {
        $cn = $match[1];
        if (preg_match('/\s+([^\s]+)$/',$cn,$match)) {
            $serial = $match[1];
        }
    }
    return $serial;
}

/************************************************************************
 * Function   : getEmailFromDN                                          *
 * Parameter  : The certificate subject DN (typically found in the      *
 *              session 'dn' variable)                                  *
 * Returns    : The email address extracted from the subject DN, or     *
 *              empty string if DN is empty or wrong format.            *
 * This function takes in a CILogon subject DN and returns just the     *
 * email address part. This function is needed since the email address  *
 * is not stored in the PHP session as a separate variable since it is  *
 * always available in the 'dn' session variable.                       *
 ************************************************************************/
function getEmailFromDN($dn) {
    $email = ''; // Return empty string upon error
    if (preg_match('/\s+email=(.+)$/',$dn,$match)) {
        $email = $match[1];
    }
    return $email;
}

/************************************************************************
 * Function   : reformatDN                                              *
 * Parameter  : The certificate subject DN (without the email=... part) *
 * Returns    : The certificate subject DN transformed according to     *
 *              the value of the <dnformat> skin config option.         *
 * This function takes in a certificate subject DN with the email=...   * 
 * part already removed. It checks the skin to see if <dnformat> has    *
 * been set. If so, it reformats the DN appropriately.                  *
 ************************************************************************/
function reformatDN($dn) {
    global $skin;

    $newdn = $dn;
    $dnformat = (string)$skin->getConfigOption('dnformat');
    if (!is_null($dnformat)) {
        if (($dnformat == 'rfc2253') &&
            (preg_match('%/DC=(.*)/DC=(.*)/C=(.*)/O=(.*)/CN=(.*)%',
                        $dn,$match))) {
            array_shift($match);
            require_once('Net/LDAP2/Util.php');
            $m = array_reverse(Net_LDAP2_Util::escape_dn_value($match));
            $newdn = "CN=$m[0],O=$m[1],C=$m[2],DC=$m[3],DC=$m[4]";
        }
    }
    return $newdn;
}

/************************************************************************
 * Function   : checkForceSkin                                          *
 * Parameter  : The entityId of the user-selected IdP.                  *
 * Side Effect: Sets the "cilogon_skin" session variable if needed.     *
 * This function checks the forceskin.txt file to see if the passed-in  *
 * entityId requires the use of a particular skin. This file has lines  *
 * consisting of "entityId skinname" pairs. An entry in this file means *
 * that when a user selects that IdP, he is forced to use the           *
 * specified skin. This is accomplished by setting the cilogon_skin     *
 * session variable.                                                    *
 ************************************************************************/
function checkForceSkin($entityId) {
    global $skin;

    $forceskinfile = '/var/www/html/include/forceskin.txt';
    $idps = util::readArrayFromFile($forceskinfile);
    if (array_key_exists($entityId,$idps)) {
        util::setSessionVar('cilogon_skin',$idps[$entityId]);
        $skin->__construct(); // Need to reinitialize the skinname
    }
}

/************************************************************************
 * Function   : getMinMaxLifetimes                                      *
 * Parameters : (1) The XML section block from which to read the        *
 *                  minlifetime and maxlifetime values. Can be one of   *
 *                  the following: 'pkcs12', 'gsca', or 'delegate'.     *
 *              (2) Default maxlifetime (in hours) for the credential.  *
 * Returns    : An array consisting of two entries: the minimum and     *
 *              maximum lifetimes (in hours) for a credential.          *
 * This function checks the skin's configuration to see if either or    *
 * both of minlifetime and maxlifetime in the specified config.xml      *
 * block have been set. If not, default to minlifetime of 1 (hour) and  *
 * the specified defaultmaxlifetime.                                    *
 ************************************************************************/
function getMinMaxLifetimes($section,$defaultmaxlifetime) {
    global $skin;

    $minlifetime = 1;    // Default minimum lifetime is 1 hour
    $maxlifetime = $defaultmaxlifetime;
    $skinminlifetime = $skin->getConfigOption($section,'minlifetime');
    // Read the skin's minlifetime value from the specified section
    if ((!is_null($skinminlifetime)) && ((int)$skinminlifetime > 0)) {
        $minlifetime = max($minlifetime,(int)$skinminlifetime);
        // Make sure $minlifetime is less than $maxlifetime;
        $minlifetime = min($minlifetime,$maxlifetime);
    }
    // Read the skin's maxlifetime value from the specified section
    $skinmaxlifetime = $skin->getConfigOption($section,'maxlifetime');
    if ((!is_null($skinmaxlifetime)) && ((int)$skinmaxlifetime) > 0) {
        $maxlifetime = min($maxlifetime,(int)$skinmaxlifetime);
        // Make sure $maxlifetime is greater than $minlifetime
        $maxlifetime = max($minlifetime,$maxlifetime);
    }

    return array($minlifetime,$maxlifetime);
}

?>
