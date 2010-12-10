<?php

require_once('include/util.php');
require_once('include/autoloader.php');
require_once('include/content.php');
require_once('include/shib.php');

/* Read in the whitelist of currently available IdPs. */
$white = new whitelist();

/* Loggit object for logging info to syslog. */
$log = new loggit();

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();
unsetSessionVar('submit');

$log->info('submit="' . $submit . '"');

/* Depending on the value of the clicked "submit" button or the    *
 * equivalent PHP session variable, take action or print out HTML. */
switch ($submit) {

    case 'Log On': // Check for OpenID or InCommon usage.
        // Set the cookie for keepidp if the checkbox was checked
        if (strlen(getPostVar('keepidp')) > 0) {
            setcookie('keepidp','checked',time()+60*60*24*365,'/','',true);
        } else {
            setcookie('keepidp','',time()-3600,'/','',true);
        }
        $providerIdPost = getPostVar('providerId');
        if (openid::urlExists($providerIdPost)) { // Use OpenID authn
            setcookie('providerId',$providerIdPost,
                      time()+60*60*24*365,'/','',true);
            redirectToGetOpenIDUser($providerIdPost);
        } elseif ($white->exists($providerIdPost)) { // Use InCommon authn
            setcookie('providerId',$providerIdPost,
                      time()+60*60*24*365,'/','',true);
            redirectToGetUser($providerIdPost);
        } else { // Either providerId not set or not in whitelist
            setcookie('providerId','',time()-3600,'/','',true);
            printLogonPage();
        }
    break; // End case 'Log On'

    case 'Log Off':   // Click the 'Log Off' button
    case 'Continue' : // Return to Log On page after error condition
        removeShibCookies();
        unsetGetUserSessionVars();
        printLogonPage();
    break; // End case 'Log Off'

    case 'gotuser': // Return from the getuser script
        handleGotUser();
    break; // End case 'gotuser'

    case 'Go Back': // Return to the 'Download Certificate' page
    case 'Proceed': // Proceed after 'User Changed' page
        // Verify the PHP session contains valid info
        if (verifyCurrentSession()) {
            printMainPage();
        } else { // Otherwise, redirect to the 'Welcome' page
            unsetGetUserSessionVars();
            printLogonPage();
        }
    break; // End case 'Go Back' / 'Proceed'

    default: // No submit button clicked nor PHP session submit variable set
        /* If both the "keepidp" and the "providerId" cookies were set *
         * (and the providerId is a whitelisted IdP or valid OpenID    *
         * provider) then skip the Logon page and proceed to the       *
         * appropriate getuser script.                                 */
        $providerIdCookie = getCookieVar('providerId');
        if ((strlen($providerIdCookie) > 0) && 
            (strlen(getCookieVar('keepidp')) > 0)) {
            if (openid::urlExists($providerIdCookie)) { // Use OpenID authn
                redirectToGetOpenIDUser($providerIdCookie);
            } elseif ($white->exists($providerIdCookie)) { // Use InCommon authn
                redirectToGetUser($providerIdCookie);
            } else { // $providerIdCookie not in whitelist
                setcookie('providerId','',time()-3600,'/','',true);
                printLogonPage();
            }
        } else { // One of the cookies for providerId or keepidp was not set.
            printLogonPage();
        }
    break; // End default case

} // End switch($submit)

/************************************************************************
 * Function   : printLogonPage                                          *
 * This function prints out the HTML for the main cilogon.org page.     *
 * Explanatory text is shown as well as a button to log in to an IdP    *
 * and get rerouted to the Shibboleth protected service script.         *
 ************************************************************************/
function printLogonPage()
{
    global $log;

    $log->info('Welcome page hit.');

    printHeader('Welcome To The CILogon Service');

    echo '
    <div class="boxed">
    ';

    printWAYF();

    echo '
    </div> <!-- End boxed -->
    ';

    printFooter();
}

/************************************************************************
 * Function   : printMainPage                                           *
 * This function prints out the HTML for the main page where the user   *
 * can download a certificate.                                          *
 ************************************************************************/
function printMainPage()
{
    global $log;
    $gridshibconf = parseGridShibConf();

    $downloadcerttext = "Download a certificate to your local computer. Clicking this button should launch a Java Web Start (JWS) application, which requires Java to be installed on your computer and enabled in your web browser.";
    $logofftext = "End your CILogon session and return to the welcome page. Note that this will not log you out at your identity provider.";

    $log->info('Get And Use Certificate page hit.');

    $scriptdir = getScriptDir();

    printHeader('Get Your Certificate');

    echo '
    <div class="boxed">

    <table class="certinfo">
      <tr>
        <th>Certificate&nbsp;Subject:</th>
        <td>' , getSessionVar('dn') , '</td>
      </tr>
      <tr>
        <th>Identity&nbsp;Provider:</th>
        <td>' , getSessionVar('idpname') , '</td>
      </tr>
      <tr>
        <th><a target="_blank" 
        href="http://ca.cilogon.org/loa">Level&nbsp;of&nbsp;Assurance:</a></th>
        <td>';
        $loa = getSessionVar('loa');
        if ($loa == 'openid') {
          echo '<a href="http://ca.cilogon.org/policy/openid"
                target="_blank">OpenID</a>';
        } elseif($loa == 'http://incommonfederation.org/assurance/silver') {
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

    <div class="certactionbox">
    ';

    printFormHead(preg_replace('/^\s*=\s*/','',
        $gridshibconf['root']['GridShibCAURL']).'shibCILaunchGSCA.jnlp',true);
        
    $maxlifetime = preg_replace('/^\s*=\s*/','',
        $gridshibconf['root']['CA']['MaximumCredLifetime']);
    $certlifetime   = getCookieVar('certlifetime');
    $certmultiplier = getCookieVar('certmultiplier');
    if ((strlen($certlifetime) == 0) || ($certlifetime <= 0)) {
        $certlifetime = round(preg_replace('/^\s*=\s*/','',
            $gridshibconf['root']['CA']['DefaultCredLifetime']) / 3600);
        $certmultiplier = 3600;
    }
    if ((strlen($certmultiplier) == 0) || ($certmultiplier <= 0)) {
        $certmultiplier = 3600;
    }

    $lifetimetext = "Specify the certificate lifetime. Maximum value is " . 
        round(($maxlifetime/3600),2) . " hours / " .
        round(($maxlifetime/86400),2) . " days / " .
        round(($maxlifetime/2635200),2) . " months.";

    $maxcleartextlifetime = preg_replace('/^\s*=\s*/','',
        $gridshibconf['root']['LaunchClient']['MaxCleartextLifetime']);
    if (($maxcleartextlifetime > 0) && 
        ($maxlifetime >= $maxcleartextlifetime)) {
        $lifetimetext .= " Lifetimes greater than " . 
            round(($maxcleartextlifetime/86400),2) . 
            " days will require you to specify a passphrase.";
    }

    echo '
      <fieldset>
      <p>
      <label for="certlifetime" title="' , $lifetimetext ,
      '" class="helpcursor">Lifetime:</label>
      <input type="text" name="certlifetime" id="certlifetime" 
      title="', $lifetimetext , 
      '" class="helpcursor" value="' , $certlifetime ,
      '" size="8" maxlength="8" disabled="true"/> 
      <select title="' , $lifetimetext , 
      '" class="helpcursor" id="certmultiplier" name="certmultiplier"
      disabled="true">
      <option value="3600"' , 
          (($certmultiplier==3600) ? ' selected="selected"' : '') , 
          '>hours</option>
      <option value="86400"' ,
          (($certmultiplier==86400) ? ' selected="selected"' : '') ,
          '>days</option>
      <option value="2635200"' ,
          (($certmultiplier==2635200) ? ' selected="selected"' : '') ,
          '>months</option>
      </select>
      <input type="hidden" name="maxlifetime" id="maxlifetime" value="' ,
      $maxlifetime , '" />
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
      ';

      echo '
      <script type="text/javascript">
      function init() {
        var certlifetimeinput = document.getElementById("certlifetime");
        if (certlifetimeinput !== null) {
          certlifetimeinput.disabled = false;
        }
        var certmultiplierselect = document.getElementById("certmultiplier");
        if (certmultiplierselect !== null) {
          certmultiplierselect.disabled = false;
        }
        var mayneedjavapara = document.getElementById("mayneedjava");
        if (mayneedjavapara !== null) {
          if (!deployJava.isWebStartInstalled("1.6.0")) {
            mayneedjavapara.style.display = "inline";
            mayneedjavapara.style.height = "auto";
            mayneedjavapara.style.width = "auto";
            mayneedjavapara.style.lineHeight = "auto";
            mayneedjavapara.style.overflow = "visible";
          }
        }
        return true;
      }
      </script>
      <noscript>
      <div class="nojs smaller">
      JavaScript must be enabled to specify Lifetime.
      </div>
      </noscript>
      </form>
    </div>

    <div class="actionbox">
    ';

    printFormHead($scriptdir);

    echo '
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $logofftext , '" value="Log Off" />
      </form>
    </div>
    </div>
    ';
    printFooter();
}

?>
