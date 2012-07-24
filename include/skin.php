<?php

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
 *              Note that this class uses the SimpleXML class to parse  *
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
 *   require_once('skin.php');                                          *
 *   $skin = new skin();                                                *
 *   // While outputting the <head> HTML block...                       *
 *   $skin->printSkinLink();                                            *
 *   // Get the value of a configuration option                         *
 *   $idpwhitelist = $skin->getConfigOption('idpwhitelist');            *
 *   // Now, process entries in the $idpwhitelist                       *
 *   if ((!is_null($idpwhitelist)) && (!empty($idpwhitelist->idp))) {   *
 *       foreach ($idpwhitelist->idp as $entityID) {                    *
 *           echo "<p>" , (string)$entityID , "<\p>\n";                 *
 *       }                                                              *
 *   }                                                                  *
 *   // Check to see if <hideportalinfo> is set                         *
 *   $hideportalinfo = false;                                           *
 *   $hpi=$skin->getConfigOption('portallistaction','hideportalinfo');  *
 *   if ((!is_null($hpi)) && ((int)$hpi > 0)) {                         *
 *       $hideportalinfo = true;                                        *
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
        $this->setMyProxyInfo();
        $this->readConfigFile();
    }

    /********************************************************************
     * Function  : readSkinName                                         *
     * Get the name of the skin and store it in the class variable      *
     * $skinname.  This function checks for the name of the skin in     *
     * several places: (1) In a URL parameter (can be "?skin=",         *
     * "?cilogon_skin=", "?vo=") (2) cilogon_vo form input variable     *
     * and (3) "cilogon_skin" PHP session variable.  If it finds the    *
     * skin name in any of these, it then checks to see if such a named *
     * 'skin/..." directory exists on the server.  If so, it sets the   *
     * class variable $skinname AND the "cilogon_skin" PHP session      *
     * variable (for use on future page loads by the user).             *
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
            // Next, look for "?vo=..."
            $skinvar = getGetVar('vo');
        }
        if (strlen($skinvar) == 0) {
            // Next, check "cilogon_vo" form input variable
            $skinvar = getPostVar('cilogon_vo');
        }
        if (strlen($skinvar) == 0) {
            // Finally, check "cilogon_skin" PHP session variable
            $skinvar = getSessionVar('cilogon_skin');
        }

        // If we found $skinvar, check to see if a skin directory with that
        // name exists.  Loop through all skin directories so we can do a
        // case-insenstive comparison.  If we find a match, set skinname.
        $found = false;
        if (strlen($skinvar) > 0) {
            $basedir = $_SERVER{'DOCUMENT_ROOT'} . '/skin';
            if ($handle = opendir($basedir)) {
                while ((false !== ($file=readdir($handle))) && (!$found)) {
                    if (($file != '.') && ($file != '..') && 
                        (is_dir("$basedir/$file")) && 
                        (strcasecmp($skinvar,$file) == 0)) {
                        $this->skinname = $file;
                        setSessionVar('cilogon_skin',$file);
                        $found = true;
                    }
                }
                closedir($handle);
            }
        }
        if (!$found) {
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
     * directory. If there is no skin specified, then it looks for the  *
     * "default" config.xml file located at the top of the skin         *
     * directory. If either file is found, it reads it in and parses it *
     * into the class variable $configxml. It uses SimpleXML to read in *
     * the file which strips off the top-level <config> from the XML.   *
     ********************************************************************/
    function readConfigFile() {
        $this->configxml = null;

        /* Note that if $this->skinname is blank, then we are simply    *
         * reading the config.xml file at the top-level skin directory. */
        if (is_readable($_SERVER{'DOCUMENT_ROOT'} . '/skin/' . 
                     $this->skinname . '/config.xml')) {
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
     * idps, call $idps = getConfigOption('idpblacklist') and then      *
     * iterate over $idps with foreach($idps as $idp) { ... }.  To get  *
     * a single subblock value such as the initial lifetime number for  *
     * the GridShib-CA client, call $gscanum =                          *
     * (int)getConfigOption('gsca','initiallifetime','number'). Note    *
     * that you should explicitly cast the values to int, string,       *
     * float, etc., when you use them.                                  *
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

    /********************************************************************
     * Function  : hasIdpWhitelist                                      *
     * Returns   : True if skin has a non-empty <idpwhitelist>.         *
     * This function checks for the presence of a <idpwhitelist> block  *
     * in the skin's config file.  There must be at least one <idp>     *
     * in the <idpwhitelist>.                                           *
     ********************************************************************/
    function hasIdpWhitelist() {
        $retval = false;  // Assume no <idpwhitelist> configured
        $idpwhitelist = $this->getConfigOption('idpwhitelist');
        if ((!is_null($idpwhitelist)) && (!empty($idpwhitelist->idp))) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : hasIdpBlacklist                                      *
     * Returns   : True if skin has a non-empty <idpblacklist>.         *
     * This function checks for the presence of a <idpblacklist> block  *
     * in the skin's config file.  There must be at least one <idp>     *
     * in the <idpblacklist>.                                           *
     ********************************************************************/
    function hasIdpBlacklist() {
        $retval = false;  // Assume no <idpblacklist> configured
        $idpblacklist = $this->getConfigOption('idpblacklist');
        if ((!is_null($idpblacklist)) && (!empty($idpblacklist->idp))) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : idpWhitelisted                                       *
     * Parameter : The entityId of an IdP to check for whitelisting.    *
     * Returns   : True if the given IdP entityId is in the skin's      *
     *             whitelist (or if the skin doesn't have a whitelist). *
     * This method checks to see if a given entityId of an IdP          *
     * is whitelisted.  "Whitelisted" in this case means either (a) the *
     * entityId is in the skin's <idpwhitelist> list or (b) the skin    *
     * doesn't have a <idpwhitelist> at all.  In the second case, all   *
     * IdPs are by default "whitelisted".  If you want to find if an    *
     * IdP should be listed in the WAYF, use "idpAvailable" which       *
     * checks the whitelist AND the blacklist.                          *
     ********************************************************************/
    function idpWhitelisted($entityId) {
        $retval = true;  // Assume the entityId is 'whitelisted'
        if ($this->hasIdpWhitelist()) {
            $idpwhitelist = $this->getConfigOption('idpwhitelist');
            $found = false;
            foreach ($idpwhitelist->idp as $whiteidp) {
                if ($entityId == ((string)$whiteidp)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $retval = false;
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : idpBlacklisted                                       *
     * Parameter : The entityId of an IdP to check for blacklisting.    *
     * Returns   : True if the given IdP entityId is in the skin's      *
     *             blacklist.                                           *
     * This method checks to see if a given entityId of an IdP          *
     * appears in the skin's <idpblacklist>.                            * 
     ********************************************************************/
    function idpBlacklisted($entityId) {
        $retval = false;  // Assume entityId is NOT in the idpblacklist
        if ($this->hasIdpBlacklist()) {
            $idpblacklist = $this->getConfigOption('idpblacklist');
            foreach ($idpblacklist->idp as $blackidp) {
                if ($entityId == ((string)$blackidp)) {
                    $retval = true;
                    break;
                }
            }
        }
        return $retval;
    }

    /********************************************************************
     * Function  : idpAvailable                                         *
     * Parameter : The entityId of an IdP to check to see if it should  *
     *             be available in the WAYF.                            *
     * Returns   : True if the given IdP entityId is available to be    *
     *             selected in the WAYF.                                *
     * This method combines idpWhitelisted and idpBlacklisted to return *
     * a "yes/no" for if a given IdP should be made available for       *
     * selection in the WAYF.  It first checks to see if the IdP is     *
     * whitelisted.  If not, it returns false. Otherwise, it then       *
     * checks if the IdP is blacklisted.  If not, it returns true.      *
     ********************************************************************/
    function idpAvailable($entityId) {
        $retval = false;   // Assume IdP is not available in the WAYF
        if (($this->idpWhitelisted($entityId)) &&
            (!$this->idpBlacklisted($entityId))) {
                $retval = true;
            }
        return $retval;
    }

    /********************************************************************
     * Function  : setMyProxyInfo                                       *
     * This method sets the 'myproxyinfo' PHP session variable.  The    *
     * variable has the form "info:key1=value1,key2=value2,..." and is  *
     * passed to the 'myproxy-logon' command as part of the username    *
     * when fetching a credential.  The MyProxy server will do extra    *
     * processing based on the content of this "info:..." tag.  If the  *
     * skinname is not empty, that is added to the info tag.  Also,     *
     * the apache REMOTE_ADDR is added.  For other key=value pairs that *
     * get added, see the code below.                                   *
     ********************************************************************/
    function setMyProxyInfo() {
        $infostr = '';

        // Add the skinname if available
        if (strlen($this->skinname) > 0) {
            $infostr .= 'cilogon_skin=' . $this->skinname;
        }

        // Add the REMOTE_ADDR
        $remoteaddr = getServerVar('REMOTE_ADDR');
        if (strlen($remoteaddr) > 0) {
            $infostr .= (strlen($infostr) > 0 ? ',' : '') .  
                        "remote_addr=$remoteaddr";
        }

        // Add ePPN, ePTID, and openidID if available
        foreach (array('ePPN','ePTID','openidID') as $id) {
            $sessvar = getSessionVar($id);
            if (strlen($sessvar) > 0) {
                $infostr .= (strlen($infostr) > 0 ? ',' : '') . "$id=$sessvar";
            }
        }
         
        // Finally, set the "myproxyinfo" PHP session variable
        if (strlen($infostr) > 0) {
            setSessionVar('myproxyinfo',"info:$infostr");
        } else {
            unsetSessionVar('myproxyinfo');
        }
    }

    /********************************************************************
     * Function  : hasPortalList                                        *
     * Returns   : True if skin has a non-empty <portallist>.  This     *
     * function checks for the presence of a <portallist> block in the  *
     * skin's config file.  There must be at least one <portal> in the  *
     * <portallist>.                                                    *
     ********************************************************************/
    function hasPortalList() {
        $retval = false;  // Assume no <portallist> configured
        $portallist = $this->getConfigOption('portallist');
        if ((!is_null($portallist)) && (!empty($portallist->portal))) {
            $retval = true;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : portalListed                                         *
     * Parameter : A "callback" URL of a portal accessing the delegate  *
     *             service.                                             *
     * Returns   : True if the callback URL matches one of the patterns *
     *             in the skin's <portallist>.  False otherwise.        *
     * This function takes in a "callback" URL of a portal passed to    *
     * the CILogon Delegate service.  It then looks through the list    *
     * of <portal> patterns in the skin's <portallist>.  If the         *
     * callback URL matches any of these patterns, true is returned.    *
     * This is used to hide the "Site Name / Site URL / Service URL"    *
     * box on the delegation WAYF page, for example.                    *
     ********************************************************************/
    function portalListed($portalurl) {
        $retval = true;  // Assume the portalurl matches a listed <portal>
        if ($this->hasPortalList()) {
            $portallist = $this->getConfigOption('portallist');
            $found = false;
            foreach ($portallist->portal as $portalmatch) {
                if (preg_match(((string)$portalmatch),$portalurl)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $retval = false;
            }
        }
        return $retval;
    }

}

?>
