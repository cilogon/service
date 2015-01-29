<?php

require_once('util.php');

/* Define several MyProxy default variables */
define('MYPROXY_LOGON','/usr/bin/myproxy-logon',true);
define('MYPROXY_HOST','myproxy.cilogon.org');
define('MYPROXY_PORT','7512');
define('MYPROXY_LIFETIME','12');

/************************************************************************
 * Function:   getMyProxyCredential()                                   *
 * Parameters: $username - The MyProxy user name (-l)                   *
 *             $passphrase - The MyProxy password for the username (-S) *
 *                 Defaults to empty string.  NOTE: If $passphrase is   *
 *                 non-empty, you CANNOT set a $certreq.                *
 *             $server - The MyProxy server to connect to (-s).         *
 *                 Defaults to MYPROXY_HOST.                            *
 *             $port - The port for the MyProxy server (-p).            *
 *                 Defaults to MYPROXY_PORT.                            *
 *             $lifetime - The life of the proxy in hours (-t).         *
 *                 Defaults to MYPROXY_LIFETIME hours.                  *
 *             $usercert - The X509_USER_CERT environment variable, OR  *
 *                 the X509_USER_PROXY environment variable if          *
 *                 $userkey is set to the empty string.  Defaults to    *
 *                 empty string.                                        *
 *             $userkey - The X509_USER_KEY environment variable.       *
 *                 Defaults to empty string.                            *
 *             $certreq - A certificate request created by the          *
 *                 openssl req command (--certreq).  Defaults to empty  *
 *                 string.  NOTE: If $certreq is non-empty, you CANNOT  *
 *                 set a $passphrase.                                   *
 *             $env - Extra environment variables in the form of        *
 *                 space-separated "key=value" pairs.                   *
 * Returns:    An X509 credential in a string upon success, or          *
 *                 an empty string upon failure.                        *
 ************************************************************************/
function getMyProxyCredential(
    $username,$passphrase='',$server=MYPROXY_HOST,$port=MYPROXY_PORT,
    $lifetime=MYPROXY_LIFETIME,$usercert='',$userkey='',
    $certreq='',$env='') {

    $retstr = '';

    // Make sure the username passed in is not empty
    if (strlen($username) == 0) {
        util::sendErrorAlert('getMyProxyCredential Error',
                             'MyProxy Error = Missing MyProxy username');
        return $retstr;
    }
    
    // Don't allow weird port numbers, i.e. negative or over 65535 
    if (($port < 0) || ($port > 65535)) {
        $port = MYPROXY_PORT;
    }

    // Don't allow weird lifetimes, i.e. negative or over 5 years
    if (($lifetime < 0) || ($lifetime > 43800)) {
        $lifetime = MYPROXY_LIFETIME;
    }

    /* If the usercert (X509_USER_CERT) is specified, check to see if 
     * the userkey (X509_USER_KEY) was as well.  If not, set userkey to
     * usercert, in effect making usercert act like X509_USER_PROXY. Then,
     * set the USER_CERT_ENV variable to bundle the two parameters into a
     * single variable holding the two X509_USER_* environment variables.
     */
    $USER_CERT_ENV = '';
    if (strlen($usercert) > 0) {
        if (strlen($userkey) == 0) {
            $userkey = $usercert;
        }
        $USER_CERT_ENV = 'X509_USER_CERT=' . escapeshellarg($usercert) . ' ' .
                         'X509_USER_KEY='  . escapeshellarg($userkey);
    }

    // Run the myproxy-logon command and capture the output and any error
    unset($output);
    $cmd = '/bin/env ' . 
           $USER_CERT_ENV . ' ' . 
           $env . ' ' .
           'MYPROXY_SOCKET_TIMEOUT=1 ' .
           MYPROXY_LOGON . ' ' .
           ' -s ' . escapeshellarg($server) .
           " -p $port" .
           " -t $lifetime" . 
           ' -l ' . escapeshellarg($username) .
           ' -S -o -' .
           ((strlen($certreq) > 0) ?
               (' --certreq - <<< ' . escapeshellarg($certreq)) : '') .
           ((strlen($passphrase) > 0) ?
               (' <<< ' . escapeshellarg($passphrase)) : ' -n') .
           ' 2>&1';
    exec($cmd,$output,$return_val);
    $retstr = implode("\n",$output);

    if ($return_val > 0) {
        util::sendErrorAlert('getMyProxyCredential Error',
                             "MyProxy Error = $return_val\n" .
                             "MyProxy Output= $retstr");
        $retstr = '';
    }

    return $retstr;
}

?>
