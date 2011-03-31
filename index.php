<?php

require_once('include/util.php');
require_once('include/autoloader.php');
require_once('include/content.php');
require_once('include/shib.php');
require_once('include/myproxy.php');

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

    case 'Go Back': // Return to the Main page
    case 'Proceed': // Proceed after 'User Changed' page
        if (verifyCurrentSession()) { // Verify PHP session contains valid info
            printMainPage();
        } else { // Otherwise, redirect to the 'Welcome' page
            unsetGetUserSessionVars();
            printLogonPage();
        }
    break; // End case 'Go Back' / 'Proceed'

    case 'Get New Certificate':
        if (verifyCurrentSession()) { // Verify PHP session contains valid info
            generateP12();
            printMainPage();
        } else { // Otherwise, redirect to the 'Welcome' page
            unsetGetUserSessionVars();
            printLogonPage();
        }
    break; // End case 'Get New Certificate'

    case 'Get New Activation Code':
        // Verify the PHP session contains valid info
        if (verifyCurrentSession()) {
            generateActivationCode();
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
 * and get rerouted to the Shibboleth protected service script, or the  *
 * OpenID script.                                                       *
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
 * can download a certificate or generate an Activation Code.           *
 ************************************************************************/
function printMainPage()
{
    global $log;

    $log->info('Get And Use Certificate page hit.');

    printHeader('Get Your Certificate');

    echo '
    <div class="boxed">
    ';

    printCertInfo();
    printGetCertificate();
    printDownloadCertificate();
    printGetActivationCode();
    printLogOff();

    echo '
    </div> <!-- boxed -->
    ';
    printFooter();
}

/************************************************************************
 * Function   : printCertInfo                                           *
 * This function prints the certificate information table at the top    *
 * of the main page.                                                    *
 ************************************************************************/
function printCertInfo() {
    $dn = getSessionVar('dn');
    $dn = preg_replace('/\s+email=.+$/','',$dn);

    echo '
    <table class="certinfo">
      <tr>
        <th>Certificate&nbsp;Subject:</th>
        <td>' , $dn , '</td>
      </tr>
      <tr>
        <th>Identity&nbsp;Provider:</th>
        <td>' , getSessionVar('idpname') , '</td>
      </tr>
      <tr>
        <th><a target="_blank" 
        href="http://ca.cilogon.org/loa">Level&nbsp;of&nbsp;Assurance:</a></th>
        <td>
    ';

    $loa = getSessionVar('loa');
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

/************************************************************************
 * Function   : printDownloadCertificate                                *
 * This function prints the "Download Certificate" box, which uses the  *
 * GridShib-CA JWS client to download a certificate for the user.       *
 ************************************************************************/
function printDownloadCertificate() {
    global $skin;

    $gridshibconf = parseGridShibConf();
    $idpname = getSessionVar('idpname');

    $downloadcerttext = "Download a certificate to your local computer. Clicking this button should launch a Java Web Start (JWS) application, which requires Java to be installed on your computer and enabled in your web browser.";

    echo '
    <div class="certactionbox">
    ';

    printFormHead(preg_replace('/^\s*=\s*/','',
        $gridshibconf['root']['GridShibCAURL']).'shibCILaunchGSCA.jnlp',true);
        
    $maxlifetime = preg_replace('/^\s*=\s*/','',
        $gridshibconf['root']['CA']['MaximumCredLifetime']);
    $certlifetime   = getCookieVar('certlifetime');
    $certmultiplier = getCookieVar('certmultiplier');
    if ((strlen($certlifetime) == 0) || ($certlifetime <= 0)) {
        // See if the skin specified an initial value
        $skinlife = $skin->getConfigOption('gsca','initiallifetime','number');
        $skinmult = $skin->getConfigOption('gsca','initiallifetime','multiplier');
        if (($skinlife !== null) && ($skinmult !== null) &&
            ((int)$skinlife > 0) && ((int)$skinmult > 0)) {
            $certlifetime = (int)$skinlife;
            $certmultiplier = (int)$skinmult;
        } else { // Use gridshib-ca.conf default value
            $certlifetime = round(preg_replace('/^\s*=\s*/','',
                $gridshibconf['root']['CA']['DefaultCredLifetime']) / 3600);
            $certmultiplier = 3600;
        }
    }
    if ((strlen($certmultiplier) == 0) || ($certmultiplier <= 0)) {
        $certmultiplier = 3600;
    }

    $lifetimetext = "Specify the certificate lifetime. Maximum value is " . 
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

      <noscript>
      <div class="nojs smaller">
      JavaScript must be enabled to specify Lifetime.
      </div>
      </noscript>

      </form>
    </div> <!-- certactionbox -->
    ';
}

/************************************************************************
 * Function   : printGetCertificate                                     *
 * This function prints the "Get New Certificate" box on the main page. *
 * If the 'p12' PHP session variable is valid, it is read and a         *
 * link for the usercred.p12 file is presented to the user.             *
 ************************************************************************/
function printGetCertificate() {
    global $csrf;
    global $skin;

    $downloadcerttext = "Clicking this button will generate a link to a new certificate, which you can download to your local computer. The certificate is valid for up to 13 months."; 
    $p12linktext = "Left-click this link to import the certificate into your broswer / operating system. (Firefox users see the FAQ.) Right-click this link and select 'Save As...' to save the certificate to your desktop.";
    $lifetimetext = "Specify the certificate lifetime. Maximum value is 13 months.";
    $passwordtext1 = 'Enter a password of at least 12 characters to protect your certificate.';
    $passwordtext2 = 'Re-enter your password to verify.';

    validateP12();
    $p12expire = '';
    $p12dir = '';
    $p12 = getSessionVar('p12');
    if (preg_match('/([^\t]*)\t(.*)/',$p12,$match)) {
        $p12expire = $match[1];
        $p12dir = $match[2];
    }
        
    $p12link = '';
    if ((strlen($p12dir) > 0) &&
        (is_readable(getServerVar('DOCUMENT_ROOT').'/pkcs12/'.$p12dir))) {
        $p12link = getScriptDir(true).'pkcs12/'.$p12dir.'/usercred.p12';
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
            sprintf("%02dm:%02ds",$minutes,$seconds);
    } else {
        $p12expire = '';
    }

    $p12lifetime = getSessionVar('p12lifetime');
    if ((strlen($p12lifetime) == 0) || ($p12lifetime == 0)) {
        $p12lifetime = getCookieVar('p12lifetime');
    }
    $p12multiplier = getSessionVar('p12multiplier');
    if ((strlen($p12multiplier) == 0) || ($p12multiplier == 0)) {
        $p12multiplier = getCookieVar('p12multiplier');
    }
    $maxlifetime = 9516; // In hours = 13 months
    if ((strlen($p12lifetime) == 0) || ($p12lifetime <= 0)) {
        // See if the skin specified an initial value
        $skinlife = $skin->getConfigOption('pkcs12','initiallifetime','number');
        $skinmult = $skin->getConfigOption('pkcs12','initiallifetime','multiplier');
        if (($skinlife !== null) && ($skinmult !== null) &&
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
    if (($p12lifetime * $p12multiplier) > $maxlifetime) {
        $p12lifetime = 13;      // Default to 13 months
        $p12multiplier = 732;
    }

    echo '
    <div class="p12actionbox">
    ';

    printFormHead();

    echo '
      <fieldset>
      ';

      $p12error = getSessionVar('p12error');
      if (strlen($p12error) > 0) {
          echo "<p class=\"openiderror\">$p12error</p>";
          unsetSessionVar('p12error');
      }

      echo '
      <p>
      Password Protect Your New Certificate:
      </p>

      <p>
      <label for="password1" class="helpcursor" title="' ,
      $passwordtext1 , '">Enter A Password:</label>
      <input type="password" name="password1" id="password1"
      size="25" title="' , $passwordtext1 , '" onkeyup="checkPassword()"/>
      <img src="/images/blankIcon.png" width="14" height="14" alt="" 
      id="pw1icon"/>
      </p>

      <p>
      <label for="password2" class="helpcursor" title="' ,
      $passwordtext2 , '">Confirm Password:</label>
      <input type="password" name="password2" id="password2"
      size="25" title="' , $passwordtext2 , '" onkeyup="checkPassword()"/>
      <img src="/images/blankIcon.png" width="14" height="14" alt="" 
      id="pw2icon"/>
      </p>

      <p class="certificatelifetime">
      <label for="p12lifetime" title="' , $lifetimetext ,
      '" class="helpcursor">Certificate Lifetime:</label>
      <input type="text" name="p12lifetime" id="p12lifetime" 
      title="', $lifetimetext , 
      '" class="helpcursor" value="' , $p12lifetime ,
      '" size="8" maxlength="8"/> 
      <select title="' , $lifetimetext , 
      '" class="helpcursor" id="p12multiplier" name="p12multiplier">
      <option value="1"' , 
          (($p12multiplier==1) ? ' selected="selected"' : '') , 
          '>hours</option>
      <option value="24"' ,
          (($p12multiplier==24) ? ' selected="selected"' : '') ,
          '>days</option>
      <option value="732"' ,
          (($p12multiplier==732) ? ' selected="selected"' : '') ,
          '>months</option>
      </select>
      <img src="/images/blankIcon.png" width="14" height="14" alt=""/>
      </p>

      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $downloadcerttext , '" value="Get New Certificate"
      onclick="showHourglass(\'p12\')"/>
      <img src="/images/hourglass.gif" width="32" height="32 alt="" 
      class="hourglass" id="p12hourglass"/>
      </p>

      <p id="p12value" class="helpcursor" title="' , 
          $p12linktext , '">' , $p12link , '</p>
      <p id="p12expire">' , $p12expire , '</p>

      </fieldset>
      </form>
    </div> <!-- p12actionbox -->
    ';
}

/************************************************************************
 * Function   : printGetActivationCode                                  *
 * This function prints the "Get New Activation Code" box on the main   *
 * page.  If the 'activation' PHP session variable is valid, it is      *
 * shown at the bottom of the box.  The Activation Code can be used by  *
 * the GridShib-CA python client to fetch a certificate.                *
 ************************************************************************/
function printGetActivationCode() {
    $generatecodetext = "Get a new one-time-use activation code for CILogon-enabled applications.";
    $tokenhelptext = "Click the button below to display a one-time-use activation code for CILogon-enabled applications. You can copy and paste this code into the application to download a certificate. See FAQ for more information.";
    $tokenvaluetext = 'Copy and paste the one-time-use activation code into your CILogon-enabled application to download a certificate.';

    echo '
    <div class="tokenactionbox">
    ';
    
    printFormHead(getScriptDir());

    validateActivationCode();
    $tokenvalue = '';
    $tokenexpire = '';
    $activation = getSessionVar('activation');
    if (preg_match('/([^\t]*)\t(.*)/',$activation,$match)) {
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
            sprintf("%02dm:%02ds",$minutes,$seconds);
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
      <img src="/images/hourglass.gif" width="32" height="32 alt="" 
      class="hourglass" id="tokenhourglass"/>
      </p>
      <p id="tokenvalue" class="helpcursor" title="' , 
          $tokenvaluetext , '">' , $tokenvalue , '</p>
      <p id="tokenexpire">' , $tokenexpire , '</p>

      </form>
    </div> <!-- tokenactionbox -->
    ';
}

/************************************************************************
 * Function   : printLogOff                                             *
 * This function prints the Log Off boxes at the bottom of the main     *
 * page.                                                                *
 ************************************************************************/
function printLogOff() {
    $logofftext = 'End your CILogon session and return to the welcome page. ' .
                  'Note that this will not log you out at ' .
                  getSessionVar('idpname') . '.';

    echo '
    <div class="logoffactionbox">
    ';

    printFormHead();

    echo '
      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $logofftext , '" value="Log Off" />
      </p>
    </form>
    </div> <!-- logoffactionbox -->
    
    <div class="logofftextbox">
      <p>To log off, please quit your browser.<p>
    </div> <!-- logofftextbox -->
    ';
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
    $maxlifetime = 9516; // In hours = 13 months
    $p12lifetime   = getPostVar('p12lifetime');
    $p12multiplier = getPostVar('p12multiplier');
    $lifetime = $p12lifetime * $p12multiplier;
    if ($lifetime <= 0) { // In case user entered negative number
        $lifetime = $maxlifetime;
        $p12lifetime = 13;
        $p12multiplier = 732;
    } elseif ($lifetime > $maxlifetime) { // Set max value based on multiplier
        $lifetime = $maxlifetime;
        if ($p12multiplier == 1) {
            $p12lifetime = 9516;  // 13 months in hours
        } elseif ($p12multiplier == 24) {
            $p12lifetime = 396.5; // 13 months in days
        } else { 
            $p12lifetime = 13;    // 13 months (in months)
            $p12multiplier = 732;
        }
    }
    setcookie('p12lifetime',$p12lifetime,time()+60*60*24*365,'/','',true);
    setcookie('p12multiplier',$p12multiplier,time()+60*60*24*365,'/','',true);
    setSessionVar('p12lifetime',$p12lifetime);
    setSessionVar('p12multiplier',$p12multiplier);

    /* Verify that the password is at least 12 characters long. */
    $password1 = getPostVar('password1');
    $password2 = getPostVar('password2');
    if (strlen($password1) < 12) {   
        setSessionVar('p12error',
            'Password must have at least 12 characters.');
        return; // SHORT PASSWORD - NO FURTHER PROCESSING NEEDED!
    }

    /* Verify that the two password entry fields matched. */
    if ($password1 != $password2) {
        setSessionVar('p12error','Passwords did not match.');
        return; // MISMATCHED PASSWORDS - NO FURTHER PROCESSING NEEDED!
    }

    /* Set the port based on the Level of Assurance */
    $port = 7512;
    $loa = getSessionVar('loa');
    if ($loa == 'http://incommonfederation.org/assurance/silver') {
        $port = 7514;
    } elseif ($loa == 'openid') {
        $port = 7516;
    }

    $dn = getSessionVar('dn');
    if (strlen($dn) > 0) {
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
            $tdirparent = getServerVar('DOCUMENT_ROOT') . '/pkcs12/';
            $polonum = '3';   // Prepend the polo? number to directory
            if (preg_match('/(\d+)\./',php_uname('n'),$polomatch)) {
                $polonum = $polomatch[1];
            }
            $tdir = tempDir($tdirparent,$polonum);
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
                $p12 = (time()+300) . "\t" . $p12dir;
                setSessionVar('p12',$p12);
                $log->info('Generated New User Certificate="'.$p12dir.'"');
            } else { // Empty or missing usercred.p12 file - shouldn't happen!
                setSessionVar('p12error',
                    'Error creating certificate. Please try again.');
                deleteDir($tdir); // Remove the temporary directory
                $log->info("Error creating certificate - missing usercred.p12");
            }
        } else { // The myproxy-logon command failed - shouldn't happen!
            setSessionVar('p12error',
                'Error! Unable to create certificate.');
            $log->info("Error creating certificate - myproxy-logon failed");
        }
    } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
        setSessionVar('p12error',
            'Missing username. Please enable cookies.');
        $log->info("Error creating certificate - missing dn session variable");
    }
}

/************************************************************************
 * Function   : generateActivationCode                                  *
 * This function is called when the user clicks the "Get New Activation *
 * Code" button.  It calls the GridShib CA functionality to create a    *
 * .jnlp file, uses 'curl' to slurp in the resulting .jnlp file, and    *
 * scans for the AuthenticationToken in the file.  This is stored in    *
 * the 'activation' PHP session value to be output to the user when     *
 * the Main Page is redrawn. The token can be used by the GridShib-CA   *
 * python client to fetch a certificate.                                *
 ************************************************************************/
function generateActivationCode() {
    global $csrf;
    global $log;

    $tokenvalue = '';
    $gridshibconf = parseGridShibConf();

    $ch = curl_init();
    if ($ch !== false) {
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

        /* If we got a valid AuthenticationToken, store it in the session */
        startPHPSession();
        if (strlen($tokenvalue) > 0) {
            $tokenlifetime = preg_replace('/^\s*=\s*/','',
                $gridshibconf['root']['Session']['CredentialRetrieverClientLifetime']);
            if ((strlen($tokenlifetime) == 0) || ($tokenlifetime == 0)) {
                $tokenlifetime = 300;
            }
            $activation = (time()+$tokenlifetime) . "\t" . $tokenvalue;
            setSessionVar('activation',$activation);
            $log->info('Generated New Activation Code="'.$tokenvalue.'"');
        }
    }
}

/************************************************************************
 * Function   : validateP12                                             *
 * This function is called just before the "Download your certificate"  *
 * link is printed out to HTML. It checks to see if the p12 is still    *
 * valid time-wise, and also that the pkcs12 download directory is      *
 * readable. If not, then it unsets the PHP session variable 'p12'.     *
 ************************************************************************/
function validateP12() {
    $p12dir = '';
    $p12expire = '';
    $p12 = getSessionVar('p12');
    if (preg_match('/([^\t]*)\t(.*)/',$p12,$match)) {
        $p12expire = $match[1];
        $p12dir = $match[2];
    }

    /* Verify that the p12expire and p12dir values are valid */
    if ((strlen($p12expire) == 0) ||
        ($p12expire == 0) ||
        (time() > $p12expire) ||
        (strlen($p12dir) == 0) ||
        (!is_readable(getServerVar('DOCUMENT_ROOT').'/pkcs12/'.$p12dir))) {
        unsetSessionVar('p12');
    }
}

/************************************************************************
 * Function   : validateActivationCode                                  *
 * This function is called just before the certificate token is printed *
 * out to HTML.  It checks to see if the activation token value is      *
 * expired. If so, it unsets the PHP session variable 'activation'.     *
 ************************************************************************/
function validateActivationCode() {
    $tokenvalue = '';
    $tokenexpire = '';
    $activation = getSessionVar('activation');
    if (preg_match('/([^\t]*)\t(.*)/',$activation,$match)) {
        $tokenexpire = $match[1];
        $tokenvalue = $match[2];
    }

    /* If there is a tokenexpire value, check against current time */
    if ((strlen($tokenexpire) == 0) ||
        ($tokenexpire == 0) ||
        (time() > $tokenexpire)) {
        unsetSessionVar('activation');
    }
}

?>
