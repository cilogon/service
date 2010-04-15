<?php

require_once("util.php");

/************************************************************************
 * Function   : getShibInfo                                             *
 * Parameter  : The metadata file from which to read IdP info (defaults *
 *              to the InCommon metadata file).  If you pass in an empty*
 *              string for this parameter, then no metadata file is     *
 *              read in and none of the shibarray variables specific    *
 *              to the given IdP will be set, such as the pretty-print  *
 *              name of the organization, the organization's home page, *
 *              and IdP contact information.                            *
 * Returns    : An array containing the various shibboleth attributes   *
 *              for the current Shibboleth session.  The keys of the    *
 *              array are "pretty print" names of the various attribute *
 *              value names (such as "User Identifier" for REMOTE_USER) *
 *              and the values of the array are the actual Shibboleth   *
 *              session values.                                         *
 * This function returns an array with two types of Shibboleth          *
 * information.  The first set of info is specific to the user's        *
 * current Shibboleth session, such as REMOTE_USER.  The second set     *
 * of info reads info from the passed-in metadata file specific to the  *
 * IdP, such as the pretty-print name of the IdP.                       *
 ************************************************************************/
function getShibInfo($metadata=incommon::defaultFilename)
{
    $shibarray = array();  /* Array to be returned */

    /* Set the first set of info, namely those shib attributes which *
     * were given by the IdP when the user authenticated.            */
    $shibarray['Identity Provider']=getServerVar('HTTP_SHIB_IDENTITY_PROVIDER');
    $shibarray['User Identifier'] = getServerVar('HTTP_REMOTE_USER');
    $shibarray['ePPN'] = getServerVar('HTTP_EPPN');
    $shibarray['ePTID'] = getServerVar('HTTP_TARGETED_ID');
    $shibarray['First Name'] = getServerVar('HTTP_GIVENNAME');
    $shibarray['Last Name'] = getServerVar('HTTP_SN');
    $shibarray['Display Name'] = getServerVar('HTTP_DISPLAYNAME');
    $shibarray['Email Address'] = getServerVar('HTTP_MAIL');
    $shibarray['Level of Assurance'] = getServerVar('HTTP_ASSURANCE');

    /* Next, read in the metadata file and search for attributes     *
     * for the given IdP.  This includes values such as the          *
     * display name for the IdP, the home page of the organization,  *
     * and contact info for if there is a problem.                   */
    $shibarray['Organization Name'] = '';
    $shibarray['Home Page'] = '';
    $shibarray['Technical Name'] = '';
    $shibarray['Technical Address'] = '';
    $shibarray['Administrative Name'] = '';
    $shibarray['Administrative Address'] = '';

    if (is_readable($metadata)) {
        $xmlstr = @file_get_contents($metadata);
        if (strlen($xmlstr) > 0) {
            $xmlstr = str_replace('xmlns=','ns=',$xmlstr);
            $xml = new SimpleXMLElement($xmlstr);

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/Organization/OrganizationDisplayName");
            if (count($result) == 1) {
                $shibarray['Organization Name'] = (string)$result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/Organization/OrganizationURL");
            if (count($result) == 1) {
                $shibarray['Home Page'] = (string)$result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='technical']/GivenName");
            if (count($result) > 0) {
                $shibarray['Technical Name'] = (string)$result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='technical']/EmailAddress");
            if (count($result) > 0) {
                $shibarray['Technical Address'] = (string)$result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='administrative']/GivenName");
            if (count($result) > 0) {
                $shibarray['Administrative Name'] = (string)$result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='administrative']/EmailAddress");
            if (count($result) > 0) {
                $shibarray['Administrative Address'] = (string)$result[0];
            }
        }
    }

    return $shibarray;
}

/************************************************************************
 * Function   : removeShibCookies                                       *
 * This function removes all "_shib*" cookies currently in the user's   *
 * browser session.  In effect, this logs the user out of any IdP.      *
 * Note that you must call this before you output any HTML.  Strictly   *
 * speaking, the cookies are not removed, rather they are set to empty  *
 * values with expired times.                                           *
 ************************************************************************/
function removeShibCookies() 
{
    while (list ($key,$val) = each ($_COOKIE)) {
        if (strncmp($key,"_shib", strlen("_shib")) == 0) {
            setcookie($key,'',time()-3600,'/','',true);
        }
    }
}

?>
