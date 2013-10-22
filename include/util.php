<?php

/* Define a bunch of named constants */
util::setDefines();

/* Start a secure PHP session simply with "require_once('util.php');" */
util::startPHPSession();

// util::startTiming();

/************************************************************************
 * Class name : util                                                    *
 * Description: This class contains a bunch of static (class) utility   *
 * methods, for example getting and setting server environment          *
 * variables and handling cookies. See the header for each function for *
 * detailed description.                                                *
 ************************************************************************/
class util {

    // Initialize by calling util::startTiming();
    public static $timeit;

    /********************************************************************
     * Function  : setDefines                                           *
     * This function defines several named constants.                   *
     ********************************************************************/
    public static function setDefines() {
        // Full path to the php.ini-style config file for the CILogon Service
        define('CILOGON_INI_FILE','/var/www/config/cilogon.ini');

        // If HTTP_HOST is set, use that as the hostname. Else, set manually.
        $thehostname = self::getServerVar('HTTP_HOST');
        define('HOSTNAME',((strlen($thehostname) > 0)?$thehostname:'cilogon.org'));

        // For domainname, use the last two segments of the hostname.
        $thedomainname = HOSTNAME;
        if (preg_match('/[^\.]+\.[^\.]+$/',$thedomainname,$matches)) {
            $thedomainname = $matches[0];
        }
        define('DOMAINNAME',$thedomainname);
    }

    /********************************************************************
     * Function  : startTiming                                          *
     * This function initializes the class variable $timeit which is    *
     * used for timing/benchmarking purposes.                           *
     ********************************************************************/
    public static function startTiming() {
        require_once('timeit.php');
        self::$timeit = new timeit(timeit::defaultFilename,true);
    }

    /********************************************************************
     * Function  : getServerVar                                         *
     * Parameters: The $_SERVER variable to query.                      *
     * Returns   : The value of the $_SERVER variable or empty string   *
     *             if that variable is not set.                         *
     * This function queries a given $_SERVER variable (which is set    *
     * by the Apache server) and returns the value.                     *
     ********************************************************************/
    public static function getServerVar($serv) {
        $retval = '';
        if (isset($_SERVER[$serv])) {
            $retval = $_SERVER[$serv];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getGetVar                                            *
     * Parameter : The $_GET variable to query.                         *
     * Returns   : The value of the $_GET variable or empty string if   *
     *             that variable is not set.                            *
     * This function queries a given $_GET parameter (which is set in   *
     * the URL via a "?parameter=value" parameter) and returns the      *
     * value.                                                           *
     ********************************************************************/
    public static function getGetVar($get) { 
        $retval = '';
        if (isset($_GET[$get])) {
            $retval = $_GET[$get];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getPostVar                                           *
     * Parameter : The $_POST variable to query.                        *
     * Returns   : The value of the $_POST variable or empty string if  *
     *             that variable is not set.                            *
     * This function queries a given $_POST variable (which is set when *
     * the user submits a form, for example) and returns the value.     *
     ********************************************************************/
    public static function getPostVar($post) { 
        $retval = '';
        if (isset($_POST[$post])) {
            $retval = $_POST[$post];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getCookieVar                                         *
     * Parameter : The $_COOKIE variable to query.                      *
     * Returns   : The value of the $_COOKIE variable or empty string   *
     *             if that variable is not set.                         *
     * This function returns the value of a given cookie.               *
     ********************************************************************/
    public static function getCookieVar($cookie) { 
        $retval = '';
        if (isset($_COOKIE[$cookie])) {
            $retval = $_COOKIE[$cookie];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : setCookieVar                                         *
     * Parameters: (1) The name of the cookie to set.                   *
     *             (2) The value to set for the cookie.                 *
     *             (3) The future expiration time (in seconds) of the   *
     *                 cookie. Defaults to 1 year from now. If set to   *
     *                 0, the cookie expires at the end of the session. *
     * This function sets a cookie.                                     *
     ********************************************************************/
    public static function setCookieVar($cookie,$value='',$exp=31536000) {
        if ($exp > 0) {
            $exp += time();
        }
        // For transition to domain-specific cookies, unset host-specific cookie
        setcookie($cookie,'',time()-3600,'/','',true);
        setcookie($cookie,$value,$exp,'/','.'.DOMAINNAME,true);
    }

    /********************************************************************
     * Function  : unsetCookieVar                                       *
     * Parameter : The name of the cookie to unset (delete).            *
     * This function unsets a cookie. Strictly speaking, the cookie is  *
     * not removed, rather it is set to an empty value with an expired  *
     * time.                                                            *
     ********************************************************************/
    public static function unsetCookieVar($cookie) {
        setcookie($cookie,'',time()-3600,'/','',true);
        setcookie($cookie,'',time()-3600,'/','.'.DOMAINNAME,true);
        unset($_COOKIE[$cookie]);
    }

    /********************************************************************
     * Function  : getSessionVar                                        *
     * Parameter : The $_SESSION variable to query.                     *
     * Returns   : The value of the $_SESSION variable or empty string  *
     *             if that variable is not set.                         *
     * This function returns the value of a given PHP Session variable. *
     ********************************************************************/
    public static function getSessionVar($sess) { 
        $retval = '';
        if (isset($_SESSION[$sess])) {
            $retval = $_SESSION[$sess];
        }
        return $retval;
    }

    /********************************************************************
     * Function   : setSessionVar                                       *
     * Parameters : (1) The name of the PHP session variable to set (or *
     *                  unset).                                         *
     *              (2) The value of the PHP session variable (to set), *
     *                  or empty string (to unset).  Defaults to empty  *
     *                  string (implies unset the session variable).    *
     * Returns    : True if the PHP session variable was set to a       *
     *              non-empty string, false if variable was unset or if *
     *              the specified session variable was not previously   *
     *              set.                                                *
     * This function can set or unset a given PHP session variable.     *
     * The first parameter is the PHP session variable to set/unset.    *
     * If the second parameter is the empty string, then the session    *
     * variable is unset.  Otherwise, the session variable is set to    *
     * the second parameter.  The function returns true if the session  *
     * variable was set to a non-empty value, false otherwise.          *
     * Normally, the return value can be ignored.                       *
     ********************************************************************/
    public static function setSessionVar($key,$value='') {
        $retval = false;  // Assume we want to unset the session variable
        if (strlen($key) > 0) {  // Make sure session var name was passed in
            if (strlen($value) > 0) {
                $_SESSION[$key] = $value;
                $retval = true;
            } else {
                self::unsetSessionVar($key);
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : unsetSessionVar                                      *
     * Parameter : The $_SESSION variable to erase.                     *
     * This function clears the given PHP session variable by first     *
     * setting it to null and then unsetting it entirely.               *
     ********************************************************************/
    public static function unsetSessionVar($sess) {
        if (isset($_SESSION[$sess])) {
            $_SESSION[$sess] = null;
            unset($_SESSION[$sess]);
        }
    }

    /********************************************************************
     * Function   : removeShibCookies                                   *
     * This function removes all "_shib*" cookies currently in the      *
     * user's browser session. In effect, this logs the user out of     *
     * any IdP. Note that you must call this before you output any      *
     * HTML. Strictly speaking, the cookies are not removed, rather     *
     * they are set to empty values with expired times.                 *
     ********************************************************************/
    public static function removeShibCookies() {
        while (list ($key,$val) = each ($_COOKIE)) {
            if (strncmp($key,"_shib", strlen("_shib")) == 0) {
                self::unsetCookieVar($key);
            }
        }
    }

    /********************************************************************
     * Function  : startPHPSession                                      *
     * Parameter : Storage location of the PHP session data, one of     *
     *             'file' or 'mysql'. Defaults to null, which means use *
     *             the value of storage.phpsessions from the            *
     *             cilogon.ini config file, or 'file' if no such        *
     *             parameter configured.                                *
     * This function starts a secure PHP session and should be called   *
     * at the beginning of each script before any HTML is output.  It   *
     * does a trick of setting a 'lastaccess' time so that the          *
     * $_SESSION variable does not expire without warning.              *
     ********************************************************************/
    public static function startPHPSession($storetype=null) {
        // No parameter given? Use the value read in from cilogon.ini file.
        // If storage.phpsessions == 'mysql', create a sessionmgr().
        if (is_null($storetype)) {
            static $ini_array = null; // Read the ini file just once
            if (is_null($ini_array)) {
                $ini_array = @parse_ini_file(CILOGON_INI_FILE);
            }
            if ((is_array($ini_array)) &&
                (array_key_exists('storage.phpsessions',$ini_array))) {
                $storetype = $ini_array['storage.phpsessions'];
            }
        }

        if ($storetype == 'mysql') {
            require_once('sessionmgr.php');
            $sessionmgr = new sessionmgr();
        }
        
        ini_set('session.cookie_secure',true);
        ini_set('session.cookie_domain','.'.DOMAINNAME);
        @session_start();
        if ((!isset($_SESSION['lastaccess']) || 
            (time() - $_SESSION['lastaccess']) > 60)) {
            $_SESSION['lastaccess'] = time();
        }
    }

    /********************************************************************
     * Function  : getScriptDir                                         *
     * Parameters: (1) (Optional) Boolean to prepend "http(s)://" to    *
     *                 the script name. Defaults to false.              *
     *             (2) (Optional) Boolean to strip off the trailing     *
     *                 filename (e.g. index.php) from the path.         *
     *                 Defaults to true (i.e., defaults to directory    *
     *                 only without the trailing filename).             *
     * Return    : The directory or url of the current script, with or  *
     *             without the trailing .php filename.                  *
     * This function returns the directory (or full url) of the script  *
     * that is currently running.  The returned directory/url is        *
     * terminated by a '/' character (unless the second parameter is    *
     * set to true). This function is useful for those scripts named    *
     * index.php where we don't want to actually see "index.php" in the *
     * address bar (again, unless the second parameter is set to true). *
     ********************************************************************/
    public static function getScriptDir($prependhttp=false,$stripfile=true) {
        $retval = self::getServerVar('SCRIPT_NAME');
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
                      ((strtolower(self::getServerVar('HTTPS'))=='on')?'s':'') .
                      '://' . self::getServerVar('HTTP_HOST') . $retval;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : readArrayFromFile                                    *
     * Parameter : The name of the file to read.                        *
     * Return    : An array containing the contents of the file.        *
     * This function reads in the contents of a file into an array. It  *
     * is assumed that the file contains lines of the form:             *
     *     key value                                                    *
     * where "key" and "value" are separated by whitespace.  The "key"  *
     * portion of the string may not contain any whitespace, but the    *
     * "value" part of the line may contain whitespace. Any empty lines *
     * in the file are skipped.  Note that this assumes that each "key" *
     * in the file is unique.  If there is any problem reading the      *
     * file, the resulting array will be empty.                         *
     ********************************************************************/
    public static function readArrayFromFile($filename) {
        $retarray = array();
        if (is_readable($filename)) {
            $lines = file($filename,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line_num => $line) {
                $values = preg_split('/\s+/',$line,2);
                $retarray[$values[0]] = $values[1];
            }
        }

        return $retarray;
    }

    /********************************************************************
     * Function  : writeArrayToFile                                     *
     * Parameters: (1) The name of the file to write.                   *
     *             (2) The array to be written to the file.             *
     * Return    : True if successfully wrote file, false otherwise.    *
     * This funtion writes an array (with key=>value pairs) to a file,  *
     * each line will be of the form:                                   *
     *     key value                                                    *
     * The "key" and "value" strings are separated by a space. Note     *
     * that a "key" may not contain any whitespace (e.g. tabs), but a   *
     * "value" may contain whitespace.                                  *
     ********************************************************************/
    public static function writeArrayToFile($filename,$thearray) {
        $retval = false;  // Assume write failed
        if ($fh = fopen($filename,'w')) {
            if (flock($fh,LOCK_EX)) {
                foreach ($thearray as $key => $value) {
                    fwrite($fh,"$key $value\n");
                }
                flock($fh,LOCK_UN);
            }
            fclose($fh);
            $retval = true;
        }

        return $retval;
    }

    /********************************************************************
     * Function  : parseGridShibConf                                    *
     * Parameter : (Optional) Full path location of gridshib-ca.conf    *
     *             file. Defaults to                                    *
     *             '/usr/local/gridshib-ca/conf/gridshib-ca.conf'.      *
     * Return    : An array containing the various configuration        *
     *             parameters in the gridshib-ca.conf file.             *
     * This function parses the gridshib-ca.conf file and returns an    *
     * array containing the various options. It uses the PHP            *
     * PEAR::Config package to parse the config file. The               *
     * gridshib-ca.conf file is MOSTLY an Apache-style config file.     *
     * However, each option has an extra ' = ' prepended, so you will   *
     * need to strip these off each config option. For example, to get  *
     * the 'MaximumCredLifetime' value which is in the 'CA' section,    *
     * you would do the following:                                      *
     *     $gridshibconf = util::parseGridShibConf();                   */
    //     $life = preg_replace('/^\s*=\s*/','',                        *
    //             $gridshibconf['root']['CA']['MaximumCredLifetime']); *
    /********************************************************************/
    public static function parseGridShibConf($conffile=
        '/usr/local/gridshib-ca/conf/gridshib-ca.conf') {
        require_once('Config.php');
        $conf = new Config;
        $root =& $conf->parseConfig($conffile,'Apache');
        $gridshibconf = array();
        if (!(PEAR::isError($root))) {
            $gridshibconf = $root->toArray();
        }
        return $gridshibconf;
    }

    /********************************************************************
     * Function  : tempDir                                              *
     * Parameters: (1) The full path to the containing directory.       *
     *             (2) (Optional) A prefix for the new temporary        *
     *                 directory. Defaults to empty string.             *
     *             (3) (Optional) Access permissions for the new        *
     *                 temporary directory. Defaults to 0775.           *
     * Return    : Full path to the newly created temporary directory.  *
     * This function creates a temporary subdirectory within the        *
     * specified subdirectory. The new directory name is composed of    *
     * 16 hexadecimal letters, plus any prefix if you specify one. The  *
     * full path of the the newly created directory is returned.        *
    /********************************************************************/
    public static function tempDir($dir,$prefix='',$mode=0775) {
        if (substr($dir,-1) != '/') {
            $dir .= '/';
        }

        $path = '';
        do {
            $path = $dir . $prefix . sprintf("%08X%08X",mt_rand(),mt_rand());
        } while (!mkdir($path,$mode,true));

        return $path;
    }

    /********************************************************************
     * Function  : deleteDir                                            *
     * Parameters: (1) The (possibly non-empty) directory to delete.    *
     *             (2) Shred the file before deleting? Defaults to      *
     *                 false.                                           *
     * This function deletes a directory and all of its contents.       *
    /********************************************************************/
    public static function deleteDir($dir,$shred=false) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        self::deleteDir($dir."/".$object);
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

    /********************************************************************
     * Function  : htmlent                                              *
     * Parameter : A string to process with htmlentities().             *
     * Returns   : The input string processed by htmlentities with      *
     *             specific options.                                    *
     * This method is necessary since htmlentities() does not seem to   *
     * obey the default arguments as documented in the PHP manual, and  *
     * instead encodes accented characters incorrectly. By specifying   *
     * the flags and encoding, the problem is solved.                   *
    /********************************************************************/
    public static function htmlent($str) {
        return htmlentities($str,ENT_COMPAT|ENT_HTML401,"UTF-8");
    }

    /********************************************************************
     * Function  : sendErrorAlert                                       *
     * Parameters: (1) A brief summary of the error (in email subject)  *
     *             (2) A detailed description of the error (in the      *
     *                 email body)                                      *
     *             (3) The destination email address. Defaults to       *
     *                 'alerts@cilogon.org'.                            *
     * Use this function to send an error message. The $summary should  *
     * be a short description of the error since it is placed in the    *
     * subject of the email. Put a more verbose description of the      *
     * error in the $detail parameter. Any session variables available  *
     * are appended to the body of the message.                         *
    /********************************************************************/
    public static function sendErrorAlert($summary,$detail,
                                          $mailto='alerts@cilogon.org') {
        $sessionvars = array(
            'idp'          => 'IdP',
            'idpname'      => 'IdP Name',
            'uid'          => 'Database UID',
            'dn'           => 'Cert DN',
            'firstname'    => 'First Name',
            'lastname'     => 'Last Name',
            'ePPN'         => 'ePPN',
            'ePTID'        => 'ePTID',
            'openID'       => 'OpenID',
            'loa'          => 'LOA',
            'cilogon_skin' => 'Skin Name',
            'twofactor'    => 'Two-Factor'
        );

        $remoteaddr = self::getServerVar('REMOTE_ADDR');
        $remotehost = gethostbyaddr($remoteaddr);
        $mailfrom = 'From: alerts@cilogon.org' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();
        $mailsubj = 'CILogon Service on ' . HOSTNAME . ' - ' . $summary;
        $mailmsg  = '
CILogon Service - ' . $summary . '
-----------------------------------------------------------
' . $detail . '

Session Variables
-----------------
Server Host   = ' . HOSTNAME . '
Remote Address= ' . $remoteaddr . '
' . (($remotehost !== false) ? "Remote Host   = $remotehost" : '' ) . '
';

        foreach ($sessionvars as $svar => $sname) {
            if (strlen($val = self::getSessionVar($svar)) > 0) {
                $mailmsg .= sprintf("%-14s= %s\n",$sname,$val);
            }
        }

        mail($mailto,$mailsubj,$mailmsg,$mailfrom);
    }

}

?>
