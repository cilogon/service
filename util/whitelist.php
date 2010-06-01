<?php

require_once('../include/whitelist.php');
require_once('../include/util.php');

if ($argc == 2) {
    $command = $argv[1];

    if (strlen($command) > 0) {
        $white = new whitelist();

        switch ($command) {
            case 'showfile':
                $white->readFromFile();
                echo "EntityIDs in the whitelist.xml file:\n";
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

function printUsage()
{
    echo "Usage: whitelist.php COMMAND\n";
    echo "     where COMMAND is one of the following:\n";
    echo "         showfile - show the contents of the whilelist.xml file\n";
    echo "         showdb   - show the contents of the database whitelist\n";
    echo "         filetodb - put contents of whitelist.xml in database\n";
    echo "         dbtofile - put contents of database in whitelist.xml\n";
    echo "     Note: file resides in /var/www/html/include/whitelist.xml\n";
}

?>
