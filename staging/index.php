<?php

require_once('content.php');
require_once('../include/autoloader.php');
require_once('../include/shib.php');
require_once('../include/util.php');
require_once('../include/myproxy.php');
require_once('Auth/OpenID/Consumer.php');
require_once('Auth/OpenID/FileStore.php');
require_once('Auth/OpenID/SReg.php');
require_once('Auth/OpenID/PAPE.php');

startPHPSession();

/* The full URL of the Shibboleth-protected getuser script. */
define('GETUSER_URL','https://cilogon.org/secure/getuser/');
define('GETOPENIDUSER_URL','https://cilogon.org/getopeniduser/');

/* Read in the whitelist of currently available IdPs. */
$white = new whitelist();

/* Get a default OpenID object to be used in case of OpenID logon. */
$openid = new openid();

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
        if (getPostVar('useopenid') == '1') { // Use OpenID authentication
            setcookie('useopenid','1',time()+60*60*24*365,'/','',true);
            // Verify that OpenID provider is in list of providers
            $openidproviderPost = getPostVar('hiddenopenid');
            $usernamePost = getPostVar('username');
            if ($openid->exists($openidproviderPost)) {
                setcookie('providerId',$openidproviderPost,
                          time()+60*60*24*365,'/','',true);
                setcookie('username',$usernamePost,
                          time()+60*60*24*365,'/','',true);
                redirectToGetOpenIDUser($openidproviderPost,$usernamePost);
            } else { // OpenID provider is not in provider list
                printLogonPage();
            }
        } else { // Use InCommon authentication
            setcookie('useopenid','',time()-3600,'/','',true);
            // Verify that InCommon providerId is in the whitelist
            $providerIdPost = getPostVar('providerId');
            if ($white->exists($providerIdPost)) {
                setcookie('providerId',$providerIdPost,
                          time()+60*60*24*365,'/','',true);
                setcookie('username','',time()-3600,'/','',true);
                redirectToGetUser($providerIdPost);
            } else { // Either providerId not set or not in whitelist
                printLogonPage();
            }
        }
    break; // End case 'Log On'

    case 'Log Off':   // Click the 'Log Off' button
    case 'Continue' : // Return to Log On page after error condition
        removeShibCookies();
        $_SESSION = array();  // Clear session variables
        printLogonPage();
    break; // End case 'Log Off'

    case 'gotuser': // Return from the getuser script
        handleGotUser();
    break; // End case 'gotuser'

    case 'Go Back': // Return to the 'Download Certificate' page
    case 'Proceed': // Proceed after 'User Changed' page
        // Verify the PHP session contains valid info
        if (verifyCurrentSession()) {
            printGetCertificatePage();
        } else { // Otherwise, redirect to the 'Welcome' page
            $_SESSION = array();  // Clear session variables
            printLogonPage();
        }
    break; // End case 'Go Back' / 'Proceed'

    case 'GSI-SSHTerm Desktop App':
        header('Location: /gsi-sshterm/cilogon.jnlp');
    break; // End case 'GSI-SSHTerm Desktop App'

    case 'GSI-SSHTerm Web Applet':
        handleGSISSHTermWebApplet();
    break; // End case 'GSI-SSHTerm Web Applet'

    default: // No submit button clicked nor PHP session submit variable set
        /* If both the "keepidp" and the "providerId" cookies were set *
         * (and the providerId is a whitelisted IdP or valid OpenID    *
         * provider) then skip the Logon page and proceed to the       *
         * appropriate getuser script.                                 */
        $providerIdCookie = urldecode(getCookieVar('providerId'));
        if ((strlen($providerIdCookie) > 0) && 
            (strlen(getCookieVar('keepidp')) > 0)) {
            if (getCookieVar('useopenid') == '1') { // Use OpenID authentication
                if ($openid->exists($providerIdCookie)) {
                    redirectToGetOpenIDUser($providerIdCookie,
                                            getCookieVar('username'));
                } else {
                    printLogonPage();
                }
            } else { // Use InCommon authentication
                if ($white->exists($providerIdCookie)) {
                    redirectToGetUser($providerIdCookie);
                } else {
                    printLogonPage();
                }
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
    printPageHeader('Welcome To The CILogon Service');

    echo '
    <div class="welcome">
      <div class="boxheader">
        About The CILogon Service
      </div>
      <h2>What Is The CILogon Service?</h2>
      <p>
      The CILogon Service allows users to authenticate
      with their home organization and obtain a
      certificate for secure access to <a target="_blank"
      href="http://www.nsf.gov/">NSF</a> <a target="_blank"
      href="http://www.nsf.gov/oci">CyberInfrastructure</a> (<acronym
      title="CyberInfrastructure">CI</acronym>) projects. Additional
      information can be found at <a target="_blank"
      href="http://www.cilogon.org/service">www.cilogon.org</a>.
      </p>
      <p class="equation">
      <span>CILogon + Your Organization = Secure Access to 
      <acronym title="National Science Foundation">NSF</acronym>
      <acronym title="CyberInfrastructure">CI</acronym></span>
      </p>
      <h2>How Does The CILogon Service Work?</h2>
      <p>
      The CILogon Service is a member of <a target="_blank"
      href="http://www.incommonfederation.org/">InCommon</a>, a formal
      federation of over 200 universities, agencies, and organizations.
      Many of these organizations maintain an authentication service to
      provide their users with web single sign-on.  An InCommon organization
      can partner with the CILogon Service to provide user information for
      the purpose of issuing certificates.  These certificates can then be
      used for accessing cyberinfrastructure resources.
      </p>
      <h2>How Do I Use The CILogon Service?</h2>
      <p>
      Select your organization from the drop-down list, then click the
      &quot;Log On&quot; button.  You will be redirected to your
      organization\'s login page.  After you authenticate with your
      organization as you typically would, you will be redirected back to
      the CILogon Service.  Then you will be able to fetch a
      certificate for use with cyberinfrastructure resources.  
      </p>
      <h2>What If I Don\'t See My Organization Listed?</h2>
      <p>
      If you don\'t have an account with any of the organizations listed in
      the drop-down list in the &quot;Start Here&quot; menu, you can
      register for a free user account at <a target="_blank"
      href="http://www.protectnetwork.org/">ProtectNetwork</a> for use with
      the CILogon Service.  Alternatively, you can <a target="_blank"
      href="/requestidp/">make a request for your organization</a> to appear
      in the list of available organizations.
      </p>
      <p class="note">
      <strong>Note:</strong> You must enable cookies in your web browser to
      use this site.
      </p>
    </div>
    ';

    printWAYF();

    printFooter();
}

/************************************************************************
 * Function   : printGetCertificatePage                                 *
 * This function prints out the HTML for the main page where the user   *
 * can download a certificate and launch the GSI-SSHTerm                *
 * application/applet.                                                  *
 ************************************************************************/
function printGetCertificatePage()
{
    global $perl_config;
    global $log;

    $log->info('Get And Use Certificate page hit.');

    $scriptdir = getScriptDir();

    printHeader('Get Your Certificate');
    printPageHeader('Welcome ' . getSessionVar('idpname') . ' User');

    echo '
    <div class="boxed">
      <div class="boxheader">
        Get And Use Your CILogon Certificate
      </div>
    <p>
    You are logged on to the CILogon Service.  You can now download a
    certificate to your local computer and then use it to securely access
    <acronym title="National Science Foundation">NSF</acronym>
    cyberinfrastructure resources.  For example, you can use your
    certificate with GSI-SSHTerm to connect to the command
    line of <acronym 
    title="National Science Foundation">NSF</acronym> cyberinfrastructure
    resources.  Note that you will need <a target="_blank"
    href="http://www.javatester.org/version.html">Java 1.5 or higher</a>
    installed on your computer and enabled in your web browser.
    </p>

    <div class="taskdiv">
    <table cellpadding="10" cellspacing="0" class="tasktable">
    <tr class="taskbox">
      <td class="buttons">
    ';

    printFormHead(
        $perl_config->getParam('GridShibCAURL').'shibCILaunchGSCA.jnlp',true);
        
    echo '
      <input type="submit" name="submit" class="submit"
       value="Download Certificate" />
      </form>
      </td>
      <td class="description">
      <h2>1. Download A Certificate To Your Local Computer</h2>
      When you click the "Download Certificate" button, you launch a Java
      Web Start (<acronym title="Java Web Start">JWS</acronym>) application
      on your computer called <a target="_blank"
      href="http://gridshibca.cilogon.org/">GridShib-CA</a>. This
      application fetches a certificate from a <a target="_blank"
      href="http://myproxy.ncsa.uiuc.edu/">MyProxy</a> server.  The
      GridShib-CA <acronym title="Java Web Start">JWS</acronym> application
      then downloads the certificate to your computer and saves it in a
      location known by other grid-enabled desktop applications such as 
      GSI-SSHTerm (below).
      </td>
    </tr>

    <tr class="taskbox">
      <td class="buttons">
    ';

    printFormHead($scriptdir);

    echo '
      <input type="submit" name="submit" class="submit"
       value="GSI-SSHTerm Desktop App" />
      </form>
    ';

    printFormHead($scriptdir);

    echo '
      <input type="submit" name="submit" class="submitmore"
       value="GSI-SSHTerm Web Applet" />
      </form>
      </td>
      <td class="description">
      <h2>2. Launch the GSI-SSHTerm Program</h2>
      GSI-SSHTerm is an example application which can use the certificates
      provided by the CILogon Service.  
      GSI-SSHTerm allows you to establish a remote terminal session 
      with a GSI-enabled SSH server.
      You can run GSI-SSHTerm on your desktop or in your web browser.
      For the "Desktop App" version, be sure to first download
      a certificate to your computer (above).
      </td>
    </tr>

    <tr class="taskbox">
      <td class="buttons">
    ';

    printFormHead($scriptdir);

    echo '
      <input type="submit" name="submit" class="submit" value="Log Off" />
      </form>
      </td>
      <td class="description">
        <h2>3. Log Off The CILogon Service Site</h2>
        To end your CILogon session and return to the welcome page, click
        the "Log Off" button.  Note that this will not log you out of your
        organization\'s authentication service.
      </td>
    </tr>
    </table>
    </div>
    </div>
    ';
    printFooter();
}

/************************************************************************
 * Function   : printFormHead                                           *
 * Parameters : (1) The value of the form's "action" parameter.         *
 *              (2) (Optional) True if extra hidden tags should be      *
 *                  output for the GridShib-CA client application.      *
 *                  Defaults to false.                                  *
 * This function prints out the opening <form> tag for displaying       *
 * submit buttons.  The first parameter is used for the "action" value  *
 * of the <form>.  This function outputs a hidden csrf field in the     *
 * form block.  If the second parameter is given and set to true, then  *
 * additional hidden input elements are also output to be used when the *
 * the GridShib-CA client launches.                                     *
 ************************************************************************/
function printFormHead($action,$gsca=false) {
    global $csrf;
    global $perl_csrf;
    global $perl_config;

    echo '
    <form action="' . $action . '" method="post">
    ';
    echo $csrf->getHiddenFormElement();

    if ($gsca) {
        echo '
        <input type="hidden" name="lifetime" value="default" />
        <input type="hidden" name="lifetimeUnit" value="hours" />';

        $trustCADir = $perl_config->getParam("TrustRoots","TrustRootsPath");
        if ((strlen($trustCADir) > 0) && (is_readable($trustCADir))) {
            echo '
            <input type="hidden" name="DownloadTrustroots" value="true" />
            ';
        }

        $hiddencsrf = $perl_csrf->getFormElement();
        // Fix for when Perl/PHP returns a string as an array element
        if (is_array($hiddencsrf)) {
            echo key($hiddencsrf) . "\n";
        } else {
            echo $hiddencsrf . "\n";
        }
    }
}

/************************************************************************
 * Function   : handleGotUser                                           *
 * This function is called upon return from the "secure/getuser" script *
 * which should have set the 'uid' and 'status' PHP session variables.  *
 * It verifies that the status return is one of STATUS_OK_* (even       *
 * values).  If the return is STATUS_OK_
 ************************************************************************/
function handleGotUser()
{
    global $log;

    $uid = getSessionVar('uid');
    $status = getSessionVar('status');
    # If empty 'uid' or 'status' or odd-numbered status code, error!
    if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
        $log->error('Failed to getuser.');

        printHeader('Error Logging On');
        printPageHeader('ERROR Logging On');

        echo '
        <div class="boxed">
          <div class="boxheader">
            Unable To Log On
          </div>
        ';
        printErrorBox('An internal error has occurred.  System
            administrators have been notified.  This may be a temporary
            error.  Please try again later, or contact us at the the email
            address at the bottom of the page.');

        echo '
        <div>
        ';
        printFormHead(getScriptDir());
        echo '
        <input type="submit" name="submit" class="submit" value="Continue" />
        </form>
        </div>
        </div>
        ';
        printFooter();
    } else { // Got one of the STATUS_OK* status codes
        // If the user got a new DN due to changed SAML attributes,
        // print out a notification page.
        $store = new store();
        if ($status == $store->STATUS['STATUS_OK_USER_CHANGED']) {
            printUserChangedPage();
        } else { // STATUS_OK or STATUS_OK_NEW_USER
            printGetCertificatePage();
        }
    }
}

/************************************************************************
 * Function   : handleGSISSHTermWebApplet                               *
 * This function is called when the user clicks on the 'GSI-SSHTerm     *
 * Web Applet' button.  It tries to fetch a credential for the user     *
 * from the MyProxyCA server.  If it cannot do so, it prints out an     *
 * error message.  Otherwise, it loads a page with the GSI-SSHTerm      *
 * applet.                                                              *
 ************************************************************************/
function handleGSISSHTermWebApplet()
{
    global $log;

    $uid = getSessionVar('uid');
    $cert = getMyProxyForUID($uid);
    if (strlen($cert) > 0) {
        printHeader('GSI-SSHTerm Web Applet');
        printPageHeader('Welcome ' . getSessionVar('idpname') . ' User');

        echo '
        <div class="boxed">
          <div class="boxheader">
            Run The GSI-SSHTerm Web-Based Applet
          </div>
        <div class="javaapplet">
        <applet width="0" height="0" 
        archive="versioncheck.jar"
        code="JavaVersionDisplayApplet" 
        codebase="http://cilogon.org/gsi-sshterm"
        name="jvmversion">
        <b>Please note, you will require at least
        <a target="_blank" href="http://java.sun.com/">Java Software
        Development Kit (SDK) 1.5</a> to launch the applet!</b>
        </applet>
        </div>

        <p class="javaapplet">
        <applet width="640" height="480" 
        archive="GSI-SSHTerm-cilogon.jar"
        code="com.sshtools.sshterm.SshTermApplet" 
        codebase="http://cilogon.org/gsi-sshterm"
        class="gsisshterm">
        <param name="sshterm.gsscredential" value="'.$cert.'" />
        <param name="sshapps.connection.userName" value="" />
        <param name="sshapps.connection.showConnectionDialog" value="true" />
        <param name="sshapps.connection.connectImmediately" value="true" />
        </applet>
        </p>
        <div>
        ';
        printFormHead(getScriptDir());
        echo '
        <input type="submit" name="submit" class="submit" value="Go Back" />
        </form>
        </div>
        </div>
        ';
        printFooter();
    } else { // Could not get a certificate - output error message
        $log->error('Unable to get certificate for GSI-SSHTerm Web Applet.');

        printHeader('Error Running GSI-SSHTerm Web Applet');
        printPageHeader('ERROR Running The GSI-SSHTerm Web Applet');

        echo '
        <div class="boxed">
          <div class="boxheader">
            Unable To Fetch A Certificate For GSI-SSHTerm Applet
          </div>
        ';
        printErrorBox('An internal error occurred and has been logged.  
            This may be a temporary error.  Please try again later, or
            contact us at the the email address at the bottom of the page.');

        echo '
        <div>
        ';
        printFormHead(getScriptDir());
        echo '
        <input type="submit" name="submit" class="submit" value="Go Back" />
        </form>
        </div>
        </div>
        ';
        printFooter();
    }
}

/************************************************************************
 * Function   : printUserChangedPage                                    *
 * This function prints out a notification page informing the user that *
 * some of their attributes have changed, which will affect the         *
 * contents of future issued certificates.  This page shows which       *
 * attributes are different (displaying both old and new values) and    *
 * what portions of the certificate are affected.                       *
 ************************************************************************/
function printUserChangedPage()
{
    global $log;

    $log->info('User IdP attributes changed.');

    $uid = getSessionVar('uid');
    $store = new store();
    $store->getUserObj($uid);
    if (!($store->getUserSub('status') & 1)) {  // STATUS_OK codes are even
        $idpname = $store->getUserSub('idpDisplayName');
        $first   = $store->getUserSub('firstName');
        $last    = $store->getUserSub('lastName');
        $email   = $store->getUserSub('email');
        $dn      = $store->getUserSub('getDN');
        $dn      = preg_replace('/\s+email=.+$/','',$dn);
        $store->getLastUserObj($uid);
        if (!($store->getUserSub('status') & 1)) {  // STATUS_OK codes are even
            $previdpname = $store->getUserSub('idpDisplayName');
            $prevfirst   = $store->getUserSub('firstName');
            $prevlast    = $store->getUserSub('lastName');
            $prevemail   = $store->getUserSub('email');
            $prevdn      = $store->getUserSub('getDN');
            $prevdn      = preg_replace('/\s+email=.+$/','',$prevdn);

            $tablerowodd = true;

            printHeader('Certificate Information Changed');
            printPageHeader('Notice: User Information Changed');

            echo '
            <div class="boxed">
              <div class="boxheader">
                Some Of Your Information Has Changed
              </div>
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
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>Organization Name:</th>
                  <td>'.$previdpname.'</td>
                  <td>'.$idpname.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($first != $prevfirst) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>First Name:</th>
                  <td>'.$prevfirst.'</td>
                  <td>'.$first.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($last != $prevlast) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
                  <th>Last Name:</th>
                  <td>'.$prevlast.'</td>
                  <td>'.$last.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($email != $prevemail) {
                echo '
                <tr' . ($tablerowodd ? ' class="odd"' : '') . '>
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
                    <td>' . $prevdn . '</td>
                  </tr>
                  <tr>
                    <td>Current Subject DN:</td>
                    <td>' . $dn . '</td>
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
            printFormHead(getScriptDir());
            echo '
            <input type="submit" name="submit" class="submit" 
             value="Proceed" />
            </form>
            </div>
            </div>
            ';
            printFooter();

            
        } else {  // Database error, should never happen
            $log->error('Database error reading previous user attributes.');
            $_SESSION = array();  // Clear session variables
            printLogonPage();
        }
    } else {  // Database error, should never happen
        $log->error('Database error reading current user attributes.');
        $_SESSION = array();  // Clear session variables
        printLogonPage();
    }
    
}

/************************************************************************
 * Function   : printErrorBox                                           *
 * Parameter  : HTML error text to be output.                           *
 * This function prints out a bordered box with an error icon and any   *
 * passed-in error HTML text.  The error icon and text are output to    *
 * a <table> so as to keep the icon to the left of the error text.      *
 ************************************************************************/
function printErrorBox($errortext) 
{
    echo '
    <div class="errorbox">
    <table cellpadding="5">
    <tr>
    <td>
    ';
    printIcon('error');
    echo '&nbsp;
    </td>
    <td> ' . $errortext . '
    </td>
    </tr>
    </table>
    </div>
    ';
}

/************************************************************************
 * Function   : getMyProxyForUID                                        *
 * Parameter  : A persistent store user identifier.                     *
 * Returns    : A MyProxy credential, or empty string upon error.       *
 * This function returns a MyProxy credential for a given persistent    *
 * store user identifier.  If there is any error in getting the         *
 * credential, an empty string is returned.                             *
 ************************************************************************/
function getMyProxyForUID($uid)
{
    $retval = '';

    if (strlen($uid) > 0) {
        $store = new store();
        $store->getUserObj($uid);
        $status = $store->getUserSub('status');
        if (!($status & 1)) {  // All OK status vars are even
            $dn = $store->getUserSub('getDN');
            $retval = getMyProxyForDN($dn);
        }
    }

    return $retval;
}

/************************************************************************
 * Function   : getMyProxyForDN                                         *
 * Parameter  : A "DN" string to be passed to myproxy.cilogon.org.      *
 * Returns    : A MyProxy credential, or empty string upon error.       *
 * This function returns a MyProxy credential for a given DN string.    *
 * This "DN" is passed to myproxy.cilogon.org as the '-l' (--username)  *
 * parameter.  It is not a true DN in that it also contains an email=   *
 * field to be put into the credential extension.  This function reads  *
 * the PHP session value of 'loa' (level of assurance) to determine     *
 * whether a 'basic', 'silver', or 'openid' credential should be issued.*
 ************************************************************************/
function getMyProxyForDN($dn) {
    $retval = '';
    $port = 7512;  // Default to 'basic' cert

    if (strlen($dn) > 0) {
        // Check if we should issue 'basic', 'silver', or 'openid' cert
        $loa = getSessionVar('loa');
        if ($loa == 'http://incommonfederation.org/assurance/silver') {
            $port = 7514;
        } elseif ($loa == 'openid') {
            $port = 7516;
        }

        $cert = getMyProxyCredential($dn,'','myproxy.cilogon.org',
                $port,12,'/var/www/config/hostcred.pem','');
        if (strlen($cert) > 0) {
            $retval = $cert;
        }
    }

    return $retval;
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
 * (2) The 'status' (of getUser()) is even (i.e. STATUS_OK_*).          *
 * (3) If $providerId is passed-in, it must match 'idp'.                *
 * If all checks are good, then this function returns true.             *
 ************************************************************************/
function verifyCurrentSession($providerId='') 
{
    $retval = false;

    $uid = getSessionVar('uid');
    $idp = getSessionVar('idp');
    $idpname = getSessionVar('idpname');
    $status = getSessionVar('status');
    if ((strlen($uid) > 0) && (strlen($idp) > 0) && 
        (strlen($idpname) > 0) && (strlen($status) > 0) &&
        (!($status & 1))) {  // All STATUS_OK_* codes are even
        if ((strlen($providerId) == 0) || ($providerId == $idp)) {
            $retval = true;
        }
    }

    return $retval;
}

/************************************************************************
 * Function   : redirectToGetUser                                       *
 * Parameters : (1) An entityID of the authenticating IdP.  If not      *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 *              (2) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 * If the first parameter (a whitelisted entityID) is not specified,    *
 * we check to see if either the providerId PHP session variable or the *
 * providerId cookie is set (in that order) and use one if available.   *
 * The function then checks to see if there is a valid PHP session      *
 * and if the providerId matches the 'idp' in the session.  If so, then *
 * we don't need to redirect to "/secure/getuser/" and instead we       *
 * we display the main "Download Certificate" page.  However, if the    *
 * PHP session is not valid, then this function redirects to the        *
 * "/secure/getuser/" script so as to do a Shibboleth authentication    *
 * via the InCommon WAYF.  When the providerId is non-empty, the WAYF   *
 * will automatically go to that IdP (i.e. without stopping at the      *
 * WAYF).  This function also sets several PHP session variables that   *
 * are needed by the getuser script, including the 'responsesubmit'     *
 * variable which is set as the return 'submit' variable in the         *
 * 'getuser' script.                                                    *
 ************************************************************************/
function redirectToGetUser($providerId='',$responsesubmit='gotuser')
{
    global $csrf;
    global $log;

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = getCookieVar('providerId');
        }
    }

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the 'Download Certificate' button page.
    if (verifyCurrentSession($providerId)) {
        printGetCertificatePage();
    } else { // Otherwise, redirect to the getuser script
        // Set PHP session varilables needed by the getuser script
        $_SESSION['responseurl'] = getScriptDir(true);
        $_SESSION['submit'] = 'getuser';
        $_SESSION['responsesubmit'] = $responsesubmit;
        $csrf->setTheCookie();
        $csrf->setTheSession();

        // Set up the "header" string for redirection thru InCommon WAYF
        $redirect = 
            'Location: https://cilogon.org/Shibboleth.sso/WAYF/InCommon?' .
            'target=' . urlencode(GETUSER_URL);
        if (strlen($providerId) > 0) {
            $redirect .= '&providerId=' . urlencode($providerId);
        }

        $log->info('Shibboleth Login="' . $redirect . '"');
        header($redirect);
    }
}

/************************************************************************
 * Function   : redirectToGetOpenIDUser                                 *
 * Parameters : 
 ************************************************************************/
function redirectToGetOpenIDUser($providerId='',$username='username',
                                 $responsesubmit='gotuser') 
{
    global $csrf;
    global $log;
    global $openid;

    // If providerId not set, try the session and cookie values
    if (strlen($providerId) == 0) {
        $providerId = getSessionVar('providerId');
        if (strlen($providerId) == 0) {
            $providerId = getCookieVar('providerId');
        }
    }

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the 'Download Certificate' button page.
    if (verifyCurrentSession($providerId)) {
        printGetCertificatePage();
    } else { // Otherwise, redirect to the getuser script
        // Set PHP session varilables needed by the getuser script
        $_SESSION['providerId'] = $providerId;
        $_SESSION['idp'] = $providerId;
        $_SESSION['idpname'] = $providerId;
        $_SESSION['loa'] = 'openid';
        $_SESSION['responseurl'] = getScriptDir(true);
        $_SESSION['submit'] = 'getuser';
        $_SESSION['responsesubmit'] = $responsesubmit;
        $csrf->setTheCookie();
        $csrf->setTheSession();

        $log->info('OpenID Login');

        $openid->setProvider($providerId);
        $openid->setUsername($username);
        $store_path = '/tmp/_php_consumer_cilogon';
        if (!(file_exists($store_path)) &&
            !(mkdir($store_path))) {
            // FIXME!
            echo "ERROR CREATING STORE DIRECTORY\n";
            exit(0);
        }
        $filestore = new Auth_OpenID_FileStore($store_path);
        $consumer = new Auth_OpenID_Consumer($filestore);
        $auth_request = $consumer->begin($openid->getURL());
        if (!$auth_request) {
            // FIXME!
            echo "ERROR CREATING AUTH_REQUEST\n";
            exit(0);
        }
        if ($auth_request->shouldSendRedirect()) {
            $redirect_url = $auth_request->redirectURL(
                'https://cilogon.org/',
                'https://cilogon.org/getopeniduser/');
            if (Auth_OpenID::isFailure($redirect_url)) {
                // FIXME!
                echo "ERROR DOING REDIRECT_URL\n";
                exit(0);
            } else {
                header("Location: " . $redirect_url);
            }
        } else {
            $form_id = 'openid_message';
            $form_html = $auth_request->htmlMarkup(
                'https://cilogon.org/',
                'https://cilogon.org/getopeniduser/',
                false, array('id' => $form_id));
            if (Auth_OpenID::isFailure($form_html)) {
                // FIXME!
                echo "ERROR DOING REDIRECT_URL " .
                      $form_html->message . "\n";
                exit(0);
            } else {
                print $form_html;
            }
        }
    }
}

?>