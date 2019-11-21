<?php

/**
 * This file contains functions called by index-site.php. The index-site.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

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
 * can download a certificate.
 */
function printMainPage()
{
    $log = new Loggit();
    $log->info('Get And Use Certificate page hit.');

    Util::setSessionVar('stage', 'main'); // For Show/Hide Help button clicks

    Content::printHeader('Get Your Certificate');

    // CIL-626 Allow browser 'reload page' by adding CSRF to the PHP session
    Util::setSessionVar('submit', 'Proceed');
    Util::getCsrf()->setTheSession();

    echo '
    <div class="boxed">
    ';

    Content::printHelpButton();
    Content::printCertInfo();
    printGetCertificate();
    printLogOff();

    echo '
    </div> <!-- boxed -->
    ';
    Content::printFooter();
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
            if (
                (!is_null($skinlife)) && (!is_null($skinmult)) &&
                ((int)$skinlife > 0) && ((int)$skinmult > 0)
            ) {
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
    if (
        (strlen($p12expire) == 0) ||
        ($p12expire == 0) ||
        (time() > $p12expire) ||
        (strlen($p12link) == 0)
    ) {
        Util::unsetSessionVar('p12');
    }
}
