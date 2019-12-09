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

Content::printCollapseBegin('logout', 'Logged Out of CILogon', false);

echo '
    <div class="card-body px-5">
      <div class="card-text my-2">
        You have successfully logged out of CILogon.
      </div> <!-- end card-text -->
';

if ($idp == 'https://accounts.google.com/o/oauth2/auth') {
    echo '
      <div class="card-text my-2">
        You can optionally click the link below to log out of Google.
        However, this will log you out from ALL of your Google accounts.
        Any current Google sessions in other tabs/windows may be invalidated.
      </div>
      <div class="row align-items-center justify-content-center mt-3">
        <div class="col-auto">
          <a class="btn btn-primary"
          href="https://accounts.google.com/Logout">(Optional)
          Logout from Google</a>
        </div> <!-- end col-auto -->
      </div> <!-- end row align-items-center -->
    ';
} elseif ($idp == 'https://github.com/login/oauth/authorize') {
    echo '
      <div class="card-text my-2">
        You can optionally click the link below to log out of GitHub.
      </div>
      <div class="row align-items-center justify-content-center mt-3">
        <div class="col-auto">
          <a class="btn btn-primary"
          href="https://github.com/logout">(Optional) Logout from GitHub</a>
        </div> <!-- end col-auto -->
      </div> <!-- end row align-items-center -->
    ';
} elseif ($idp == 'https://orcid.org/oauth/authorize') {
    echo '
      <div class="card-text my-2">
        You can optionally click the link below to log out of ORCID.
        Note that ORCID will redirect you to the ORCID Sign In page.
        You can ignore this as your authentication session with ORCID
        will have been cleared first.
      </div>
      <div class="row align-items-center justify-content-center mt-3">
        <div class="col-auto">
          <a class="btn btn-primary"
          href="https://orcid.org/signout">(Optional) Logout from ORCID</a>
        </div> <!-- end col-auto -->
      </div> <!-- end row align-items-center -->
    ';
} elseif (!empty($idp)) {
    if (empty($idpname)) {
        $idpname = 'your Identity Provider';
    }
    $idplist = Util::getIdpList();
    $logout = $idplist->getLogout($idp);
    if (empty($logout)) {
        echo '
      <div class="card-text my-2">
        You may still be logged in to ', $idpname , '.
        Close your web browser or <a target="_blank"
        href="https://www.lifewire.com/how-to-delete-cookies-2617981">clear
        your cookies</a> to clear your authentication session.
      </div>
      ';
    } else {
        echo '
      <div class="card-text my-2">
        You can optionally click the link below to log out of ' , $idpname , '.
        Note that some Identity Providers do not support log out. If you
        receive an error, close your web browser or <a target="_blank"
        href="https://www.lifewire.com/how-to-delete-cookies-2617981">clear
        your cookies</a> to clear your authentication session.
      </div>
      <div class="row align-items-center justify-content-center mt-3">
        <div class="col-auto">
          <a class="btn btn-primary"
          href="' , $logout , '">(Optional) Logout from ' , $idpname , '</a>
        </div> <!-- end col-auto -->
      </div> <!-- end row align-items-center -->
      ';
    }
}

echo '
    </div> <!-- end card-body -->   
';

Content::printCollapseEnd();
Content::printFooter();
