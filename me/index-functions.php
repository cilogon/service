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

    Content::printHeader('Manage CILogon Cookies', false); // Don't set CSRF

    Content::printFormHead();

    printAboutThisPage($browsercount, $sessioncount);
    printBrowserCookies($browsercount);
    printSessionVariables($sessioncount);
    printEnvironmentVars();

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
 */
function printAboutThisPage($browsercount, $sessioncount)
{
    Content::printCollapseBegin('aboutme', 'CILogon Attributes', false);

    echo '
        <div class="card-body px-5">
          <div class="card-text my-2">
            This page allows you to view and (potentially) delete various 
            cookies associated with the <a target="_blank" href="..">CILogon 
            Service</a>. There are three sections below.
          </div> <!-- end card-text -->
          <ol>
            <li><b>Browser Cookies</b> - These are &quot;cookies&quot;
            which are stored in your browser. They are used as preferences 
            for the CILogon Service.
            </li>
            <li><b>Session Variables</b> - These are &quot;short-lived&quot;
            values related to your current CILogon session. Deleting any of 
            these values may require you to re-logon.
            </li>
            <li><b>Environment Variables</b> - These are values set by the
            interaction between your browser and the web server. These are 
            displayed
            mainly for information purposes.
            </li>
          </ol>
    ';

    // If there are brower cookies or session variables which can be
    // deleted, output the appropriate 'Delete ...' button(s).
    if (($browsercount > 0) || ($sessioncount > 0)) {
        echo '
          <div class="card-text my-2">
            You can delete cookies individually by checking the associated
            checkbox(es) and clicking the &quot;Delete Checked&quot; button.
            You can also delete groups of cookies by clicking ';
        if ($browsercount > 0) {
            echo 'the &quot;Delete Browser Cookies&quot; button';
        }
        if ($sessioncount > 0) {
            if ($browsercount > 0) {
                echo ', ';
            }
            echo 'the &quot;Delete Session Variables&quot; button';
            if ($browsercount > 0) {
                echo ', or the &quot;Delete ALL&quot; button';
            }
        }
        echo '.
          </div> <!-- end card-text -->';
    }

    echo '
          <div class="row align-items-center justify-content-center">
            <div class="col-auto">
              <a class="btn btn-primary form-control" href="/">Proceed
              to the CILogon Service</a>
            </div> <!-- end col-auto -->';

    if ($browsercount > 0) {
        echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="Delete Browser Cookies" />
            </div> <!-- end col-auto -->';
    }
    if ($sessioncount > 0) {
        echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="Delete Session Variables" />
            </div> <!-- end col-auto -->';
    }
    if (($browsercount > 0) && ($sessioncount > 0)) {
        echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="Delete ALL" />
            </div> <!-- end col-auto -->';
    }
    echo '
            <div class="col-auto">
              <input type="submit" name="submit"
              class="btn btn-primary submit form-control"
              value="Reload Page" />
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
 */
function printBrowserCookies($browsercount)
{
    global $hide;

    Content::printCollapseBegin('cookies', 'Browser Cookies', false);

    if ($browsercount > 0) {
        echo '
        <div class="card-body">
          <table class="table table-striped table-sm table-hover small">
          <tbody>
        ';

        ksort($_COOKIE);
        foreach ($_COOKIE as $key => $value) {
            if (!in_array($key, $hide)) {
                echo '<tr title="' , getTitleText($key) , '">' ,
                     '<td><input type="checkbox" name="del_browser[]" ',
                     'value="', $key , '"/></td>' ,
                     '<th scope="row" style="word-break: break-all"><samp>' ,
                     Util::htmlent($key) ,
                     '</samp></th><td><samp>';
                // Special handling of portalparams cookie
                if ($key == PortalCookie::COOKIENAME) {
                    $pc = new PortalCookie();
                    echo Util::htmlent($pc);
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
              value="Delete Checked" />
            </div> <!-- end col-auto -->
          </div> <!-- end row align-items-center -->';
    } else {
        echo '
        <div class="card-body px-5">
          <div class="row">
            <div class="col-auto">
              No browser cookies found.
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
 */
function printSessionVariables($sessioncount)
{
    global $hide;

    Content::printCollapseBegin('session', 'Session Variables', false);

    if ($sessioncount > 0) {
        echo '
        <div class="card-body">
          <table class="table table-striped table-sm table-hover small">
          <tbody>
        ';

        ksort($_SESSION);
        foreach ($_SESSION as $key => $value) {
            if (!in_array($key, $hide)) {
                echo '<tr title="' , getTitleText($key) , '">' ,
                     '<td><input type="checkbox" name="del_session[]" ',
                     'value="', $key , '"/></td>' ,
                     '<th scope="row"><samp>' ,
                     Util::htmlent($key) ,
                     '</samp></th><td><samp>' ,
                     Util::htmlent($value) ,
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
              value="Delete Checked" />
            </div> <!-- end col-auto -->
          </div> <!-- end row align-items-center -->';
    } else {
        echo '
        <div class="card-body px-5">
          <div class="row">
            <div class="col-auto">
              No session variables found.
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
 */
function printEnvironmentVars()
{
    Content::printCollapseBegin('environment', 'Environment Variables', false);

    echo '
        <div class="card-body">
          <table class="table table-striped table-hover table-sm small">
          <tbody>
    ';

    ksort($_SERVER);
    foreach ($_SERVER as $key => $value) {
        echo '<tr><th scope="row"><samp>' ,
             Util::htmlent($key) ,
             '</samp></th><td><samp>' ,
             Util::htmlent($value) ,
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
    // appear before shorter strings with the same prefix, e.g.
    // 'p12 error' appears before 'p12'.
    $explain = array(
        "acr" => "Authentication Context Class Ref",
        "affiliation" => "A list of attributes describing your affiliations at your Identity Provider." ,
        "authntime" => "The Unix timestamp of the last successful user authentication." ,
        "callbackuri" => "The URL of the callback servlet used by portals connecting to the CILogon Delegate service." ,
        "cilogon_skin" => "The skin affects the look-and-feel and " .
            "functionality of the CILogon Service. It is typically " .
            "specified by a portal." ,
        "clientparams" => "A set of cookies for each portal you have used with CILogon." ,
        "displayname" => "Your full name set by your Identity Provider." ,
        "dn" => "A quasi distinguished name for the certificate issued by a MyProxy server to the CILogon Service." ,
        "emailaddr" => "Your email address given by your Identity Provider." ,
        "entitlement" => "A list of URIs representing permissions to access a resource or service." ,
        "ePPN" => "'eduPerson Principal Name' - a SAML attribute set by your Identity Provider." ,
        "ePTID" => "'eduPerson Targeted Identifier' - a SAML attribute set by your Identity Provider" ,
        "failureuri" => "A URL used by portals in case the CILogon " .
            "Service is unable to issue a certificate on your behalf. " ,
        "firstname" => "Your given name set by your Identity Provider." ,
        "idpname" => "The display name of your chosen Identity Provider." ,
        "idp" => "The authentication URI of your chosen Identity Provider." ,
        "itrustuin" => "Your university ID number.",
        "keepidp" => "Remember if you checked the 'Remember this " .
            "selection' checkbox when you selected and Identity Provider." ,
        "lastname" => "Your surname set by your Identity Provider." ,
        "loa" => "Level of Assurance set by your Identity Provider." ,
        "logonerror" => "A text message of the reason for the last authentication error." ,
        "memberof" => "Groups of which you are a member",
        "oidcID" => "Your user identifier set by the OpenID Connect Identity Provider." ,
        "openidID" => "Your user identifier set by the OpenID Identity Provider." ,
        "ou" => "Your organizational unit set by your Identity Provider." ,
        "pairwiseID" => "The pairwise subject identifier provided by the Identity Provider",
        "p12error" => "A text message of the reason why the PKCS12 certificate could not be created." ,
        "p12lifetime" => "This multiplied by the p12multipler gives the lifetime of the PKCS12 certificate in hours." ,
        "p12multiplier" => "This multiplied by the p12lifetime gives the lifetime of the PKCS12 certificate in hours." ,
        "p12" => "The expiration time and URL to download a PKCS12 certificate file." ,
        "portalcookie" => "Contains certificate lifetimes for all " .
            "portals you have used with the CILogon Delegate service." ,
        "portalname" => "The display name of the portal connected to the CILogon Delegate service. " ,
        "portalparams" => "For portals previously using the CILogon " .
            "Delegate service, this is the saved lifetime of the " .
            "delegated certificate." ,
        "portalstatus" => "An internal return code when fetching portal parameters from the datastore." ,
        "providerId" => "The previously selected Identity Provider." ,
        "responsesubmit" => "The name of the 'stage' to return to after " .
            "authentication at your chosen Identity Provider." ,
        "responseurl" => "The URL to return to after authentication at your chosen Identity Provider." ,
        "_shibsession" => "A shibboleth session token set by an InCommon Identity Provider." ,
        "stage" => "The current page displayed." ,
        "status" => "An internal return code when fetching user data from the datastore." ,
        "subjectID" => "The subject identifier provided by the Identity Provider",
        "submit" => "The name of the 'submit' button clicked." ,
        "successuri" => "A URL used by portals for redirection after successful issuance of a certificate." ,
        "tempcred" => "An OAUTH identifier used to track portal sessions." ,
        "uid" => "The datastore user identifier." ,
    );

    foreach ($explain as $key => $value) {
        if (preg_match("/^$key$/", $cookie)) {
            $retval = $value;
            break;
        }
    }

    return $retval;
}
