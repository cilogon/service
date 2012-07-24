<?php

require_once("util.php");

/************************************************************************
 * Class name : dbservice                                               *
 * Description: This class is a wrapper for the dbService servlet.      *
 *              The dbService servlet acts as a frontend to the         *
 *              database that stores info on users, portal parameters,  *
 *              and IdPs. This was created to allow for fast access     *
 *              to the database by keeping a connection open.  This     *
 *              class is a rework of the old store.php class.           *
 *                                                                      *
 * Example usage:                                                       *
 *     // For InCommon authn, we have a bunch of SAML attributes from   *
 *     // a Shibboleth session. Thus get the database uid for the user  *
 *     // by using the 6-parameter version of getUser().                *
 *     $uid = '';                                                       *
 *     $dbservice = new dbservice();                                    *
 *     $dbservice->getUser('jsmith@illinois.edu',                       *
 *                         'urn:mace:incommon:uiuc.edu',                *
 *                         'University of Illinois at Urbana-Champaign',*
 *                         'John','Smith','jsmith@illinois.edu');       *
 *     if (!($dbservice->status & 1)) { // OK status codes are even     *
 *         $uid = $dbservice->user_uid;                                 *
 *     }                                                                *
 *                                                                      *
 *     // For OpenID authn, we have only the OpenID provider name and   *
 *     // the OpenID identity.  Thus get the database uid for the user  *
 *     // by using the 2-parameter version of getUser().                *
 *     $uid = '';                                                       *
 *     $dbservice->getUser('https://me.yahoo.com/jsmith', 'Yahoo');     *
 *     if (!($dbservice->status & 1)) { // OK status codes are even     *
 *         $uid = $dbservice->user_uid;                                 *
 *     }                                                                *
 *                                                                      *
 *     // Later in the code, re-fetch the user using this uid           *
 *     // and print out the stored attributes.                          *
 *     if (strlen($uid) > 0) {                                          *
 *         $dbservice->getUser($uid);                                   *
 *         echo "Name = " . $dbservice->first_name . " " .              *
 *                          $dbservice->last_name  . "\n";              *
 *         echo "DN = "   . $dbservice->distinguished_name . "\n";      *
 *     }                                                                *
 *                                                                      *
 *     // For getting/setting the Shibboleth-based IdPs, use the        *
 *     // getIdps()/setIdps() methods.  These methods utilize the       *
 *     // class member array $idp_uids for reading/writing. Two         *
 *     // convenience methods (setIdpsFromKeys($array) and              *
 *     // setIdpsFromValues($array)) are provided to populate the       *
 *     // $idp_uids array from the passed-in $array.                    *
 *     $dbservice->getIdps();                                           *
 *     foreach($dbservice->idp_uids as $value) {                        *
 *         echo "$value\n";                                             *
 *     }                                                                *
 *                                                                      *
 *     $idps = array('urn:mace:incommon:ucsd.edu',                      *
 *                   'urn:mace:incommon:uiuc.edu');                     *
 *     $dbservice->setIdpsFromValues($idps);                            *
 *     //   --- OR ---                                                  *
 *     $idps = array('urn:mace:incommon:ucsd.edu' => 1,                 *
 *                   'urn:mace:incommon:uiuc.edu' => 1);                *
 *     $dbservice->setIdpsFromKeys($idps);                              *
 ************************************************************************/

class dbservice {

    /* Define the URL for the dbService */
    const defaultDBServiceURL = 'http://localhost:8080/oauth/dbService';

    /* The various STATUS_* constants, originally from Store.pm. The    *
     * The keys of the array are strings corresponding to the contant   *
     * names. The values of the array are the integer (hex) values.     *
     * For example, dbservice::$STATUS['STATUS_OK'] = 0;                *
     * Use "array_search($this->status,dbservice::$STATUS)" to look up  *
     * the STATUS_* name given the status integer value.                */
    public static $STATUS = array(
        'STATUS_OK'                        => 0x0,
        'STATUS_ACTION_NOT_FOUND'          => 0x1,
        'STATUS_NEW_USER'                  => 0x2,  
        'STATUS_USER_UPDATED'              => 0x4,
        'STATUS_USER_NOT_FOUND'            => 0x6,
        'STATUS_USER_EXISTS'               => 0x8,
        'STATUS_USER_EXISTS_ERROR'         => 0xFFFA1,
        'STATUS_USER_NOT_FOUND_ERROR'      => 0xFFFA3,
        'STATUS_TRANSACTION_NOT_FOUND'     => 0xFFFA5,
        'STATUS_IDP_SAVE_FAILED'           => 0xFFFA7,
        'STATUS_DUPLICATE_PARAMETER_FOUND' => 0xFFFF1,
        'STATUS_INTERNAL_ERROR'            => 0xFFFF3,
        'STATUS_SAVE_IDP_FAILED'           => 0xFFFF5,
        'STATUS_MALFORMED_INPUT_ERROR'     => 0xFFFF7,
        'STATUS_MISSING_PARAMETER_ERROR'   => 0xFFFF9,
        'STATUS_NO_REMOTE_USER'            => 0xFFFFB,
        'STATUS_NO_IDENTITY_PROVIDER'      => 0xFFFFD,
    );

    /* Define the various member variables previously stored by *
     * the User and PortalParameter objects.  These are "set"   *
     * by the various class methods, but you can "get" their    *
     * values by accessing them directly.                       */
    public $status;
    public $user_uid;
    public $remote_user;
    public $idp;
    public $idp_display_name;
    public $first_name;
    public $last_name;
    public $email;
    public $distinguished_name;
    public $serial_string;
    public $create_time;
    public $oauth_token;
    public $cilogon_callback;
    public $cilogon_success;
    public $cilogon_failure;
    public $cilogon_portal_name;
    public $idp_uids;  /* IdPs stored in the "values" of the array */

    private $dbserviceurl;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameter : (Optional) The URL of the database service servlet.  *
     * Returns   : A new dbservice object.                              *
     * Default constructor.  All of the various class members are       *
     * initialized to 'null' or empty arrays.                           *
     ********************************************************************/
    function __construct($serviceurl=self::defaultDBServiceURL) {
        $this->clear();
        $this->setDBServiceURL($serviceurl);
    }

    /********************************************************************
     * Function  : getDBServiceURL                                      *
     * Returns   : The URL of the database service servlet.             *
     * Returns the full URL of the database servlet used by the call()  *
     * function.                                                        *
     ********************************************************************/
    function getDBServiceURL() {
        return $this->dbserviceurl;
    }

    /********************************************************************
     * Function  : setDBServiceURL                                      *
     * Parameter : The URL of the database service servlet.             *
     * Set the private variable $dbserviceurl to the full URL of the    *
     * database servlet, which is used by the call() function.          *
     ********************************************************************/
    function setDBServiceURL($serviceurl) {
        $this->dbserviceurl = $serviceurl;
    }

    /********************************************************************
     * Function  : clear                                                *
     * Set all of the class members to 'null' or empty arrays.          *
     ********************************************************************/
    function clear() {
        $this->clearUser();
        $this->clearPortal();
        $this->clearIdps();
    }

    /********************************************************************
     * Function  : clearUser                                            *
     * Set all of the class member variables associated with getUser()  *
     * to 'null'.                                                       *
     ********************************************************************/
    function clearUser() {
        $this->status = null;
        $this->user_uid = null;
        $this->remote_user = null;
        $this->idp = null;
        $this->idp_display_name = null;
        $this->first_name = null;
        $this->last_name = null;
        $this->email = null;
        $this->distinguished_name = null;
        $this->serial_string = null;
        $this->create_time = null;
    }

    /********************************************************************
     * Function  : clearPortal                                          *
     * Set all of the class member variables associated with            *
     * getPortalParameters() to 'null'.                                 *
     ********************************************************************/
    function clearPortal() {
        $this->status = null;
        $this->oauth_token = null;
        $this->cilogon_callback = null;
        $this->cilogon_success = null;
        $this->cilogon_failure = null;
        $this->cilogon_portal_name = null;
    }

    /********************************************************************
     * Function  : clearIdps                                            *
     * Set the class member variable $idp_uids to an empty array.       *
     ********************************************************************/
    function clearIdps() {
        $this->status = null;
        $this->idp_uids = array();
    }

    /********************************************************************
     * Function  : getUser                                              *
     * Parameters: Variable number of parameters: 1, 2, or 6.           *
     *             For 1 parameter : $uid (database user identifier)    *
     *             For 2 parameters: $remote_user and $idp - used by    *
     *                 OpenID authentication.                           *
     *             For 6 parameters: $remote_user, $idp,                *
     *                 $idp_display_name, $first_name, $last_name,      *
     *                 $email - used by InCommon authentication.        *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method calls the 'getUser' action of the servlet and sets   *
     * the class member variables associated with user info             *
     * appropriately.  If the servlet returns correctly (i.e. an HTTP   *
     * status code of 200), this method returns true.                   *
     ********************************************************************/
    function getUser() {
        $retval = false;
        $this->clearUser();
        $numargs = func_num_args();
        if ($numargs == 1) {
            $retval = $this->call('action=getUser&user_uid=' . 
                urlencode(func_get_arg(0)));
        } elseif ($numargs == 2) {
            $retval = $this->call('action=getUser&remote_user=' .
                urlencode(func_get_arg(0)) . '&idp=' .
                urlencode(func_get_arg(1)));
        } elseif ($numargs == 6) {
            $retval = $this->call('action=getUser&remote_user=' .
                urlencode(func_get_arg(0)) . '&idp=' .
                urlencode(func_get_arg(1)) . '&idp_display_name=' .
                urlencode(func_get_arg(2)) . '&first_name=' .
                urlencode(func_get_arg(3)) . '&last_name=' .
                urlencode(func_get_arg(4)) . '&email=' .
                urlencode(func_get_arg(5)));
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getLastArchivedUser                                  *
     * Parameter : $uid - the database user identifier                  *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method calls the 'getLastArchivedUser' action of the        *
     * servlet and sets the class member variables associated with user *
     * info appropriately.  If the servlet returns correctly (i.e. an   *
     * HTTP status code of 200), this method returns true.              *
     ********************************************************************/
    function getLastArchivedUser($uid) {
        $this->clearUser();
        return $this->call('action=getLastArchivedUser&user_uid=' .
            urlencode($uid));
    }

    /********************************************************************
     * Function  : removeUser                                           *
     * Parameter : $uid - the database user identifier                  *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method calls the 'removeUser' action of the servlet and     *
     * sets the class member variable $status appropriately.  If the    *
     * servlet returns correctly (i.e. an HTTP status code of 200),     *
     * this method returns true.                                        *
     ********************************************************************/
    function removeUser($uid) {
        $this->clearUser();
        return $this->call('action=removeUser&user_uid=' .
            urlencode($uid));
    }

    /********************************************************************
     * Function  : getPortalParameters                                  *
     * Parameter : $oauth_token - the database OAuth identifier token   *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method calls the 'getPortalParameter' action of the servlet *
     * and sets the class member variables associated with the portal   *
     * parameters appropriately. If the servlet returns correctly (i.e. *
     * an HTTP status code of 200), this method returns true.           *
     ********************************************************************/
    function getPortalParameters($oauth_token) {
        $this->clearPortal();
        return $this->call('action=getPortalParameter&oauth_token=' .
            urlencode($oauth_token));
    }

    /********************************************************************
     * Function  : getIdps                                              *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method calls the 'getAllIdps' action of the servlet and     *
     * sets the class member array $idp_uris to contain all of the      *
     * Idps in the database, stored in the "values" of the array.  If   *
     * the servlet returns correctly (i.e. an HTTP status code of 200), *
     * this method returns true.                                        *
     ********************************************************************/
    function getIdps() {
        $this->clearIdps();
        return $this->call('action=getAllIdps');
    }

    /********************************************************************
     * Function  : setIdps                                              *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method calls the 'setAllIdps' action of the servlet using   *
     * the class memeber array $idp_uris as the source for the Idps to  *
     * be stored to the database.  Note that if this array is empty,    *
     * an error code will be returned in the status since at least one  *
     * IdP should be saved to the database.  If you want to pass an     *
     * array of Idps to be saved, see the setIdpsFromKeys($array) and   *
     * setIdpsFromValues($array) methods.  If the servlet returns       *
     * correctly (i.e. an HTTP status code of 200), this method         *
     * returns true.                                                    *
     ********************************************************************/
    function setIdps() {
        $cmdstr = 'action=setAllIdps';
        foreach ($this->idp_uids as $value) {
            $cmdstr .=  '&idp_uid=' . urlencode($value);
        }
        return $this->call($cmdstr);
    }

    /********************************************************************
     * Function  : setIdpsFromKeys                                      *
     * Parameter : An array of IdPs to be saved, stored in the "keys"   *
     *             of the array.                                        *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This is a convenience method which calls setIdps using a         *
     * passed-in array of IdPs stored as the keys of the array.  It     *
     * first sets the class member array $idp_uids appropriately and    *
     * then calls the setIdps() method. If the servlet returns          *
     * correctly (i.e. an HTTP status code of 200), this method         *
     * returns true.  See also setIdpsFromValues().                     *
     ********************************************************************/
    function setIdpsFromKeys($idps) {
        $this->clearIdps();
        foreach ($idps as $key => $value) {
            $this->idp_uids[] = $key;
        }
        return $this->setIdps();
    }

    /********************************************************************
     * Function  : setIdpsFromValues                                    *
     * Parameter : An array of IdPs to be saved, stored in the "values" *
     *             of the array.                                        *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This is a convenience method which calls setIdps using a         *
     * passed-in array of IdPs stored as the values of the array.  It   *
     * first sets the class member array $idp_uids appropriately and    *
     * then calls the setIdps() method. If the servlet returns          *
     * correctly (i.e. an HTTP status code of 200), this method         *
     * returns true.  See also setIdpsFromKeys().                       *
     ********************************************************************/
    function setIdpsFromValues($idps) {
        $this->clearIdps();
        foreach ($idps as $value) {
            $this->idp_uids[] = $value;
        }
        return $this->setIdps();
    }

    /********************************************************************
     * Function  : call                                                 *
     * Parameter : A string containing "key=value" pairs, separated by  *
     *             ampersands ("&") as appropriate for passing to a     *
     *             URL for a GET query.                                 *
     * Returns   : True if the servlet returned correctly. Else false.  *
     * This method does the brunt of the work for calling the           *
     * dbService servlet.  The single parameter is a string of          *
     * "key1=value1&key2=value2&..." containing all of the parameters   *
     * for the dbService.  If the servlet returns an HTTP status code   *
     * of 200, then this method will return true.  It parses the return *
     * output for various "key=value" lines and stores then in the      *
     * appropriate member variables, urldecoded of course.              *
     ********************************************************************/
    function call($params) {
        $success = false;

        $ch = curl_init();
        if ($ch !== false) {
            $url = $this->getDBServiceURL() . '?' . $params;
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_TIMEOUT,30);
            $output = curl_exec($ch);
            if (curl_errno($ch)) { // Send alert on curl errors
                sendErrorAlert('cUrl Error',
                           'cUrl Error    = ' . curl_error($ch) . "\n" . 
                           "URL Accessed  = $url");
            }
            if (!empty($output)) {
                $httpcode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
                if ($httpcode == 200) {
                    $success = true;
                    if (preg_match('/status=([^\r\n]+)/',$output,$match)) {
                        $this->status = urldecode($match[1]);
                    }
                    if (preg_match('/user_uid=([^\r\n]+)/',$output,$match)) {
                        $this->user_uid = urldecode($match[1]);
                    }
                    if (preg_match('/remote_user=([^\r\n]+)/',$output,$match)) {
                        $this->remote_user = urldecode($match[1]);
                    }
                    if (preg_match('/idp=([^\r\n]+)/',$output,$match)) {
                        $this->idp = urldecode($match[1]);
                    }
                    if (preg_match('/idp_display_name=([^\r\n]+)/',$output,$match)) {
                        $this->idp_display_name = urldecode($match[1]);
                    }
                    if (preg_match('/first_name=([^\r\n]+)/',$output,$match)) {
                        $this->first_name = urldecode($match[1]);
                    }
                    if (preg_match('/last_name=([^\r\n]+)/',$output,$match)) {
                        $this->last_name = urldecode($match[1]);
                    }
                    if (preg_match('/email=([^\r\n]+)/',$output,$match)) {
                        $this->email = urldecode($match[1]);
                    }
                    if (preg_match('/distinguished_name=([^\r\n]+)/',$output,$match)) {
                        $this->distinguished_name = urldecode($match[1]);
                    }
                    if (preg_match('/serial_string=([^\r\n]+)/',$output,$match)) {
                        $this->serial_string = urldecode($match[1]);
                    }
                    if (preg_match('/create_time=([^\r\n]+)/',$output,$match)) {
                        $this->create_time = urldecode($match[1]);
                    }
                    if (preg_match('/oauth_token=([^\r\n]+)/',$output,$match)) {
                        $this->oauth_token = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_callback=([^\r\n]+)/',$output,$match)) {
                        $this->cilogon_callback = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_success=([^\r\n]+)/',$output,$match)) {
                        $this->cilogon_success = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_failure=([^\r\n]+)/',$output,$match)) {
                        $this->cilogon_failure = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_portal_name=([^\r\n]+)/',$output,$match)) {
                        $this->cilogon_portal_name = urldecode($match[1]);
                    }
                    if (preg_match_all('/idp_uid=([^\r\n]+)/',$output,$match)) {
                        foreach ($match[1] as $value) {
                            $this->idp_uids[] = urldecode($value);
                        }
                    }
                }
            }
            curl_close($ch);
        }
        return $success;
    }

    /********************************************************************
     * Function  : dump                                                 *
     * This is a convenience method which prints out all of the         *
     * non-null / non-empty member variables to stdout.                 *
     ********************************************************************/
    function dump() {
        if (!is_null($this->status)) {
            echo "status=$this->status\n";
        }
        if (!is_null($this->user_uid)) {
            echo "user_uid=$this->user_uid\n";
        }
        if (!is_null($this->remote_user)) {
            echo "remote_user=$this->remote_user\n";
        }
        if (!is_null($this->idp)) {
            echo "idp=$this->idp\n";
        }
        if (!is_null($this->idp_display_name)) {
            echo "idp_display_name=$this->idp_display_name\n";
        }
        if (!is_null($this->first_name)) {
            echo "first_name=$this->first_name\n";
        }
        if (!is_null($this->last_name)) {
            echo "last_name=$this->last_name\n";
        }
        if (!is_null($this->email)) {
            echo "email=$this->email\n";
        }
        if (!is_null($this->distinguished_name)) {
            echo "distinguished_name=$this->distinguished_name\n";
        }
        if (!is_null($this->serial_string)) {
            echo "serial_string=$this->serial_string\n";
        }
        if (!is_null($this->create_time)) {
            echo "create_time=$this->create_time\n";
        }
        if (!is_null($this->oauth_token)) {
            echo "oauth_token=$this->oauth_token\n";
        }
        if (!is_null($this->cilogon_callback)) {
            echo "cilogon_callback=$this->cilogon_callback\n";
        }
        if (!is_null($this->cilogon_success)) {
            echo "cilogon_success=$this->cilogon_success\n";
        }
        if (!is_null($this->cilogon_failure)) {
            echo "cilogon_failure=$this->cilogon_failure\n";
        }
        if (!is_null($this->cilogon_portal_name)) {
            echo "cilogon_portal_name=$this->cilogon_portal_name\n";
        }
        if (count($this->idp_uids) > 0) {
            natcasesort($this->idp_uids);
            echo "idp_uids={\n";
            foreach($this->idp_uids as $value) {
                echo "    $value\n";
            }
            echo "}\n";
        }
    }


}

?>
