<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\TwoFactor;
use CILogon\Service\MyProxy;
use CILogon\Service\PortalCookie;
use CILogon\Service\DBService;
use CILogon\Service\OAuth2Provider;
use CILogon\Service\Loggit;
use Net_LDAP2_Util;

// If needed, set the 'Notification' banner text to a non-empty value
// and uncomment the 'define' statement in order to display a
// notification box at the top of each page.
/*
define('BANNER_TEXT',
       'We are currently experiencing problems issuing certificates. We are
       working on a solution. We apologize for the inconvenience.'
);
*/

/**
 * Content
 */
class Content
{
    /**
     * printHeader
     *
     * This function should be called to print out the main HTML header
     * block for each web page.  This gives a consistent look to the site.
     * Any style changes should go in the cilogon.css file.
     *
     * @param string $title The text in the window's titlebar
     * @param string $extra Optional extra text to go in the <head> block
     * @param bool $csrfcookie Set the CSRF and CSRFProtetion cookies.
     *        Defaults to true.
     */
    public static function printHeader($title = '', $extra = '', $csrfcookie = true)
    {
        if ($csrfcookie) {
            $csrf = Util::getCsrf();
            $csrf->setTheCookie();
            // Set the CSRF cookie used by GridShib-CA
            Util::setCookieVar('CSRFProtection', $csrf->getTokenValue(), 0);
        }

        // Find the 'Powered By CILogon' image if specified by the skin
        $poweredbyimg = "/images/poweredbycilogon.png";
        $skin = Util::getSkin();
        $skinpoweredbyimg = (string)$skin->getConfigOption('poweredbyimg');
        if ((!is_null($skinpoweredbyimg)) &&
            (strlen($skinpoweredbyimg) > 0) &&
            (is_readable('/var/www/html' . $skinpoweredbyimg))) {
            $poweredbyimg = $skinpoweredbyimg;
        }

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
        <head><title>' , $title , '</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="initial-scale=0.6" />
        <link rel="stylesheet" type="text/css" href="/include/cilogon.css" />
        ';

        $skin->printSkinLink();

        $deployjava = $skin->getConfigOption('deployjava');
        if ((!is_null($deployjava)) && ((int)$deployjava == 1)) {
            echo '<script type="text/javascript" src="/include/deployJava.js"></script>';
        }

        echo '
        <script type="text/javascript" src="/include/cilogon.js"></script>
        ' ;

        echo '
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

        $providerId = Util::getSessionVar('idp');
        if ($providerId == "urn:mace:incommon:idp.protectnetwork.org") {
            echo '
            <div class="noticebanner">Availability of the ProtectNetwork
            Identity Provider (IdP) will end after December 2014. Please
            consider using another IdP.</div>
            ';
        }
    }

    /**
     * printFooter
     *
     * This function should be called to print out the closing HTML block
     * for each web page.
     *
     * @param string $footer Optional extra text to be output before the
     * closing footer div.
     */
    public static function printFooter($footer = '')
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

    /**
     * printPageHeader
     *
     * This function prints a fancy formatted box with a single line of
     * text, suitable for a titlebox on each web page (to appear just below
     * the page banner at the very top). It prints a gradent border around
     * the four edges of the box and then outlines the inner box.
     *
     * @param string $text The text string to appear in the titlebox.
     */
    public static function printPageHeader($text)
    {
        echo '
        <div class="titlebox">' , $text , '
        </div>
        ';
    }

    /**
     * printFormHead
     *
     * This function prints out the opening <form> tag for displaying
     * submit buttons.  The first parameter is used for the 'action' value
     * of the <form>.  If omitted, getScriptDir() is called to get the
     * location of the current script.  This function outputs a hidden csrf
     * field in the form block.  If the second parameter is given and set
     * to true, then an additional hidden input element is output to be
     * utilized by the GridShib-CA client.
     *
     * @param string $action (Optional) The value of the form's 'action'
     *        parameter. Defaults to getScriptDir().
     * @param string $method (Optional) The <form> 'method', one of 'get' or
     *        'post'. Defaults to 'post'.
     * @param bool $gsca  (Optional) True if extra hidden tags should be
     *        output for the GridShib-CA client application.
     *        Defaults to false.
     */
    public static function printFormHead(
        $action = '',
        $method = 'post',
        $gsca = false
    ) {
        static $formnum = 0;

        if (strlen($action) == 0) {
            $action = Util::getScriptDir();
        }

        echo '
        <form action="' , $action , '" method="' , $method , '"
         autocomplete="off" id="form' , sprintf("%02d", ++$formnum) , '">
        ';
        $csrf = Util::getCsrf();
        echo $csrf->hiddenFormElement();

        if ($gsca) {
            // Output hidden form element for GridShib-CA
            echo '
            <input type="hidden" name="CSRFProtection" value="' .
            $csrf->getTokenValue() . '" />
            ';
        }
    }

    /**
     * printWAYF
     *
     * This function prints the list of IdPs in a <select> form element
     * which can be printed on the main login page to allow the user to
     * select 'Where Are You From?'.  This function checks to see if a
     * cookie for the 'providerId' had been set previously, so that the
     * last used IdP is selected in the list.
     *
     * @param bool $showremember (Optional) Show the 'Remember this
     *        selection' checkbox? Defaults to true.
     * @param bool $incommonidps (Optional) Show all InCommon IdPs in
     *        selection list? Defaults to false, which means show
     *        only whitelisted IdPs.
     */
    public static function printWAYF($showremember = true, $incommonidps = false)
    {
        $whiteidpsfile = '/var/www/html/include/whiteidps.txt';
        $helptext = 'Check this box to bypass the welcome page on ' .
            'subsequent visits and proceed directly to the selected ' .
            'identity provider. You will need to clear your browser\'s ' .
            'cookies to return here.';
        $searchtext = "Enter characters to search for in the list above.";

        // Get an array of IdPs
        $idps = static::getCompositeIdPList($incommonidps);

        $skin = Util::getSkin();

        // Check if the user had previously selected an IdP from the list.
        // First, check the portalcookie, then the 'normal' cookie.
        $keepidp = '';
        $providerId = '';
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if (strlen($pn) > 0) {
            $keepidp    = $pc->get('keepidp');
            $providerId = $pc->get('providerId');
        } else {
            $keepidp    = Util::getCookieVar('keepidp');
            $providerId = Util::getCookieVar('providerId');
        }

        // Make sure previously selected IdP is in list of available IdPs.
        if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
            $providerId = '';
        }

        // If no previous providerId, get from skin, or default to Google.
        if (strlen($providerId) == 0) {
            $initialidp = (string)$skin->getConfigOption('initialidp');
            if ((!is_null($initialidp)) && (isset($idps[$initialidp]))) {
                $providerId = $initialidp;
            } else {
                $providerId = Util::getAuthzUrl('Google');
            }
        }

        // Check if an OIDC client selected an IdP for the transaction.
        // If so, verify that the IdP is in the list of available IdPs.
        $useselectedidp = false;
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        if (isset($clientparams['selected_idp'])) {
            $selected_idp = $clientparams['selected_idp'];
            if ((strlen($selected_idp) > 0) && (isset($idps[$selected_idp]))) {
                $useselectedidp = true;
                $providerId = $selected_idp;
                // Update the IdP selection list to show only this one IdP
                $idps = array($selected_idp => $idps[$selected_idp]);
            }
        }

        echo '
        <br />
        <div class="actionbox"';

        if (Util::getSessionVar('showhelp') == 'on') {
            echo ' style="width:92%;"';
        }

        echo '>
        <table class="helptable">
        <tr>
        <td class="actioncell">

          <form action="' , Util::getScriptDir() , '" method="post">
          <fieldset>

          <p>' , ($useselectedidp ? 'Selected' : 'Select An') ,
          ' Identity Provider:</p>
          ';

          // See if the skin has set a size for the IdP <select> list
          $selectsize = 4;
        $ils = $skin->getConfigOption('idplistsize');
        if ((!is_null($ils)) && ((int)$ils > 0)) {
            $selectsize = (int)$ils;
        }

        // When selected_idp is used, list size should always be 1.
        if ($useselectedidp) {
            $selectsize = 1;
        }

        echo '
          <p>
          <select name="providerId" id="providerId" size="' , $selectsize , '"
           onkeypress="enterKeySubmit(event)" ondblclick="doubleClickSubmit()"' ,
           // Hide the drop-down arrow in Firefox and Chrome
          ($useselectedidp ?
              'style="-moz-appearance:none;-webkit-appearance:none"' : '') ,
           '>
        ';

        foreach ($idps as $entityId => $idpName) {
            echo '    <option value="' , $entityId , '"';
            if ($entityId == $providerId) {
                echo ' selected="selected"';
            }
            echo '>' , Util::htmlent($idpName) , '</option>' , "\n    ";
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
            ((strlen($keepidp) > 0) ? 'checked="checked" ' : '') ,
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

        echo Util::getCsrf()->hiddenFormElement();

        $lobtext = static::getLogOnButtonText();

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

        $logonerror = Util::getSessionVar('logonerror');
        if (strlen($logonerror) > 0) {
            echo "<p class=\"logonerror\">$logonerror</p>";
            Util::unsetSessionVar('logonerror');
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

        if (Util::getSessionVar('showhelp') == 'on') {
            echo '
          <td class="helpcell">
          <div>
          ';

            if ($incommonidps) { // InCommon IdPs only means running from /testidp/
                echo '
                <p>
                CILogon facilitates secure access to CyberInfrastructure
                (<acronym title="CyberInfrastructure">CI</acronym>). In
                order to test your identity provider with the CILogon Service,
                you must first Log On. If your preferred identity provider is
                not listed, please contact <a
                href="mailto:help@cilogon.org">help@cilogon.org</a>, and
                we will try to add your identity provider in the future.
                </p>
                ';
            } else { // If not InCommon only, print help text for OpenID providers.
                echo '
                <p>
                CILogon facilitates secure access to CyberInfrastructure
                (<acronym title="CyberInfrastructure">CI</acronym>).
                In order to use the CILogon Service, you must first select
                an identity provider. An identity provider (IdP) is an
                organization where you have an account and can log on
                to gain access to online services.
                </p>
                <p>
                If you are a faculty, staff, or student member of a university
                or college, please select it for your identity provider.
                If your school is not listed, please contact <a
                href="mailto:help@cilogon.org">help@cilogon.org</a>, and we will
                try to add your school in the future.
                </p>
                ';

                $googleauthz = Util::getAuthzUrl('Google');
                if ((isset($idps[$googleauthz])) &&
                    ($skin->idpAvailable($googleauthz))) {
                    echo '
                  <p>
                  If you have a <a target="_blank"
                  href="https://myaccount.google.com">Google</a>
                  account, you can select it for
                  authenticating to the CILogon Service.
                  </p>
                  ';
                }
                $githubauthz = Util::getAuthzUrl('GitHub');
                if ((isset($idps[$githubauthz])) &&
                    ($skin->idpAvailable($githubauthz))) {
                    echo '
                  <p>
                  If you have a <a target="_blank"
                  href="https://github.com/settings/profile">GitHub</a>
                  account, you can select it for
                  authenticating to the CILogon Service.
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

    /**
     * printTwoFactorBox
     *
     * This function prints the 'Manage Two-Factor' box on the main page.
     */
    public static function printTwoFactorBox()
    {
        $managetwofactortext = 'Enable or disable two-factor authentication for your account';

        echo '
        <div class="twofactoractionbox"';

        $style = ''; // Might add extra CSS to the twofactoractionbox
        if (Util::getSessionVar('showhelp') == 'on') {
            $style .= "width:92%;";
        }
        if (TwoFactor::getEnabled() != 'none') {
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

        static::printFormHead();

        $twofactorname = TwoFactor::getEnabledName();
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

        if (Util::getSessionVar('showhelp') == 'on') {
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

    /**
     * printTwoFactorPage
     *
     * This function prints out the Manage Two-Factor Authentication page.
     * Display of which two-factor types are available to the user is
     * controlled by CSS. From this page, the user can Enable or Disable
     * various two-factor authentication methods.
     */
    public static function printTwoFactorPage()
    {
        Util::setSessionVar('stage', 'managetwofactor'); // For Show/Hide Help button

        static::printHeader('Manage Two-Factor Authentication');

        $twofactorname = TwoFactor::getEnabledName();

        echo '
        <div class="boxed">
        ';
        static::printHelpButton();
        echo'
        <h2>Two-Factor Authentication</h2>
        <div class="actionbox">
        <p><b>Two-Factor Authentication:</b></p>
        <p>' , $twofactorname , '</p>
        </div> <!-- actionbox -->
        ';

        if (Util::getSessionVar('showhelp') == 'on') {
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
        (TwoFactor::isEnabled('ga') ? ' style="display:table-row;"' : '') ,
        '>
        <th>Google Authenticator</th>
        <td>
        ';
        static::printFormHead();
        echo '
        <input type="hidden" name="twofactortype" value="ga" />
        <input type="submit" name="submit" class="submit" value="' ,
        (TwoFactor::isEnabled('ga') ? 'Disable' : 'Enable') ,
        '" />
        </form>
        </td>
        </tr>
        ';

        if (Util::getSessionVar('showhelp') == 'on') {
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
        (TwoFactor::isEnabled('duo') ? '
            style="display:table-row;border-top-width:1px"' : '') ,
        '>
        <th>Duo Security</th>
        <td>
        ';
        static::printFormHead();
        echo '
        <input type="hidden" name="twofactortype" value="duo" />
        <input type="submit" name="submit" class="submit" value="' ,
        (TwoFactor::isEnabled('duo') ? 'Disable' : 'Enable') ,
        '" />
        </form>
        </td>
        </tr>
        ';

        if (Util::getSessionVar('showhelp') == 'on') {
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
        static::printFormHead();
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
        static::printFooter();
    }

    /**
     * handleEnableDisableTwoFactor
     *
     * This function is called when the user clicks either an 'Enable' or
     * 'Disable' button from the Manage Two-Factor page, or when the user
     * clicks 'Verify' on the Google Authenticator Registration page.
     * The passed-in parameter tells which type of button was pressed.
     * If 'Disable', then simply set 'enabled=none' in the datastore and
     * display the Manage Two-Factor page again. If 'Enable' or 'Verify',
     * check the 'twofactortype' hidden form variable for which two-factor
     * authentication method is to be enabled. Then print out that
     * two-factor page. Note that TwoFactor::printPage() does the work of
     * figuring out if the user has registered the phone yet or not, and
     * displays the appropriate page.
     *
     * @param bool $enable (Optional) True for 'enable', false for 'disable'.
     *        Defaults to false (for 'disable').
     */
    public static function handleEnableDisableTwoFactor($enable = false)
    {
        if ($enable) {
            $twofactortype = Util::getPostVar('twofactortype');
            if (strlen($twofactortype) > 0) {
                TwoFactor::printPage($twofactortype);
            } else {
                printLogonPage();
            }
        } else { // 'Disable' clicked
            // Check if the user clicked 'Disable Two-Factor' and send email
            if (Util::getPostVar('missingphone') == '1') {
                // Make sure two-factor was enabled
                $twofactorname = TwoFactor::getEnabledName();
                if ($twofactorname != 'Disabled') {
                    $email = static::getEmailFromDN(Util::getSessionVar('dn'));
                    if (strlen($email) > 0) { // Make sure email address exists
                        TwoFactor::sendPhoneAlert(
                            'Forgot Phone for Two-Factor Authentication',
                            'While using the CILogon Service, you (or someone using your account)
indicated that you forgot your phone by clicking the "Disable Two-Factor"
button. This disabled two-factor authentication by "' . $twofactorname . '"
using "' . Util::getSessionVar('idpname') . '" as your Identity Provider.

If you did not disable two-factor authentication, please send email to
"help@cilogon.org" to report this incident.',
                            $email
                        );
                    } else { // No email address is bad - send error alert
                        Util::sendErrorAlert(
                            'Missing Email Address',
                            'When attempting to send an email notification to a user who clicked the
"Disable Two-Factor" button because of a forgotten phone, the CILogon
Service was unable to find an email address. This should never occur and
is probably due to a badly formatted "dn" string.'
                        );
                    }
                }
            }

            // Finally, disable two-factor authentication
            TwoFactor::setDisabled();
            TwoFactor::write();
            static::printTwoFactorPage();
        }
    }

    /**
     * handleILostMyPhone
     *
     * This function is called when the user clicks the 'I Lost My Phone'
     * button.  It sends email to the user AND to alerts because Duo
     * Security requires that a sysadmin unregister the phone for the user.
     * It then unsets the 'twofactor' session variable, and writes it to
     * the datastore, effectively wiping out all two-factor information for
     * the user.
     */
    public static function handleILostMyPhone()
    {
        // First, send email to user
        $email = static::getEmailFromDN(Util::getSessionVar('dn'));
        if (strlen($email) > 0) { // Make sure email address exists
            TwoFactor::sendPhoneAlert(
                'Lost Phone for Two-Factor Authentication',
                'While using the CILogon Service, you (or someone using your account)
indicated that you lost your phone by clicking the "I Lost My Phone"
button. This removed two-factor authentication for your account when
using "' . Util::getSessionVar('idpname') . '" as your Identity Provider.

System administrators have been notified of this incident. If you require
further assistance, please send email to "help@cilogon.org".',
                $email
            );
        } else { // No email address is bad - send error alert
            Util::sendErrorAlert(
                'Missing Email Address',
                'When attempting to send an email notification to a user who clicked the
"I Lost My Phone" button, the CILogon Service was unable to find an
email address. This should never occur and is probably due to a badly
formatted "dn" string.'
            );
        }

        // Next, send email to sysadmin
        $errortext = 'A user clicked the "I Lost My Phone" button. ';
        if (TwoFactor::isRegistered('duo')) {
            $duoconfig = new DuoConfig();
            $errortext .= '

The user had registered "Duo Security" as one of the two-factor methods.
Since there is no way for the CILogon Service to UNregister this method
at the Duo Security servers, a system administrator will need to delete
this user\'s registration at https://' . $duoconfig->param['host'] . ' .';
        }
        Util::sendErrorAlert('Two-Factor Authentication Disabled', $errortext);

        // Finally, disable and unregister two-factor authentication
        Util::unsetSessionVar('twofactor');
        TwoFactor::write();
        static::printTwoFactorPage();
    }

    /**
     * handleGoogleAuthenticatorLogin
     *
     * This function is called when the user enters a one time password as
     * generated by the Google Authenticator app. This can occur (1) when
     * the user is first configuring GA two-factor and (2) when the user
     * logs in to the CILogon Service and GA is enabled. If the OTP is
     * correctly validated, the gotUserSuccess() function is called to
     * show output to the user.
     */
    public static function handleGoogleAuthenticatorLogin()
    {
        $gacode = Util::getPostVar('gacode');
        if ((strlen($gacode) > 0) && (TwoFactor::isGACodeValid($gacode))) {
            static::gotUserSuccess();
        } else {
            TwoFactor::printPage('ga');
        }
    }

    /**
     * handleDuoSecurityLogin
     *
     * This function is called when the user authenticates with Duo
     * Security. If the Duo authentication is valid, then the
     * gotUserSuccess() function is then called to show output to the user.
     */
    public static function handleDuoSecurityLogin()
    {
        $sig_response = Util::getPostVar('sig_response');
        if ((strlen($sig_response) > 0) &&
            (TwoFactor::isDuoCodeValid($sig_response))) {
            static::gotUserSuccess();
        } else {
            TwoFactor::printPage('duo');
        }
    }

    /**
     * handleLogOnButtonClicked
     *
     * This function is called when the user clicks the 'Log On' button
     * on the IdP selection page. It checks to see if the 'Remember this
     * selection' checkbox was checked and sets a cookie appropriately. It
     * also sets a cookie 'providerId' so the last chosen IdP will be
     * selected the next time the user visits the site. The function then
     * calls the appropriate 'redirectTo...' function to send the user
     * to the chosen IdP.
     */
    public static function handleLogOnButtonClicked()
    {
        // Get the list of currently available IdPs
        $idps = static::getCompositeIdPList();

        // Set the cookie for keepidp if the checkbox was checked
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if (strlen(Util::getPostVar('keepidp')) > 0) {
            if (strlen($pn) > 0) {
                $pc->set('keepidp', 'checked');
            } else {
                Util::setCookieVar('keepidp', 'checked');
            }
        } else {
            if (strlen($pn) > 0) {
                $pc->set('keepidp', '');
            } else {
                Util::unsetCookieVar('keepidp');
            }
        }

        // Get the user-chosen IdP from the posted form
        $providerId = Util::getPostVar('providerId');

        // Set the cookie for the last chosen IdP and redirect to it if in list
        if ((strlen($providerId) > 0) && (isset($idps[$providerId]))) {
            if (strlen($pn) > 0) {
                $pc->set('providerId', $providerId);
                $pc->write();
            } else {
                Util::setCookieVar('providerId', $providerId);
            }
            $providerName = Util::getAuthzIdP($providerId);
            if (in_array($providerName, [ 'Google' , 'GitHub' ])) {
                // Log in with Google or GitHub
                static::redirectToGetOAuth2User($providerId);
            } else { // Use InCommon authn
                static::redirectToGetShibUser($providerId);
            }
        } else { // IdP not in list, or no IdP selected
            if (strlen($pn) > 0) {
                $pc->set('providerId', '');
                $pc->write();
            } else {
                Util::unsetCookieVar('providerId');
            }
            Util::setSessionVar('logonerror', 'Please select a valid IdP.');
            printLogonPage();
        }
    }

    /**
     * handleHelpButtonClicked
     *
     * This function is called when the user clicks on the 'Show Help' /
     * 'Hide Help' button in the upper right corner of the page. It toggles
     * the 'showhelp' session variable and redisplays the appropriate page
     * with help now shown or hidden.
     */
    public static function handleHelpButtonClicked()
    {
        if (Util::getSessionVar('showhelp') == 'on') {
            Util::unsetSessionVar('showhelp');
        } else {
            Util::setSessionVar('showhelp', 'on');
        }

        $stage = Util::getSessionVar('stage');
        if (static::verifyCurrentUserSession()) {
            if ($stage == 'main') {
                printMainPage();
            } elseif ($stage == 'managetwofactor') {
                static::printTwoFactorPage();
            } else {
                printLogonPage();
            }
        } else {
            printLogonPage();
        }
    }

    /**
     * handleNoSubmitButtonClicked
     *
     * This function is the 'default' case when no 'submit' button has been
     * clicked, or if the submit session variable is not set. It checks
     * to see if either the <forceinitialidp> option is set, or if the
     * 'Remember this selection' checkbox was previously checked. If so,
     * then rediret to the appropriate IdP. Otherwise, print the main
     * Log On page.
     */
    public static function handleNoSubmitButtonClicked()
    {
        $providerId = '';
        $keepidp = '';
        $selected_idp = '';
        $redirect_uri = '';
        $callbackuri = Util::getSessionVar('callbackuri');
        $readidpcookies = true;  // Assume config options are not set
        $skin = Util::getSkin();
        $forceinitialidp = (int)$skin->getConfigOption('forceinitialidp');
        $initialidp = (string)$skin->getConfigOption('initialidp');

        // If this is a OIDC transaction, get the selected_idp and
        // redirect_uri parameters from the session var clientparams.
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        if (isset($clientparams['selected_idp'])) {
            $selected_idp = $clientparams['selected_idp'];
        }
        if (isset($clientparams['redirect_uri'])) {
            $redirect_uri = $clientparams['redirect_uri'];
        }

        // If the <forceinitialidp> option is set, use either the
        // <initialidp> or the 'selected_idp' as the providerId, and
        // use <forceinitialidp> as keepIdp. Otherwise, read the
        // cookies 'providerId' and 'keepidp'.
        if (($forceinitialidp == 1) &&
            ((strlen($initialidp) > 0) || (strlen($selected_idp) > 0))) {
            // If the <allowforceinitialidp> option is set, then make sure
            // the callback / redirect uri is in the portal list.
            $afii=$skin->getConfigOption('portallistaction', 'allowforceinitialidp');
            if ((is_null($afii)) || // Option not set, no need to check portal list
                (((int)$afii == 1) &&
                  (($skin->inPortalList($callbackuri)) ||
                   ($skin->inPortalList($redirect_uri))))) {
                // 'selected_idp' takes precedence over <initialidp>
                if (strlen($selected_idp) > 0) {
                    $providerId = $selected_idp;
                } else {
                    $providerId = $initialidp;
                }
                $keepidp = $forceinitialidp;
                $readidpcookies = false; // Don't read in the IdP cookies
            }
        }

        // <initialidp> options not set, or portal not in portal list?
        // Get idp and 'Remember this selection' from cookies instead.
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if ($readidpcookies) {
            // Check the portalcookie first, then the 'normal' cookies
            if (strlen($pn) > 0) {
                $keepidp    = $pc->get('keepidp');
                $providerId = $pc->get('providerId');
            } else {
                $keepidp    = Util::getCookieVar('keepidp');
                $providerId = Util::getCookieVar('providerId');
            }
        }

        // If both 'keepidp' and 'providerId' were set (and the
        // providerId is a whitelisted IdP or valid OpenID provider),
        // then skip the Logon page and proceed to the appropriate
        // getuser script.
        if ((strlen($providerId) > 0) && (strlen($keepidp) > 0)) {
            // If selected_idp was specified at the OIDC authorize endpoint,
            // make sure that it matches the saved providerId. If not,
            // then show the Logon page and uncheck the keepidp checkbox.
            if ((strlen($selected_idp) == 0) || ($selected_idp == $providerId)) {
                $providerName = Util::getAuthzIdP($providerId);
                if (in_array($providerName, [ 'Google', 'GitHub' ])) {
                    // Log in with Google or GitHub
                    static::redirectToGetOAuth2User($providerId);
                } elseif (Util::getIdpList()->exists($providerId)) {
                    // Log in with InCommon
                    static::redirectToGetShibUser($providerId);
                } else { // $providerId not in whitelist
                    if (strlen($pn) > 0) {
                        $pc->set('providerId', '');
                        $pc->write();
                    } else {
                        Util::unsetCookieVar('providerId');
                    }
                    printLogonPage();
                }
            } else { // selected_idp does not match saved providerId
                if (strlen($pn) > 0) {
                    $pc->set('keepidp', '');
                    $pc->write();
                } else {
                    Util::unsetCookieVar('keepidp');
                }
                printLogonPage();
            }
        } else { // One of providerId or keepidp was not set
            printLogonPage();
        }
    }

    /**
     * printIcon
     *
     * This function prints out the HTML for the little icons which can
     * appear inline with other information.  This is accomplished via the
     * use of wrapping the image in a <span> tag.
     *
     * @param string $icon: The prefix of the '...Icon.png' image to be
     *        shown. E.g., to show 'errorIcon.png', pass in 'error'.
     * @param string $popuptext (Optionals) The popup 'title' text to be
     *        displayed when the  mouse cursor hovers over the icon.
     *        Defaults to empty string.
     * @param string $class (Optionals) A CSS class for the icon. Will be
     *        appended after the 'helpcursor' class. Defaults to empty
     *        string.
     */
    public static function printIcon($icon, $popuptext = '', $class = '')
    {
        echo '<span';
        if (strlen($popuptext) > 0) {
            echo ' class="helpcursor ' , $class , '" title="' , $popuptext , '"';
        }
        echo '>&nbsp;<img src="/images/' , $icon , 'Icon.png"
              alt="&laquo; ' , ucfirst($icon) , '"
              width="14" height="14" /></span>';
    }

    /**
     * printHelpButton
     *
     * This function prints the 'Show Help' / 'Hide Help' button in the
     * upper-right corner of the main box area on the page.
     */
    public static function printHelpButton()
    {
        echo '
        <div class="helpbutton">
        ';

        static::printFormHead();

        echo '
          <input type="submit" name="submit" class="helpbutton" value="' ,
          (Util::getSessionVar('showhelp')=='on' ? 'Hide':'Show') , '&#10; Help " />
          </form>
        </div>
        ';
    }

    /**
     * verifyCurrentUserSession
     *
     * This function verifies the contents of the PHP session.  It checks
     * the following:
     * (1) The persistent store 'uid', the Identity Provider 'idp', the
     *     IdP Display Name 'idpname', and the 'status' (of getUser()) are
     *     all non-empty strings.
     * (2) The 'status' (of getUser()) is even (i.e. STATUS_OK).
     * (3) If $providerId is passed-in, it must match 'idp'.
     * If all checks are good, then this function returns true.
     *
     * @param string $providerId (Optional) The user-selected Identity
     *        Provider. If set, make sure $providerId matches the PHP
     *        session variable 'idp'.
     * @return bool True if the contents of the PHP session ar valid.
     *              False otherwise.
     */
    public static function verifyCurrentUserSession($providerId = '')
    {
        $retval = false;

        // Check for eduGAIN IdP and possible get cert context
        if (static::isEduGAINAndGetCert()) {
            Util::unsetUserSessionVars();
        }

        $idp       = Util::getSessionVar('idp');
        $idpname   = Util::getSessionVar('idpname');
        $uid       = Util::getSessionVar('uid');
        $status    = Util::getSessionVar('status');
        $dn        = Util::getSessionVar('dn');
        $authntime = Util::getSessionVar('authntime');


        if ((strlen($uid) > 0) && (strlen($idp) > 0) &&
            (strlen($idpname) > 0) && (strlen($status) > 0) &&
            (strlen($dn) > 0) && (strlen($authntime) > 0) &&
            (!($status & 1))) {  // All STATUS_OK codes are even
            if ((strlen($providerId) == 0) || ($providerId == $idp)) {
                $retval = true;
            }
        }

        // As a final check, see if the IdP requires a forced skin
        if ($retval) {
            Util::getSkin()->init();
        }

        return $retval;
    }

    /**
     * redirectToGetShibUser
     *
     * This method redirects control flow to the getuser script for
     * If the first parameter (a whitelisted entityId) is not specified,
     * we check to see if either the providerId PHP session variable or the
     * providerId cookie is set (in that order) and use one if available.
     * The function then checks to see if there is a valid PHP session
     * and if the providerId matches the 'idp' in the session.  If so, then
     * we don't need to redirect to '/secure/getuser/' and instead we
     * we display the main page.  However, if the PHP session is not valid,
     * then this function redirects to the '/secure/getuser/' script so as
     * to do a Shibboleth authentication via mod_shib. When the providerId
     * is non-empty, the SessionInitiator will automatically go to that IdP
     * (i.e. without stopping at a WAYF).  This function also sets
     * several PHP session variables that are needed by the getuser script,
     * including the 'responsesubmit' variable which is set as the return
     * 'submit' variable in the 'getuser' script.
     *
     * @param string $providerId (Optional) An entityId of the
     *        authenticating IdP. If not specified (or set to the empty
     *        string), we check providerId PHP session variable and
     *        providerId cookie (in that order) for non-empty values.
     * @param string $responsesubmit (Optional) The value of the PHP session
     *       'submit' variable to be set upon return from the 'getuser'
     *        script.  This is utilized to control the flow of this script
     *        after 'getuser'. Defaults to 'gotuser'.
     * @param string responseurl (Optional) A response url for redirection
     *        after successful processing at /secure/getuser/. Defaults to
     *        the current script directory.
     * @param bool $allowsilver Is it okay to request silver assurance in
     *        the authnContextClassRef? If not, then ignore the 'Request
     *        Silver' checkbox and silver certification in metadata.
     *        Defaults to true.
     */
    public static function redirectToGetShibUser(
        $providerId = '',
        $responsesubmit = 'gotuser',
        $responseurl = null,
        $allowsilver = true
    ) {

        // If providerId not set, try the cookie value
        if (strlen($providerId) == 0) {
            $providerId = Util::getPortalOrNormalCookieVar('providerId');
        }

        // If the user has a valid 'uid' in the PHP session, and the
        // providerId matches the 'idp' in the PHP session, then
        // simply go to the main page.
        if (static::verifyCurrentUserSession($providerId)) {
            printMainPage();
        } else { // Otherwise, redirect to the getuser script
            // Set PHP session varilables needed by the getuser script
            Util::setSessionVar(
                'responseurl',
                (is_null($responseurl) ?
                    Util::getScriptDir(true) : $responseurl)
            );
            Util::setSessionVar('submit', 'getuser');
            Util::setSessionVar('responsesubmit', $responsesubmit);
            Util::getCsrf()->setCookieAndSession();

            // Set up the 'header' string for redirection thru mod_shib
            $redirect =
                'Location: https://' . static::getMachineHostname() .
                '/Shibboleth.sso/Login?' .
                'target=' . urlencode(
                    'https://' . static::getMachineHostname() .
                    '/secure/getuser/'
                );

            if (strlen($providerId) > 0) {
                // Use special NIHLogin Shibboleth SessionInitiator for acsByIndex
                if ($providerId == 'urn:mace:incommon:nih.gov') {
                    $redirect = preg_replace(
                        '%/Shibboleth.sso/Login%',
                        '/Shibboleth.sso/NIHLogin',
                        $redirect
                    );
                }

                $redirect .= '&providerId=' . urlencode($providerId);

                // To bypass SSO at IdP, check for session var 'forceauthn' == 1
                $forceauthn = Util::getSessionVar('forceauthn');
                Util::unsetSessionVar('forceauthn');
                if ($forceauthn) {
                    $redirect .= '&forceAuthn=true';
                } elseif (strlen($forceauthn)==0) {
                    // 'forceauth' was not set to '0' in the session, so
                    // check the skin's option instead.
                    $forceauthn = Util::getSkin()->getConfigOption('forceauthn');
                    if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                        $redirect .= '&forceAuthn=true';
                    }
                }

                // If Silver IdP or 'Request Silver' checked, send extra parameter
                if ($allowsilver) {
                    if ((Util::getIdpList()->isSilver($providerId)) ||
                        (strlen(Util::getPostVar('silveridp')) > 0)) {
                        Util::setSessionVar('requestsilver', '1');
                        $redirect .= '&authnContextClassRef=' .
                            urlencode('http://id.incommon.org/assurance/silver');
                    }
                }
            }

            $log = new Loggit();
            $log->info('Shibboleth Login="' . $redirect . '"');
            header($redirect);
            exit; // No further processing necessary
        }
    }

    /**
     * redirectToGetOAuth2User
     *
     * This method redirects control flow to the getuser script for
     * when the user logs in via OAuth 2.0. It first checks to see
     * if we have a valid session. If so, we don't need to redirect and
     * instead simply show the Get Certificate page. Otherwise, we start
     * an OAuth 2.0 logon by composing a parameterized GET URL using
     * the OAuth 2.0 endpoint.
     *
     * @param string $providerId (Optional) An entityId of the
     *        authenticating IdP. If not specified (or set to the empty
     *        string), we check providerId PHP session variable and
     *        providerId cookie (in that order) for non-empty values.
     * @param string $responsesubmit (Optional) The value of the PHP session
     *        'submit' variable to be set upon return from the 'getuser'
     *         script.  This is utilized to control the flow of this script
     *         after 'getuser'. Defaults to 'gotuser'.
     */
    public static function redirectToGetOAuth2User(
        $providerId = '',
        $responsesubmit = 'gotuser'
    ) {
        // If providerId not set, try the cookie value
        if (strlen($providerId) == 0) {
            $providerId = Util::getPortalOrNormalCookieVar('providerId');
        }

        // If the user has a valid 'uid' in the PHP session, and the
        // providerId matches the 'idp' in the PHP session, then
        // simply go to the 'Download Certificate' button page.
        if (static::verifyCurrentUserSession($providerId)) {
            printMainPage();
        } else { // Otherwise, redirect to the OAuth 2.0 endpoint
            // Set PHP session varilables needed by the getuser script
            Util::unsetSessionVar('logonerror');
            Util::setSessionVar('responseurl', Util::getScriptDir(true));
            Util::setSessionVar('submit', 'getuser');
            Util::setSessionVar('responsesubmit', $responsesubmit);
            $csrf = Util::getCsrf();
            $csrf->setCookieAndSession();
            $extraparams['state'] = $csrf->getTokenValue();

            // To bypass SSO at IdP, check for session var 'forceauthn' == 1
            $forceauthn = Util::getSessionVar('forceauthn');
            Util::unsetSessionVar('forceauthn');
            if ($forceauthn) {
                $extraparams['approval_prompt'] = 'force';
            } elseif (strlen($forceauthn)==0) {
                // 'forceauth' was not set to '0' in the session, so
                // check the skin's option instead.
                $forceauthn = Util::getSkin()->getConfigOption('forceauthn');
                if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                    $extraparams['approval_prompt'] = 'force';
                }
            }

            // Get the provider name based on the provider authz URL
            $providerName = Util::getAuthzIdP($providerId);

            // Get the authz URL and redirect
            $oauth2 = new OAuth2Provider($providerName);
            if (is_null($oauth2->provider)) {
                Util::setSessionVar('logonerror', 'Invalid Identity Provider.');
                printLogonPage();
            } else {
                $authUrl = $oauth2->provider->getAuthorizationUrl(
                    array_merge(
                        $oauth2->authzUrlOpts,
                        $extraparams
                    )
                );
                header('Location: ' . $authUrl);
                exit; // No further processing necessary
            }
        }
    }

    /**
     * printErrorBox
     *
     * This function prints out a bordered box with an error icon and any
     * passed-in error HTML text.  The error icon and text are output to
     * a <table> so as to keep the icon to the left of the error text.
     *
     * @param string $errortext HTML error text to be output
     */
    public static function printErrorBox($errortext)
    {
        echo '
        <div class="errorbox">
        <table cellpadding="5">
        <tr>
        <td valign="top">
        ';
        static::printIcon('error');
        echo '&nbsp;
        </td>
        <td> ' , $errortext , '
        </td>
        </tr>
        </table>
        </div>
        ';
    }

    /**
     * handleGotUser
     *
     * This function is called upon return from one of the getuser scripts
     * which should have set the 'uid' and 'status' PHP session variables.
     * It verifies that the status return is one of STATUS_OK (even
     * values).  If not, we print an error message to the user.
     */
    public static function handleGotUser()
    {
        $log = new Loggit();
        $uid = Util::getSessionVar('uid');
        $status = Util::getSessionVar('status');

        // We must get and unset session vars BEFORE any HTML output since
        // a redirect may go to another site, meaning we need to update
        // the session cookie before we leave the cilogon.org domain.
        $ePPN         = Util::getSessionVar('ePPN');
        $ePTID        = Util::getSessionVar('ePTID');
        $firstname    = Util::getSessionVar('firstname');
        $lastname     = Util::getSessionVar('lastname');
        $displayname  = Util::getSessionVar('displayname');
        $emailaddr    = Util::getSessionVar('emailaddr');
        $idp          = Util::getSessionVar('idp');
        $idpname      = Util::getSessionVar('idpname');
        $affiliation  = Util::getSessionVar('affiliation');
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $failureuri   = Util::getSessionVar('failureuri');

        // Check for OIDC redirect_uri or OAuth 1.0a failureuri.
        // If found, set 'Proceed' button redirect appropriately.
        $redirect = '';
        $redirectform = '';
        // First, check for OIDC redirect_uri, with parameters in <form>
        if (isset($clientparams['redirect_uri'])) {
            $redirect = $clientparams['redirect_uri'];
            $redirectform = '<input type="hidden" name="error" value="access_denied" />' .
                '<input type="hidden" name="error_description" value="Missing attributes" />';
            if (isset($clientparams['state'])) {
                $redirectform .= '<input type="hidden" name="state" value="' .
                    $clientparams['state'] . '" />';
            }
        }
        // Next, check for OAuth 1.0a
        if ((strlen($redirect) == 0) && (strlen($failureuri) > 0)) {
            $redirect = $failureuri. "?reason=missing_attributes";
        }

        // If empty 'uid' or 'status' or odd-numbered status code, error!
        if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
            // Got all session vars by now, so okay to unset.
            Util::unsetAllUserSessionVars();

            $log->error('Failed to getuser.');

            static::printHeader('Error Logging On');

            echo '
            <div class="boxed">
            ';

            if ($status == DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) {
                // Check if the problem IdP was Google: probably no first/last name
                if ($idpname == 'Google') {
                    static::printErrorBox('
                    <p>
                    There was a problem logging on. It appears that you have
                    attempted to use Google as your identity provider, but your
                    name or email address was missing. To rectify this problem,
                    go to the <a target="_blank"
                    href="https://myaccount.google.com/privacy#personalinfo">Google
                    Account Personal Information page</a>, and enter your First
                    Name, Last Name, and email address. (All other Google
                    account information is not required by the CILogon Service.)
                    </p>
                    <p>
                    After you have updated your Google account profile, click
                    the "Proceed" button below and attempt to log on
                    with your Google account again. If you have any questions,
                    please contact us at the email address at the bottom of the
                    page.</p>
                    ');

                    echo '
                    <div>
                    ';
                    static::printFormHead($redirect, 'get');
                    echo '
                    <p class="centered">
                    <input type="hidden" name="providerId" value="' ,
                    Util::getAuthzUrl('Google') , '" /> ' , $redirectform , '
                    <input type="submit" name="submit" class="submit"
                    value="Proceed" />
                    </p>
                    </form>
                    </div>
                    ';
                } elseif ($idpname == 'GitHub') {
                    static::printErrorBox('
                    <p>
                    There was a problem logging on. It appears that you have
                    attempted to use GitHub as your identity provider, but your
                    name or email address was missing. To rectify this problem,
                    go to the <a target="_blank"
                    href="https://github.com/settings/profile">GitHub
                    Public Profile page</a>, and enter your Name and email address.
                    (All other GitHub account information is not required by
                    the CILogon Service.)
                    </p>
                    <p>
                    After you have updated your GitHub account profile, click
                    the "Proceed" button below and attempt to log on
                    with your GitHub account again. If you have any questions,
                    please contact us at the email address at the bottom of the
                    page.</p>
                    ');

                    echo '
                    <div>
                    ';
                    static::printFormHead($redirect, 'get');
                    echo '
                    <p class="centered">
                    <input type="hidden" name="providerId" value="' ,
                    Util::getAuthzUrl('GitHub') , '" /> ' , $redirectform , '
                    <input type="submit" name="submit" class="submit"
                    value="Proceed" />
                    </p>
                    </form>
                    </div>
                    ';
                } else { // Problem was missing SAML attribute from Shib IdP
                    static::printAttributeReleaseErrorMessage(
                        $ePPN,
                        $ePTID,
                        $firstname,
                        $lastname,
                        $displayname,
                        $emailaddr,
                        $idp,
                        $idpname,
                        $affiliation,
                        $clientparams,
                        $redirect,
                        $redirectform
                    );
                }
            } else {
                static::printErrorBox('An internal error has occurred. System
                    administrators have been notified. This may be a temporary
                    error. Please try again later, or contact us at the the email
                    address at the bottom of the page.');

                echo '
                <div>
                ';
                static::printFormHead($redirect, 'get');
                echo $redirectform , '
                <input type="submit" name="submit" class="submit" value="Proceed" />
                </form>
                </div>
                ';
            }

            echo '
            </div>
            ';
            static::printFooter();
        } elseif (static::isEduGAINAndGetCert($idp, $idpname)) {
            // If eduGAIN IdP and session can get a cert, then error!
            // Got all session vars by now, so okay to unset.
            Util::unsetAllUserSessionVars();

            $log->error('Failed to getuser due to eduGAIN IdP restriction.');

            static::printHeader('Error Logging On');

            echo '
            <div class="boxed">
            ';
            static::printAttributeReleaseErrorMessage(
                $ePPN,
                $ePTID,
                $firstname,
                $lastname,
                $displayname,
                $emailaddr,
                $idp,
                $idpname,
                $affiliation,
                $clientparams,
                $redirect,
                $redirectform
            );

            echo '
            </div>
            ';
            static::printFooter();
        } else { // Got one of the STATUS_OK status codes
            // Extra security check: Once the user has successfully authenticated
            // with an IdP, verify that the chosen IdP was actually whitelisted.
            // If not, then set error message and show Select an Identity Provider
            // page again.
            Util::getSkin()->init();  // Check for forced skin
            $idps = static::getCompositeIdPList();
            $providerId = Util::getSessionVar('idp');
            if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
                Util::setSessionVar(
                    'logonerror',
                    'Invalid IdP selected. Please try again.'
                );
                Util::sendErrorAlert(
                    'Authentication attempt using non-whitelisted IdP',
                    'A user successfully authenticated with an IdP, however, the
selected IdP was not in the list of whitelisted IdPs as determined
by the current skin. This might indicate the user attempted to
circumvent the security check in "handleGotUser()" for valid
IdPs for the skin.'
                );
                Util::unsetCookieVar('providerId');
                Util::unsetAllUserSessionVars();
                printLogonPage();
            } else { // Check if two-factor authn is enabled and proceed accordingly
                if (TwoFactor::getEnabled() == 'none') {
                    static::gotUserSuccess();
                } else {
                    TwoFactor::printPage();
                }
            }
        }
    }

    /**
     * gotUserSuccess
     *
     * This function is called after the user has been successfully
     * authenticated. In the case of two-factor authentication, the user
     * is first authenticated by the IdP, and then by the configured
     * two-factor authentication method. If the 'status' session variable is
     * STATUS_OK then it checks if we have a new or changed user and prints
     * that page as appropriate. Otherwise it continues to the MainPage.
     */
    public static function gotUserSuccess()
    {
        $status = Util::getSessionVar('status');

        // If this is the first time the user has used the CILogon Service, we
        // skip the New User page under the following circumstances.
        // (1) We are using the OIDC authorization endpoint code flow (check for
        // 'clientparams' session variable);
        // (2) We are using the 'delegate' code flow (check for 'callbackuri'
        // session variable), and one of the following applies:
        //    (a) Skin has 'forceremember' set or
        //    (b) Skin has 'initialremember' set and there is no cookie for the
        //        current portal
        // In these cases, we skip the New User page and proceed directly to the
        // main page. Note that we still want to show the User Changed page to
        // inform the user about updated DN strings.
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $callbackuri = Util::getSessionVar('callbackuri');
        $skin = Util::getSkin();
        $forceremember = $skin->getConfigOption('delegate', 'forceremember');

        if (($status == DBService::$STATUS['STATUS_NEW_USER']) &&
            ((strlen($callbackuri) > 0) ||
             (isset($clientparams['code'])))) {
            // Extra check for new users: see if any HTML entities
            // are in the user name. If so, send an email alert.
            $dn = Util::getSessionVar('dn');
            $dn = static::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));
            $htmldn = Util::htmlent($dn);
            if (strcmp($dn, $htmldn) != 0) {
                Util::sendErrorAlert(
                    'New user DN contains HTML entities',
                    "htmlentites(DN) = $htmldn\n"
                );
            }

            if (isset($clientparams['code'])) {
                // OIDC authorization code flow always skips New User page
                $status = DBService::$STATUS['STATUS_OK'];
            } elseif (strlen($callbackuri) > 0) {
                // Delegation code flow might skip New User page
                if ((!is_null($forceremember)) && ((int)$forceremember == 1)) {
                    // Check forcerememeber skin option to skip new user page
                    $status = DBService::$STATUS['STATUS_OK'];
                } else {
                    // Check initialremember skin option PLUS no portal cookie
                    $initialremember =
                        $skin->getConfigOption('delegate', 'initialremember');
                    if ((!is_null($initialremember)) && ((int)$initialremember==1)) {
                        $pc = new PortalCookie();
                        $portallifetime = $pc->get('lifetime');
                        if ((strlen($portallifetime)==0) || ($portallifetime==0)) {
                            $status = DBService::$STATUS['STATUS_OK'];
                        }
                    }
                }
            }
        }

        // If the user got a new DN due to changed SAML attributes,
        // print out a notification page.
        if ($status == DBService::$STATUS['STATUS_NEW_USER']) {
            static::printNewUserPage();
        } elseif ($status == DBService::$STATUS['STATUS_USER_UPDATED']) {
            static::printUserChangedPage();
        } else { // STATUS_OK
            printMainPage();
        }
    }

    /**
     * printNewUserPage
     *
     * This function prints out a notification page to new users showing
     * that this is the first time they have logged in with a particular
     * identity provider.
     */
    public static function printNewUserPage()
    {
        $log = new Loggit();
        $log->info('New User page.');

        $dn = Util::getSessionVar('dn');
        $dn = static::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));

        static::printHeader('New User');

        echo '
        <div class="boxed">
        <br class="clear"/>
        <p>
        Welcome! Your new certificate subject is as follows.
        </p>
        <p>
        <blockquote><tt>' , Util::htmlent($dn) , '</tt></blockquote>
        </p>
        <p>
        You may need to register this certificate subject with relying parties.
        </p>
        <p>
        You will not see this page again unless the CILogon Service assigns you
        a new certificate subject. This may occur in the following situations:
        </p>
        <ul>
        <li>You log on to the CILogon Service using an identity provider other
        than ' , Util::getSessionVar('idpname') , '.
        </li>
        <li>You log on using a different ' , Util::getSessionVar('idpname') , '
        identity.
        </li>
        <li>The CILogon Service has experienced an internal error.
        </li>
        </ul>
        <p>
        Click the "Proceed" button to continue. If you have any questions,
        please contact us at the email address at the bottom of the page.
        </p>
        <div>
        ';
        static::printFormHead();
        echo '
        <p class="centered">
        <input type="submit" name="submit" class="submit" value="Proceed" />
        </p>
        </form>
        </div>
        </div>
        ';
        static::printFooter();
    }

    /**
     * printUserChangedPage
     *
     * This function prints out a notification page informing the user that
     * some of their attributes have changed, which will affect the
     * contents of future issued certificates.  This page shows which
     * attributes are different (displaying both old and new values) and
     * what portions of the certificate are affected.
     */
    public static function printUserChangedPage()
    {
        $errstr = '';

        $log = new Loggit();
        $log->info('User IdP attributes changed.');

        $uid = Util::getSessionVar('uid');
        $dbs = new DBService();
        if (($dbs->getUser($uid)) &&
            (!($dbs->status & 1))) {  // STATUS_OK codes are even

            $idpname = $dbs->idp_display_name;
            $first   = $dbs->first_name;
            $last    = $dbs->last_name;
            $email   = $dbs->email;
            $dn      = $dbs->distinguished_name;
            $dn      = static::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));

            if (($dbs->getLastArchivedUser($uid)) &&
                (!($dbs->status & 1))) {  // STATUS_OK codes are even

                $previdpname = $dbs->idp_display_name;
                $prevfirst   = $dbs->first_name;
                $prevlast    = $dbs->last_name;
                $prevemail   = $dbs->email;
                $prevdn      = $dbs->distinguished_name;
                $prevdn      = static::reformatDN(
                    preg_replace(
                        '/\s+email=.+$/',
                        '',
                        $prevdn
                    )
                );

                $tablerowodd = true;

                static::printHeader('Certificate Information Changed');

                echo '
                <div class="boxed">
                <br class="clear"/>
                <p>
                One or more of the attributes released by your organization has
                changed since the last time you logged on to the CILogon
                Service. This will affect your certificates as described below.
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
                      <td>'.Util::htmlent($prevfirst).'</td>
                      <td>'.Util::htmlent($first).'</td>
                    </tr>
                    ';
                    $tablerowodd = !$tablerowodd;
                }

                if ($last != $prevlast) {
                    echo '
                    <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                      <th>Last Name:</th>
                      <td>'.Util::htmlent($prevlast).'</td>
                      <td>'.Util::htmlent($last).'</td>
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
                    <strong>certificate subject</strong> to change. You may be
                    required to re-register with relying parties using this new
                    certificate subject.
                    </p>
                    <p>
                    <blockquote>
                    <table cellspacing="0">
                      <tr>
                        <td>Previous Subject DN:</td>
                        <td>' , Util::htmlent($prevdn) , '</td>
                      </tr>
                      <tr>
                        <td>Current Subject DN:</td>
                        <td>' , Util::htmlent($dn) , '</td>
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
                    clients. Possible problems which may occur include:
                    </p>
                    <ul>
                    <li>If your "from" address does not match what is
                        contained in the certificate, recipients may fail to
                        verify your signed email messages.</li>
                    <li>If the email address in the certificate does not
                        match the destination address, senders may have
                        difficulty encrypting email addressed to you.</li>
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
                static::printFormHead();
                echo '
                <p class="centered">
                <input type="submit" name="submit" class="submit"
                value="Proceed" />
                </p>
                </form>
                </div>
                </div>
                ';
                static::printFooter();
            } else {  // Database error, should never happen
                if (!is_null($dbs->status)) {
                    $errstr = array_search($dbs->status, DBService::$STATUS);
                }
                $log->error('Database error reading last archived ' .
                                  'user attributes. ' . $errstr);
                Util::sendErrorAlert(
                    'dbService Error',
                    'Error calling dbservice action "getLastArchivedUser" in ' .
                    'printUserChangedPaged() method. ' . $errstr
                );
                Util::unsetAllUserSessionVars();
                printLogonPage();
            }
        } else {  // Database error, should never happen
            if (!is_null($dbs->status)) {
                $errstr = array_search($dbs->status, DBService::$STATUS);
            }
            $log->error('Database error reading current user attributes. ' .
                              $errstr);
            Util::sendErrorAlert(
                'dbService Error',
                'Error calling dbservice action "getUser" in ' .
                'printUserChangedPaged() method. ' . $errstr
            );
            Util::unsetAllUserSessionVars();
            printLogonPage();
        }
    }

    /**
     * generateP12
     *
     * This function is called when the user clicks the 'Get New
     * Certificate' button. It first reads in the password fields and
     * verifies that they are valid (i.e. they are long enough and match).
     * Then it gets a credential from the MyProxy server and converts that
     * certificate into a PKCS12 which is written to disk.  If everything
     * succeeds, the temporary pkcs12 directory and lifetime is saved to
     * the 'p12' PHP session variable, which is read later when the Main
     * Page HTML is shown.
     */
    public static function generateP12()
    {
        $log = new Loggit();

        // Get the entered p12lifetime and p12multiplier and set the cookies
        list($minlifetime, $maxlifetime) =
            static::getMinMaxLifetimes('pkcs12', 9516);
        $p12lifetime   = Util::getPostVar('p12lifetime');
        $p12multiplier = Util::getPostVar('p12multiplier');
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
        Util::setCookieVar('p12lifetime', $p12lifetime);
        Util::setCookieVar('p12multiplier', $p12multiplier);
        Util::setSessionVar('p12lifetime', $p12lifetime);
        Util::setSessionVar('p12multiplier', $p12multiplier);

        // Verify that the password is at least 12 characters long
        $password1 = Util::getPostVar('password1');
        $password2 = Util::getPostVar('password2');
        $p12password = Util::getPostVar('p12password');  // For ECP clients
        if (strlen($p12password) > 0) {
            $password1 = $p12password;
            $password2 = $p12password;
        }
        if (strlen($password1) < 12) {
            Util::setSessionVar(
                'p12error',
                'Password must have at least 12 characters.'
            );
            return; // SHORT PASSWORD - NO FURTHER PROCESSING NEEDED!
        }

        // Verify that the two password entry fields matched
        if ($password1 != $password2) {
            Util::setSessionVar('p12error', 'Passwords did not match.');
            return; // MISMATCHED PASSWORDS - NO FURTHER PROCESSING NEEDED!
        }

        // Set the port based on the Level of Assurance
        $port = 7512;
        $loa = Util::getSessionVar('loa');
        if ($loa == 'http://incommonfederation.org/assurance/silver') {
            $port = 7514;
        } elseif ($loa == 'openid') {
            $port = 7516;
        }

        $dn = Util::getSessionVar('dn');
        if (strlen($dn) > 0) {
            // Append extra info, such as 'skin', to be processed by MyProxy
            $myproxyinfo = Util::getSessionVar('myproxyinfo');
            if (strlen($myproxyinfo) > 0) {
                $dn .= " $myproxyinfo";
            }
            // Attempt to fetch a credential from the MyProxy server
            $cert = MyProxy::getMyProxyCredential(
                $dn,
                '',
                'myproxy.cilogon.org,myproxy2.cilogon.org',
                $port,
                $lifetime,
                '/var/www/config/hostcred.pem',
                ''
            );

            // The 'openssl pkcs12' command is picky in that the private
            // key must appear BEFORE the public certificate. But MyProxy
            // returns the private key AFTER. So swap them around.
            $cert2 = '';
            if (preg_match(
                '/-----BEGIN CERTIFICATE-----([^-]+)' .
                '-----END CERTIFICATE-----[^-]*' .
                '-----BEGIN RSA PRIVATE KEY-----([^-]+)' .
                '-----END RSA PRIVATE KEY-----/',
                $cert,
                $match
            )) {
                $cert2 = "-----BEGIN RSA PRIVATE KEY-----" .
                         $match[2] . "-----END RSA PRIVATE KEY-----\n".
                         "-----BEGIN CERTIFICATE-----" .
                         $match[1] . "-----END CERTIFICATE-----";
            }

            if (strlen($cert2) > 0) { // Successfully got a certificate!
                // Create a temporary directory in /var/www/html/pkcs12/
                $tdirparent = '/var/www/html/pkcs12/';
                $polonum = '3';   // Prepend the polo? number to directory
                if (preg_match('/(\d+)\./', php_uname('n'), $polomatch)) {
                    $polonum = $polomatch[1];
                }
                $tdir = Util::tempDir($tdirparent, $polonum, 0770);
                $p12dir = str_replace($tdirparent, '', $tdir);
                $p12file = $tdir . '/usercred.p12';

                // Call the openssl pkcs12 program to convert certificate
                exec('/bin/env ' .
                     'RANDFILE=/tmp/.rnd ' .
                     'CILOGON_PKCS12_PW=' . escapeshellarg($password1) . ' ' .
                     '/usr/bin/openssl pkcs12 -export ' .
                     '-passout env:CILOGON_PKCS12_PW ' .
                     "-out $p12file " .
                     '<<< ' . escapeshellarg($cert2));

                // Verify the usercred.p12 file was actually created
                $size = @filesize($p12file);
                if (($size !== false) && ($size > 0)) {
                    $p12link = 'https://' . static::getMachineHostname() .
                               '/pkcs12/' . $p12dir . '/usercred.p12';
                    $p12 = (time()+300) . " " . $p12link;
                    Util::setSessionVar('p12', $p12);
                    $log->info('Generated New User Certificate="'.$p12link.'"');
                } else { // Empty or missing usercred.p12 file - shouldn't happen!
                    Util::setSessionVar(
                        'p12error',
                        'Error creating certificate. Please try again.'
                    );
                    Util::deleteDir($tdir); // Remove the temporary directory
                    $log->info('Error creating certificate - missing usercred.p12');
                }
            } else { // The myproxy-logon command failed - shouldn't happen!
                Util::setSessionVar(
                    'p12error',
                    'Error! MyProxy unable to create certificate.'
                );
                $log->info('Error creating certificate - myproxy-logon failed');
            }
        } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
            Util::setSessionVar(
                'p12error',
                'Missing username. Please enable cookies.'
            );
            $log->info('Error creating certificate - missing dn session variable');
        }
    }

    /**
     * getLogOnButtonText
     *
     * This function checks the current skin to see if <logonbuttontext>
     * has been configured.  If so, it returns that value.  Otherwise,
     * it returns 'Log On'.
     *
     * @return string The text of the 'Log On' button for the WAYF, as
     *         configured for the skin.  Defaults to 'Log On'.
     */
    public static function getLogOnButtonText()
    {
        $retval = 'Log On';
        $lobt = Util::getSkin()->getConfigOption('logonbuttontext');
        if (!is_null($lobt)) {
            $retval = (string)$lobt;
        }
        return $retval;
    }

    /**
     * getSerialStringFromDN
     *
     * This function takes in a CILogon subject DN and returns just the
     * serial string part (e.g., A325). This function is needed since the
     * serial_string is not stored in the PHP session as a separate
     * variable since it is always available in the 'dn' session variable.
     *
     * @param string $dn The certificate subject DN (typically found in the
     *        session 'dn' variable)
     * @return string The serial string extracted from the subject DN, or
     *         empty string if DN is empty or wrong format.
     */
    public static function getSerialStringFromDN($dn)
    {
        $serial = ''; // Return empty string upon error

        // Strip off the email address, if present
        $dn = preg_replace('/\s+email=.+$/', '', $dn);
        // Find the 'CN=' entry
        if (preg_match('%/DC=org/DC=cilogon/C=US/O=.*/CN=(.*)%', $dn, $match)) {
            $cn = $match[1];
            if (preg_match('/\s+([^\s]+)$/', $cn, $match)) {
                $serial = $match[1];
            }
        }
        return $serial;
    }

    /**
     * getEmailFromDN
     *
     * This function takes in a CILogon subject DN and returns just the
     * email address part. This function is needed since the email address
     * is not stored in the PHP session as a separate variable since it is
     * always available in the 'dn' session variable.
     *
     * @param string $dn The certificate subject DN (typically found in the
     *        session 'dn' variable)
     * @return string The email address extracted from the subject DN, or
     *         empty string if DN is empty or wrong format.
     */
    public static function getEmailFromDN($dn)
    {
        $email = ''; // Return empty string upon error
        if (preg_match('/\s+email=(.+)$/', $dn, $match)) {
            $email = $match[1];
        }
        return $email;
    }

    /**
     * reformatDN
     *
     * This function takes in a certificate subject DN with the email=...
     * part already removed. It checks the skin to see if <dnformat> has
     * been set. If so, it reformats the DN appropriately.
     *
     * @param string $dn The certificate subject DN (without the email=... part)
     * @return string The certificate subject DN transformed according to
     *         the value of the <dnformat> skin config option.
     */
    public static function reformatDN($dn)
    {
        $newdn = $dn;
        $dnformat = (string)Util::getSkin()->getConfigOption('dnformat');
        if (!is_null($dnformat)) {
            if (($dnformat == 'rfc2253') &&
                (preg_match(
                    '%/DC=(.*)/DC=(.*)/C=(.*)/O=(.*)/CN=(.*)%',
                    $dn,
                    $match
                ))) {
                array_shift($match);
                $m = array_reverse(Net_LDAP2_Util::escape_dn_value($match));
                $newdn = "CN=$m[0],O=$m[1],C=$m[2],DC=$m[3],DC=$m[4]";
            }
        }
        return $newdn;
    }

    /**
     * getMinMaxLifetimes
     *
     * This function checks the skin's configuration to see if either or
     * both of minlifetime and maxlifetime in the specified config.xml
     * block have been set. If not, default to minlifetime of 1 (hour) and
     * the specified defaultmaxlifetime.
     *
     * @param string $section The XML section block from which to read the
     *        minlifetime and maxlifetime values. Can be one of the
     *        following: 'pkcs12', 'gsca', or 'delegate'.
     * @param int $defaultmaxlifetime Default maxlifetime (in hours) for the
     *        credential.
     * @return array An array consisting of two entries: the minimum and
     *         maximum lifetimes (in hours) for a credential.
     */
    public static function getMinMaxLifetimes($section, $defaultmaxlifetime)
    {
        $minlifetime = 1;    // Default minimum lifetime is 1 hour
        $maxlifetime = $defaultmaxlifetime;
        $skin = Util::getSkin();
        $skinminlifetime = $skin->getConfigOption($section, 'minlifetime');
        // Read the skin's minlifetime value from the specified section
        if ((!is_null($skinminlifetime)) && ((int)$skinminlifetime > 0)) {
            $minlifetime = max($minlifetime, (int)$skinminlifetime);
            // Make sure $minlifetime is less than $maxlifetime;
            $minlifetime = min($minlifetime, $maxlifetime);
        }
        // Read the skin's maxlifetime value from the specified section
        $skinmaxlifetime = $skin->getConfigOption($section, 'maxlifetime');
        if ((!is_null($skinmaxlifetime)) && ((int)$skinmaxlifetime) > 0) {
            $maxlifetime = min($maxlifetime, (int)$skinmaxlifetime);
            // Make sure $maxlifetime is greater than $minlifetime
            $maxlifetime = max($minlifetime, $maxlifetime);
        }

        return array($minlifetime, $maxlifetime);
    }

    /**
     * getMachineHostname
     *
     * This function is utilized in the formation of the URL for the
     * PKCS12 credential download link.  It returns a host-specific
     * URL hostname by mapping the local machine hostname (as returned
     * by 'uname -n') to an InCommon metadata cilogon.org hostname
     * (e.g., polo2.cilogon.org). This function contains an array
     * '$hostnames' where the values are the local machine hostname and
     * the keys are the *.cilogon.org hostname. Since this array is
     * fairly static, I didn't see the need to read it in from a config
     * file. In case the local machine hostname cannot be found in the
     * $hostnames array, 'cilogon.org' is returned by default.
     *
     * @return string The full cilogon-specific hostname of this host.
     */
    public static function getMachineHostname()
    {
        $retval = 'cilogon.org';
        $hostnames = array(
            "polo1.ncsa.illinois.edu"        => "polo1.cilogon.org" ,
            "poloa.ncsa.illinois.edu"        => "polo1.cilogon.org" ,
            "polo2.ncsa.illinois.edu"        => "polo2.cilogon.org" ,
            "polob.ncsa.illinois.edu"        => "polo2.cilogon.org" ,
            "fozzie.nics.utk.edu"            => "polo3.cilogon.org" ,
            "poloc.ncsa.illinois.edu"        => "test.cilogon.org" ,
            "polot.ncsa.illinois.edu"        => "test.cilogon.org" ,
            "polo-staging.ncsa.illinois.edu" => "test.cilogon.org" ,
        );
        $localhost = php_uname('n');
        if (array_key_exists($localhost, $hostnames)) {
            $retval = $hostnames[$localhost];
        }
        return $retval;
    }

    /**
     * getCompositeIdPList
     *
     * This function generates a list of IdPs to display in the 'Select
     * An Identity Provider' box on the main CILogon page or on the
     * TestIdP page. For the main CILogon page, this is a filtered list of
     * IdPs based on the skin's whitelist/blacklist and the global
     * blacklist file. For the TestIdP page, the list is all InCommon IdPs.
     *
     * @param bool $incommon idps (Optional) Show all InCommon IdPs in
     *        selection list? Defaults to false, which means show only
     *        whitelisted IdPs.
     */
    public static function getCompositeIdPList($incommonidps = false)
    {
        $retarray = array();

        $idplist = Util::getIdpList();
        if ($incommonidps) { // Get all InCommon IdPs only
            $retarray = $idplist->getInCommonIdPs();
        } else { // Get the whitelisted InCommon IdPs, plus maybe Google/GitHub
            $retarray = $idplist->getWhitelistedIdPs();

            // Add Google and GitHub to the list
            $retarray[Util::getAuthzUrl('Google')] = 'Google';
            $retarray[Util::getAuthzUrl('GitHub')] = 'GitHub';

            // Check to see if the skin's config.xml has a whitelist of IDPs.
            // If so, go thru master IdP list and keep only those IdPs in the
            // config.xml's whitelist.
            $skin = Util::getSkin();
            if ($skin->hasIdpWhitelist()) {
                foreach ($retarray as $entityId => $displayName) {
                    if (!$skin->idpWhitelisted($entityId)) {
                        unset($retarray[$entityId]);
                    }
                }
            }
            // Next, check to see if the skin's config.xml has a blacklist of
            // IdPs. If so, cull down the master IdP list removing 'bad' IdPs.
            if ($skin->hasIdpBlacklist()) {
                $idpblacklist = $skin->getConfigOption('idpblacklist');
                foreach ($idpblacklist->idp as $blackidp) {
                    unset($retarray[(string)$blackidp]);
                }
            }

            // Check the global blacklist.txt file and remove any IdPs listed
            $globalblacklistfile = '/var/www/html/include/blacklist.txt';
            $globalblackidps = Util::readArrayFromFile($globalblacklistfile);
            foreach (array_keys($globalblackidps) as $blackidp) {
                unset($retarray[(string)$blackidp]);
            }
        }

        // Fix for CIL-174 - As suggested by Keith Hazelton, replace commas and
        // hyphens with just commas. Resort list for correct alphabetization.
        $regex = '/(University of California)\s*[,-]\s*/';
        foreach ($retarray as $entityId => $idpName) {
            if (preg_match($regex, $idpName)) {
                $retarray[$entityId] = preg_replace($regex, '$1, ', $idpName);
            }
        }
        uasort($retarray, 'strcasecmp');

        return $retarray;
    }

    /**
     * printAttributeReleaseErrorMessage
     *
     * This is a convenience method called by handleGotUser to print out
     * the attribute release error page to the user.
     *
     * @param string $ePPN
     * @param string $ePTID
     * @param string $firstname
     * @param string $lastname,
     * @param string $displayname
     * @param string $emailaddr
     * @param string $idp
     * @param string $idpname
     * @param string $affiliation
     * @param string $clientparams
     * @param string $redirect
     * @param string $redirectform (Optional)
     */
    public static function printAttributeReleaseErrorMessage(
        $ePPN,
        $ePTID,
        $firstname,
        $lastname,
        $displayname,
        $emailaddr,
        $idp,
        $idpname,
        $affiliation,
        $clientparams,
        $redirect,
        $redirectform = ''
    ) {
        $errorboxstr =
        '<p>There was a problem logging on. Your identity
        provider has not provided CILogon with required information.</p>
        <blockquote><table cellpadding="5">';

        $missingattrs = '';
        // Show user which attributes are missing
        if ((strlen($ePPN) == 0) && (strlen($ePTID) == 0)) {
            $errorboxstr .=
            '<tr><th>ePTID:</th><td>MISSING</td></tr>
            <tr><th>ePPN:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    eduPersonPrincipalName'.
                             '%0D%0A    eduPersonTargetedID ';
        }
        if ((strlen($firstname) == 0) && (strlen($displayname) == 0)) {
            $errorboxstr .=
            '<tr><th>First Name:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    givenName (first name)';
        }
        if ((strlen($lastname) == 0) && (strlen($displayname) == 0)) {
            $errorboxstr .=
            '<tr><th>Last Name:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    sn (last name)';
        }
        if ((strlen($displayname) == 0) &&
            ((strlen($firstname) == 0) || (strlen($lastname) == 0))) {
            $errorboxstr .=
            '<tr><th>Display Name:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    displayName';
        }
        $emailvalid = filter_var($emailaddr, FILTER_VALIDATE_EMAIL);
        if ((strlen($emailaddr) == 0) || (!$emailvalid)) {
            $errorboxstr .=
            '<tr><th>Email Address:</th><td>' .
            ((strlen($emailaddr) == 0) ? 'MISSING' : 'INVALID') .
            '</td></tr>';
            $missingattrs .= '%0D%0A    mail (email address)';
        }
        // CIL-326 - For eduGAIN IdPs, check for R&S and SIRTFI
        $idplist = Util::getIdpList();
        if (!$idplist->isRegisteredByInCommon($idp)) {
            if (!$idplist->isREFEDSRandS($idp)) {
                $errorboxstr .=
                '<tr><th><a target="_blank"
                href="http://refeds.org/category/research-and-scholarship">Research
                and Scholarship</a>:</th><td>MISSING</td></tr>';
                $missingattrs .= '%0D%0A    http://refeds.org/category/research-and-scholarship';
            }
            if (!$idplist->isSIRTFI($idp)) {
                $errorboxstr .=
                '<tr><th><a target="_blank"
                href="https://refeds.org/sirtfi">SIRTFI</a>:</th><td>MISSING</td></tr>';
                $missingattrs .= '%0D%0A    http://refeds.org/sirtfi';
            }
        }
        $student = false;
        $errorboxstr .= '</table></blockquote>';
        if ((strlen($emailaddr) == 0) &&
            (preg_match('/student@/', $affiliation))) {
            $student = true;
            $errorboxstr .= '<p><b>If you are a student</b>, ' .
            'you may need to ask your identity provider ' .
            'to release your email address.</p>';
        }

        // Get contacts from metadata for email addresses
        $shibarray = $idplist->getShibInfo($idp);
        $emailmsg = '?subject=Attribute Release Problem for CILogon' .
        '&cc=help@cilogon.org' .
        '&body=Hello, I am having trouble logging on to ' .
        'https://cilogon.org/ using the ' . $idpname .
        ' Identity Provider (IdP) ' .
        'due to the following missing attributes:%0D%0A' .
        $missingattrs;
        if ($student) {
            $emailmsg .= '%0D%0A%0D%0ANote that my account is ' .
            'marked "student" and thus my email address may need ' .
            'to be released.';
        }
        $emailmsg .= '%0D%0A%0D%0APlease see ' .
            'http://www.cilogon.org/service/addidp for more ' .
            'details. Thank you for any help you can provide.';
        $errorboxstr .= '<p>Contact your identity provider to ' .
        'let them know you are having having a problem logging on ' .
        'to CILogon.</p><blockquote><ul>';

        $namefound = false;
        $name = @$shibarray['Support Name'];
        $addr = @$shibarray['Support Address'];
        $addr = preg_replace('/^mailto:/', '', $addr);
        if ((strlen($name) > 0) && (strlen($addr) > 0)) {
            $namefound = true;
            $errorboxstr .= '<li> Support Contact: ' .
                $name . ' &lt;<a href="mailto:' .
                $addr . $emailmsg . '">' .
                $addr . '</a>&gt;</li>';
        }

        if (!$namefound) {
            $name = @$shibarray['Technical Name'];
            $addr = @$shibarray['Technical Address'];
            $addr = preg_replace('/^mailto:/', '', $addr);
            if ((strlen($name) > 0) && (strlen($addr) > 0)) {
                $namefound = true;
                $errorboxstr .= '<li> Technical Contact: ' .
                    $name . ' &lt;<a href="mailto:' .
                    $addr . $emailmsg . '">' .
                    $addr . '</a>&gt;</li>';
            }
        }

        if (!$namefound) {
            $name = @$shibarray['Administrative Name'];
            $addr = @$shibarray['Administrative Address'];
            $addr = preg_replace('/^mailto:/', '', $addr);
            if ((strlen($name) > 0) && (strlen($addr) > 0)) {
                $errorboxstr .= '<li>Administrative Contact: ' .
                    $name . ' &lt;<a href="mailto:' .
                    $addr . $emailmsg.'">' .
                    $addr . '</a>&gt</li>';
            }
        }

        $errorboxstr .= '</ul></blockquote>

        <p> Alternatively, you can contact us at the email address
        at the bottom of the page.</p>
        ';

        static::printErrorBox($errorboxstr);

        echo '
        <div>
        ';

        static::printFormHead($redirect, 'get');
        echo $redirectform , '
        <input type="submit" name="submit" class="submit"
        value="Proceed" />
        </form>
        </div>
        ';
    }

    /**
     * isEduGAINAndGetCert
     *
     * This function checks to see if the current session IdP is an
     * eduGAIN IdP (i.e., not Registered By InCommon) and the IdP does not
     * have both the REFEDS R&S and SIRTFI extensions in metadata. If so,
     * check to see if the transaction could be used to fetch a
     * certificate. (The only time the transaction is not used to fetch
     * a cert is during OIDC without the 'getcert' scope.) If all that is
     * true, then return true. Otherwise return false.
     *
     * @param string $idp (optional) The IdP entityID. If empty, read value
     *        from PHP session.
     * @param string $idpname (optional) The IdP display name. If empty,
     *        read value from PHP session.
     * @return bool True if the current IdP is an eduGAIN IdP without
     *         both REFEDS R&S and SIRTFI, AND the session could be
     *         used to get a certificate.
     */
    public static function isEduGAINAndGetCert($idp = '', $idpname = '')
    {
        $retval = false; // Assume not eduGAIN IdP and getcert

        // If $idp or $idpname not passed in, get from current session.
        if (strlen($idp) == 0) {
            $idp = Util::getSessionVar('idp');
        }
        if (strlen($idpname) == 0) {
            $idpname = Util::getSessionVar('idpname');
        }

        // Check if this was an OIDC transaction, and if the
        // 'getcert' scope was requested.
        $oidcscopegetcert = false;
        $oidctrans = false;
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        if (isset($clientparams['scope'])) {
            $oidctrans = true;
            if (preg_match(
                '/edu\.uiuc\.ncsa\.myproxy\.getcert/',
                $clientparams['scope']
            )) {
                $oidcscopegetcert = true;
            }
        }

        // First, make sure $idp was set and is not Google/GitHub.
        $idplist = Util::getIdpList();
        if (((strlen($idp) > 0) &&
            (strlen($idpname) > 0) &&
            (!in_array($idpname, ['Google', 'GitHub']))) &&
                (
                // Next, check for eduGAIN without REFEDS R&S and SIRTFI
                ((!$idplist->isRegisteredByInCommon($idp)) &&
                       ((!$idplist->isREFEDSRandS($idp)) ||
                        (!$idplist->isSIRTFI($idp))
                       )
                ) &&
                // Next, check if user could get X509 cert,
                // i.e., OIDC getcert scope, or a non-OIDC
                // transaction such as PKCS12, JWS, or OAuth 1.0a
                ($oidcscopegetcert || !$oidctrans)
                )
            ) {
            $retval = true;
        }

        return $retval;
    }
}
