<?php

/****************************************************************************
 * Function  : autoloader                                                   *
 * Parameter : The name of the class / file to automatically load.          *
 *             This parameter is automatically passed to this function      *
 *             by the PHP interpreter by the use of the function            *
 *             spl_autoload_register('autoloader') given at the bottom      *
 *             of this file.                                                *
 *                                                                          *
 * Taken from http://www.php.net/manual/en/language.oop5.autoload.php#91119 *
 *       and  http://www.php.net/manual/en/language.oop5.autoload.php#94441 *
 * By including this file, any classes are automatically included when      *
 * you do a new() on them, i.e. no no need to do "require_once(class.php)". *
 ****************************************************************************/
function autoloader($className) {
    /* Directories added here must be relative to the script that *
     * uses this file.  New entries can be added to this list.    */
    $directories = array(
        '',
        'classes/',
        'include/',
        '../include/'
    );

    /* Add your file naming formats here. */
    $fileNameFormats = array(
        '%s.php',
        '%s.class.php',
        'class.%s.php',
        '%s.inc.php'
    );

    /* This is to take care of the PEAR style of naming classes. */
    $path = str_ireplace('_', '/', $className);
    if (@include_once $path.'.php') {
        return;
    }
   
    foreach ($directories as $directory) {
        foreach ($fileNameFormats as $fileNameFormat) {
            $path = $directory.sprintf($fileNameFormat,$className);
            if (file_exists($path)) {
                require_once $path;
                if (class_exists($className)) {
                    return;
                }
            }
        }
    }
}

spl_autoload_register('autoloader');

?>
