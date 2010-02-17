<?php

/* Be sure to declare "global $shibarray" when using it in other files. */
$shibarray = array();

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
function getShibInfo($metadata='/etc/shibboleth/InCommon-metadata.xml')
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
