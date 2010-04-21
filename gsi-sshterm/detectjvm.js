/***************************************************************************
 * Function  : setInnerDiv                                                 *
 * Parameters: (1) the id string of the target <div> to replace innerHTML  *
 *             (2) the new innerHTML text of the target <div>              *
 * This function can replace the text of a given <div> section by setting  *
 * it's innerHTML value.  Simply give the div an "id" such as              *
 *     <div id="mydiv">This text will be replaced.</div>                   *
 * Then call this function with the div's id (mydiv) and the new html      *
 * text to be rendered within the div.                                     *
 ***************************************************************************/
function setInnerDiv(whichDiv,innerText) {
    var divs = document.getElementsByTagName('div');
    var i;
    for (i = 0; i < divs.length; i++) {
        if (divs[i].id.match(whichDiv)) {
            if (document.getElementById) { // Current browsers, i.e. IE5, NS6
                divs[i].innerHTML = innerText;
            } else if (document.layers) { // NS4
                document.layers[divs[i]].innerHTML = innerText;
            } else { // IE4
                document.all[divs[i]].innerHTML = innerText;
            }
        }
    }
}

/***************************************************************************
 * Function  : getInnerDiv                                                 *
 * Parameter : the id string of the target <div> to fetch the innerHTML    *
 * Returns   : a string of the requested <div>'s innerHTML                 *
 * This fuction returns the current innerHTML of a given <div> section.    *
 * Note that it returns on the FIRST <div> which matches whichDiv.         *
 ***************************************************************************/
function getInnerDiv(whichDiv) {
    var divs = document.getElementsByTagName('div');
    var retstr = "";
    var done = false;
    var i = 0;
    while ((i < divs.length) && (!done)) {
        if (divs[i].id.match(whichDiv)) {
            done = true;
            if (document.getElementById) { // Current browsers, i.e. IE5, NS6
                retstr = divs[i].innerHTML;
            } else if (document.layers) { // NS4
                retstr = document.layers[divs[i]].innerHTML;
            } else { // IE4
                retstr = document.all[divs[i]].innerHTML;
            }
        }
      i++;
    }
    return retstr;
}

/***************************************************************************
 * Function  : init                                                        *
 * Parameters: none                                                        *
 * This method is called via the <body onload=""> method.  Once the page   *
 * is loaded, it calls the getVersion() method imbedded within the         *
 * JavaVersionDisplayApplet to find the version of the currently running   *
 * JVM.  Based on this version, it sets the maindiv <div>'s innerHTML      *
 * appropriately.                                                          *
 ***************************************************************************/
function init() {
    var jvmver;
    var jvmven;
    var firstpart;
    var newinner;
    try {
        jvmver = document.jvmversion.getVersion();
        jvmven = document.jvmversion.getVendor();
        firstpart = 'You are currently running Java ' +
                    jvmver + ' from ' + jvmven + '. However, the ';
    } catch (err) {
        jvmver = "";
        firstpart = 'You are not running Java. The ';
    }
    if ((String(jvmver) < "1.5") || (jvmver.length == 0)) {
        var oldinner = getInnerDiv("maindiv");
        oldinner = oldinner.replace(/\"/g,'\\\"');  // Escape the quotes
        newinner = '<p><b>' + firstpart + 
            'GSI-SSHTerm applet requires at least ' +
            '<a target="_blank" href="http://java.com/">Java 1.5</a>.</b></p>' +
            '<p><a href=\'javascript:setInnerDiv("maindiv","' + oldinner +
            '");\'>Click here to force load the applet</a> ' +
            'if you believe you received this message in error.</p>';
        setInnerDiv("maindiv",newinner);
    }
}

