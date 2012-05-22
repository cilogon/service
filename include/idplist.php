<?php

/************************************************************************
 * Class name : idplist                                                 *
 * Description: This class manages the list of InCommon IdPs and their  *
 *              attributes of interest. Since the InCommon-metadata.xml *
 *              file is rather large and slow to parse using xpath      *
 *              queries, this class creates/reads/writes a smaller XML  *
 *              file containing only the IdPs and the few attributes    *
 *              needed by the CILogon Service. This file also marks the *
 *              "whitelisted" IdPs so that the datastore does not need  *
 *              to be queried all the time for that info.               *
 *                                                                      *
 *              When you create a new instance of this class via        *
 *              "$idplist = new idplist();", the code first tries to    *
 *              read in a previously created idplist.xml file. If no    *
 *              such file can be read in successfully, the "new" method *
 *              then reads in the big InCommon metadata AND queries     *
 *              the datastore for whitelisted IdPs, in order to create  *
 *              the idpdom and write it to the idplist.xml file. You    *
 *              can (re)create the file at any time by calling          *
 *              create() (which simply updates the idpdom) and          *
 *              write() (which writes the idpdom to file).              *
 *                                                                      *
 *              There are several constants in the class that you       *
 *              should set for your particular set up:                  *
 *                                                                      *
 *              defaultIdPFilename - this is the full path and name     *
 *                  of the processed IdP list file used by the CILogon  *
 *                  Service. It should have read/write permissions for  *
 *                  apache (via either owner or group).                 *
 *                                                                      *
 *              defaultInCommonFilename - this is the full path and     *
 *                  name of the InCommon metadata file used by the      *
 *                  CILogon Service. It should have read permissions    *
 *                  for apache (via either owner or group).             *
 *                                                                      *
 *              testIdPFilename - this is the full path and name        *
 *                  of an XML-formatted list of test IdPs. If found,    *
 *                  these test IdPs will be added to the full IdP       *
 *                  list when create()/write() is called. This file     *
 *                  should have read/write permissions for apache.      *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('idplist.php');                                      *
 *    // Read in extant idplist.xml file, or create one from scratch    *
 *    $idplist = new idplist();                                         *
 *    // Get the whitelisted IdPs as entityID/OrganizationName pairs    *
 *    $whitelist = $idplist->getWhitelistedIdPs();                      *
 *    // Rescan InCommon metadata, update IdP list, and write to file   *
 *    $idplist->create();                                               *
 *    $idplist->setFilename('/tmp/newidplist.xml');                     *
 *    $idplist->write();                                                *
 ************************************************************************/

class idplist {

    /* Set the constants to correspond to your particular set up.       */
    const defaultIdPFilename      = '/var/www/html/include/idplist.xml';
    const defaultInCommonFilename = '/etc/shibboleth/InCommon-metadata.xml';
    const testIdPFilename         = '/var/www/html/include/testidplist.xml';

    /* The $idpdom is a DOMDocument which holds the list of IdP         *
     * entityIDs and their corresponding attributes.                    */
    protected $idpdom;
    protected $idpfilename;
    protected $incommonfilename;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new idplist object.                                *
     * Default constructor. This method first attempts to read in an    *
     * existing idplist from an XML file and store it in the idpdom.    *
     * If a valid idplist file cannot be read, a new one is created     *
     * and written to file.                                             *
     ********************************************************************/
    function __construct($idpfilename=self::defaultIdPFilename,
                         $incommonfilename=self::defaultInCommonFilename) {
        $this->setFilename($idpfilename);
        $this->setInCommonFilename($incommonfilename);
        $result = $this->read();
        if ($result === false) {
            $this->create();
            $this->write();
        }
    }

    /********************************************************************
     * Function  : read                                                 *
     * Returns   : True if the idplist file was read in correctly.      *
     *             False otherwise.                                     *
     * This method attempts to read in an existing idplist XML file and *
     * store its contents in the class $idpdom DOMDocument.             *
     ********************************************************************/
    function read() {
        $retval = false;  // Assume read failed
        if ((is_readable($this->getFilename())) &&
            (($dom = DOMDocument::load($this->getFilename())) !== false)) {
            $this->idpdom = $dom;
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : write                                                *
     * Returns   : True if the idpdom was written to the ldplist XML    *
     *             file. False otherwise.                               *
     * This method writes the class $idpdom to an XML file.             *
     ********************************************************************/
    function write() {
         $retval = false; // Assume write failed
         $this->idpdom->preserveWhiteSpace = false;
         $this->idpdom->formatOutput = true;
         if ($this->idpdom->save($this->getFilename()) > 0) {
             $retval = true;
         }
         return $retval;
    }

    /********************************************************************
     * Function  : addNode                                              *
     * Parameters: 1. A DOMDocument object                              *
     *             2. A pointer to a parent <idp> DOMElement            *
     *             3. The name of the new child node DOMElement         *
     *             4. The value of the new child node DOMElement        *
     * This is a convenience method used by create() to add a new       *
     * child node (such as "Organization_Name") to a parent idp node.   *
     ********************************************************************/
    private function addNode($dom,$idpnode,$nodename,$nodevalue) {
        $elemnode = $dom->createElement($nodename);
        $textnode = $dom->createTextNode($nodevalue);
        $elemnode->appendChild($textnode);
        $idpnode->appendChild($elemnode);
    }

    /********************************************************************
     * Function  : sortDOM                                              *
     * Parameter : A DOMDocument to be sorted by Organization_Name      *
     * Returns   : A new DOMDocument with the <idp> elements sorted by  *
     *             Organization_Name.                                   *
     * This method is called by create() to sort the newly created      *
     * DOMDocument <idp> nodes by Organization_Name. It uses an XSL     *
     * transformation to do the work. A new DOMDocument is created      *
     * and returned.                                                    *
     ********************************************************************/
    private function sortDOM($dom) {
        $xsltsort = <<<EOT
            <xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                            version="1.0">
            <xsl:output method="xml" encoding="UTF-8"/>
            <xsl:template match="node() | @*">
              <xsl:copy>
                <xsl:apply-templates select="node() | @*">
                  <xsl:sort select="translate(Organization_Name,'abcdefghijklmnopqrstuvwxyz','ABCDEFGHIJKLMNOPQRSTUVWXYZ')"
                  data-type="text" order="ascending"/>
                </xsl:apply-templates>
              </xsl:copy>
            </xsl:template>
            </xsl:stylesheet>
EOT;
        $xsl = new DOMDocument('1.0');
        $xsl->loadXML($xsltsort);
        $proc = new XSLTProcessor();
        $proc->importStyleSheet($xsl);
        $newdom = $proc->transformToDoc($dom);
        return $newdom;
    }

    /********************************************************************
     * Function  : create                                               *
     * Returns   : True upon successful extraction of IdP information   *
     *             from the InCommon metadata file into the class       *
     *             $idpdom DOMDocument. False otherwise.                *
     * This method is used to populate the class $idpdom DOMDocument    *
     * using information from the InCommon metadata file. It also       *
     * queries the datastore for whilelisted IdPs. Note that this       *
     * method simply updates the $idpdom. If you want to save this      *
     * to a file, be sure to call write() afterwards.                   *
     ********************************************************************/
    function create() {
        $retval = false; // Assume create failed
        if (is_readable($this->getInCommonFilename())) {
            // Read in the InCommon metadata file
            $xmlstr = @file_get_contents($this->getInCommonFilename());
            if (strlen($xmlstr) > 0) {
                // Need to fix the namespace for Xpath queries to work
                $xmlstr = str_replace('xmlns=','ns=',$xmlstr);
                $xml = new SimpleXMLElement($xmlstr);

                // Fetch the whitelisted IdPs from the datastore
                $whitelist = new whitelist();

                // Select only IdPs from the InCommon metadata
                $result = $xml->xpath(
                    "//EntityDescriptor/IDPSSODescriptor" .
                    "/ancestor::EntityDescriptor" 
                    );

                /* Create a DOMDocument to build up the list of IdPs. */
                $dom = DOMImplementation::createDocument(null,'idps');
                $idps = $dom->documentElement; // Top level <idps> element

                // Loop through the IdPs searching for desired attributes
                foreach ($result as $idx) {
                    // Get the entityID of the IdP. Save it for later.
                    // The entityID will be the keys of the class idpdom.
                    $entityID = '';
                    $xp = $idx[0]->xpath('attribute::entityID');
                    if (($xp !== false) && (count($xp)>0)) {
                        $entityID = (string)$xp[0]->entityID;
                    } else { // No entityID is bad!
                        continue;
                    }

                    // Create an <idp> element to hold sub elements
                    $idp = $dom->createElement('idp');
                    $idp->setAttribute('entityID',$entityID);
                    $idps->appendChild($idp);
           
                    // Search for the desired <idp> attribute sub-blocks
                    $xp = $idx[0]->xpath(
                        'Organization/OrganizationDisplayName');
                    if (($xp !== false) && (count($xp)>0)) {
                        $this->addNode($dom,$idp,
                            'Organization_Name',(string)$xp[0]);
                    }

                    $xp = $idx[0]->xpath('Organization/OrganizationURL');
                    if (($xp !== false) && (count($xp)>0)) {
                        $this->addNode($dom,$idp,'Home_Page',(string)$xp[0]);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='technical']/GivenName");
                    if (($xp !== false) && (count($xp)>0)) {
                        $this->addNode($dom,$idp,
                            'Technical_Name',(string)$xp[0]);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='technical']/EmailAddress");
                    if (($xp !== false) && (count($xp)>0)) {
                        $this->addNode($dom,$idp,
                            'Technical_Address',(string)$xp[0]);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='administrative']/GivenName");
                    if (($xp !== false) && (count($xp)>0)) {
                        $this->addNode($dom,$idp,
                            'Administrative_Name',(string)$xp[0]);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='administrative']/EmailAddress");
                    if (($xp !== false) && (count($xp)>0)) {
                        $this->addNode($dom,$idp,
                            'Administrative_Address',(string)$xp[0]);
                    }

                    // Add a <Whitelisted> block if necessary 
                    if ((strlen($entityID) > 0) && 
                        ($whitelist->exists($entityID))) {
                        $this->addNode($dom,$idp,'Whitelisted','1');
                    }
                }

                // Read in any test IdPs and add them to the list
                if ((is_readable(self::testIdPFilename)) &&
                    (($dom2 = DOMDocument::load(
                              self::testIdPFilename)) !== false)) {
                    $idpnodes = $dom2->getElementsByTagName('idp');
                    foreach ($idpnodes as $idpnode) {
                        $node = $dom->importNode($idpnode,true);
                        $idps->appendChild($node);
                    }
                }
                
                // Sort the DOMDocument by Organization_Name
                $this->idpdom = $this->sortDOM($dom);

                $retval = true;
            }
        }

        return $retval;
    }

    /********************************************************************
     * Function  : getFilename                                          *
     * Returns   : A string of the IdP list filename.                   *
     * This function returns a string of the full path of the IdP list  *
     * filename.  See also setFilename().                               *
     ********************************************************************/
    function getFilename() {
        return $this->idpfilename;
    }

    /********************************************************************
     * Function  : setFilename                                          *
     * Parameter : The new name of the IdP list filename.               *
     * This function sets the string of the full path of the IdP list   *
     * filename.  See also getFilename().                               *
     ********************************************************************/
    function setFilename($filename) {
        $this->idpfilename = $filename;
    }

    /********************************************************************
     * Function  : getInCommonFilename                                  *
     * Returns   : A string of the InCommon metadata filename.          *
     * This function returns a string of the full path of the InCommon  *
     * metadata filename.  See also setInCommonFilename().              *
     ********************************************************************/
    function getInCommonFilename() {
        return $this->incommonfilename;
    }

    /********************************************************************
     * Function  : setInCommonFilename                                  *
     * Parameter : The new name of the InCommon metadata filename.      *
     * This function sets the string of the full path of the InCommon   *
     * metadata filename.  See also getInCommonFilename().              *
     ********************************************************************/
    function setInCommonFilename($filename) {
        $this->incommonfilename = $filename;
    }

    /********************************************************************
     * Function  : entityIDExists                                       *
     * Parameter : The entityID to search for in the idpdom.            *
     * Returns   : True if the given entityID is in the idpdom.         *
     *             False otherwise.                                     *
     * This function runs an xpath query in the idpdom to search for    *
     * the given idp entityID.                                          *
     ********************************************************************/
    function entityIDExists($entityID) {
        $xpath = new DOMXpath($this->idpdom);
        return ($xpath->query("idp[@entityID='$entityID']")->length > 0);
    }

    /********************************************************************
     * Function  : exists                                               *
     * Parameter : The enityID to search for in the idpdom.             *
     * Returns   : True if the given entityID is in the idpdom.         *
     *             False otherwise.                                     *
     * This is simply a convenience function for entityIDExists.        *
     ********************************************************************/
    function exists($entityID) {
        return $this->entityIDExists($entityID);
    }

    /********************************************************************
     * Function  : isWhitelisted                                        *
     * Parameter : The enityID to search for in the idpdom.             *
     * Returns   : True if the given entityID is 'whitelisted'.         *
     *             False otherwise.                                     *
     * This method searches for the given entityID and checks if the    *
     *'Whitelisted' entry has been set to '1'.                          *
     ********************************************************************/
    function isWhitelisted($entityID) {
        $xpath = new DOMXpath($this->idpdom);
        return ($xpath->query(
            "idp[@entityID='$entityID'][Whitelisted=1]")->length > 0);
    }

    /********************************************************************
     * Function  : getWhitelistedIdPs                                   *
     * Returns   : An array of whitelisted IdPs.                        *
     * This method returns an array of whitelisted IdPs where the keys  *
     * of the array are the entityIDs and the values are the            *
     * pretty print Organization Names.                                 *
     ********************************************************************/
    function getWhitelistedIdPs() {
        $retarray = array();
        $xpath = new DOMXpath($this->idpdom);
        $nl = $xpath->query("idp[Whitelisted=1]/attribute::entityID | " . 
                            "idp[Whitelisted=1]/Organization_Name");
        for ($i = 0; $i < $nl->length; $i += 2) {
            $retarray[$nl->item($i)->nodeValue] = $nl->item($i+1)->nodeValue;
        }
        return $retarray;
    }

    /********************************************************************
     * Function  : getNonWhitelistedIdPs                                *
     * Returns   : An array of non-whitelisted IdPs.                    *
     * This method returns an array of non-whitelisted IdPs where the   *
     * keys of the array are the entityIDs and the values are the       *
     * pretty print Organization Names.                                 *
     ********************************************************************/
    function getNonWhitelistedIdPs() {
        $retarray = array();
        $xpath = new DOMXpath($this->idpdom);
        $nl = $xpath->query("idp[not(Whitelisted=1)]/attribute::entityID | " . 
                            "idp[not(Whitelisted=1)]/Organization_Name");
        for ($i = 0; $i < $nl->length; $i += 2) {
            $retarray[$nl->item($i)->nodeValue] = $nl->item($i+1)->nodeValue;
        }
        return $retarray;
    }

    /********************************************************************
     * Function  : getShibInfo                                          *
     * Returns   : An array containing the various shibboleth           *
     *             attributes for the current Shibboleth session. The   *
     *             keys of the array are "pretty print" names of the    *
     *             various attribute value names (such as               *
     *             "User Identifier" for REMOTE_USER) and the values    *
     *             of the array are the actual Shibboleth session       *
     *             values.                                              *
     * This function returns an array with two types of Shibboleth      *
     * information.  The first set of info is specific to the user's    *
     * current Shibboleth session, such as REMOTE_USER. The second set  *
     * of info reads info from the passed-in metadata file specific to  *
     * the IdP, such as the pretty-print name of the IdP.               *
     ********************************************************************/
    function getShibInfo() {
        $shibarray = array();  /* Array to be returned */

        /* Set the blob set of info, namely those shib attributes which *
         * were given by the IdP when the user authenticated.           */
        $entityID = getServerVar('HTTP_SHIB_IDENTITY_PROVIDER');
        $shibarray['Identity Provider'] = $entityID;
        $shibarray['User Identifier'] = getServerVar('HTTP_REMOTE_USER');
        $shibarray['ePPN'] = getServerVar('HTTP_EPPN');
        $shibarray['ePTID'] = getServerVar('HTTP_PERSISTENT_ID');
        $shibarray['First Name'] = getServerVar('HTTP_GIVENNAME');
        $shibarray['Last Name'] = getServerVar('HTTP_SN');
        $shibarray['Display Name'] = getServerVar('HTTP_DISPLAYNAME');
        $shibarray['Email Address'] = getServerVar('HTTP_MAIL');
        $shibarray['Level of Assurance'] = getServerVar('HTTP_ASSURANCE');
        
        /* Make sure to use only the first of multiple email addresses. */
        if (($pos = strpos($shibarray['Email Address'],';')) !== false) {
            $shibarray['Email Address'] = 
              substr($shibarray['Email Address'],0,$pos);
        }

        /* Next, read the attributes for the given IdP. This includes   *
         * values such as the display name for the IdP, the home page   *
         * of the organization, and contact information.                */
        $attrarray = array(
            'Organization_Name',
            'Home_Page',
            'Technical_Name',
            'Technical_Address',
            'Administrative_Name',
            'Administrative_Address'
        );
        $xpath = new DOMXpath($this->idpdom);
        foreach ($attrarray as $attr) {
            $nl = $xpath->query("idp[@entityID='$entityID']/$attr");
            if ($nl->length > 0) {
                $shibarray[preg_replace('/_/',' ',$attr)] = 
                    $nl->item(0)->nodeValue;
            }
        }

        return $shibarray;
    }

}

?>
