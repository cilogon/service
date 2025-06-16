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
 *        variables should be cleared out before displaying the page.
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
    $log->info('Welcome page hit.', false, false);

    Content::printHeader('Welcome To The CILogon Service');
    Content::printWAYF();
    Content::printFooter();
}

/**
 * printMainPage
 *
 * This function prints out the HTML for the main page where the user
 * can view their attributes (user and IdP). Before June 2025, the user
 * could also download a certificate.
 */
function printMainPage()
{
    $log = new Loggit();
    $log->info('Get And Use Certificate page hit.', false, false);

    // CIL-626 Allow browser 'reload page' by adding CSRF to the PHP session
    Util::setSessionVar('submit', 'Proceed');
    Util::getCsrf()->setTheSession();

    Content::printHeader('CILogon Service');
    Content::printUserAttributes();
    Content::printIdPMetadata();
    Content::printLogOff();
    Content::printFooter();
}
