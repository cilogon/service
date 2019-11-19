<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::startPHPSession();

Content::printHeader('Site Maintenance');

echo '
<div class="boxed">
<br class="clear"/>
  <p class="centered">
  The CILogon Service is currently undergoing maintenance.
  Please try again in a minute.
  <br class="clear"/>
  Visit <a
  href="http://www.cilogon.org/service/outages">www.cilogon.org/service/outages</a> for more
  information.
  </p>
</div> <!-- boxed -->
';
Content::printFooter();
