<?php

require_once('autoloader.php');

/************************************************************************
 * Class name : whitelist                                               *
 * Description: This class manages the entityID whitelist utilized by   *
 *              the CILogon Service.  You can read in the current list  *
 *              of whitelisted entityIDs.  You can also manage the      *
 *              list of entityIDs by adding new entityIDs or deleting   *
 *              existing entityIDs, then (re)writing the file to disk.  *
 *                                                                      *
 *              There is one constant in the class that you should      *
 *              set for your particular set up:                         *
 *                                                                      *
 *              defaultFilename - this is the full path and name of the *
 *                  whitelist file used by the CILogon Service.  It     *
 *                  should have read/write permissions for apache       *
 *                  (via either owner or group).                        *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('whitelist.php');                                    *
 *    $white = new whitelist();                                         *
 *    $entityID = 'urn:mace:incommon:uiuc.edu';                         *
 *    if ($white->add($entityID)) {                                     *
 *        if ($white->write()) {                                        *
 *            echo "Added $entityID to whitelist\n";                    *
 *        }                                                             *
 *    }                                                                 *
 ************************************************************************/

class whitelist {

    /* Set the constants to correspond to your particular set up.       */
    const defaultFilename = '/var/www/html/include/whitelist.xml';

    /* The $whitearray holds the list of entityIDs in the whitelist     *
     * file.  The keys of the array are the actual entityIDs (to allow  *
     * for easy searching for a particular entityID).  The values of    *
     * the array are '1's (just to show existence).                     */
    public $whitearray;

    /* These variables should be accessed only by the get/set methods.  */
    protected $whitefilename;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameter : (Optional) The full path of the whitelist filename   *
     *             used by the CILogon Service.                         *
     * Returns   : A new whitelist object.                              *
     * Default constructor.  The contents of the $filename are read in  *
     * and populate the $whitearray.  However, if the contents of       *
     * $filename cannot be read, the $whitearray is set to an empty     *
     * array.                                                           *
     ********************************************************************/
    function __construct($filename=self::defaultFilename) {
        $this->whitearray = array();
        $this->setFilename($filename);
        $this->read();
    }

    /********************************************************************
     * Function  : read                                                 *
     * Returns   : True if the whitelist was read in successfully,      *
     *             false otherwise.                                     *
     * This function is an alias to either readFromFile or              *
     * readFromStore.  This way, the default storage mechanism can      *
     * be changed by modifying the underlying read method that gets     *
     * called.                                                          *
     ********************************************************************/
    function read() {
        return $this->readFromStore();
    }

    /********************************************************************
     * Function  : readFromFile                                         *
     * Returns   : True if the whitelist file was read in successfully, *
     *             false otherwise.                                     *
     * This function reads in the whitelist file containing the list    *
     * of entityIDs to be shown on the CILogon Service page.  The       *
     * entityIDs are stored in the $whitearray as the keys.  The        *
     * <EntityID></EntityID> tags are stripped off.                     *
     ********************************************************************/
    function readFromFile() {
        $retval = false;  // Assume read failed
        if (is_readable($this->getFilename())) {
            $xmlstr = '<?xml version="1.0"?><Dummy>';
            $xmlstr .= @file_get_contents($this->getFilename());
            $xmlstr .= '</Dummy>';
            $xml = new SimpleXMLElement($xmlstr);

            foreach ($xml->children() as $entityID) {
                $this->add((string)$entityID);
                $retval = true;
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : readFromStore                                        *
     * Returns   : True if the whitelist was read in from the database  *
     *             store successfully, false otherwise.                 *
     * This function reads in the whitelist from the database           *
     * containing the list of entityIDs to be shown on the CILogon      *
     * Service page.  The entityIDs are stored in the $whitearray as    *
     * the keys.                                                        *
     ********************************************************************/
    function readFromStore() {
        $retval = false;  // Assume read failed, or empty Idp list
        $store = new store();
        $store->perlobj->eval('@idps = CILogon::Store->getIdps();');
        foreach ($store->perlobj->array->idps as $value) {
            $this->add($value);
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : write                                                *
     * Returns   : True if the whitelist file was written successfully, *
     *             false otherwise.                                     *
     * This function is an alias to either writeToFile or               *
     * writeToStore.  This way, the default storage mechanism can       *
     * be changed by modifying the underlying write method that gets    *
     * called.                                                          *
     ********************************************************************/
    function write() {
        return $this->writeToStore();
    }

    /********************************************************************
     * Function  : writeToFile                                          *
     * Returns   : True if the whitelist file was written successfully, *
     *             false otherwise.                                     *
     * This function writes out the list of entityIDs in $whitearray    *
     * to the whitelist file.  The <EntityID>...</EntityID> tags are    *
     * re-added as appropriate.                                         *
     ********************************************************************/
    function writeToFile() {
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
     * Function  : writeToStore                                         *
     * Returns   : True if the whitelist was written successfully to    *
     *             the persistent store, false otherwise.               *
     * This function writes out the list of entityIDs in $whitearray    *
     * to the database.                                                 *
     ********************************************************************/
    function writeToStore() {
        $retval = false;  // Assume write failed
        if (count($this->whitearray) > 0) {
            $store = new store();
            $store->perlobj->eval('@newidps = ();');
            foreach ($this->whitearray as $key => $value) {
                $store->perlobj->eval('push(@newidps,\'' . $key . '\');');
            }
            $store->perlobj->eval(
                '$result = CILogon::Store->saveIdps(@newidps);');
            $retval = ($store->perlobj->result > 0);
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

    /********************************************************************
     * Function  : remove                                               *
     * Parameter : An entityID string to be removed from the            *
     *             $whitearray.                                         *
     * Returns   : True if the new entityID was removed from the list   *
     *             of entityIDs, false if the passed-in entityID was    *
     *             not in the $whitearray.                              *
     * This function allows you to remove an existing entityID from the *
     * $whitearray.  If the entityID does not exist in the $whitearray, *
     * then it is not removed, and false is returned.                   *
     ********************************************************************/
    function remove($entityID) {
        $retval = false;  // Assume remove from list failed
        if ($this->exists($entityID)) {
            unset($this->whitearray[$entityID]);
            $retval = true;
        }
        return $retval;
    }

}

?>
