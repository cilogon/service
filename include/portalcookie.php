<?php

require_once('util.php');

/************************************************************************
 * Class name : portalcookie                                            *
 * Description: This class is used by the "CILogon Delegate Service"    *
 * to keep track of the user-selected lifetime (in hours) of the        *
 * delegated certificate and if the user clicked the "Always Allow"     *
 * button to remember the allowed delegation upon future accesses.      *
 * The information related to certificate "lifetime" and "remember" the *
 * delegation settings is stored in a single cookie.  Since the data    *
 * is actually a two dimensional array (first element is the name of    *
 * the portal to delegate the certificate to, the second element is     *
 * the "lifetime" and "remember" settings), the stored cookie is        *
 * actually a base64 encoded serialization of the 2D array.  This class *
 * provides methods to read/write the cookie, and to get/set the values *
 * of "lifetime" and "remember" for a given portal.                     *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('portalcookie.php');                                 *
 *    $portal = new portalcookie();  // Automatically reads the cookie  *
 *    $lifetime = $portal->getPortalLifetime('http://portal.org/');     *
 *    if ($lifetime < 1) {                                              *
 *        $lifetime = 1;                                                *
 *    } elseif ($lifetime > 240) {                                      *
 *        $lifetime = 240;                                              *
 *    }                                                                 *
 *    $portal->setPortalRemember('http://portal.org/',1);               *
 *    $portal->write();  // Must be done before any HTML output         *
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
     * Function  : removeTheCookie                                      *
     * This method unsets the portal cookie in the user's browser.      *
     * This should be called before any HTML is output.                 *
     ********************************************************************/
    public static function removeTheCookie() {
        util::unsetCookieVar(self::cookiename);
    }

    /********************************************************************
     * Function  : getPortalParam                                       *
     * Parameters: (1) The name of the portal in question.              *
     *             (2) The parameter of the portal to get.  Should be   *
     *                 either 'lifetime' or 'remember'.                 *
     * Returns   : The value of the $param for the portal $name.        *
     * This method is a generalized getter to fetch the value of a      *
     * parameter for a given portal.  In otherwords, this method        *
     * $this->portalarray[$name][$param], where $param is one of        *
     * 'lifetime' or 'remember'.                                        *
     ********************************************************************/
    function getPortalParam($name,$param) {
        $retval = '';
        if ((isset($this->portalarray[$name])) &&
            (isset($this->portalarray[$name][$param]))) {
            $retval = $this->portalarray[$name][$param];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : setPortalParam                                       *
     * Parameters: (1) The name of the portal in question.              *
     *             (2) The parameter of the portal to set.  Should be   *
     *                 either 'lifetime' or 'remember'.                 *
     *             (3) The value to set for the parameter.              *
     * This method sets a portal's parameter to a given value.  Note    *
     * that $value should be an integer or character value.             *
     ********************************************************************/
    function setPortalParam($name,$param,$value) {
        $this->portalarray[$name][$param] = $value;
    }

    /********************************************************************
     * Function  : getPortalLifetime                                    *
     * Parameter : The name of the portal in question.                  *
     * Return    : The lifetime of the delegated certificate for the    *
     *             given portal.                                        *
     * This is a convenience function for                               *
     * getPortalParameter($name,'lifetime').                            *
     ********************************************************************/
    function getPortalLifetime($name) {
        return $this->getPortalParam($name,'lifetime');
    }
    
    /********************************************************************
     * Function  : setPortalLifetime                                    *
     * Parameters: (1) The name of the portal in question.              *
     *             (2) The 'lifetime' of the certificate delegated to   *
     *                 the portal.                                      *
     * This is a convenience function for                               *
     * setPortalParameter($name,'lifetime',$life).                      *
     ********************************************************************/
    function setPortalLifetime($name,$life) {
        $this->setPortalParam($name,'lifetime',$life);
    }

    /********************************************************************
     * Function  : getPortalRemember                                    *
     * Parameter : The name of the portal in question.                  *
     * Return    : The 'remember' value for the given portal.           *
     * This is a convenience function for                               *
     * getPortalParameter($name,'remember').                            *
     ********************************************************************/
    function getPortalRemember($name) {
        return $this->getPortalParam($name,'remember');
    }
    
    /********************************************************************
     * Function  : setPortalRemember                                    *
     * Parameters: (1) The name of the portal in question.              *
     *             (2) The 'remember' value of the portal, should be    *
     *                 an integer value (0 or 1).                       *
     * This is a convenience function for                               *
     * setPortalParameter($name,'remember',$remem).                     *
     ********************************************************************/
    function setPortalRemember($name,$remem) {
        $this->setPortalParam($name,'remember',$remem);
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
            $retval .= 'portal=' . $key . 
                       ',lifetime=' . $this->getPortalLifetime($key) .
                       ',remember='.  $this->getPortalRemember($key);
        }
        return $retval;
    }

}

?>
