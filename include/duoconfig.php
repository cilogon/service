<?php

/************************************************************************
 * Class name : duoconfig                                               *
 * Description: This class reads the Duo Security configuration from    *
 * /var/www/config/cilogon.ini and stores the values in a 'param'       *
 * array. It also contains methods for communicating with the REST API  *
 * (https://www.duosecurity.com/docs/duorest). The REST API is used     *
 * when a user attempts to get a certificate via ECP.                   *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('duoconfig.php');                                    *
 *    $duoconfig = new duoconfig();                                     *
 *    $ikey = $duoconfig->param['ikey'];                                *
 *    $valid = $duoconfig->auth('A263@cilogon.org','275829');           *
 ************************************************************************/

class duoconfig {

    const cilogon_ini_file = '/var/www/config/cilogon.ini';

    public $param = array();

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new duoconfig object.                              *
     * Default constructor. This method 
     ********************************************************************/
    function __construct() {
        $duoparamnames = 
            array('host','ikey','skey','akey','name','ikey-rest','skey-rest');

        $ini_array = @parse_ini_file(self::cilogon_ini_file);
        if (is_array($ini_array)) {
            foreach ($duoparamnames as $val) {
                if (array_key_exists('duo.'.$val,$ini_array)) {
                    $this->param[$val] = $ini_array['duo.'.$val];
                }
            }
        }
    }

    /********************************************************************
     * Function  : getSignature                                         *
     * Parameters: (1) An HTTP method, either 'get' or 'post'           *
     *             (2) The endpoint of the REST API method (e.g.,       *
     *                 '/rest/v1/ping')                                 *
     *             (3) An array of parameters to be passed to the       *
     *                 REST API method. Pass 'false' if no parameters   *
     *                 are needed by the given REST API method.         *
     * Returns   : A SHA1 HMAC signature of the parameters as needed    *
     *             by the Duo REST API.                                 *
     * This method returns the HTTP Basic Auth password for connecting  *
     * to the Duo Security REST API. This is a SHA1 HMAC signature of   *
     * the request as documented at:                                    *
     *     https://www.duosecurity.com/docs/duorest                     *
     ********************************************************************/
    function getSignature($method,$endpoint,$params) {
        $request = strtoupper($method) . "\n" .
                   $this->param['host'] . "\n" .
                   $endpoint . "\n";
        if ($params !== false) {
            $request .= http_build_query($params);
        }
        return hash_hmac('sha1',$request,$this->param['skey-rest']);
    }

    /********************************************************************
     * Function  : call                                                 *
     * Parameters: (1) An HTTP method, either 'get' or 'post'           *
     *             (2) The endpoint of the REST API method (e.g.,       *
     *                 '/rest/v1/ping')                                 *
     *             (3) An array of parameters to be passed to the       *
     *                 REST API method. Defaults to 'false', which      *
     *                 means no parameters are needed by the given      *
     *                 REST API method.                                 *
     * Returns   : An array containing the response from the Duo REST   *
     *             API method upon success. False upon failure.         *
     * This method encapsulates the curl call needed to communicate     *
     * with the Duo Security REST API. This method is called by the     *
     * other class methods (e.g., ping()). If communication with Duo    *
     * is successful, an array containing the response from the REST    *
     * API method is returned. The contents of this array varies        *
     * depending on the method called. If there is a problem, false     *
     * is returned.                                                     *
     ********************************************************************/
    function call($method,$endpoint,$params=false) {
        $retval = false;

        $ch = curl_init();
        if ($ch !== false) {
            $url = 'https://' . $this->param['host'] . $endpoint;
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch,CURLOPT_TIMEOUT,30);
            if (strtoupper($method) == 'POST') {
                curl_setopt($ch,CURLOPT_POST,true);
            }
            curl_setopt($ch,CURLOPT_HEADER,false);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
            if ($params !== false) {
                curl_setopt($ch,CURLOPT_POSTFIELDS,$params);
            }
            curl_setopt($ch,CURLOPT_HTTPAUTH,CURLAUTH_BASIC);
            curl_setopt($ch,CURLOPT_USERPWD,
                $this->param['ikey-rest'] . ':' . 
                $this->getSignature($method,$endpoint,$params));

            $output = curl_exec($ch);
            if (curl_errno($ch)) { // Send alert on curl errors
                util::sendErrorAlert('cUrl Error',
                    'cUrl Error    = ' . curl_error($ch) . "\n" . 
                    "URL Accessed  = $url",
                    'tfleury@illinois.edu');
            }
            if (!empty($output)) {
                $httpcode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
                if ($httpcode == 200) {
                    $retval = json_decode($output,true);
                }
            }
            curl_close($ch);
        }
        return $retval;
    }

    /********************************************************************
     * Function  : ping                                                 *
     * Returns   : True if the Duo server is up. False otherwise.       *
     * This method acts as a "liveness check" that can be called to     *
     * verify that Duo is up before trying to call other methods. Note  *
     * that it is not really necessary since curl will timeout in       *
     * 30 seconds if Duo is down when called by other methods.          *
     ********************************************************************/
    function ping() {
        $retval = false;
        $result = $this->call('get','/rest/v1/ping');
        if (($result !== false) && ($result['stat'] == 'OK') &&
            ($result['response'] == 'pong')) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : check                                                *
     * Returns   : True if integration keys and signature creation      *
     *             are correct. False otherwise.                        *
     * This method can be called to verify that the integration and     *
     * secret keys are valid, and that the signature is being           *
     * generated properly. Upon success, true is returned.              *
     ********************************************************************/
    function check() {
        $retval = false;
        $result = $this->call('get','/rest/v1/check');
        if (($result !== false) && ($result['stat'] == 'OK') &&
             ($result['response'] == 'valid')) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : preauth                                              *
     * Parameter : The Duo Security username (e.g., 'A325@cilogon.org') *
     * Returns   : An array containing the Duo response upon success.   *
     *             False upon failure.                                  *
     * This method is used to determine whether a user is authorized    *
     * to log in. If so, it returns an array containing the user's      *
     * available authentication factors. If the user is not authorized  *
     * to log in, false is returned.                                    *
     ********************************************************************/
    function preauth($user) {
        $retval = false;
        $params = array('user' => $user);
        $result = $this->call('post','/rest/v1/preauth',$params);
        if (($result !== false) && ($result['stat'] == 'OK')) {
            $retval = $result;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : auth                                                 *
     * Parameters: (1) The Duo Security username ('A325@cilogon.org')   *
     *             (2) The authentication 'factor', which should be     *
     *                 either a six-digit passcode, or a number         *
     *                 representing the array position of the user's    *
     *                 selected auth method. (For example, '1'          *
     *                 typically means 'push' authentication.)          *
     * Returns   : True if the user successfully authenticated, false   *
     *             otherwise.                                           *
     * This method performs second-factor authentication for a given    *
     * user by verifying a passcode, placing a phone call, or sending   *
     * a push notification to the user's smartphone app. If the user    *
     * is successfully authenticated, true is returned. Otherwise,      *
     * false is returned.                                               *
     ********************************************************************/
    function auth($user,$factor) {
        $retval = false;
        $factorval = '';
        $preauth = $this->preauth($user);
        if ($preauth !== false) {
            if ((strlen($factor) == 6) ||  // Use 6-digit passcode
                (strlen($factor) == 7)) {  // For SMS, use 1 letter + 6 digits
                $factorval = $factor;
            } else { 
                // User selected an alternate method (e.g., 'push')
                // Get factorval name using factor index
                if (isset($preauth['response']['factors'][$factor])) {
                    $factorval = $preauth['response']['factors'][$factor];
                }
            }
            if (strlen($factorval) > 0) {
                $params = array('auto'   => $factorval ,
                                'factor' => 'auto' ,
                                'user'   => $user);
                $result = $this->call('post','/rest/v1/auth',$params);
                if (($result !== false) && ($result['stat'] == 'OK') &&
                    ($result['response']['result'] == 'allow')) {
                    $retval = true;
                }
            }
        }
        return $retval;
    }

}

?>
