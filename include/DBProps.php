<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use PEAR;
use DB;

/**
 * DBProps
 *
 * This class reads the config.ini file for the username, password,
 * and database name used for storage.  You specify which database type
 * to use when calling the constructor, either 'mysql' or 'pgsql'. There
 * is also a method getDBConnect to open a PEAR DB database connection
 * using the parameters in the config file.
 */
class DBProps
{
    /**
     * @var string $dbtype Either 'mysql' or 'pgsql'
     */
    private $dbtype;

    /**
     * __construct
     *
     * The constuctor sets the $dbtype class variable.
     *
     * @param string $db Database type, either 'mysql' or 'pgsql'.
     *        Defaults to 'mysql'.
     */
    public function __construct($db = 'mysql')
    {
        $this->dbtype = $db;
    }

    /**
     * queryAttribute
     *
     * This is a general method looks in the cilogon.ini file for the
     * named  database configuration attribute.
     *
     * @param string $attr Name of the attribute to query, one of
     *        'username', 'password', or 'database'.
     * @return string The value of the desired attribute, or empty string
     *         on error.
     */
    public function queryAttribute($attr)
    {
        return Util::getConfigVar($this->dbtype . '.' . $attr);
    }

    /**
     * getUsername
     *
     * This is a convenience method which calls queryAttribute to get
     * the database username.
     *
     * @return string The username for the selected database type.
     */
    public function getUsername()
    {
        return $this->queryAttribute('username');
    }

    /**
     * getPassword
     *
     * This is a convenience method which calls queryAttribute to get
     * the database password.
     *
     * @return string The password for the selected database type.
     */
    public function getPassword()
    {
        return $this->queryAttribute('password');
    }

    /**
     * getDatabase
     *
     * This is a convenience method which calls queryAttribute to get
     * the database name.
     *
     * @return string The database name for the selected database type.
     */
    public function getDatabase()
    {
        return $this->queryAttribute('database');
    }

    /**
     * getHostspec
     *
     * This is a convenience method which calls queryAttribute to get
     * the hostspec, i.e., 'host:port' to connect to. If the hostspec
     * has not been configured in cilogon_ini_file, return 'localhost'.
     *
     * @return string The hostspec type for the selected database type.
     *         Defaults to 'localhost'.
     */
    public function getHostspec()
    {
        $hostspec = $this->queryAttribute('hostspec');
        if (strlen($hostspec) == 0) {
            $hostspec = 'localhost';
        }
        return $hostspec;
    }

    /**
     * getDBConnect
     *
     * This function uses the PEAR DB module to connect to database
     * using the parameters found in the cilogon_ini_file. Upon
     * success, it returns a DB connection returned by 'DB::connect'
     * suitable for future DB calls. If there is a problem connecting,
     * it returns null.
     *
     * @return DB A PEAR DB object connected to a database, or null
     *         on error connecting to database.
     */
    public function getDBConnect()
    {
        $retval = null;

        $db = new DB();
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

        $retval = DB::connect($dsn, $opts);
        if (PEAR::isError($retval)) {
            $retval = null;
        }

        return $retval;
    }
}
