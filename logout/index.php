<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::cilogonInit();

// Handle the rare case that the language chooser is shown
// and the user selects a different language.
$submit = Util::getPostVar('submit');
Util::unsetSessionVar('submit');
if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $submit)) {
    Util::setSessionVar('lang', $submit);
}

Content::printLogout();
