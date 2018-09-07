<?php

/**
 * secure/testidp/index.php

 * This file prints out the main Shibboleth Attributes Test page.
 * It displays a table of all of the shib attributes utilized by the
 * CILogon Service. If any attribute is missing, an error message is
 * printed out.
 */

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../../vendor/autoload.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::startPHPSession();

// Check the csrf cookie against either a hidden <form> element or a
// PHP session variable, and get the value of the 'submit' element.
$submit = Util::getCsrf()->verifyCookieAndGetSubmit();

// Get the Shibboleth information for the current session.
$idplist = Util::getIdpList();
$shibarray = $idplist->getShibInfo();
$entityID = @$shibarray['Identity Provider'];

$gotattrs = false;  // Did we get all shib attributes?

Content::printHeader('Test Identity Provider');
Content::printPageHeader('Test Your Organization\'s Identity Provider');

echo '
<div class="boxed">
  <div class="boxheader">
    Verify SAML Attribute Release Policy
  </div>

<p>
Thank you for your interest in the CILogon Service. This page allows
the administrator of an Identity Provider (<acronym
title="Identity Provider">IdP</acronym>) to verify that all necessary
SAML attributes have been released to the CILogon Service Provider
(<acronym title="Service Provider">SP</acronym>). Below you will see
the various attributes required by the CILogon Service and their values
as released by your IdP.
</p>

<div class="summary">
<h2>Summary</h2>
';

$emailvalid=filter_var(@$shibarray['Email Address'], FILTER_VALIDATE_EMAIL);

if ((strlen($entityID) > 0) &&
    (strlen(@$shibarray['User Identifier']) > 0) &&
    (strlen(@$shibarray['Email Address']) > 0) && ($emailvalid) &&
    (strlen(@$shibarray['Organization Name']) > 0) &&
        ((strlen(@$shibarray['Display Name']) > 0) ||
             ((strlen(@$shibarray['First Name']) > 0) &&
              (strlen(@$shibarray['Last Name']) > 0)))) {
    $gotattrs = true;
}

if ($gotattrs) {
    echo '<div class="icon">';
    Content::printIcon('okay');
    echo '
    </div>
    <div class="summarytext">
    <p>
    All required attributes have been released by your <acronym
    title="Identity Provider">IdP</acronym>. For details of the various
    attributes utilized by the CILogon Service and their current values,
    see the sections below.
    </p>
    <p class="addsubmit">
    <a href="/">Proceed to the CILogon
    Service</a>.
    </p>
    </div>
    ';
} else {
    echo '<div class="icon">';
    Content::printIcon('error', 'Missing one or more attributes.');
    echo '
    </div>
    <div class="summarytext">
    <p>
    One or more of the attributes required by the CILogon Service are
    not available. Please see the sections below for details. Contact
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
Javascript is disabled. In order to expand or collapse the sections
below, please enable Javascript in your browser.
</div>
</noscript>

<div class="summary">
    <div id="saml1" style="display:' ,
        ($gotattrs ? "inline" : "none") ,
    '"><span class="expander"><a
    href="javascript:showHideDiv(\'saml\',-1)"><img
    src="/images/triright.gif" alt="&rArr;" width="14" height="14" />
    SAML Attributes</a></span>';

// CIL-416 Show warning for missing ePPN
if ((strlen(@$shibarray['ePPN']) == 0) &&
    (strlen(@$shibarray['ePTID']) != 0)) {
    Content::printIcon('warn', 'Some CILogon clients (e.g., Globus) require ePPN.');
}

echo '
    </div>
    <div id="saml2" style="display:' ,
        ($gotattrs ? "none" : "inline") ,
    '"><span class="expander"><a
    href="javascript:showHideDiv(\'saml\',-1)"><img
    src="/images/tridown.gif" alt="&dArr;" width="14" height="14" />
    SAML Attributes</a></span>
    </div>
    <br class="clear" />
    <div id="saml3" style="display:' ,
        ($gotattrs ? "none" : "inline") ,
    '">

    <table cellpadding="5">
      <tr class="odd">
        <th>Identity Provider (entityID):</th>
        <td>' , $entityID , '</td>
        <td>';

if (strlen($entityID) == 0) {
    Content::printIcon('error', 'Missing the entityID of the IdP.');
}

echo '
        </td>
      </tr>

      <tr>
        <th>ePTID:</th>
        <td>' , @$shibarray['ePTID'] , '</td>
        <td>';

if ((strlen(@$shibarray['ePPN']) == 0) &&
    (strlen(@$shibarray['ePTID']) == 0)) {
    Content::printIcon('error', 'Must have either ePPN -OR- ePTID.');
}

echo '
        </td>
      </tr>

      <tr class="odd">
        <th>ePPN:</th>
        <td>' , @$shibarray['ePPN'] , '</td>
        <td>';

if (strlen(@$shibarray['ePPN']) == 0) {
    if (strlen(@$shibarray['ePTID']) == 0) {
        Content::printIcon('error', 'Must have either ePPN -OR- ePTID.');
    } else {
        Content::printIcon('warn', 'Some CILogon clients (e.g., Globus) require ePPN.');
    }
}

echo '
        </td>
      </tr>

      <tr>
        <th>First Name (givenName):</th>
        <td>' , @$shibarray['First Name'] , '</td>
        <td>';

if ((strlen(@$shibarray['First Name']) == 0) &&
    (strlen(@$shibarray['Display Name']) == 0)) {
    Content::printIcon('error', 'Must have either givenName + sn -OR- displayName.');
}

echo '
        </td>
      </tr>

      <tr class="odd">
        <th>Last Name (sn):</th>
        <td>' , @$shibarray['Last Name'] , '</td>
        <td>';

if ((strlen(@$shibarray['Last Name']) == 0) &&
    (strlen(@$shibarray['Display Name']) == 0)) {
    Content::printIcon('error', 'Must have either givenName + sn -OR- displayName.');
}

echo '
        </td>
      </tr>

      <tr>
        <th>Display Name (displayName):</th>
        <td>' , @$shibarray['Display Name'] , '</td>
        <td>';

if ((strlen(@$shibarray['Display Name']) == 0) &&
        ((strlen(@$shibarray['First Name']) == 0) ||
         (strlen(@$shibarray['Last Name']) == 0))) {
    Content::printIcon('error', 'Must have either displayName -OR- givenName + sn.');
}

echo '
        </td>
      </tr>

      <tr class="odd">
        <th>Email Address (email):</th>
        <td>' , @$shibarray['Email Address'] , '</td>
        <td>';

if ((strlen(@$shibarray['Email Address']) == 0) || (!$emailvalid)) {
    Content::printIcon('error', 'Missing valid email address.');
}

echo '
        </td>
      </tr>

      <tr>
        <th>Level of Assurance (assurance):</th>
        <td>' , @$shibarray['Level of Assurance'] , '</td>
        <td> </td>
      </tr>

      <tr class="odd">
        <th>AuthnContextClassRef:</th>
        <td>' , @$shibarray['Authn Context'] , '</td>
        <td> </td>
      </tr>

      <tr>
        <th>Affiliation (affiliation):</th>
        <td>' , @$shibarray['Affiliation'] , '</td>
        <td> </td>
      </tr>

      <tr class="odd">
        <th>Organizational Unit (ou):</th>
        <td>' , @$shibarray['OU'] , '</td>
        <td> </td>
      </tr>

      <tr>
        <th>Member (member):</th>
        <td>' , @$shibarray['Member'] , '</td>
        <td> </td>
      </tr>

    </table>
    </div> <!-- saml3 -->
</div> <!-- summary -->

<div class="summary">
    <div id="meta1" style="display:' ,
        ($gotattrs ? "inline" : "none") ,
    '"><span class="expander"><a
    href="javascript:showHideDiv(\'meta\',-1)"><img
    src="/images/triright.gif" alt="&rArr;" width="14" height="14" />
    Metadata Attributes</a></span>';

// CIL-416 Check for eduGAIN IdPs without both REFEDS R&S and SIRTFI
// since these IdPs are not allowed to get certificates.
$eduGainWithoutRandSandSIRTFI = 0;
if ((!$idplist->isRegisteredByInCommon($entityID)) &&
    ((!$idplist->isREFEDSRandS($entityID)) ||
     (!$idplist->isSIRTFI($entityID)))) {
    $eduGainWithoutRandSandSIRTFI = 1;
}

if ($eduGainWithoutRandSandSIRTFI) {
    Content::printIcon('warn', 'This IdP does not support both ' .
        'REFEDS R&amp;S and SIRTFI. CILogon functionality may be limited.');
}

echo '
    </div>
    <div id="meta2" style="display:' ,
        ($gotattrs ? "none" : "inline") ,
    '"><span class="expander"><a
    href="javascript:showHideDiv(\'meta\',-1)"><img
    src="/images/tridown.gif" alt="&dArr;" width="14" height="14" />
    Metadata Attributes</a></span>
    </div>
    <br class="clear" />
    <div id="meta3" style="display:' ,
        ($gotattrs ? "none" : "inline") ,
    '">

    <table cellpadding="5">
      <tr class="odd">
        <th>Organization Name:</th>
        <td>' , @$shibarray['Organization Name'] , '</td>
        <td>';

if (strlen(@$shibarray['Organization Name']) == 0) {
    Content::printIcon('error', 'Could not find ' .
        '&lt;OrganizationDisplayName&gt; in InCommon metadata.');
}

echo '
        </td>
      </tr>
      <tr>
        <th>Home Page:</th>
        <td><a target="_blank" href="' , @$shibarray['Home Page'] , '">' ,
        @$shibarray['Home Page'] , '</a></td>
        <td> </td>
      </tr>

      <tr class="odd">
        <th>Support Contact:</th>
';
if ((strlen(@$shibarray['Support Name']) > 0) ||
    (strlen(@$shibarray['Support Address']) > 0)) {
    echo '
        <td>' , @$shibarray['Support Name'] , ' &lt;' ,
                preg_replace('/^mailto:/', '', @$shibarray['Support Address']) , '&gt;</td>
        <td> </td>';
}
echo '
      </tr>

      <tr>
        <th>Technical Contact:</th>
';
if ((strlen(@$shibarray['Technical Name']) > 0) ||
    (strlen(@$shibarray['Technical Address']) > 0)) {
    echo '
        <td>' , @$shibarray['Technical Name'] , ' &lt;' ,
                preg_replace('/^mailto:/', '', @$shibarray['Technical Address']) , '&gt;</td>
        <td> </td>';
}
echo '
      </tr>

      <tr class="odd">
        <th>Administrative Contact:</th>
';
if ((strlen(@$shibarray['Administrative Name']) > 0) ||
    (strlen(@$shibarray['Administrative Address']) > 0)) {
    echo '
        <td>' , @$shibarray['Administrative Name'] , ' &lt;' ,
                preg_replace('/^mailto:/', '', @$shibarray['Administrative Address']) , '&gt;</td>
        <td> </td>';
}
echo '
      </tr>

      <tr>
        <th>Registered by InCommon:</th>
        <td>' , ($idplist->isRegisteredByInCommon($entityID) ? 'Yes' : 'No') , '</td>
        <td> </td>
      </tr>

      <tr class="odd">
        <th><a style="text-decoration:underline" target="_blank"
        href="http://id.incommon.org/category/research-and-scholarship">InCommon R
        &amp; S</a>:</th>
        <td>' , ($idplist->isInCommonRandS($entityID) ? 'Yes' : 'No') , '</td>
        <td> </td>
      </tr>

      <tr>
        <th><a style="text-decoration:underline" target="_blank"
        href="http://refeds.org/category/research-and-scholarship">REFEDS
        R &amp; S</a>:</th>
        <td>' , ($idplist->isREFEDSRandS($entityID) ? 'Yes' : 'No') , '</td>
        <td>' ,
        (($eduGainWithoutRandSandSIRTFI && !$idplist->isREFEDSRandS($entityID)) ?
            Content::printIcon('warn', 'This IdP does not support both ' .
                'REFEDS R&amp;S and SIRTFI. CILogon functionality may be limited.') :
            '') ,
        '</td>
      </tr>

      <tr class="odd">
        <th><a style="text-decoration:underline" target="_blank" href="https://refeds.org/sirtfi">SIRTFI</a>:</th>
        <td>' , ($idplist->isSIRTFI($entityID) ? 'Yes' : 'No') , '</td>
        <td>',
        (($eduGainWithoutRandSandSIRTFI && !$idplist->isSIRTFI($entityID)) ?
            Content::printIcon('warn', 'This IdP does not support both ' .
                'REFEDS R&amp;S and SIRTFI. CILogon functionality may be limited.') :
            '') ,
        '</td>
      </tr>

      <tr>
        <th><a style="text-decoration:underline" target="_blank"
        href="http://id.incommon.org/assurance/bronze">InCommon Bronze</a>:</th>
        <td>' , ($idplist->isBronze($entityID) ? 'Yes' : 'No') , '</td>
        <td> </td>
      </tr>

      <tr class="odd">
        <th><a style="text-decoration:underline" target="_blank"
        href="http://id.incommon.org/assurance/silver">InCommon Silver</a>:</th>
        <td>' , ($idplist->isSilver($entityID) ? 'Yes' : 'No') , '</td>
        <td> </td>
      </tr>

      <tr>
        <th>Entity ID</th>
        <td><a style="text-decoration:underline" target="_blank"
        href="https://met.refeds.org/met/entity/',
        rawurlencode($entityID),
        '">', $entityID, '</td>
        <td> </td>
      </tr>
';

echo '</table>
    </div>  <!-- meta3 -->
</div>  <!-- summary -->
</div>  <!-- boxed -->
';

Content::printFooter();
