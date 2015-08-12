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

    // Read the cilogon.ini file into an array
    public static $ini_array = null;

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

        // The old Google OpenID2 and new Google+ OIDC URLs
        define('GOOGLE_OID2','https://www.google.com/accounts/o8/id');
        define('GOOGLE_OIDC','https://accounts.google.com/o/oauth2/auth');
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
            if (is_null(self::$ini_array)) {
                self::$ini_array = @parse_ini_file(CILOGON_INI_FILE);
            }
            if ((is_array(self::$ini_array)) &&
                (array_key_exists('storage.phpsessions',self::$ini_array))) {
                $storetype = self::$ini_array['storage.phpsessions'];
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
     * or lines starting with '#" (comments, without leading spaces)    *
     * in the file are skipped.  Note that this assumes that each "key" *
     * in the file is unique.  If there is any problem reading the      *
     * file, the resulting array will be empty.                         *
     ********************************************************************/
    public static function readArrayFromFile($filename) {
        $retarray = array();
        if (is_readable($filename)) {
            $lines = file($filename,
                          FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (substr($line,0,1) != '#') { // Skip '#' comment lines
                    $values = preg_split('/\s+/',$line,2);
                    $retarray[$values[0]] = @$values[1];
                }
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
     * "value" may contain whitespace. To be super safe, the array is   *
     * first written to a temporary file, which is then renamed to the  *
     * final desired filename.                                          *
     ********************************************************************/
    public static function writeArrayToFile($filename,$thearray) {
        $retval = false;  // Assume write failed
        $tmpfnmae = tempnam("/tmp","ARR");
        if ($fh = fopen($tmpfname,'w')) {
            if (flock($fh,LOCK_EX)) {
                foreach ($thearray as $key => $value) {
                    fwrite($fh,"$key $value\n");
                }
                flock($fh,LOCK_UN);
            }
            fclose($fh);
            if (@rename($tmpfname,$filename)) {
                $retval = true;
            } else {
                @unlink($tmpfname);
            }
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
            'idp'          => 'IdP ID',
            'idpname'      => 'IdP Name',
            'uid'          => 'Database UID',
            'dn'           => 'Cert DN',
            'firstname'    => 'First Name',
            'lastname'     => 'Last Name',
            'ePPN'         => 'ePPN',
            'ePTID'        => 'ePTID',
            'openID'       => 'OpenID ID',
            'oidcID'       => 'OIDC ID',
            'loa'          => 'LOA',
            'cilogon_skin' => 'Skin Name',
            'twofactor'    => 'Two-Factor',
            'authntime'    => 'Authn Time'
        );

        $remoteaddr = self::getServerVar('REMOTE_ADDR');
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

    /********************************************************************
     * Function  : getFirstAndLastName                                  *
     * Parameters: (1) The "full name" of the user                      *
     *             (2) (Optional) The "first name" of the user          *
     *             (3) (Optional) The "last name" of the user           *
     * Return    : an array "list(firstname,lastname)"                  *
     * This function attempts to get the first and last name of a user  *
     * extracted from the "full name" (displayName) of the user.        *
     * Simply pass in all name info (full, first, and last) and the     *
     * function first tries to break up the full name into first/last.  *
     * If this is not sufficient, the function checks first and last    *
     * name. Finally, if either first or last is blank, the function    *
     * duplicates first <=> last so both names have the same value.     *
     * Note that even with all this, you still need to check if the     *
     * returned (first,last) names are blank.                           *
    /********************************************************************/
    public static function getFirstAndLastName($full,$first='',$last='') {
        $firstname = '';
        $lastname = '';

        # Try to split the incoming $full name into first and last names
        if (strlen($full) > 0) {
            $names = preg_split('/\s+/',$full,2);
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

    /********************************************************************
     * Function  : saveUserToDataStore                                  *
     * Parameters: (1) remote_user from http session                    *
     *             (2) provider IdP Identifier / URL endpoint           *
     *             (3) pretty print provider IdP name                   *
     *             (4) user's first name                                *
     *             (5) user's last name                                 *
     *             (6) user's email address                             *
     *             (7) level of assurance (e.g., openid/basic/silver)   *
     *             (8) (optional) ePPN (for SAML IdPs)                  *
     *             (9) (optional) ePTID (for SAML IdPs)                 *
     *             (10) (optional) OpenID 2.0 Identifier                *
     *             (11) OpenID Connect Identifier                       *
     * This function is called when a user logs on to save identity     *
     * information to the datastore. As it is used by both Shibboleth   *
     * and OpenID Identity Providers, some parameters passed in may     *
     * be blank (empty string). The function verifies that the minimal  *
     * sets of parameters are valid, the dbservice servlet is called    *
     * to save the user info. Then various session variables are set    *
     * for use by the program later on. In case of error, an email      *
     * alert is sent showing the missing parameters.                    *
    /********************************************************************/
    public static function saveUserToDataStore($remoteuser,$providerId,
        $providerName,$firstname,$lastname,$emailaddr,$loa,
        $eppn='',$eptid='',$openidid='',$oidcid='') {

        global $csrf;

        $dbs = new dbservice();

        // Keep original values of providerName and providerId
        $databaseProviderName = $providerName;
        $databaseProviderId   = $providerId;

        // Make sure parameters are not empty strings, and email is valid
        if (  ((strlen($remoteuser) > 0) ||
               (strlen($eppn) > 0) ||
               (strlen($eptid) > 0) ||
               (strlen($openidid) > 0) ||
               (strlen($oidcid) > 0)) && 
            (strlen($databaseProviderId) > 0) &&
            (strlen($databaseProviderName) > 0)  &&
            (strlen($firstname) > 0) &&
            (strlen($lastname) > 0) &&
            (strlen($emailaddr) > 0) &&
            (filter_var($emailaddr,FILTER_VALIDATE_EMAIL))) {

            /* For the new Google OAuth 2.0 endpoint, we want to keep the   *
             * old Google OpenID endpoint URL in the database (so user does *
             * not get a new certificate subject DN). Change the providerId *
             * and providerName to the old Google OpenID values.            */
            if (($databaseProviderName == 'Google+') ||
                ($databaseProviderId == GOOGLE_OIDC)) {
                $databaseProviderName = 'Google';
                $databaseProviderId = GOOGLE_OID2;
            }

            /* In the database, keep a consistent ProviderId format: only   *
             * allow "http" (not "https") and remove any "www." prefix.     */
            if ($loa == 'openid') {
                $databaseProviderId = preg_replace('%^https://(www\.)?%',
                    'http://',$databaseProviderId);
            }

            $result = $dbs->getUser($remoteuser,
                                    $databaseProviderId,
                                    $databaseProviderName,
                                    $firstname,
                                    $lastname,
                                    $emailaddr,
                                    $eppn,
                                    $eptid,
                                    $openidid,
                                    $oidcid); 
            util::setSessionVar('uid',$dbs->user_uid);
            util::setSessionVar('dn',$dbs->distinguished_name);
            util::setSessionVar('twofactor',$dbs->two_factor);
            util::setSessionVar('status',$dbs->status);
            if (!$result) {
                util::sendErrorAlert('dbService Error',
                    'Error calling dbservice action "getUser" in ' .
                    'saveUserToDatastore() method.');
            }
        } else { // Missing one or more required attributes
            util::unsetSessionVar('uid');
            util::unsetSessionVar('dn');
            util::unsetSessionVar('twofactor');
            util::setSessionVar('status',
                dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);
        }

        // If 'status' is not STATUS_OK*, then send an error email
        if (util::getSessionVar('status') & 1) { // Bad status codes are odd
            $mailto = 'alerts@cilogon.org';
            // Fixes CIL-205 - Notify LIGO about IdP login errors
            if (preg_match('/ligo\.org/',$databaseProviderId)) {
                $mailto .= ',rt-auth@ligo.org';
            }
            util::sendErrorAlert('Failure in ' . 
                                 (($loa == 'openid') ? '' : '/secure') .
                                 '/getuser/',
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
                'Database UID  = ' . ((strlen(
                    $i=util::getSessionVar('uid')) > 0) ? 
                        $i : '<MISSING>') . "\n" .
                'Status Code   = ' . ((strlen($i = array_search(
                    util::getSessionVar('status'),dbservice::$STATUS)) > 0) ? 
                        $i : '<MISSING>') ,
                $mailto
            );
            util::unsetSessionVar('firstname');
            util::unsetSessionVar('lastname');
            util::unsetSessionVar('loa');
            util::unsetSessionVar('idp');
            util::unsetSessionVar('ePPN');
            util::unsetSessionVar('ePTID');
            util::unsetSessionVar('openidID');
            util::unsetSessionVar('oidcID');
            util::unsetSessionVar('authntime');
        } else {
            util::setSessionVar('firstname',$firstname);
            util::setSessionVar('lastname',$lastname);
            util::setSessionVar('loa',$loa);
            util::setSessionVar('idp',$providerId);
            util::setSessionVar('ePPN',$eppn);
            util::setSessionVar('ePTID',$eptid);
            util::setSessionVar('openidID',$openidid);
            util::setSessionVar('oidcID',$oidcid);
            util::setSessionVar('authntime',time());
        }

        util::setSessionVar('idpname',$providerName); // Enable check for Google
        util::setSessionVar('submit',util::getSessionVar('responsesubmit'));
        util::unsetSessionVar('responsesubmit');
        util::unsetSessionVar('requestsilver');

        $csrf->setCookieAndSession();


        if (strlen($eppn) == 0) {
            util::unsetSessionVar('ePPN');
        }
        if (strlen($eptid) == 0) {
            util::unsetSessionVar('ePTID');
        }
        if (strlen($openidid) == 0) {
            util::unsetSessionVar('openidID');
        }
        if (strlen($oidcid) == 0) {
            util::unsetSessionVar('oidcID');
        }

    }

}

?>
