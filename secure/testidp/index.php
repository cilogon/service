<?php

require_once('../../include/util.php');
require_once('../../include/autoloader.php');
require_once('../../include/content.php');
require_once('../../include/shib.php');

define('ADD_SUBMIT_TEXT','Add Your IdP to the CILogon Service');

/* Check the csrf cookie against either a hidden <form> element or a   *
 * PHP session variable, and get the value of the "submit" element.    */
$submit = csrf::verifyCookieAndGetSubmit();

/* Get the full list of InCommon IdPs and the whitelist of *
 * IdPs available to the CILogon Service.                  */
$incommon = new incommon();
$whitelist = new whitelist();

/* If the CSRF cookie was good and the user clicked a "Submit" *
 * button then do the appropriate action before displaying     *
 * the main Shibboleth Attributes Test Page.                   */
if ($submit == ADD_SUBMIT_TEXT) {
    /* Add the current IdP entityID to the WAYF whitelist and reload */
    $entityID = getServerVar('HTTP_SHIB_IDENTITY_PROVIDER');
    if (($incommon->exists($entityID)) &&
        ($whitelist->add($entityID))) {
        $whitelist->write();
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
    global $incommon;
    global $whitelist;
    global $csrf;

    $gotattrs = false;  // Did we get all shib attributes?

    $shibarray = getShibInfo();

    printHeader('Test Identity Provider');
    printPageHeader('Test Your Organization\'s Identity Provider');

    echo '
    <div class="boxed">
      <div class="boxheader">
        Verify SAML Attribute Release Policy
      </div>

    <p>
    Thank you for your interest in the CILogon Service.  This page allows
    the administrator of an Identity Provider (<acronym
    title="Identity Provider">IdP</acronym>) to verify that all necessary
    SAML attributes have been released to the CILogon Service Provider
    (<acronym title="Service Provider">SP</acronym>).  Below you will see
    the various attributes required by the CILogon Service and their values
    as released by your IdP.  If all required attributes are present, you
    can add your <acronym title="Identity Provider">IdP</acronym> to the
    list of organizations available to the CILogon Service (assuming it has
    not already been added).
    </p>

    <div class="summary">
    <h2>Summary</h2>
    ';

    if ((strlen($shibarray['Identity Provider']) > 0) &&
        (strlen($shibarray['User Identifier']) > 0) &&
        (strlen($shibarray['Email Address']) > 0) &&
        (strlen($shibarray['Organization Name']) > 0) &&
            ((strlen($shibarray['Display Name']) > 0) ||
                 ((strlen($shibarray['First Name']) > 0) &&
                  (strlen($shibarray['Last Name']) > 0)))) {
        $gotattrs = true;
    }

    if ($gotattrs) {
        echo '<div class="icon">';
        printIcon('okay');
        echo ' 
        </div>
        <div class="summarytext">
        <p>
        All required attributes have been released by your <acronym
        title="Identity Provider">IdP</acronym>.  For details of the various
        attributes utilized by the CILogon Service and their current values,
        see the sections below.
        </p>
        ';
        if ((!$whitelist->exists($shibarray['Identity Provider'])) &&
            ($incommon->exists($shibarray['Identity Provider']))) {
            echo '
            <p class="addsubmit">
            <form action="' , getScriptDir() , '" method="post">
              <input class="submit" type="submit" name="submit"
                     value="' , ADD_SUBMIT_TEXT , '" />' ,
            $csrf->getHiddenFormElement() , '
            </form>
            </p>
            ';
        } else {
            echo '
            <p class="addsubmit">
            <a href="/">Proceed to the CILogon
            Service</a>.
            </p>
            ';
        }
        echo '
        </div>
        ';
    } else {
        echo '<div class="icon">';
        printIcon('error','Missing one or more attributes.');
        echo '
        </div>
        <div class="summarytext">
        <p>
        One or more of the attributes required by the CILogon Service are
        not available.  Please see the sections below for details.  Contact
        <a href="mailto:help@cilogon.org">help&nbsp;@&nbsp;cilogon.org</a>
        for additional information and assistance.
        </p>
        </div>
        ';
    }

    echo '
    </div> <!-- summary -->

    <noscript>
    <div class="nojs">
    Javascript is disabled.  In order to expand or collapse the sections
    below, please enable Javascript in your browser.
    </div>
    </noscript>

    <div class="summary">
        <div id="saml1" style="display:' , 
            ($gotattrs ? "inline" : "none" ) , 
        '"><span class="expander"><a 
        href="javascript:showHideDiv(\'saml\',-1)"><img 
        src="/images/triright.gif" alt="&rArr;" width="14" height="14" /> 
        SAML Attributes</a></span>
        </div>
        <div id="saml2" style="display:' ,
            ($gotattrs ? "none" : "inline" ) , 
        '"><span class="expander"><a 
        href="javascript:showHideDiv(\'saml\',-1)"><img 
        src="/images/tridown.gif" alt="&dArr;" width="14" height="14" /> 
        SAML Attributes</a></span>
        </div>
        <br class="clear" />
        <div id="saml3" style="display:' , 
            ($gotattrs ? "none" : "inline" ) , 
        '">

        <table cellpadding="5">
          <tr class="odd">
            <th>Identity Provider (entityID):</th>
            <td>' , $shibarray['Identity Provider'] , '</td>
            <td>';

    if (strlen($shibarray['Identity Provider']) == 0) {
        printIcon('error','Missing the entityID of the IdP.');
    }

    echo '
            </td>
          </tr>

          <tr>
            <th>ePTID:</th>
            <td>' , $shibarray['ePTID'] , '</td>
            <td>';
            
    if ((strlen($shibarray['ePPN']) == 0) &&
        (strlen($shibarray['ePTID']) == 0)) {
        printIcon('error','Must have either ePPN -OR- ePTID.');
    }
            
    echo '
            </td>
          </tr>

          <tr class="odd">
            <th>ePPN:</th>
            <td>' , $shibarray['ePPN'] , '</td>
            <td>';
            
    if ((strlen($shibarray['ePPN']) == 0) &&
        (strlen($shibarray['ePTID']) == 0)) {
        printIcon('error','Must have either ePPN -OR- ePTID.');
    }
            
    echo '
            </td>
          </tr>

          <tr>
            <th>First Name (givenName):</th>
            <td>' , $shibarray['First Name'] , '</td>
            <td>';

    if ((strlen($shibarray['First Name']) == 0) &&
        (strlen($shibarray['Display Name']) == 0)) {
        printIcon('error','Must have either givenName + sn -OR- displayName.');
    }

    echo '
            </td>
          </tr>
          <tr class="odd">
            <th>Last Name (sn):</th>
            <td>' , $shibarray['Last Name'] , '</td>
            <td>';

    if ((strlen($shibarray['Last Name']) == 0) &&
        (strlen($shibarray['Display Name']) == 0)) {
        printIcon('error','Must have either givenName + sn -OR- displayName.');
    }

    echo '
            </td>
          </tr>
          <tr>
            <th>Display Name (displayName):</th>
            <td>' , $shibarray['Display Name'] , '</td>
            <td>';

    if ((strlen($shibarray['Display Name']) == 0) &&
            ((strlen($shibarray['First Name']) == 0) ||
             (strlen($shibarray['Last Name']) == 0))) {
        printIcon('error','Must have either displayName -OR- givenName + sn.');
    }

    echo '
            </td>
          </tr>
          <tr class="odd">
            <th>Email Address (email):</th>
            <td>' , $shibarray['Email Address'] , '</td>
            <td>';

    if (strlen($shibarray['Email Address']) == 0) {
        printIcon('error','Missing valid email address.');
    }

    echo '
            </td>
          </tr>
          <tr>
            <th>Level of Assurance (assurance):</th>
            <td>' , $shibarray['Level of Assurance'] , '</td>
            <td> </td>
          </tr>

        </table>
        </div> <!-- saml3 -->
    </div> <!-- summary -->

    <div class="summary">
        <div id="meta1" style="display:' , 
            ($gotattrs ? "inline" : "none" ) , 
        '"><span class="expander"><a 
        href="javascript:showHideDiv(\'meta\',-1)"><img 
        src="/images/triright.gif" alt="&rArr;" width="14" height="14" /> 
        Metadata Attributes</a></span>
        </div>
        <div id="meta2" style="display:' , 
            ($gotattrs ? "none" : "inline" ) , 
        '"><span class="expander"><a 
        href="javascript:showHideDiv(\'meta\',-1)"><img 
        src="/images/tridown.gif" alt="&dArr;" width="14" height="14" /> 
        Metadata Attributes</a></span>
        </div>
        <br class="clear" />
        <div id="meta3" style="display:' , 
            ($gotattrs ? "none" : "inline" ) , 
        '">

        <table cellpadding="5">
          <tr class="odd">
            <th>Organization Name:</th>
            <td>' , $shibarray['Organization Name'] , '</td>
            <td>';

    if (strlen($shibarray['Organization Name']) == 0) {
        printIcon('error','Could not find &lt;OrganizationDisplayName&gt;'. 
                          ' in InCommon metadata.');
    }

    echo '
            </td>
          </tr>
          <tr>
            <th>Home Page:</th>
            <td><a target="_blank" href="' , $shibarray['Home Page'] , '">' ,
            $shibarray['Home Page'] , '</a></td>
            <td> </td>
          </tr>
    ';

    if ((strlen($shibarray['Technical Name']) > 0) &&
        (strlen($shibarray['Technical Address']) > 0)) {
        echo '
          <tr class="odd">
            <th>Technical Contact:</th>
            <td>' , $shibarray['Technical Name'] , ' &lt;' ,
                    $shibarray['Technical Address'] , '&gt;</td>
            <td> </td>
          </tr>';
    }

    if ((strlen($shibarray['Administrative Name']) > 0) &&
        (strlen($shibarray['Administrative Address']) > 0)) {
        echo '
          <tr>
            <th>Administrative Contact:</th>
            <td>' , $shibarray['Administrative Name'] , ' &lt;' ,
                    $shibarray['Administrative Address'] , '&gt;</td>
            <td> </td>
          </tr>';
    }

    echo '</table>
        </div>  <!-- meta3 -->
    </div>  <!-- summary -->
    </div>  <!-- boxed -->
    ';

    printFooter();
}

?>
