<?

require_once('util.php');
require_once('Config.php');

/************************************************************************
 * Class name : skin                                                    *
 * Description: This class 
 *                                                                      *
 * Example usage:                                                       *
 ************************************************************************/

class skin {

    // The directory name of the skin
    protected $skinname;

    // An array containing the parsed contents of the config.xml file
    protected $configarray;

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
     * directory and reads it in, parsing it into the class array       *
     * $configarray.  It uses PEAR::Config to parse the XML file, and   *
     * does additional processing to strip off the ['root'] array       *
     * automatically added by the PEAR::Config module.  It also strips  *
     * off the top-level <config> from the XML file, making it easier   *
     * to reference useful XML config options in getConfigOption().     *
     ********************************************************************/
    function readConfigFile() {
        $this->configarray = array();

        if ((strlen($this->skinname) > 0) &&
            (is_readable($_SERVER{'DOCUMENT_ROOT'} . '/skin/' . 
                         $this->skinname . '/config.xml'))) {
            $conf = new Config;
            $root =& $conf->parseConfig($_SERVER{'DOCUMENT_ROOT'} . '/skin/' .
                $this->skinname . '/config.xml','XML');
            if (!(PEAR::isError($root))) {
                $peararray = $root->toArray();
                if (array_key_exists('root',$peararray)) {
                    $rootarray = $peararray['root'];
                    $keys = array_keys($rootarray);
                    if (count($keys) == 1) {
                        // Strip off top-level <config> XML tag
                        $this->configarray = $rootarray[$keys[0]];
                    }
                }
            }
        }
    }

    /********************************************************************
     * Function  : getConfigArray                                       *
     * Returns   : The array corresponding to the XML config file.      *
     * This function returns an array corresponding to the contents of  *
     * the skin's config.xml file.  Note that you should call           *
     * readConfigFile to set the contents of $configarray.              *
     ********************************************************************/
    function getConfigArray() {
        return $this->configarray;
    }

    /********************************************************************
     * Function  : getConfigOption                                      *
     * Parameter : The name of the config.xml option to return.         *
     * Returns   : Either a single value or an array corresponding to   *
     *             the passed in XML option.                            *
     * This function returns the value of a particular XML option from  *
     * the config.xml file.  Some special processing is performed to    *
     * ease the parsing of arrays.  If a particular XML option has a    *
     * single value, e.g. <name>Mark</name>, then the return value of   *
     * getConfigOption('name') is the string "Mark".  If an XML block   *
     * has several of the same named subblocks, e.g.                    *
     * <ages> <age>15</age> <age>34</age> <age>56</age> </ages>         *
     * extra processing is done so that getConfigOption('ages') returns *
     * an array ['15','34','56'].  In other words, the <age></age>      *
     * tags are removed.   In all other cases, an array is returned     *
     * containing the XML blocks/subblocks and values (i.e. you must    *
     * perform processing of the XML tree yourself).                    *
     ********************************************************************/
    function getConfigOption($opt) {
        if (array_key_exists($opt,$this->configarray)) {
            $temp = $this->configarray[$opt];
            if (is_array($temp)) {
                $keys = array_keys($temp);
                if (count($keys) == 1) {
                    return $temp[$keys[0]];
                } else {
                    return $temp;
                }
            } else {
                return $temp;
            }
        }
    }

    /********************************************************************
     * Function  : printSkinLink                                        *
     * Call this function in the HTML header to print out the <link>    *
     * tag pointing to the skin.css file.                               *
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
