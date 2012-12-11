<?php

require_once('autoloader.php');
require_once('content.php');

/************************************************************************
 * Class name : twofactor                                               *
 * Description: This class contains a bunch of static (class) methods   *
 * for handling two-factor authentication. Information about which      *
 * methods are registered and/or enabled is stored in the datastore.    *
 * However, to speed access to the two-factor information, the session  *
 * variable 'twofactor' contains the current state. To populate the     *
 * 'twofactor' session variable, call the read() method. This is done   *
 * (automatically) by the 'getuser' and 'getopeniduser' servlets when   *
 * the user logs on. If changes are made to the 'twofactor' session     *
 * variable, call the write() method to save the new state to the       *
 * datastore.                                                           *
 ************************************************************************/

class twofactor {

    /********************************************************************
     * Function  : read                                                 *
     * Returns   : True if read two-factor info from database and saved *
     *             in 'twofactor' session variable. False otherwise.    *
     * This method reads the two-factor information from the database   *
     * for the current 'uid' session variable. The result is stored in  *
     * the 'twofactor' session variable. If the 'uid' session variable  *
     * is not set, or if there is a problem reading from the database,  *
     * false is returned. Note that this function isn't really needed   *
     * since the 'twofactor' session variable is set when the database  *
     * servlet function 'getUser' is called.                            *
     ********************************************************************/
    public static function read() {
        $retval = false;  // Assume read failed
        $uid = util::getSessionVar('uid');
        if (strlen($uid) > 0) {
            $dbs = new dbservice();
            $retval = $dbs->getTwoFactorInfo($uid);
            if ($retval) {
                util::setSessionVar('twofactor',$dbs->two_factor);
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : write                                                *
     * Returns   : True if successfully wrote 'twofactor' session       *
     *             variable to database. False otherwise.               *
     * This method writes the 'twofactor' session variable to the       *
     * database for the current 'uid' session variable. If the 'uid'    *
     * session variable is not set, or if there is a problem writing    *
     * to the database, false is returned. Note that this simply saves  *
     * two-factor info to the database. You need to use other methods   *
     * to maintain/update the 'twofactor' session variable.             *
     ********************************************************************/
    public static function write() {
        $retval = false; // Assume write failed
        $uid = util::getSessionVar('uid');
        if (strlen($uid) > 0) {
            $dbs = new dbservice();
            $retval = $dbs->setTwoFactorInfo(
                $uid,util::getSessionVar('twofactor'));
        }
        return $retval;
    }

    /********************************************************************
     * Function  : setSessVar                                           *
     * Parameters: The key and val when setting "key=val" in the        *
     *             'twofactor' session variable. If "val" is the emtpy  *
     *             string, then remove any existing "key=..." entry.    *
     * This method takes in a "key" and a "val" to set "key=val" in the *
     * 'twofactor' session variable. If "val" is empty, then any        *
     * existing "key=..." entry is deleted. (Thus there are no null     *
     * entries in 'twofactor' such as "duo=".) The final result is      *
     * saved back to the 'twofactor' session variable.                  *
     ********************************************************************/
    private static function setSessVar($key,$val) {
        $twofactor = util::getSessionVar('twofactor');
        // Check if $key=... is present in 'twofactor' session variable
        if (preg_match("/$key=([^,]*)/",$twofactor,$match)) {
            $oldval = $match[1]; // Keep the old value
            if (strlen($val) > 0) { // If new val is not empty, set key=val
                $twofactor = preg_replace(
                    "/$key=$oldval/","$key=$val",$twofactor);
            } else { // New val is empty, delete key=oldval (plus commas)
                $twofactor = preg_replace("/$key=$oldval,?/",'',$twofactor);
                // Remove trailing comma if deleted last key=oldval pair
                $twofactor = preg_replace('/,$/','',$twofactor);
            }
        } else { // $key does not exist in 'twofactor' session variable
            if (strlen($val) > 0) { // Make sure $val is not empty
                if (strlen($twofactor) > 0) { // Append comma if other info
                    $twofactor .= ',';
                }
                $twofactor .= "$key=$val"; 
            }
        }
        util::setSessionVar('twofactor',$twofactor);
    }

    /********************************************************************
     * Function  : getSessVar                                           *
     * Parameter : The key to look for in the 'twofactor' session       *
     *             variable.                                            *
     * Returns   : The val for "key=val" in the 'twofactor' session     *
     *             variable, or empty string if no such "key" found.    *
     * This method looks in the 'twofactor' session variable for a      *
     * "key=val" pair and returns "val". If no such pair is found, then *
     * empty string is returned.                                        *
     ********************************************************************/
    private static function getSessVar($key) {
        $retval = '';
        $twofactor = util::getSessionVar('twofactor');
        if (preg_match("/$key=([^,]*)/",$twofactor,$match)) {
            $retval = $match[1];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : setRegister                                          *
     * Parameters: (1) The two-factor type to set, e.g., 'ga' for       *
     *                 Google Authenticator.                            *
     *             (2) The secret registration key to set for the       *
     *                 two-factor type. Empty string implies unregister *
     *                 the $tftype.                                     *
     * This method sets "$tftype=$key" in the 'twofactor' session       *
     * variable. If $key is empty, it UNverifies the $tftype and then   *
     * deletes the current "$tftype=..." (if any) from the 'twofactor'  *
     * session  variable. Also, if $key is empty, check if $tftype is   *
     * currently 'enabled'. If so, set 'enabled' to 'none'.             *
     ********************************************************************/
    public static function setRegister($tftype,$key) {
        // When deleting the key for tftype, also UNverify and disable.
        if (strlen($key) == 0) {
            self::setVerified($tftype,false);
            if (self::isEnabled($tftype)) {
                self::setDisabled($tftype,false);
            }
        }
        self::setSessVar($tftype,$key);
    }

    /********************************************************************
     * Function  : getRegister                                          *
     * Parameter : The two-factor type to query, e.g., 'ga' for         *
     *             Google Authenticator.                                *
     * Returns   : The secret registration key for the given two-factor *
     *             type, or emtpy string if not set.                    *
     * This method looks for "$tftype=..." in the 'twofactor' session   *
     * variable, and returns the "...". If no such $tftype is found,    *
     * empty string is returned.                                        *
     ********************************************************************/
    public static function getRegister($tftype) {
        return self::getSessVar($tftype);
    }

    /********************************************************************
     * Function  : isRegistered                                         *
     * Parameters: The two-factor type to query, e.g., 'ga'.            *
     * Returns   : True if "$tftype=..." is found in the 'twofactor'    *
     *             session variable. False otherwise.                   *
     * This method is a convenience function to check if "$tftype=..."  *
     * is present in the 'twofactor' session variable. It does this     *
     * by checking if the string length of the $tftype key is non-zero. *
     ********************************************************************/
    public static function isRegistered($tftype) {
        return (strlen(self::getRegister($tftype)) > 0);
    }

    /********************************************************************
     * Function  : setEnabled                                           *
     * Parameter : The two-factor type two set enabled, e.g., 'ga'.     *
     * This method sets the 'enabled' entry in the 'twofactor' session  *
     * variable. It first checks if the $tftype is registered. If so,   *
     * it then checks if the $tftype is not currently enabled. If both  *
     * conditions are true, then set 'enabled' to $tftype.              *
     ********************************************************************/
    public static function setEnabled($tftype) {
        if ((self::isRegistered($tftype)) && (!self::isEnabled($tftype))) {
            self::setSessVar('en',$tftype);
        }
    }

    /********************************************************************
     * Function  : setDisabled                                          *
     * Parameter : The two-factor type two set disabled, e.g., 'ga'.    *
     * This method sets the 'enabled' entry in the 'twofactor' session  *
     * variable to 'none'.                                              *
     ********************************************************************/
    public static function setDisabled() {
        self::setSessVar('en','none');
    }

    /********************************************************************
     * Function  : getEnabled                                           *
     * This function queries the 'enabled' session variable to find out *
     * which two factor method is enabled (if any). It returns the      *
     * the enabled two factor method, or "none" if nothing is enabled.  *
     ********************************************************************/
    public static function getEnabled() {
        $enabled = 'none';  // Default
        $enabledvar = self::getSessVar('en');
        if (strlen($enabledvar) > 0) {
            $enabled = $enabledvar;
        }
        return $enabled;
    }

    /********************************************************************
     * Function  : getEnabledName                                       *
     * Parameter : (Optional) The short enabled two factor string.      *
     * This function queries the 'enabled' session variable and returns *
     * the 'pretty print' name of the enabled two factor method. If     *
     * a parameter is specified (i.e. the result of calling getEnabled),*
     * then this method simply returns the 'pretty print' name of that  *
     * two factor type.                                                 *
     ********************************************************************/
    public static function getEnabledName($enabled=null) {
        $name = '';
        if (is_null($enabled)) { // No parameter means call getEnabled()
            $enabled = twofactor::getEnabled();
        }
        switch ($enabled) {
            case 'ga' : $name = 'Google Authenticator'; break;
            case 'duo': $name = 'Duo Security';         break;
            default   : $name = 'Disabled';             break;
        }
        return $name;
    }

    /********************************************************************
     * Function  : isEnabled                                            *
     * Parameter : The two-factor type to query, e.g., 'ga'.            *
     * Returns   : True if the $tftype is found enabled in the          *
     *             'twofactor' session variable. False otherwise.       *
     * This method looks in the 'twofactor' session variable for the    *
     * "en=..." entry. If this matches the passed-in $tftype,           *
     * true is returned. Otherwise, false is returned.                  *
     ********************************************************************/
    public static function isEnabled($tftype) {
        return (self::getSessVar('en') == $tftype);
    }

    /********************************************************************
     * Function  : setVerified                                          *
     * Parameters: (1) The two-factor type two set verified, e.g., 'ga'.*
     *             (2) True for set verified, false for set unverified. *
     *                 Defaults to true.                                *
     * This method sets a 'verified' entry for the given $tftype to     *
     * '1' (for $verified=true) or deletes the verified entry (for      *
     * $verified=false). The verified entry in the 'twofactor' session  *
     * variable is the $tftype concatenated with '-v'. So the entry in  *
     * the 'twofactor' session variable looks like "ga-v=1". This       *
     * method first checks if the given $tftype is registered. If so,   *
     * then it sets verified appropriately.                             *
     ********************************************************************/
    public static function setVerified($tftype,$verified=true) {
        if (self::isRegistered($tftype)) {
            self::setSessVar("$tftype-v", ($verified ? '1' : ''));
        }
    }

    /********************************************************************
     * Function  : isVerified                                           *
     * Parameter : The two-factor type to query, e.g., 'ga'.            *
     * Returns   : True if the $tftype is found verified in the         *
     *             'twofactor' session variable. False otherwise.       *
     * This method looks in the 'twofactor' session variable for the    *
     * "$tftype-v=..." entry. If this is '1', true is returned.         *
     * Otherwise, false is returned.                                    *
     ********************************************************************/
    public static function isVerified($tftype) {
        return (self::getSessVar("$tftype-v") == '1');
    }

    /********************************************************************
     * Function  : printPage                                            *
     * Parameters: The two-factor type to print out, e.g., 'ga'.  If    *
     *             empty, then print the currently enabled two-factor   *
     *             type page.                                           *
     * This method is called when the user clicks the "Enable" button   *
     * for a particular two-factor type, or when the user needs to      *
     * log in with an enabled two-factor type. This function does the   *
     * work of figuring out if the user has registered and verified     *
     * a given two-factor authentication and displays the appropriate   *
     * page (for registering or for logging in).                        *
     ********************************************************************/
    public static function printPage($tftype='') {
        // If $tftype is not specified, use the currently enabled type
        if (strlen($tftype) == 0) {
            $tftype = self::getEnabled();
        }
        if ($tftype == 'ga') { // Google Authenticator
            // Check if user clicked the 'Verify' button. We do this by
            // looking for the hidden 'verifyga' field, set to '1'. If found,
            // set 'verified' for 'ga' so that we progress to the Login page.
            $verifyga = util::getPostVar('verifyga');
            if ($verifyga == '1') {
                self::setVerified($tftype);
                self::write(); 
            }

            // If user registered and verified Google Authenticator,
            // proceed to the Google Authenticator Login page.
            if ((self::isRegistered('ga')) && 
                (self::isVerified('ga'))) {
                self::printGALoginPage();
            } else {
                self::printGARegisterPage();
            }
        } elseif ($tftype == 'duo') { // Duo Security
            self::printDuoPage();
        }
    }

    /********************************************************************
     * Function  : printGARegisterPage                                  *
     * This method prints out the page for Google Authenticator         *
     * Registration. Links for the Google Authenticator app are shown   *
     * along with the 'secret' key (and corresponding QR code) to       *
     * register the user's account on their phone.                      *
     ********************************************************************/
    private static function printGARegisterPage() {
        $secret = '';
        // Check if the secret key is already 'registered'.
        if (self::isRegistered('ga')) {
            $secret = self::getRegister('ga');
        } else { // Generate a new secret key and save it to the database
            $secret = Google2FA::generate_secret_key();
            self::setRegister('ga',$secret);
            self::write(); 
        }

        $serialstr = getSerialStringFromDN(util::getSessionVar('dn'));

        printHeader('Google Authenticator Registration');
        echo '
        <div class="boxed">

        <h2>Google Authenticator Registration</h2>
        <h3>Step 1</h3>
        <p>
        Download the Google Authenticator app specific to your device.
        </p>
        <noscript>
        <div class="nojs">
        Javascript is disabled. In order to view the QR Code Links
        below, please enable Javascript in your browser.
        </div>
        </noscript>
        <ul>
        <li> <a target="_blank"
        href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2">Android
        OS (2.1 and higher)</a> (<a
        href="javascript:showHideDiv(\'qrandroid\',-1)">QR Code Link</a>)
        <div id="qrandroid" style="display:none">
        <br/>
        <img alt="QR Code for Android" width="200" height="200"
        src="/include/phpqrcode.php?data=https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
        />
        </div>
        </li>
        <li><a target="_blank"
        href="https://itunes.apple.com/us/app/google-authenticator/id388497605">Apple
        iOS (3.1.3 and higher)</a> (<a href="javascript:showHideDiv(\'qrios\',-1)">QR Code Link</a>)
        <div id="qrios" style="display:none">
        <br/>
        <img alt="QR Code for iOS" width="200" height="200"
        src="/include/phpqrcode.php?data=https://itunes.apple.com/us/app/google-authenticator/id388497605"
        />
        </div>
        </li>
        <li><a target="_blank"
        href="http://m.google.com/authenticator">BlackBerry OS (4.5-6.0)</a> (<a 
        href="javascript:showHideDiv(\'qrblackberry\',-1)">QR Code Link</a>)
        <div id="qrblackberry" style="display:none">
        <br/>
        <img alt="QR Code for iOS" width="200" height="200"
        src="/include/phpqrcode.php?data=http://m.google.com/authenticator"
        />
        </div>
        </li>
        </ul>

        <h3>Step 2</h3>
        <p>
        Launch the Google Authenticator app and add your token by scanning the
        QR code or by entering the account information shown below.
        </p>
        <p>
        <div style="float:left;margin-left:2em">
        <img alt="QR Code" width="200" height="200"
        src="/include/phpqrcode.php?data=otpauth://totp/',
        $serialstr,
        '@cilogon.org%3Fsecret%3D',
        $secret,
        '" />
        </div>
        <div id="securitycode" style="float:left;margin-left:2em">
        <table style="margin-left:2em">
        <tr>
        <th style="text-align:left">Account Name:</th>
        <td><tt>',$serialstr,'@cilogon.org</tt></td>
        </tr>
        <tr>
        <th style="text-align:left">Key:</th>
        <td><tt>',$secret,'</tt></td>
        </tr>
        <tr>
        <th style="text-align:left">Type of Key:</th>
        <td><tt>Time based</tt></td>
        </tr>
        </table>
        </div>
        </p>
        <br class="clear" />

        <h3>Step 3</h3>
        <p>
        Click the "Verify" button to log in with a one-time password generated
        by the Google Authenticator app.
        </p>
        <div>
        ';

        printFormHead();
        echo '
        <p>
        <input type="hidden" name="verifyga" value="1" />
        <input type="hidden" name="twofactortype" value="ga" />
        <input type="submit" name="submit" class="submit" value="Verify" />
        </p>
        </form>
        </div>
        </div> <!-- boxed -->
        ';
        printFooter();
    }

    /********************************************************************
     * Function  : printGALoginPage                                     *
     * This method prints out the Google Authenticator Log In page.     *
     * The user's Account Name is shown, and the user is prompted to    *
     * run the Google Authenticator app on their phone to generate a    *
     * one time password.                                               *
     ********************************************************************/
    private static function printGALoginPage() {
        $serialstr = getSerialStringFromDN(util::getSessionVar('dn'));

        printHeader('Google Authenticator Login');
        echo '
        <div class="boxed">

        <h2>Google Authenticator Login</h2>
        <p>
        Your account has been configured to use Google Authenticator as a
        second authentication factor. Enter a one-time passcode as generated
        by the app below.
        </p>
        <div class="actionbox">
        ';

        printFormHead();
        echo '
        <p>
        <b>Account:</b> ' , $serialstr , '@cilogon.org
        </p>
        <p>
        <b>Passcode:</b> <input type="text" name="gacode" 
        id="gacode" size="20" />
<!--[if IE]><input type="text" style="display:none;" disabled="disabled" size="1"/><![endif]-->
        </p>
        <p>
        <input type="submit" name="submit" class="submit" value="Enter" />
        </p>
        </form>
        </div>
        ';

        self::printForgotPhone();

        echo '
        </div> <!-- boxed -->
        ';
        printFooter();
    }

    /********************************************************************
     * Function  : printDuoPage                                         *
     * This method prints out the Duo Security page. Note that Duo      *
     * manages itself via an iframe and JavaScript, so there is only    *
     * a single page for Duo Security, which handles both registrations *
     * and authentications.                                             *
     ********************************************************************/
    private static function printDuoPage() {
        global $csrf;

        // Check if the 'secret key' is already 'registered'. This is
        // a bit silly since registration is handled on Duo's servers.
        if (!self::isRegistered('duo')) {
            self::setRegister('duo','1');
            self::setVerified('duo');
            self::write(); 
        }

        $serialstr = getSerialStringFromDN(util::getSessionVar('dn'));
        $duoconfig = new duoconfig();
        $sig_request = Duo::signRequest(
            $duoconfig->param['ikey'],
            $duoconfig->param['skey'],
            $duoconfig->param['akey'],
            $serialstr . '@cilogon.org'
        );

        printHeader('Duo Security');
        echo '
        <div class="boxed">

        <h2>Duo Security</h2>
        <p>
        Your account has been configured to use Duo Security as a
        second authentication factor. Follow the instructions below to use
        Duo Security for "' , $duoconfig->param['name'] , '".
        </p>

        <noscript>
        <div class="nojs">
        Javascript is disabled. In order to use Duo Security, please enable
        Javascript in your browser.
        </div>
        </noscript>

        <script src="/include/Duo-Web-v1.bundled.min.js"></script>
        <script>
        Duo.init({
            \'host\':\'', $duoconfig->param['host'] ,'\',
            \'post_action\':\'', util::getScriptDir() ,'\',
            \'sig_request\':\'', $sig_request , '\'
        });
        </script>


        <div>
        <iframe id="duo_iframe" width="450" height="350" 
        frameborder="0" allowtransparency="true">
        </iframe>
        </div>

        <form method="post" id="duo_form" style="display:none">
            <input type="hidden" name="SUBMIT" value="EnterDuo" />
        ' , $csrf->hiddenFormElement() , '
        </form>
        ';

        self::printForgotPhone();

        echo '
        </div> <!-- boxed -->
        ';
        printFooter();
    }

    /********************************************************************
     * Function  : printForgotPhone                                     *
     * This method prints out the HTML block for the "Don't have your   *
     * phone with you" link.                                            *
     ********************************************************************/
    private static function printForgotPhone() {
        echo '
        <noscript>
        <div class="nojs">
        Javascript is disabled. In order to activate the link
        below, please enable Javascript in your browser.
        </div>
        </noscript>

        <p>
        <a href="javascript:showHideDiv(\'missingdevice\',-1)">Don\'t have
        your phone with you?</a>
        </p>
        <div id="missingdevice" style="display:none">
        <p>
        If you temporarily do not have access to your phone,
        you can click the "Disable Two-Factor" button below
        to disable two-factor authentication. This will
        send a message to the email address provided by your Identity
        Provider.  You will then proceed to the CILogon
        Service without two-factor authentication enabled.
        </p>
        ';

        printFormHead();
        echo '
        <p>
        <input type="hidden" name="missingphone" value="1" />
        <input type="submit" name="submit" class="submit" 
        value="Disable Two-Factor"/>
        </p>
        </form>
        </div>
        ';
    }

    /********************************************************************
     * Function  : sendPhoneAlert                                       *
     * Parameters: (1) Subject line summary text.                       *
     *             (2) Detail text in the body of the email.            *
     *             (3) The destination email address.                   *
     * This method is used to send email to the user when he clicks     *
     * either "Disable Two-Factor" (under "Don't have your phone with   *
     * you?") or "I Lost My Phone" (under "Lost your phone?").          *
     ********************************************************************/
    public static function sendPhoneAlert($summary,$detail,$mailto) {
        $mailfrom = 'From: help@cilogon.org' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();
        $mailsubj = 'CILogon Service - ' . $summary;
        $mailmsg  = '
CILogon Service - ' . $summary . '
-----------------------------------------------------------
' . $detail . '
';

        mail($mailto,$mailsubj,$mailmsg,$mailfrom);
    }

    /********************************************************************
     * Function  : writeEnabledAndVerified                              *
     * Parameter : The two-factor authentication type (e.g., 'ga').     *
     * This method is called after a two-factor passcode has been       *
     * successfully validated. It updates the database (if necessary)   *
     * by setting verified and enabled to true for the given two-       *
     * factor method.                                                   *
     ********************************************************************/
    private static function writeEnabledAndVerified($tftype) {
        $needtowrite = false; // Write to database only if necessary
        if (!self::isVerified($tftype)) {
            self::setVerified($tftype);
            $needtowrite = true;
        }
        if (!self::isEnabled($tftype)) {
            self::setEnabled($tftype);
            $needtowrite = true;
        }
        if ($needtowrite) {
            self::write();
        }
    }

    /********************************************************************
     * Function  : isGACodeValid                                        *
     * Parameter : The six-digit code generated by Google Authenticator *
     * Returns   : True if the input code is valid, false otherwise.    *
     * This method takes in a passcode generated by the Google          *
     * Authenticator app and attempts to verify it. If the passcode is  *
     * valid, the database is updated for GA verified and enabled, and  *
     * true is returned.                                                *
     ********************************************************************/
    public static function isGACodeValid($code) {
        $valid = false;
        $secret = self::getRegister('ga');
        if ((strlen($code) > 0) && (strlen($secret) > 0)) {
            $valid = Google2FA::verify_key($secret,$code,1);
        }

        if ($valid) {
            self::writeEnabledAndVerified('ga');
        }

        return $valid;
    }

    /********************************************************************
     * Function  : isDuoCodeValid                                       *
     * Parameter : A response code generated by Duo Security login      *
     * Returns   : True if the duo code is valid, false otherwise.      *
     * This method takes in a "sig_response" as generated by the        *
     * Duo Security login code and attempts to verify it. If the        *
     * response is valid, the database is updated for Duo verified and  *
     * enabled, and true is returned.                                   *
     ********************************************************************/
    public static function isDuoCodeValid($code) {
        $valid = false;
        if (strlen($code) > 0) {
            $duoconfig = new duoconfig();
            $resp = Duo::verifyResponse(
                $duoconfig->param['ikey'],
                $duoconfig->param['skey'],
                $duoconfig->param['akey'],
                $code
            );
            if ($resp !== null) {
                $valid = true;
            }
        }

        if ($valid) {
            self::writeEnabledAndVerified('duo');
        }

        return $valid;
    }

    /********************************************************************
     * Function  : isDuoCodeValidREST                                   *
     * Parameter : A 6-digit passcode, a 7-character code, or "factor"  *
     *             index to be used when calling "duoconfig->auth()"    *
     * Returns   : True if the user is authenticated by Duo Security.   *
     *             False otherwise.                                     *
     * This method is called by ecpCheck to verify Duo Security         *
     * authentication for the current user. The code is either a six-   *
     * digit passcode as generated by the Duo Security app, a seven-    *
     * character code (1 letter + 6 digits) as sent by SMS, or a        *
     * number indicating which authentication method the user wants to  *
     * use (e.g., '1' often indicates 'push' authentication). This      *
     * method calls "duoconfig->push()" to validate the code.           *
     ********************************************************************/
    public static function isDuoCodeValidREST($code) {
        $valid = false;
        if (strlen($code) > 0) {
            $serialstr = getSerialStringFromDN(util::getSessionVar('dn'));
            $duoconfig = new duoconfig();
            $valid = $duoconfig->auth("$serialstr@cilogon.org",$code);
        }
        return $valid;
    }

    /********************************************************************
     * Function  : ecpCheck                                             *
     * Returns   : True if either (a) two-factor is disabled or         *
     *             (b) two-factor is enabled and a valid passcode       *
     *             was entered. False otherwise.                        *
     * This method is called when a user attempts to get a PKCS12       *
     * credential or a certificate via ECP. It first checks if two-     *
     * factor authentication is enabled. If not, then true is returned. *
     * If two-factor is enabled, it checks for a form variable          *
     * 'tfpasscode'. If the passcode is '0', two-factor is disabled,    *
     * and true is returned. Otherwise, the method attempts to validate *
     * the passcode. If successful, true is returned. Otherwise, this   *
     * method outputs a "401" header with the "realm" set to a user     *
     * prompt, and false is returned.                                   *
     ********************************************************************/
    public static function ecpCheck() {
        $retval = false; // Assume ecp twofactor check failed
        $tftype = self::getEnabled();

        if ($tftype == 'none') { // Two-factor disabled => okay to do ecp
            $retval = true;
        } else {
            $valid = false;
            // Look for form-submitted passcode
            $code = util::getPostVar('tfpasscode');
            if (strlen($code) > 0) { // Passcode was entered
                if ($code == '0') {  // '0' => Disable two-factor
                    self::setDisabled();
                    self::write();
                    $valid = true;
                } else { // Check if entered passcode is valid
                    if ((($tftype == 'ga') && 
                         (self::isGACodeValid($code))) ||
                        (($tftype == 'duo') && 
                         (self::isDuoCodeValidREST($code)))) {
                        $valid = true;
                    }
                }
            }

            if ($valid) { // Entered code was validated correctly
                $retval = true;
            } else { // Either no code, or code was not valid
                // Send "401 Unauthorized" header with user prompt in 'realm'
                $realm = '';
                $serialstr = getSerialStringFromDN(util::getSessionVar('dn'));

                if ($tftype == 'ga') { // Google Authenticator
                    $realm = "Two-factor authentication with Google Authenticator is required.\nPlease enter a passcode for '$serialstr@cilogon.org', or\n'0' to disable two-factor authentication for your account: ";

                } elseif ($tftype == 'duo') { // Duo Security
                    $duoconfig = new duoconfig();
                    $preauth = $duoconfig->preauth("$serialstr@cilogon.org");
                    if ($preauth === false) { // Problem with Duo
                        $realm = "Two-factor authentication with Duo Security is required.\nHowever, Duo servers are not responding. Please try again later or\nenter '0' to disable two-factor authentication for your account: ";
                    } else { // Check response type from Duo
                        if ($preauth['response']['result'] == 'allow') {
                            $retval = true; // No need for 401 header
                        } elseif ($preauth['response']['result'] == 'auth') {
                            $realm = $preauth['response']['prompt'];
                            // Add an option for '0. Disable two-factor'
                            $realm = preg_replace('/(1\. )/',
                                "0. Disable two-factor authentication\n $1",
                                $realm);
                            $realm = preg_replace('/\(1-/','(0-',$realm);
                        } else { // Either 'deny' or 'enroll' returned
                        $realm = "Two-factor authentication with Duo Security is required.\nHowever, Duo indicated that access to your account is denied.\nPlease contact help@cilogon.org for further assistance or\nenter '0' to disable two-factor authentication for your account: ";
                        }
                    }
                }

                if (strlen($realm) > 0) {
                    // Need to urlencode $realm due to \n and spaces
                    header('WWW-Authenticate: Basic realm="' .
                           urlencode($realm) . '"');
                    header('HTTP/1.0 401 Unauthorized');
                }
            }
        }
        
        return $retval;
    }

}

?>
