<?php

require_once('loggit.php');

/************************************************************************
 * Class name : csrf                                                    *
 * Description: This class creates and manages CSRF (cross-site request *
 *              forgery) values.  Upon creation of a new csrf object,   *
 *              a random value is created that can be (a) stored in a   *
 *              cookie and (b) written to a hidden form element or      *
 *              saved to a PHP session.  There are functions to see     *
 *              if a previously set csrf cookie value matches a         *
 *              form-submitted csrf element or PHP session value.       *
 *              Note that cookies must be set before any HTML is        *
 *              printed out.                                            *
 *                                                                      *
 * Example usage:                                                       *
 *    // Set cookie and output hidden element in HTML <form> block      *
 *    require_once('csrf.php');                                         *
 *    $csrf = new csrf();                                               *
 *    $csrf->setTheCookie();                                            *
 *    // Output an HTML <form> block                                    *
 *    echo $csrf->hiddenFormElement();                                  *
 *    // Close </form> block                                            *
 *                                                                      *
 *    // When user submits the form, first check for csrf equality      *
 *    if ($csrf->isCookieEqualToForm()) {                               *
 *        // Form submission is okay - process it                       *
 *    } else {                                                          *
 *        $csrf->removeTheCookie();                                     *
 *    }                                                                 *
 *                                                                      *
 *    // Alternatively, set cookie and PHP session value and compare    *
 *    require_once('csrf.php');                                         *
 *    session_start();                                                  *
 *    $csrf = new csrf();                                               *
 *    $csrf->setCookieAndSession();                                     *
 *                                                                      *
 *    // When the user (re)loads the page, check for csrf equality      *
 *    session_start();                                                  *
 *    if ($csrf->isCookieEqualToSession()) {                            *
 *        // Session csrf value was okay - process as normal            *
 *    } else {                                                          *
 *        $csrf->removeTheCookie();                                     *
 *        $csrf->removeTheSession();                                    *
 *    }                                                                 *
 ************************************************************************/

class csrf {

    /* The default token name can be overridden in the constructor. */
    const defaultTokenname = "CSRF";

    /* The "name" of the CSRF token, as saved in session and cookie. */
    private $tokenname;
    /* The "value" of the CSRF token, a random sequence of characters. */
    private $tokenvalue;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameter : The "name" of the csrf token. Defaults to "CSRF".    *
     * Returns   : A new csrf object.                                   *
     * Default constructor.  This sets the value of the csrf token to   *
     * a random string of characters.                                   *
     ********************************************************************/
    function __construct($tokenname=self::defaultTokenname) {
        $this->setTokenName($tokenname);
        $this->tokenvalue = md5(uniqid(rand(),true));
    }

    /********************************************************************
     * Function  : getTokenName                                         *
     * Returns   : The string name of the csrf token.                   *
     * Returns the name of the csrf token stored in the private         *
     * variable $tokenname.                                             *
     ********************************************************************/
    function getTokenName() {
        return $this->tokenname;
    }

    /********************************************************************
     * Function  : setTokenName                                         *
     * Parameter  : The string name of the csrf token.                  *
     * Sets the private variable $tokenname to the name of the csrf     *
     * token. Use this method within other methods of the csrf class.   *
     ********************************************************************/
    function setTokenName($tokenname) {
        $this->tokenname = $tokenname;
    }

    /********************************************************************
     * Function  : getTokenValue                                        *
     * Returns   : The string value of the random csrf token.           *
     * Returns the value of the csrf token stored in the private        *
     * variable $tokenvalue.                                            *
     ********************************************************************/
    function getTokenValue() {
        return $this->tokenvalue;
    }

    /********************************************************************
     * Function  : hiddenFormElement                                    *
     * Returns   : The string of an <input> HTML element.               *
     * Returns an <input ...> form element of type 'hidden' with the    *
     * name and value set to the csrf tokenname and tokenvalue.         *
     ********************************************************************/
    function hiddenFormElement() {
        return '<input type="hidden" name="' . $this->getTokenName() .
               '" value="' . $this->getTokenValue() .  '" />';
    }

    /********************************************************************
     * Function  : setTheCookie                                         *
     * Sets a session cookie with the csrf tokenname and tokenvalue.    *
     * You must call this method before you output any HTML.            *
     ********************************************************************/
    function setTheCookie() {
        util::setCookieVar($this->getTokenName(),$this->getTokenValue(),0);
    }

    /********************************************************************
     * Function  : getTheCookie                                         *
     * Returns   : The current value of the CSRF cookie (which was      *
     *             actually set on a previous page load), or empty      *
     *             string if it has not been set.                       *
     * Returns the value of the CSRF cookie if it has been set, or      *
     * returns an empty string otherwise. Note that the token in the    *
     * cookie is actually the PREVIOUSLY SET token value, which is      *
     * different from the object instance's $tokenvalue. This is due    *
     * to the way cookies are processed on the NEXT page load.          *
     ********************************************************************/
    function getTheCookie() {
        return util::getCookieVar($this->getTokenName());
    }

    /********************************************************************
     * Function  : removeTheCookie                                      *
     * removes the csrf cookie.  You must call this method before you   *
     * output any HTML.  Strictly speaking, the cookie is not removed,  *
     * rather it is set to an empty value with an expired time.         *
     ********************************************************************/
    function removeTheCookie() {
        util::unsetCookieVar($this->getTokenName());
    }

    /********************************************************************
     * Function  : setTheSession                                        *
     * Sets a value in the PHP session csrf tokenname and tokenvalue.   *
     * You must have a valid PHP session (e.g. by calling               *
     * session_start()) before you call this method.                    *
     ********************************************************************/
    function setTheSession() {
        util::setSessionVar($this->getTokenName(),$this->getTokenValue());
    }

    /********************************************************************
     * Function  : getTheSession                                        *
     * Returns   : The current value of the CSRF in the PHP session,    *
     *             or empty string if it has not been set.              *
     * Returns the value of the CSRF token as set in the PHP session,   *
     * or returns an empty string otherwise.                            *
     ********************************************************************/
    function getTheSession() {
        return util::getSessionVar($this->getTokenName());
    }

    /********************************************************************
     * Function  : removeTheSession                                     *
     * Removes the csrf value from the PHP session.                     *
     ********************************************************************/
    function removeTheSession() {
        util::unsetSessionVar($this->getTokenName());
    }

    /********************************************************************
     * Function  : setCookieAndSession                                  *
     * This is a convenience function which sets both the cookie and    *
     * the session tokens. You must have a valid PHP session (e.g. by   *
     * calling session_start()) before you call this method.            *
     ********************************************************************/
    function setCookieAndSession() {
        $this->setTheCookie();
        $this->setTheSession();
    }

    /********************************************************************
     * Function  : isCookieEqualToForm                                  *
     * Return    : True if the csrf cookie value matches the submitted  *
     *             csrf form value, false otherwise.                    *
     * This is a convenience method which compares the value of a       *
     * previously set csrf cookie to the value of a submitted csrf      *
     * form element.  If the two values are equal (and non-empty), then *
     * this returns true and you can continue processing.  Otherwise,   *
     * false is returned and you should assume that the form was not    *
     * submitted properly.                                              *
     ********************************************************************/
    function isCookieEqualToForm() {
        $retval = false;  // Assume csrf values don't match

        $csrfcookievalue = $this->getTheCookie();
        $csrfformvalue = util::getPostVar($this->getTokenName());
        if ((strlen($csrfcookievalue) > 0) &&
            (strlen($csrfformvalue) > 0) &&
            (strcmp($csrfcookievalue,$csrfformvalue) == 0)) {
            $retval = true;
        }

        return $retval;
    }

    /********************************************************************
     * Function  : isCookieEqualToSession                               *
     * Return    : True if the csrf cookie value matches the csrf value *
     *             in the PHP session, false otherwise.                 *
     * This is a convenience method which compares the value of a       *
     * previously set csrf cookie to the csrf value in the current      *
     * PHP session.  If the two values are equal (and non-empty), then  *
     * this returns true and you can continue processing.  Otherwise,   *
     * false is returned and you should assume that the session was not *
     * initialized properly.                                            *
     ********************************************************************/
    function isCookieEqualToSession() {
        $retval = false;  // Assume csrf values don't match

        $csrfcookievalue = $this->getTheCookie();
        $csrfsesionvalue = $this->getTheSession();
        if ((strlen($csrfcookievalue) > 0) &&
            (strlen($csrfsesionvalue) > 0) &&
            (strcmp($csrfcookievalue,$csrfsesionvalue) == 0)) {
            $retval = true;
        }

        return $retval;
    }

    /********************************************************************
     * Function  : verifyCookieAndGetSubmit                             *
     * Parameter : The name of a <form>'s "submit" button OR the key    *
     *             of a PHP session variable, defaults to "submit".     *
     * Return    : The value of the <form>'s clicked "submit" button if *
     *             the csrf cookie matches the hidden form element, or  *
     *             the value of the PHP session variable $submit if the *
     *             csrf cookie matches the hidden PHP session csrf      *
     *             variable, or empty string otherwise.                 *
     * This function assumes that one of the two following actions has  *
     * occurred:                                                        *
     *   (1) The user has clicked a <form> submit button.               *
     *   (2) A PHP session variable has been set.                       *
     * The function first checks the <form>'s hidden "csrf" element     *
     * to see if it matches the csrf cookie.  If so, it checks the      *
     * the value of the submit button with the passed-in "name"         *
     * attribute (defaults to "submit").  If the value is non-empty,    *
     * then that value is returned.  However, if the <form> hidden csrf *
     * element doesn't match the csrf cookie or the submit element is   *
     * empty, the function then checks the PHP session csrf value to    *
     * see if that matches the csrf cookie.  If so, it looks for a      *
     * variable with the passed-in parameter name and returns that      *
     * value.  In other words, a non-empty <form> submit button has     *
     * priority over a PHP session value.  In any case, if the csrf     *
     * test fails for both <form> and PHP session, the csrf cookie is   *
     * removed, and the empty string is returned.                       *
     ********************************************************************/
    function verifyCookieAndGetSubmit($submit='submit')
    {
        $retval = '';
        // First, check <form> hidden csrf element
        if ($this->isCookieEqualToForm()) {
            $retval = util::getPostVar($submit);
            // Hack for Duo Security - look for all uppercase e.g., 'SUBMIT'
            if (strlen($retval) == 0) {
                $retval = util::getPostVar(strtoupper($submit));
            }
        }
        // If <form> element missing or bad, check PHP session csrf variable
        if ((strlen($retval) == 0) && ($this->isCookieEqualToSession())) {
            $retval = util::getSessionVar($submit);
            $this->removeTheSession();  // No need to use it again
        }
        // If csrf failed or no "submit" element in <form> or session, 
        // remove the csrf cookie.
        if (strlen($retval) == 0) {
            $this->removeTheCookie();
            $log = new loggit();
            $log->info('CSRF check failed.');
        }
        return $retval;
    }

}

?>
