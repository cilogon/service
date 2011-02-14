/* Global variables used by cacheOptions, addOption, and searchOptions */
var idpstext, idpsvalue, idps;

/***************************************************************************
 * Function  : partial                                                     *
 * Parameters: Any parameters to be passed to the partial function.        *
 * Returns   : A "partial" function which can be passed as an argument     *
 *             to another JS function.                                     *
 * This function was taken from :                                          *
 * http://stackoverflow.com/questions/373157/how-can-i-pass-a-reference-to-a-function-with-parameters
 * It creates a "partial" function, which is basically a function with a   *
 * bunch of parameters preset.  This new partial function can be passed    *
 * to other JavaScript functions which expect to receive a function name   *
 * (withOUT parameters) as a parameter (e.g. countdown(f,ms) and           *
 * addLoadEvent(f).                                                        *
 ***************************************************************************/
function partial(func /*, 0..n args */) {
  var i;
  var args = new Array(); 
  for (i = 1; i < arguments.length; i++) { 
    args.push(arguments[i]); 
  } 
  return function() {
    var allArguments = args.concat(Array.prototype.slice.call(arguments));
    return func.apply(this,allArguments);
  };
}

/***************************************************************************
 * Function  : addLoadEvent                                                *
 * Parameters: The function to be called upon window loading               * 
 * Rather than use the <body onload="myfunc"> tag to invoke some piece of  *
 * JavaScript upon page loading, call addLoadEvent(myfunc).  This function *
 * allows for multiple functions to be called when the page is loaded. If  *
 * there is already an onload function defined, it appends the new         *
 * function to the onload event handler.                                   *
 ***************************************************************************/
function addLoadEvent(func) {
  var oldonload = null;
  if ('onload' in window) {
    oldonload = window.onload;
  }
  if (typeof oldonload !== 'function') {
    window.onload = func;
  } else {
    window.onload = function() {
      if (oldonload) {
        oldonload();
      }
      func();
    };
  }
}

/***************************************************************************
 * Function  : showHideDiv                                                 *
 * Parameters: (1) the id string of the <div> to show or hide.             *
 *             (2) 1=>show, 0=>hide, -1=>toggle state                      *
 * This function can show, hide, or toggle a <div> section.  When 'shown'  *
 * the style 'display' is set to 'inline' so you will have to use <br> or  *
 * <p> to insert line breaks.  A typical <div> section will look like:     *
 *     <div id="step01" style="display:none"> ... </div>                   *
 * This <div> is named "step01" and is initially hidden.  You can make it  * 
 * initially displayed by doing "display:inline".  To show/hide this <div> *
 * you would have a link like:                                             *
 *     <a href="javascript:showHideDiv('step01',-1)">Show/hide Step 1</a>  *
 * Since this function uses the 'match' string comparison function, you    *
 * can do a sort of wildcard matching for showing/hiding a group of        *
 * <div>s.  For example, if you did "showHideDiv('step',-1)", every <div>  *
 * that had "step" in the "id" string would toggle its display state.      *
 ***************************************************************************/
function showHideDiv(whichDiv,showhide) {
  var divs = document.getElementsByTagName('div');
  var i;
  var style2;
  for (i = 0; i < divs.length; i++) {
    if (divs[i].id.match(whichDiv)) {
      if (document.getElementById) { // Current browsers, i.e. IE5, NS6
        style2 = divs[i].style;
      } else if (document.layers) { // NS4
        style2 = document.layers[divs[i]].style;
      } else { // IE4
        style2 = document.all[divs[i]].style;
      }

      if (showhide === 1) { // show div
        style2.display = "inline";
      } else if (showhide === 0) { // hide div
        style2.display = "none";
      } else { // toggle div
        if (style2.display === "inline") {
          style2.display = "none";
        } else {
          style2.display = "inline";
        }
      }
    }
  }
}

/***************************************************************************
 * Function  : handleLifetime                                              *
 * This function is specific to the "Download Certificate" button area on  *
 * the "Get And Use Your CILogon Certificate" page.  It handles the issue  *
 * with the "Lifetime" of the certificate.  The GridShib-CA code expects   *
 * a RequestedLifetime field in seconds, but the cilogon.org site prompts  *
 * the user for hours/days/months. So this fUnction transforms the visible *
 * certlifetime field to the hidden RequestedLifetime field (in seconds).  *
 * It also sets a cookie for the certlifetime field and the hour/day/      *
 * month selector so that they can be populated correctly upon the user's  *
 * next visit.                                                             *
 ***************************************************************************/
function handleLifetime()
{
  /* Get the various lifetime interface objects */
  var certlifetimefield      = document.getElementById('certlifetime');
  var maxlifetimefield       = document.getElementById('maxlifetime');
  var requestedlifetimefield = document.getElementById('RequestedLifetime'); 
  var certmultiplierselect   = document.getElementById('certmultiplier');

  var certlifetimefieldvalue = 12;      /* Default lifetime is 12 hours */
  var certmultiplierselectvalue = 3600; /* Default unit is hours */
  var maxlifetimefieldvalue = 34257600; /* Default max lifetime is 13 months */
  var needtoreset = false;

  /* Get the number in the lifetime field */
  if (certlifetimefield !== null) {
    certlifetimefieldvalue = parseFloat(certlifetimefield.value);
    if (isNaN(certlifetimefieldvalue)) {
      certlifetimefieldvalue = 12;
    }
  }

  /* Get the multiplier (hours/days/months) as seconds */
  if (certmultiplierselect !== null) {
    var certmultiplierselectindex = certmultiplierselect.selectedIndex;
    if (certmultiplierselectindex >= 0) {
      certmultiplierselectvalue = 
        certmultiplierselect.options[certmultiplierselectindex].value;
    }
  }

  /* Get the hidden maxlifetime field value */
  if (maxlifetimefield !== null) {
    maxlifetimefieldvalue = parseInt(maxlifetimefield.value,10);
  }

  /* Calculate requested cert lifetime in seconds */
  var requestedcertlifetime = 
    Math.round(certlifetimefieldvalue * certmultiplierselectvalue);

  /* Make sure the certlifetime is within bounds, reset text input if needed */
  if (requestedcertlifetime < 0) {
    requestedcertlifetime = 0;
    needtoreset = true;
  }
  if (requestedcertlifetime > maxlifetimefieldvalue) {
    requestedcertlifetime = maxlifetimefieldvalue;
    needtoreset = true;
  }
  if (needtoreset) {
    certlifetimefieldvalue = 
      Math.round(100 * requestedcertlifetime / certmultiplierselectvalue) / 100;
    certlifetimefield.value = certlifetimefieldvalue;
  }

  /* Set the hidden RequestedLifetime field, in seconds */
  if (requestedlifetimefield !== null) {
    requestedlifetimefield.value = requestedcertlifetime;
  }

  /* Set the cookie for the certlifetime field and hour/day/month selector */
  var today  = new Date();
  var expire = new Date();
  expire.setTime(today.getTime() + 365*24*3600000);
  var cookiestr = "certlifetime=" + escape(certlifetimefieldvalue) +
    ";expires=" + expire.toGMTString() + ";path=/;secure";
  document.cookie = cookiestr;
  cookiestr = "certmultiplier=" + escape(certmultiplierselectvalue) +
    ";expires=" + expire.toGMTString() + ";path=/;secure";
  document.cookie = cookiestr;

  return true;
}

/***************************************************************************
 * Function  : countdown                                                   *
 * Parameters: (1) Prefix to prepend to "expire" and "value" ids.          *
 *             (2) Label to prepend to "Expires:" time.                    *
 * This function counts down a timer for a paragraph with an attribute     *
 * id=which+"expire".  In this case "which" can be "p12" or "token".       *
 * If there is still time left in the expire element, the value is fetched *
 * and decremented by one second, then updated.  Once time has run out,    *
 * the which+"value" and which+"expire" paragraph elements are set to      *
 * empty strings, which hides them.                                        *
 ***************************************************************************/
function countdown(which,expirelabel) {
  var expire = document.getElementById(which+"expire");
  if (expire !== null) {
    var expiretext = expire.innerHTML;
    if ((expiretext !== null) && (expiretext.length > 0)) {
      var matches = expiretext.match(/\d+/g);
      if (matches.length === 2) {
        var minutes = parseInt(matches[0],10);
        var seconds = parseInt(matches[1],10);
        if ((minutes > 0) || (seconds > 0)) {
          seconds -= 1;
          if (seconds < 0) {
            minutes -= 1;
            if (minutes >= 0) {
              seconds = 59;
            }
          }
          if ((seconds > 0) || (minutes > 0)) {
            expire.innerHTML = expirelabel + " Expires: " + 
              ((minutes < 10) ? "0" : "") + minutes + "m:" +
              ((seconds < 10) ? "0" : "") + seconds + "s";
            var pc = partial(countdown,which,expirelabel);
            setTimeout(pc,1000);
          } else {
            expire.innerHTML = "";
            var thevalue = document.getElementById(which+"value");
            if (thevalue !== null) {
              thevalue.innerHTML = "";
            }
          }
        }
      }
    }
  }
}

/***************************************************************************
 * Function  : cacheOptions                                                *
 * This function is called when the WAYF loads. It reads in the list of    *
 * IdPs and saves them in the global idpstext and idpsvalue arrays.  These *
 * are used later by the searchOptions function.  Also, the "Search" box   *
 * on the WAYF page is hidden by default.  If JavaScript is enabled, this  *
 * function "unhides" it.                                                  *
 ***************************************************************************/
function cacheOptions() {
  var i;
  var lf;
  idpstext = [];
  idpsvalue = [];
  idps = document.getElementById("providerId");
  /* Populate the idpstext and idpsvalue arrays from the WAYF's <select> */
  if (idps !== null) {
    for (i = 0; i < idps.options.length; i++) {
      idpstext[idpstext.length] = idps.options[i].text;
      idpsvalue[idpsvalue.length] = idps.options[i].value;
    }
    /* Unhide the "Search" box which is hidden by default */
    lf = document.getElementById("listsearch");
    if (lf !== null) {
      lf.style.display = "block";
      lf.style.height = "2em";
      lf.style.width = "auto";
      lf.style.lineHeight = "normal";
      lf.style.overflow = "visible";
    }
  }
}

/***************************************************************************
 * Function  : addOption                                                   *
 * Parameters: (1) Text of the <option> to be added.                       *
 *             (2) Value of the <option> to be added.                      *
 * This function is called by searchOptions to dynamically create a new    *
 * <select> list based on the currently entered Search text.               *
 ***************************************************************************/
function addOption(text,value) {
  var opt;
  if (idps !== null) {
    opt = document.createElement("option");
    opt.text = text;
    opt.value = value;
    idps.options.add(opt);
  }
}

/***************************************************************************
 * Function  : searchOptions                                               *
 * Parameter : A string entered into the "Search" field on the WAYF page.  *
 * This function is called when the user enters text into the "Search"     *
 * text field on the logon page.  It compares the entered text against     *
 * each of the <option> items in the WAYF list.  If a substring match is   *
 * found, addOption is called to add that IdP to a new sublist of IdPs.    *
 ***************************************************************************/
function searchOptions(value) {
  var i;
  var idpsselected;
  var seltext;
  var selindex;
  if (idps !== null) {
    /* Figure out which (if any) IdP was previously highlighted */
    idpsselected = idps.selectedIndex;
    seltext = "";
    if (idpsselected >= 0) {
      seltext = idps.options[idpsselected].text;
    }

    /* Scan thru the <options> for substrings matching the "Search" field */
    idps.options.length = 0;
    for (i = 0; i < idpstext.length; i++) {
      if (idpstext[i].toLowerCase().indexOf(value) !== -1) {
        addOption(idpstext[i],idpsvalue[i]);
      }
    }
    if (idps.options.length === 0) {  /* No items in new sublist */
      addOption("No matches","");
    } else {
      /* Find the previously highlighted option, if in new sublist, */
      /* or default to the first item in the new sublist.           */
      selindex = 0;
      for (i = 0; i < idps.options.length; i++) {
        if (seltext === idps.options[i].text) {
          selindex = i;
          break;
        }
      }
      idps.selectedIndex = selindex;
      idps.options[selindex].selected = true;
    }
  }
}

/***************************************************************************
 * Function  : enterKeySubmit                                              *
 * Parameters: The event that occurred when this function was called.      *
 * This function is called on the keyup event in the WAYF's <select>       *
 * element.  It's purpose is to allow the user to press the <enter> key    *
 * to submit the form when the <select> element has focus.                 *
 ***************************************************************************/
function enterKeySubmit(event) {
  var code = event.keyCode;
  var logonbutton;
  if (code === 13) {
    logonbutton = document.getElementById("wayflogonbutton");
    if (logonbutton !== null) {
      logonbutton.click(); 
    }
  }
}

/***************************************************************************
 * Function  : textInputFocus                                              *
 * This function looks for the "Search" text field on the WAYF page or the *
 * "Enter Password" text field on the Main Page.  If either is found, it   *
 * gives the found text field focus.                                       *
 ***************************************************************************/
function textInputFocus() {
  var search = document.getElementById("searchlist");
  var passwd = document.getElementById("password1");
  if (search !== null) {
    search.focus();
  } else if (passwd !== null) {
    passwd.focus();
  }
}

/***************************************************************************
 * Function  : setPasswordIcon                                             *
 * Parameters: (1) The <img> element for the "check password" icon.        *
 *             (2) The new icon to show; one of "blank", "error", "okay".  *
 *             (3) The "title" hover text to display on the icon.          *
 *             (4) The hover cursor for the icon; one of "auto" or "help". *
 * This is a convenience function called by checkPassword().               *
 ***************************************************************************/
function setPasswordIcon(pwicon,iconname,title,cursor) {
  if (pwicon !== null) {
    pwicon.src = "/images/" + iconname + "Icon.png";
    pwicon.title = title;
    pwicon.style.cursor = cursor;
  }
}

/***************************************************************************
 * Function  : checkPassword                                               *
 * This function is called onkeyup when the user types in one of the two   *
 * passwords on the main page.  It verifies that the first password is     *
 * at least 12 characters long, and that the second password matches.  It  *
 * changes the password icons next to the input text fields as appropriate.*
 ***************************************************************************/
function checkPassword() {
  var pw1input = document.getElementById("password1");
  var pw2input = document.getElementById("password2");
  var pw1icon = document.getElementById("pw1icon");
  var pw2icon = document.getElementById("pw2icon");
  var pw1text;
  var pw2text;
  if ((pw1input !== null) && (pw2input !== null) &&
      (pw1icon !== null) && (pw2icon !== null)) {
    pw1text = pw1input.value;
    pw2text = pw2input.value;
    if ((pw1text.length === 0) && (pw2text.length === 0)) {
      setPasswordIcon(pw1icon,"blank","","auto");
      setPasswordIcon(pw2icon,"blank","","auto");
    } else if (pw1text.length < 12) {
      setPasswordIcon(pw1icon,"error",
        "Password must be at least 12 characters in length.","help");
      setPasswordIcon(pw2icon,"blank","","auto");
    } else if (pw1text !== pw2text) {
      setPasswordIcon(pw1icon,"okay","","auto");
      setPasswordIcon(pw2icon,"error","The two passwords must match.","help");
    } else {
      setPasswordIcon(pw1icon,"okay","","auto");
      setPasswordIcon(pw2icon,"okay","","auto");
    }
  }
}

/***************************************************************************
 * Function  : showHourglass                                               *
 * Parameter : Which hourglass icon to show (e.g. 'p12')                   *
 * This function is called when either the "Get New Certificate" button    *
 * or "Get New Activation code" button is clicked.  It unhides the small   *
 * hourglass icon next to the button.  The "which" parameter corresponds   *
 * to the prefix of the id=which+"hourglass" attribute of the <img>.       *
 ***************************************************************************/
function showHourglass(which) {
  var thehourglass = document.getElementById(which+'hourglass');
  if (thehourglass !== null) {
    thehourglass.style.display = 'inline';
  }
}

/***************************************************************************
 * Function  : enableCertlifetime                                          *
 * This function is called upon page load to enable the "certlifetime"     *
 * and "certmultiplier" elements.  These are disabled by default since     *
 * JavaScript must be enabled to calculate the RequestedLifetime hidden    *
 * input field.  This function also attempts to detect the version of      *
 * Java installed and displays a message if less than v.1.6 is detected.   *
 ***************************************************************************/
function enableCertlifetime() {
  var certlifetimeinput    = document.getElementById("certlifetime");
  var certmultiplierselect = document.getElementById("certmultiplier");
  var mayneedjavapara      = document.getElementById("mayneedjava");
  if (certlifetimeinput !== null) {
    certlifetimeinput.disabled = false;
  }
  if (certmultiplierselect !== null) {
    certmultiplierselect.disabled = false;
  }
  if (mayneedjavapara !== null) {
    if (!deployJava.isWebStartInstalled("1.6.0")) {
      mayneedjavapara.style.display = "block";
      mayneedjavapara.style.height = "1.5em";
      mayneedjavapara.style.width = "auto";
      mayneedjavapara.style.lineHeight = "auto";
      mayneedjavapara.style.overflow = "visible";
    }
  }
  return true;
}

addLoadEvent(cacheOptions);
var fp12 = partial(countdown,'p12','Link');
var ftok = partial(countdown,'token','Code');
addLoadEvent(fp12);
addLoadEvent(ftok);
addLoadEvent(textInputFocus);
addLoadEvent(enableCertlifetime);

