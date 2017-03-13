<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;

/**
 * OAuth2Provider
 */
class OAuth2Provider
{
    /**
     * @var League\OAuth2\Client\Provider $provider Member variable for
     *      OAuth2 PHP provider object
     */
    public $provider = null;

    /**
     * @var array $authzUrlOpts An array of parameters to be passed to
     *      getAuthorizationUrl().
     */
    public $authzUrlOpts = array();

    /**
     * __construct
     *
     * Class constructor. Initializes the class variables using the passed-in
     * Identity Provider ($idp). Sets the class variables 'provider' (the
     * OAuth2 Client library provider object) and 'authzUrlOpts' (for use
     * with getAuthorizationUrl()).
     *
     * @param string $idp The Identity Provider to use for OAuth2 connection.
     * @return OAuth2Provider A new OAuth2Provider for the given IdP.
     */
    public function __construct($idp)
    {
        if (is_null($idp)) {
            $idp = Util::getSessionVar('idpname');
        }
        $idp = strtolower($idp);

        $client_id = '';
        $client_secret = '';
        $classname = '';
        $extraparams = array();

        // Set the client id and secret for the $idp
        if ($idp == 'google') {
            $client_id     = Util::getConfigVar('googleoauth2.clientid');
            $client_secret = Util::getConfigVar('googleoauth2.clientsecret');
            $classname     = 'League\OAuth2\Client\Provider\Google';
            $this->authzUrlOpts = [ 'scope' => ['openid','email','profile'] ];
            $extraparams = array('accessType' => 'offline');
        } elseif ($idp == 'github') {
            $client_id     = Util::getConfigVar('githuboauth2.clientid');
            $client_secret = Util::getConfigVar('githuboauth2.clientsecret');
            $classname     = 'League\OAuth2\Client\Provider\Github';
            $this->authzUrlOpts = [ 'scope' => ['user:email'] ];
        }

        if ((strlen($client_id) > 0) && (strlen($client_secret) > 0)) {
            $this->provider = new $classname(array_merge(array(
                'clientId'     => $client_id,
                'clientSecret' => $client_secret,
                'redirectUri'  => 'https://' . Util::getHN() . '/getuser/'
            ), $extraparams));
        }
    }
}
