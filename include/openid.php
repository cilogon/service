<?php

if (include_once('DB.php')) {
    include_once('Auth/OpenID/PostgreSQLStore.php');
}
require_once('Auth/OpenID/FileStore.php');


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
 *              There are constants in the class that you should set    *
 *              for your particular set up:                             *
 *                                                                      *
 *              databasePropertiesFile - this is the full path and name *
 *                  of the database.properties file utilized to connect *
 *                  to the PostgreSQL database.  It is used by the      *
 *                  OpenID consumer to store temporary tokens.          *
 *              fileStoreDirectory - the full path to the apache-owned  *
 *                  directory for storing OpenID information to be used *
 *                  by the "FileStore" module. Note that this directory *
 *                  must exist and be owner (or group) 'apache'.        *
 ************************************************************************/

class openid {

    /* Set the constants to correspond to your particular set up.       */
    const databasePropertiesFile = '/var/www/config/database.properties';
    const fileStoreDirectory     = '/var/run/openid';

    /* The $providerUrls array is a list of all supported OpenID IdP    *
     * URLs (as the keys) and their associated display names (as the    *
     * values). This key=>value ordering was chosen to align with the   *
     * InCommon IdP=>DisplayName whiteids.txt file. Note that only      *
     * OpenID providers which do NOT require a username in the URL are  *
     * supported at this time.                                          */
    public static $providerUrls = array(
        'http://google.com/accounts/o8/id' => 'Google' ,
        'http://pip.verisignlabs.com'      => 'Verisign' ,
        'http://yahoo.com'                 => 'Yahoo'
    );

    /* Database connection for the OpenID library.                      */
    protected $db;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new openid object.                                 *
     * Default constructor.  This method initializes the storage needed *
     * by the OpenID consumer library.                                  *
     ********************************************************************/
    function __construct() {
        $this->db = null;
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
        if ($this->db != null) {
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
     * reads database parameters from a local database.properties file  *
     * and tries to open a connection to the PostgreSQL database.  If   *
     * successful, it creates a new Auth_OpenID_PostgreSQLStore to be   *
     * returned for use by an Auth_OpenID_Consumer.                     *
     ********************************************************************/
    function getPostgreSQLStorage() {
        $retval = null;
        
        $this->disconnect();  // Close any previous database connection

        $props = parse_ini_file(self::databasePropertiesFile);
        if ($props !== false) {
            $dsn = array(
                'phptype'  => 'pgsql',
                'username' => $props['org.cilogon.database.userName'],
                'password' => $props['org.cilogon.database.password'],
                'hostspec' => $props['org.cilogon.database.host'],
                'database' => $props['org.cilogon.database.databaseName']
            );
            $this->db =& DB::connect($dsn);
            if (PEAR::isError($this->db)) {
                $this->db = null;
            }
        }

        if ($this->db != null) {
            $retval =& new Auth_OpenID_PostgreSQLStore($this->db);
            $retval->createTables();
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
     * Returns   : A new Auth_OpenID_FileStore or                       *
     *             Auth_OpenID_PostgreSQLStore object for use by an     *
     *             Auth_OpenID_Consumer.                                *
     * This method points to either getPostgreSQLStorage() or           *
     * getFileStoreage() and returns a store suitable for use by a      *
     * Auth_OpenID_Consumer. 
     ********************************************************************/
    function getStorage() {
        return $this->getPostgreSQLStorage();
    }
}

?>
