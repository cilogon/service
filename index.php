<?php

require_once('include/autoloader.php');
require_once('include/content.php');
require_once('include/shib.php');
require_once('include/util.php');

startPHPSession();

/* The full URL of the Shibboleth-protected getuser script. */
define('GETUSER_URL','https://cilogon.org/secure/getuser/');

/* Read in the whitelist of currently available IdPs. */
$white = new whitelist();

/* Loggit object for logging info to syslog. */
$log = new loggit();

/* Check the csrf cookie against either a hidden <form> element or a *
 * PHP session variable, and get the value of the "submit" element.  */
$submit = csrf::verifyCookieAndGetSubmit();

$log->info('submit="' . $submit . '"');

/* Depending on the value of the clicked "submit" button or the    *
 * equivalent PHP session variable, take action or print out HTML. */
switch ($submit) {

    case 'Log On': // User selected an IdP - go to getuser script
        // Verify that providerId is set and is in the whitelist
        $providerIdPost = getPostVar('providerId');
        if ((strlen($providerIdPost) > 0) &&
            ($white->exists($providerIdPost))) {
            setcookie('providerId',$providerIdPost,
                      time()+60*60*24*365,'/','',true);
            // Set the cookie for keepidp if the checkbox was checked
            if (strlen(getPostVar('keepidp')) > 0) {
                setcookie('keepidp','checked',time()+60*60*24*365,'/','',true);
            } else {
                setcookie('keepidp','',time()-3600,'/','',true);
            }
            // Finally, redirect to the getuser script
            redirectToGetuser($providerIdPost);
        } else { // Either providerId not set or not in whitelist
            printLogonPage();
        }
    break; // End case 'Log On'

    case 'Log Off':
        deleteShibCookies();
        clearSession();
        printLogonPage();
    break; // End case 'Log Off'

    case 'gotuser': // Return from the getuser script
    case 'main':    // Display main 'fetch certificate' page
        printGetCertificatePage();
    break; // End case 'gotuser'

    /*
    case 'GSI-SSHTerm Desktop App':
        $log->info('Launching GSI-SSHTerm Desktop App');
        $_SESSION['submit'] = 'main';
        header('Location: http://cilogon.org/gsi-sshterm/ncsa.jnlp');
    break; // End case 'GSI-SSHTerm Desktop App'

    case 'GSI-SSHTerm Web Applet':
        handleGSISSHTermWebApplet();
    break; // End case 'GSI-SSHTerm Web Applet'
    */

    default: // No submit button clicked nor PHP session variable set
        /* If both the "keepidp" and the "providerId" cookies were set 
         * (and the providerId is a whitelisted IdP) then skip the 
         * Logon page and proceed to the getuser script.  */
        $providerIdCookie = urldecode(getCookieVar('providerId'));
        if ((strlen($providerIdCookie) > 0) && 
            (strlen(getCookieVar('keepidp')) > 0) &&
            ($white->exists($providerIdCookie))) {
            redirectToGetuser($providerIdCookie);
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
    printHeader('Welcome to the CILogon Service');
    printPageHeader('Welcome to the CILogon Service');

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
    printHeader('Get Your Certificate');
    printPageHeader('Welcome ' . getSessionVar('idpname') . ' User');

    echo '
    <div class="boxed">
      <div class="boxheader">
        Fetch And Utilize A CILogon Certificate
      </div>
    <p>
    You are logged on to the CILogon Service.  You can now download a
    certificate to your local computer\'s desktop.  You can utilize this
    certificate by launching the GSI-SSHTerm desktop application, which will
    allow you to connect to the command line of <acronym 
    title="National Science Foundation">NSF</acronym> cyberinfrastructre
    resources.  Note that you will need <a target="_blank"
    href="http://www.javatester.org/version.html">Java 1.5 or higher</a>
    installed on your local computer and enabled in your web browser.
    </p>

    <div class="taskdiv">
    <table cellpadding="10" cellspacing="0" class="tasktable">
    <tr class="taskbox">
      <td class="buttons">
    ';

    printFormHead();
        
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
      href="http://myproxy.teragrid.org/">MyProxy</a> server.  The
      GridShib-CA <acronym title="Java Web Start">JWS</acronym> application
      then downloads the certificate to your computer and saves it in a
      location known by other grid-enabled desktop applications such as 
      GSI-SSHTerm (below).
      </td>
    </tr>

    <tr class="taskbox">
      <td class="buttons">
    ';

    printFormHead();

    echo '
      <input type="submit" name="submit" class="submit"
       value="GSI-SSHTerm Desktop App" />
      <br />
      <input type="submit" name="submit" class="submitmore"
       value="GSI-SSHTerm Web Applet" />
      </form>
      </td>
      <td class="description">
      <h2>2. Launch the GSI-SSHTerm Program</h2>
      GSI-SSHTerm is an SSH-based terminal application which can utilize the
      certificates served by the CILogon Service.  GSI-SSHTerm can be run as
      a desktop application or as a browser-based web applet.  For the
      "Desktop App" version, be sure to first download a certificate to your
      desktop (above).
      </td>
    </tr>

    <tr class="taskbox">
      <td class="buttons">
    ';

    printFormHead();

    echo '
      <input type="submit" name="submit" class="submit"
       value="Log Off" />
      </form>
      </td>
      <td class="description">
        <h2>3. Log Off The CILogon Service Site</h2>
        To end your session and return to the welcome page, click the 
        "Log Off" button.  
      </td>
    </tr>
    </table>
    </div>
    ';

    /*
    echo '
    <p>
    <br/><hr/><br/><b>$_SESSION</b><table>
    ';

    foreach ($_SESSION as $key => $value) {
        echo '<tr><td>'.$key.'</td><td>'.$value.'</td></tr>';
    }

    echo '</table>
    </p>
    ';
    */

    echo '
    </div>
    ';

    printFooter();
}

/************************************************************************
 * Function   : printFormHead                                           *
 * This function 
 ************************************************************************/
function printFormHead($action='') {
    /*
    global $perl_csrf;
    global $perl_config;
    */
    global $csrf;

    $formaction = getScriptDir();
    
    if ($action == 'gridshib-ca') {
        /*
        $formaction = $perl_config->getParam("ShibProtectedURL") .
                                             "/shibLaunchGSCA.jnlp";
        */
    }

    echo '
    <form action="' . $formaction . '" method="post">
    ';
    echo $csrf->getHiddenFormElement();

    if ($action == 'gridshib-ca') {
        echo '
        <input type="hidden" name="lifetime" value="default">
        <input type="hidden" name="lifetimeUnit" value="hours">';

        /*
        $trustedCADirectory = $perl_config->getParam("TrustRoots",
                                                     "TrustRootsPath");
        if ((strlen($trustedCADirectory) > 0) && 
            (is_readable($trustedCADirectory))) {
            echo '
            <input type="hidden" name="DownloadTrustroots" value="true">
            ';
        }
        */
    }

    /*
    $hiddencsrf = $perl_csrf->getFormElement();
    if (is_array($hiddencsrf)) {
        echo  key($hiddencsrf) . "\n";
    } else {
        echo $hiddencsrf . "\n";
    }
    */
}

/************************************************************************
 * Function   : handleGSISSHTermWebApplet                               *
 * This funciton
 ************************************************************************/
function handleGSISSHTermWebApplet()
{

}

/************************************************************************
 * Function   : redirectToGetuser                                       *
 * Parameter  : (Optional) An entityID of the authenticating IdP.       *
 * This function redirects to the "/secure/getuser/" script so as to    *
 * do a Shibboleth authentication via the InCommon WAYF.  If the        *
 * optional parameter (a whitelisted entityID) is specified, the WAYF   *
 * will automatically go to that IdP (i.e. without stopping at the      *
 * WAYF).  This function also sets several PHP session variables that   *
 * are needed by the getuser script.                                    *
 ************************************************************************/
function redirectToGetuser($providerId='')
{
    global $csrf;
    // Set PHP session varilables needed by the getuser script
    $csrf->setTheCookie();
    $csrf->setTheSession();
    $_SESSION['responseurl'] = getScriptDir(true);
    $_SESSION['submit'] = 'getuser';

    // Set up the "header" string for redirection thru InCommon WAYF
    $redirect = 'Location: https://cilogon.org/Shibboleth.sso/WAYF/InCommon?' .
        'target=' . urlencode(GETUSER_URL);
    if (strlen($providerId) > 0) {
        $redirect .= '&providerId=' . urlencode($providerId);
    }
    header($redirect);
}

/************************************************************************
 * Function   : clearSession                                            *
 * This function clears (unsets) all of the PHP session values.         *
 ************************************************************************/
function clearSession() {
    while (list($key,$val) = each($_SESSION)) {
        unset($_SESSION[$key]);
    }
}

?>
