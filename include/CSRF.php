<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\Loggit;

/**
 * CSRF
 *
 * This class creates and manages CSRF (cross-site request forgery)
 * values.  Upon creation of a new csrf object, a random value is
 * created that can be (a) stored in a cookie and (b) written to a
 * hidden form element or saved to a PHP session.  There are functions
 * to see if a previously set csrf cookie value matches a form-submitted
 * csrf element or PHP session value. Note that cookies must be set
 * before any HTML is printed out.
 *
 * Example usage:
 *    // Set cookie and output hidden element in HTML <form> block
 *    require_once 'CSRF.php';
 *    $csrf = new csrf();
 *    $csrf->setTheCookie();
 *    // Output an HTML <form> block
 *    echo $csrf->hiddenFormElement();
 *    // Close </form> block
 *
 *    // When user submits the form, first check for csrf equality
 *    if ($csrf->isCookieEqualToForm()) {
 *        // Form submission is okay - process it
 *    } else {
 *        $csrf->removeTheCookie();
 *    }
 *
 *    // Alternatively, set cookie and PHP session value and compare
 *    require_once 'CSRF.php';
 *    session_start();
 *    $csrf = new csrf();
 *    $csrf->setCookieAndSession();
 *
 *    // When the user (re)loads the page, check for csrf equality
 *    session_start();
 *    if ($csrf->isCookieEqualToSession()) {
 *        // Session csrf value was okay - process as normal
 *    } else {
 *        $csrf->removeTheCookie();
 *        $csrf->removeTheSession();
 *    }
 */
class CSRF
{
    /**
     * @var string DEFAULTTOKENNAME The default token name can be
     *      overridden in the constructor.
     */
    const DEFAULTTOKENNAME = "CSRF";

    /**
     * @var string $tokenname The 'name' of the CSRF token, as saved
     *      in session and cookie.
     */
    private $tokenname;

    /**
     * @var string $tokenvalie The 'value' of the CSRF token, a random
     *      sequence of characters.
     */
    private $tokenvalue;

    /**
     * __construct
     *
     * Default constructor. This sets the value of the csrf token to
     * a random string of characters.
     *
     * @param string $tokenname (Optional) The 'name' of the csrf
     *        token. Defaults to 'CSRF'.
     * @return CSRF A new csrf object
     */
    public function __construct($tokenname = self::DEFAULTTOKENNAME)
    {
        $this->setTokenName($tokenname);
        $this->tokenvalue = md5(uniqid(rand(), true));
    }

    /**
     * getTokenName
     *
     * Returns the name of the csrf token stored in the private
     * variable $tokenname.
     *
     * @return string The string name of the csrf token.
     */
    public function getTokenName()
    {
        return $this->tokenname;
    }

    /**
     * setTokenName
     *
     * Sets the private variable $tokenname to the name of the csrf
     * token. Use this method within other methods of the csrf class.
     *
     * @param string $tokenname The string name of the csrf token.
     */
    public function setTokenName($tokenname)
    {
        $this->tokenname = $tokenname;
    }

    /**
     * getTokenValue
     *
     * Returns the value of the csrf token stored in the private
     * variable $tokenvalue.
     *
     * @return string The value of the random csrf token.
     */
    public function getTokenValue()
    {
        return $this->tokenvalue;
    }

    /**
     * hiddenFormElement
     *
     * Returns an <input ...> form element of type 'hidden' with the
     * name and value set to the csrf tokenname and tokenvalue.
     *
     * @return string The string of an <input> HTML element.
     */
    public function hiddenFormElement()
    {
        return '<input type="hidden" name="' . $this->getTokenName() .
               '" value="' . $this->getTokenValue() .  '" />';
    }

    /**
     * setTheCookie
     *
     * Sets a session cookie with the csrf tokenname and tokenvalue.
     * You must call this method before you output any HTML.
     */
    public function setTheCookie()
    {
        Util::setCookieVar($this->getTokenName(), $this->getTokenValue(), 0);
    }

    /**
     * getTheCookie
     *
     * Returns the value of the CSRF cookie if it has been set, or
     * returns an empty string otherwise. Note that the token in the
     * cookie is actually the PREVIOUSLY SET token value, which is
     * different from the object instance's $tokenvalue. This is due
     * to the way cookies are processed on the NEXT page load.
     *
     * @return string The current value of the CSRF cookie (which was
     *         actually set on a previous page load), or empty string
     *         if it has not been set.
     */
    public function getTheCookie()
    {
        return Util::getCookieVar($this->getTokenName());
    }

    /**
     * removeTheCookie
     *
     * Removes the csrf cookie.  You must call this method before you
     * output any HTML.  Strictly speaking, the cookie is not removed,
     * rather it is set to an empty value with an expired time.
     */
    public function removeTheCookie()
    {
        Util::unsetCookieVar($this->getTokenName());
    }

    /**
     * setTheSession
     *
     * Sets a value in the PHP session csrf tokenname and tokenvalue.
     * You must have a valid PHP session (e.g. by calling
     * session_start()) before you call this method.
     */
    public function setTheSession()
    {
        Util::setSessionVar($this->getTokenName(), $this->getTokenValue());
    }

    /**
     * getTheSession
     *
     * Returns the value of the CSRF token as set in the PHP session,
     * or returns an empty string otherwise.
     *
     * @return string The current value of the CSRF in the PHP session,
     *         or empty string if it has not been set.
     */
    public function getTheSession()
    {
        return Util::getSessionVar($this->getTokenName());
    }

    /**
     * removeTheSession
     *
     * Removes the csrf value from the PHP session.
     */
    public function removeTheSession()
    {
        Util::unsetSessionVar($this->getTokenName());
    }

    /**
     * setCookieAndSession
     *
     * This is a convenience function which sets both the cookie and
     * the session tokens. You must have a valid PHP session (e.g. by
     * calling session_start()) before you call this method.
     */
    public function setCookieAndSession()
    {
        $this->setTheCookie();
        $this->setTheSession();
    }

    /**
     * isCookieEqualToForm
     *
     * This is a convenience method which compares the value of a
     * previously set csrf cookie to the value of a submitted csrf
     * form element.  If the two values are equal (and non-empty), then
     * this returns true and you can continue processing.  Otherwise,
     * false is returned and you should assume that the form was not
     * submitted properly.
     *
     * @return bool True if the csrf cookie value matches the submitted
     *         csrf form value, false otherwise.
     */
    public function isCookieEqualToForm()
    {
        $retval = false;  // Assume csrf values don't match

        $csrfcookievalue = $this->getTheCookie();
        $csrfformvalue = Util::getPostVar($this->getTokenName());
        if ((strlen($csrfcookievalue) > 0) &&
            (strlen($csrfformvalue) > 0) &&
            (strcmp($csrfcookievalue, $csrfformvalue) == 0)) {
            $retval = true;
        }

        return $retval;
    }

    /**
     * isCookieEqualToSession
     *
     * This is a convenience method which compares the value of a
     * previously set csrf cookie to the csrf value in the current
     * PHP session.  If the two values are equal (and non-empty), then
     * this returns true and you can continue processing.  Otherwise,
     * false is returned and you should assume that the session was not
     * initialized properly.
     *
     * @return bool True if the csrf cookie value matches the csrf value
     *         in the PHP session, false otherwise.
     */
    public function isCookieEqualToSession()
    {
        $retval = false;  // Assume csrf values don't match

        $csrfcookievalue = $this->getTheCookie();
        $csrfsesionvalue = $this->getTheSession();
        if ((strlen($csrfcookievalue) > 0) &&
            (strlen($csrfsesionvalue) > 0) &&
            (strcmp($csrfcookievalue, $csrfsesionvalue) == 0)) {
            $retval = true;
        }

        return $retval;
    }

    /**
     * verifyCookieAndGetSubmit
     *
     * This function assumes that one of the two following actions has
     * occurred:
     *   (1) The user has clicked a <form> submit button.
     *   (2) A PHP session variable has been set.
     * The function first checks the <form>'s hidden 'csrf' element
     * to see if it matches the csrf cookie.  If so, it checks the
     * the value of the submit button with the passed-in 'name'
     * attribute (defaults to 'submit').  If the value is non-empty,
     * then that value is returned.  However, if the <form> hidden csrf
     * element doesn't match the csrf cookie or the submit element is
     * empty, the function then checks the PHP session csrf value to
     * see if that matches the csrf cookie.  If so, it looks for a
     * variable with the passed-in parameter name and returns that
     * value.  In other words, a non-empty <form> submit button has
     * priority over a PHP session value.  In any case, if the csrf
     * test fails for both <form> and PHP session, the csrf cookie is
     * removed, and the empty string is returned.
     *
     * @param string $submit (Optional) The name of a <form>'s 'submit'
     *        button OR the key of a PHP session variable, defaults to
     *        'submit'.
     * @return string The value of the <form>'s clicked 'submit' button if
     *         the csrf cookie matches the hidden form element, or
     *         the value of the PHP session variable $submit if the
     *         csrf cookie matches the hidden PHP session csrf
     *         variable, or empty string otherwise.
     */
    public function verifyCookieAndGetSubmit($submit = 'submit')
    {
        $retval = '';
        // First, check <form> hidden csrf element
        if ($this->isCookieEqualToForm()) {
            $retval = Util::getPostVar($submit);
            // Hack for Duo Security - look for all uppercase e.g., 'SUBMIT'
            if (strlen($retval) == 0) {
                $retval = Util::getPostVar(strtoupper($submit));
            }
        }
        // If <form> element missing or bad, check PHP session csrf variable
        if ((strlen($retval) == 0) && ($this->isCookieEqualToSession())) {
            $retval = Util::getSessionVar($submit);
            $this->removeTheSession();  // No need to use it again
        }
        // If csrf failed or no 'submit' element in <form> or session,
        // remove the csrf cookie.
        if (strlen($retval) == 0) {
            $this->removeTheCookie();
            $log = new Loggit();
            $log->info('CSRF check failed.');
        }
        return $retval;
    }
}
