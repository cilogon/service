<?php

require_once '../include/idplist.php';
require_once '../include/util.php';
require_once '../include/whitelist.php';

if (($argc == 2) || ($argc == 3)) {
    $command = $argv[1];
    $filename = whitelist::defaultFilename;
    if ($argc == 3) {
        $filename = $argv[2];
    }

    if (strlen($command) > 0) {
        $white = null;
        $white = new whitelist($filename);

        switch ($command) {
            case 'showfile':
                $white->readFromFile();
                echo "EntityIDs in the file '$filename':\n";
                foreach ($white->whitearray as $key => $value) {
                    echo "    $key\n";
                }
            break;

            case 'showdb':
                $white->readFromStore();
                echo "EntityIDs in the database whitelist:\n";
                foreach ($white->whitearray as $key => $value) {
                    echo "    $key\n";
                }
            break;

            case 'filetodb':
                $white->readFromFile();
                $white->writeToStore();
            break;

            case 'dbtofile':
                $white->readFromStore();
                $white->writeToFile();
            break;

            default:
               printUsage();
            break; 
        } // End switch ($command)
    } else {
        printUsage();
    }
} else {
    printUsage();
}

function printUsage() {
    echo "Usage: whitelist.php COMMAND {FILE}\n";
    echo "     where COMMAND is one of the following:\n";
    echo "         showfile - show the contents of the whilelist file\n";
    echo "         showdb   - show the contents of the database whitelist\n";
    echo "         filetodb - put contents of whitelist in database\n";
    echo "         dbtofile - put contents of database in whitelist file\n";
    echo "     FILE is the optional full path name of the whitelist file\n";
    echo "         (defaults to ", whitelist::defaultFilename ,")\n";
}

?>
