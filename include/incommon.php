<?php

/************************************************************************
 * Class name : incommon                                                *
 * Description: This class manages the entityIDs and corresponding      *
 *              OrganizationDisplayNames for IdPs in the InCommon       *
 *              metadata file.  Creating a new incommon object instance *
 *              reads in entityIDs and OrganizationDisplayNames         *
 *              for entries tagged as <IDPSSODescriptor>, and stores    *
 *              them in the $idparray member.  There are methods        *
 *              for getting a display name corresponding to an entityID *
 *              and vice-versa.  Also, there are two special methods    *
 *              getOnlyWhitelist() and getNoWhitelist().  The first     *
 *              returns a culled version of the $idparray containing    *
 *              ONLY entityIDs in a given $whitelist object.  The       *
 *              second returns a culled version of the $idparray        *
 *              containing all entityIDS EXCEPT for those in a given    *
 *              $whitelist object.                                      *
 *                                                                      *
 *              There is one constant in the class that you should      *
 *              set for your particular set up:                         *
 *                                                                      *
 *              defaultFilename - this is the full path and name of the *
 *                  InCommon metadata file used by the CILogon Service. *
 *                  It should have read permissions for apache (via     *
 *                  either owner or group).                             *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('incommon.php');                                     *
 *    $incommon = new incommon();                                       *
 *    foreach ($incommon->idparray as $entityID => $displayName) {      *
 *       echo "idparray[$entityID] = $displayName \n";                  *
 *    }                                                                 *
 ************************************************************************/

class incommon {

    /* Set the constants to correspond to your particular set up.       */
    const defaultFilename = '/etc/shibboleth/InCommon-metadata.xml';

    /* The $idparray holds the list of IdP entityIDs and their          *
     * corresponding "pretty print" names.  The keys of the array are   *
     * the actual entityIDs, while the values are the corresponding     *
     * "OrganizationDisplayName" as read from the metadata file.        *
     * Upon successful read of the metatdata file, the $idparray is     *
     * sorted alphabetically by values (display names).                 */
    public $idparray;

    /* These variables should be accessed only by the get/set methods.  */
    protected $incommonfilename;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameter : (Optional) The full path of the InCommon metadata    *
     *             filename used by the CILogon Service.                *
     * Returns   : A new incommon object.                               *
     * Default constructor.  The contents of the $filename are read in  *
     * and populate the $idparray.  However, if the contents of         *
     * $filename cannot be read, the $idparray is set to an empty       *
     * array.                                                           *
     ********************************************************************/
    function __construct($filename=self::defaultFilename) {
        $this->idparray = array();
        $this->setFilename($filename);
        $this->read();
    }

    /********************************************************************
     * Function  : read                                                 *
     * Returns   : True if the metadata file was read in successfully,  *
     *             false otherwise.                                     *
     * This function reads in the metadata file containing the list     *
     * of entityIDs in the InCommon metadata file.  The entityIDs are   *
     * stored in the $idparray as the keys, with the "display names"    *
     * stored as the corresponding values.                              *
     ********************************************************************/
    function read() {
        $retval = false;  // Assume read failed
        if (is_readable($this->getFilename())) {
            $xmlstr = @file_get_contents($this->getFilename());
            if (strlen($xmlstr) > 0) {
                $xmlstr = str_replace('xmlns=','ns=',$xmlstr);
                $xml = new SimpleXMLElement($xmlstr);

                /* This XPATH query is actually two queries in one. *
                 * The first half of the expression finds all       *
                 * entityIDs that contain <IDPSSODescriptor> tags   *
                 * (meaning they can act as an IdP).  The second    *
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

                /* Loop through the resulting array. Notice the     *
                 * entityIDs are in the odd numbered array elements *
                 * and the display names are in the even numbered   *
                 * elements, so increment through the array by 2.   */
                for ($i = 0; $i < count($result); $i += 2) {
                    $this->add((string)$result[$i]->entityID,
                               (string)$result[$i+1]);
                }

                /* Finally sort the array by the display names.     */
                natcasesort($this->idparray);
                $retval = true;
            }
        }

        return $retval;
    }

    /********************************************************************
     * Function  : getFilename                                          *
     * Returns   : A string of the InCommon metadata filename.          *
     * This function returns a string of the full path of the InCommon  *
     * metadata filename.  See also setFilename().                      *
     ********************************************************************/
    function getFilename() {
        return $this->incommonfilename;
    }

    /********************************************************************
     * Function  : setFilename                                          *
     * Parameter : The new name of the InCommon metadata filename.      *
     * This function sets the string of the full path of the InCommon   *
     * metadata filename.  See also getFilename().                      *
     ********************************************************************/
    function setFilename($filename) {
        $this->incommonfilename = $filename;
    }

    /********************************************************************
     * Function  : getDisplayName                                       *
     * Parameter : An entityID string.                                  *
     * Returns   : The OrganizationDisplayName corresponding to the     *
     *             passed-in entityID, or empty string if not found.    *
     * This function takes in a string of an entityID and returns the   *
     * corresponding OrganizationDisplayName from the $idparray.  If    *
     * no such entityID is found in the $idparray, then the empty       *
     * string is returned.  See also getEntityID().                     *
     ********************************************************************/
    function getDisplayName($entityID) {
        $retval = '';  // Assume entityID is not in idparray
        if (isset($this->idparray[$entityID])) {
            $retval = $this->idparray[$entityID];
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getEntityID                                          *
     * Parameter : An OrganizationDisplayName string.                   *
     * Returns   : The entityID corresponding to the passed-in          *
     *             display name, or empty string if not found.          *
     * This function takes in a string of an OrganzationDisplayname     *
     * and returns the corresponding entityID from the $idparray.  If   *
     * no such display name is found in the $idparray, then the empty   *
     * string is returned.  See also getDisplayName().                  *
     ********************************************************************/
    function getEntityID($displayName) {
        $retval = '';  // Assume displayName is not in idparray
        $entityID = array_search($displayName);
        if (($entityID !== false) && (strlen($entityID) > 0)) {
            $retval = $entityID;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : entityIDExists                                       *
     * Parameter : An entityID string.                                  *
     * Returns   : True if the entityID exists in the InCommon          *
     *             metadata. False otherwise.                           *
     * This function takes in a string of an entityID and returns true  *
     * if that entityID is an IdP in the InCommon metadata file.        *
     * See also displayNameExists().                                    *
     ********************************************************************/
    function entityIDExists($entityID) {
        return (strlen($this->getDisplayName($entityID)) > 0);
    }

    /********************************************************************
     * Function  : exists                                               *
     * Parameter : An entityID string.                                  *
     * Returns   : True if the entityID exists in the InCommon          *
     *             metadata. False otherwise.                           *
     * This function is an alias for entityIDExists().                  *
     ********************************************************************/
    function exists($entityID) {
        return ($this->entityIDExists($entityID));
    }

    /********************************************************************
     * Function  : displayNameExists                                    *
     * Parameter : An IdP display name string.                          *
     * Returns   : True if the display name of an IdP exists in the     *
     *             InCommon metadata. False otherwise.                  *
     * This function takes in the display name string of an IdP and     *
     * returns true if that string is in the InCommon metadata file.    *
     * See also entityIDExists().                                       *
     ********************************************************************/
    function displayNameExists($displayName) {
        return (strlen($this->getEntityID($displayName)) > 0);
    }

    /********************************************************************
     * Function  : add                                                  *
     * Parameters: (1) An entityID string.                              *
     *             (2) The corresponding OrganizationDisplayName string.*
     * Returns   : True if the new (entityID,OrganizationDisplayName)   *
     *             tuple was added to the $idparray, false if the       *
     *             passed-in entityID was already in the $idparray.     *
     * This function allows you to add a _new_ entityID to the          *
     * $idparray, along with its corresponding display name (as read    *
     * from the InCommon metadata file.  If the entityID already exists *
     * in the $idparray, then it is not readded, and false is returned. *
     * Notice that this prevents a particular entityID from being       *
     * mapped to multiple OrganizationDisplayNames.                     *
     ********************************************************************/
    function add($entityID,$displayName) {
        $retval = false;  // Assume add to list failed
        if ((strlen($entityID) > 0) && 
            (strlen($this->getDisplayName($entityID)) == 0)) {
            $this->idparray[$entityID] = $displayName;
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : remove                                               *
     * Parameter : An entityID string to be removed from the            *
     *             $idparray.                                           *
     * Returns   : True if the entityID was removed from the list       *
     *             of entityIDs, false if the passed-in entityID was    *
     *             not in the $idparray.                                *
     * This function is an alias for removeEntityID().  See also        *
     * removeEntityID() and removeDisplayName().                        *
     ********************************************************************/
    function remove($entityID) {
        return $this->removeEntityID($entityID);
    }

    /********************************************************************
     * Function  : removeEntityID                                       *
     * Parameter : An entityID string to be removed from the            *
     *             $idparray.                                           *
     * Returns   : True if the entityID was removed from the list       *
     *             of entityIDs, false if the passed-in entityID was    *
     *             not in the $idparray.                                *
     * This function allows you to remove an existing entityID (and its *
     * corresponding OrganizationDisplayName) from the $idparray.  If   *
     * the entityID does not exist in the $idparray, then it is not     *
     * removed, and false is returned.  See also remove() and           *
     * removeDisplayName().                                             *
     ********************************************************************/
    function removeEntityID($entityID) {
        $retval = false;  // Assume remove from list failed
        if (strlen($this->getDisplayName($entityID)) > 0) {
            unset($this->idparray[$entityID]);
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : removeDisplayName                                    *
     * Parameter : An OrganizationDisplayName string to be removed      *
     *             from the $idparray.                                  *
     * Returns   : True if the OrganizationDisplayName was removed      *
     *             from the $idparray, false if the passed-in display   *
     *             name was not in the $idparray.                       *
     * This function allows you to remove an existing                   *
     * OrganzationDisplayName (and its corresponding entityID) from the *
     * $idparray.  If the display name does not exist in the $idparray, *
     * then it is not removed, and false is returned.  See also         *
     * remove() and removeEntityID().                                   *
     ********************************************************************/
    function removeDisplayName($displayName) {
        $retval = false;  // Assume remove from list failed
        $entityID = $this->getEntityID($displayName);
        if (strlen($entityID) > 0) {
            unset($this->idparray[$entityID]);
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getOnlyWhitelist                                     *
     * Parameter : A whitelist object containing a list of whitelisted  *
     *             entityIDs.                                           *
     * Returns   : A pared-down version of the $idparray, containing    *
     *             ONLY the entityIDs in the passed-in whitelist.       *
     * This function returns a culled copy of the $idparray.  This      *
     * new array contains ONLY the entityIDs (and corresponding         *
     * OrganizationDisplayNames) from the passed-in $whitelist object.  *
     * This is useful for printing out the <select> form element        *
     * showing the list of organizations available for selection on the *
     * CILogon site.  See also getNoWhitelist().                        *
     ********************************************************************/
    function getOnlyWhitelist($whitelist) {
        $idps = $this->idparray;
        foreach ($idps as $entityID => $displayName) {
            if (!$whitelist->exists($entityID)) {
                unset($idps[$entityID]);
            }
        }
        return $idps;
    }

    /********************************************************************
     * Function  : getNoWhitelist                                       *
     * Parameter : A whitelist object containing a list of whitelisted  *
     *             entityIDs.                                           *
     * Returns   : A pared-down version of the $idparray, containing    *
     *             all entityIDs EXCEPT for those in the passed-in      *
     *             whitelist.                                           *
     * This function returns a culled copy of the $idparray.  This      *
     * new array contains all entityIDs (and corresponding              *
     * OrganizationDisplayNames) EXCEPT those in the passed-in          *
     * $whitelist object.  This is useful for printing out the <select> *
     * form element showing the organizations which are not yet         *
     * available on the CILogon site, but can be requested by a         *
     * potential user.  See also getOnlyWhitelist().                    *
     ********************************************************************/
    function getNoWhitelist($whitelist) {
        $idps = $this->idparray;
        foreach ($idps as $entityID => $displayName) {
            if ($whitelist->exists($entityID)) {
                unset($idps[$entityID]);
            }
        }
        return $idps;
    }

}

?>
