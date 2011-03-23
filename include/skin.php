<?

require_once('util.php');

/************************************************************************
 * Class name : skin                                                    *
 * Description: This class reads in CSS and configuration options       *
 *              for a "skin".  The skin is a named subdirectory under   *
 *              /var/www/html/skin/ and is set by passing the           *
 *              "?skin=..." (or "?cilogon_skin=...") URL parameter.     *
 *              If found, this class verifies the existence of such     *
 *              a named directory and reads the skin.css and config.xml *
 *              files.  It also sets a PHP session variable so that     *
 *              the skin name is remembered across page loads.          *
 *                                                                      *
 *              Note that this calss uses the SimpleXML class to parse  *
 *              the config.xml file.  This stores the XML in a special  *
 *              SimpleXMLElement object, which is NOT an array.  But    *
 *              you can iterate over elements in the structure.  See    *
 *              the PHP SimpleXML online manual "Basic Usage" for       *
 *              more information.                                       *
 *                                                                      *
 *              This class provides a getConfigOption() method to       *
 *              access XML (sub)blocks to get at a config value.        *
 *              It is important to rememeber that the values returned   *
 *              by the getConfigOption() method must be typecast to     *
 *              native datatypes in order to be used effectively.       *
 *                                                                      *
 * An example configuration file (with all available options) is at     *
 *     /var/www/html/skin/config-example.xml                            *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('skin.php');                                         *
 *    $skin = new skin();                                               *
 *    // While outputting the <head> HTML block...                      *
 *    $skin->printSkinLink();                                           *
 *    // Get the value of a configuration option                        *
 *    $whitelist = $skin->getConfigOption('whitelist');                 *
 *   // Now, process entries in the $whitelist                          *
 *   if (($whitelist !== null) && (!empty($whitelist->idp))) {          *
 *       foreach ($whitelist->idp as $entityID) {                       *
 *           echo "<p>" , (string)$entityID , "<\p>\n";                 *
 *       }                                                              *
 *   }                                                                  *
 ************************************************************************/

class skin {

    // The directory name of the skin
    protected $skinname;

    // A SimpleXMLElement object for the config.xml file
    protected $configxml;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new skin object.                                   *
     * Default constructor.  Finds the name of the skin (if any) and    *
     * reads in the config.xml file (if present).                       *
     ********************************************************************/
    function __construct() {
        $this->readSkinName();
        $this->readConfigFile();
    }

    /********************************************************************
     * Function  : readSkinName                                         *
     * Get the name of the skin and store it in the class variable      *
     * $skinname.  This function checks for the name of the skin in     *
     * three places: (1) "?skin=..." URL parameter,                     *
     * (2) "?cilogon_skin=..." URL parameter, and (3) "cilogon_skin"    *
     * PHP session variable.  If it finds the skin name in any of       *
     * these, it then checks to see if such a named 'skin/..."          *
     * directory exists on the server.  If so, it sets the class        *
     * $skinname variable AND the "cilogon_skin" PHP session variable   *
     * (for use on future page loads by the user).                      *
     ********************************************************************/
    function readSkinName() {
        $this->skinname = '';

        // First, look for "?skin=..."
        $skinvar = getGetVar('skin');
        if (strlen($skinvar) == 0) {
            // Next, look for "?cilogon_skin=..."
            $skinvar = getGetVar('cilogon_skin');
        }
        if (strlen($skinvar) == 0) {
            // Finally, check "cilogon_skin" PHP session variable
            $skinvar = getSessionVar('cilogon_skin');
        }

        // Verify we found $skinvar and that the named skin directory exists
        if ((strlen($skinvar) > 0) &&
            (is_readable($_SERVER{'DOCUMENT_ROOT'} . "/skin/$skinvar/"))) {
            $this->skinname = $skinvar;
            setSessionVar('cilogon_skin',$skinvar);
        } else {
            unsetSessionVar('cilogon_skin');
        }
    }

    /********************************************************************
     * Function  : getSkinName                                          *
     * Returns   : The name of the skin stored in the protected class   *
     *             variable $skinname.                                  *
     * This function returns the name of the skin.  Note that you must  *
     * call readSkinName to set the name of the skin.                   *
     ********************************************************************/
    function getSkinName() {
        return $this->skinname;
    }

    /********************************************************************
     * Function  : readConfigFile                                       *
     * This function looks for a file 'config.xml' in the skin          *
     * directory and reads it in, parsing it into the class variable    *
     * $configxml.  It uses SimpleXML to read in the file which strips  *
     * off the top-level <config> from the XML file.                    *
     ********************************************************************/
    function readConfigFile() {
        $this->configxml = null;

        if ((strlen($this->skinname) > 0) &&
            (is_readable($_SERVER{'DOCUMENT_ROOT'} . '/skin/' . 
                         $this->skinname . '/config.xml'))) {
            $xml = @simplexml_load_file($_SERVER{'DOCUMENT_ROOT'} . 
                   '/skin/' . $this->skinname . '/config.xml');
            if ($xml !== false) {
                $this->configxml = $xml;
            }
        }
    }

    /********************************************************************
     * Function  : getconfigxml                                         *
     * Returns   : The SimpleXMLElement object corresponding to the     *
     *             parsed in XML config file.                           *
     * This function returns a SimpleXMLElement corresponding to the    *
     * contents of the skin's config.xml file.  Note that you should    *
     * call readConfigFile to set the contents of $configxml.           *
     ********************************************************************/
    function getconfigxml() {
        return $this->configxml;
    }

    /********************************************************************
     * Function  : getConfigOption                                      *
     * Parameters: One or more parameters corresponding to XML blocks   *
     *             (and possible sub-blocks).                           *
     * Returns   : A SimpleXMLElement corresponding to the passed-in    *
     *             XML option, or 'null' if no such option exists.      *
     * This method returns a SimpleXMLElement block corresponding to    *
     * the passed in arguments.  For example, to get the blacklist of   *
     * idps, call $idps = getConfigOption('blacklist') and then iterate *
     * over $idps with foreach($idps as $idp) { ... }.  To get a single *
     * subblock value such as the initial lifetime number for the       *
     * GridShib-CA client, call $gscanum = (int)getConfigOption('gsca', *
     *'initiallifetime','number').  Note that you should explicitly     *
     * cast the values to int, string, float, etc., when you use them.  *
     ********************************************************************/
    function getConfigOption() {
        $retval = null;
        $numargs = func_num_args();
        if ($numargs > 0) {
            $retval = $this->configxml;
        }
        for ($i = 0; $i < $numargs; $i++) {
            $argval = func_get_arg($i);
            if (empty($retval->$argval)) {
                $retval = null;
                break;
            } else {
                $retval = $retval->$argval;
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : printSkinLink                                        *
     * Call this function in the HTML <head> block to print out the     *
     * <link> tag pointing to the skin.css file.                        *
     ********************************************************************/
    function printSkinLink() {
        if ((strlen($this->skinname) > 0) &&
            (is_readable($_SERVER{'DOCUMENT_ROOT'} . '/skin/' . 
                         $this->skinname . '/skin.css'))) {
            echo '
            <link rel="stylesheet" type="text/css" 
             href="/skin/' , $this->skinname , '/skin.css" />
            ';
        }
    }

}

?>
