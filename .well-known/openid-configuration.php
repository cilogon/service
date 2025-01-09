<?php

require_once __DIR__ . '/../config.php';

$hostname = 'cilogon.org';
if (defined('DEFAULT_HOSTNAME')) {
    $hostname = DEFAULT_HOSTNAME;
}
$localhost = php_uname('n');
if (in_array($localhost, [ 'poloc.ncsa.illinois.edu', 'polol.ncsa.illinois.edu' ])) {
    $hostname = 'test.cilogon.org';
} elseif (in_array($localhost, [ 'polod.ncsa.illinois.edu' ])) {
    $hostname = 'dev.cilogon.org';
}

header('Content-Type:application/json;charset=utf-8');

echo '{
  "issuer": "https://' , $hostname , '",
  "authorization_endpoint": "https://' , $hostname , '/authorize",
  "device_authorization_endpoint": "https://' , $hostname , '/oauth2/device_authorization",
  "registration_endpoint": "https://' , $hostname , '/oauth2/oidc-cm",
  "token_endpoint": "https://' , $hostname , '/oauth2/token",
  "userinfo_endpoint": "https://' , $hostname , '/oauth2/userinfo",
  "introspection_endpoint": "https://' , $hostname , '/oauth2/introspect",
  "revocation_endpoint": "https://' , $hostname , '/oauth2/revoke",
  "jwks_uri": "https://' , $hostname , '/oauth2/certs",
  "service_documentation": "https://www.cilogon.org/oidc",
  "response_types_supported": [
    "code",
    "id_token"
  ],
  "response_modes_supported": [
    "query",
    "fragment",
    "form_post"
  ],
  "grant_types_supported": [
    "authorization_code",
    "client_credentials",
    "refresh_token",
    "urn:ietf:params:oauth:grant-type:token-exchange",
    "urn:ietf:params:oauth:grant-type:device_code"
  ],
  "subject_types_supported": [
    "public"
  ],
  "id_token_signing_alg_values_supported": [
    "RS256",
    "RS384",
    "RS512"
  ],
  "scopes_supported": [
    "openid",
    "email",
    "profile",
    "org.cilogon.userinfo",
    "offline_access"
  ],
  "token_endpoint_auth_methods_supported": [
    "client_secret_basic",
    "client_secret_post"
  ],
  "code_challenge_methods_supported": [
    "plain",
    "S256"
  ],
  "claims_supported" : [
    "acr",
    "affiliation",
    "amr",
    "aud",
    "auth_time",
    "cert_subject_dn",
    "email",
    "entitlement",
    "eppn",
    "eptid",
    "eduPersonAssurance",
    "eduPersonOrcid",
    "exp",
    "family_name",
    "given_name",
    "iat",
    "idp",
    "idp_name",
    "isMemberOf",
    "iss",
    "itrustuin",
    "name",
    "nonce",
    "oidc",
    "openid",
    "ou",
    "pairwise_id",
    "preferred_username",
    "sub",
    "subject_id",
    "token_id",
    "uid",
    "uidNumber",
    "voPersonExternalID"
  ]
}';
