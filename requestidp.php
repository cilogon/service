<?php

require_once('include/autoloader.php');
require_once('include/content.php');
require_once('include/shib.php');
require_once('include/util.php');

$submit = getPostVar('submit');
$validator = new EmailAddressValidator();
define('DEFAULT_OPTION_TEXT','-- Choose one -or- Type one in below --');

/* Check if the user clicked the "Submit" button. */
if (strlen($submit) > 0) { 
    /* Check the CSRF protection cookie */
    if (!csrf::isCookieEqualToForm()) {
        /* ERROR! - CSRF cookie not equal to hidden form element! */
        csrf::deleteTheCookie();
        $submit = '';
    }
}

/* If the CSRF cookie was good and the user clicked the "Submit" *
 * button then read in the various form elements and verify that  *
 * the form values are non-empty (or at least make sense).        */
if ($submit == 'Submit') {
    $yourName = getPostVar('yourName');
    $emailAddr = getPostVar('emailAddr');
    $selectIdP = getPostVar('selectIdP');
    $otherIdP = getPostVar('otherIdP');
    $comments = getPostVar('comments');

    /* Check for non-empty Name, Email, IdP, and valid Email Address */
    if ((strlen($yourName) > 2) &&
        (strlen($emailAddr) > 2) &&
        ($validator->check_email_address($emailAddr)) &&
            (($selectIdP != DEFAULT_OPTION_TEXT) ||
             (strlen($otherIdP) > 2))) {
        /* Everything is okay! Send email request and print "Thank you!" */
        printRequestSubmitted($yourName,$emailAddr,
                              $selectIdP,$otherIdP,$comments);
    } else {
        /* User entered some blank (or invalid) fields.  Show the *
         * Main Form page again, with their inputs, PLUS the      *
         * places that there are errors.                          */
        printRequestForm(true,$yourName,$emailAddr,
                         $selectIdP,$otherIdP,$comments);
    }

} else {   
    /* Default condition, print the Main form */
    printRequestForm();
}

/************************************************************************
 * Function   : printRequestForm                                        *
 * Parameters : (1) Boolean for if we should try to verify that there   *
 *                  are valid entries in the various fields.            *
 *              (2) The input Name of the user.                         *
 *              (3) The input email address of the user.                *
 *              (4) The selected InCommon IdP.                          *
 *              (5) The input "other" IdP.                              *
 *              (6) The input Comments field.                           *
 * This function prints out the main "Request An Identity Provider"     *
 * page to the user.  If called with no parameters, it simply prints    *
 * out the page with empty form fields.  If any of the parameters are   *
 * passed in, then those values will populate the form fields.  Also,   *
 * if $verify is set to true, the Name, Email, and IdP fields are       *
 * checked for valid (non-empty) input.  For any bad input, a little    *
 * error icon is displayed next to that field, and the user is asked    *
 * to fix the errors before submitting again.                           *
 ************************************************************************/
function printRequestForm($verify=false,$yourName='',$emailAddr='',
                          $selectIdP='',$otherIdP='',$comments='') 
{
    global $validator;

    $csrf = new csrf();
    $csrf->setTheCookie();

    $goterror = false;  /* Did we find any errors in the form? */

    $idps = getInCommonIdPs();

    printHeader('Request Identity Provider');

    echo '
    <div id="pageHeader">
      <h1><span>Request Identity Provider</span></h1>
      <h2><span>Help Us Add Your Organization To Our Service</span></h2>
    </div>

    <div id="summaryDiv">
      <p class="p1"><span>Thank you for your interest in the CILogon Service.
        The CILogon Service Provider (<acronym 
        title="Service Provider">SP</acronym>) is a member of the <a
        target="_blank" href="http://www.incommonfederation.org/">InCommon
        Federation</a>, which brings together over 200 educational
        institutions and professional organizations.  However, before you can
        utilize the CILogon Service, your organization\'s Identity Provider
        (<acronym title="Identity Provider">IdP</acronym>) must release
        specific user attributes to our site.</span></p>
      <p class="p2"><span>You can help us by letting us know that you are
        interested in using our service.  Please enter your contact
        information and select your organization below.  We will contact
        the appropriate administrators in an effort to have your
        organizations\'s <acronym title="Identity Provider">IdP</acronym>
        operate with our <acronym 
        title="Service Provider">SP</acronym>.</span></p>
    </div>

    <div id="contactForm">
      <form action="requestidp.php" method="post" class="requestForm">
      <fieldset>
      <legend><span>Contact Information</span></legend>
      <ol>
      <li>
      <label for="yourName">Your Name</label>
      <input id="yourName" name="yourName" class="text" type="text" 
       size="50" maxlength="80" value="' . $yourName . '" />';

    if (($verify) && (strlen($yourName) <= 2)) {
        printErrorIcon();
        $goterror = true;
    }

    echo '
      </li>
      <li>
      <label for="emailAddr">Email Address</label>
      <input id="emailAddr" name="emailAddr" class="text" type="text" 
       size="50" maxlength="80" value="' . $emailAddr . '" />';

    if (($verify) && (!$validator->check_email_address($emailAddr))) {
        printErrorIcon();
        $goterror = true;
    }

    echo '
      </li>
      </ol>
      </fieldset>
      <fieldset>
      <legend><span>Identity Provider</span></legend>
      <ol>
      <li>
      <label for="selectIdP">Select an IdP</label>
      <select name="selectIdP" id="selectIdP" class="select">
      <option value="' . DEFAULT_OPTION_TEXT . '"';

    if (($selectIdP == DEFAULT_OPTION_TEXT) ||
        (strlen($selectIdP) == 0)) {
        echo ' selected="selected"';
    }
        
    echo '>' . DEFAULT_OPTION_TEXT . '</option>
    ';

    foreach ($idps as $value) {
        echo '
        <option value="'.$value.'"';
        if ($value == $selectIdP) {
            echo ' selected="selected"';
        }
        echo '>'.$value.'</option>';
    }

    echo '
      </select>';

    if (($verify) && ($selectIdP == DEFAULT_OPTION_TEXT) &&
        (strlen($otherIdP) <= 2)) {
        printErrorIcon();
        $goterror = true;
    }

    echo '
      </li>
      <li>
      <label for="otherIdP">Other IdP</label>
      <input id="otherIdP" name="otherIdP" class="text" type="text" 
       size="50" maxlength="80" value="' . $otherIdP . '" />
      </li>
      <li>
      <label for="comments">(Optional) Comments</label>
      <textarea id="comments" name="comments" class="textarea" 
      cols="50" rows="5">' .  $comments . '</textarea>
      </li>
      </ol>
      </fieldset>
      <fieldset class="submit">
      <input class="submit" type="submit" name="submit" value="Submit"
      />';

    if ($goterror) {
        echo '
        <span class="fixerror">(Please fix the errors above first.)</span>
        ';
    }

    echo '
      </fieldset>
    ' .  $csrf->getHiddenFormElement() . '
      </form>
    </div>
    ';

    printFooter();
}

/************************************************************************
 * Function   : printRequestSubmitted                                   *
 * Parameters : (1) The input Name of the user.                         *
 *              (2) The input email address of the user.                *
 *              (3) The selected InCommon IdP.                          *
 *              (4) The input "other" IdP.                              *
 *              (5) The input Comments field.                           *
 * This function is called when when the user clicks the "Submit"       *
 * button with valid entries in the various form fields.  It composes   *
 * an email to "info@cilogon.org" and then prints out a "Thank You"     *
 * page to the user.                                                    *
 ************************************************************************/
function printRequestSubmitted($yourName,$emailAddr,
                               $selectIdP,$otherIdP,$comments)
{
    $loggit = new loggit('mail','tfleury@illinois.edu');
    $loggit->info("
CILogon Service - New Identity Provider Request!
---------------
Name          = $yourName
Email Address = $emailAddr
Selected IdP  = $selectIdP
Other IdP     = $otherIdP
Comments      = $comments
");

    printHeader('Identity Provider Requested');

    echo '
    <div id="pageHeader">
      <h1><span>Request Received</span></h1>
      <h2><span>Thank You For Your Interest In the CILogon Service</span></h2>
    </div>

    <div id="summaryDiv">
      <p class="p1"><span>Thank you!  Your request for a new Identity
      Provider has been sent to the CILogon Service development team.
      We will contact the appropriate administrators at your organization
      and ask that they release the necessary attributes to our Service
      Provider (<acronym title="Service Provider">SP</acronym>).</span></p>
      <p class="p2"><span>Please allow several business days for your
      organization\'s administrators to reply to your request.  We will
      contact you as soon as we have any information.  Thank you again for
      your request.</span></p>
      <p class="p3"><span>If you have any further questions, please
      contact us at <a
      href="mailto:info@cilogon.org">info@cilogon.org</a>.  You can now <a
      href="https://cilogon.org/">return to the main CILogon Service
      page</a>.</span></p>
    </div>
    ';

    printFooter();
}


/************************************************************************
 * Function   : printErrorIcon                                          *
 * This function prints out the HTML for the little error icon which    *
 * appears after any input fields that contain bad information.         *
 ************************************************************************/
function printErrorIcon()
{
    echo '&nbsp;<img src="images/errorIcon.png" alt="&laquo; Error" 
    title="Enter valid data" width="14" height="14" />';
}

?>
