<?php

require_once('DB.php');

/************************************************************************
 * Class name : dbprops                                                 *
 * Description: This class reads the config file (as specified by the   *
 *              class constant cilogon_ini_file) for the username,      *
 *              password, and database name used for storage.           *
 *              You specify which database type to use when calling     *
 *              the constructor, either 'mysql' or 'pgsql'. There is    *
 *              also a method getDBConnect to open a PEAR DB database   *
 *              connection using the parameters in the config file.     *
 *                                                                      *
 *              There is a constant in the class that you should set    *
 *              for your particular set up:                             *
 *                                                                      *
 *              cilogon_ini_file - this is the full path and name       *
 *                  of the cilogon.ini file containing the database     *
 *                  parameters (such as username and password). The     *
 *                  designated database is used by the OpenID consumer  *
 *                  to store temporary tokens.                          *
 ************************************************************************/

class dbprops {

    /* Set the constant to correspond to your particular set up.       */
    const cilogon_ini_file = '/var/www/config/cilogon.ini';

    private $ini_array; // Read .ini file into an array
    private $dbtype;    // Either 'mysql' or 'pgsql'

    /********************************************************************
     * Function  : __construct                                          *
     * Parameter : Database type, either 'mysql' or 'pgsql'. Defaults   *
     *             to 'mysql'.                                          *
     * The constuctor sets several class variables and reads the        *
     * cilogon_ini_file config file into the array $ini_array.          *
     ********************************************************************/
    function __construct($db='mysql') {
        $this->dbtype = $db;
        $this->ini_array = @parse_ini_file(self::cilogon_ini_file);
    }

    /********************************************************************
     * Function  : queryAttribute                                       *
     * Parameter : Name of the attribute to query, one of 'username',   *
     *             'password', or 'database'.                           *
     * Returns   : The value of the desired attribute, or empty string  *
     *             on error.                                            *
     * This is a general method looks in the $ini_array for the named   *
     * attribute to return the username, password, or database value.   *
     ********************************************************************/
    function queryAttribute($attr) {
        $retstr = '';

        if (is_array($this->ini_array)) {
            $query = $this->dbtype . '.' . $attr;
            if (array_key_exists($query,$this->ini_array)) {
                $retstr = $this->ini_array[$query];
            }
        }

        return $retstr;
    }

    /********************************************************************
     * Function  : getUsername                                          *
     * Returns   : The username for the selected database type.         *
     * This is a convenience method which calls queryAttribute to get   *
     * the database username.                                           *
     ********************************************************************/
    function getUsername() {
        return $this->queryAttribute('username');
    }

    /********************************************************************
     * Function  : getPassword                                          *
     * Returns   : The password for the selected database type.         *
     * This is a convenience method which calls queryAttribute to get   *
     * the database password.                                           *
     ********************************************************************/
    function getPassword() {
        return $this->queryAttribute('password');
    }

    /********************************************************************
     * Function  : getDatabase                                          *
     * Returns   : The database name for the selected database type.    *
     * This is a convenience method which calls queryAttribute to get   *
     * the database name.                                               *
     ********************************************************************/
    function getDatabase() {
        return $this->queryAttribute('database');
    }

    /********************************************************************
     * Function  : getHostspec                                          *
     * Returns   : The hostspec type for the selected database type.    *
     *             Defaults to 'localhost'.                             *
     * This is a convenience method which calls queryAttribute to get   *
     * the hostspec, i.e., "host:port" to connect to. If the hostspec   *
     * has not been configured in cilogon_ini_file, return 'localhost'. *
     ********************************************************************/
    function getHostspec() {
        $hostspec = $this->queryAttribute('hostspec');
        if (strlen($hostspec) == 0) {
            $hostspec = 'localhost';
        }
        return $hostspec;
    }

    /********************************************************************
     * Function  : getDBConnect                                         *
     * Returns   : A PEAR DB object connected to a database, or null    *
     *             on error connecting to database.                     *
     * This function uses the PEAR DB module to connect to database     *
     * using the parameters found in the cilogon_ini_file. Upon       *
     * success, it returns a DB connection returned by "DB::connect"    *
     * suitable for future DB calls. If there is a problem connecting,  *
     * it returns null.                                                 *
     ********************************************************************/
    function getDBConnect() {
        $retval = null;

        $dsn = array(
            'phptype'  => $this->dbtype,
            'username' => $this->getUsername(),
            'password' => $this->getPassword(),
            'database' => $this->getDatabase(),
            'hostspec' => $this->getHostspec()
        );

        $opts = array(
            'persistent'  => true,
            'portability' => DB_PORTABILITY_ALL
        );

        $retval =& DB::connect($dsn,$opts);
        if (PEAR::isError($retval)) {
            $retval = null;
        }

        return $retval;
    }

}

?>
