<?php

require_once("whitelist.php");

/* Be sure to declare "global $shibarray" when using it in other files. */
$shibarray = array();

/* The full file path location of the local InCommon metadata XML file. */
define('INCOMMON_METADATA','/etc/shibboleth/InCommon-metadata.xml');

/************************************************************************
 * Function   : getShibInfo                                             *
 * Parameter  : The metadata file from which to read IdP info (defaults *
 *              to the InCommon metadata file).  If you pass in an empty*
 *              string for this parameter, then no metadata file is     *
 *              read in and none of the shibarray variables specific    *
 *              to the given IdP will be set, such as the pretty-print  *
 *              name of the organization, the organization's home page, *
 *              and IdP contact information.                            *
 * Returns    : True if the following shibboleth attributes have been   *
 *              provided by the IdP: HTTP_SHIB_IDENTITY_PROVIDER,       *
 *              HTTP_REMOTE_USER (which can be ePPN or ePTID),          *
 *              HTTP_GIVENNAME, HTTP_SN (last name), and HTTP_MAIL.     *
 *              False if any of these have not been set by the IdP.     *
 * Side Effect: The global $shibarray is populated with various         *
 *              shibboleth session environment variables.               *
 * This function populates the global $shibarray with two types of      *
 * Shibboleth information.  The first set of info is specific to the    *
 * user's current shib session, such as remote_user.  The second set    *
 * of info reads info from the passed-in metadata file specific to the  *
 * IdP, such as the pretty-print name of the IdP.  If all of the shib   *
 * attributes needed for the cilogon.org service are present, then      *
 * the function returns true.                                           *
 ************************************************************************/
function getShibInfo($metadata=INCOMMON_METADATA)
{
    $retval = false;  // Assume not all necessary shib attributes are set

    /* First, set all of the shib attributes to empty values. */
    global $shibarray;
    $shibarray['Identity Provider'] = '';
    $shibarray['User Identifier'] = '';
    $shibarray['First Name'] = '';
    $shibarray['Last Name'] = '';
    $shibarray['Email Address'] = '';
    $shibarray['Level of Assurance'] = '';
    $shibarray['Organization Name'] = '';
    $shibarray['Home Page'] = '';
    $shibarray['Technical Name'] = '';
    $shibarray['Technical Address'] = '';
    $shibarray['Administrative Name'] = '';
    $shibarray['Administrative Address'] = '';

    /* Next, set the first set of info, namely those shib attributes *
     * which were given by the IdP when the user authenticated.      */
    if (isset($_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'])) {
      $shibarray['Identity Provider'] = $_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'];
    };
    if (isset($_SERVER['HTTP_REMOTE_USER'])) {
      $shibarray['User Identifier'] = $_SERVER['HTTP_REMOTE_USER'];
    }
    if (isset($_SERVER['HTTP_GIVENNAME'])) {
      $shibarray['First Name'] = $_SERVER['HTTP_GIVENNAME'];
    }
    if (isset($_SERVER['HTTP_SN'])) {
      $shibarray['Last Name'] = $_SERVER['HTTP_SN'];
    }
    if (isset($_SERVER['HTTP_MAIL'])) {
      $shibarray['Email Address'] = $_SERVER['HTTP_MAIL'];
    }
    if (isset($_SERVER['HTTP_ASSURANCE'])) {
      $shibarray['Level of Assurance'] = $_SERVER['HTTP_ASSURANCE'];
    }

    /* Next, read in the metadata file and search for attributes     *
     * for the given IdP.  This includes values such as the          *
     * display name for the IdP, the home page of the organization,  *
     * and contact info for if there is a problem.                   */
    if (is_readable($metadata)) {
        $xmlstr = @file_get_contents($metadata);
        if (strlen($xmlstr) > 0) {
            $xmlstr = str_replace('xmlns=','ns=',$xmlstr);
            $xml = new SimpleXMLElement($xmlstr);

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/Organization/OrganizationDisplayName");
            if (count($result) == 1) {
                $shibarray['Organization Name'] = $result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/Organization/OrganizationURL");
            if (count($result) == 1) {
                $shibarray['Home Page'] = $result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='technical']/GivenName");
            if (count($result) > 0) {
                $shibarray['Technical Name'] = $result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='technical']/EmailAddress");
            if (count($result) > 0) {
                $shibarray['Technical Address'] = $result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='administrative']/GivenName");
            if (count($result) > 0) {
                $shibarray['Administrative Name'] = $result[0];
            }

            $result = $xml->xpath("//EntityDescriptor[@entityID='" .
                $shibarray['Identity Provider'] .
                "']/ContactPerson[@contactType='administrative']/EmailAddress");
            if (count($result) > 0) {
                $shibarray['Administrative Address'] = $result[0];
            }
        }
    }

    /* Finally, check to see if all of the shib attributes required  *
     * for the cilogon.org service have been set.                    */
    if ((strlen($shibarray['Identity Provider']) > 0) &&
        (strlen($shibarray['User Identifier']) > 0) &&
        (strlen($shibarray['First Name']) > 0) &&
        (strlen($shibarray['Last Name']) > 0) &&
        (strlen($shibarray['Email Address']) > 0)) {
        $retval = true;
    }

    return $retval;
}

/************************************************************************
 * Function  : getInCommonIdPs                                          *
 * Parameters: (1) The full path of the (InCommon) metadata XML file.   *
 *                 If you do not specify this parameter or set this     *
 *                 parameter to 'null', then the local InCommon         *
 *                 metadata file will be used.  (Setting to 'null' is   *
 *                 useful for setting the second parameter to the       *
 *                 empty string while still using the default value     *
 *                 for the first parameter).                            *
 *             (2) The full path of the local Discovery Service         *
 *                 whitelist.xml file.  The entityIDs in this file are  *
 *                 omitted from the returned list of IdPs.  If set to   *
 *                 the empty string '', then no entityIDs are omitted   *
 *                 from the returned list of IdPs.                      *
 * Returns   : An array of IdPs from a metadata file, typically the     *
 *             InCommon metadata.  The keys of the array are the        *
 *             entityIDs, and the values of the array are the "pretty   *
 *             print" names of the IdPs.                                *
 * This function scans a metadata file (defaults to the local InCommon  *
 * metadata XML file) for IdPs, tagged as <IDPSSODescriptor>.  It       *
 * finds the entityIDs for each such tag, as well as the "pretty print" *
 * name of the IdP, stored in the <OrganizationDisplayName> tag.  The   *
 * keys of the returned array are the entityIDs (for example            *
 * "urn:mace:incommon:uiuc.edu"), while the values of the array are     *
 * the display names (for example "University of Illinois at            *
 * Urbana-Champaign").  The array is sorted alphabetically by the       *
 * values (display names).  By default, the local Discovery Service's   *
 * whitelist is read in as well.  The whitelist contains entityIDs that *
 * appear in the local WAYF.  By default, this function OMITS the       *
 * whitelisted IdPs from the returned array.  If you want a full list   *
 * of IdPs (including the whitelisted IdPs), the pass 'null' as the     *
 * first argument and an empty string as the second argument.           *
 *                                                                      *
 * The main purpose of this function is to get a list of InCommon       *
 * IdPs which have not yet been added to the whitelist for the local    *
 * WAYF.  This array can be used to populate a dropdown list of         *
 * "potential" IdPs to be added to the white list.                      *
 ************************************************************************/

function getInCommonIdPs($metadata=INCOMMON_METADATA,
                         $remove_whitelist=whitelist::defaultFilename)
{
    $idps = array();
    $white = null;

    /* By default, the whitelist for the local WAYF is read in *
     * so as to remove IdPs which have already been accepted   *
     * as releasing attributes to the SP.  If the second       *
     * parameter is the empty string, then don't read in the   *
     * local WAYF's whitelist file.                            */
    if (strlen($remove_whitelist) > 0) {
        $white = new whitelist($remove_whitelist);
        $white->read();
    }

    /* Next, attempt to read in the specified metadata file    *
     * create an XPATH query.  If the metadata file parameter  *
     * is 'null', then set it to the default InCommon metadata *
     * XML file.  This is useful for setting the whitelist     *
     * file parameter to the empty string while still using    *
     * the default InCommon metadata file.                     */
    if (is_null($metadata)) {
        $metadata = INCOMMON_METADATA;
    }

    if (is_readable($metadata)) {
        $xmlstr = @file_get_contents($metadata);
        if (strlen($xmlstr) > 0) {
            $xmlstr = str_replace('xmlns=','ns=',$xmlstr);
            $xml = new SimpleXMLElement($xmlstr);

            /* This XPATH query is actually two queries in one. *
             * The first half of the expression finds all       *
             * entityIDs that contain <IDPSSODescriptor> tags   *
             * (meaning they can act as an IDP).  The second    *
             * half of the expression finds the corresponding   *
             * <OrganizationDisplayName> for the entityIDs.     *
             * This query is faster than doing an XPATH query   *
             * to find the OrganizationDisplayName for each     *
             * entityID, but ends up with an array that has     *
             * entityIDs as the odd numbered elements and       *
             * display names as the even numbered elements.     */
            $result = $xml->xpath(
                "//EntityDescriptor/IDPSSODescriptor" .
                "/ancestor::EntityDescriptor" .
                "/attribute::entityID" .
                " | " .
                "//EntityDescriptor/IDPSSODescriptor" .
                "/ancestor::EntityDescriptor" .
                "/Organization/OrganizationDisplayName"
                );

            /* Loop through the resulting array skipping any    *
             * whitelisted IdPs if necessary.  Notice that the  *
             * entityIDs are in the odd numbered array elements *
             * and the display names are in the even numbered   *
             * elements, so increment through the array by 2s.  */
            for ($i = 0; $i < count($result); $i += 2) {
                $entityID = (string)$result[$i]->entityID;
                if (($white == null) || (!$white->exists($entityID))) {
                    $idps[$entityID] = (string)$result[$i+1];
                }
            }

            /* Finally sort the array by the display names.     */
            natcasesort($idps);
        }
    }

    return $idps;
}

/************************************************************************
 * Function   : deleteShibCookies                                       *
 * This function deletes all "_shib*" cookies currently in the user's   *
 * browser session.  In effect, this logs the user out of any IdP.      *
 * Note that you must call this before you output any HTML.  Strictly   *
 * speaking, the cookies are not deleted, rather they are set to empty  *
 * values with expired times.                                           *
 ************************************************************************/
function deleteShibCookies() {
    while (list ($key,$val) = each ($_COOKIE)) {
        if (strncmp($key,"_shib", strlen("_shib")) == 0) {
            setcookie($key,'',time()-3600,'/','',true);
        }
    }
}

?>
