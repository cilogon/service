<?php

/**
 * This file contains functions called by index-site.php. The index-site.php
 * file should include this file with the following statement at the top:
 *
 * require_once __DIR__ . '/index-functions.php';
 */

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\DBService;
use CILogon\Service\Loggit;

/**
 * printLogonPage
 *
 * This function first checks if the user_code has been entered and
 * matching client registration information has been found. If not, then go
 * to printUserCodePage instead. Otherwise, print out the HTML for the
 * "Select an Identity Provider" page along with the client registration
 * info.
 */
function printLogonPage()
{
    if (!verifyUserCodeParam()) {
        printUserCodePage();
    } else {
        $log = new Loggit();
        $log->info('Welcome page hit.');

        Content::printHeader(
            'Welcome To The CILogon Device Authorization Service'
        );

        Content::printOIDCConsent();
        Content::printWAYF(true, true);
        Content::printFooter();
    }
}

/**
 * printUserCodePage
 *
 * If the user has not yet entered a user_code, or if the previously entered
 * user_code has expired or is not found, print out a form to prompt the
 * user to enter the device user_code.
 */
function printUserCodePage()
{
    $log = new Loggit();
    $log->info('User code page hit.');

    Content::printHeader(
        'Welcome To The CILogon Device Authorization Service'
    );
    Content::printCollapseBegin(
        'usercodedefault',
        'CILogon Device Flow User Code',
        false
    );

    // Check for any previously generated error message
    $user_code_error_msg = Util::getSessionVar('user_code_error_msg');
    Util::unsetSessionVar('user_code_error_msg');
    if (strlen($user_code_error_msg) > 0) {
        echo '
            <div class="alert alert-danger alert-dismissable fade show" role="alert">';
        echo $user_code_error_msg;
        echo '
              <button type="button" class="close" data-dismiss="alert"
                      aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }

    echo '
      <div class="card-body px-5">
        <div class="card-text my-2">
          Please enter the user code displayed on your device.
          If you do not have a user code, please proceed to the main
          <a href="..">CILogon site</a>.
        </div> <!-- end row -->
    ';

    Content::printFormHead();

    echo '
        <div class="form-group col-lg-6 offset-lg-3 col-md-8 offset-md-2 col-sm-10 offset-sm-1 mt-3">
          <label for="user-code">Enter User Code</label>
          <div class="form-row">
            <div class="col-11">
              <input type="text" name="user_code" id="user-code"
              required="required" autocomplete="off" maxlength="40"
              class="form-control upper" aria-describedby="user-code-help"
              oninput="this.value=this.value.replace(/[^a-zA-Z0-9\-\s\_]/,\'\'); upperCaseF(this);"
              title="User code is alphanumeric"/>
              <div class="invalid-tooltip">
                Please enter a valid user code.
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="col-11">
              <small id="user-code-help" class="form-text text-muted">
                Enter the user code displayed on your device.
              </small>
            </div>
          </div>
        </div> <!-- end form-group -->

        <div class="form-group">
          <div class="form-row align-items-center">
            <div class="col text-center">
              <input type="submit" name="submit"
              class="btn btn-primary submit"
              value="Enter User Code"/>
            </div>
          </div>
        </div> <!-- end form-group -->

        </form>
      </div> <!-- end card-body-->';

    Content::printCollapseEnd();
    Content::printFooter();
}

/**
 * printMainPage
 *
 * This is the page the user is redirected to after successfully logging in
 * with their chosen identity provider OR when the user clicks the 'Cancel'
 * button to deny the device authorization grant flow. When the 'Cancel'
 * button is clicked, a PHP session variable 'user_code_denied' is set. In
 * either case, if the PHP session contains the user_code entered by the
 * user, the userCodeApproved dbService method is called to let the OA4MP
 * service know that the user has approved/denied use of the user_code.
 */
function printMainPage()
{
    $log = new Loggit();

    // If user_code_denied is set (by clicking 'Cancel') then set approved
    // to zero when calling userCodeApproved.
    $user_code_approved = 1; // Assume user approved use of the user_code
    if (strlen(Util::getSessionVar('user_code_denied')) > 0) {
        Util::unsetSessionVar('user_code_denied');
        $user_code_approved = 0;
    }

    // Check the PHP session for the user_code entered by the user
    $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    Util::unsetSessionVar('clientparams');
    $user_code = @$clientparams['user_code'];

    $errstr = '';
    if (strlen($user_code) > 0) {
        $log->info('Calling userCodeApproved dbService method...');
        $dbs = new DBService();
        if (
            ($dbs->userCodeApproved($user_code, $user_code_approved)) &&
            (!($dbs->status & 1))
        ) { // STATUS_OK codes are even
            // SUCCESSFULLY told database about decision to approve/deny
        } else { // STATUS_ERROR code returned
            // There was a problem with the user_code
            $errstr = 'Error confirming user code.'; // Generic error message
            if (!is_null($dbs->status)) {
                $errstr = array_search($dbs->status, DBService::$STATUS);
                // Customize error messages for Device Authz Grant flow
                if ($dbs->status == 0x10001) {
                    $errstr = 'Error confirming user code: Code not found. ' .
                        'This can happen when the user code has expired and ' .
                        'is no longer avaiable in the system.';
                } elseif ($db->status == 0x10003) {
                    $errstr = 'Error confirming user code: Code expired. ' .
                        'Please return to your device and start a new request.';
                }
            }
        }
    } else { // No user_code in PHP session - weird error!
        $errstr = 'Error confirming user code: Code not found. ' .
            'Please enable cookies in your web browser.';
    }

    $log->info('User Code Verified page hit.');

    Content::printHeader('User Code Approval');
    Content::printCollapseBegin(
        'usercodeapproval',
        'CILogon User Code Verification',
        false
    );

    // Check for any previously generated error message
    if (strlen($errstr) > 0) {
        echo '
            <div class="alert alert-danger show" role="alert">';
        echo $errstr;
        echo '
            </div>';
    } else {
        echo '
      <div class="card-body px-5">
        <div class="card-text my-2">
          You have successfully ' .
          ($user_code_approved ? 'approved ' : 'denied ') .
          'the user code. Please return to your device for further
          instructions.
        </div> <!-- end row -->
      </div> <!-- end card-body-->';
    }

    Content::printCollapseEnd();
    Content::printFooter();
}

/**
 * verifyUserCodeParam
 *
 * This function checks for the presence of a 'user_code' query parameter.
 * If found, the dbService checkUserCode action is called to get the
 * associated client_id. Then the database is queried to find the details of
 * that client_id. The results are stored in the clientparams PHP session
 * variable. If no user_code was passed in, then the clientparams PHP
 * session variable is used. The final check verifies that all required
 * client info is available. If all of that is true, then this function
 * returns true.
 *
 * @return bool True if all required client registration info associated
 *         with the user_code is available. False otherwise.
 */
function verifyUserCodeParam()
{
    $retval = false; // Assume user code & other session info is not valid

    $log = new Loggit();

    // If idphint/selected_idp were previously set in the clientparams
    // PHP session variable, get them this time around.
    $clientparams = array_intersect_key(
        json_decode(Util::getSessionVar('clientparams'), true),
        ['idphint' => 1, 'selected_idp' => 1]
    );

    // If a user_code was passed in, use that to get the associated
    // clientparams. Otherwise, get clientparams from the PHP session.
    $user_code = Util::getGetorPostVar('user_code');
    if (strlen($user_code) > 0) {
        Util::unsetSessionVar('clientparams'); // Don't use any previous values
        $log->info('Calling checkUserCode dbService method...');
        $dbs = new DBService();
        if (
            ($dbs->checkUserCode($user_code)) &&
            (!($dbs->status & 1))
        ) { // STATUS_OK codes are even
            if (strlen($dbs->client_id) > 0) {
                // Use the client_id associated with the user_code to get
                // the rest of the OAuth2 client registration information.
                $clientparams['user_code'] = $dbs->user_code;
                $clientparams['client_id'] = $dbs->client_id;
                $clientparams['scope'] = $dbs->scope;
                // getOIDCClientParams assumes client_id is stored in the
                // passed-in $clientparams variable.
                Util::getOIDCClientParams($clientparams);
                // If no scope was requested, then assume ALL scopes
                // 'scope' is a space-separated string, while 
                // client_scopes is a JSON list; need to transform into
                // space-separated string.
                if (strlen($clientparams['scope']) == 0) {
                    $clientparams['scope'] = implode(
                        ' ',
                        json_decode($clientparams['client_scopes'], true)
                    );
                }
            } else {
                Util::setSessionVar('user_code_error_msg', 'Unable to find a client matching the user code.');
            }
        } else { // STATUS_ERROR code returned
            $errstr = 'Error checking user code.'; // Generic error message
            if (!is_null($dbs->status)) {
                $errstr = array_search($dbs->status, DBService::$STATUS);
                // Customize error messages for Device Authz Grant flow
                if ($dbs->status == 0x10001) {
                    $errstr = 'User code not found.';
                } elseif ($db->status == 0x10003) {
                    $errstr = 'User code expired.';
                }
            }
            Util::setSessionVar('user_code_error_msg', $errstr);
        }
    } else { // No user_code passed in, so check the PHP session clientparams
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
    }

    // If no error so far, check all of the client parameters
    if (
        (strlen(Util::getSessionVar('user_code_error_msg')) == 0) &&
        (isset($clientparams['user_code'])) &&
        (isset($clientparams['client_id'])) &&
        (isset($clientparams['scope'])) &&
        (isset($clientparams['client_name'])) &&
        (isset($clientparams['client_home_url'])) &&
        (isset($clientparams['client_callback_uri'])) &&
        (isset($clientparams['client_scopes'])) &&
        (isset($clientparams['clientstatus'])) &&
        (!($clientparams['clientstatus'] & 1))
    ) { // STATUS_OK codes are even
        $retval = true;
        Util::setSessionVar('clientparams', json_encode($clientparams));
    } else {
        Util::unsetSessionVar('clientparams');
    }

    // Save idphint/selected_idp from query parameters to PHP session
    $idphint = Util::getGetVar('idphint');
    $selected_idp = Util::getGetVar('selected_idp');
    if (
        (strlen($idphint) > 0) ||
        (strlen($selected_idp) > 0)
    ) {
        if (strlen($idphint) > 0) {
            $clientparams['idphint'] = $idphint;
        }
        if (strlen($selected_idp) > 0) {
            $clientparams['selected_idp'] = $selected_idp;
        }
        Util::setSessionVar('clientparams', json_encode($clientparams));
    }

    return $retval;
}
