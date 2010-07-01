<?php

/* NOTE: Look at the bottom of this file to see that it calls           *
 * startPHPSession().  Thus you simply need to require_once(util.php)   *
 * at the top of your PHP code to start a PHP session.                  */

/************************************************************************
 * Function  : getServerVar                                             *
 * Parameter : The $_SERVER variable to query.                          *
 * Returns   : The value of the $_SERVER variable or empty string if    *
 *             that variable is not set.                                *
 * This function queries a given $_SERVER variable (which is set by     *
 * the Apache server) and returns the value.                            *
 ************************************************************************/
function getServerVar($serv) {
    $retval = '';
    if (isset($_SERVER[$serv])) {
        $retval = $_SERVER[$serv];
    }
    return $retval;
}

/************************************************************************
 * Function  : getGetVar                                                *
 * Parameter : The $_GET variable to query.                             *
 * Returns   : The value of the $_GET variable or empty string if       *
 *             that variable is not set.                                *
 * This function queries a given $_GET parameter (which is set in the   *
 * URL via a "?parameter=value" parameter) and returns the value.       *
 ************************************************************************/
function getGetVar($get) 
{ 
    $retval = '';
    if (isset($_GET[$get])) {
        $retval = $_GET[$get];
    }
    return $retval;
}

/************************************************************************
 * Function  : getPostVar                                               *
 * Parameter : The $_POST variable to query.                            *
 * Returns   : The value of the $_POST variable or empty string if      *
 *             that variable is not set.                                *
 * This function queries a given $_POST variable (which is set when     *
 * the user submits a form, for example) and returns the value.         *
 ************************************************************************/
function getPostVar($post) 
{ 
    $retval = '';
    if (isset($_POST[$post])) {
        $retval = $_POST[$post];
    }
    return $retval;
}

/************************************************************************
 * Function  : getCookieVar                                             *
 * Parameter : The $_COOKIE variable to query.                          *
 * Returns   : The value of the $_COOKIE variable or empty string if    *
 *             that variable is not set.                                *
 * This function returns the value of a given cookie.                   *
 ************************************************************************/
function getCookieVar($cookie) 
{ 
    $retval = '';
    if (isset($_COOKIE[$cookie])) {
        $retval = $_COOKIE[$cookie];
    }
    return $retval;
}

/************************************************************************
 * Function  : getSessionVar                                            *
 * Parameter : The $_SESSION variable to query.                         *
 * Returns   : The value of the $_SESSION variable or empty string if   *
 *             that variable is not set.                                *
 * This function returns the value of a given PHP Session variable.     *
 ************************************************************************/
function getSessionVar($sess) 
{ 
    $retval = '';
    if (isset($_SESSION[$sess])) {
        $retval = $_SESSION[$sess];
    }
    return $retval;
}

/************************************************************************
 * Function  : unsetSessionVar                                          *
 * Parameter : The $_SESSION variable to erase.                         *
 * This function clears the given PHP session variable by first setting *
 * it to null and then unsetting it entirely.                           *
 ************************************************************************/
function unsetSessionVar($sess) 
{
    if (isset($_SESSION[$sess])) {
        $_SESSION[$sess] = null;
        unset($_SESSION[$sess]);
    }
}

/************************************************************************
 * Function   : setOrUnsetSessionVar                                    *
 * Parameters : (1) The name of the PHP session variable to set or      *
 *                  unset.                                              *
 *              (2) The value of the PHP session variable (to set),     *
 *                  or empty string (to unset).  Defaults to empty      *
 *                  string (implies unset the session variable).        *
 * Returns    : True if the PHP session variable was set, else false.   *
 * This function can set or unset a given PHP session variable.         *
 * The first parameter is the PHP session variable to set/unset.  If    *
 * the second parameter is the empty string, then the session variable  *
 * is unset.  Otherwise, the session variable is set to the second      *
 * parameter.  The function returns true if the session variable was    *
 * set, false otherwise.  Normally, the return value can be ignored.    *
 ************************************************************************/
function setOrUnsetSessionVar($key,$value='') 
{
    $retval = false;  // Assume we want to unset the session variable
    if (strlen($key) > 0) {  // Make sure session variable name was passed in
        if (strlen($value) > 0) {
            $_SESSION[$key] = $value;
            $retval = true;
        } else {
            unsetSessionVar($key);
        }
    }
    return $retval;
}

/************************************************************************
 * Function  : startPHPSession                                          *
 * This function starts a secure PHP session and should be called at    *
 * at the beginning of each script before any HTML is output.  It also  *
 * does a trick of setting a 'lastaccess' time so that the $_SESSION    *
 * variable does not expire without warning.                            *
 ************************************************************************/
function startPHPSession()
{
    ini_set('session.cookie_secure',true);
    if (session_id() == "") session_start();
    if ((!isset($_SESSION['lastaccess']) || 
        (time() - $_SESSION['lastaccess']) > 60)) {
        $_SESSION['lastaccess'] = time();
    }
}

/************************************************************************
 * Function  : getScriptDir                                             *
 * Parameter : (Optional) Boolean to make the script a "full" url by    *
 *             prepending "https://<hostname>" to the script name.      *
 *             Defaults to false.                                       *
 * Return    : The directory or full url of the current script.         *
 * This function returns the directory (or full url) of the script that *
 * is currently running.  The returned directory/url is terminated by   *
 * a '/' character.  This function is useful for those scripts named    *
 * index.php were we don't want to actually see "index.php" in the      *
 * address bar.                                                         *
 ************************************************************************/
function getScriptDir($fullurl=false) {
    $sn = getServerVar('SCRIPT_NAME');
    $retval = dirname($sn);
    if ($retval == '.') {
        $retval = '';
    }
    if ((strlen($retval) == 0) || ($retval[strlen($retval)-1] != '/')) {
        $retval .= '/';  // Append a slash if necessary
    }
    if ($fullurl) {  // Prepend http(s)://cilogon.org
        $retval = 'http' . 
                  ((strtolower(getServerVar('HTTPS')) == 'on') ? 's' : '') .
                  '://' . getServerVar('HTTP_HOST') . $retval;
    }
    return $retval;
}

/* Start a secure PHP session */
startPHPSession();

?>
