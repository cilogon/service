<?php

if (include_once('DB.php')) {
    include_once('Auth/OpenID/PostgreSQLStore.php');
    include_once('Auth/OpenID/MySQLStore.php');
}
require_once('Auth/OpenID/FileStore.php');
require_once('dbprops.php');


/************************************************************************
 * Class name : openid                                                  *
 * Description: This class serves two purposes:                         *
 *              (1) It maintains a list of OpenID provider URLs and     *
 *                  their corresponding display names.  The list of     *
 *                  OpenID providers is stored in the static array      *
 *                  $providerUrls.  There are static methods for        *
 *                  looking in this array for a given OpenID provider   *
 *                  by URL or by display name.                          *
 *              (2) It gets "storage" for the OpenID library. This      *
 *                  file-based or database-based storage is needed by   *
 *                  the Janrain OpenID library for OpenID connections.  *
 *                                                                      *
 *              There is a constant in the class that you should set    *
 *              for your particular set up:                             *
 *                                                                      *
 *              fileStoreDirectory - the full path to the apache-owned  *
 *                  directory for storing OpenID information to be used *
 *                  by the "FileStore" module. Note that this directory *
 *                  must exist and be owner (or group) 'apache'.        *
 *              cilogon_ini_file - the full path to the php.ini-style   *
 *                  configuration file for the CILogon Service.         *
 ************************************************************************/

class openid {

    /* Set the constants to correspond to your particular set up.       */
    const fileStoreDirectory = '/var/run/openid';
    const cilogon_ini_file   = '/var/www/config/cilogon.ini';

    /* The $providerUrls array is a list of all supported OpenID IdP    *
     * URLs (as the keys) and their associated display names (as the    *
     * values). This key=>value ordering was chosen to align with the   *
     * InCommon IdP=>DisplayName whiteids.txt file. Note that only      *
     * OpenID providers which do NOT require a username in the URL are  *
     * supported at this time.                                          */
    public static $providerUrls = array(
        'https://www.google.com/accounts/o8/id'     => 'Google' ,
        // 'https://accounts.google.com/o/oauth2/auth' => 'Google+' ,
        'https://openid.paypal-ids.com'             => 'PayPal' , 
        'https://pip.verisignlabs.com'              => 'Verisign' ,
        // 'https://yahoo.com'                      => 'Yahoo'
    );

    /* Database connection for the OpenID library.                      */
    protected $db = null;
    /* Type of storage to use for OpenID state. null => filesystem      */
    protected $storage = null;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new openid object.                                 *
     * Default constructor.  This method reads in the value of          *
     * storage.openid from the cilogon.ini configuration file.          *
     ********************************************************************/
    function __construct() {
        $ini_array = @parse_ini_file(self::cilogon_ini_file);
        if ((is_array($ini_array)) &&
            (array_key_exists('storage.openid',$ini_array))) {
            $this->storage = $ini_array['storage.openid'];
        }
    }

    /********************************************************************
     * Function  : __destruct                                           *
     * Default destructor.  Closes the database connection.             *
     ********************************************************************/
    function __destruct() {
        $this->disconnect();
    }

    /********************************************************************
     * Function  : getProviderUrl                                       *
     * Parameter : The display name of an OpenID provider.              *
     * Returns   : The corresponding OpenID provider URL, or empty      *
     *             string if no such URL is found.                      *
     * This method takes in an OpenID display name and returns the      *
     * corresponding URL.                                               *
     ********************************************************************/
    public static function getProviderUrl($name) {
        $retval = '';
        $url = array_search($name,self::$providerUrls);
        if (($url !== false) && (strlen($url) > 0)) {
            $retval = $url;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getProviderName                                      *
     * Parameter : The URL of an OpenID provider.                       *
     * Returns   : The corresponding OpenID provider display name, or   *
     *             empty string if no such display name is found.       *
     * This method takes in an OpenID provider URL and returns the      *
     * corresponding display name.                                      *
     ********************************************************************/
    public static function getProviderName($url) {
        $retval = '';
        if (isset(self::$providerUrls[$url])) {
            $retval = self::$providerUrls[$url];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : urlExists                                            *
     * Parameter : The URL of an OpenID provider.                       *
     * Returns   : True if the URL exists in the list of OpenID         *
     *             providers.  False otherwise.                         *
     * This is a convenience method which returns true if the passed-in *
     * URL exists in the list of OpenID providers.                      *
     ********************************************************************/
    public static function urlExists($url) {
        return (strlen(self::getProviderName($url)) > 0);
    }

    /********************************************************************
     * Function  : isGoogleOAuth2                                       *
     * Parameter : The URL of an OpenID provider.                       *
     * Returns   : True if the provider name corresponding to the URL   *
     *             is 'Google+'. False otherwise.                       *
     * This is a convenience method which returns true if the passed-in *
     * URL is the one used by Google OAUth2 authentication.             *
     ********************************************************************/
    public static function isGoogleOAuth2($url) {
        return (self::getProviderName($url) == 'Google+');
    }

    /********************************************************************
     * Function  : nameExists                                           *
     * Parameter : The display name of an OpenID provider.              *
     * Returns   : True if the name exists in the list of OpenID        *
     *             providers.  False otherwise.                         *
     * This is a convenience method which returns true if the passed-in *
     * display name exists in the list of OpenID providers.             *
     ********************************************************************/
    public static function nameExists($name) {
        return (strlen(self::getProviderUrl($name)) > 0);
    }

    /********************************************************************
     * Function  : disconnect                                           *
     * This method closes the database connection utilized by the       *
     * OpenID toolkit.  It should be called after getStorage() and the  *
     * associated usage of that storage.                                *
     ********************************************************************/
    function disconnect() {
        if (!is_null($this->db)) {
            $this->db->disconnect();
            $this->db = null;
        }
    }

    /********************************************************************
     * Function  : getPostgrSQLStorage                                  *
     * Returns   : A new Auth_OpenID_PostgreSQLStore object for use by  *
     *             an Auth_OpenID_Consumer.                             *
     * This method connects to a PostgreSQL database in order to store  *
     * the temporary OpenID tokens used by an Auth_OpenID_Consumer.  It *
     * reads database parameters from a local configuration file        *
     * and tries to open a connection to the PostgreSQL database.  If   *
     * successful, it creates a new Auth_OpenID_PostgreSQLStore to be   *
     * returned for use by an Auth_OpenID_Consumer.                     *
     ********************************************************************/
    function getPostgreSQLStorage() {
        return $this->getDBStorage('pgsql');
    }

    /********************************************************************
     * Function  : getMySQLStorage                                      *
     * Returns   : A new Auth_OpenID_MySQLStore object for use by       *
     *             an Auth_OpenID_Consumer.                             *
     * This method connects to a MySQL database in order to store the   *
     * temporary OpenID tokens used by an Auth_OpenID_Consumer.  It     *
     * reads database parameters from a local configuration file        *
     * and tries to open a connection to the MySQL database.  If        *
     * successful, it creates a new Auth_OpenID_MySQLStore to be        *
     * returned for use by an Auth_OpenID_Consumer.                     *
     ********************************************************************/
    function getMySQLStorage() {
        return $this->getDBStorage('mysql');
    }

    /********************************************************************
     * Function  : getDBStorage                                         *
     * Parameter : Database type. One of 'pgsql' or 'mysql'.            *
     * Returns   : A new Auth_OpenID_PostgreSQL or                      *
     *             Auth_OpenID_MySQLStore object for use by an          *
     *             Auth_OpenID_Consumer.                                *
     * This method connects to a database in order to store the         *
     * temporary OpenID tokens used by an Auth_OpenID_Consumer.  It     *
     * reads database parameters from a local configuration file        *
     * and tries to open a connection to the specified database, either *
     * PostgreSQL (pgsql) or MySQL (mysql).  If successful, it creates  *
     * a new Auth_OpenID_PostgreSQL or Auth_OpenID_MySQLStore to be     *
     * returned for use by an Auth_OpenID_Consumer.                     *
     ********************************************************************/
    function getDBStorage($dbtype) {
        $retval = null;

        $this->disconnect();  // Close any previous database connection

        $dbprops = new dbprops($dbtype);
        $this->db = $dbprops->getDBConnect();

        if (!is_null($this->db)) {
            if ($dbtype == 'pgsql') {
                $retval =& new Auth_OpenID_PostgreSQLStore($this->db);
            } elseif ($dbtype == 'mysql') {
                $retval =& new Auth_OpenID_MySQLStore($this->db);
            }
            // Create tables only needed for new installations
            // $retval->createTables();
        }

        return $retval;
    }

    /********************************************************************
     * Function  : getFileStorage                                       *
     * Returns   : A new Auth_OpenID_FileStore object for use by        *
     *             an Auth_OpenID_Consumer.                             *
     * This method returns a new FileStore using the directory constant *
     * 'fileStoreDirectory'.  This can be used by an                    *
     * Auth_OpenID_Consumer.                                            *
     ********************************************************************/
    function getFileStorage() {
        $retval = null;

        if (file_exists(self::fileStoreDirectory)) {
            $retval =& new Auth_OpenID_FileStore(self::fileStoreDirectory);
        }

        return $retval;
    }

    /********************************************************************
     * Function  : getStorage                                           *
     * Parameter : Storage type, one of 'pgsql', 'mysql', or 'file'.    *
     *             Defaults to null, which means use the value of       *
     *             storage.openid from the cilogon.ini config file,     *
     *             or 'file' if no such parameter configured.           *
     * Returns   : A new Auth_OpenID_FileStore,                         *
     *             Auth_OpenID_PostgreSQLStore, or                      *
                   Auth_OpenID_MySQLStore object for use by an          *
     *             Auth_OpenID_Consumer.                                *
     * This method calls one of getMySQLStorage(),                      *
     * getPostgreSQLStorage() or  getFileStoreage() and returns a       *
     * store suitable for use by an Auth_OpenID_Consumer.  If you       *
     * don't pass in a parameter, the value of storage.openid from      *
     * from the cilogon_ini_file will be used. If that value is also    *
     * null, then default to 'file'.                                    *
     ********************************************************************/
    function getStorage($storetype=null) {
        $storage = null;

        // No parameter given? Use the value read in from cilogon_ini_file.
        if (is_null($storetype)) {
            $storetype = $this->storage;
        }

        if ($storetype == 'pgsql') {
            $storage = $this->getPostgreSQLStorage();
        } elseif ($storetype == 'mysql') {
            $storage = $this->getMySQLStorage();
        } else {
            $storage = $this->getFileStorage();
        }

        return $storage;
    }
}

?>
