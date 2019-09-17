<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/vendor/autoload.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\ShibError;
use CILogon\Service\Loggit;

Util::startPHPSession();

// Util::startTiming();
// Util::$timeit->printTime('MAIN Program START...');

// Check for a Shibboleth error and handle it
$shiberror = new ShibError();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
// Note: replace CR/LF with space for 'Show/Hide Help' buttons.
$retchars = array("\r\n","\n","\r");
$submit = str_replace(
    $retchars,
    " ",
    Util::getCsrf()->verifyCookieAndGetSubmit()
);
Util::unsetSessionVar('submit');

$log = new Loggit();
$log->info('submit="' . $submit . '"');

// Depending on the value of the clicked 'submit' button or the
// equivalent PHP session variable, take action or print out HTML.
switch ($submit) {
    case 'Log On': // Check for OpenID or InCommon usage.
    case 'Continue': // For OOI
        Content::handleLogOnButtonClicked();
        break; // End case 'Log On'

    case 'Log Off':   // Click the 'Log Off' button
        printLogonPage(true);
        break; // End case 'Log Off'

    case 'gotuser': // Return from the getuser script
        Content::handleGotUser();
        break; // End case 'gotuser'

    case 'Go Back': // Return to the Main page
    case 'Proceed': // Proceed after 'User Changed' or Error page
    case 'Done with Two-Factor':
        Util::verifySessionAndCall('printMainPage');
        break; // End case 'Go Back' / 'Proceed'

    case 'Cancel': // Cancel button on WAYF page - go to Google
        header('Location: https://www.google.com/');
        exit; // No further processing necessary
        break;

    case 'Get New Certificate':
        if (Util::verifySessionAndCall(
            'CILogon\\Service\\Content::generateP12'
        )) {
            printMainPage();
        }
        break; // End case 'Get New Certificate'

    case 'Get New Activation Code':
        if (Util::verifySessionAndCall('generateActivationCode')) {
            printMainPage();
        }
        break; // End case 'Get New Activation Code'

    case 'Manage Two-Factor':
        Util::verifySessionAndCall(
            'CILogon\\Service\\Content::printTwoFactorPage'
        );
        break; // End case 'Manage Two-Factor'

    case 'Enable':   // Enable / Disable two-factor authentication
    case 'Disable':
    case 'Verify':   // Log in with Google Authenticator
    case 'Disable Two-Factor':
        $enable = !preg_match('/^Disable/', $submit);
        Util::verifySessionAndCall(
            'CILogon\\Service\\Content::handleEnableDisableTwoFactor',
            array($enable)
        );
        break; // End case 'Enable' / 'Disable'

    case 'I Lost My Phone':
        Util::verifySessionAndCall(
            'CILogon\\Service\\Content::handleILostMyPhone'
        );
        break; // End case 'I Lost My Phone'

    case 'Enter': // Verify Google Authenticator one time password
        Util::verifySessionAndCall(
            'CILogon\\Service\\Content::handleGoogleAuthenticatorLogin'
        );
        break; // End case 'Enter'

    case 'EnterDuo': // Verify Duo Security login
        Util::verifySessionAndCall(
            'CILogon\\Service\\Content::handleDuoSecurityLogin'
        );
        break; // End case 'EnterDuo'

    case 'Show  Help ': // Toggle showing of help text on and off
    case 'Hide  Help ':
        Content::handleHelpButtonClicked();
        break; // End case 'Show Help' / 'Hide Help'

    default: // No submit button clicked nor PHP session submit variable set
        Content::handleNoSubmitButtonClicked();
        break; // End default case
} // End switch($submit)


/**
 * printLogonPage
 *
 * This function prints out the HTML for the main cilogon.org page.
 * Explanatory text is shown as well as a button to log in to an IdP
 * and get rerouted to the Shibboleth protected service script, or the
 * OpenID script.
 *
 * @param bool $clearcookies True if the Shibboleth cookies and session
 *        variables  should be cleared out before displaying the page.
 *        Defaults to false.
 */
function printLogonPage($clearcookies = false)
{
    if ($clearcookies) {
        Util::removeShibCookies();
        Util::unsetAllUserSessionVars();
        Util::getSkin()->init(true);  // Clear cilogon_skin var; check for forced skin
    }

    $log = new Loggit();
    $log->info('Welcome page hit.');

    Util::setSessionVar('stage', 'logon'); // For Show/Hide Help button clicks

    Content::printHeader('Welcome To The CILogon Service');

    echo '
    <div class="boxed">
    ';

    Content::printHelpButton();
    Content::printWAYF();

    echo '
    </div> <!-- End boxed -->
    ';
    Content::printFooter();
}

/**
 * printMainPage
 *
 * This function prints out the HTML for the main page where the user
 * can download a certificate or generate an Activation Code.
 */
function printMainPage()
{
    $log = new Loggit();
    $log->info('Get And Use Certificate page hit.');

    Util::setSessionVar('stage', 'main'); // For Show/Hide Help button clicks

    Content::printHeader('Get Your Certificate');

    echo '
    <div class="boxed">
    ';

    Content::printHelpButton();
    printCertInfo();
    printGetCertificate();
    printDownloadCertificate();
    printGetActivationCode();
    Content::printTwoFactorBox();
    printLogOff();

    echo '
    </div> <!-- boxed -->
    ';
    Content::printFooter();
}

/**
 * printCertInfo
 *
 * This function prints the certificate information table at the top
 * of the main page.
 */
function printCertInfo()
{
    $dn = Util::getSessionVar('dn');
    $dn = Content::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));

    echo '
    <table class="certinfo">
      <tr>
        <th>Certificate&nbsp;Subject:</th>
        <td>' , Util::htmlent($dn) , '</td>
      </tr>
      <tr>
        <th>Identity&nbsp;Provider:</th>
        <td>' , Util::getSessionVar('idpname') , '</td>
      </tr>
      <tr>
        <th><a target="_blank"
        href="http://ca.cilogon.org/loa">Level&nbsp;of&nbsp;Assurance:</a></th>
        <td>
    ';

    $loa = Util::getSessionVar('loa');
    if ($loa == 'openid') {
        echo '<a href="http://ca.cilogon.org/policy/openid"
              target="_blank">OpenID</a>';
    } elseif ($loa == 'http://incommonfederation.org/assurance/silver') {
        echo '<a href="http://ca.cilogon.org/policy/silver"
              target="_blank">Silver</a>';
    } else {
        echo '<a href="http://ca.cilogon.org/policy/basic"
              target="_blank">Basic</a>';
    }
    echo '
        </td>
      </tr>
    </table>
    ';
}

/**
 * printGetCertificate
 *
 * This function prints the 'Get New Certificate' box on the main page.
 * If the 'p12' PHP session variable is valid, it is read and a link for the
 * usercred.p12 file is presented to the user.
 */
function printGetCertificate()
{
    // Check if PKCS12 downloading is disabled. If so, print out message.
    $skin = Util::getSkin();
    $disabled = $skin->getConfigOption('pkcs12', 'disabled');
    if ((!is_null($disabled)) && ((int)$disabled == 1)) {
        $disabledmsg = $skin->getConfigOption(
            'pkcs12',
            'disabledmessage'
        );
        if (!is_null($disabledmsg)) {
            $disabledmsg = trim(html_entity_decode($disabledmsg));
        }
        if (strlen($disabledmsg) == 0) {
            $disabledmsg = "Downloading PKCS12 certificates is " .
                "restricted. Please try another method or log on " .
                "with a different Identity Provider.";
        }

        echo '<div class="p12actionbox"><p>
             ', $disabledmsg , '
             </p></div> <!-- p12actionbox -->';
    } else { // PKCS12 downloading is okay
        $downloadcerttext = "Clicking this button will generate a link " .
            "to a new certificate, which you can download to your local " .
            "computer. The certificate is valid for up to 13 months.";
        $p12linktext = "Left-click this link to import the certificate " .
            "into your broswer / operating system. (Firefox users see " .
            "the FAQ.) Right-click this link and select 'Save As...' to " .
            "save the certificate to your desktop.";
        $passwordtext1 = 'Enter a password of at least 12 characters to " .
            "protect your certificate.';
        $passwordtext2 = 'Re-enter your password to verify.';

        validateP12();
        $p12expire = '';
        $p12link = '';
        $p12 = Util::getSessionVar('p12');
        if (preg_match('/([^\s]*)\s(.*)/', $p12, $match)) {
            $p12expire = $match[1];
            $p12link = $match[2];
        }

        if ((strlen($p12link) > 0) && (strlen($p12expire) > 0)) {
            $p12link = '<a href="' . $p12link .
                '">&raquo; Click Here To Download Your Certificate &laquo;</a>';
        }
        if ((strlen($p12expire) > 0) && ($p12expire > 0)) {
            $expire = $p12expire - time();
            $minutes = floor($expire % 3600 / 60);
            $seconds = $expire % 60;
            $p12expire = 'Link Expires: ' .
                sprintf("%02dm:%02ds", $minutes, $seconds);
        } else {
            $p12expire = '';
        }

        $p12lifetime = Util::getSessionVar('p12lifetime');
        if ((strlen($p12lifetime) == 0) || ($p12lifetime == 0)) {
            $p12lifetime = Util::getCookieVar('p12lifetime');
        }
        $p12multiplier = Util::getSessionVar('p12multiplier');
        if ((strlen($p12multiplier) == 0) || ($p12multiplier == 0)) {
            $p12multiplier = Util::getCookieVar('p12multiplier');
        }

        // Try to read the skin's intiallifetime if not yet set
        if ((strlen($p12lifetime) == 0) || ($p12lifetime <= 0)) {
            // See if the skin specified an initial value
            $skinlife = $skin->getConfigOption('pkcs12', 'initiallifetime', 'number');
            $skinmult = $skin->getConfigOption('pkcs12', 'initiallifetime', 'multiplier');
            if ((!is_null($skinlife)) && (!is_null($skinmult)) &&
                ((int)$skinlife > 0) && ((int)$skinmult > 0)) {
                $p12lifetime = (int)$skinlife;
                $p12multiplier = (int)$skinmult;
            } else {
                $p12lifetime = 13;      // Default to 13 months
                $p12multiplier = 732;
            }
        }
        if ((strlen($p12multiplier) == 0) || ($p12multiplier <= 0)) {
            $p12multiplier = 732;   // Default to months
            if ($p12lifetime > 13) {
                $p12lifetime = 13;
            }
        }

        // Make sure lifetime is within [minlifetime,maxlifetime]
        list($minlifetime, $maxlifetime) =
            Content::getMinMaxLifetimes('pkcs12', 9516);
        if (($p12lifetime * $p12multiplier) < $minlifetime) {
            $p12lifetime = $minlifetime;
            $p12multiplier = 1; // In hours
        } elseif (($p12lifetime * $p12multiplier) > $maxlifetime) {
            $p12lifetime = $maxlifetime;
            $p12multiplier = 1; // In hours
        }

        $lifetimetext = "Specify the certificate lifetime. Acceptable range " .
                        "is between $minlifetime and $maxlifetime hours" .
                        (($maxlifetime > 732) ?
                            " ( = " . round(($maxlifetime / 732), 2) . " months)." :
                            "."
                        );

        echo '
        <div class="p12actionbox"';

        if (Util::getSessionVar('showhelp') == 'on') {
            echo ' style="width:92%;"';
        }

        echo '>
        <table class="helptable">
        <tr>
        <td class="actioncell">
        ';

        Content::printFormHead();

        echo '
          <fieldset>
          ';

        $p12error = Util::getSessionVar('p12error');
        if (strlen($p12error) > 0) {
            echo "<p class=\"logonerror\">$p12error</p>";
            Util::unsetSessionVar('p12error');
        }

        echo '
          <p>
          Password Protect Your New Certificate:
          </p>

          <p>
          <label for="password1" class="helpcursor" title="' ,
          $passwordtext1 , '">Enter A Password:</label>
          <input type="password" name="password1" id="password1"
          size="22" title="' , $passwordtext1 , '" onkeyup="checkPassword()"/>
          <img src="/images/blankIcon.png" width="14" height="14" alt=""
          id="pw1icon"/>
          </p>

          <p>
          <label for="password2" class="helpcursor" title="' ,
          $passwordtext2 , '">Confirm Password:</label>
          <input type="password" name="password2" id="password2"
          size="22" title="' , $passwordtext2 , '" onkeyup="checkPassword()"/>
          <img src="/images/blankIcon.png" width="14" height="14" alt=""
          id="pw2icon"/>
          </p>

          <p class="p12certificatelifetime">
          <label for="p12lifetime" title="' , $lifetimetext ,
          '" class="helpcursor">Certificate Lifetime:</label>
          <input type="text" name="p12lifetime" id="p12lifetime"
          title="', $lifetimetext ,
          '" class="helpcursor" value="' , $p12lifetime ,
          '" size="8" maxlength="8"/>
          <select title="' , $lifetimetext ,
          '" class="helpcursor" id="p12multiplier" name="p12multiplier">
          <option value="1"' ,
              (($p12multiplier == 1) ? ' selected="selected"' : '') ,
              '>hours</option>
          <option value="24"' ,
              (($p12multiplier == 24) ? ' selected="selected"' : '') ,
              '>days</option>
          <option value="732"' ,
              (($p12multiplier == 732) ? ' selected="selected"' : '') ,
              '>months</option>
          </select>
          <img src="/images/blankIcon.png" width="14" height="14" alt=""/>
          </p>

          <p>
          <input type="submit" name="submit" class="submit helpcursor"
          title="' , $downloadcerttext , '" value="Get New Certificate"
          onclick="showHourglass(\'p12\')"/>
          <img src="/images/hourglass.gif" width="32" height="32" alt=""
          class="hourglass" id="p12hourglass"/>
          </p>

          <p id="p12value" class="helpcursor" title="' ,
              $p12linktext , '">' , $p12link , '</p>
          <p id="p12expire">' , $p12expire , '</p>

          </fieldset>
          </form>
        </td>
        ';

        if (Util::getSessionVar('showhelp') == 'on') {
            echo '
            <td class="helpcell">
            <div>
            <p>
            In order to get a new certificate, please enter a password of at
            least 12 characters in length.  This password protects the private
            key of the certificate and is different from your identity provider
            password.  You must enter the password twice for verification.
            </p>
            <p>
            After entering a password, click the "Get New Certificate" button to
            generate a new link.  Right-click on this link to download the
            certificate to your computer.  The certificate is valid for up to 13
            months.
            </p>
            </div>
            </td>
            ';
        }

        echo '
        </tr>
        </table>
        </div> <!-- p12actionbox -->
        ';
    }
}

/**
 * printDownlaodCertificate
 *
 * This function prints the 'Download Certificate' box, which uses the
 * GridShib-CA JWS client to download a certificate for the user.
 */
function printDownloadCertificate()
{
    $gridshibconf = Util::parseGridShibConf();
    $idpname = Util::getSessionVar('idpname');

    $downloadcerttext = "Download a certificate to your local computer. " .
        "Clicking this button should launch a Java Web Start (JWS) " .
        "application, which requires Java to be installed on your " .
        "computer and enabled in your web browser.";

    echo '
    <div class="certactionbox"';

    if (Util::getSessionVar('showhelp') == 'on') {
        echo ' style="width:92%;"';
    }

    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">
    ';

    // CIL-593 - Add note to retire Download Certificate
    echo '
    <div>
    <p style="color:red;font-weight:bold">
    "Download Certificate" will be retired 2019-10-01. Please use "Get New Certificate" above instead.
    </p>
    </div>
    ';

    Content::printFormHead(
        preg_replace(
            '/^\s*=\s*/',
            '',
            $gridshibconf['root']['GridShibCAURL']
        ) . 'shibCILaunchGSCA.jnlp',
        'post',
        true
    );

    $certlifetime   = Util::getCookieVar('certlifetime');
    $certmultiplier = Util::getCookieVar('certmultiplier');

    // Try to read the skin's initiallifetime if not yet set
    if ((strlen($certlifetime) == 0) || ($certlifetime <= 0)) {
        $skin = Util::getSkin();
        $skinlife = $skin->getConfigOption('gsca', 'initiallifetime', 'number');
        $skinmult = $skin->getConfigOption('gsca', 'initiallifetime', 'multiplier');
        if ((!is_null($skinlife)) && (!is_null($skinmult)) &&
            ((int)$skinlife > 0) && ((int)$skinmult > 0)) {
            $certlifetime = (int)$skinlife;
            $certmultiplier = (int)$skinmult;
        } else { // Use gridshib-ca.conf default value
            $certlifetime = round(preg_replace(
                '/^\s*=\s*/',
                '',
                $gridshibconf['root']['CA']['DefaultCredLifetime']
            ) / 3600);
            $certmultiplier = 3600;
        }
    }
    if ((strlen($certmultiplier) == 0) || ($certmultiplier <= 0)) {
        $certmultiplier = 3600;   // Default to hours
    }

    // Make sure lifetime is within [minlifetime,maxlifetime]
    $defaultmaxlifetime = preg_replace(
        '/^\s*=\s*/',
        '',
        $gridshibconf['root']['CA']['MaximumCredLifetime']
    ) / 3600;
    list($minlifetime, $maxlifetime) =
        Content::getMinMaxLifetimes('gsca', $defaultmaxlifetime);
    if (($certlifetime * $certmultiplier / 3600) < $minlifetime) {
        $certlifetime = $minlifetime;
        $certmultiplier = 3600; // In hours
    } elseif (($certlifetime * $certmultiplier / 3600) > $maxlifetime) {
        $certlifetime = $maxlifetime;
        $certmultiplier = 3600; // In hours
    }

    $lifetimetext = "Specify the certificate lifetime. Acceptable range " .
                    "is between $minlifetime and $maxlifetime hours" .
                    (($maxlifetime > 732) ?
                        " ( = " . round(($maxlifetime / 732), 2) . " months)." :
                        "."
                    );

    $maxcleartextlifetime = preg_replace(
        '/^\s*=\s*/',
        '',
        $gridshibconf['root']['LaunchClient']['MaxCleartextLifetime']
    ) / 3600;
    if (($maxcleartextlifetime > 0) &&
        ($maxlifetime >= $maxcleartextlifetime)) {
        $lifetimetext .= " Lifetimes greater than " .
            round(($maxcleartextlifetime / 24), 2) .
            " days will require you to specify a passphrase.";
    }

    echo '
      <fieldset>
      <p class="jwscertificatelifetime">
      <label for="certlifetime" title="' , $lifetimetext ,
      '" class="helpcursor">Lifetime:</label>
      <input type="text" name="certlifetime" id="certlifetime"
      title="', $lifetimetext ,
      '" class="helpcursor" value="' , $certlifetime ,
      '" size="8" maxlength="8" disabled="disabled"/>
      <select title="' , $lifetimetext ,
      '" class="helpcursor" id="certmultiplier" name="certmultiplier"
      disabled="disabled">
      <option value="3600"' ,
          (($certmultiplier == 3600) ? ' selected="selected"' : '') ,
          '>hours</option>
      <option value="86400"' ,
          (($certmultiplier == 86400) ? ' selected="selected"' : '') ,
          '>days</option>
      <option value="2635200"' ,
          (($certmultiplier == 2635200) ? ' selected="selected"' : '') ,
          '>months</option>
      </select>
      <input type="hidden" name="minlifetime" id="minlifetime" value="' ,
      $minlifetime * 3600 , '" />
      <input type="hidden" name="maxlifetime" id="maxlifetime" value="' ,
      $maxlifetime * 3600 , '" />
      <input type="hidden" name="RequestedLifetime" id="RequestedLifetime"
      value="' , ($certlifetime * $certmultiplier) , '" />
      </p>
      <p>
      <input type="submit" name="submit" class="submit helpcursor"
      title="' , $downloadcerttext ,
      '" value="Download Certificate" onclick="handleLifetime();" />
      </p>
      <p class="smaller zeroheight" id="mayneedjava">
      You may need to install <a target="_blank"
      href="http://www.javatester.org/version.html">Java</a>.
      </p>
      </fieldset>

      <noscript>
      <div class="nojs smaller">
      JavaScript must be enabled to specify Lifetime.
      </div>
      </noscript>

      </form>
    </td>
    ';

    if (Util::getSessionVar('showhelp') == 'on') {
        echo '
        <td class="helpcell">
        <div>
        <p>
        When you click on the "Download Certificate" button, a JNLP file is
        downloaded to your computer which will launch Java Web Start
        (assuming <a target="_blank"
        href="http://java.com/getjava/">Java</a> is correctly installed on
        your machine).  This will run the CILogon Certificate Retriever
        program to download a certificate.  The program may prompt you to
        enter a password of at least 12 characters to protect the private
        key of the certificate.  This password is different from your
        identity provider password.
        </p>
        </div>
        </td>
        ';
    }

    echo '
    </tr>
    </table>
    </div> <!-- certactionbox -->
    ';
}

/**
 * printGetActivationCode
 *
 * This function prints the 'Get New Activation Code' box on the main
 * page.  If the 'activation' PHP session variable is valid, it is
 * shown at the bottom of the box.  The Activation Code can be used by
 * the GridShib-CA python client to fetch a certificate.
 */
function printGetActivationCode()
{
    $generatecodetext = "Get a new one-time-use activation code for " .
        "CILogon-enabled applications.";
    $tokenhelptext = "Click the button below to display a one-time-use " .
        "activation code for CILogon-enabled applications. You can copy " .
        "and paste this code into the application to download a " .
        "certificate. See FAQ for more information.";
    $tokenvaluetext = 'Copy and paste the one-time-use activation code " .
        "into your CILogon-enabled application to download a certificate.';

    echo '
    <div class="tokenactionbox"';

    if (Util::getSessionVar('showhelp') == 'on') {
        echo ' style="width:92%;"';
    }

    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">
    ';

    Content::printFormHead();

    validateActivationCode();
    $tokenvalue = '';
    $tokenexpire = '';
    $activation = Util::getSessionVar('activation');
    if (preg_match('/([^\s]*)\s(.*)/', $activation, $match)) {
        $tokenexpire = $match[1];
        $tokenvalue = $match[2];
    }
    if ((strlen($tokenvalue) > 0) && (strlen($tokenexpire) > 0)) {
        $tokenvalue = 'Activation&nbsp;Code: ' . $tokenvalue;
    }
    if ((strlen($tokenexpire) > 0) && ($tokenexpire > 0)) {
        $expire = $tokenexpire - time();
        $minutes = floor($expire % 3600 / 60);
        $seconds = $expire % 60;
        $tokenexpire = 'Code Expires: ' .
            sprintf("%02dm:%02ds", $minutes, $seconds);
    } else {
        $tokenexpire = '';
    }

    echo '
      <p class="helpcursor" title="' ,
          $tokenhelptext , '">For CILogon-enabled Applications:</p>
      <p>

      <input type="submit" name="submit" class="submit helpcursor"
      title="' , $generatecodetext , '" value="Get New Activation Code"
      onclick="showHourglass(\'token\')"/>
      <img src="/images/hourglass.gif" width="32" height="32" alt=""
      class="hourglass" id="tokenhourglass"/>
      </p>
      <p id="tokenvalue" class="helpcursor" title="' ,
          $tokenvaluetext , '">' , $tokenvalue , '</p>
      <p id="tokenexpire">' , $tokenexpire , '</p>

      </form>
    </td>
    ';

    if (Util::getSessionVar('showhelp') == 'on') {
        echo '
        <td class="helpcell">
        <div>
        <p>
        An Activation Code can be used by a <a target="_blank"
        href="http://www.cilogon.org/enabled">CILogon-enabled
        Application</a> to download a certificate. Click the "Get New
        Activation Code" button to generate a random sequence of letters and
        numbers.  Highlight the activation code (e.g. double-click it), copy
        the code from your browser, and paste it into the CILogon-enabled
        application.
        </p>
        </div>
        </td>
        ';
    }

    echo '
    </tr>
    </table>
    </div> <!-- tokenactionbox -->
    ';
}

/**
 * printLogOff
 *
 * This function prints the Log Off boxes at the bottom of the main page.
 */
function printLogOff()
{
    $logofftext = 'End your CILogon session and return to the welcome page. ' .
                  'Note that this will not log you out at ' .
                  Util::getSessionVar('idpname') . '.';

    $showhelp = Util::getSessionVar('showhelp');

    echo '
    <div class="logoffactionbox"';

    if ($showhelp == 'on') {
        echo ' style="width:92%;"';
    }

    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">
    ';

    Content::printFormHead();

    echo '
      <p>
      <input type="submit" name="submit" class="submit helpcursor"
      title="' , $logofftext , '" value="Log Off" />
      </p>
    </form>
    </td>
    ';

    if ($showhelp == 'on') {
        echo '
        <td class="helpcell">
        <div>
        <p>
        This button will log you off of the CILogon Service. In order to log
        out from your identity provider, you must either quit your browser
        or manually clear your browser\'s cookies.
        </p>
        </div>
        </td>
        ';
    }

    echo '
    </tr>
    </table>
    </div> <!-- logoffactionbox -->

    <div class="logofftextbox"';

    if ($showhelp == 'on') {
        echo ' style="width:92%;"';
    }

    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">
      <p>To log off, please quit your browser.<p>
    </td>
    ';

    if ($showhelp == 'on') {
        echo '
        <td class="helpcell">
        <div>
        <p>
        Quitting your browser clears all session cookies which logs you out
        from your identity provider.  Alternatively, you can manually clear
        your browser\'s cookies.
        </p>
        </div>
        </td>
        ';
    }

    echo '
    </tr>
    </table>
    </div> <!-- logofftextbox -->
    ';
}

/**
 * generateActivationCode
 *
 * This function is called when the user clicks the 'Get New Activation
 * Code' button.  It calls the GridShib CA functionality to create a
 * .jnlp file, uses 'curl' to slurp in the resulting .jnlp file, and
 * scans for the AuthenticationToken in the file.  This is stored in
 * the 'activation' PHP session value to be output to the user when
 * the Main Page is redrawn. The token can be used by the GridShib-CA
 * python client to fetch a certificate.
 */
function generateActivationCode()
{
    $tokenvalue = '';
    $gridshibconf = Util::parseGridShibConf();

    $ch = curl_init();
    if ($ch !== false) {
        $csrf = Util::getCsrf();
        $url = 'https://' . Util::getHN() . preg_replace(
            '/^\s*=\s*/',
            '',
            $gridshibconf['root']['GridShibCAURL']
        ) . 'shibCILaunchGSCA.jnlp';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'CSRFProtection=' .
            $csrf->getTokenValue());
        curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' .
            Util::getCookieVar('PHPSESSID') . '; CSRFProtection=' .
            $csrf->getTokenValue() . ';');

        // Must close PHP session file so GridShib-CA can read it.
        session_write_close();
        $output = curl_exec($ch);
        if (curl_errno($ch)) { // Send alert on curl errors
            Util::sendErrorAlert(
                'cUrl Error',
                'cUrl Error    = ' . curl_error($ch) . "\n" .
                "URL Accessed  = $url"
            );
        }
        if (!empty($output)) {
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode == 200) {
                if (preg_match(
                    '/AuthenticationToken = ([^<]+)/',
                    $output,
                    $match
                )) {
                    $tokenvalue = $match[1];
                }
            }
        }
        curl_close($ch);

        // If we got a valid AuthenticationToken, store it in the session.
        Util::startPHPSession();
        if (strlen($tokenvalue) > 0) {
            $tokenlifetime = preg_replace(
                '/^\s*=\s*/',
                '',
                $gridshibconf['root']['Session']['CredentialRetrieverClientLifetime']
            );
            if ((strlen($tokenlifetime) == 0) || ($tokenlifetime == 0)) {
                $tokenlifetime = 300;
            }
            $activation = (time() + $tokenlifetime) . " " . $tokenvalue;
            Util::setSessionVar('activation', $activation);
            $log = new Loggit();
            $log->info('Generated New Activation Code="' . $tokenvalue . '"');
        }
    }
}

/**
 * validateP12
 *
 * This function is called just before the 'Download your certificate'
 * link is printed out to HTML. It checks to see if the p12 is still
 * valid time-wise. If not, then it unsets the PHP session variable
 * 'p12'.
 */
function validateP12()
{
    $p12link = '';
    $p12expire = '';
    $p12 = Util::getSessionVar('p12');
    if (preg_match('/([^\s]*)\s(.*)/', $p12, $match)) {
        $p12expire = $match[1];
        $p12link = $match[2];
    }

    // Verify that the p12expire and p12link values are valid.
    if ((strlen($p12expire) == 0) ||
        ($p12expire == 0) ||
        (time() > $p12expire) ||
        (strlen($p12link) == 0)) {
        Util::unsetSessionVar('p12');
    }
}

/**
 * validateActivationCode
 *
 * This function is called just before the certificate token is printed
 * out to HTML.  It checks to see if the activation token value is
 * expired. If so, it unsets the PHP session variable 'activation'.
 */
function validateActivationCode()
{
    $tokenvalue = '';
    $tokenexpire = '';
    $activation = Util::getSessionVar('activation');
    if (preg_match('/([^\s]*)\s(.*)/', $activation, $match)) {
        $tokenexpire = $match[1];
        $tokenvalue = $match[2];
    }

    // If there is a tokenexpire value, check against current time.
    if ((strlen($tokenexpire) == 0) ||
        ($tokenexpire == 0) ||
        (time() > $tokenexpire)) {
        Util::unsetSessionVar('activation');
    }
}

// Util::$timeit->printTime('MAIN Program END...  ');
