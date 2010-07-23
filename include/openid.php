<?php

if (include_once('DB.php')) {
    include_once('Auth/OpenID/PostgreSQLStore.php');
}
require_once('Auth/OpenID/FileStore.php');


/************************************************************************
 * Class name : openid                                                  *
 * Description: This class aids in the formation of OpenID user URLs.   *
 *              There is a public array $providerarray which lists      *
 *              the available OpenID Providers (as the keys of the      *
 *              array) and their corresponding URLs (as the values of   *
 *              the array).  If the URL has the string 'username' in    *
 *              it, this must be replaced by the user's actual account  *
 *              username.  This substitution is done automatically by   *
 *              the getURL() method.                                    *
 *                                                                      *
 *              There are constants in the class that you should set    *
 *              for your particular set up:                             *
 *                                                                      *
 *              databasePropertiesFile - this is the full path and name *
 *                  of the database.properties file utilized to connect *
 *                  to the PostgreSQL database.  It is used by the      *
 *                  OpenID consumer to store temporary tokens.          *
                fileStoreDirectory - the full path to the apache-owned  *
                    directory for storing OpenID information to be used *
                    by the "FileStore" module. Note that this directory *
                    must exist and be owner (or group) 'apache'.        *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('openid.php');                                       *
 *    $openid = new openid();                                           *
 *    $openid->setProvider('Blogger');                                  *
 *    $openid->setUsername('johndoe');                                  *
 *    // OR simply $openid = new openid('Blogger','johndoe');           *
 *    $url = $openid->getURL();                                         *
 *    // Now do an OpenID login using the $url                          *
 ************************************************************************/

class openid {

    /* Set the constants to correspond to your particular set up.       */
    const databasePropertiesFile = '/var/www/config/database.properties';
    const fileStoreDirectory = '/var/run/openid';

    /* The $providerarray lists all of the supported OpenID providers   *
     * as the keys and their corresponding URLs as the values.  If      *
     * there is the string 'username' in the URL, it needs to be        *
     * replaced  by the actual user's username for the URL to be valid. */
    public $providerarray = array(
        'AOL'         => 'http://openid.aol.com' ,
        'Blogger'     => 'http://username.blogspot.com' ,
        'certifi.ca'  => 'http://certifi.ca/username' ,
        'Chi.mp'      => 'http://username.mp' ,
        'clavid'      => 'http://username.clavid.com' ,
        'Flickr'      => 'http://flickr.com/photos/username' ,
        'GetOpenID'   => 'http://getopenid.com/username' ,
        'Google'      => 'http://google.com/accounts/o8/id' ,
        'Hyves'       => 'http://hyves.nl' ,
        'LaunchPad'   => 'http://login.launchpad.net' ,
        'LiquidID'    => 'http://username.liquidid.net' ,
        'LiveJournal' => 'http://username.livejournal.com' ,
        'myID'        => 'http://myid.net' ,
        'myOpenID'    => 'http://myopenid.com' ,
        'MySpace'     => 'http://myspace.com' ,
        'myVidoop'    => 'http://myvidoop.com' ,
        'NetLog'      => 'http://netlog.com/username' ,
        'OneLogin'    => 'https://app.onelogin.com/openid/username' ,
        'OpenID'      => 'http://username' ,
        'Verisign'    => 'http://pip.verisignlabs.com' ,
        'Vox'         => 'http://username.vox.com' ,
        'WordPress'   => 'http://username.wordpress.com' ,
        'Yahoo'       => 'http://yahoo.com' ,
        'Yiid'        => 'http://yiid.com'
    );

    /* The actual OpenID provider to be used.  Corresponds to one of    *
     * keys in the $providerarray.                                      */
    protected $provider;

    /* If the OpenID Provider URL has the string 'username' in it, it   *
     * must be replaced with the actual user's account username.        */
    protected $username;

    protected $db = null;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameters: (1) The name of the OpenID provider, corresponding   *
     *                 to one of the keys in the $providerarray.        *
     *                 Defaults to 'OpenID'.                            *
     *             (2) The user's account username to be utilized when  *
     *                 forming the OpenID URL containing the string     *
     *                 'username'.  Defaults to 'username'.             *
     * Returns   : A new openid object.                                 *
     * Default constructor.  This mehthod sets the $provider and        *
     * $username to the passed in values.  These can be (re)set after   *
     * ojbect creation by the setProvider() and setUsername() methods.  *
     ********************************************************************/
    function __construct($provider='OpenID',$username='username') {
        $this->setProvider($provider);
        $this->setUsername($username);
    }

    /********************************************************************
     * Function  : getProvider                                          *
     * Returns   : The OpenID provider to be utilized.                  *
     * This method returns the class variable $provider.                *
     ********************************************************************/
    function getProvider() {
        return $this->provider;
    }

    /********************************************************************
     * Function  : setProvider                                          *
     * Parameter : The OpenID provider to be utilized.                  *
     * This method sets the class variable $provider to the passed-in   *
     * OpenID provider.  It first checks to see if the parameter is a   *
     * key in the $providerarray.  If so, it sets $provider.  Otherwise *
     * no action is taken.                                              *
     ********************************************************************/
    function setProvider($provider) {
        if ($this->exists($provider)) {
            $this->provider = $provider;
        }
    }

    /********************************************************************
     * Function  : getUsername                                          *
     * Returns   : The user's account username.                         *
     * This method returns the class variable $username.                *
     ********************************************************************/
    function getUsername() {
        return $this->username;
    }

    /********************************************************************
     * Function  : setUsername                                          *
     * Parameter : The username to be put in the OpenID URL.            *
     * This method sets the class variable $username.                   *
     ********************************************************************/
    function setUsername($username) {
        $username = trim($username);
        $this->username = $username;
    }

    /********************************************************************
     * Function  : exists                                               *
     * Parameter : The name of an OpenID Provider.                      *
     * Returns   : True if the OpenID provider exists in the            *
     *             $providerarray.  False otherwise.                    *
     * This method takes a string for an OpenID provider.  The          *
     * $providerarray is searched for this string.  If found, true is   *
     * returned.                                                        *
     ********************************************************************/
    function exists($provider) {
        return isset($this->providerarray[$provider]);
    }

    /********************************************************************
     * Function  : getURL                                               *
     * Returns   : The OpenID URL to be used for an OpenID login.       *
     * This method returns the URL to be used for an OpenID login.  If  *
     * the URL in the $providerarray contains the string 'username',    *
     * the class variable $username is substituted, giving a user-      *
     * specific URL.  Otherwise, the URL is simply returned without any *
     * string replacement.                                              *
     ********************************************************************/
    function getURL() {
        $url = $this->providerarray[$this->getProvider()];
        return preg_replace('/username/',$this->username,$url);
    }

    /********************************************************************
     * Function  : getInputTextURL                                      *
     * Returns   : The OpenID URL to be used for an OpenID login,       *
     *             replacing any 'username' string with an <input       *
     *             type="text"> form element.                           *
     * This method is similar to getURL, but rather than simply         *
     * replacing any 'username' string with the class variable          *
     * $username, a full <input type="text"> form element is inserted.  *
     * This is useful when outputting the OpenID logon form.            *
     ********************************************************************/
    function getInputTextURL() {
        $url = $this->providerarray[$this->getProvider()];
        $len = strlen($this->username) + 1;
        if ($len > 20) {
            $len = 20;
        }
        return preg_replace('/username/',
               '<input type="text" name="username" size="' . $len . '" '.
               'value="' .  $this->username . '" id="openidusername" ' .
               'onfocus="setInterval(\'boxExpand()\',1);" />',
               $url);
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
