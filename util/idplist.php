<?php

require_once('../include/util.php');
require_once('../include/idplist.php');
require_once('../include/whitelist.php');

if ($argc == 2) {
    $idpfile = $argv[1];

    $idplist = new idplist($idpfile);
    $idplist->create();
    $result = $idplist->write();

    if (!$result) {
       echo "Error! There was a problem writing to the file '$idpfile'.\n";
    }
} else {
    printUsage();
}

function printUsage() {
    echo "Usage: idplist.php IDPFILE\n";
    echo "     IDPFILE is the full path name of the idplist.xml file\n";
    echo "This function reads the InCommon metadata and writes out the\n";
    echo "IDPFILE, which contains the list of all IdPs along with\n";
    echo "their attributes needed by the CILogon Service.\n";
}

?>
