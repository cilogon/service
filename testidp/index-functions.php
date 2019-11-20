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
 */
function printLogonPage()
{
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

    Content::printWAYF(false, true);

    echo '
    </div> <!-- End boxed -->
    ';
    Content::printFooter();
}

/**
 * redirectToTestIdP
 *
 * If the first parameter (a whitelisted entityId) is not specified,
 * we check to see if either the providerId PHP session variable or the
 * providerId cookie is set (in that order) and use one if available.
 * Then this function redirects to the "/secure/testidp/" script so as
 * to do a Shibboleth authentication via mod_shib.  When the providerId
 * is non-empty, the SessionInitiator will automatically go to that IdP
 * (i.e. without stopping at a WAYF).
 *
 * @param string $providerId (Optionals) An entityId of the authenticating
 *        IdP. If not specified (or set to the empty string), we check
 *        providerId PHP session variable and providerId cookie (in that
 *        order) for non-empty values.
 */
function redirectToTestIdP($providerId = '')
{
    // If providerId not set, try the cookie value
    if (empty($providerId)) {
        $providerId = Util::getCookieVar('providerId');
    }

    // Set up the "header" string for redirection thru mod_shib
    $testidp_url = 'https://' . Util::getHN() . '/secure/testidp/';
    $redirect =
        'Location: https://' . Util::getHN() . '/Shibboleth.sso/Login?' .
        'target=' . urlencode($testidp_url);
    if (!empty($providerId)) {
        $redirect .= '&providerId=' . urlencode($providerId);
    }

    header($redirect);
    exit; // No further processing necessary
}
