<?php

set_include_path(
    '/var/www/html' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/config' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/vendor/cilogon/service-lib/src/Service' . PATH_SEPARATOR .
    '.'
);

require_once 'config.php';
include_once 'config.secrets.php';
require_once 'DBService.php';
require_once 'Util.php';
require_once 'IdpList.php';

use CILogon\Service\IdpList;

if ($argc == 4) {
    $command = $argv[1];
    $file1 = $argv[2];
    $file2 = $argv[3];


    if ($command == 'xml2json') {
        $readtype = 'xml';
        $writetype = 'json';
    } elseif ($command = 'json2xml') {
        $readtype = 'json';
        $writetype = 'xml';
    } else {
        printUsage();
    }

    $idplist = new IdpList($file1, '', false, $readtype);
    if (!$idplist->read($readtype)) {
        fwrite(STDERR, "Unable to read from $file1\n");
        exit(1);
    }

    $idplist->setFilename($file2);
    if (!$idplist->write($writetype)) {
        fwrite(STDERR, "Unable to write to $file2\n");
        exit(1);
    }
} else {
    printUsage();
}


function printUsage()
{
    echo "Usage: convertidplist.php COMMAND {FILE1} {FILE2}\n";
    echo "     where COMMAND is one of the following:\n";
    echo "         xml2json - read FILE1 as XML and write FILE2 as JSON\n";
    echo "         json2xml - read FILE1 as JSON and write FILE2 as XML\n";
    echo "     FILE1 is the file to read from\n";
    echo "     FILE2 is the file to write to\n";
    echo "Convert the idplist file betwewn XML and JSON.\n";
}
