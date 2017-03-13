<?php

namespace CILogon\Service;

use CILogon\Service\Util;

/**
 * DBService
 *
 * This class is a wrapper for the dbService servlet.  The dbService
 * servlet acts as a frontend to the database that stores info on users,
 * portal parameters, and IdPs. This was created to allow for fast
 * access to the database by keeping a connection open.  This class is a
 * rework of the old store.php class.
 *
 * Example usage:
 *     // For authentication, we have a bunch of attributes from an
 *     // identity provider. Thus get the database uid for the user
 *     // by using the multi-parameter version of getUser().
 *     $uid = '';
 *     $dbservice = new DBService();
 *     $dbservice->getUser('jsmith@illinois.edu',
 *                         'urn:mace:incommon:uiuc.edu',
 *                         'University of Illinois at Urbana-Champaign',
 *                         'John','Smith','John Smith,
 *                          'jsmith@illinois.edu');
 *     if (!($dbservice->status & 1)) { // OK status codes are even
 *         $uid = $dbservice->user_uid;
 *     }
 *
 *     // Later in the code, re-fetch the user using this uid
 *     // and print out the stored attributes.
 *     if (strlen($uid) > 0) {
 *         $dbservice->getUser($uid);
 *         echo 'Name = ' . $dbservice->first_name . ' ' .
 *                          $dbservice->last_name  . "\n";
 *         echo 'DN = '   . $dbservice->distinguished_name . "\n";
 *     }
 *
 *     // For getting/setting the Shibboleth-based IdPs, use the
 *     // getIdps()/setIdps() methods.  These methods utilize the
 *     // class member array $idp_uids for reading/writing. Two
 *     // convenience methods (setIdpsFromKeys($array) and
 *     // setIdpsFromValues($array)) are provided to populate the
 *     // $idp_uids array from the passed-in $array.
 *     $dbservice->getIdps();
 *     foreach($dbservice->idp_uids as $value) {
 *         echo "$value\n";
 *     }
 *
 *     $idps = array('urn:mace:incommon:ucsd.edu',
 *                   'urn:mace:incommon:uiuc.edu');
 *     $dbservice->setIdpsFromValues($idps);
 *     //   --- OR ---
 *     $idps = array('urn:mace:incommon:ucsd.edu' => 1,
 *                   'urn:mace:incommon:uiuc.edu' => 1);
 *     $dbservice->setIdpsFromKeys($idps);
 */

class DBService
{
    /**
     * @var string DEFAULTDBSERVICEURL The main URL for the dbService.
     *      Corresponds to the OAuth 1.0a .war.
     */
    const DEFAULTDBSERVICEURL = 'http://localhost:8080/oauth/dbService';

    /**
     * @var string DEFAULTDBSERVICEURL The new URL for the dbService, to be
     *      used once Jeff has verified all dbService calls work with the
     *      new OAuth 2.0 .war.
     */
    const OAUTH2DBSERVICEURL  = 'http://localhost:8080/oauth2/dbService';

    /**
     * @var array $STATUS The various STATUS_* constants, originally from
     *      Store.pm. The keys of the array are strings corresponding to the
     *      constant names. The values of the array are the integer (hex)
     *      values. For example, DBService::$STATUS['STATUS_OK'] = 0;
     *      Use 'array_search($this->status,DBService::$STATUS)' to look
     *      up the STATUS_* name given the status integer value.
     */
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
        'STATUS_CLIENT_NOT_FOUND'          => 0xFFFFF,
        'STATUS_TRANSACTION_NOT_FOUND'     => 0x10001,
        'STATUS_EPTID_MISMATCH'            => 0x100001,
    );

    /**
     * @var int $status The returned status code from dbService calls
     */
    public $status;

    /**
     * @var string $user_uid The CILogon UID
     */
    public $user_uid;

    /**
     * @var string $remote_user The HTTP session REMOTE_USER
     */
    public $remote_user;

    /**
     * @var string $idp The Identity Provider's entityId
     */
    public $idp;

    /**
     * @var string $idp_display_name The Identity Provider's name
     */
    public $idp_display_name;

    /**
     * @var string $first_name User's given name
     */
    public $first_name;

    /**
     * @var string $last_name User's family name
     */
    public $last_name;

    /**
     * @var string $display_name User's full name
     */
    public $display_name;

    /**
     * @var string $email User's email address
     */
    public $email;

    /**
     * @var string $distinguished_name X.509 DN + email address
     */
    public $distinguished_name;

    /**
     * @var string $eppn eduPersonPrincipalName
     */
    public $eppn;

    /**
     * @var string $eptid eduPersonTargetedID
     */
    public $eptid;

    /**
     * @var string $open_id Old Google OpenID 2.0 identifier
     */
    public $open_id;

    /**
     * @var string $oidc OpenID Connect identifier
     */
    public $oidc;

    /**
     * @var string $affiliation eduPersonScopedAffiliation
     */
    public $affiliation;

    /**
     * @var string $ou Organizational Unit
     */
    public $ou;

    /**
     * @var string $serial_string CILogon serial string (e.g., A34201)
     */
    public $serial_string;

    /**
     * @var string $create_time Time user entry was created
     */
    public $create_time;

    /**
     * @var string $oauth_token OAuth 2.0 token
     */
    public $oauth_token;

    /**
     * @var string $cilogon_callback OAuth 1.0a callback URL
     */
    public $cilogon_callback;

    /**
     * @var string $cilogon_success OAuth 1.0a success URL
     */
    public $cilogon_success;

    /**
     * @var string $cilogon_failure OAuth 1.0a failure URL
     */
    public $cilogon_failure;

    /**
     * @var string $cilogon_portal_name OAuth client name
     */
    public $cilogon_portal_name;

    /**
     * @var string $two_factor Two factor string used by TwoFactor.php
     */
    public $two_factor;

    /**
     * @var string $idp_uids IdPs stored in the 'values' of the array
     */
    public $idp_uids;

    /**
     * @var string $client_name OAuth 2.0 client name
     */
    public $client_name;

    /**
     * @var string $client_id OAuth 2.0 client identifier
     */
    public $client_id;

    /**
     * @var string $client_home_uri OAuth 2.0 client home URL
     */
    public $client_home_uri;

    /**
     * @var string $client_callback_uris An array of OAuth 2.0 callback URLs
     */
    public $client_callback_uris;

    /**
     * @var string $dbservice URL The URL to use for the dbService
     */
    private $dbserviceurl;

    /**
     * __construct
     *
     * Default constructor.  All of the various class members are
     * initialized to 'null' or empty arrays.
     *
     * @param string $serviceurl (Optional) The URL of the database service
     *        servlet
     * @return DBService A new DBService object
     */
    public function __construct($serviceurl = self::DEFAULTDBSERVICEURL)
    {
        $this->clear();
        $this->setDBServiceURL($serviceurl);
    }

    /**
     * getDBServiceURL
     *
     * Returns the full URL of the database servlet used by the call()
     * function.
     *
     * @return string The URL of the database service servlet
     */
    public function getDBServiceURL()
    {
        return $this->dbserviceurl;
    }

    /**
     * setDBServiceURL
     *
     * Set the private variable $dbserviceurl to the full URL of the
     * database servlet, which is used by the call() function.
     *
     * @param string $serviceurl The URL of the database service servlet.
     */
    public function setDBServiceURL($serviceurl)
    {
        $this->dbserviceurl = $serviceurl;
    }

    /**
     * clear
     *
     * Set all of the class members to 'null' or empty arrays.
     */
    public function clear()
    {
        $this->clearUser();
        $this->clearPortal();
        $this->clearIdps();
        $this->clearClient();
    }

    /**
     * clearUser
     *
     * Set all of the class member variables associated with getUser()
     * to 'null'.
     */
    public function clearUser()
    {
        $this->status = null;
        $this->user_uid = null;
        $this->remote_user = null;
        $this->idp = null;
        $this->idp_display_name = null;
        $this->first_name = null;
        $this->last_name = null;
        $this->display_name = null;
        $this->email = null;
        $this->distinguished_name = null;
        $this->serial_string = null;
        $this->create_time = null;
        $this->two_factor = null;
        $this->affiliation = null;
        $this->ou = null;
    }

    /**
     * clearPortal
     *
     * Set all of the class member variables associated with
     * getPortalParameters() to 'null'.
     */
    public function clearPortal()
    {
        $this->status = null;
        $this->oauth_token = null;
        $this->cilogon_callback = null;
        $this->cilogon_success = null;
        $this->cilogon_failure = null;
        $this->cilogon_portal_name = null;
    }

    /**
     * clearIdps
     *
     * Set the class member variable $idp_uids to an empty array.
     */
    public function clearIdps()
    {
        $this->status = null;
        $this->idp_uids = array();
    }

    /**
     * clearClient
     *
     * Set all of the class member variables associated with
     * getClient() to 'null'.
     */
    public function clearClient()
    {
        $this->status = null;
        $this->client_name = null;
        $this->client_id = null;
        $this->client_home_uri = null;
        $this->client_callback_uris = array();
    }

    /**
     * getUser
     *
     * This method calls the 'getUser' action of the servlet and sets
     * the class member variables associated with user info
     * appropriately.  If the servlet returns correctly (i.e. an HTTP
     * status code of 200), this method returns true.
     *
     * @param string $params,... Variable number of parameters: 1, or more.
     *        For 1 parameter : $uid (database user identifier)
     *        For more than 1 parameter, parameters can include:
     *            $remote_user, $idp, $idp_display_name,
     *            $first_name, $last_name, $display_name, $email,
     *            $eppn, $eptid, $openid, $oidc, $affiliation, $ou
     *
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getUser()
    {
        $retval = false;
        $this->clearUser();
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        $numargs = func_num_args();
        if ($numargs == 1) {
            $retval = $this->call('action=getUser&user_uid=' .
                urlencode(func_get_arg(0)));
        } elseif ($numargs > 1) {
            $params = array('remote_user','idp','idp_display_name',
                            'first_name','last_name','display_name','email',
                            'eppn','eptid','open_id','oidc','affiliation','ou');
            $cmd = 'action=getUser';
            for ($i = 0; $i < $numargs; $i++) {
                $arg = func_get_arg($i);
                if (strlen($arg) > 0) {
                    $cmd .= '&' . $params[$i] . '=';
                    // Convert first_name and last_name to UTF-7
                    if (($i == 3) || ($i == 4)) {
                        $cmd .= urlencode(iconv('UTF-8', 'UTF-7', $arg));
                    } else {
                        $cmd .= urlencode($arg);
                    }
                }
            }
            // Add 'us_idp' parameter for InCommon/Google (1) or eduGAIN (0)
            $us_idp = 0;
            $idp = func_get_arg(1);
            $idp_display_name = func_get_arg(2);
            if ((Util::getIdpList()->isRegisteredByInCommon($idp)) ||
                ($idp_display_name == 'Google')) {
                $us_idp = 1;
            }
            $cmd .= "&us_idp=$us_idp";

            $retval = $this->call($cmd);
        }
        return $retval;
    }

    /**
     * getLastArchivedUser
     *
     * This method calls the 'getLastArchivedUser' action of the
     * servlet and sets the class member variables associated with user
     * info appropriately.  If the servlet returns correctly (i.e. an
     * HTTP status code of 200), this method returns true.
     *
     * @param string $uid The database user identifier
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getLastArchivedUser($uid)
    {
        $this->clearUser();
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        return $this->call('action=getLastArchivedUser&user_uid=' .
            urlencode($uid));
    }

    /**
     * removeUser
     *
     * This method calls the 'removeUser' action of the servlet and
     * sets the class member variable $status appropriately.  If the
     * servlet returns correctly (i.e. an HTTP status code of 200),
     * this method returns true.
     *
     * @param string $uid The database user identifier
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function removeUser($uid)
    {
        $this->clearUser();
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        return $this->call('action=removeUser&user_uid=' .
            urlencode($uid));
    }

    /**
     * getTwoFactorInfo
     *
     * This method calls the 'getTwoFactorInfo' action of the servlet
     * and sets the class member variables associated with the user's
     * two-factor info appropriately. If the servlet returns correctly
     * (i.e. an HTTP status code of 200), this method returns true.
     * Note that this method isn't strictly necessary since the
     * two_factor info data is returned when getUser is called.
     *
     * @param string $uid The database user identifier
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getTwoFactorInfo($uid)
    {
        $this->two_factor = null;
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        return $this->call('action=getTwoFactorInfo&user_uid=' .
            urlencode($uid));
    }

    /**
     * setTwoFactorInfo
     *
     * This method calls the 'setTwoFactorInfo' action of the servlet
     * and sets the class member variable associated with the user's
     * two-factor info appropriately. If the servlet returns correctly
     * (i.e. an HTTP status code of 200), this method returns true.
     *
     * @param string $uid The database user identifier
     * @param string $two_factor (Optional) The two-factor info string.
     *        Defaults to empty string.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setTwoFactorInfo($uid, $two_factor = '')
    {
        $this->two_factor = $two_factor;
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        return $this->call('action=setTwoFactorInfo&user_uid=' .
            urlencode($uid) . '&two_factor=' . urlencode($two_factor));
    }

    /**
     * getPortalParameters
     *
     * This method calls the 'getPortalParameter' action of the servlet
     * and sets the class member variables associated with the portal
     * parameters appropriately. If the servlet returns correctly (i.e.
     * an HTTP status code of 200), this method returns true.
     *
     * @param string $oauth_token The database OAuth identifier token
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getPortalParameters($oauth_token)
    {
        $this->clearPortal();
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        return $this->call('action=getPortalParameter&oauth_token=' .
            urlencode($oauth_token));
    }

    /**
     * getIdps
     *
     * This method calls the 'getAllIdps' action of the servlet and
     * sets the class member array $idp_uris to contain all of the
     * Idps in the database, stored in the 'values' of the array.  If
     * the servlet returns correctly (i.e. an HTTP status code of 200),
     * this method returns true.
     *
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getIdps()
    {
        $this->clearIdps();
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        return $this->call('action=getAllIdps');
    }

    /**
     * setIdps
     *
     * This method calls the 'setAllIdps' action of the servlet using
     * the class memeber array $idp_uris as the source for the Idps to
     * be stored to the database.  Note that if this array is empty,
     * an error code will be returned in the status since at least one
     * IdP should be saved to the database.  If you want to pass an
     * array of Idps to be saved, see the setIdpsFromKeys($array) and
     * setIdpsFromValues($array) methods.  If the servlet returns
     * correctly (i.e. an HTTP status code of 200), this method
     * returns true.
     *
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setIdps()
    {
        $retval = false;
        $this->setDBServiceURL(static::DEFAULTDBSERVICEURL);
        $idpcount = count($this->idp_uids);
        $idpidx = 0;
        if ($idpcount > 0) {
            // Loop through the idp_uids in chunks of 50 to deal
            // with query parameter limit of http browsers/servers.
            while ($idpidx < $idpcount) { // Loop through all IdPs
                $fiftyidx = 0;
                $idplist = '';
                while (($fiftyidx < 50) && // Send 50 IdPs at a time
                       ($idpidx < $idpcount)) {
                    $idplist .=  '&idp_uid=' .
                                 urlencode($this->idp_uids[$idpidx]);
                    $fiftyidx++;
                    $idpidx++;
                }
                $cmd = 'action=setAllIdps' . $idplist;
                $retval = $this->call($cmd);
            }
        }
        return $retval;
    }

    /**
     * setIdpsFromKeys
     *
     * This is a convenience method which calls setIdps using a
     * passed-in array of IdPs stored as the keys of the array.  It
     * first sets the class member array $idp_uids appropriately and
     * then calls the setIdps() method. If the servlet returns
     * correctly (i.e. an HTTP status code of 200), this method
     * returns true.  See also setIdpsFromValues().
     *
     * @param array $idps An array of IdPs to be saved, stored in the
     *       'keys' of the array.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setIdpsFromKeys($idps)
    {
        $this->clearIdps();
        foreach ($idps as $key => $value) {
            $this->idp_uids[] = $key;
        }
        return $this->setIdps();
    }

    /**
     * setIdpsFromValues
     *
     * This is a convenience method which calls setIdps using a
     * passed-in array of IdPs stored as the values of the array.  It
     * first sets the class member array $idp_uids appropriately and
     * then calls the setIdps() method. If the servlet returns
     * correctly (i.e. an HTTP status code of 200), this method
     * returns true.  See also setIdpsFromKeys().
     *
     * @param array $idps An array of IdPs to be saved, stored in the
     *        'values' of the array.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setIdpsFromValues($idps)
    {
        $this->clearIdps();
        foreach ($idps as $value) {
            $this->idp_uids[] = $value;
        }
        return $this->setIdps();
    }

    /**
     * getClient
     *
     * This method calls the 'getClient' action of the Oauth 2.0
     * servlet and sets the class member variables associated with
     * client info appropriately.  If the servlet returns correctly
     * (i.e. an HTTP status code of 200), this method returns true.
     *
     * @param string $cid The Oauth 2.0 Client ID (client_id).
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getClient($cid)
    {
        $this->clearClient();
        $this->setDBServiceURL(static::OAUTH2DBSERVICEURL);
        return $this->call('action=getClient&client_id=' .
            urlencode($cid));
    }

    /**
     * setTransactionState
     *
     * This method calls the 'setTransactionState' action of the Oauth
     * 2.0 servlet to associate the Oauth 2.0 'code' with the database
     * user UID. This is necessary for the Oauth 2.0 server to be able
     * to return information about the user (name, email address) as
     * well as return a certificate for the user. If the servlet
     * returns correctly (i.e. an HTTP status code of 200), this method
     * returns true. Check the 'status' return value to verify that
     * the transaction state was set successfully.
     *
     * @param string $code The 'code' as returned by the OAuth 2.0 server.
     * @param string $uid The database user UID.
     * @param int The Unix timestamp of the user authentication.
     * @param string $loa (Optional) The Level of Assurance: '' = basic,
     *        'openid' =  OpenID Connect (e.g., Google),
     *        'http://incommonfederation.org/assurance/silver' = silver
     * @param string $myproxyinfo (Optional) the 'info:...' string to be
     *        passed to MyProxy.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setTransactionState(
        $code,
        $uid,
        $authntime,
        $loa = '',
        $myproxyinfo = ''
    ) {
        $this->setDBServiceURL(static::OAUTH2DBSERVICEURL);
        return $this->call(
            'action=setTransactionState' .
            '&code=' . urlencode($code) .
            '&user_uid=' . urlencode($uid) .
            '&auth_time=' . urlencode($authntime) .
            '&loa=' . urlencode($loa) .
            ((strlen($myproxyinfo) > 0) ?
                ('&cilogon_info=' . urlencode($myproxyinfo)) : '')
        );
    }

    /**
     * call
     *
     * This method does the brunt of the work for calling the
     * dbService servlet.  The single parameter is a string of
     * 'key1=value1&key2=value2&...' containing all of the parameters
     * for the dbService.  If the servlet returns an HTTP status code
     * of 200, then this method will return true.  It parses the return
     * output for various 'key=value' lines and stores then in the
     * appropriate member variables, urldecoded of course.
     *
     * @param string $params A string containing 'key=value' pairs,
     *        separated by ampersands ('&') as appropriate for passing to a
     *        URL for a GET query.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function call($params)
    {
        $success = false;

        $ch = curl_init();
        if ($ch !== false) {
            $url = $this->getDBServiceURL() . '?' . $params;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $output = curl_exec($ch);
            if (curl_errno($ch)) { // Send alert on curl errors
                Util::sendErrorAlert(
                    'cUrl Error',
                    'cUrl Error    = ' . curl_error($ch) . "\n" .
                    "URL Accessed  = $url"
                );
            }
            if (!empty($output)) {
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpcode == 200) {
                    $success = true;
                    if (preg_match('/status=([^\r\n]+)/', $output, $match)) {
                        $this->status = urldecode($match[1]);
                    }
                    if (preg_match('/user_uid=([^\r\n]+)/', $output, $match)) {
                        $this->user_uid = urldecode($match[1]);
                    }
                    if (preg_match('/remote_user=([^\r\n]+)/', $output, $match)) {
                        $this->remote_user = urldecode($match[1]);
                    }
                    if (preg_match('/idp=([^\r\n]+)/', $output, $match)) {
                        $this->idp = urldecode($match[1]);
                    }
                    if (preg_match('/idp_display_name=([^\r\n]+)/', $output, $match)) {
                        $this->idp_display_name = urldecode($match[1]);
                    }
                    if (preg_match('/first_name=([^\r\n]+)/', $output, $match)) {
                        $this->first_name = urldecode($match[1]);
                    }
                    if (preg_match('/last_name=([^\r\n]+)/', $output, $match)) {
                        $this->last_name = urldecode($match[1]);
                    }
                    if (preg_match('/display_name=([^\r\n]+)/', $output, $match)) {
                        $this->display_name = urldecode($match[1]);
                    }
                    if (preg_match('/email=([^\r\n]+)/', $output, $match)) {
                        $this->email = urldecode($match[1]);
                    }
                    if (preg_match('/distinguished_name=([^\r\n]+)/', $output, $match)) {
                        $this->distinguished_name = urldecode($match[1]);
                    }
                    if (preg_match('/eppn=([^\r\n]+)/', $output, $match)) {
                        $this->eppn = urldecode($match[1]);
                    }
                    if (preg_match('/eptid=([^\r\n]+)/', $output, $match)) {
                        $this->eptid = urldecode($match[1]);
                    }
                    if (preg_match('/open_id=([^\r\n]+)/', $output, $match)) {
                        $this->open_id = urldecode($match[1]);
                    }
                    if (preg_match('/oidc=([^\r\n]+)/', $output, $match)) {
                        $this->oidc = urldecode($match[1]);
                    }
                    if (preg_match('/affiliation=([^\r\n]+)/', $output, $match)) {
                        $this->affiliation = urldecode($match[1]);
                    }
                    if (preg_match('/ou=([^\r\n]+)/', $output, $match)) {
                        $this->ou = urldecode($match[1]);
                    }
                    if (preg_match('/serial_string=([^\r\n]+)/', $output, $match)) {
                        $this->serial_string = urldecode($match[1]);
                    }
                    if (preg_match('/create_time=([^\r\n]+)/', $output, $match)) {
                        $this->create_time = urldecode($match[1]);
                    }
                    if (preg_match('/oauth_token=([^\r\n]+)/', $output, $match)) {
                        $this->oauth_token = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_callback=([^\r\n]+)/', $output, $match)) {
                        $this->cilogon_callback = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_success=([^\r\n]+)/', $output, $match)) {
                        $this->cilogon_success = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_failure=([^\r\n]+)/', $output, $match)) {
                        $this->cilogon_failure = urldecode($match[1]);
                    }
                    if (preg_match('/cilogon_portal_name=([^\r\n]+)/', $output, $match)) {
                        $this->cilogon_portal_name = urldecode($match[1]);
                    }
                    if (preg_match('/two_factor=([^\r\n]+)/', $output, $match)) {
                        $this->two_factor = urldecode($match[1]);
                    }
                    if (preg_match_all('/idp_uid=([^\r\n]+)/', $output, $match)) {
                        foreach ($match[1] as $value) {
                            $this->idp_uids[] = urldecode($value);
                        }
                    }
                    if (preg_match('/client_name=([^\r\n]+)/', $output, $match)) {
                        $this->client_name = urldecode($match[1]);
                    }
                    if (preg_match('/client_id=([^\r\n]+)/', $output, $match)) {
                        $this->client_id = urldecode($match[1]);
                    }
                    if (preg_match('/client_home_uri=([^\r\n]+)/', $output, $match)) {
                        $this->client_home_uri = urldecode($match[1]);
                    }
                    if (preg_match('/client_callback_uris=([^\r\n]+)/', $output, $match)) {
                        $this->client_callback_uris = explode(urldecode($match[1]), ',');
                    }
                }
            }
            curl_close($ch);
        }
        return $success;
    }

    /**
     * dump
     *
     * This is a convenience method which prints out all of the
     * non-null / non-empty member variables to stdout.
     */
    public function dump()
    {
        if (!is_null($this->status)) {
            echo "status=$this->status (" .
            array_search($this->status, static::$STATUS) . ")\n";
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
        if (!is_null($this->display_name)) {
            echo "display_name=$this->display_name\n";
        }
        if (!is_null($this->email)) {
            echo "email=$this->email\n";
        }
        if (!is_null($this->distinguished_name)) {
            echo "distinguished_name=$this->distinguished_name\n";
        }
        if (!is_null($this->eppn)) {
            echo "eppn=$this->eppn\n";
        }
        if (!is_null($this->eptid)) {
            echo "eptid=$this->eptid\n";
        }
        if (!is_null($this->open_id)) {
            echo "open_id=$this->open_id\n";
        }
        if (!is_null($this->oidc)) {
            echo "oidc=$this->oidc\n";
        }
        if (!is_null($this->affiliation)) {
            echo "affiliation=$this->affiliation\n";
        }
        if (!is_null($this->ou)) {
            echo "ou=$this->ou\n";
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
        if (!is_null($this->two_factor)) {
            echo "two_factor=$this->two_factor\n";
        }
        if (count($this->idp_uids) > 0) {
            uasort($this->idp_uids, 'strcasecmp');
            echo "idp_uids={\n";
            foreach ($this->idp_uids as $value) {
                echo "    $value\n";
            }
            echo "}\n";
        }
        if (!is_null($this->client_name)) {
            echo "client_name=$this->client_name\n";
        }
        if (!is_null($this->client_id)) {
            echo "client_id=$this->client_id\n";
        }
        if (!is_null($this->client_home_uri)) {
            echo "client_home_uri=$this->client_home_uri\n";
        }
        if (count($this->client_callback_uris) > 0) {
            uasort($this->client_callback_uris, 'strcasecmp');
            echo "client_callback_uris={\n";
            foreach ($this->client_callback_uris as $value) {
                echo "    $value\n";
            }
            echo "}\n";
        }
    }
}
