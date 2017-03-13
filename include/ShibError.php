<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

/**
 * ShibError
 * This class handles errors passed by the Shibboleth SP
 * software when /etc/shibboleth/shibboleth2.xml is
 * configured as follows:
 *     <Errors redirectErrors="https://cilogon.org"/>
 * Note that with v.2.4.x of the Shibboleth SP software,
 * the 'redirectErrors' attribute must be an absolute URL,
 * meaning that the shibboleth2.xml file will be different
 * for https://test.cilogon.org/.  V.2.5 of the SP
 * software should allow relative URLS.
 *
 * To handle errors from the Shibboleth SP software,
 * create a new ShibError instance at the root index.php
 * file. The constructor looks for various 'GET'
 * parameters that get passed by the SP software. If there
 * is an error, it checks to see if the error was caused
 * by requesting silver assurance. If so, try the request
 * again WITHOUT requesting silver assurance. If not, put
 * put an error page with the info provided by the SP
 * software.
 *
 * Example usage:
 *     require_once 'ShibError.php';
 *     // The class constructor does ALL of the work
 *     $shiberror = new ShibError();
 */
class ShibError
{

    /**
     * @var array $errorarray Holds the values of the various shibboleth
     *      error parameters
     */
    private $errorarray;

    /**
     * @var array $errorparams Shibboleth error parameters passed to the
     * redirectErrors handler:
     * https://wiki.shibboleth.net/confluence/display/SHIB2/NativeSPErrors
     */
    private static $errorparams = array(
        'requestURL',    // The URL associated with the request
        'errorType',     // The general type of error
        'errorText',     // The actual error message
        'entityID',      // Name of identity provider, if known
        'now',           // Current date and time
        'statusCode',    // SAML status code causing error, sent by IdP
        'statusCode2',   // SAML sub-status code causing error, sent by IdP
        'statusMessage', // SAML status message, sent by IdP
        'contactName',   // A support contact name for the IdP
                         //     provided by that site's metadata.
        'contactEmail',  // A contact email address for the IdP contact
                         //     provided by that site's metadata.
        'errorURL',      // The URL of an error handling page for the IdP
                         //     provided by that site's metadata.
    );

    /**
     * __construct
     *
     * Default constructor. This method attempts to read in the
     * various Shibboleth SP error parameters that would have been
     * passed as parameters to the 'redirectErrors' handler URL, i.e.
     * in the $_GET global variable.
     *
     * @return ShibError A new ShibError object.
     */
    public function __construct()
    {
        $this->errorarray = array();
        foreach (static::$errorparams as $param) {
            if (isset($_GET[$param])) {
                $this->errorarray[$param] = rtrim($_GET[$param]);
            }
        }

        if ($this->isError()) {
            // Check if we tried to get silver before. If so, don't print
            // an error. Instead, try again without asking for silver.
            if (Util::getSessionVar('requestsilver') == '1') {
                $responseurl = null;
                if (strlen(Util::getSessionVar('responseurl')) > 0) {
                    $responseurl = Util::getSessionVar('responseurl');
                }
                $providerId = Util::getPortalOrNormalCookieVar('providerId');
                Content::redirectToGetShibUser(
                    $providerId,
                    'gotuser',
                    $responseurl,
                    false
                );
            } else {
                $this->printError();
            }
            Util::unsetSessionVar('requestsilver');
            exit; // No further processing!!!
        }
    }

    /**
     * isError
     *
     * This method returns true if several Shibboleth error parameters
     * were passed in via the $_GET array. These parameters are sent
     * by the Shibboleth SP software when there is an error.
     *
     * @return bool True if several Shibboleth error parameters were
     *         passed to the handler URL. False otherwise.
     */
    public function isError()
    {
        return ((isset($this->errorarray['requestURL'])) &&
                (isset($this->errorarray['errorType'])) &&
                (isset($this->errorarray['errorText'])) &&
                (isset($this->errorarray['now'])) &&
                (strlen($this->errorarray['requestURL']) > 0) &&
                (strlen($this->errorarray['errorType']) > 0) &&
                (strlen($this->errorarray['errorText']) > 0) &&
                (strlen($this->errorarray['now']) > 0));
    }

    /**
     * printError
     *
     * This method prints out text for an error message page. It also
     * logs the error and sends an alert email with the shibboleth
     * error parameters. You should probably 'exit()' after you call
     * this so more HTML doesn't get output.
     */
    public function printError()
    {
        $errorstr1 = '';  // For logging - one line
        $errorstr2 = '';  // For HTML and email - multi-line
        foreach ($this->errorarray as $key => $value) {
            $errorstr1 .= Util::htmlent($key.'="' . $value . '" ');
            $errorstr2 .= Util::htmlent(sprintf("%-14s= %s\n", $key, $value));
        }

        $log = new Loggit();
        $log->error('Shibboleth error: ' . $errorstr1);

        Content::printHeader('Shiboleth Error');

        echo '
        <div class="boxed">
        ';

        $erroroutput = '
        <p>
        The CILogon Service has encountered a Shibboleth error.
        </p>
        <blockquote><pre>' . $errorstr2 . '</pre>
        </blockquote>
        <p>
        System administrators have been
        notified. This may be a temporary error. Please try again later, or
        contact us at the email address at the bottom of the page.
        </p>
        ';

        $forceauthn = Content::$skin->getConfigOption('forceauthn');
        if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
            $erroroutput .= '
            <p>
            Note that this error may be due to your selected Identity
            Provider (IdP) not fully supporting &quot;forced
            reauthentication&quot;. This setting forces users to log in at
            the IdP every time, thus bypassing Single Sign-On (SSO).
            </p>
            ';
        }

        Content::printErrorBox($erroroutput);

        echo '
        <div>
        ';
        Content::printFormHead();
        echo '
        <input type="submit" name="submit" class="submit" value="Proceed" />
        </form>
        </div>
        ';


        echo '
        </div>
        ';
        Content::printFooter();

        Util::sendErrorAlert('Shibboleth Error', $errorstr2);
    }
}
