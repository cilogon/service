/***************************************************************************
 * Function  : boxExpand                                                   *
 * This function is called by a "setInterval" call in the OpenID username  *
 * input text box.  It allows the text box to grow up to 20 characters     *
 * in size (above the default 9 characters) when the user inputs a long    *
 * username.                                                               *
 ***************************************************************************/
function boxExpand()
{
  var usernamefield = document.getElementById('openidusername');
  if (usernamefield !== null) {
    var fieldvalue = usernamefield.value;

    if ((fieldvalue === null) || (fieldvalue.length === 0)) {
      usernamefield.size = 9;
    }

    if (fieldvalue.length >= 8) {
      if (fieldvalue.length < 20) {
        usernamefield.size = fieldvalue.length + 1;
      } else {
        usernamefield.size = 20;
      }
    }
  }
}

/***************************************************************************
 * Function  : selectOID                                                   *
 * Parameter : A string corresponding to the new OpenID provider.          *
 * This function is called when the user selects a new OpenID provider     *
 * from the dropdown list of available providers.  It changes the OpenID   *
 * URL string (something like http://username.wordpress.com) with the      *
 * newly selected OpenID Provider's URL.  These URLs are stored in a       *
 * "providers" object, where the attributes are the passed-in provider     *
 * strings, and the values are the corresponding URLs.  This function      *
 * checks to see if "username" is part of the URL.  If so, it replaces     *
 * that portion of the URL with an input text box allowing the user to     *
 * type in a username for that provider.  The dropdown icon is also        *
 * updated to the new provider icon.                                       *
 ***************************************************************************/
function selectOID(provider)
{
  /* An object linking provider string names to URLs */
  var providers = {
    'AOL'         : 'http://openid.aol.com' ,
    'Blogger'     : 'http://username.blogspot.com' ,
    'certifi.ca'  : 'http://certifi.ca/username' ,
    'Chi.mp'      : 'http://username.mp' ,
    'clavid'      : 'http://username.clavid.com' ,
    'Clickpass'   : 'http://clickpass.com/public/username' ,
    'Flickr'      : 'http://flickr.com/photos/username' ,
    'Fupei'       : 'http://id.fupei.com/username' ,
    'GetOpenID'   : 'http://getopenid.com/username' ,
    'Google'      : 'http://google.com/accounts/o8/id' ,
    'Hyves'       : 'http://hyves.nl' ,
    'LaunchPad'   : 'http://login.launchpad.net' ,
    'LiquidID'    : 'http://username.liquidid.net' ,
    'LiveJournal' : 'http://username.livejournal.com' ,
    'myID'        : 'http://myid.net' ,
    'myOpenID'    : 'http://myopenid.com' ,
    'MySpace'     : 'http://myspace.com' ,
    'myVidoop'    : 'http://myvidoop.com' ,
    'NetLog'      : 'http://netlog.com/username' ,
    'OneLogin'    : 'https://app.onelogin.com/openid/username' ,
    'OpenID'      : 'http://username' ,
    'Steam'       : 'http://steamcommunity.com/openid' ,
    'Verisign'    : 'http://pip.verisignlabs.com' ,
    'WordPress'   : 'http://username.wordpress.com' ,
    'Yahoo'       : 'http://yahoo.com' 
  };

  var providerurl = providers[provider];

  /* Make sure we passed in a valid provider string. */
  if (providerurl !== undefined) {

    /* Find the openidurl element and replace it with the new url. */
    var urlelement = document.getElementById('openidurl');
    if (urlelement !== null) {
      /* If the url contains "username", replace it with an input text box. */
      var newurl = providerurl.replace('username','<input type="text" name="username" size="9" value="username" id="openidusername" onfocus="setInterval(\'boxExpand()\',1);" />');
      urlelement.innerHTML = newurl;
    }

    /* Set the focus and selection to the new "username" field. */
    var usernamefield = document.getElementById('openidusername');
    if (usernamefield !== null) {
      usernamefield.focus();
      usernamefield.select();
    }

    /* Change the dropdown OpenID icon. */
    var iconelement = document.getElementById('currentopenidicon');
    if (iconelement !== null) {
      iconelement.src = '/images/' + provider.toLowerCase() + '.png';
      iconelement.alt = provider;
    }

    /* Set the hiddenopenid field to the provider string so   */
    /* that it can be saved to a cookie upon form submission. */
    var hiddenelement = document.getElementById('hiddenopenid');
    if (hiddenelement !== null) {
      hiddenelement.value = provider;
    }
  }
}

/***************************************************************************
 * Function  : useOpenID                                                   *
 * Parameter : A string of either '0' or '1' to set as the value of the    *
 *             hidden "useopenid" form input.                              *
 * This function is called when the user clicks either "Log on with        *
 * OpenID instead..." or "Log on with InCommon instead...".  It sets the   *
 * value of the hidden input field "useopenid".  This form value can then  *
 * be saved to a cookie so we know which form of authentication was        *
 * utilized by the user.                                                   *
 ***************************************************************************/
function useOpenID(useit)
{
  var useelement = document.getElementById('useopenid');
  if (useelement !== undefined) {
    useelement.value = useit;
    if (useit == '1') {
      /* If there is a 'username' field in the OpenID, select/focus it */
      openidusername = document.getElementById('openidusername');
      if (openidusername !== undefined) {
        openidusername.focus();
        openidusername.select();
      }
    }
  }
}

