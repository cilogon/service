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

    Content::printHeader(_('Test Your Identity Provider With CILogon'));
    Content::printCollapseBegin('testidp', _('Test Your Identity Provider'), false);

    echo '
        <div class="card-body px-5">
          <div class="card-text my-2" id="id-testidp-1">
            ',
            _('To test that your identity provider works with CILogon, ' .
            'please select it from the list below and Log On.'), '
          </div> <!-- end card-text -->
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
    Content::printWAYF(false);
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
        return; // No further processing necessary
    }

    // CIL-626 Allow browser 'reload page' by adding CSRF to the PHP session
    Util::setSessionVar('submit', 'Proceed');
    Util::getCsrf()->setTheSession();

    Content::printHeader(_('Test Identity Provider'));

    Content::printCollapseBegin('showidp', _('Verify Attribute Release'), false);

    echo '
        <div class="card-body px-5">
          <div class="card-text my-2" id="id-testidp-2">
            ',
            _('Thank you for your interest in the CILogon Service. ' .
            'This page enables you to verify that all necessary ' .
            'attributes have been released to the CILogon Service Provider ' .
            '(SP) by your selected Identity Provider (IdP). ' .
            'Below you will see the various attributes required by the ' .
            'CILogon Service and their values as released by your IdP.'), '
          </div> <!-- end card-text -->
    ';

    echo '
          <div class="row my-3">
            <div class="col-1 text-center">';

    if (
        ((strlen(Util::getSessionVar('remote_user')) > 0) ||
            (strlen(Util::getSessionVar('eppn')) > 0) ||
            (strlen(Util::getSessionVar('eptid')) > 0) ||
            (strlen(Util::getSessionVar('subject_id')) > 0) ||
            (strlen(Util::getSessionVar('pairwise_id')) > 0) ||
            (strlen(Util::getSessionVar('open_id')) > 0) ||
            (strlen(Util::getSessionVar('oidc')) > 0)) &&
        (strlen(Util::getSessionVar('idp')) > 0) &&
        (strlen(Util::getSessionVar('idp_display_name')) > 0)
    ) {
        echo '<large>',
            Content::getIcon('fa-check-square fa-2x', 'lime'), '</large>
            </div> <!-- end col-1 -->
            <div class="col">
              ',
              _('All required attributes have been released by your ' .
              'IdP. For details of the various attributes utilized ' .
              'by the CILogon Service and their current values, ' .
              'see the sections below.'), '
            </div>
          </div> <!-- end row -->
          <div class="row align-items-center justify-content-center">
            <div class="col-auto">
              <a class="btn btn-primary"
              title="', _('Proceed to the CILogon Service'), '"
              href="/">', _('Proceed to the CILogon Service'), '</a>
            </div> <!-- end col-auto -->
        ';
    } else {
        echo Content::getIcon(
            'fa-exclamation-circle fa-2x',
            'red',
            _('Missing one or more attributes.')
        ), '
            </div> <!-- end col-1 -->
            <div class="col">
              ',
              _('One or more of the attributes required by the CILogon ' .
              'Service are not available. Please see the sections below ' .
              'for details. For additional information and assistance, ' .
              'please contact'),
              ' <a href="mailto:', EMAIL_HELP, '">', EMAIL_HELP, '</a>',
              '
            </div>
          </div> <!-- end row -->
          <div class="row align-items-center justify-content-center">
        ';
    }
    echo '
            <div class="col-auto">
               <a class="btn btn-primary"
               title="', _('Logout'), '"
               href="/logout">', _('Logout'), '</a>
            </div> <!-- end col-auto -->
          </div> <!-- end row align-items-center -->
        </div> <!-- end card-body --> ';

    Content::printCollapseEnd();

    Content::printUserAttributes();
    Content::printIdPMetadata();
    Content::printFooter();
}
