<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';
require_once __DIR__ . '/index-functions.php';

use CILogon\Service\Util;

Util::cilogonInit();

// This array contains the cookies that we do not want to show to the
// user because we don't want these cookies to be deleted. The cookie
// names are then excluded from the cookie counts, as well as the
// list of cookies which could be deleted.
$hide = array(
    'CSRF',
    'PHPSESSID',
    'lastaccess',
    'myproxyinfo',
);

// Get the value of the 'submit' input element
$submit = Util::getGetOrPostVar('submit');
Util::unsetSessionVar('submit');

// Depending on the value of the clicked 'submit' button,
// take action and print out HTML.
switch ($submit) {
    case 'Delete Checked':
        deleteChecked();
        break; // End case 'Delete Checked'

    case 'Delete Browser Cookies':
        deleteBrowserCookies();
        break; // End case 'Delete Browser Cookies'

    case 'Delete Session Variables':
        deleteSessionVariables();
        break; // End case 'Delete Session Variables'

    case 'Delete ALL':
        deleteBrowserCookies();
        deleteSessionVariables();
        break; // End case 'Delete ALL'
} // End switch($submit)

printMainCookiesPage();
