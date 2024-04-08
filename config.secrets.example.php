<?php

/**
 * This is an example file of the various 'secrets' used by CILogon. Make a
 * copy of this file to 'config.secrets.php' and edit as appropriate.
 *
 *     cp config.secrets.example.php config.secrets.php
 *
 * WARNING: DO NOT commit config.secrets.php to github.
 */

/**
 * Configure the parameters for the database connection. DB_TYPE is one of
 * the PEAR DB supported database backends documented at
 * https://pear.php.net/manual/en/package.database.db.intro-dsn.php .
 */
//define('DB_TYPE', 'mysqli');
//define('DB_USERNAME', '');
//define('DB_PASSWORD', '');
//define('DB_HOSTSPEC', '');
//define('DB_HOSTSPEC_RO', ''); // Optional read-only endpoint
//define('DB_DATABASE', '');

/**
 * Configure the parameters for the AWS DynamoDB connection for saving
 * PHP sessions.
 */
//define('DYNAMODB_REGION', 'us-east-2');
//define('DYNAMODB_PHPSESSIONS_TABLE', 'phpsessions');
//define('DYNAMODB_PHPSESSIONS_ACCESSKEY', '');
//define('DYNAMODB_PHPSESSIONS_SECRETACCESSKEY', '');

/**
 * For Google OAuth 2.0 settings specific to this application,
 * go to https://cloud.google.com/console and sign in as google@cilogon.org .
 * The password can be found in LastPass in the 'Shared-CILogon 2' folder
 * in the 'CILogon Google User' key.
 */
//define('GOOGLE_OAUTH2_CLIENT_ID', '');
//define('GOOGLE_OAUTH2_CLIENT_SECRET', '');

/**
 * For GitHub OAuth 2.0 settings specific to this application,
 * Go to https://github.com/organizations/cilogon/settings/applications .
 */
//define('GITHUB_OAUTH2_CLIENT_ID', '');
//define('GITHUB_OAUTH2_CLIENT_SECRET', '');

/**
 * For ORCID OAuth 2.0 settings specific to this application,
 * go to https://orcid.org/developer-tools .
 */
//define('ORCID_OAUTH2_CLIENT_ID', '');
//define('ORCID_OAUTH2_CLIENT_SECRET', '');

/**
 * For Microsoft/Azure AD OAuth 2.0 settings specific to this application,
 * go to https://portal.azure.com .
 */
//define('MICROSOFT_OAUTH2_CLIENT_ID', '');
//define('MICROSOFT_OAUTH2_CLIENT_SECRET', '');

/**
 * Secret key for openssl_encrypt/decrypt of portal cookie. If set, must be
 * 16 bytes (chars) long since AES-128-CBC encryption algorithm is used.
 */
//define('OPENSSL_KEY', '');
