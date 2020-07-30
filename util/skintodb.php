<?php

set_include_path(
    '/var/www/html' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear-core-minimal/src' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/pear_exception' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/log' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/db' . PATH_SEPARATOR .
    '/var/www/html/vendor/pear/net_ldap2' . PATH_SEPARATOR .
    '/var/www/html/vendor/cilogon/service-lib/src/Service' . PATH_SEPARATOR .
    '.'
);

require_once 'config.php';
include_once 'config.secrets.php';
require_once 'PEAR.php';
require_once 'DB.php';

if ($argc != 2) {
    echo "Usage: " . $argv[0] . " SKINDIR\n";
    echo "    where SKINDIR is a directory containing skin subdirectories\n";
    echo "    each containing config.xml and/or skin.css\n";
    echo "    (typically /var/www/html/skin). Read the skins stored in\n";
    echo "    SKINDIR and write them to the database.\n";
    exit;
}

$basedir = $argv[1];
if ((is_dir($basedir)) && (is_readable($basedir))) {
    $dsn = array(
        'phptype'  => 'mysqli',
        'username' => MYSQLI_USERNAME,
        'password' => MYSQLI_PASSWORD,
        'database' => 'ciloa2',
        'hostspec' => 'localhost'
    );

    $opts = array(
        'persistent'  => true,
        'portability' => DB_PORTABILITY_ALL
    );

    $db = DB::connect($dsn, $opts);
    if (!PEAR::isError($db)) {
        $ins = $db->prepare('INSERT INTO skins VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE config=?, css=?');

        foreach (scandir($basedir) as $skindir) {
            if (
                ($skindir != '.') && ($skindir != '..') &&
                (is_dir($basedir . '/' . $skindir))
            ) {
                echo "Processing skin '$skindir'.";

                // Read in the config XML
                $xml = '';
                $skinconf = $basedir . '/' . $skindir . '/config.xml';
                if (is_readable($skinconf)) {
                    if (($xml = file_get_contents($skinconf)) === false) {
                        $xml = '';
                    }
                }
                //Read in the CSS
                $css = '';
                $skincss = $basedir . '/' . $skindir . '/skin.css';
                if (is_readable($skincss)) {
                    if (($css = file_get_contents($skincss)) === false) {
                        $css = '';
                    }
                }

                // Either XML or CSS should be available
                if ((strlen($xml) > 0) || (strlen($css) > 0)) {
                    if (strlen($xml) > 0) {
                        echo " Found config.xml.";
                    }
                    if (strlen($css) > 0) {
                        echo " Found skin.css.";
                    }
                    $data = array($skindir, $xml, $css, $xml, $css);
                    $db->execute($ins, $data);
                    if (!DB::isError($ins)) {
                        echo " Success!\n";
                    } else {
                        echo " ERROR!\n";
                    }
                } else {
                    echo " No config.xml or skin.css. Skipping.\n";
                }
            }
        }
        $db->disconnect();
    } else {
        echo "Error: Unable to connect to database!\n";
    }
} else {
    echo "Error: Unable to read contents of '$basedir'!\n";
}
