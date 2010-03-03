<?php

require_once('../include/autoloader.php');
require_once('../include/content.php');
require_once('../include/shib.php');
require_once('../include/util.php');

$submit = getPostVar('submit');

/* Check if the user clicked a "Submit" button. */
if (strlen($submit) > 0) { 
    /* Check the CSRF protection cookie */
    if (!csrf::isCookieEqualToForm()) {
        /* ERROR! - CSRF cookie not equal to hidden form element! */
        csrf::deleteTheCookie();
        $submit = '';
    }
}

/* If the CSRF cookie was good and the user clicked a "Submit" *
 * button then do the appropriate action before displaying     *
 * the main Shibboleth Attributes Test Page.                   */
if ($submit == 'Add Your IdP to CILogon') {
    /* Add the current IdP entityID to the WAYF whitelist and reload */
    $white = new whitelist();
    if ($white->read()) {
        $entityID = getServerVar('HTTP_SHIB_IDENTITY_PROVIDER');
        if ($white->add($entityID)) {
            if ($white->write()) {
                $white->reload();
            }
        }
    }
}
printTestPage();

/************************************************************************
 * Function   : printTestPage                                           *
 * This function prints out the main Shibboleth Attributes Test page.   *
 * It displays a table of all of the shib attributes utilized by the    *
 * CILogon Service.  If all of the required attributes have been        *
 * released, a button is presented to the user to add the IdP to the    *
 * list of IdPs in the local Discovery Service (assuming the IdP was    *
 * was not already in the list).  If any attribute is missing, an       *
 * error message is printed out.                                        *
 ************************************************************************/
function printTestPage()
{
    $csrf = new csrf();
    $csrf->setTheCookie();

    $gotattrs = true;  /* Did we get all shib attributes? */

    $shibarray = getShibInfo();

    printHeader('Test Identity Provider');

    echo '
    <div id="pageHeader">
      <h1><span>Test Your Identity Provider</span></h1>
      <h2><span>Verify Shibboleth Attributes Released To Our
           Service</span></h2>
    </div>

    <div id="summaryDiv">
      <p class="p1"><span>Thank you for your interest in the CILogon
        Service.  This page allows an Identity Provider (<acronym 
        title="Identity Provider">IdP</acronym>) to verify that all
        necessary Shibboleth attributes have been released to the
        CILogon Service Provider (<acronym 
        title="Service Provider">SP</acronym>).</span></p>
      <p class="p2"><span>Below you will see the various Shibboleth
        attributes utilized by the CILogon <acronym 
        title="Service Provider">SP</acronym>.  If all required
        attributes are present, you can add your <acronym
        title="Identity Provider">IdP</acronym> to our Discovery Service
        <acronym title="Where Are You From">WAYF</acronym> (if it has not
        already been added).</span></p>
    </div>

    <div id="attributesDiv">
      <h2>Attributes</h2>
        <table cellpadding="5" rules="rows">
          <tr>
            <th align="right">Identity Provider (EntityID):</th>
            <td>' . $shibarray['Identity Provider'] . '</td>
            <td>';

    $gotattrs = ($gotattrs && 
                 printErrorOrOkayIcon($shibarray['Identity Provider']));

    echo '
            </td>
          </tr>
          <tr>
            <th align="right">Organization Name:</th>
            <td>' . $shibarray['Organization Name'] . '</td>
            <td> </td>
          </tr>
          <tr>
            <th align="right">Home Page:</th>
            <td><a target="_blank" href="' . $shibarray['Home Page'] . '">' .
            $shibarray['Home Page'] . '</a></td>
            <td> </td>
          </tr>
          <tr>
            <th align="right">User Identifier (REMOTE_USER):</th>
            <td>' . $shibarray['User Identifier'] . '</td>
            <td>';

    $gotattrs = ($gotattrs && 
                 printErrorOrOkayIcon($shibarray['User Identifier']));

    echo '
            </td>
          </tr>
          <tr>
            <th align="right">ePTID / ePPN:</th>
            <td>' . ((strlen($shibarray['ePTID']) > 0) ? 'Yes' : 'No') .
            ' / ' . ((strlen($shibarray['ePPN']) > 0) ? 'Yes' : 'No') . 
            '</td>
            <td> </td>
          </tr>
          <tr>
            <th align="right">First Name (givenName):</th>
            <td>' . $shibarray['First Name'] . '</td>
            <td>';

    $gotattrs = ($gotattrs && 
                 printErrorOrOkayIcon($shibarray['First Name']));

    echo '
            </td>
          </tr>
          <tr>
            <th align="right">Last Name (sn):</th>
            <td>' . $shibarray['Last Name'] . '</td>
            <td>';

    $gotattrs = ($gotattrs && 
                 printErrorOrOkayIcon($shibarray['Last Name']));

    echo '
            </td>
          </tr>
          <tr>
            <th align="right">Email Address (email):</th>
            <td>' . $shibarray['Email Address'] . '</td>
            <td>';

    $gotattrs = ($gotattrs && 
                 printErrorOrOkayIcon($shibarray['Email Address']));
    echo '
            </td>
          </tr>
          <tr>
            <th align="right">Level of Assurance (assurance):</th>
            <td>' . $shibarray['Level of Assurance'] . '</td>
            <td> </td>
          </tr>';

    if ((strlen($shibarray['Technical Name']) > 0) &&
        (strlen($shibarray['Technical Address']) > 0)) {
        echo '
          <tr>
            <th align="right">Technical Contact:</th>
            <td>' . $shibarray['Technical Name'] . ' &lt;'.
                    $shibarray['Technical Address'] . '&gt;</td>
            <td> </td>
          </tr>';
    }

    if ((strlen($shibarray['Administratvie Name']) > 0) &&
        (strlen($shibarray['Administratvie Address']) > 0)) {
        echo '
          <tr>
            <th align="right">Administrative Contact:</th>
            <td>' . $shibarray['Administrative Name'] . ' &lt;'.
                    $shibarray['Administrative Address'] . '&gt;</td>
            <td> </td>
          </tr>';
    }

    echo '</table>
    </div>

    <div id="resultsDiv">
    ';

    if ($gotattrs) {
        $white = new whitelist();
        $white->read();

        echo '
        <h2>Success!</h2>
        <p class="p1"><span>Congratulations! All required attributes have
        been released from your organization.</span></p>
        ';

        if ($white->exists($shibarray['Identity Provider'])) {
            echo '
            <p class="p2"><span>Your organization\'s Identity
            Provider is available for authentication with the CILogon
            Service Provider.  Please continue to the
            CILogon Service home page.</span></p>
            <div id="buttonDiv">
              <form action="https://cilogon.org/" method="post">
                <input class="submit" type="submit" name="submit"
                       value="Continue to CILogon Service Home Page" />
              </form>
            </div>
            ';
        } else {
            echo '
            <p class="p2"></span>You can add your Identity
            Provider (<acronym title="Identity Provider">IdP</acronym>) 
            to our Discovery Service so that users at your
            organization can utilize the CILogon Service Provider.
            </span></p>
            <div id="buttonDiv">
              <form action="' . basename(__FILE__) . '" method="post">
                <input class="submit" type="submit" name="submit"
                       value="Add Your IdP to CILogon" />
              </form>
            </div>
            ';
        }
    } else { /* Didn't get all of the required shib attributes */
        echo '
        <h2>Failed</h2>
        <p class="p1"><span>We\'re sorry, but some of the required attributes
        have not been released by your organization\'s Identity Provider
        (<acronym title="Identity Provider">IdP</acronym>), so your
        organization cannot access our service at this time.  For more
        information, please contact <a
        href="mailto:info@cilogon.org">info@cilogon.org</a>.</span></p>
        ';
    }

    echo '
      </div>
    ';

    printFooter();
}

/************************************************************************
 * Function   : printErrorOrOkayIcon                                    *
 * Parameter  : A string corresponding to a Shibboleth attribute.       *
 * Returns    : True if the string length of the input parameter is     *
 *              greater than zero, false otherwise.                     *
 * Side Effect: An "error" or "okay" icon is output as an HTML <img>.   *
 * This function takes in a Shibboleth attribute string.  If the length *
 * of the string is greater than zero, then an "okay" icon is output    *
 * to HTML and the function returns 'true'.  If the length of the       *
 * string is zero, then an "error" icon is output to HTML and the       *
 * function returns 'false'.                                            *
 ************************************************************************/
function printErrorOrOkayIcon($attr='')
{
    $retval = false;
    $icon = 'error';

    if (strlen($attr) > 0) {
        $icon = 'okay';
        $retval = true;
    }

    echo '&nbsp;<img src="/images/' . $icon . 'Icon.png" 
          alt="&laquo; ' . $icon . '" width="14" height="14" />';

    return $retval;
}

?>
