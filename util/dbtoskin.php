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
    echo "    where SKINDIR is a directory to write skin subdirectories\n";
    echo "    (typically /var/www/html/skin). Read skins stored in the\n";
    echo "    database and write them to the filesystem under SKINDIR.\n";
    exit;
}

$basedir = $argv[1];
if ((is_dir($basedir)) && (is_readable($basedir))) {
    if (substr($basedir, -1, 1) == '/') { // Chop off any trailing slash
        $basedir = substr($basedir, 0, strlen($basedir) - 1);
    }
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

    $overwrite = false;
    $db = DB::connect($dsn, $opts);
    if (!PEAR::isError($db)) {
        $res = $db->query('SELECT * FROM skins');
        if (!DB::isError($res)) {
            while ($res->fetchInto($row, DB_FETCHMODE_ASSOC)) {
                $name = $row['name'];
                $config = $row['config'];
                $css = $row['css'];
                if ((!$overwrite) && (file_exists("$basedir/$name"))) {
                    $line = readline("'$basedir/$name' exists. Overwrite? (Yes/No/All, default=No) ");
                    $ans = strtoupper(substr($line, 0, 1));
                    if ((strlen($ans) == 0) || ($ans == 'N')) {
                        echo "Skipping skin '$name'.\n";
                        continue;
                    } elseif ($ans == 'A') {
                        $overwrite = true;
                    }
                }
                echo "Writing skin '$name'.\n";
                if (
                    (file_exists("$basedir/$name")) ||
                    (mkdir("$basedir/$name"))
                ) {
                    if (strlen($config) > 0) {
                        if (file_put_contents("$basedir/$name/config.xml", $config) === false) {
                            echo "Error writing '$basedir/$name/config.xml!'\n";
                        }
                    }
                    if (strlen($css) > 0) {
                        if (file_put_contents("$basedir/$name/skin.css", $css) === false) {
                            echo "Error writing '$basedir/$name/skin.css!'\n";
                        }
                    }
                } else {
                    echo "Error creating '$basedir/$name'!\n";
                }
            }
        } else {
            echo " ERROR! " . $res->getMessage() . "\n";
        }

        $db->disconnect();
    } else {
        echo "Error: Unable to connect to database!\n";
    }
} else {
    echo "Error: Unable to write to '$basedir'!\n";
}
