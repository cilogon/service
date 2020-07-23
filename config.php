<?php

/**
 * If needed, set the 'Notification' banner text to a non-empty value
 * and uncomment the 'define' statement in order to display a
 * notification box at the top of each page.
 */

/*
define('BANNER_TEXT',
       'We are currently experiencing problems issuing certificates. We are
       working on a solution. We apologize for the inconvenience.'
);
*/

/**
 * The URL for the OAuth 1.0a dbService.
 */
define('OAUTH1_DBSERVICE_URL', 'http://localhost:8080/oauth/dbService');

/**
 * The URL for the OAuth 2.0 dbService.
 */
define('OAUTH2_DBSERVICE_URL', 'http://localhost:8080/oauth2/dbService');

/**
 * The main URL for the dbService. Defaults to the OAUTH2_DBSERVICE_URL.
 */
define('DEFAULT_DBSERVICE_URL', OAUTH2_DBSERVICE_URL);

/**
 * The full URL of the OAuth 2.0 (OIDC) script for 'createTransation', which
 * associates the current user's user_uid with the OIDC transation.
 */
define('OAUTH2_CREATE_TRANSACTION_URL', OAUTH2_DBSERVICE_URL . '?action=createTransaction');

/**
 * The full URL of the OAuth 1.0a script for 'authorized' endpoint.
 */
define('OAUTH1_AUTHORIZED_URL', 'http://localhost:8080/oauth/authorized');

/**
 * The full path/filename of the InCommon metadata XML file.
 * It should have read permissions for apache (via either owner or group).
 */
define('DEFAULT_INCOMMON_XML', '/var/cache/shibboleth/InCommon-metadata.xml');

/**
 * The full path/filename of the generated list of IdPs in JSON format.
 * It should have read/write permissions for apache (via either owner or group).
 */
define('DEFAULT_IDP_JSON', __DIR__ . '/include/idplist.json');

/**
 * The full path/filename of the XML file containing test IdPs' metadata.
 * If found, these test IdPs will be added to the full IdP list when
 * create()/write() is called. This file should have read/write permissions
 * for apache (either owner or group).
 */
define('TEST_IDP_XML', __DIR__ . '/include/testidplist.xml');

/**
 * The default hostname of the service website. This is used as the public-
 * facing hostname and returned by Util::getHN() in the case that HTTP_HOST
 * is not set.
 */
define('DEFAULT_HOSTNAME', 'cilogon.org');

/**
 * This array is used by Util::getMachineHostname to determine the public-
 * facing FQDN for (1) the PKCS12 certificate download link and (2) the
 * Shibboleth Single Sign-on session initiator URL. It maps the local
 * machine name (uname) to a 'cilogon.org' name. If no mapping exists,
 * then DEFAULT_HOSTNAME is used.
 */
define('HOSTNAME_ARRAY', array(
    'poloa.ncsa.illinois.edu' => 'polo1.cilogon.org' ,
    'polob.ncsa.illinois.edu' => 'polo2.cilogon.org' ,
    'poloc.ncsa.illinois.edu' => 'test.cilogon.org' ,
    'polod.ncsa.illinois.edu' => 'dev.cilogon.org' ,
    'fozzie.nics.utk.edu'     => 'polo3.cilogon.org' ,
));

/**
 * The destination email addresses for "help" and "alerts" mails.
 */
define('EMAIL_HELP', 'help@' . DEFAULT_HOSTNAME);
define('EMAIL_ALERTS', 'alerts@' . DEFAULT_HOSTNAME);

/**
 * If you want to completely disable the ability to fetch X509 certificates,
 * set DISABLE_X509 to true. (Defaults to false.) This is similiar to
 * setting MYPROXY_LOGON below to empty string, but completely hides the
 * "Create Password-Protected Certificate" box, and also checks if the
 * OIDC transaction has the edu.ncsa.uiuc.myproxy.getcert scope.
 *
 */
//define('DISABLE_X509', true);

/**
 * In order for CILogon to be able to generate X.509 certificates, the
 * myproxy-logon binary must be installed and set in the MYPROXY_LOGON
 * define. If left blank, X.509 certificate functionality will be disabled.
 * Also, be sure to set the hostname(s) and port of the MyProxy server, as
 * well as the default certifcate lifetime (in hours).
 */
define('MYPROXY_LOGON', '/usr/bin/myproxy-logon');
define('MYPROXY_HOST', 'myproxy.cilogon.org,myproxy2.cilogon.org');
define('MYPROXY_PORT', '7512');
define('MYPROXY_LIFETIME', '12');

/**
 * Storage type for PHP sessions.
 * Can be one of 'file', 'mysqli', or 'pgsql'. Defaults to 'file'.
 */
define('STORAGE_PHPSESSIONS', 'mysqli');

/**
 * This array contains IdPs that are globally redlit (disabled) for the
 * CILogon service. Each entry contains an IdP's entityId (as shown
 * in the InCommon metadata).
 *
 * NOTE: After modifying this array, run /etc/cron.hourly/idplist.cron so that
 * /var/www/html/include/idplist.xml is updated to remove these IdPs.
 */
define('REDLIT_IDP_ARRAY', array(
    'https://shibboleth.csupomona.edu/idp/shibboleth',
    'https://idp.pitt.edu/idp/shibboleth',
    'https://shib.mdanderson.org/idp/shibboleth',
    'https://idp.itsligo.ie/idp/shibboleth',
    'https://idp.login.iu.edu/idp/shibboleth',
));

/**
 * This array contains OAuth2/OIDC clients that should bypass the CILogon
 * 'Select an Identity Provider' screen and go directly to a specific
 * Identity Provider. Each array key/value pair has the following format:
 *
 *   'URI' => 'entityId'
 *
 * The URI must be a PHP PCRE (Perl-Compatible Regular Expression). See
 * http://www.php.net/manual/en/pcre.pattern.php for details on syntax.
 * '%' (percent) is a good choice for delimiter so that slashes do not
 * need to be escaped. Note that period '.' matches any character,
 * so if you want to match a dot, prefix with a backslash, e.g., '\.' .
 * However, in practice this unnecessary since dots appear mainly in
 * the FQDN.
 *
 * The URI regex should match one of:
 *    * an OAuth2.0 redirect_uri
 *    * an OAuth2.0 client_id
 *
 * The entityId must exactly match the IdP metadata value.
 *
 * If an incoming OIDC redirect_uri or client_id is matched from this array,
 * the associated IdP will be automatically used. This is used primarily by
 * campus gateways (portals) where users only ever log in with a single IdP.
 * It replaces the complex skin configuration which required a combination
 * of <forceinitialidp>, <allowforceinitialidp>, and <portallist>.
 */
define('BYPASS_IDP_ARRAY', array(
    '%^https://iam.scigap.org/auth/realms/virginaaccord/broker/cilogon/endpoint%' =>
        'urn:mace:incommon:virginia.edu',
    '%^https://iam.scigap.org/auth/realms/iugateway/broker/cilogon/endpoint%' =>
        'urn:mace:incommon:iu.edu',
    '%^https://iam.scigap.org/auth/realms/georgiastate/broker/cilogon/endpoint%' =>
        'https://idp.gsu.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/university-of-kentucky-hpc-gateway/broker/cilogon/endpoint%' =>
        'https://ukidp.uky.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/nanoconfinement/broker/cilogon/endpoint%' =>
        'urn:mace:incommon:iu.edu',
    '%^https://iam.scigap.org/auth/realms/new-mexico-state/broker/cilogon/endpoint%' =>
        'https://myidp.nmsu.edu',
    '%^https://iam.scigap.org/auth/realms/oscer/broker/cilogon/endpoint%' =>
        'https://shib.ou.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/southdakota/broker/cilogon/endpoint%' =>
        'https://usd-shibboleth.usd.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/southill/broker/cilogon/endpoint%' =>
        'https://shib-idp.siu.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/alabama-birmingham/broker/cilogon/endpoint%' =>
        'urn:mace:incommon:uab.edu',
    '%^https://iam.scigap.org/auth/realms/utah/broker/cilogon/endpoint%' =>
        'urn:mace:incommon:utah.edu',
    '%^https://bhr-test.internal.ncsa.edu/oidc/callback/%' =>
        'https://idp.ncsa.illinois.edu/idp/shibboleth',
    '%^https://bhr.security.ncsa.illinois.edu/oidc/callback/%' =>
        'https://idp.ncsa.illinois.edu/idp/shibboleth',
    '%^https://portal-dev.security.internal.ncsa.edu/oidc/callback/%' =>
        'https://idp.ncsa.illinois.edu/idp/shibboleth',
    '%^https://portal.security.ncsa.illinois.edu/oidc/callback/%' =>
        'https://idp.ncsa.illinois.edu/idp/shibboleth',
    '%^https://rpz.security.ncsa.illinois.edu/oidc/callback/%' =>
        'https://idp.ncsa.illinois.edu/idp/shibboleth',
    '%^https://iamdev.scigap.org/auth/realms/usd/broker/usd/endpoint%' =>
        'https://usd-shibboleth.usd.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/sdstate/broker/sdsu/endpoint%' =>
        'https://icarus.sdstate.edu/idp/shibboleth',
    '%^https://iam.scigap.org/auth/realms/pfec-hydro/broker/cilogon/endpoint%' =>
        'urn:mace:incommon:iu.edu',
    '%^cilogon:/client_id/5b8f6c5e89b58adf254a2cece1254b13%' =>
        'https://idp.xsede.org/idp/shibboleth',
    '%^cilogon:/client_id/79d280aef88dd3c97bb1cd92f8217286%' =>
        'https://sts.windows.net/06219a4a-a835-44d5-afaf-3926343bfb89/',
));

/**
 * This array contains OAuth2/OIDC client URIs that are allowed to bypass
 * the 'Select an Identity Provider' page when passing 'idphint=...'
 * (a.k.a., 'selected_idp=...').
 *
 * The URI must be a PHP PCRE (Perl-Compatible Regular Expression). See
 * http://www.php.net/manual/en/pcre.pattern.php for details on syntax.
 * '%' (percent) is a good choice for delimiter so that slashes do not
 * need to be escaped. Note that period '.' matches any character,
 * so if you want to match a dot, prefix with a backslash, e.g., '\.' .
 * However, in practice this unnecessary since dots appear mainly in
 * the FQDN.
 *
 * The URI regex should match one of:
 *    * an OAuth2.0 redirect_uri
 *    * an OAuth2.0 client_id
 *
 * This feature is used by portals that have been vetted to show a
 * 'consent to release attributes' on their site (since this is usually
 * handled by the 'Select an Identity Provider' page). It replaces the
 * complex skin configuration which required a combination of
 * <forceinitialidp>, <allowforceinitialidp>, and <portallist> which was
 * previously configured in the 'allowbypass' skin.
 *
 * NOTE: If a matching redirect_url / client_id is found in the
 *       BYPASS_IDP_ARRAY, that IdP takes precedence over a match in
 *       ALLOW_BYPASS_ARRAY.
 */
define('ALLOW_BYPASS_ARRAY', array(
    '%cilogon:/client_id/3cbc990448f1ea8df6ebe128b101d84c%',
    '%cilogon:/client_id/d00f2e2f70cbcd8ea1cab897f946e3a%',
    '%cilogon:/client_id/1883125fe1207b080e5005123bcc0beb%',
    '%cilogon:/client_id/4f41b3c617a2bee2d538a7d09e801f42%',
    '%cilogon:/client_id/4b7cd25266231fe4328dd5596d9dd0a3%',
    '%cilogon:/client_id/2aa1d47daf2964397668e3a64c9ec287%',
    '%cilogon:/client_id/6cbd814fd631ce99a95436705822cb2%',
    '%cilogon:/client_id/d37bc71195aafc638cfc083250cb802%',
    '%cilogon:/client_id/599ebdd631fca76a8d88850dab8569c3%',
    '%^https://flywheel-(dev|prod)\.auth0\.com/.*$%',
    '%^https://.*\.flywheel.io/.*$%',
));

/**
 * This array contains IdPs and Portals that should have a skin
 * applied by force. Each array key/value pair has the following format:
 *
 *    'URI' => 'skinname'
 *
 * The URI must be a PHP PCRE (Perl-Compatible Regular Expression). See
 * http://www.php.net/manual/en/pcre.pattern.php for details on syntax.
 * '%' (percent) is a good choice for delimiter so that slashes do not
 * need to be escaped. Note that period '.' matches any character,
 * so if you want to match a dot, prefix with a backslash, e.g., '\.' .
 * However, in practice this unnecessary since dots appear mainly in
 * the FQDN.
 *
 * The URI regex should match one of :
 *    * an IdP entityID
 *    * an OAuth1.0a callbackuri
 *    * an OAuth2.0 redirect_uri
 *    * an OAuth2.0 client_id
 *
 * In the case that multiple entries match, the skinname for the
 * first match in the list above wins. Skinname is case-insensitive.
 */
define('FORCE_SKIN_ARRAY', array(
    '%^https://login\d?\.ligo\.org/idp/shibboleth%' => 'ligo',
    '%^https://.*\.?seedme\.org%' => 'seedme',
    '%^https://redivis\.com/userRequirements/oauth2/authorize%' => 'redivis',
    '%^https://clowder.ncsa.illinois.edu/clowder/authenticate/cilogon%' => 'ncsa',
    '%^myproxy:oa4mp,2012:/client_id/63408f84f8ba20d1446336ee1f330a0e%' => 'fairdata',
    '%^https://identity.lsst.org/cilogon-result%' => 'lsst',
    '%^https://registry.gw-astronomy.org/secure/redirect%' => 'gwastro',
    '%^https://registry-test.gw-astronomy.org/secure/redirect%' => 'gwastro',
    '%^https://registry-dev.gw-astronomy.org/secure/redirect%' => 'gwastro',
    '%^https://gw-astronomy-dev.cilogon.org/secure/redirect%' => 'gwastro',
    '%^cilogon:/client_id/35ea6130bd4fea11367ba02fcaf2da48%' => 'nih',
    '%^cilogon:/client_id/2ce2d2377dc96beb216c08a5e6ef8726%' => 'nih',
    '%^cilogon:/client_id/5669cffc68ac9b0c2c05275a9499bf64%' => 'nih',
    '%^cilogon:/client_id/29c11c3e800cfa611eeda42891e52274%' => 'nih',
    '%^cilogon:/client_id/77b8fe3403f8901a11ed613050e00092%' => 'nih',
    '%^cilogon:/client_id/78b7ae39b2d4e6190ffa866013ec8ad4%' => 'nih',
    '%^cilogon:/client_id/4052f88f2de770162e1451873b367a61%' => 'nih',
    '%^cilogon:/client_id/71998e36fea6792d48234ca38958d48%' => 'nih',
    '%^cilogon:/client_id/112d2f5ffcb986660e0e57e7bcf72a4e%' => 'nih',
    '%^cilogon:/client_id/620b5873fda93e285ea48ad5b09568d2%' => 'nih',
    '%^cilogon:/client_id/5e918a48b3f70beaed24a2c1bb2dbc34%' => 'nih',
    '%^cilogon:/client_id/1ca80460249331360475a24b81079e68%' => 'nih',
    '%^cilogon:/client_id/4a4fed754f3bcfdb2c75ab850be8c2c4%' => 'nih',
    '%^cilogon:/client_id/40da4844bd5bf5baf29b4c252c49ee7c%' => 'nih',
    '%^cilogon:/client_id/730421ec502c1384a99cc439e412d7a7%' => 'nih',
    '%^cilogon:/client_id/38afed60f24d0f627a23edf10314e097%' => 'nih',
    '%^cilogon:/client_id/56727d508077d1402cdcc66714ecf5ef%' => 'orcidfirst',
    '%^cilogon:/client_id/3176b0133ec1efa8090dcf11ad96877f%' => 'mit',
    '%^cilogon:/client_id/47fabe917b37f4969efe93ffaa4e510a%' => 'flywheel',
    '%^https://.*\.scimma\.org/.*$%' => 'scimma',
    '%^cilogon:/client_id/3c7e9528486a4ab9f141cf7379a7f54a%' => 'classtranscribe',
    '%^cilogon:/client_id/3d39b5d80d2b72ca8a447fd5c7dc5192%' => 'scimma',
    '%^https://flywheel-(dev|prod)\.auth0\.com/.*$%' => 'flywheel',
    '%^https://.*\.flywheel.io/.*$%' => 'flywheel',
));

/**
 * When a new LIGO user attempts to use cilogon.org, an alert is sent to
 * both EMAIL_ALERTS and cilogon-alerts@ligo.org to let them know that
 * the new user needs to be added to the LIGO IdP. However, some users are
 * very persistent and continue to try to log in, generating many emails.
 * To temporarily disable the EMAIL_ALERTS emails, set this to true.
 */
define('DISABLE_LIGO_ALERTS', false);
