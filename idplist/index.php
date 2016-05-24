<?php

/************************************************************************
 * The '/idplist/' endpoint prints out the list of available IdPs as a  *
 * JSON object. The endpoint supports the 'skin=..." URL query string   *
 * parameter so that the blacklisted/whitelisted IdPs are returned as   *
 * appropriate. Note that if there is a problem reading the idplist.xml *
 * file, the returned JSON is simply an empty array '[]'.               *
 ************************************************************************/

require_once('../include/util.php');
require_once('../include/autoloader.php');
require_once('../include/content.php');

/************************************************************************
 * The IdP class is used internally by getIdPListAsJSON(). It simply    *
 * stores the IdP attributes in an object.                              *
 ************************************************************************/
class IdP {
    public $EntityID = '';
    public $OrganizationName = '';
    public $RandS = '';

    function __construct($entityid,$orgname,$rands=false) {
        $this->EntityID = $entityid;
        $this->OrganizationName = $orgname;
        $this->RandS = $rands;
    }
}

/************************************************************************
 * Function : getIdPListAsJSON                                          *
 * Returns  : A JSON formatted list of whitelisted IdPs (for the        *
 *            current skin). For each IdP, it also sets the R&S status. *
 *            Additional attributes may be added to the IdP class       *
 *            (above) if future needs dictate. Code would need to be    *
 *            added here to set the the additional attributes.          *
 ************************************************************************/
function getIdPListAsJSON() {
    $idparray = array(); // Array of IdP objects to be converted to JSON
    $idplist = new idplist(); // Needed for checking R&S status
    if ($idplist !== false) { // Verify we read in the idplist.xml file
        $idps = getCompositeIdPList(); // Take into consideration the 'skin'

        foreach ($idps as $entityId => $idpName) {
            $idparray[] = new IdP($entityId,$idpName,
                                  $idplist->isRandS($entityId));
        }
    }

    // Don't escape '/' or unicode characters
    return json_encode($idparray,
           JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}

echo getIdPListAsJSON();

?>
