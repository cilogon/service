<?php

require_once('util.php');

/************************************************************************
 * Class name : portalcookie                                            *
 * Description: This class is used by the "CILogon Delegate Service"    *
 * and the CILogon OIDC "authorize" endpoint to keep track of           *
 * user-selected  attributes such as lifetime (in hours) of the         *
 * delegated certificate and if the user clicked the "Always Allow"     *
 * button to remember the allowed delegation upon future accesses.      *
 * The information related to certificate "lifetime" and "remember" the *
 * delegation settings is stored in a single cookie.  Since the data    *
 * is actually a two dimensional array (first element is the name of    *
 * the portal, the second element is an array of the various            *
 * attributes), the stored cookie is actually a base64-encoded          *
 * serialization of the 2D array.  This class provides methods to       *
 * read/write the cookie, and to get/set the values for a given portal. *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('portalcookie.php');                                 *
 *    $pc = new portalcookie();  // Automatically reads the cookie      *
 *    // Assume the callbackuri or redirect_uri for the portal has      *
 *    // been set in the PHP session.                                   *
 *    $lifetime = $pc->get('lifetime');                                 *
 *    if ($lifetime < 1) {                                              *
 *        $lifetime = 1;                                                *
 *    } elseif ($lifetime > 240) {                                      *
 *        $lifetime = 240;                                              *
 *    }                                                                 *
 *    $pc->set('remember',1);                                           *
 *    $pc->write();  // Must be done before any HTML output             *
 ************************************************************************/

class portalcookie {

    /* The token name is const to be accessible from removeTheCookie. */
    const cookiename = "portalparams";

    /* An array of arrays.  First index is portal name. */
    public $portalarray = array();

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new portalcookie object.                           *
     * Default constructor.  This reads the current portal cookie into  *
     * the class $portalarray arary.                                    *
     ********************************************************************/
    function __construct() {
        $this->read();
    }

    /********************************************************************
     * Function  : read                                                 *
     * This method reads the portal cookie, decodes the base64 string,  *
     * and unserializes the 2D array.  This is stored in the class      *
     * $portalarray array.                                              *
     ********************************************************************/
    function read() {
        if (isset($_COOKIE[self::cookiename])) {
            $cookie = $_COOKIE[self::cookiename];
            $b64 = base64_decode($cookie);
            if ($b64 !== false) {
                $unserial = unserialize($b64);
                if ($unserial !== false) {
                    $this->portalarray = $unserial;
                }
            }
        }
    }

    /********************************************************************
     * Function  : write                                                *
     * This method writes the class $portalarray to a cookie.  In       *
     * order to store the 2D array as a cookie, the array is first      *
     * serialized and then base64 encoded.                              *
     ********************************************************************/
    function write() {
        if (!empty($this->portalarray)) {
            util::setCookieVar(self::cookiename,
                base64_encode(serialize($this->portalarray)));
        }
    }

    /********************************************************************
     * Function  : getPortalName                                        *
     * Returns   : The name of the portal, which is either the          *
     *             OAuth 1.0a 'callbackuri' or the OIDC 'redirect_uri'. *
     * This method looks in the PHP session for one of 'callbackuri'    *
     * (in the OAuth 1.0a "delegate" case) or                           *
     * $clientparams['redirect_uri'] (in the OIDC "authorize" case).    *
     * This is used as the key for the $portalarray. If neither of      *
     * these session variables is set, return empty string.             *
     ********************************************************************/
    function getPortalName() {
        // Check the OAuth 1.0a 'delegate' 'callbackuri'
        $retval = util::getSessionVar('callbackuri');
        if (strlen($retval) == 0) {
            // Next, check the OAuth 2.0 'authorize' 'redirect_uri'
            $clientparams = json_decode(
                util::getSessionVar('clientparams'),true);
            if (isset($clientparams['redirect_uri'])) {
                $retval = $clientparams['redirect_uri'];
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : removeTheCookie                                      *
     * This method unsets the portal cookie in the user's browser.      *
     * This should be called before any HTML is output.                 *
     ********************************************************************/
    public static function removeTheCookie() {
        util::unsetCookieVar(self::cookiename);
    }

    /********************************************************************
     * Function  : get                                                  *
     * Parameter : The attribute of the portal to get.  Should be       *
     *             something like 'lifetime' or 'remember'.             *
     * Returns   : The value of the $param for the portal.              *
     * This method is a generalized getter to fetch the value of a      *
     * parameter for a given portal.  In other words, this method       *
     * returns $this->portalarray[$param], where $param is something    *
     * like 'lifetime' or 'remember'. If the portal name is not set,    *
     * or the requested parameter is missing from the cookie, return    *
     * empty string.                                                    *
     ********************************************************************/
    function get($param) {
        $retval = '';
        $name = $this->getPortalName();
        if ((strlen($name) > 0) &&
            (isset($this->portalarray[$name])) &&
            (isset($this->portalarray[$name][$param]))) {
            $retval = $this->portalarray[$name][$param];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : set                                                  *
     * Parameter : (1) The parameter of the portal to set.  Should be   *
     *                 something like 'lifetime' or 'remember'.         *
     *             (2) The value to set for the parameter.              *
     * This method sets a portal's parameter to a given value.  Note    *
     * that $value should be an integer or character value.             *
     ********************************************************************/
    function set($param,$value) {
        $name = $this->getPortalName();
        if (strlen($name) > 0) {
            $this->portalarray[$name][$param] = $value;
        }
    }

    /********************************************************************
     * Function  : toString                                             *
     * Return    : A 'pretty print' representation of the class         *
     *             portalarray.                                         *
     * This function returns a string representation of the object.     *
     * The format is "portal=...,lifetime=...,remember=...". Multiple   *
     * portals are separated by a newline character.                    *
     ********************************************************************/
    function toString() {
        $retval = '';
        $first = true; 
        foreach ($this->portalarray as $key => $value) {
            if (!$first) {
                $retval .= "\n";
            }
            $first = false;
            $retval .= 'portal=' . $key; 
            ksort($value);
            foreach ($value as $key2 => $value2) {
                $retval .= ",$key2=$value2";
            }
        }
        return $retval;
    }

}

?>
