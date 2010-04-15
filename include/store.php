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
 *     $store->getUser('jsmith@illinois.edu',                           *
 *                     'urn:mace:incommon:uiuc.edu',                    *
 *                     'University of Illinois at Urbana-Champaign',    *
 *                     'John','Smith','jsmith@illinois.edu');           *
 *     $status = $store->getUserSub('status');                          *
 *     if (!($status & 1)) { // OK status codes are even                *
 *         $uid = $store->getUserSub('uid');                            *
 *     }                                                                *
 *                                                                      *
 *     // Later in the code, re-fetch the user using this uid           *
 *     // and print out the stored attributes.                          *
 *     if (strlen($uid) > 0) {                                          *
 *         $store->getUser($uid);                                       *
 *         echo "Name = " . $store->getUserSub('firstName') . " " .     *
 *                          $store->getUserSub('lastName') . "\n";      *
 *         echo "DN = "   . $store->getUserSub('getDN') . "\n";         *
 *     }                                                                *
 ************************************************************************/

class store {

    // The Perl object instance.
    public $perlobj;

    // The various STATUS constants from Store.pm
    public $STATUS_OK;
    public $STATUS_OK_NEW_USER;
    public $STATUS_OK_USER_CHANGED;
    public $STATUS_ERROR_DUPLICATE_SERIAL;
    public $STATUS_ERROR_DATABASE_FAILURE;
    public $STATUS_ERROR_NOT_FOUND;
    public $STATUS_ERROR_MALFORMED_INPUT;
    public $STATUS_ERROR_MISSING_PARAMETER;
    public $STATUS_ERROR_MALFORMED_URI;
    public $STATUS_ERROR_NO_IDENTITY_PROVIDER;

    // A User object returned by CILogon::Store->getUser()
    public $userobj = null;


    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new store object.                                  *
     * Default constructor.  
     ********************************************************************/
    function __construct() {
        // Set up the Perl environment
        $this->perlobj = new Perl();
        $this->perlobj->eval(
            'BEGIN {unshift(@INC,\'/usr/local/cilogon/lib/perl5\');}');
        $this->perlobj->eval('$ENV{\'CILOGON_ROOT\'} = \'/var/www/config/\'');
        $this->perlobj->eval('use CILogon::Store;');
        $this->perlobj->eval('use CILogon::User;');
        $this->perlobj->eval('use CILogon::Exceptions;');
        $this->perlobj->eval('use CILogon::Namespaces;');
        $this->perlobj->eval('use CILogon::IdentifierFactory;');

        // Set the various STATUS variables
        $this->perlobj->eval('$st = CILogon::Store::STATUS_OK;');
        $this->STATUS_OK = $this->perlobj->st;
        $this->perlobj->eval('$st = CILogon::Store::STATUS_OK_NEW_USER;');
        $this->STATUS_OK_NEW_USER = $this->perlobj->st;
        $this->perlobj->eval('$st = CILogon::Store::STATUS_OK_USER_CHANGED;');
        $this->STATUS_OK_USER_CHANGED = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_DUPLICATE_SERIAL;');
        $this->STATUS_ERROR_DUPLICATE_SERIAL = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_DATABASE_FAILURE;');
        $this->STATUS_ERROR_DATABASE_FAILURE = $this->perlobj->st;
        $this->perlobj->eval('$st = CILogon::Store::STATUS_ERROR_NOT_FOUND;');
        $this->STATUS_ERROR_NOT_FOUND = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_MALFORMED_INPUT;');
        $this->STATUS_ERROR_MALFORMED_INPUT = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_MISSING_PARAMETER;');
        $this->STATUS_ERROR_MISSING_PARAMETER = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_MALFORMED_URI;');
        $this->STATUS_ERROR_MALFORMED_URI = $this->perlobj->st;
        $this->perlobj->eval(
            '$st = CILogon::Store::STATUS_ERROR_NO_IDENTITY_PROVIDER;');
        $this->STATUS_ERROR_NO_IDENTITY_PROVIDER = $this->perlobj->st;
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

}

?>
