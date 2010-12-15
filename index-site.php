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

    case 'Get New Activation Code':
        // Verify the PHP session contains valid info
        if (verifyCurrentSession()) {
            generateToken();
            printMainPage();
        } else { // Otherwise, redirect to the 'Welcome' page
            unsetGetUserSessionVars();
            printLogonPage();
        }
    break; // End case 'Get New Activation Code'

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
    $generatetokentext = "Get a new one-time-use activation code for CILogon-enabled applications.";

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
      '" size="8" maxlength="8" disabled="disabled"/> 
      <select title="' , $lifetimetext , 
      '" class="helpcursor" id="certmultiplier" name="certmultiplier"
      disabled="disabled">
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
//<![CDATA[
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
            mayneedjavapara.style.display = "block";
            mayneedjavapara.style.height = "1.5em";
            mayneedjavapara.style.width = "auto";
            mayneedjavapara.style.lineHeight = "auto";
            mayneedjavapara.style.overflow = "visible";
          }
        }
        return true;
      }
//]]>
      </script>
      <noscript>
      <div class="nojs smaller">
      JavaScript must be enabled to specify Lifetime.
      </div>
      </noscript>
      </form>
    </div> <!-- certactionbox -->

    <div class="tokenactionbox">
    ';
    
    /* Print out the box for the Certificate Token, which can be used by *
     * the GridShib-CA python client to download a certificate.  The     *
     * JavaScript in the following section is a countdown timer showing  *
     * the remaining validity time for the given token.                  */

    printFormHead($scriptdir);

    validateToken();
    $tokenvalue = getSessionVar('tokenvalue');
    $tokenexpire = getSessionVar('tokenexpire');
    $tokenvaluetext = '';
    if ((strlen($tokenvalue) > 0) && (strlen($tokenexpire) > 0)) {
        $tokenvalue = 'Activation&nbsp;Code: ' . $tokenvalue;
        $tokenvaluetext = 'Copy and paste the one-time-use activation code into your CILogon-enabled application to download a certificate.';
    } else {
        $tokenvalue = 'For CILogon-enabled Applications:';
        $tokenvaluetext = 'Click the button below to display a one-time-use activation code for CILogon-enabled applications. You can copy and paste this code into the application to download a certificate.';
    }
    if ((strlen($tokenexpire) > 0) && ($tokenexpire > 0)) {
        $expire = $tokenexpire - time();
        $minutes = floor($expire % 3600 / 60);
        $seconds = $expire % 60;
        $tokenexpire = 'Expires: ' . 
            sprintf("%02dm:%02ds",$minutes,$seconds);
    } else {
        $tokenexpire = '';
    }

    echo '
      <p id="tokenvalue" class="helpcursor" title="' , 
          $tokenvaluetext , '">' , $tokenvalue , '</p>
      <p id="tokenexpire">' , $tokenexpire , '</p>
      <script type="text/javascript">
//<![CDATA[
        function countdown() {
          var tokenexpire = document.getElementById(\'tokenexpire\');
          if (tokenexpire !== null) {
            var expiretext = tokenexpire.innerHTML;
            if ((expiretext !== null) && (expiretext.length > 0)) {
              var matches = expiretext.match(/\d+/g);
              if (matches.length == 2) {
                var minutes = parseInt(matches[0],10);
                var seconds = parseInt(matches[1],10);
                if ((minutes > 0) || (seconds > 0)) {
                  seconds -= 1;
                  if (seconds < 0) {
                    minutes -= 1;
                    if (minutes >= 0) {
                      seconds = 59;
                    }
                  }
                  if ((seconds > 0) || (minutes > 0)) {
                    tokenexpire.innerHTML = "Expires: " + 
                      ((minutes < 10) ? "0" : "") + minutes + "m:" +
                      ((seconds < 10) ? "0" : "") + seconds + "s";
                      setTimeout(countdown,1000);
                  } else {
                    tokenexpire.innerHTML = "";
                    var tokenvalue = document.getElementById(\'tokenvalue\');
                    if (tokenvalue !== null) {
                      tokenvalue.innerHTML = 
                        "For CILogon-enabled Applications:";
                      tokenvalue.title = "Click the button below to display a one-time-use activation code for CILogon-enabled applications. You can copy and paste this code into the application to download a certificate.";
                    }
                  }
                }
              }
            }
          }
        }
        countdown();
//]]>
      </script>

      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $generatetokentext , '" value="Get New Activation Code" />
      </p>
      </form>
    </div> <!-- tokenactionbox -->

    <div class="actionbox">
    ';

    printFormHead($scriptdir);

    echo '
      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $logofftext , '" value="Log Off" />
      </p>
      </form>
    </div>
    </div>
    ';
    printFooter();
}

/************************************************************************
 * Function   : generateToken                                           *
 * This function is called when the user clicks the "Generate New       *
 * Token" button.  It basically does the same thing as when the user    *
 * clicks the "Download Certificate", i.e. it calls the function to     *
 * create a .jnlp file.  However, this method uses 'curl' to slurp in   *
 * the resulting .jnlp file and scans for the AuthenticationToken       *
 * in the file.  This is printed out to the user.  The token can be     *
 * used by the GridShib-CA python client to fetch a certificate.        *
 ************************************************************************/
function generateToken() {
    global $csrf;

    $tokenvalue = '';
    $gridshibconf = parseGridShibConf();

    $ch = curl_init();
    if ($ch != false) {
        $url = 'https://' . HOSTNAME . preg_replace('/^\s*=\s*/','',
            $gridshibconf['root']['GridShibCAURL']) . 'shibCILaunchGSCA.jnlp';
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_TIMEOUT,30);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_HEADER,false);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,1);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_POSTFIELDS,"CSRFProtection=" .
            $csrf->getTokenValue());
        curl_setopt($ch,CURLOPT_COOKIE,'PHPSESSID=' .
            getCookieVar('PHPSESSID') . '; CSRFProtection=' .
            $csrf->getTokenValue() . ';');

        // Must close PHP session file so GridShib-CA can read it.
        session_write_close();
        $output = curl_exec($ch);
        if (!empty($output)) {
            $httpcode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if ($httpcode == 200) {
                if (preg_match('/AuthenticationToken = ([^<]+)/',
                    $output,$match)) {
                    $tokenvalue = $match[1];
                }
            }
        }
        curl_close($ch);

        session_start();
        if (strlen($tokenvalue) > 0) {
            setOrUnsetSessionVar('tokenvalue',$tokenvalue);
            $tokenlifetime = preg_replace('/^\s*=\s*/','',
                $gridshibconf['root']['Session']['CredentialRetrieverClientLifetime']);
            if ((strlen($tokenlifetime) <= 0) || ($tokenlifetime == 0)) {
                $tokenlifetime = 300;
            }
            setOrUnsetSessionVar('tokenexpire',time()+$tokenlifetime);
        }
    }
}

/************************************************************************
 * Function   : validateToken                                           *
 * This function is called just before the certificate token is printed *
 * out to HTML.  It checks to see if the PHP session value              *
 * 'tokenexpire' (which is the expiration time in Unix seconds) is      *
 * greater than the current time().  If not, then it unsets the PHP     *
 * session variables 'tokenexpire' and 'tokenvalue'.                    *
 ************************************************************************/
function validateToken() {
    $tokenvalue = getSessionVar('tokenvalue');
    $tokenexpire = getSessionVar('tokenexpire');

    /* If there is a tokenexpire value, check against current time */
    if ((strlen($tokenexpire) > 0) && ($tokenexpire > 0)) {
        if (time() > $tokenexpire) {
            unsetSessionVar('tokenvalue');
            unsetSessionVar('tokenexpire');
        }
    } else {
        unsetSessionVar('tokenvalue');
    }
}

?>
