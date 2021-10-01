<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Util;
use CILogon\Service\Content;

Util::startPHPSession();

Content::printHeader('CILogon Delegation Service');
Content::printCollapseBegin('maint', 'OAuth1 Service Retired', false);

echo '
    <div class="card-body px-5">
      <div class="row">
        <div class="col-1 align-self-center text-center">
        ', Content::getIcon('fa-exclamation-triangle fa-2x', 'gold'), '
        </div> <!-- end col-1 -->
        <div class="col">
          <div class="card-text my-2">
            The CILogon OAuth 1.0a Delegation Service has been
            retired as of October 1, 2021.
          </div> <!-- end card-text -->
          <div class="card-text my-2">
            Please visit <a target="_blank"
            href="http://www.cilogon.org/oidc">www.cilogon.org/oidc</a> for
            information on using CILogon\'s OpenID Connect (OIDC) service.
          </div> <!-- end card-text -->
        </div> <!-- end col -->
       </div> <!-- end row -->
    </div> <!-- end card-body -->   
';

Content::printCollapseEnd();
Content::printFooter();
