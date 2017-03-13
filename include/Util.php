<?php

namespace CILogon\Service;

use CILogon\Service\CSRF;
use CILogon\Service\Loggit;
use CILogon\Service\IdpList;
use CILogon\Service\DBService;
use CILogon\Service\SessionMgr;
use CILogon\Service\Skin;
use CILogon\Service\TimeIt;
use CILogon\Service\PortalCookie;
use PEAR;
use Config;

// Full path to the php.ini-style config file for the CILogon Service
define('CILOGON_INI_FILE', '/var/www/config/cilogon.ini');

/**
 * Util
 *
 * This class contains a bunch of static (class) utility
 * methods, for example getting and setting server environment
 * variables and handling cookies. See the header for each function for
 * detailed description.
 */
class Util
{
    /**
     * @var array $ini_array Read the cilogon.ini file into an array
     */
    public static $ini_array = null;

    /**
     * @var TimeIt $timeit Initialize by calling static::startTiming() in
     * init().
     */
    public static $timeit;

    /**
     * @var IdPList $idplist A 'global' IdpList object since dplist.xml is
     *      large and expensive to create multiple times.
     */
    public static $idplist = null;

    /**
     * @var CSRF $csrf A 'global' CSRF token object to set the CSRF cookie
     * and print the hidden CSRF form element. Needs to be set only once
     * to keep the same CSRF value through the session.
     */
    public static $csrf = null;

    /**
     * @var Skin $skin A 'global' Skin object for skin configuration.
     */
    public static $skin = null;


    /**
     * getIdPList
     *
     * This function initializes the class $idplist object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $idplist to be used by other classes (since creating an IdPList
     * object is expensive).
     *
     * @return IdPList The class instantiated IdPList object.
     **/
    public static function getIdpList()
    {
        if (is_null(static::$idplist)) {
            static::$idplist = new IdpList();
        }
        return static::$idplist;
    }

    /**
     * getCsrf
     *
     * This function initializes the class $csrf object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $csrf to be used by other classes (since we want the CSRV value
     * to be consistent for the current page load).
     *
     * @return CSRF The class instantiated CSRF object.
     */
    public static function getCsrf()
    {
        if (is_null(static::$csrf)) {
            static::$csrf = new CSRF();
        }
        return static::$csrf;
    }

    /**
     * getSkin
     *
     * This function initializes the class $skin object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $skin to be used by other classes (since loading the skin is
     * potentially expensive).
     *
     * @return The class instantiated Skin object.
     */
    public static function getSkin()
    {
        if (is_null(static::$skin)) {
            static::$skin = new Skin();
        }
        return static::$skin;
    }

    /**
     * getConfigVar
     *
     * This function returns a sinle configuration vale from the
     * CILOGON_INI_FILE, or empty string if no such configuration
     * value is found in the file.
     *
     * @param string $config The config parameter to read from the
     *        cilogon.ini file.
     * @return string The value of the config parameter, or empty string
     *         if no such parameter found in config.ini.
     */
    public static function getConfigVar($config)
    {
        $retval = '';
        // Read in the config file into an array
        if (is_null(static::$ini_array)) {
            static::$ini_array = @parse_ini_file(CILOGON_INI_FILE);
        }
        if ((is_array(static::$ini_array)) &&
            (array_key_exists($config, static::$ini_array))) {
            $retval = static::$ini_array[$config];
        }
        return $retval;
    }

    /**
     * startTiming
     *
     * This function initializes the class variable $timeit which is
     * used for timing/benchmarking purposes.
     */
    public static function startTiming()
    {
        static::$timeit = new TimeIt(TimeIt::DEFAULTFILENAME, true);
    }

    /**
     * getServerVar
     *
     * This function queries a given $_SERVER variable (which is set
     * by the Apache server) and returns the value.
     *
     * @param string $serv The $_SERVER variable to query.
     * @return string The value of the $_SERVER variable or empty string
     *         if that variable is not set.
     */
    public static function getServerVar($serv)
    {
        $retval = '';
        if (isset($_SERVER[$serv])) {
            $retval = $_SERVER[$serv];
        }
        return $retval;
    }

    /**
     * getGetVar
     *
     * This function queries a given $_GET parameter (which is set in
     * the URL via a '?parameter=value' parameter) and returns the
     * value.
     *
     * @param string $get The $_GET variable to query.
     * @return string The value of the $_GET variable or empty string if
     *         that variable is not set.
     */
    public static function getGetVar($get)
    {
        $retval = '';
        if (isset($_GET[$get])) {
            $retval = $_GET[$get];
        }
        return $retval;
    }

    /**
     * getPostVar
     *
     * This function queries a given $_POST variable (which is set when
     * the user submits a form, for example) and returns the value.
     *
     * @param string $post The $_POST variable to query.
     * @return string The value of the $_POST variable or empty string if
     *         that variable is not set.
     */
    public static function getPostVar($post)
    {
        $retval = '';
        if (isset($_POST[$post])) {
            $retval = $_POST[$post];
        }
        return $retval;
    }

    /**
     * getCookieVar
     *
     * This function returns the value of a given cookie.
     *
     * @param string $cookie he $_COOKIE variable to query.
     * @return string The value of the $_COOKIE variable or empty string
     *         if that variable is not set.
     */
    public static function getCookieVar($cookie)
    {
        $retval = '';
        if (isset($_COOKIE[$cookie])) {
            $retval = $_COOKIE[$cookie];
        }
        return $retval;
    }

    /**
     * setCookieVar
     *
     * This function sets a cookie.
     *
     * @param string $cookie The name of the cookie to set.
     * @param string $value (Optional) The value to set for the cookie.
     *        Defaults to empty string.
     * @param int $exp The future expiration time (in seconds) of the
     *        cookie. Defaults to 1 year from now. If set to 0,
     *        the cookie expires at the end of the session.
     */
    public static function setCookieVar($cookie, $value = '', $exp = 31536000)
    {
        if ($exp > 0) {
            $exp += time();
        }
        setcookie($cookie, $value, $exp, '/', '.'.static::getDN(), true);
        $_COOKIE[$cookie] = $value;
    }

    /**
     * unsetCookieVar
     *
     * This function unsets a cookie. Strictly speaking, the cookie is
     * not removed, rather it is set to an empty value with an expired
     * time.
     *
     * @param string $cookie The name of the cookie to unset (delete).
     */
    public static function unsetCookieVar($cookie)
    {
        setcookie($cookie, '', 1, '/', '.'.static::getDN(), true);
        unset($_COOKIE[$cookie]);
    }

    /**
     * getPortalOrNormalCookieVar
     *
     * This is a convenience function which first checks if there is a
     * OAuth 1.0a ('delegate') or OIDC ('authorize') session active.
     * If so, it attempts to get the requested cookie from the
     * associated portalcookie. If there is not an OAuth/OIDC session
     * active, it looks for a 'normal' cookie. If you need a
     * portalcookie object to do multiple get/set method calls from
     * one function, it is probably better NOT to use this method since
     * creating the portalcookie object is potentially expensive.
     *
     * @param string $cookie The name of the cookie to get.
     * @return string The cookie value from either the portalcookie
     *         (in the case of an active OAuth session) or the
     *         'normal' cookie. Return empty string if no matching
     *         cookie in either place.
     */
    public static function getPortalOrNormalCookieVar($cookie)
    {
        $retval = '';
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if (strlen($pn) > 0) {
            $retval = $pc->get($cookie);
        } else {
            $retval = static::getCookieVar($cookie);
        }
        return $retval;
    }

    /**
     * getSessionVar
     *
     * This function returns the value of a given PHP Session variable.
     *
     * @param string $sess The $_SESSION variable to query.
     * @return string The value of the $_SESSION variable or empty string
     *         if that variable is not set.
     */
    public static function getSessionVar($sess)
    {
        $retval = '';
        if (isset($_SESSION[$sess])) {
            $retval = $_SESSION[$sess];
        }
        return $retval;
    }

    /**
     * setSessionVar
     *
     * This function can set or unset a given PHP session variable.
     * The first parameter is the PHP session variable to set/unset.
     * If the second parameter is the empty string, then the session
     * variable is unset.  Otherwise, the session variable is set to
     * the second parameter.  The function returns true if the session
     * variable was set to a non-empty value, false otherwise.
     * Normally, the return value can be ignored.
     *
     * @param string $key The name of the PHP session variable to set
     *        (or unset).
     * @param string $value (Optional) The value of the PHP session variable
     *        (to set), or empty string (to unset). Defaults to empty
     *        string (implies unset the session variable).
     * @return bool True if the PHP session variable was set to a
     *         non-empty string, false if variable was unset or if
     *         the specified session variable was not previously set.
     */
    public static function setSessionVar($key, $value = '')
    {
        $retval = false;  // Assume we want to unset the session variable
        if (strlen($key) > 0) {  // Make sure session var name was passed in
            if (strlen($value) > 0) {
                $_SESSION[$key] = $value;
                $retval = true;
            } else {
                static::unsetSessionVar($key);
            }
        }
        return $retval;
    }

    /**
     * unsetSessionVar
     *
     * This function clears the given PHP session variable by first
     * setting it to null and then unsetting it entirely.
     *
     * @param string $sess The $_SESSION variable to erase.
     */
    public static function unsetSessionVar($sess)
    {
        if (isset($_SESSION[$sess])) {
            $_SESSION[$sess] = null;
            unset($_SESSION[$sess]);
        }
    }

    /**
     * removeShibCookies
     *
     * This function removes all '_shib*' cookies currently in the
     * user's browser session. In effect, this logs the user out of
     * any IdP. Note that you must call this before you output any
     * HTML. Strictly speaking, the cookies are not removed, rather
     * they are set to empty values with expired times.
     */
    public static function removeShibCookies()
    {
        while (list($key, $val) = each($_COOKIE)) {
            if (strncmp($key, '_shib', strlen('_shib')) == 0) {
                static::unsetCookieVar($key);
            }
        }
    }

    /**
     * startPHPSession
     *
     * This function starts a secure PHP session and should be called
     * at the beginning of each script before any HTML is output.  It
     * does a trick of setting a 'lastaccess' time so that the
     * $_SESSION variable does not expire without warning.
     *
     * @param string $storetype (Optional) Storage location of the PHP
     *        session data, one of 'file' or 'mysql'. Defaults to null,
     *        which means use the value of storage.phpsessions from the
     *        cilogon.ini config file, or 'file' if no such
     *        parameter configured.
     */
    public static function startPHPSession($storetype = null)
    {
        // No parameter given? Use the value read in from cilogon.ini file.
        // If storage.phpsessions == 'mysql', create a sessionmgr().
        $storetype = static::getConfigVar('storage.phpsessions');

        if ($storetype == 'mysql') {
            $sessionmgr = new SessionMgr();
        }

        ini_set('session.cookie_secure', true);
        ini_set('session.cookie_domain', '.'.static::getDN());
        session_start();
        if ((!isset($_SESSION['lastaccess']) ||
            (time() - $_SESSION['lastaccess']) > 60)) {
            $_SESSION['lastaccess'] = time();
        }
    }

    /**
     * getScriptDir
     *
     * This function returns the directory (or full url) of the script
     * that is currently running.  The returned directory/url is
     * terminated by a '/' character (unless the second parameter is
     * set to true). This function is useful for those scripts named
     * index.php where we don't want to actually see 'index.php' in the
     * address bar (again, unless the second parameter is set to true).
     *
     * @param bool $prependhttp (Optional) Boolean to prepend 'http(s)://' to
     *        the script name. Defaults to false.
     * @param bool $stripfile (Optional) Boolean to strip off the trailing
     *        filename (e.g. index.php) from the path.
     *        Defaults to true (i.e., defaults to directory
     *        only without the trailing filename).
     * @return string The directory or url of the current script, with or
     *         without the trailing .php filename.
     */
    public static function getScriptDir($prependhttp = false, $stripfile = true)
    {
        $retval = static::getServerVar('SCRIPT_NAME');
        if ($stripfile) {
            $retval = dirname($retval);
        }
        if ($retval == '.') {
            $retval = '';
        }
        if ((strlen($retval) == 0) ||
            ($stripfile && ($retval[strlen($retval)-1] != '/'))) {
            $retval .= '/';  // Append a slash if necessary
        }
        if ($prependhttp) {  // Prepend http(s)://hostname
            $retval = 'http' .
                      ((strtolower(static::getServerVar('HTTPS')) == 'on')?'s':'') .
                      '://' . static::getServerVar('HTTP_HOST') . $retval;
        }
        return $retval;
    }

    /**
     * readArrayFromFile
     *
     * This function reads in the contents of a file into an array. It
     * is assumed that the file contains lines of the form:
     *     key value
     * where 'key' and 'value' are separated by whitespace.  The 'key'
     * portion of the string may not contain any whitespace, but the
     * 'value' part of the line may contain whitespace. Any empty lines
     * or lines starting with '#' (comments, without leading spaces)
     * in the file are skipped.  Note that this assumes that each 'key'
     * in the file is unique.  If there is any problem reading the
     * file, the resulting array will be empty.
     *
     * @param string $filename The name of the file to read.
     * @return array An array containing the contents of the file.
     */
    public static function readArrayFromFile($filename)
    {
        $retarray = array();
        if (is_readable($filename)) {
            $lines = file(
                $filename,
                FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES
            );
            foreach ($lines as $line) {
                if (substr($line, 0, 1) != '#') { // Skip '#' comment lines
                    $values = preg_split('/\s+/', $line, 2);
                    $retarray[$values[0]] = @$values[1];
                }
            }
        }

        return $retarray;
    }

    /**
     * writeArrayToFile
     *
     * This funtion writes an array (with key=>value pairs) to a file,
     * each line will be of the form:
     *     key value
     * The 'key' and 'value' strings are separated by a space. Note
     * that a 'key' may not contain any whitespace (e.g. tabs), but a
     * 'value' may contain whitespace. To be super safe, the array is
     * first written to a temporary file, which is then renamed to the
     * final desired filename.
     *
     * @param string $filename The name of the file to write.
     * @param array $thearray The array to be written to the file.
     * @return bool True if successfully wrote file, false otherwise.
     */
    public static function writeArrayToFile($filename, $thearray)
    {
        $retval = false;  // Assume write failed
        $tmpfnmae = tempnam('/tmp', 'ARR');
        if ($fh = fopen($tmpfname, 'w')) {
            if (flock($fh, LOCK_EX)) {
                foreach ($thearray as $key => $value) {
                    fwrite($fh, "$key $value\n");
                }
                flock($fh, LOCK_UN);
            }
            fclose($fh);
            if (@rename($tmpfname, $filename)) {
                $retval = true;
            } else {
                @unlink($tmpfname);
            }
        }

        return $retval;
    }

    /**
     * parseGridShibConf
     *
     * This function parses the gridshib-ca.conf file and returns an
     * array containing the various options. It uses the PHP
     * PEAR::Config package to parse the config file. The
     * gridshib-ca.conf file is MOSTLY an Apache-style config file.
     * However, each option has an extra ' = ' prepended, so you will
     * need to strip these off each config option. For example, to get
     * the 'MaximumCredLifetime' value which is in the 'CA' section,
     * you would do the following:
     *     $gridshibconf = Util::parseGridShibConf();
     *     $life = preg_replace('%^\s*=\s*%','',
     *             $gridshibconf['root']['CA']['MaximumCredLifetime']);
     *
     * @param string $conffile (Optional) Full path location of
     *        gridshib-ca.conf file. Defaults to
     *        '/usr/local/gridshib-ca/conf/gridshib-ca.conf'.
     * @return array An array containing the various configuration
     *         parameters in the gridshib-ca.conf file.
     */
    public static function parseGridShibConf(
        $conffile = '/usr/local/gridshib-ca/conf/gridshib-ca.conf'
    ) {
        $conf = new Config;
        $root = $conf->parseConfig($conffile, 'Apache');
        $gridshibconf = array();
        if (!(PEAR::isError($root))) {
            $gridshibconf = $root->toArray();
        }
        return $gridshibconf;
    }

    /**
     * tempDir
     *
     * This function creates a temporary subdirectory within the
     * specified subdirectory. The new directory name is composed of
     * 16 hexadecimal letters, plus any prefix if you specify one. The
     * full path of the the newly created directory is returned.
     *
     * @param string $dir The full path to the containing directory.
     * @param string $prefix (Optional) A prefix for the new temporary
     *        directory. Defaults to empty string.
     * @param int $mode (Optional) Access permissions for the new
     *        temporary directory. Defaults to 0775.
     * @return string Full path to the newly created temporary directory.
     */
    public static function tempDir($dir, $prefix = '', $mode = 0775)
    {
        if (substr($dir, -1) != '/') {
            $dir .= '/';
        }

        $path = '';
        do {
            $path = $dir . $prefix . sprintf("%08X%08X", mt_rand(), mt_rand());
        } while (!mkdir($path, $mode, true));

        return $path;
    }

    /**
     * deleteDir
     *
     * This function deletes a directory and all of its contents.
     *
     * @param string $dir The (possibly non-empty) directory to delete.
     * @param bool $shred (Optional) Shred the file before deleting?
     *        Defaults to false.
     */
    public static function deleteDir($dir, $shred = false)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        static::deleteDir($dir."/".$object);
                    } else {
                        if ($shred) {
                            @exec('/bin/env /usr/bin/shred -u -z '.$dir."/".$object);
                        } else {
                            @unlink($dir."/".$object);
                        }
                    }
                }
            }
            reset($objects);
            @rmdir($dir);
        }
    }

    /**
     * htmlent
     *
     * This method is necessary since htmlentities() does not seem to
     * obey the default arguments as documented in the PHP manual, and
     * instead encodes accented characters incorrectly. By specifying
     * the flags and encoding, the problem is solved.
     *
     * @param string $str : A string to process with htmlentities().
     * @return string The input string processed by htmlentities with
     *         specific options.
     */
    public static function htmlent($str)
    {
        return htmlentities($str, ENT_COMPAT|ENT_HTML401, 'UTF-8');
    }

    /**
     * sendErrorAlert
     *
     * Use this function to send an error message. The $summary should
     * be a short description of the error since it is placed in the
     * subject of the email. Put a more verbose description of the
     * error in the $detail parameter. Any session variables available
     * are appended to the body of the message.
     *
     * @param string $summary A brief summary of the error (in email subject)
     * @param string $detail A detailed description of the error (in the
     *        email body)
     * @param string $mailto (Optional) The destination email address.
     *        Defaults to 'alerts@cilogon.org'.
     */
    public static function sendErrorAlert(
        $summary,
        $detail,
        $mailto = 'alerts@cilogon.org'
    ) {
        $sessionvars = array(
            'idp'          => 'IdP ID',
            'idpname'      => 'IdP Name',
            'uid'          => 'Database UID',
            'dn'           => 'Cert DN',
            'firstname'    => 'First Name',
            'lastname'     => 'Last Name',
            'displayname'  => 'Display Name',
            'ePPN'         => 'ePPN',
            'ePTID'        => 'ePTID',
            'openID'       => 'OpenID ID',
            'oidcID'       => 'OIDC ID',
            'loa'          => 'LOA',
            'affiliation'  => 'Affiliation',
            'ou'           => 'OU',
            'cilogon_skin' => 'Skin Name',
            'twofactor'    => 'Two-Factor',
            'authntime'    => 'Authn Time'
        );

        $remoteaddr = static::getServerVar('REMOTE_ADDR');
        $remotehost = gethostbyaddr($remoteaddr);
        $mailfrom = 'From: alerts@cilogon.org' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();
        $mailsubj = 'CILogon Service on ' . php_uname('n') .
                    ' - ' . $summary;
        $mailmsg  = '
CILogon Service - ' . $summary . '
-----------------------------------------------------------
' . $detail . '

Session Variables
-----------------
Server Host   = ' . static::getHN() . '
Remote Address= ' . $remoteaddr . '
' . (($remotehost !== false) ? "Remote Host   = $remotehost" : '') . '
';

        foreach ($sessionvars as $svar => $sname) {
            if (strlen($val = static::getSessionVar($svar)) > 0) {
                $mailmsg .= sprintf("%-14s= %s\n", $sname, $val);
            }
        }

        mail($mailto, $mailsubj, $mailmsg, $mailfrom);
    }

    /**
     * getFirstAndLastName
     *
     * This function attempts to get the first and last name of a user
     * extracted from the 'full name' (displayName) of the user.
     * Simply pass in all name info (full, first, and last) and the
     * function first tries to break up the full name into first/last.
     * If this is not sufficient, the function checks first and last
     * name. Finally, if either first or last is blank, the function
     * duplicates first <=> last so both names have the same value.
     * Note that even with all this, you still need to check if the
     * returned (first,last) names are blank.
     *
     * @param string $full The 'full name' of the user
     * @param string $first (Optional) The 'first name' of the user
     * @param string $last (Optional) The 'last name' of the user
     * @return array An array 'list(firstname,lastname)'
     */
    public static function getFirstAndLastName($full, $first = '', $last = '')
    {
        $firstname = '';
        $lastname = '';

        # Try to split the incoming $full name into first and last names
        if (strlen($full) > 0) {
            $names = preg_split('/\s+/', $full, 2);
            $firstname = @$names[0];
            $lastname =  @$names[1];
        }

        # If either first or last name blank, then use incoming $first and $last
        if (strlen($firstname) == 0) {
            $firstname = $first;
        }
        if (strlen($lastname) == 0) {
            $lastname = $last;
        }

        # Finally, if only a single name, copy first name <=> last name
        if (strlen($lastname) == 0) {
            $lastname = $firstname;
        }
        if (strlen($firstname) == 0) {
            $firstname = $lastname;
        }

        # Return both names as an array (i.e., use list($first,last)=...)
        return array($firstname,$lastname);
    }

    /**
     * getHN
     *
     * This function calculates and returns the 'hostname' for the
     * server. It first checks HTTP_HOST. If not set, it returns
     * 'cilogon.org'. This is needed by command line scripts.
     *
     * @return string The 'Hostname' for the web server.
     */
    public static function getHN()
    {
        $thehostname = static::getServerVar('HTTP_HOST');
        if (strlen($thehostname) == 0) {
            $thehostname = 'cilogon.org';
        }
        return $thehostname;
    }

    /**
     * getDN
     *
     * This function calculates and returns the 'domainname' for the
     * server. It uses the hostname value calculated by getHN() and
     * uses the last two segments.
     *
     * @return string The 'Domainname' for the web server.
     */
    public static function getDN()
    {
        $thedomainname = static::getHN();
        if (preg_match('/[^\.]+\.[^\.]+$/', $thedomainname, $matches)) {
            $thedomainname = $matches[0];
        }
        return $thedomainname;
    }

    /**
     * getAuthzUrl
     *
     * This funtion takes in the name of an IdP (e.g., 'Google') and
     * returns the assoicated OAuth2 authorization URL.
     *
     * @param string $idp The name of an OAuth2 Identity Provider.
     * @return string The authorization URL for the given IdP.
     */
    public static function getAuthzUrl($idp)
    {
        $url = null;
        $idptourl = array(
            'Google' => 'https://accounts.google.com/o/oauth2/auth',
            'GitHub' => 'https://github.com/login/oauth/authorize',
        );
        if (array_key_exists($idp, $idptourl)) {
            $url = $idptourl[$idp];
        }
        return $url;
    }

    /**
     * getAuthzIdP
     *
     * This function takes in the OAuth2 authorization URL and returns
     * the associated pretty-print name of the IdP.
     *
     * @param string $url The authorization URL of an OAuth2 Identity Provider.
     * @return string The name of the IdP.
     */
    public static function getAuthzIdP($url)
    {
        $idp = null;
        $urltoidp = array(
            'https://accounts.google.com/o/oauth2/auth' => 'Google',
            'https://github.com/login/oauth/authorize'  => 'GitHub',
        );
        if (array_key_exists($url, $urltoidp)) {
            $idp = $urltoidp[$url];
        }
        return $idp;
    }

    /**
     * saveUserToDataStore
     *
     * This function is called when a user logs on to save identity
     * information to the datastore. As it is used by both Shibboleth
     * and OpenID Identity Providers, some parameters passed in may
     * be blank (empty string). The function verifies that the minimal
     * sets of parameters are valid, the dbservice servlet is called
     * to save the user info. Then various session variables are set
     * for use by the program later on. In case of error, an email
     * alert is sent showing the missing parameters.
     *
     * @param string $remoteuser The REMOTE_USER from HTTP headers
     * @param string $providerId The provider IdP Identifier / URL endpoint
     * @param string providerName The pretty print provider IdP name
     * @param string $firstname The user's first name
     * @param string $lastname The user's last name
     * @param string $displayname The user's display name
     * @param string $emailaddr The user's email address
     * @param string $loa The level of assurance (e.g., openid/basic/silver)
     * @param string $eppn (optional) User's ePPN (for SAML IdPs)
     * @param string $eptid (optional) User's ePTID (for SAML IdPs)
     * @param string $openidid (optional) User's OpenID 2.0 Identifier
     * @param string $oidcid (optional) User's OpenID Connect Identifier
     * @param string $affiliation (optional) User's affiliation
     * @param string $ou (optional) User's organizational unit (OU)
     */
    public static function saveUserToDataStore(
        $remoteuser,
        $providerId,
        $providerName,
        $firstname,
        $lastname,
        $displayname,
        $emailaddr,
        $loa,
        $eppn = '',
        $eptid = '',
        $openidid = '',
        $oidcid = '',
        $affiliation = '',
        $ou = ''
    ) {
        $dbs = new DBService();

        // Keep original values of providerName and providerId
        $databaseProviderName = $providerName;
        $databaseProviderId   = $providerId;

        // Save the passed-in variables to the session for later use
        // (e.g., by the error handler in handleGotUser).
        if (strlen($firstname) > 0) {
            static::setSessionVar('firstname', $firstname);
        }
        if (strlen($lastname) > 0) {
            static::setSessionVar('lastname', $lastname);
        }
        if (strlen($displayname) > 0) {
            static::setSessionVar('displayname', $displayname);
        }
        if (strlen($emailaddr) > 0) {
            static::setSessionvar('emailaddr', $emailaddr);
        }
        if (strlen($loa) > 0) {
            static::setSessionVar('loa', $loa);
        }
        if (strlen($eppn) > 0) {
            static::setSessionVar('ePPN', $eppn);
        }
        if (strlen($eptid) > 0) {
            static::setSessionVar('ePTID', $eptid);
        }
        if (strlen($openidid) > 0) {
            static::setSessionVar('openidID', $openidid);
        }
        if (strlen($oidcid) > 0) {
            static::setSessionVar('oidcID', $oidcid);
        }
        if (strlen($affiliation) > 0) {
            static::setSessionVar('affiliation', $affiliation);
        }
        if (strlen($ou) > 0) {
            static::setSessionVar('ou', $ou);
        }
        static::setSessionVar('idp', $providerId); // Enable error message
        static::setSessionVar('idpname', $providerName); // Enable check for Google
        static::setSessionVar('submit', static::getSessionVar('responsesubmit'));

        // Make sure parameters are not empty strings, and email is valid
        // Must have at least one of remoteuser/eppn/eptid/openidid/oidcid
        if (((strlen($remoteuser) > 0) ||
               (strlen($eppn) > 0) ||
               (strlen($eptid) > 0) ||
               (strlen($openidid) > 0) ||
               (strlen($oidcid) > 0)) &&
            (strlen($databaseProviderId) > 0) &&
            (strlen($databaseProviderName) > 0)  &&
            (strlen($firstname) > 0) &&
            (strlen($lastname) > 0) &&
            (strlen($emailaddr) > 0) &&
            (filter_var($emailaddr, FILTER_VALIDATE_EMAIL))) {
            // For the new Google OAuth 2.0 endpoint, we want to keep the
            // old Google OpenID endpoint URL in the database (so user does
            // not get a new certificate subject DN). Change the providerId
            // and providerName to the old Google OpenID values.
            if (($databaseProviderName == 'Google+') ||
                ($databaseProviderId == static::getAuthzUrl('Google'))) {
                $databaseProviderName = 'Google';
                $databaseProviderId = 'https://www.google.com/accounts/o8/id';
            }

            // In the database, keep a consistent ProviderId format: only
            // allow 'http' (not 'https') and remove any 'www.' prefix.
            if ($loa == 'openid') {
                $databaseProviderId = preg_replace(
                    '%^https://(www\.)?%',
                    'http://',
                    $databaseProviderId
                );
            }

            $result = $dbs->getUser(
                $remoteuser,
                $databaseProviderId,
                $databaseProviderName,
                $firstname,
                $lastname,
                $displayname,
                $emailaddr,
                $eppn,
                $eptid,
                $openidid,
                $oidcid,
                $affiliation,
                $ou
            );
            static::setSessionVar('uid', $dbs->user_uid);
            static::setSessionVar('dn', $dbs->distinguished_name);
            static::setSessionVar('twofactor', $dbs->two_factor);
            static::setSessionVar('status', $dbs->status);
            if (!$result) {
                static::sendErrorAlert(
                    'dbService Error',
                    'Error calling dbservice action "getUser" in ' .
                    'saveUserToDatastore() method.'
                );
            }
        } else { // Missing one or more required attributes
            static::setSessionVar(
                'status',
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']
            );
        }

        // If 'status' is not STATUS_OK*, then send an error email
        $status = static::getSessionVar('status');
        if ($status & 1) { // Bad status codes are odd

            // For missing parameter errors, log an error message
            if ($status ==
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) {
                $log = new Loggit();
                $log->error('STATUS_MISSING_PARAMETER_ERROR', true);
            }

            // For other dbservice errors OR for any error involving
            // LIGO (e.g., missing parameter error), send email alert.
            if (($status !=
                    DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) ||
                (preg_match('/ligo\.org/', $databaseProviderId))) {
                $mailto = 'alerts@cilogon.org';

                // Set $disableligoalerts = true to stop LIGO failures
                // from being sent to 'alerts@cilogon.org', but still
                // sent to 'cilogon-alerts@ligo.org'.
                $disableligoalerts = false;

                // Fixes CIL-205 - Notify LIGO about IdP login errors
                if (preg_match('/ligo\.org/', $databaseProviderId)) {
                    if ($disableligoalerts) {
                        $mailto = '';
                    }
                    $mailto .= ((strlen($mailto) > 0) ? ',' : '') .
                        'cilogon-alerts@ligo.org';
                }

                static::sendErrorAlert(
                    'Failure in ' .
                        (($loa == 'openid') ? '' : '/secure') . '/getuser/',
                    'Remote_User   = ' . ((strlen($remoteuser) > 0) ?
                        $remoteuser : '<MISSING>') . "\n" .
                    'IdP ID        = ' . ((strlen($databaseProviderId) > 0) ?
                        $databaseProviderId : '<MISSING>') . "\n" .
                    'IdP Name      = ' . ((strlen($databaseProviderName) > 0) ?
                        $databaseProviderName : '<MISSING>') . "\n" .
                    'First Name    = ' . ((strlen($firstname) > 0) ?
                        $firstname : '<MISSING>') . "\n" .
                    'Last Name     = ' . ((strlen($lastname) > 0) ?
                        $lastname : '<MISSING>') . "\n" .
                    'Display Name  = ' . ((strlen($displayname) > 0) ?
                        $displayname : '<MISSING>') . "\n" .
                    'Email Address = ' . ((strlen($emailaddr) > 0) ?
                        $emailaddr : '<MISSING>') . "\n" .
                    'ePPN          = ' . ((strlen($eppn) > 0) ?
                        $eppn : '<MISSING>') . "\n" .
                    'ePTID         = ' . ((strlen($eptid) > 0) ?
                        $eptid : '<MISSING>') . "\n" .
                    'OpenID ID     = ' . ((strlen($openidid) > 0) ?
                        $openidid : '<MISSING>') . "\n" .
                    'OIDC ID       = ' . ((strlen($oidcid) > 0) ?
                        $oidcid : '<MISSING>') . "\n" .
                    'Affiliation   = ' . ((strlen($affiliation) > 0) ?
                        $affiliation : '<MISSING>') . "\n" .
                    'OU            = ' . ((strlen($ou) > 0) ?
                        $ou : '<MISSING>') . "\n" .
                    'Database UID  = ' . ((strlen(
                        $i = static::getSessionVar('uid')
                    ) > 0) ?  $i : '<MISSING>') . "\n" .
                    'Status Code   = ' . ((strlen(
                        $i = array_search(
                            $status,
                            DBService::$STATUS
                        )
                    ) > 0) ?  $i : '<MISSING>'),
                    $mailto
                );
            }
        } else { // status is okay, set authntime
            static::setSessionVar('authntime', time());
        }

        static::unsetSessionVar('responsesubmit');
        static::unsetSessionVar('requestsilver');

        static::getCsrf()->setCookieAndSession();
    }

    /**
     * unsetClientSessionVars
     *
     * This function removes all of the PHP session variables related to
     * the client session.
     */
    public static function unsetClientSessionVars()
    {
        static::unsetSessionVar('submit');

        // Specific to 'Download Certificate' page
        static::unsetSessionVar('activation');
        static::unsetSessionVar('p12');
        static::unsetSessionVar('p12lifetime');
        static::unsetSessionVar('p12multiplier');

        // Specific to OAuth 1.0a flow
        static::unsetSessionVar('portalstatus');
        static::unsetSessionVar('callbackuri');
        static::unsetSessionVar('successuri');
        static::unsetSessionVar('failureuri');
        static::unsetSessionVar('portalname');
        static::unsetSessionVar('tempcred');

        // Specific to OIDC flow
        static::unsetSessionVar('clientparams');
    }

    /**
     * unsetUserSessionVars
     *
     * This function removes all of the PHP session variables related to
     * the user's session.  This will force the user to log on (again)
     * with their IdP and call the 'getuser' script to repopulate the PHP
     * session.
     */
    public static function unsetUserSessionVars()
    {
        // Needed for verifyCurrentUserSession
        static::unsetSessionVar('idp');
        static::unsetSessionVar('idpname');
        static::unsetSessionVar('status');
        static::unsetSessionVar('uid');
        static::unsetSessionVar('dn');
        static::unsetSessionVar('authntime');

        // Specific to 2FA
        static::unsetSessionVar('twofactor');

        // Variables set by getuser
        static::unsetSessionVar('firstname');
        static::unsetSessionVar('lastname');
        static::unsetSessionVar('displayname');
        static::unsetSessionVar('emailaddr');
        static::unsetSessionVar('loa');
        static::unsetSessionVar('ePPN');
        static::unsetSessionVar('ePTID');
        static::unsetSessionVar('openidID');
        static::unsetSessionVar('oidcID');
        static::unsetSessionVar('affiliation');
        static::unsetSessionVar('ou');

        // Current skin
        static::unsetSessionVar('cilogon_skin');
    }

    /**
     * unsetAllUserSessionVars
     *
     * This is a convenience method to clear all session variables related
     * to the client and the user.
     */
    public static function unsetAllUserSessionVars()
    {
        static::unsetClientSessionVars();
        static::unsetUserSessionVars();
    }

    /**
     * verifySessionAndCall
     *
     * This function is a convenience method called by several cases in the
     * main 'switch' call at the top of the index.php file. I noticed
     * a pattern where verifyCurrentUserSession() was called to verify the
     * current user session. Upon success, one or two functions were called
     * to continue program, flow. Upon failure, cookies and session
     * variables were cleared, and the main Logon page was printed. This
     * function encapsulates that pattern. If the user's session is valid,
     * the passed-in $func is called, possibly with parameters passed in as
     * an array. The function returns true if the session is verified, so
     * that other functions may be called upon return.
     *
     * @param function $func The function to call if the current session is
     *        successfully verified.
     * @param array $params (Optional) An array of parameters to pass to the
     8        function. Defaults to empty array, meaning zero parameters.
     */
    public static function verifySessionAndCall($func, $params = array())
    {
        $retval = false;
        if (Content::verifyCurrentUserSession()) { // Verify PHP session is valid
            $retval = true;
            call_user_func_array($func, $params);
        } else {
            printLogonPage(true); // Clear cookies and session vars too
        }
        return $retval;
    }
}
