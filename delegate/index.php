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
    Util::changeLanguage($submit);
}

Content::printHeader(_('CILogon Delegation Service'));
Content::printCollapseBegin('maint', _('OAuth1 Service Retired'), false);

echo '
    <div class="card-body px-5">
      <div class="row">
        <div class="col-1 align-self-center text-center">
        ', Content::getIcon('fa-exclamation-triangle fa-2x', 'gold'), '
        </div> <!-- end col-1 -->
        <div class="col">
          <div class="card-text my-2">
            ',
            _('The CILogon OAuth 1.0a Delegation Service was ' .
            'retired on October 1, 2021.'), '
          </div> <!-- end card-text -->
          <div class="card-text my-2">
            ',
            _('For information on using CILogon\'s OpenID Connect (OIDC)' .
            ' service, please visit'), ' <a target="_blank"
            href="https://www.cilogon.org/oidc">www.cilogon.org/oidc</a>.
          </div> <!-- end card-text -->
        </div> <!-- end col -->
       </div> <!-- end row -->
    </div> <!-- end card-body -->
';

Content::printCollapseEnd();
Content::printFooter();
