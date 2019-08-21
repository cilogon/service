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
 "registration_endpoint": "https://' , $hostname , '/oauth2/register",
 "token_endpoint": "https://' , $hostname , '/oauth2/token",
 "userinfo_endpoint": "https://' , $hostname , '/oauth2/userinfo",
 "jwks_uri": "https://' , $hostname , '/oauth2/certs",
 "response_types_supported": [
  "code"
 ],
 "response_modes_supported": [
  "query",
  "fragment",
  "form_post"
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
  "client_secret_post"
 ],
 "claims_supported" : [
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
  "iss",
  "name",
  "oidc",
  "openid",
  "ou",
  "sub"
 ]
}';
