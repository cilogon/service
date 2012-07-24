<?php

require_once('dbprops.php');

/************************************************************************
 * Class name : sessionmgr                                              *
 * Description: This class is an implementation of a PHP Session        *
 *              handler using MySQL as the storage mechanism. There are *
 *              several required functions implemented as documented at *
 * http://us3.php.net/manual/en/function.session-set-save-handler.php   *
 * and http://us3.php.net/manual/en/class.sessionhandlerinterface.php.  *
 *              Implementation details were gleaned from several        *
 *              web pages, in particular:                               *
 * http://www.devshed.com/c/a/PHP/Storing-PHP-Sessions-in-a-Database/   *
 *              Also, the PEAR HTTP_Session2 package inspired several   *
 *              tweaks, such as the crc check to prevent database       *
 *              writes when the session data had not changed.           *
 *                                                                      *
 *              In order to use this class, you must first configure    *
 *              MySQL with correct privileges and a new table.          *
 *                                                                      *
 * # mysql -u root -p                                                   *
 * ### password is found in /var/www/config/cilogon.xml                 *
 * mysql> use oauth;                                                    *
 * mysql> GRANT ALL PRIVILEGES ON oauth.phpsessions                     *
 *     ->  TO 'cilogon'@'localhost' WITH GRANT OPTION;                  *
 * mysql> COMMIT;                                                       *
 * mysql> CREATE TABLE oauth.phpsessions (                              *
 *     ->  id VARCHAR(32) NOT NULL,                                     *
 *     ->  data BLOB NOT NULL,                                          *
 *     ->  expires INTEGER NOT NULL,                                    *
 *     ->  PRIMARY KEY (id)                                             *
 *     -> ) ENGINE=MyISAM;                                              *
 * mysql> COMMIT;                                                       *
 * mysql> \q                                                            *
 *                                                                      *
 *              To use this class, simply create a new instance         *
 *              before the call to session_start().                     *
 *                                                                      *
 * require_once('sessionmgr.php');                                      *
 * $sessionmgr = new sessionmgr();                                      *
 * session_start();                                                     *
 *                                                                      *
 *              The session data is written to database upon script     *
 *              completion or when session_write_close() is called.     *
 ************************************************************************/

class sessionmgr {

    protected $db = null;  // PEAR DB database connection object
    protected $crc = null; // Session data cache id

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new sessionmgr object.                             *
     * Default constructor.  This method calls                          *
     * session_set_save_handler() with the methods in this class as     *
     * parameters.                                                      *
     ********************************************************************/
    function __construct() {
        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );
        // The following prevents unexpected effects when using 
        // objects as save handlers
        register_shutdown_function('session_write_close');
    }

    /********************************************************************
     * Function  : open                                                 *
     * Parameters: (1) The path where PHP session files are to be       *
     *                 saved. (Ignored in the MySQL case.)              *
     *             (2) The PHP session identifier.                      *
     * Returns   : True if database connection opened successfully,     *
     *             false otherwise.                                     *
     * This method opens the database connection.                       *
     ********************************************************************/
    function open($save_path,$session_id) {
        $retval = true;  // Assume connect to database succeeded

        $dbprops = new dbprops('mysql');
        $this->db = $dbprops->getDBConnect();

        if (is_null($this->db)) {
            $retval = false;
        }

        return $retval;
    }

    /********************************************************************
     * Function  : close                                                *
     * Returns   : True if database connection was closed successfully, *
     *             false otherwise.                                     *
     * This method closes the database connection.                      *
     ********************************************************************/
    function close() {
        $retval = true;  // Assume close database succeeded

        if (is_null($this->db)) {  // Can't close a null database
            $retval = false;
        } else {
            $retval = $this->db->disconnect();
            $this->db = null;
        }

        return $retval;
    }

    /********************************************************************
     * Function  : read                                                 *
     * Parameter : The PHP session identifier.                          *
     * Returns   : The PHP session data associated with the identifier, *
     *             or empty string on error.                            *
     * This method reads the PHP session data from the database         *
     * associated with the passed-in identifier. It calculates a cache  *
     * string using crc32 (so we can check if session data has been     *
     * updated). If there is a problem reading the data, empty string   *
     * is returned.                                                     *
     ********************************************************************/
    function read($session_id) {
        $retval = '';

        if (!is_null($this->db)) {
            $time = time();
            $quoteid = $this->db->quoteSmart($session_id);
            $query = "SELECT data FROM phpsessions " . 
                     "WHERE id = $quoteid AND expires >= $time";
            $retval = $this->db->getOne($query);
            if (DB::isError($retval)) {
                $retval = '';
            } else {
                $this->crc = strlen($retval) . crc32($retval);
            }
        }

        return $retval;
    }

    /********************************************************************
     * Function  : write                                                *
     * Parameters: (1) The PHP session identifier.                      *
     *             (2) The PHP session data to be written.              *
     * Returns   : True upon successful write of data to the database,  *
     *             or false on error.                                   *
     * This method is called when the PHP session data should be        *
     * written to the database (usually upon script completion, or when *
     * session_write_close() is called). It tries to be 'smart' by      *
     * updating only the information that has changed, e.g. update      *
     * just the expiration time if session data has not changed.        *
     ********************************************************************/
    function write($session_id,$session_data) {
        $retval= true; // Assume write to database succeeded

        if (is_null($this->db)) {  // Can't write to a null database
            $retval = false;
        } else {
            $time = time();
            $newtime = $time + get_cfg_var("session.gc_maxlifetime");
            $quoteid = $this->db->quoteSmart($session_id);
            $query = '';
            if ((!is_null($this->crc)) && 
                ($this->crc === (strlen($session_data).crc32($session_data)))) {
                // $_SESSION hasn't been touched, so update the expires column
                $query = "UPDATE phpsessions SET expires = $newtime " .
                         "WHERE id = $quoteid";
            } else {
                // Check if the table row already exists
                $query = "SELECT COUNT(id) FROM phpsessions " . 
                         "WHERE id = $quoteid";
                $result = $this->db->getOne($query);
                if (DB::isError($result)) {
                    $retval = false;
                } else {
                    $quotedata = $this->db->quoteSmart($session_data);
                    if (intval($result) == 0) { 
                        // Insert a new row into the table
                        $query = "INSERT INTO phpsessions (id,data,expires) ".
                                 "VALUES($quoteid,$quotedata,$newtime)";
                    } else {
                        // Update existing row with new data and expires
                        $query = "UPDATE phpsessions " . 
                                 "SET data = $quotedata, expires = $newtime ".
                                 "WHERE id = $quoteid";
                    }
                }
            }

            if ($retval) {
                $result = $this->db->query($query);
                if (DB::isError($result)) {
                    $retval = false;
                }
            }
        }

        return $retval;
    }

    /********************************************************************
     * Function  : destroy                                              *
     * Parameter : The PHP session identifier.                          *
     * Returns   : True upon successful deletion of data from the       *
     *             database, or false on error.                         *
     * This method deletes a session identifier from the database.      *
     ********************************************************************/
    function destroy($session_id) {
        $retval = true;  // Assume delete session_id from database succeeded

        if (is_null($this->db)) {  // Can't delete from a null database
            $retval = false;
        } else {
            $quoteid = $this->db->quoteSmart($session_id);
            $query = "DELETE FROM phpsessions WHERE id = $quoteid";
            $result = $this->db->query($query);
            if (DB::isError($result)) {
                $retval = false;
            }
        }

        return $retval;
    }

    /********************************************************************
     * Function  : gc                                                   *
     * Parameter : The lifetime of the PHP session (ignored since       *
     *             the 'expires' column is set using the                *
     *             session.gc_maxlifetime value).                       *
     * Returns   : True upon successful garbage collection run on the   *
     *             database, or false on error.                         *
     * This method is invoked internally by PHP periodically in order   *
     * to purge old session data. It simply looks for rows where the    *
     * 'expires' column is older than the current time (less 10         *
     * seconds to allow for multiple threads).                          *
     ********************************************************************/
    function gc($maxlifetime) {
        $retval = true;  // Assume garbage collection succeeded

        if (is_null($this->db)) {  // Can't garbage collect on a null database
            $retval = false;
        } else {
            $time = time() - 10; // Allow extra time for multi-threads
            $query = "DELETE FROM phpsessions WHERE expires < $time";
            $result = $this->db->query($query);
            if (DB::isError($result)) {
                $retval = false;
            }
        }

        return $retval;
    }

}

?>
