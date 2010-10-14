<?php

require_once('../include/util.php');
require_once('../include/whitelist.php');
require_once('../include/incommon.php');

if ($argc == 2) {
    $idpfile = $argv[1];

    $incommon  = new incommon();
    $whitelist = new whitelist();
    $whitelist->readFromStore();
    $idps = $incommon->getOnlyWhitelist($whitelist);
    $result = writeArrayToFile($idpfile,$idps);

    if (!$result) {
       echo "Error! There was a problem writing to the file '$idpfile'.\n";
    }
} else {
    printUsage();
}

function printUsage()
{
    echo "Usage: whiteidps.php WHITEIDPFILE\n";
    echo "     WHITEIDPFILE is the full path name of the whiteidp file\n";
    echo "This function reads a list of idps from the database,\n";
    echo "and writes out the WHITEIDPFILE, which contains the list\n";
    echo "of whitelisted idps along with their 'pretty print' names as\n";
    echo "read in from the InCommon metadata file.\n";
}

?>
