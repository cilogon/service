<?

/************************************************************************
 * Class name : csrf                                                    *
 * Description: This class creates and manages CSRF (cross-site request *
 *              forgery) values.  Upon creation of a new csrf object,   *
 *              a random value is created that can be (a) stored in a   *
 *              cookie and (b) written to a hidden form element.        *
 *              There is also a static function to see if a previously  *
 *              set csrf cookie value matches a form-submitted csrf     *
 *              element.  Note that cookies must be set before any      *
 *              HTML is output.                                         *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('csrf.php');                                         *
 *    $csrf = new csrf();                                               *
 *    $csrf->setTheCookie();                                            *
 *    // Output an HTML <form> block                                    *
 *    echo $csrf->getHiddenFormElement();                               *
 *                                                                      *
 *    // When user submits the form, first check for csrf equality      *
 *    if (csrf::isCookieEqualToForm()) {                                *
 *        // Form submission is okay - process it                       *
 *    } else {                                                          *
 *        csrf::deleteTheCookie();                                      *
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
        if (!isset($_COOKIE[$this->getTokenName()])) {
            setcookie($this->getTokenName(),$this->getTokenValue(),
                      0,'/','',true);
        }
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

        $csrfcookievalue = "";
        $csrfformvalue = "";
        if (isset($_COOKIE[csrf::$tokenname])) {
            $csrfcookievalue = $_COOKIE[csrf::$tokenname];
        }
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
}

?>
