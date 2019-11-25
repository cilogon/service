<?php

/**
 * This file contains functions called by index.php. The index.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;

/**
 * printLogonPage
 *
 * This function prints out the HTML for the IdP Selector page.
 * Explanatory text is shown as well as a button to log in to an IdP
 * and get rerouted to the Shibboleth protected testidp script.
 *
 * @param bool $clearcookies True if the Shibboleth cookies and session
 *        variables  should be cleared out before displaying the page.
 *        Defaults to false.
 */
function printLogonPage($clearcookies = false)
{
    Util::setSessionVar('cilogon_skin', 'orcidfirst');
    Util::getSkin();
    if ($clearcookies) {
        Util::removeShibCookies();
        Util::unsetAllUserSessionVars();
    }

    Util::setSessionVar('stage', 'logon'); // For Show/Hide Help button clicks

    Content::printHeader('Test Your Identity Provider With CILogon');

    echo '
    <div class="boxed">
    ';

    Content::printHelpButton();

    echo '
      <br />
      <p>
      To test that your identity provider works with CILogon, please select
      it from the list below and Log On.
      </p>
    ';

    Content::printWAYF(false);

    echo '
    </div> <!-- End boxed -->
    ';
    Content::printFooter();
}

/**
 * printMainPage
 *
 * This function prints the user attributes and IdP metadata after the user
 * has logged on.
 */
function printMainPage()
{
    // If the 'idp' PHP session variable isn't set, then force the user to
    // start over by logging in again.
    $idp = Util::getSessionVar('idp');
    if (empty($idp)) {
        printLogonPage(true);
        exit; // No further processing necessary
    }

    Util::setSessionVar('stage', 'main'); // For Show/Hide Help button clicks

    Content::printHeader('Test Identity Provider');
    Content::printPageHeader('Test Your Organization\'s Identity Provider');

    // CIL-626 Allow browser 'reload page' by adding CSRF to the PHP session
    Util::setSessionVar('submit', 'Proceed');
    Util::getCsrf()->setTheSession();

    echo '
    <div class="boxed">
    ';

    echo '
    <div class="boxed">
      <div class="boxheader">
        Verify SAML Attribute Release Policy
      </div>

    <p>
    Thank you for your interest in the CILogon Service. This page allows
    the administrator of an Identity Provider (<acronym
    title="Identity Provider">IdP</acronym>) to verify that all necessary
    SAML attributes have been released to the CILogon Service Provider
    (<acronym title="Service Provider">SP</acronym>). Below you will see
    the various attributes required by the CILogon Service and their values
    as released by your IdP.
    </p>

    <div class="summary">
    <h2>Summary</h2>
    ';

    $gotattrs = Util::gotUserAttributes();

    if ($gotattrs) {
        echo '<div class="icon">';
        Content::printIcon('okay');
        echo '
        </div>
        <div class="summarytext">
        <p>
        All required attributes have been released by your <acronym
        title="Identity Provider">IdP</acronym>. For details of the various
        attributes utilized by the CILogon Service and their current values,
        see the sections below.
        </p>
        <p class="addsubmit">
        <a href="/">Proceed to the CILogon Service</a>
        </p>
        <p class="addsubmit">
        <a href="/logout">Logout</a>
        </p>
        </div>
        ';
    } else {
        echo '<div class="icon">';
        Content::printIcon('error', 'Missing one or more attributes.');
        echo '
        </div>
        <div class="summarytext">
        <p>
        One or more of the attributes required by the CILogon Service are
        not available. Please see the sections below for details. Contact
        <a href="mailto:help@cilogon.org">help&nbsp;@&nbsp;cilogon.org</a>
        for additional information and assistance.
        </p>
        <p class="addsubmit">
        <a href="/logout">Logout</a>
        </p>
        </div>
        ';
    }

    echo '
    </div> <!-- summary -->

    <noscript>
    <div class="nojs">
    Javascript is disabled. In order to expand or collapse the sections
    below, please enable Javascript in your browser.
    </div>
    </noscript>
    ';

    Content::printUserAttributes();
    Content::printIdPMetadata();

    echo '
    </div> <!-- End boxed -->
    ';
    Content::printFooter();
}
