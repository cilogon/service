<?php

class loggit {

    protected $logger;

    /*********************************************************************
     * Function  : __construct - default constructor                     *
     * Parameters: (1) The log type, can be 'syslog' or 'mail'           *
     *             (2) The destination email address if (1) is 'mail'    *
     * Default constructor.  This functions creates a new internal       *
     * $logger object to be utilized to write out log messages to the    *
     * intended destination (the first parameter) using the PHP Pear Log *
     * module.  End-user documentation can be found at:                  *
     *     http://www.indelible.org/php/Log/guide.html                   *
     * You can log either to the system syslog, or send a 'log' message  *
     * to an email address.  Default is 'syslog' if you don't specify    *
     * the first parameter.  If first parameter is 'mail', the default   *
     * for the second parameter is help@cilogon.org.  If running in a    *
     * browser session, the SERVER_NAME and REQUEST_URI are included     *
     * in all log events.                                                *
     *                                                                   *
     * Example usage:                                                    *
     *     // Log info message to syslog                                 *
     *     $sysloggit = new loggit();                                    *
     *     $sysloggit->info("This is an info message.");                 *
     *     // Send alert to email address                                *
     *     $mailloggit = new loggit('mail','help@google.com');           *
     *     $mailloggit->alert("There is a problem!");                    *
     *********************************************************************/
    function __construct($logtype='syslog',$email='help@cilogon.org')
    {
        $ident = util::getServerVar('SERVER_NAME') .
                 util::getServerVar('REQUEST_URI');

        $this->logger = &Log::singleton($logtype,
            ($logtype=='syslog' ? 'LOG_SYSLOG' : $email), $ident);
    }

    /*********************************************************************
     * Function  : info                                                  *
     * Parameters: (1) The message string to be logged.                  *
     *             (2) The PHP Pear-Log level for the message, which     *
     *                 defaults to PEAR_LOG_INFO.                        *
     * This function writes a message to a "log" using the PHP Pear Log  *
     * module. Several server variables and cookies (if they are set)    *
     * are automatically appended to the message to be logged.  These    *
     * are found in the $envs and $cookies array in the code below.      *
     * Also, all PHP session variables are logged.                       *
     *********************************************************************/
    function info($message,$level=PEAR_LOG_INFO)
    {
        $envs    = array('REMOTE_ADDR',
                         'REMOTE_USER',
                         'HTTP_SHIB_IDENTITY_PROVIDER',
                         'HTTP_SHIB_SESSION_ID'
                        );
        $cookies = array('providerId',
                         'CSRFProtection'
                        );
        $envstr = ' ';
        foreach ($envs as $value) {
            if ((isset($_SERVER[$value])) && (strlen($_SERVER[$value]) > 0)) {
                $envstr .= $value . '="' . $_SERVER[$value] . '" ';
            }
        }

        foreach ($cookies as $value) {
            if ((isset($_COOKIE[$value])) && (strlen($_COOKIE[$value]) > 0)) {
                $envstr .= $value . '="' . $_COOKIE[$value] . '" ';
            }
        }

        /* NEED TO CHANGE THIS WHEN USING HTTP_Session2 */
        if (session_id() != '') {
            foreach ($_SESSION as $key => $value) {
                $envstr .= $key . '="' . $value . '" ';
            }
        }

        $this->logger->log($message . ' ' . $envstr, $level);
    }

    /*********************************************************************
     * Function  : warn                                                  *
     * Parameter : The message string to be logged.                      *
     * This function writes a warning message message to the log.        *
     *********************************************************************/
    function warn($message) {
        $this->info($message,PEAR_LOG_WARNING);
    }

    /*********************************************************************
     * Function  : error                                                 *
     * Parameter : The message string to be logged.                      *
     * This function writes an error message message to the log.         *
     *********************************************************************/
    function error($message) {
        $this->info($message,PEAR_LOG_ERR);
    }

    /*********************************************************************
     * Function  : alert                                                 *
     * Parameter : The message string to be logged.                      *
     * This function writes an alert message message to the log.         *
     *********************************************************************/
    function alert($message) {
        $this->info($message,PEAR_LOG_ALERT);
    }
}

?>
