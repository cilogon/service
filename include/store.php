<?

/************************************************************************
 * Class name : store                                                   *
 * Description: This class is a wrapper for the CILogon Perl library.   *
 *                                                                      *
 * Example usage:                                                       *
 *     // Given a bunch of SAML attributes from a Shibboleth session,   *
 *     // get the database uid for that user to be utilized later.      *
 *     $uid = '';                                                       *
 *     $store = new store();                                            *
 *     $store->getUserObj('jsmith@illinois.edu',                        *
 *                        'urn:mace:incommon:uiuc.edu',                 *
 *                        'University of Illinois at Urbana-Champaign', *
 *                        'John','Smith','jsmith@illinois.edu');        *
 *     $status = $store->getUserSub('status');                          *
 *     if (!($status & 1)) { // OK status codes are even                *
 *         $uid = $store->getUserSub('uid');                            *
 *     }                                                                *
 *                                                                      *
 *     // Later in the code, re-fetch the user using this uid           *
 *     // and print out the stored attributes.                          *
 *     if (strlen($uid) > 0) {                                          *
 *         $store->getUserObj($uid);                                    *
 *         echo "Name = " . $store->getUserSub('firstName') . " " .     *
 *                          $store->getUserSub('lastName') . "\n";      *
 *         echo "DN = "   . $store->getUserSub('getDN') . "\n";         *
 *     }                                                                *
 ************************************************************************/

class store {

    // The Perl object instance.
    public $perlobj;

    // An object returned by CILogon::Store->getUser()
    public $userobj = null;

    // An object returned by CILogon::Store->getPortalParameters()
    public $portalobj = null;

    /* The various STATUS_* constants from Store.pm.  The keys of the   *
     * array are strings corresponding to the contant names.  The       *
     * values of the array are the integer values.  For example,        *
     *     $this->STATUS['STATUS_OK'] = 0;                              *
     * Use "array_search($this->getUserSub('status'),$this->STATUS)" to *
     * look up the STATUS_* name given the status integer value.        */
    public $STATUS = array();


    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new store object.                                  *
     * Default constructor.  This method initializes the PHP/Perl       *
     * environment to use the CILogon Perl classes.  It also populates  *
     * the $STATUS array with the various STATUS_* codes.               *
     ********************************************************************/
    function __construct() {
        // Set up the Perl environment
        $this->perlobj = new Perl();
        $this->perlobj->eval(
            'BEGIN {unshift(@INC,\'/usr/local/cilogon/lib/perl5\');}');
        $this->perlobj->eval('$ENV{\'CILOGON_ROOT\'} = \'/var/www/config/\'');
        $this->perlobj->eval('use CILogon::Store;');
        $this->perlobj->eval('use CILogon::User;');
        $this->perlobj->eval('use CILogon::IdentifierFactory;');
        $this->perlobj->eval('use CILogon::PortalParameters;');

        // Set the various STATUS variables
        $this->perlobj->eval('$st = CILogon::Store::STATUS_OK;');
        $this->STATUS['STATUS_OK'] = $this->perlobj->st;
        $this->perlobj->eval('$st = CILogon::Store::STATUS_OK_NEW_USER;');
        $this->STATUS['STATUS_OK_NEW_USER'] = $this->perlobj->st;
        $this->perlobj->eval('$st = CILogon::Store::STATUS_OK_USER_CHANGED;');
        $this->STATUS['STATUS_OK_USER_CHANGED'] = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_DUPLICATE_SERIAL;');
        $this->STATUS['STATUS_ERROR_DUPLICATE_SERIAL'] = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_DATABASE_FAILURE;');
        $this->STATUS['STATUS_ERROR_DATABASE_FAILURE'] = $this->perlobj->st;
        $this->perlobj->eval('$st = CILogon::Store::STATUS_ERROR_NOT_FOUND;');
        $this->STATUS['STATUS_ERROR_NOT_FOUND'] = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_MALFORMED_INPUT;');
        $this->STATUS['STATUS_ERROR_MALFORMED_INPUT'] = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_MISSING_PARAMETER;');
        $this->STATUS['STATUS_ERROR_MISSING_PARAMETER'] = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_MALFORMED_URI;');
        $this->STATUS['STATUS_ERROR_MALFORMED_URI'] = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_NO_IDENTITY_PROVIDER;');
        $this->STATUS['STATUS_ERROR_NO_IDENTITY_PROVIDER'] = $this->perlobj->st;
    }

    /********************************************************************
     * Function  : getUserObj                                           *
     * Parameters: Variable number of parameters: 1, 2, or 6.           *
     *             For 1 parameter: $uid (the database user identifier) *
     *             For 2 parameters: $remote_user and $idp              *
     *             For 6 parameters: $remote_user, $idp, $idpname,      *
     *                               $firstname, $lastname, $emailaddr  *
     * This method calls the getUser() method in the CILogon::Store     *
     * perl module.  It can accept 1, 2, or 6 parameters (just like     *
     * the getUser() method in the perl module).  The method does NOT   *
     * return anything.  Instead, it sets the object's $userobj         *
     * variable for later use by getUserSub().                          *
     ********************************************************************/
    function getUserObj() {
        $this->userobj = null;
        $numargs = func_num_args();
        if (($numargs == 1) || ($numargs == 2) || ($numargs == 6)) {
            // Set up the parameters to pass to getUser()
            $cmd = '$userobj = CILogon::Store->getUser(';
            for ($i = 0; $i < $numargs; $i++) {
                $cmd .= '\'' . func_get_arg($i) . '\'';
                if ($i < ($numargs-1)) {
                    $cmd .= ',';
                }
            }
            $cmd .= ');';
            // Call the getUser() method and save result in $userobj
            $this->perlobj->eval($cmd);
            $this->userobj = $this->perlobj->userobj;
        }
    }

    /********************************************************************
     * Function  : getLastUserObj                                       *
     * Parameter : $uid - the database user identifier                  *
     * This method calls the getLastArchivedUser() method in the        *
     * CILogon::Store perl module.  The method does NOT return          *
     * anything.  Instead, it sets the object's $userobj variable for   *
     * later use by getUserSub().  This method is useful when the       *
     * getUserObj() method returns a userobj that has a status code of  *
     * STATUS_OK_USER_CHANGED.  Then you can call getLastUserObj() to   *
     * find the previous attributes of the given uid.  Note that this   *
     * overwrites any existing userobj so that future calls to          *
     * getUserSub() will act on the "last archived" user.               *
     ********************************************************************/
    function getLastUserObj($uid) {
        $this->userobj = null;
        // Call the getLastArchivedUser() method and save result in $userobj
        $this->perlobj->eval(
            '$userobj = CILogon::Store->getLastArchivedUser(\''.$uid.'\');');
        $this->userobj = $this->perlobj->userobj;
    }

    /********************************************************************
     * Function  : getUserSub                                           *
     * Parameters: The name of the CILogon::User->subroutine to call.   *
     * Returns   : The string returned by the given subroutine.         *
     * This method is a wrapper to all of the various perl subroutines  *
     * in the CILogon::User perl module.  The parameter you pass in     *
     * corresponds to the subroutine name to be called.  See the        *
     * User.pm file for all available subrotines.  The important ones:  *
     *    firstName, lastName, remoteUser, idp, idpDisplayName,         *
     *    email, uid, status, getDN.                                    *
     ********************************************************************/
    function getUserSub($sub) {
        $retval = '';
        if ($this->userobj != null) {
            $retval = $this->userobj->{$sub}();
            // Sometimes, the PHP/Perl code returns strings in an array
            if (is_array($retval)) {
                $retval = key($retval);
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getPortalObj                                         *
     * Parameter : $tempcred - the temporary credential passed by a     *
     *             Community Portal, corresponds to "oauth_token".      *
     * This method calls the getPortalParameters() method in the        *
     * CILogon::Store perl module.  The method does NOT return          *
     * anything.  Instead, it sets the object's $portalobj variable for *
     * later use by getPortalSub().                                     *
     ********************************************************************/
    function getPortalObj($tempcred) {
        $this->portalobj = null;
        // Call the getPortalParamters() method and save result in $portalobj
        $this->perlobj->eval(
            '$portalobj = CILogon::Store->getPortalParameters(\'' .
                $tempcred . '\');');
        $this->portalobj = $this->perlobj->portalobj;
    }

    /********************************************************************
     * Function  : getPortalSub                                         *
     * Parameters: The name of the                                      *
     *             CILogon::PortalParameters->subroutine to call.       *
     * Returns   : The string returned by the given subroutine.         *
     * This method is a wrapper to all of the various perl subroutines  *
     * in the CILogon::PortalParameters perl module.  The parameter     *
     * you pass in corresponds to the subroutine name to be called.     *
     *  See the PortalParameters.pm file for all available subrotines.  *
     *  The important ones:                                             *
     *    callbackUri, failureUri, successUri, name (of Community       *
     *    Portal), tempCred, status.                                    *
     ********************************************************************/
    function getPortalSub($sub) {
        $retval = '';
        if ($this->portalobj != null) {
            $retval = $this->portalobj->{$sub}();
            // Sometimes, the PHP/Perl code returns strings in an array
            if (is_array($retval)) {
                $retval = key($retval);
            }
        }
        return $retval;
    }

}

?>
