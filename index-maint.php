<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/vendor/autoload.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::startPHPSession();

Content::printHeader('Site Maintenance');
Content::printCollapseBegin('maint', 'Site Maintenance', false);

echo '
    <div class="card-body px-5">
      <div class="row">
        <div class="col-1 align-self-center text-center">
        ', Content::getIcon('fa-exclamation-triangle fa-2x', 'gold'), '
        </div> <!-- end col-1 -->
        <div class="col">
          <div class="card-text my-2">
            The CILogon Service is currently undergoing maintenance.
            Please try again in a few minutes.
          </div> <!-- end card-text -->
          <div class="card-text my-2">
            Visit <a target="_blank"
            href="https://cilogon.statuspage.io/">https://cilogon.statuspage.io/</a> for more
          information.
          </div> <!-- end card-text -->
        </div> <!-- end col -->
       </div> <!-- end row -->
    </div> <!-- end card-body -->   
';

Content::printCollapseEnd();
Content::printFooter();
