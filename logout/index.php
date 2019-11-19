<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

Util::startPHPSession();

$log = new Loggit();
$log->info('Logout page hit.');

$idp     = Util::getSessionVar('idp');
$idpname = Util::getSessionVar('idpname');
$skin    = Util::getSessionVar('cilogon_skin'); // Preserve the skin

Util::removeShibCookies();
Util::unsetUserSessionVars();
Util::setSessionVar('cilogon_skin', $skin); // Re-apply the skin

Content::printHeader('Logged Out of the CILogon Service');

Util::unsetSessionVar('cilogon_skin'); // Clear the skin

echo '
<div class="boxed">
  <br class="clear"/>
  <p>
  You have successfully logged out of CILogon.
  </p>
';

if ($idp == 'https://accounts.google.com/o/oauth2/auth') {
    echo '
    <p>
    You can optionally click the link below to log out of Google.
    However, this will log you out from ALL of your Google accounts.
    Any current Google sessions in other tabs/windows may be invalidated.
    </p>
    <p>
    <a href="https://accounts.google.com/Logout">(Optional)
    Logout from Google</a>
    </p>
    ';
} elseif ($idp == 'https://github.com/login/oauth/authorize') {
    echo '
    <p>
    You can optionally click the link below to log out of GitHub.
    </p>
    <p>
    <a href="https://github.com/logout">(Optional) Logout from GitHub</a>
    </p>
    ';
} elseif ($idp == 'https://orcid.org/oauth/authorize') {
    echo '
    <p>
    You can optionally click the link below to log out of ORCID.
    Note that ORCID will redirect you to the ORCID Sign In page.
    You can ignore this as your authentication session with ORCID
    will have been cleared first.
    </p>
    <p>
    <a href="https://orcid.org/signout">(Optional) Logout from ORCID</a>
    </p>
    ';
} elseif (!empty($idp)) {
    if (empty($idpname)) {
        $idpname = 'your Identity Provider';
    }
    $idplist = Util::getIdpList();
    $logout = $idplist->getLogout($idp);
    if (empty($logout)) {
        echo '
          <p>
          You may still be logged in to ', $idpname , '.
          Close your web browser or <a target="_blank"
          href="https://www.lifewire.com/how-to-delete-cookies-2617981">clear
          your cookies</a> to clear your authentication session.
          </p>
        ';
    } else {
        echo '
        <p>
        You can optionally click the link below to log out of ' , $idpname , '.
        Note that some Identity Providers do not support log out. If you
        receive an error, close your web browser or <a target="_blank"
        href="https://www.lifewire.com/how-to-delete-cookies-2617981">clear
        your cookies</a> to clear your authentication session.
        </p>
        <p>
        <a href="' , $logout , '">(Optional) Logout from ' , $idpname , '</a>
        </p>
        ';
    }
}

echo '
</div> <!-- End boxed -->
';

Content::printFooter();
