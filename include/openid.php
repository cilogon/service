<?php

/************************************************************************
 * Class name : openid                                                  *
 * Description: This class aids in the formation of OpenID user URLs.   *
 *              There is a public array $providerarray which lists      *
 *              the available OpenID Providers (as the keys of the      *
 *              array) and their corresponding URLs (as the values of   *
 *              the array).  If the URL has the string 'username' in    *
 *              it, this must be replaced by the user's actual account  *
 *              username.  This substitution is done automatically by   *
 *              the getURL() method.                                    *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('openid.php');                                       *
 *    $openid = new openid();                                           *
 *    $openid->setProvider('Blogger');                                  *
 *    $openid->setUsername('johndoe');                                  *
 *    // OR simply $openid = new openid('Blogger','johndoe');           *
 *    $url = $openid->getURL();                                         *
 *    // Now do an OpenID login using the $url                          *
 ************************************************************************/

class openid {

    /* The $providerarray lists all of the supported OpenID providers   *
     * as the keys and their corresponding URLs as the values.  If      *
     * there is the string 'username' in the URL, it needs to be        *
     * replaced  by the actual user's username for the URL to be valid. */
    public $providerarray = array(
        'AOL'         => 'http://openid.aol.com' ,
        'Blogger'     => 'http://username.blogspot.com' ,
        'certifi.ca'  => 'http://certifi.ca/username' ,
        'Chi.mp'      => 'http://username.mp' ,
        'clavid'      => 'http://username.clavid.com' ,
        'Flickr'      => 'http://flickr.com/photos/username' ,
        'GetOpenID'   => 'http://getopenid.com/username' ,
        'Google'      => 'http://google.com/accounts/o8/id' ,
        'Hyves'       => 'http://hyves.nl' ,
        'LaunchPad'   => 'http://login.launchpad.net' ,
        'LiquidID'    => 'http://username.liquidid.net' ,
        'LiveJournal' => 'http://username.livejournal.com' ,
        'myID'        => 'http://myid.net' ,
        'myOpenID'    => 'http://myopenid.com' ,
        'MySpace'     => 'http://myspace.com' ,
        'myVidoop'    => 'http://myvidoop.com' ,
        'NetLog'      => 'http://netlog.com/username' ,
        'OneLogin'    => 'https://app.onelogin.com/openid/username' ,
        'OpenID'      => 'http://username' ,
        'Verisign'    => 'http://pip.verisignlabs.com' ,
        'Vox'         => 'http://username.vox.com' ,
        'WordPress'   => 'http://username.wordpress.com' ,
        'Yahoo'       => 'http://yahoo.com' ,
        'Yiid'        => 'http://yiid.com'
    );

    /* The actual OpenID provider to be used.  Corresponds to one of    *
     * keys in the $providerarray.                                      */
    protected $provider;

    /* If the OpenID Provider URL has the string 'username' in it, it   *
     * must be replaced with the actual user's account username.        */
    protected $username;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameters: (1) The name of the OpenID provider, corresponding   *
     *                 to one of the keys in the $providerarray.        *
     *                 Defaults to 'OpenID'.                            *
     *             (2) The user's account username to be utilized when  *
     *                 forming the OpenID URL containing the string     *
     *                 'username'.  Defaults to 'username'.             *
     * Returns   : A new openid object.                                 *
     * Default constructor.  This mehthod sets the $provider and        *
     * $username to the passed in values.  These can be (re)set after   *
     * ojbect creation by the setProvider() and setUsername() methods.  *
     ********************************************************************/
    function __construct($provider='OpenID',$username='username') {
        $this->setProvider($provider);
        $this->setUsername($username);
    }

    /********************************************************************
     * Function  : getProvider                                          *
     * Returns   : The OpenID provider to be utilized.                  *
     * This method returns the class variable $provider.                *
     ********************************************************************/
    function getProvider() {
        return $this->provider;
    }

    /********************************************************************
     * Function  : setProvider                                          *
     * Parameter : The OpenID provider to be utilized.                  *
     * This method sets the class variable $provider to the passed-in   *
     * OpenID provider.  It first checks to see if the parameter is a   *
     * key in the $providerarray.  If so, it sets $provider.  Otherwise *
     * no action is taken.                                              *
     ********************************************************************/
    function setProvider($provider) {
        if ($this->exists($provider)) {
            $this->provider = $provider;
        }
    }

    /********************************************************************
     * Function  : getUsername                                          *
     * Returns   : The user's account username.                         *
     * This method returns the class variable $username.                *
     ********************************************************************/
    function getUsername() {
        return $this->username;
    }

    /********************************************************************
     * Function  : setUsername                                          *
     * Parameter : The username to be put in the OpenID URL.            *
     * This method sets the class variable $username.                   *
     ********************************************************************/
    function setUsername($username) {
        $username = trim($username);
        $this->username = $username;
    }

    /********************************************************************
     * Function  : exists                                               *
     * Parameter : (Optional) The name of an OpenID Provider.  If not   *
     *             passed in, defaults to the class variable $provider. *
     * Returns   : True if the OpenID provider exists in the            *
     *             $providerarray.  False otherwise.                    *
     * This method takes zero or one argument.  If no arguments are     *
     * passed in, then it uses the current $provider as previously set  *
     * by setProvider().  If one argument is given, then that provider  *
     * value is searched for in the $providerarray.  If the given       *
     * provider is a key in the $providerarray, true is returned.       *
     ********************************************************************/
    function exists() {
        return isset($this->providerarray[
            (func_num_args()==0 ? $this->getProvider() : func_get_arg(0))]);
    }

    /********************************************************************
     * Function  : getURL                                               *
     * Returns   : The OpenID URL to be used for an OpenID login.       *
     * This method returns the URL to be used for an OpenID login.  If  *
     * the URL in the $providerarray contains the string 'username',    *
     * the class variable $username is substituted, giving a user-      *
     * specific URL.  Otherwise, the URL is simply returned without any *
     * string replacement.                                              *
     ********************************************************************/
    function getURL() {
        $url = $this->providerarray[$this->getProvider()];
        return preg_replace('/username/',$this->username,$url);
    }
}

?>
