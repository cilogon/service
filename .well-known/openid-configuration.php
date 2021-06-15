<?php

$hostname = 'cilogon.org';
$localhost = php_uname('n');
if (in_array($localhost, [ 'poloc.ncsa.illinois.edu' ])) {
    $hostname = 'test.cilogon.org';
} elseif (in_array($localhost, [ 'polod.ncsa.illinois.edu' ])) {
    $hostname = 'dev.cilogon.org';
}

header('Content-Type:application/json;charset=utf-8');

echo '{
 "issuer": "https://' , $hostname , '",
 "authorization_endpoint": "https://' , $hostname , '/authorize",
 "registration_endpoint": "https://' , $hostname , '/oauth2/oidc-cm",
 "token_endpoint": "https://' , $hostname , '/oauth2/token",
 "userinfo_endpoint": "https://' , $hostname , '/oauth2/userinfo",
 "jwks_uri": "https://' , $hostname , '/oauth2/certs",
 "service_documentation": "https://www.cilogon.org/oidc",
 "response_types_supported": [
  "code"
 ],
 "response_modes_supported": [
  "query",
  "fragment",
  "form_post"
 ],
 "grant_types_supported": [
  "authorization_code",
  "refresh_token",
  "urn:ietf:params:grant_type:token_exchange"
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
  "edu.uiuc.ncsa.myproxy.getcert"
 ],
 "token_endpoint_auth_methods_supported": [
  "client_secret_basic",
  "client_secret_post"
 ],
 "claims_supported" : [
  "acr",
  "affiliation",
  "aud",
  "auth_time",
  "cert_subject_dn",
  "email",
  "eppn",
  "eptid",
  "exp",
  "family_name",
  "given_name",
  "iat",
  "idp",
  "idp_name",
  "isMemberOf",
  "iss",
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
