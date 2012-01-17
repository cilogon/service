<?php

require_once('include/util.php');
require_once('include/autoloader.php');
require_once('include/content.php');

printHeader('Site Maintenance');

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
printFooter();

?>
