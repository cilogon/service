<?

/************************************************************************
 * Class name : csrf                                                    *
 * Description: This class creates and manages CSRF (cross-site request *
 *              forgery) values.  Upon creation of a new csrf object,   *
 *              a random value is created that can be (a) stored in a   *
 *              cookie and (b) written to a hidden form element or      *
 *              saved to a PHP session.  There are static functions to  *
 *              see if a previously set csrf cookie value matches a     *
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
 *    echo $csrf->getHiddenFormElement();                               *
 *    // Close </form> block                                            *
 *                                                                      *
 *    // When user submits the form, first check for csrf equality      *
 *    if (csrf::isCookieEqualToForm()) {                                *
 *        // Form submission is okay - process it                       *
 *    } else {                                                          *
 *        csrf::deleteTheCookie();                                      *
 *    }                                                                 *
 *                                                                      *
 *    // Alternatively, set cookie and PHP session value and compare    *
 *    require_once('csrf.php');                                         *
 *    session_start();                                                  *
 *    $csrf = new csrf();                                               *
 *    $csrf->setTheCookie();                                            *
 *    $csrf->setTheSession();                                           *
 *                                                                      *
 *    // When the user (re)loads the page, check for csrf equality      *
 *    session_start();                                                  *
 *    if (csrf::isCookieEqualToSession()) {                             *
 *        // Session csrf value was okay - process as normal            *
 *    } else {                                                          *
 *        csrf::deleteTheCookie();                                      *
 *        csrf::deleteTheSession();                                     *
 *    }                                                                 *
 ************************************************************************/

class csrf {

    /* The token name is static to be accessible from isCookieEqualTo Form. */
    public static $tokenname = "CSRF";

    /* A random sequence of characters. */
    protected $tokenvalue;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new csrf object.                                   *
     * Default constructor.  This sets the value of the csrf token to   *
     * a random string of characters.                                   *
     ********************************************************************/
    function __construct() {
        $this->tokenvalue = md5(uniqid(rand(),true));
    }

    /********************************************************************
     * Function  : getTokenName                                         *
     * Returns   : The string name of the csrf token.                   *
     * Returns the name of the csrf token.  Use this method within      *
     * other methods of the csrf class.                                 *
     ********************************************************************/
    function getTokenName() {
        return self::$tokenname;
    }

    /********************************************************************
     * Function  : getTokenValue                                        *
     * Returns   : The string value of the random csrf token.           *
     * Returns the value of the csrf token.  Use this method within     *
     * other methods of the csrf class.                                 *
     ********************************************************************/
    function getTokenValue() {
        return $this->tokenvalue;
    }

    /********************************************************************
     * Function  : getHiddenFormElement                                 *
     * Returns   : The string of an <input> HTML element.               *
     * Returns an <input ...> form element of type 'hidden' with the    *
     * name and value set to the csrf tokenname and tokenvalue.         *
     ********************************************************************/
    function getHiddenFormElement() {
        return '<input type="hidden" name="' . $this->getTokenName() .
               '" value="' . $this->getTokenValue() .  '" />';
    }

    /********************************************************************
     * Function  : setTheCookie                                         *
     * Sets a session cookie with the csrf tokenname and tokenvalue.    *
     * You must call this method before you output any HTML.            *
     ********************************************************************/
    function setTheCookie() {
        setcookie($this->getTokenName(),$this->getTokenValue(),0,'/','',true);
    }

    /********************************************************************
     * Function  : getTheCookie                                         *
     * Returns   : The current value of the CSRF cookie, or empty       *
     *             string if it has not been set.                       *
     * Returns the value of the CSRF cookie if it has been set, or      *
     * returns an empty string otherwise.                               *
     ********************************************************************/
    public static function getTheCookie() {
        $retval = '';
        if (isset($_COOKIE[csrf::$tokenname])) {
            $retval = $_COOKIE[csrf::$tokenname];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : deleteTheCookie                                      *
     * Deletes the csrf cookie.  You must call this method before you   *
     * output any HTML.  Strictly speaking, the cookie is not deleted,  *
     * rather it is set to an empty value with an expired time.         *
     ********************************************************************/
    public static function deleteTheCookie() {
        setcookie(csrf::$tokenName,'',time()-3600,'/','',true);
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
    public static function isCookieEqualToForm() {
        $retval = false;  // Assume csrf values don't match

        $csrfcookievalue = csrf::getTheCookie();
        $csrfformvalue = "";
        if (isset($_POST[csrf::$tokenname])) {
            $csrfformvalue = $_POST[csrf::$tokenname];
        }
        if ((strlen($csrfcookievalue) > 0) &&
            (strlen($csrfformvalue) > 0) &&
            (strcmp($csrfcookievalue,$csrfformvalue) == 0)) {
            $retval = true;
        }

        return retval;
    }

    /********************************************************************
     * Function  : setTheSession                                        *
     * Sets a value in the PHP session csrf tokenname and tokenvalue.   *
     * You must have a valid PHP session (e.g. by calling               *
     * session_start()) before you call this method.                    *
     ********************************************************************/
    function setTheSession() {
        $_SESSION[$this->getTokenName()] = $this->getTokenValue();
    }

    /********************************************************************
     * Function  : getTheSession                                        *
     * Returns   : The current value of the CSRF in the PHP session,    *
     *             or empty string if it has not been set.              *
     * Returns the value of the CSRF token as set in the PHP session,   *
     * or returns an empty string otherwise.                            *
     ********************************************************************/
    public static function getTheSession() {
        $retval = '';
        if (isset($_SESSION[csrf::$tokenname])) {
            $retval = $_SESSION[csrf::$tokenname];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : deleteTheSession                                     *
     * Deletes the csrf value from the PHP session.                     *
     ********************************************************************/
    public static function deleteTheSession() {
        unset($_SESSION[csrf::$tokenname]);
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
    public static function isCookieEqualToSession() {
        $retval = false;  // Assume csrf values don't match

        $csrfcookievalue = csrf::getTheCookie();
        $csrfsesionvalue = csrf::getTheSession();
        if ((strlen($csrfcookievalue) > 0) &&
            (strlen($csrfsesionvalue) > 0) &&
            (strcmp($csrfcookievalue,$csrfsesionvalue) == 0)) {
            $retval = true;
        }

        return retval;
    }

    /********************************************************************
     * Function  : verifyCookieAndGetSubmit                             *
     * Parameter : The name of a <form>'s "submit" button, defaults to  *
     *             "submit".                                            *
     * Return    : The value of the <form>'s clicked "submit" button if *
     *             the csrf cookie matches the hidden form element, or  *
     *             empty string otherwise.                              *
     * This function assumes that the user has clicked a <form>'s       *
     * "submit" button.  The function takes in the "name" attribute of  *
     * the submit button, and returns the "value" of the submit button. *
     * For example, if the form has a button like this:                 *
     *     <input type="submit" name="mysubmit" value="Logon">          *
     * You should then pass "mysubmit" as the parameter to this         *
     * function, and "Logon" would be returend.  However, this function *
     * also verifies that the csrf cookie matches the hidden csrf form  *
     * element.  If not, then the returned string is the empty string,  *
     * and the csrf cookie is deleted.                                  *
     ********************************************************************/
    public static function verifyCookieAndGetSubmit($submit='submit')
    {
        $retval = getPostVar($submit);
        if (strlen($retval) > 0) { 
            /* Check the CSRF protection cookie */
            if (!csrf::isCookieEqualToForm()) {
                /* ERROR! - CSRF cookie not equal to hidden form element! */
                csrf::deleteTheCookie();
                $retval = '';
            }
        }
        return $retval;
    }

}

?>
