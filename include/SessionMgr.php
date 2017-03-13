<?php

namespace CILogon\Service;

use CILogon\Service\DBProps;
use DB;

/**
 * SessionMgr
 *
 * This class is an implementation of a PHP Session
 * handler using MySQL as the storage mechanism. There are
 * several required functions implemented as documented at
 * http://us3.php.net/manual/en/function.session-set-save-handler.php
 * and http://us3.php.net/manual/en/class.sessionhandlerinterface.php.
 * Implementation details were gleaned from several
 * web pages, in particular:
 * http://www.devshed.com/c/a/PHP/Storing-PHP-Sessions-in-a-Database/
 * Also, the PEAR HTTP_Session2 package inspired several
 * tweaks, such as the crc check to prevent database
 * writes when the session data had not changed.
 *
 * In order to use this class, you must first configure
 * MySQL with correct privileges and a new table.
 *
 * # mysql -u root -p
 * ### password is found in /var/www/config/cilogon.xml
 * mysql> use oauth;
 * mysql> GRANT ALL PRIVILEGES ON oauth.phpsessions
 *     ->  TO 'cilogon'@'localhost' WITH GRANT OPTION;
 * mysql> COMMIT;
 * mysql> CREATE TABLE oauth.phpsessions (
 *     ->  id VARCHAR(32) NOT NULL,
 *     ->  data BLOB NOT NULL,
 *     ->  expires INTEGER NOT NULL,
 *     ->  PRIMARY KEY (id)
 *     -> ) ENGINE=MyISAM;
 * mysql> COMMIT;
 * mysql> \q
 *
 * To use this class, simply create a new instance
 * before the call to session_start().
 *
 * require_once 'SessionMgr.php';
 * $sessionmgr = new SessionMgr();
 * session_start();
 *
 * The session data is written to database upon script
 * completion or when session_write_close() is called.
 */
class SessionMgr
{
    /**
     * @var DB $db A PEAR DB database connection object
     */
    protected $db = null;

    /**
     * @var int $crc Session data cache id
     */
    protected $crc = null;

    /**
     * __construct
     *
     * Default constructor.  This method calls
     * session_set_save_handler() with the methods in this class as
     * parameters.
     *
     * @return SessionMgr A new SessionMgr object.
     */
    public function __construct()
    {
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

    /**
     * open
     *
     * This method opens the database connection.
     *
     * @param string $save_path The path where PHP session files are to be
     *        saved. (Ignored in the MySQL case.)
     * @param string $session_id The PHP session identifier.
     * @return bool True if database connection opened successfully,
     *         false otherwise.
     */
    public function open($save_path, $session_id)
    {
        $retval = true;  // Assume connect to database succeeded

        $dbprops = new DBProps('mysql');
        $this->db = $dbprops->getDBConnect();

        if (is_null($this->db)) {
            $retval = false;
        }

        return $retval;
    }

    /**
     * close
     *
     * This method closes the database connection.
     *
     * @return bool True if database connection was closed successfully,
     *         false otherwise.
     */
    public function close()
    {
        $retval = true;  // Assume close database succeeded

        if (is_null($this->db)) {  // Can't close a null database
            $retval = false;
        } else {
            $retval = $this->db->disconnect();
            $this->db = null;
        }

        return $retval;
    }

    /**
     * read
     *
     * This method reads the PHP session data from the database
     * associated with the passed-in identifier. It calculates a cache
     * string using crc32 (so we can check if session data has been
     * updated). If there is a problem reading the data, empty string
     * is returned.
     *
     * @param string $session_id The PHP session identifier.
     * @return string The PHP session data associated with the identifier,
     *         or empty string on error.
     */
    public function read($session_id)
    {
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

    /**
     * write
     *
     * This method is called when the PHP session data should be
     * written to the database (usually upon script completion, or when
     * session_write_close() is called). It tries to be 'smart' by
     * updating only the information that has changed, e.g. update
     * just the expiration time if session data has not changed.
     *
     * @param string $session_id The PHP session identifier.
     * @param string $session_data The PHP session data to be written.
     * @return bool True upon successful write of data to the database,
     *         or false on error.
     */
    public function write($session_id, $session_data)
    {
        $retval= true; // Assume write to database succeeded

        if (is_null($this->db)) {  // Can't write to a null database
            $retval = false;
        } else {
            $time = time();
            $newtime = $time + get_cfg_var('session.gc_maxlifetime');
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

    /**
     * destroy
     *
     * This method deletes a session identifier from the database.
     *
     * @param string $session_id The PHP session identifier.
     * @return bool True upon successful deletion of data from the
     *         database, or false on error.
     */
    public function destroy($session_id)
    {
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

    /**
     * gc
     *
     * This method is invoked internally by PHP periodically in order
     * to purge old session data. It simply looks for rows where the
     * 'expires' column is older than the current time (less 10
     * seconds to allow for multiple threads).
     *
     * @param int $maxlifetime The lifetime of the PHP session (ignored since
     *        the 'expires' column is set using the
     *        session.gc_maxlifetime value).
     * @return bool True upon successful garbage collection run on the
     *         database, or false on error.
     */
    public function gc($maxlifetime)
    {
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
