<?php

/**
 * This file contains functions called by index.php. The index.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\PortalCookie;

/**
 * printMainCookiesPage
 *
 * This function prints out the main 'Manage Cookies' page on each
 * reload. It calls other functions to print out the subsections of
 * the page. Note that all subsections are enclosed in a <form> so
 * we need only a single <form> group.
 */
function printMainCookiesPage()
{
    // CIL-555 Allow for deletion of session/cookie vars without
    // refreshing the user's browser.
    if ((isset($_GET['nooutput'])) || (isset($_POST['nooutput']))) {
        http_response_code(204);
        exit;
    }

    $browsercount = countBrowserCookies();
    $sessioncount = countSessionVariables();

    /* CIL-1416 Check for query parameter 'hide' to collapse
     * any of the three informational sections. Value for 'hide'
     * parameter is any of 'browser', 'session', and/or
     * 'environment', any order, separated by comma.
     */
    $gethide = Util::getGetOrPostVar('hide');

    Content::printHeader(_('Manage CILogon Cookies'), false); // Don't set CSRF

    Content::printFormHead(_('Manage Cookies'));

    printAboutThisPage($browsercount, $sessioncount, $gethide);
    printBrowserCookies($browsercount, (preg_match('/browser/i', $gethide)));
    printSessionVariables($sessioncount, (preg_match('/session/i', $gethide)));
    printEnvironmentVars(preg_match('/environment/i', $gethide));

    echo '</form>';

    Content::printFooter();
}

/**
 * printAboutThisPage
 *
 * This function prints the 'About This Page' subsection, which also
 * contains several 'submit' buttons (such as .Delete Browser Cookies.
 * and 'Reload Page').
 *
 * @param int $browsercount The number of deletable browser cookies.
 * @param int $sessioncount The number of deletable session variables
 * @param string $gethide (Optional) Which sections to hide: browser,
 *        session, and/or environment; comma-separated.
 */
function printAboutThisPage($browsercount, $sessioncount, $gethide = '')
{
    Content::printCollapseBegin('aboutme', _('CILogon Attributes'), false);

    echo '
        <div class="card-body px-5">
          <div class="card-text my-2" id="id-me-cilogon-attributes-1">
            ',
            _('This page enables you to view and delete various ' .
            'cookies associated with the'), ' ' ,
            '<a target="_blank" href="..">', 
            _('CILogon Service'), '</a>. ',
            _('There are three sections below.'), '
          </div> <!-- end card-text -->
          <ol>
            <li><b>', _('Browser Cookies'), '</b> - ', _('These are ' .
            'cookies which are stored in your browser. ' .
            'They are used as preferences for the CILogon Service.'), '
            </li>
            <li><b>', _('Session Variables'), '</b> - ', _('These are ' .
            'short-lived values related to your current ' .
            'CILogon session. Deleting any of these values may require ' .
            'you to re-logon.'), '
            </li>
            <li><b>', _('Environment Variables'), '</b> - ', _('These are ' .
            'values set by the interaction between your browser and the web ' .
            'server. These are displayed mainly for information purposes.'), '
            </li>
          </ol>
    ';

    // If there are brower cookies or session variables which can be
    // deleted, output the appropriate 'Delete ...' button(s).
    if (($browsercount > 0) || ($sessioncount > 0)) {
        echo '
          <div class="card-text my-2" id="id-me-cilogon-attributes-2">
            ',
            _('You can delete cookies individually by checking the ' .
            'associated checkbox(es) and clicking the &quot;Delete ' .
            'Checked&quot; button. You can also delete groups of ' .
            'cookies by clicking'), ' ';
        if ($browsercount > 0) {
            echo _('the &quot;Delete Browser Cookies&quot; button');
            if ($sessioncount > 0) {
                echo ', ';
            }
        }

        if ($sessioncount > 0) {
            echo _('the &quot;Delete Session Variables&quot; button');
            if ($browsercount > 0) {
                echo ', ';
            }
        }
        echo _(' or the &quot;Delete ALL&quot; button');
        echo '.
          </div> <!-- end card-text -->';
    }

    echo '
          <div class="row align-items-center justify-content-center">
            <div class="col-auto">
              <a class="btn btn-primary form-control"
              title="', _('Proceed to the CILogon Service'), '"
              href="/">', _('Proceed to the CILogon Service'), '</a>
            </div> <!-- end col-auto -->';

    // CIL-1416 Put the "hide" parameter in the form for next page load
    if (strlen($gethide) > 0) {
        echo '
            <input type="hidden" name="hide" value="', $gethide, '">';
    }

    if ($browsercount > 0) {
        echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="', _('Delete Browser Cookies'), '"
              title="', _('Delete Browser Cookies'), '" />
            </div> <!-- end col-auto -->';
    }
    if ($sessioncount > 0) {
        echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="', _('Delete Session Variables'), '"
              title="', _('Delete Session Variables'), '" />
            </div> <!-- end col-auto -->';
    }
    if (($browsercount > 0) || ($sessioncount > 0)) {
        echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="', _('Delete ALL'), '"
              title="', _('Delete ALL'), '" />
            </div> <!-- end col-auto -->';
    }
    echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="', _('Reload Page'), '"
              title="', _('Reload Page'), '" />
            </div> <!-- end col-auto -->
          </div> <!-- end row align-items-center -->
        </div> <!-- end card-body --> ';

        Content::printCollapseEnd();
}

/**
 * printBrowserCookies
 *
 * This function prints the 'Browser Cookies' section, with checkboxes
 * next to the cookies to allow for deletion. If there are no browser
 * cookies, then simply output 'none found' message.
 *
 * @param int $browsercount The number of deletable browser cookies
 * @param bool $collapsed Initially display the section collapsed or not
 */
function printBrowserCookies($browsercount, $collapsed = false)
{
    global $hide;

    Content::printCollapseBegin('cookies', _('Browser Cookies'), $collapsed);

    if ($browsercount > 0) {
        echo '
        <div class="card-body">
          <table class="table table-striped table-sm table-hover small"
          aria-label="', _('Browser Cookies'), '">
          <tbody>
        ';

        ksort($_COOKIE);
        foreach ($_COOKIE as $key => $value) {
            if (!in_array($key, $hide)) {
                echo '<tr title="' , getTitleText($key) , '">',
                     '<td><input type="checkbox" name="del_browser[]" ',
                     'value="', $key , '" title="', $key, '"/></td>',
                     '<th scope="row" style="word-break: break-all"><samp>',
                     Util::htmlent($key),
                     '</samp></th><td><samp>';
                // Special handling of portalparams cookie
                if ($key == PortalCookie::COOKIENAME) {
                    $pc = new PortalCookie();
                    echo preg_replace('/\nportal=/', '<br/>portal=/', Util::htmlent($pc));
                } else {
                    echo Util::htmlent($value);
                }
                echo '</samp></td></tr>';
            }
        }

        echo '
          </tbody>
          </table>

          <div class="row align-items-center justify-content-center">
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="', _('Delete Checked'), '"
              title="', _('Delete Checked'), '" />
            </div> <!-- end col-auto -->
          </div> <!-- end row align-items-center -->';
    } else {
        echo '
        <div class="card-body px-5">
          <div class="row">
            <div class="col-auto">
              ',
              _('No browser cookies found.'), '
            </div>
          </div>';
    }

    echo '
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
}

/**
 * printSessionVariables
 *
 * This function prints the 'Session Variables' section, with
 * checkboxes  next to the variables to allow for deletion. If there
 * are no session variables, then simply output 'none found' message.
 *
 * @param int $sessioncount The number of deletable session variables
 * @param bool $collapsed Initially display the section collapsed or not
 */
function printSessionVariables($sessioncount, $collapsed = false)
{
    global $hide;

    Content::printCollapseBegin('session', _('Session Variables'), $collapsed);

    if ($sessioncount > 0) {
        echo '
        <div class="card-body">
          <table class="table table-striped table-sm table-hover small"
          aria-label="', _('Session Variables') ,'">
          <tbody>
        ';

        ksort($_SESSION);
        foreach ($_SESSION as $key => $value) {
            if (!in_array($key, $hide)) {
                echo '<tr title="' , getTitleText($key) , '">',
                     '<td><input type="checkbox" name="del_session[]" ',
                     'value="', $key , '" title="', $key, '"/></td>',
                     '<th scope="row"><samp>',
                     Util::htmlent($key),
                     '</samp></th><td><samp>',
                     Util::htmlent(is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value),
                     '</samp></td></tr>';
            }
        }

        echo '
          </tbody>
          </table>

          <div class="row align-items-center justify-content-center">
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="', _('Delete Checked'), '"
              title="', _('Delete Checked'), '" />
            </div> <!-- end col-auto -->
          </div> <!-- end row align-items-center -->';
    } else {
        echo '
        <div class="card-body px-5">
          <div class="row">
            <div class="col-auto">
              ',
              _('No session variables found.'), '
            </div>
          </div>';
    }

    echo '
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
}

/**
 * printEnvironmentVars
 *
 * This function prints out the display-only web environment variables
 * (e.g. the $_SERVER array).
 *
 * @param bool $collapsed Initially display the section collapsed or not
 */
function printEnvironmentVars($collapsed = false)
{
    Content::printCollapseBegin('environment', _('Environment Variables'), $collapsed);

    echo '
        <div class="card-body">
          <table class="table table-striped table-hover table-sm small"
          aria-label="', _('Environment Variables'), '">
          <tbody>
    ';

    ksort($_SERVER);
    foreach ($_SERVER as $key => $value) {
        echo '<tr><th scope="row"><samp>',
             Util::htmlent($key),
             '</samp></th><td><samp>',
             Util::htmlent($value),
             '</samp></td></tr>';
    }

    echo '
          </tbody>
          </table>
        </div> <!-- end card-body -->
    ';

    Content::printCollapseEnd();
}

/**
 * countBrowsercookies
 *
 * This function counts the number of elements in the $_COOKIE array,
 * minus those elements in the global $hide array.
 *
 * @return int The number of deletable browser cookies
 */
function countBrowserCookies()
{
    global $hide;

    $retval = count($_COOKIE);

    foreach ($hide as $h) {
        if (isset($_COOKIE[$h])) {
            $retval--;
        }
    }

    if ($retval < 0) {
        $retval = 0;
    }

    return $retval;
}

/**
 * countSessionVariables
 *
 * This function counts the number of elements in the $_SESSION array,
 * minus those elements in the global $hide array.
 *
 * @return int The number of deletable session variables
 */
function countSessionVariables()
{
    global $hide;

    $retval = count($_SESSION);

    foreach ($hide as $h) {
        if (isset($_SESSION[$h])) {
            $retval--;
        }
    }

    if ($retval < 0) {
        $retval = 0;
    }

    return $retval;
}

/**
 * deleteChecked
 *
 * This function is called when the 'Delete Checked' button is clicked.
 * It iterates through all of the checked boxes for the 'Browser
 * Cookies' and 'Session Variables' sections and deletes the
 * corresponding cookie or session variable.
 */
function deleteChecked()
{
    $del_browser = Util::getPostVar('del_browser');
    if (is_array($del_browser)) {
        foreach ($del_browser as $value) {
            Util::unsetCookieVar($value);
        }
    }

    $del_session = Util::getPostVar('del_session');
    if (is_array($del_session)) {
        foreach ($del_session as $value) {
            Util::unsetSessionVar($value);
        }
    }
}

/**
 * deleteBrowserCookies
 *
 * This function is called when the 'Delete Browser Cookies' button
 * or the 'Delete ALL' button is pressed. It deletes all elements in
 * the $_COOKIE array except for those in the global $hide array.
 */
function deleteBrowserCookies()
{
    global $hide;

    foreach ($_COOKIE as $key => $value) {
        if (!in_array($key, $hide)) {
            Util::unsetCookieVar($key);
        }
    }
}

/**
 * deleteSessionVariables
 *
 * This function is called when the 'Delete Session Variables' button
 * or the 'Delete ALL' button is pressed. It deletes all elements in
 * the $_SESSION array except for those in the global $hide array.
 */
function deleteSessionVariables()
{
    global $hide;

    foreach ($_SESSION as $key => $value) {
        if (!in_array($key, $hide)) {
            Util::unsetSessionVar($key);
        }
    }
}

/**
 * getTitleText
 *
 * This function takes in a browser cookie or session variable and
 * returns an 'explanation' string which is used in the 'title=...'
 * attribute. This text is displayed in the user's browser when the
 * mouse cursor hovers over the row containing the cookie/variable
 * and corresponding value. The function simply looks in the $explain
 * array for the $cookie key, and returns the value (if any).
 *
 * @param string $cookie The name of a browser cookie or session variable.
 * @return string  A string explaining the cookie/variable in question.
 *         Returns empty string if no such cookie/variable found.
 */
function getTitleText($cookie)
{
    $retval = '';

    // Keys are brower cookies / session variables. Values are
    // explanation string to be shown in 'title=...' attributes.
    // NOTE: the array is searched using 'preg_match' to allow for
    // substring matches (which is important in the case of
    // _shibsession...). Thus, it is important that longer strings
    // appear before shorter strings with the same prefix.
    $explain = array(
        "acr" => "Authentication Context Class Ref",
        "amr" => "Authentication Method Ref",
        "affiliation" => _("A list of attributes describing your affiliations at your Identity Provider."),
        "authntime" => _("The Unix timestamp of the last successful user authentication."),
        "callbackuri" => _("The URL of the callback servlet used by " .
            "portals connecting to the CILogon Delegate service."),
        "cilogon_skin" => _("The skin affects the look-and-feel and " .
            "functionality of the CILogon Service. It is typically " .
            "specified by a portal."),
        "clientparams" => _("A set of cookies for each portal you have used with CILogon."),
        "display_name" => _("Your full name set by your Identity Provider."),
        "eduPersonOrcid" => _("ORCID identifier."),
        "email" => _("Your email address given by your Identity Provider."),
        "entitlement" => _("A list of URIs representing permissions to access a resource or service."),
        "eppn" => _("'eduPerson Principal Name' - a SAML attribute set by your Identity Provider."),
        "eptid" => _("'eduPerson Targeted Identifier' - a SAML attribute set by your Identity Provider."),
        "failureuri" => _("A URL used by portals in case of error."),
        "first_name" => _("Your given name set by your Identity Provider."),
        "idp_display_name" => _("The display name of your chosen Identity Provider."),
        "idp" => _("The authentication URI of your chosen Identity Provider."),
        "itrustuin" => _("Your university ID number."),
        "keepidp" => _("Remember if you checked the 'Remember this " .
            "selection' checkbox when you selected and Identity Provider."),
        "last_name" => _("Your surname set by your Identity Provider."),
        "loa" => _("Level of Assurance set by your Identity Provider."),
        "logonerror" => _("A text message of the reason for the last authentication error."),
        "member_of" => _("Groups of which you are a member."),
        "oidc" => _("Your user identifier set by the OpenID Connect Identity Provider."),
        "open_id" => _("Your user identifier set by the OpenID Identity Provider."),
        "ou" => _("Your organizational unit set by your Identity Provider."),
        "pairwise_id" => _("The pairwise subject identifier provided by the Identity Provider."),
        "portalcookie" => _("Contains certificate lifetimes for all " .
            "portals you have used with the CILogon Delegate service."),
        "portalname" => _("The display name of the portal connected to the CILogon Delegate service."),
        "portalparams" => _("For portals previously using the CILogon " .
            "Delegate service, this is the saved lifetime of the " .
            "delegated certificate."),
        "portalstatus" => _("An internal return code when fetching portal parameters from the datastore."),
        "preferred_username" => _("The GitHub login name. Should not be used as a persistent identifier."),
        "providerId" => _("The previously selected Identity Provider."),
        "recentidps" => _("A list of the most recently selected Identity Providers."),
        "responsesubmit" => _("The name of the page to return to after " .
            "authentication at your chosen Identity Provider."),
        "responseurl" => _("The URL to return to after authentication at your chosen Identity Provider."),
        "_shibsession" => _("A shibboleth session token set by an InCommon Identity Provider."),
        "showhidden" => _("Always show any hidden IdPs."),
        "sso_idp_array" => _("Keep track of IdPs used for Single Sign On (SSO)."),
        "status" => _("An internal return code when fetching user data from the datastore."),
        "subject_id" => _("The subject identifier provided by the Identity Provider"),
        "submit" => _("The name of the 'submit' button clicked."),
        "successuri" => _("A URL used by portals for redirection after successful issuance of a certificate."),
        "tempcred" => _("An OAUTH identifier used to track portal sessions."),
        "uidNumber" => _("The user integer identification number provided by the Identity Provider."),
        "user_uid" => _("The unique CILogon user identifier."),
    );

    foreach ($explain as $key => $value) {
        if (preg_match("/^$key$/", $cookie)) {
            $retval = $value;
            break;
        }
    }

    return $retval;
}
