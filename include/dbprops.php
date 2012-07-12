<?php

/************************************************************************
 * Class name : dbprops                                                 *
 * Description: This class reads the database config file (as specified *
 *              by the class constant databaseConfigFile) for the       *
 *              username, password, and database name used for storage. *
 *              You specify which database type to use when calling     *
 *              the constructor, either 'mysql' or 'pgsql'.             *
 *                                                                      *
 *              There is a constant in the class that you should set    *
 *              for your particular set up:                             *
 *                                                                      *
 *              databaseConfigFile - this is the full path and name     *
 *                  of the cilogon.xml file containing the database     *
 *                  parameters (such as username and password). The     *
 *                  designated database is used by the OpenID consumer  *
 *                  to store temporary tokens.                          *
 ************************************************************************/

class dbprops {

    /* Set the constant to correspond to your particular set up.       */
    const databaseConfigFile = '/var/www/config/cilogon.xml';

    private $xml;     // SimpleXML object representation of config file
    private $dbtype;  // Either 'mysql' or 'pgsql'

    /********************************************************************
     * Function  : __construct                                          *
     * Parameter : Database type, either 'mysql' or 'pgsql'. Defaults   *
     *             to 'mysql'.                                          *
     * The constuctor sets several class variables and reads the        *
     * database config file into the SimpleXML class object $xml.       *
     ********************************************************************/
    function __construct($db='mysql') {
        $this->dbtype = $db;
        $this->xml = @simplexml_load_file(self::databaseConfigFile);
    }

    /********************************************************************
     * Function  : queryAttribute                                       *
     * Parameter : Name of the attribute to query, one of 'username',   *
     *             'password', or 'database'.                           *
     * Returns   : The value of the desired attribute, or empty string  *
     *             on error.                                            *
     * This is a general method which runs an xpath query on the        *
     * class $xml object to find the username, password, or database    *
     * value from the config file.                                      *
     ********************************************************************/
    function queryAttribute($attr) {
        $retstr = '';

        if ($this->xml !== false) {
            /* Since the database config file uses 'postgres' and       *
             * 'postgresql' instead of 'pgsql', we need to set          *
             * variables used by the xpath query.                       */
            $dbstr1 = $this->dbtype;
            $dbstr2 = $this->dbtype;
            if ($this->dbtype == 'pgsql') {
                $dbstr1 = 'postgres';
                $dbstr2 = 'postgresql';
            }

            $query = $this->xml->xpath("/config/service[@name='" . 
                     $dbstr1 . "']/" . $dbstr2 . '/@' . $attr);
            if ($query !== false) {
                $retstr = (string)$query[0];
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
}

?>
