<?php

/**
 * If needed, set the 'Notification' banner text to a non-empty value
 * and uncomment the 'define' statement in order to display a
 * notification box at the top of each page.
 */

/*
define('BANNER_TEXT',
       'We are currently experiencing problems. System administrators are
       working on a solution. We apologize for the inconvenience.'
);
*/

/**
 * A local timezone from https://www.php.net/manual/en/timezones.php .
 */
define('LOCAL_TIMEZONE', 'America/Chicago');

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
 * Note: The service will fail to run if this value is not defined.
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
//define('OAUTH1_AUTHORIZED_URL', 'http://localhost:8080/oauth/authorized');

/**
 * An array of the full path/filename of various metadata XML files.
 * These files should be readable by apache.
 */
define('DEFAULT_METADATA_FILES', array(
    '/var/cache/shibboleth/InCommon-metadata.xml',
    '/var/cache/shibboleth/aaf-metadata.xml',
));

/**
 * An array of the URLs corresponding to the metadata XML files above.
 * This array is used by Util::updateIdPList() to generate the
 * idplist.{json,xml} files.
 */
define('DEFAULT_METADATA_URLS', array(
    'https://mdq.incommon.org/entities/idps/all',
    'https://md.aaf.edu.au/aaf-metadata.xml',
));

/**
 * The full path/filename of the generated list of IdPs in JSON format.
 * It should have read/write permissions for apache (via either owner or group).
 * Note: the default idplist.xml file is in the same directory. The filename
 * is calculated by replacing '.json' with '.xml', so no need to define a
 * separate DEFAULT_IDP_XML constant.
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
 * The default domain name of the service website. This is returned by
 * Util::getDN(). If not defined, the domain name is calculated by
 * returning the last two parts of the host name (which is incorrect
 * for UK and Australian domains such as example.org.au).
 */
define('DEFAULT_DOMAINNAME', 'cilogon.org');

/**
 * If HOSTNAME_FOOTER is defined as 'true', the local hostname will be
 * output below the page footer in transparent text.
 */
define('HOSTNAME_FOOTER', false);

/**
 * The destination email addresses for "help" and "alerts" mails.
 */
define('EMAIL_HELP', 'help@cilogon.org');
define('EMAIL_ALERTS', 'alerts@cilogon.org');
// Comment out the following line to prevent "New IdPs Added" emails
define('EMAIL_IDP_UPDATES', 'idp-updates@cilogon.org');

/**
 * Used by PHP Pear Log (in Logger.php), set the default log handler
 * to one of 'console', 'syslog', or 'file' (or another type
 * supported by https://github.com/pear/Log). If using 'file',
 * you can also specify the filename using DEFAULT_LOGNAME.
 */
define('DEFAULT_LOGTYPE', 'syslog');
define('DEFAULT_LOGNAME', '');

/**
 * Define where to store PHP sessions. Must be one of:
 * 'file', 'database', or 'dynamodb'. If not defined, defaults to 'file'.
 * If 'file', optionally set the storage directory with PHPSESSIONS_DIR.
 * If 'database', set appropriate values for DB_* in config.secrets.php.
 * If 'dynamodb', set appropriate values for DYNAMODB_* in config.secrets.php.
 */
define('PHPSESSIONS_STORAGE', 'file');

/**
 * When PHPSESSIONS_STORAGE is 'file', optionally set the storage directory.
 * NOTE: If you use this option, garbage collection MUST be done manually,
 * e.g., set an hourly cronjob to do something like this:
 *     find PHPSESSIONS_DIR -cmin +24 -type f | xargs rm
 */
define('PHPSESSIONS_DIR', '');

/**
 * This array contains IdPs that are globally redlit (disabled) for the
 * CILogon service. Each entry contains an IdP's entityId (as shown
 * in the InCommon metadata).
 *
 * NOTE: After modifying this array, run /etc/cron.hourly/idplist.cron so that
 * /var/www/html/include/idplist.xml is updated to remove these IdPs.
 */
define('REDLIT_IDP_ARRAY', array(
    'https://shib.mdanderson.org/idp/shibboleth',
    'https://idp.itsligo.ie/idp/shibboleth',
    'https://idp.xsede.org/idp/shibboleth',
));

/**
 * This array contains OAuth2/OIDC clients that should bypass the CILogon
 * 'Select an Identity Provider' screen and go directly to a specific
 * Identity Provider. Each array key/value pair has the following format:
 *
 *   'URI_Regex' => 'entityId'
 *
 * The URI_Regex can be either a PHP PCRE (Perl-Compatible Regular
 * Expression) or an exact string match. See
 * https://www.php.net/manual/en/pcre.pattern.php for details on syntax.
 * '!' (exclamation) is a good choice for delimiter so that slashes do not
 * need to be escaped. Note that period '.' matches any character,
 * so if you want to match a dot, prefix with a backslash, e.g., '\.' .
 * However, in practice this unnecessary since dots appear mainly in
 * the FQDN.
 *
 * The URI/Regex should match one of:
 *    * an OAuth2.0 redirect_uri
 *    * an OAuth2.0 client_id
 *    * a CILogon admin_id
 *
 * The entityId must exactly match the IdP metadata value.
 *
 * If an incoming OIDC redirect_uri or client_id is matched from this array,
 * the associated IdP will be automatically used. This is used primarily by
 * campus gateways (portals) where users only ever log in with a single IdP.
 * It replaces the complex skin configuration which required a combination
 * of <forceinitialidp>, <allowforceinitialidp>, and <portallist>.
 */
/*
define('BYPASS_IDP_ARRAY', array(
    '%^https://bhr.security.ncsa.illinois.edu/oidc/callback/%' =>
        'https://idp.ncsa.illinois.edu/idp/shibboleth',
    'cilogon:/client_id/1234567890' =>
        'https://idp.xsede.org/idp/shibboleth',
));
*/

/**
 * This array contains OAuth2/OIDC client URIs that are allowed to bypass
 * the 'Select an Identity Provider' page when passing 'idphint=...'
 * (a.k.a., 'selected_idp=...'). Each array key/value pair has the following
 * format:
 *
 *     'URI/Regex' => '1'
 *
 * This is to store the URIs in the 'keys' of the array rather than in
 * the 'values'.
 *
 * The URI/Regex can be either a PHP PCRE (Perl-Compatible Regular
 * Expression) or an exact string match. See
 * https://www.php.net/manual/en/pcre.pattern.php for details on syntax.
 * '!' (exclamation) is a good choice for delimiter so that slashes do not
 * need to be escaped. Note that period '.' matches any character,
 * so if you want to match a dot, prefix with a backslash, e.g., '\.' .
 * However, in practice this unnecessary since dots appear mainly in
 * the FQDN.
 *
 * The URI/Regex should match one of:
 *    * an OAuth2.0 redirect_uri
 *    * an OAuth2.0 client_id
 *    * a CILogon admin_id
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
/*
define('ALLOW_BYPASS_ARRAY', array(
    'cilogon:/client_id/1234567890' => '1',
    '!^https://.*\.flywheel.io/.*$!' => '1',
));
*/

/**
 * This array contains IdPs and Portals that should have a skin
 * applied by force. Each array key/value pair has the following format:
 *
 *    'URI/Regex' => 'skinname'
 *
 * The URI/Regex can be either a PHP PCRE (Perl-Compatible Regular
 * Expression) or an exact string match. See
 * https://www.php.net/manual/en/pcre.pattern.php for details on syntax.
 * '!' (exclamation) is a good choice for delimiter so that slashes do not
 * need to be escaped. Note that period '.' matches any character,
 * so if you want to match a dot, prefix with a backslash, e.g., '\.' .
 * However, in practice this unnecessary since dots appear mainly in
 * the FQDN.
 *
 * The URI/Regex should match one of :
 *    * an IdP entityID
 *    * an OAuth1.0a callbackuri
 *    * an OAuth2.0 redirect_uri
 *    * an OAuth2.0 client_id
 *    * a CILogon admin_id
 *
 * In the case that multiple entries match, the skinname for the
 * first match in the list above wins. Skinname is case-insensitive.
 */
/*
define('FORCE_SKIN_ARRAY', array(
    'cilogon:/client_id/1234567890' => 'xsede',
    '!^https://.*\.flywheel.io/.*$!' => 'flywheel',
));
*/

/**
 * This array contains admin clients that should be used for Single
 * Sign On (SSO). Each array key/value pair has the following format:
 *
 *    'admin_id' => 'CO_Name'
 *
 * By convention, admin clients are named as follows:
 *
 *     CO_Name Descriptive Name TIER
 *
 * If TIER is PROD, it is typically omitted. Examples:
 *
 *     ACCESS CONECT Registry TEST
 *     ACCESS Service Provider Registry
 */
/*
define('SSO_ADMIN_ARRAY', array(
    'cilogon:/adminClient/15caf92c7e7e12cf54902502f65dbec7/1656685026455' => 'ACCESS',
    'cilogon:/adminClient/561778a9ab8e33a68e6ed581950f5bd5/1661180615314' => 'ACCESS,
));
*/

/**
 * CIL-975 This is an array of Active Directory IdP entityIDs which have
 * trouble dealing with AssertionConsumerServiceURLs containing
 * polo1/polo2. For these IdPs, use 'cilogon.org' in getMachineHostname().
 */
define('ADFS_IDP_ARRAY', array(
    'https://sts.windows.net/06219a4a-a835-44d5-afaf-3926343bfb89/',
    'http://adfs.nsf.gov/adfs/services/trust',
));

/**
 * CIL-1080 This is an array of IdP entityIDs which should not be shown in
 * the "Select an Identity Provider" list, but still be present in
 * idplist.json/xml. This allows problematic entityIDs to pass the
 * "greenlit" test, but not be selectable by end-users (e.g., when the
 * Indiana University IdP asserts an Issuer which is different from their
 * entityId.
 */
define('HIDE_IDP_ARRAY', array(
    'urn:mace:incommon:iu.edu',
));

/**
 * When a new LIGO user attempts to use cilogon.org, an alert is sent to
 * both EMAIL_ALERTS and cilogon-alerts@ligo.org to let them know that
 * the new user needs to be added to the LIGO IdP. However, some users are
 * very persistent and continue to try to log in, generating many emails.
 * To temporarily disable the EMAIL_ALERTS emails, set this to true.
 */
define('DISABLE_LIGO_ALERTS', false);

/**
 * CIL-1674 When the dbService returns STATUS_EPTID_MISMATCH, the new
 * default behavior is to log this as a WARNING. In this case, the
 * dbService?action=getUser call is retried WITHOUT the eptid so as to
 * match the user on the eppn instead. To revert to the old behavior of
 * treating STATUS_EPTID_MISMATCH as an ERROR, set EPTID_MISMATCH_IS_WARNING
 * to false.
 */
//define('EPTID_MISMATCH_IS_WARNING', true);

/**
 * CIL-2178 If OMIT_IDP is set to true, the "idp" parameter is NOT sent to the
 * dbService for SAML-based IdPs. This would allow IdPs to change their
 * entityIDs without adverse effects on their users.
 */
//define('OMIT_IDP', true);

/**
 * CIL-2123 i18n support for all users. If SITE_LANGUAGES is set, then those
 * languages are available to all users. However, if languages are configured
 * in a skin, those take precedence. SITE_LANGUAGES is an array of 5
 * character languages, e.g,. en_US. The languages are shown to the user
 * IN ORDER listed here.
 */
/*
define('SITE_LANGUAGES', array(
    'en_US',
    'fr_FR'
));
*/

/**
 * If SITE_LANGUAGES is defined, you can optionally specify
 * SITE_DEFAULT_LANGUAGE from one of the languages listed in
 * SITE_LANGUAGES. If not specified, the default language is English.
 */
//define('SITE_DEFAULT_LANGUAGE', 'fr_FR');

/**
 * If OMIT_CILOGON_INFO is set to true, the "cilogon_info" parameter is NOT
 * sent to dbService?action=setTransactionState . (Defaults to false.) The
 * "cilogon_info" parameter is the same as the "myproxyinfo" session variable,
 * which should no longer be needed since MyProxy is no longer supported.
 * However, it was discovered in CIL-2274 that omitting "cilogon_info" from
 * the setTransactionState dbService call caused issues for ORCID users
 * without a public email address. This issue was addressed in OA4MP v6.2.x,
 * but it's possible that there was an edge case not found. The
 * OMIT_CILOGON_INFO variable allows for quickly diabling/enabling the
 * cilogon_info parameter.  If cilogon_info is found to be unnecessary, the
 * PHP code should be updated to remove all references to cilogon_info and
 * myproxyinfo.
 */
//define('OMIT_CILOGON_INFO', true);
