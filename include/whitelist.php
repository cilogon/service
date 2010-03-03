<?php

/************************************************************************
 * Class name : whitelist                                               *
 * Description: This class manages the entityID whitelist utilized by   *
 *              the local Shibboleth Discovery Service (WAYF).  You     *
 *              can read in the current list of whitelisted entityIDs.  *
 *              You can also add a new entityID to the whitelist and    *
 *              tell the WAYF to reload the whitelist.                  *
 *                                                                      *
 *              There are a few constants in the class that you should  *
 *              set for your particular set up.                         *
 *                                                                      *
 *              defaultFilename - this is the full path and name of the *
 *                  whitelist file used by the WAYF.  It should have    *
 *                  read/write permissions for apache (either via       *
 *                  owner or group).                                    *
 *              defaultTomcatUsername - this is the Tomcat username     *
 *                  that can do 'manager' actions to the Tomcat server. *
 *                  This is typically set in the tomcat-users.xml       *
 *                  config file.                                        *
 *              defaultTomcatPassword - this is the password that       *
 *                  corresponds to the defaultTomcatUsername.           *
 *                  Here is a minimal tomcat-users.xml file:            *
 *                                                                      *
 * <?xml version='1.0' encoding='utf-8'?>                               *
 * <tomcat-users>                                                       *
 *     <role rolename="manager"/>                                       *
 *     <user username="manager" password="stupid1" roles="manager"/>    *
 * </tomcat-users>                                                      *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('whitelist.php');                                    *
 *    $white = new whitelist();                                         *
 *    if ($white->read()) {                                             *
 *        $entityID = 'urn:mace:incommon:uiuc.edu';                     *
 *        if ($white->add($entityID)) {                                 *
 *            if ($white->write()) {                                    *
 *                if ($white->reload()) {                               *
 *                    echo "Added $entityID to whitelist\n";            *
 *                }                                                     *
 *            }                                                         *
 *        }                                                             *
 *    }                                                                 *
 ************************************************************************/

class whitelist {

    /* Set the constants to correspond to your particular set up.       */
    const defaultFilename = '/etc/shibboleth/discovery/conf/whitelist.xml';
    const defaultTomcatUsername = 'manager';
    const defaultTomcatPassword = 'stupid1';

    /* The $whitearray holds the list of entityIDs in the whitelist     *
     * file.  The keys of the array are the actual entityIDs (to allow  *
     * for easy searching for a particular entityID).  The values of    *
     * the array are '1's (just to show existence).                     */
    public    $whitearray;

    /* These variables should be accessed only by the get/set methods.  */
    protected $whitefilename;
    protected $tomcatusername;
    protected $tomcatpassword;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameters: (1) The full path of the whitelist filename used by  *
     *                 the local discovery service (WAYF).              *
     *             (2) The Tomcat username with 'manager' role for      *
     *                 restarting the discovery service servlet.        *
     *             (3) The Tomcat password corresponding to the         *
     *                 Tomcat username.                                 *
     * Returns   : A new whitelist object.                              *
     * Default constructor.  All of the parameters are optional, in     *
     * which case they get set to the default constants listed above.   *
     * The $whitearray is initialized to an empty array.                *
     ********************************************************************/
    function __construct($filename=self::defaultFilename,
                         $tomuser=self::defaultTomcatUsername,
                         $tompass=self::defaultTomcatPassword) {
        $this->whitearray = array();
        $this->setFilename($filename);
        $this->setTomcatUsername($tomuser);
        $this->setTomcatPassword($tompass);
    }

    /********************************************************************
     * Function  : read                                                 *
     * Returns   : True if the whitelist file was read in successfully, *
     *             false otherwise.                                     *
     * This function reads in the whitelist file containing the list    *
     * of entityIDs to be shown on the local discovery service (WAYF).  *
     * The entityIDs are stored in the $whitearray as the keys.  The    *
     * <EntityID></EntityID> tags are stripped off.                     *
     ********************************************************************/
    function read() {
        $retval = false;  // Assume read failed
        if (is_readable($this->getFilename())) {
            $xmlstr = '<?xml version="1.0"?><Filter>';
            $xmlstr .= @file_get_contents($this->getFilename());
            $xmlstr .= '</Filter>';
            $xml = new SimpleXMLElement($xmlstr);

            foreach ($xml->children() as $entityID) {
                $this->whitearray[(string)$entityID] = 1;
                $retval = true;
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : write                                                *
     * Returns   : True if the whitelist file was written successfully, *
     *             false otherwise.                                     *
     * This function writes out the list of entityIDs in $whitearray    *
     * to the whitelist file.  The <EntityID>...</EntityID> tags are    *
     * readded as appropriate.                                          *
     ********************************************************************/
    function write() {
         $retval = false; // Assume write failed
         if (is_writable($this->getFilename())) {
             if ($fh = fopen($this->getFilename(),'w')) {
                 foreach ($this->whitearray as $key => $value) {
                     fwrite($fh,"<EntityId>$key</EntityId>\n");
                 }
                 fclose($fh);
                 $retval = true;
             }
         }
         return $retval;
    }

    /********************************************************************
     * Function  : reload                                               *
     * Returns   : True if the whitelist file was reloaded successfully,*
     *             false otherwise.                                     *
     * This function tells the Tomcat server to restart the local       *
     * discovery service, which reloads the whitelist, thus updating    *
     * the list of available organizations in the WAYF.  It requires    *
     * that the Tomcat manager utilities be installed and utilizes      *
     * the manager API to reload the discovery application.  See:       *
     * http://tomcat.apache.org/tomcat-5.5-doc/manager-howto.html#      *
     *    Reload%20An%20Existing%20Application                          *
     ********************************************************************/
    function reload() {
        $retval = false; // Assume couldn't reload whitelist
        $result = @file_get_contents('http://' .
            $this->tomcatusername . ':' . $this->tomcatpassword .
            '@localhost:8080/manager/reload?path=/discovery');
        if (strncmp($result,'OK',2) == 0) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : getFilename                                          *
     * Returns   : A string of the whitelist filename.                  *
     * This function returns a string of the full path of the whitelist *
     * filename.  See also setFilename().                               *
     ********************************************************************/
    function getFilename() {
        return $this->whitefilename;
    }

    /********************************************************************
     * Function  : setFilename                                          *
     * Parameter : The new name of the whitelist filename.              *
     * This function sets the string of the full path of the whitelist  *
     * filename.  See also getFilename().                               *
     ********************************************************************/
    function setFilename($filename) {
        $this->whitefilename = $filename;
    }

    /********************************************************************
     * Function  : setTomcatUsername                                    *
     * Parameter : The new name of the Tomcat username.                 *
     * This function sets the string of the Tomcat username which       *
     * should have a 'manager' role.  Note that there is no 'getter'    *
     * corresponding to this 'setter'.  Instead, use                    * 
     * $this->tomcatusername.                                           *
     ********************************************************************/
    function setTomcatUsername($tomuser) {
        $this->tomcatusername = $tomuser;
    }

    /********************************************************************
     * Function  : setTomcatPassword                                    *
     * Parameter : The new name of the Tomcat password.                 *
     * This function sets the string of the Tomcat password             *
     * corresponding to the Tomcat username. Note that there is no      *
     * 'getter' corresponding to this 'setter'.  Instead, use           * 
     * $this->tomcatpassword.                                           *
     ********************************************************************/
    function setTomcatPassword($tompass) {
        $this->tomcatPassword = $tompass;
    }

    /********************************************************************
     * Function  : exists                                               *
     * Parameter : An entityID string to test for existence in the      *
     *             $whitearray.                                         *
     * Returns   : True if the incoming entityID is in the $whitearray, *
     *             false otherwise.                                     *
     * This function takes in a string of an entityID and sees if that  *
     * entityID is in the $whitearray whitelist.  If so, return true.   *
     ********************************************************************/
    function exists($entityID) {
        $retval = false;  // Assume entityID is not in whitelist
        if (isset($this->whitearray[$entityID])) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : add                                                  *
     * Parameter : An entityID string to be added to the $whitearray.   *
     * Returns   : True if the new entityID was added to the list of    *
     *             entityIDs, false if the passed-in entityID was       *
     *             already in the $whitearray.                          *
     * This function allows you to add a _new_ entityID to the          *
     * $whitearray.  If the entityID already exists in the $whitearray, *
     * then it is not readded, and false is returned.                   *
     ********************************************************************/
    function add($entityID) {
        $retval = false;  // Assume add to list failed
        if ((strlen($entityID) > 0) && (!$this->exists($entityID))) {
            $this->whitearray[$entityID] = 1;
            $retval = true;
        }
        return $retval;
    }
}

?>
