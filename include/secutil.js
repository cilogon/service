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
function showHideDiv(whichDiv,showhide)
{
  var divs = document.getElementsByTagName('div');
  var i;
  for (i = 0; i < divs.length; i++)
    {
      if (divs[i].id.match(whichDiv))
        {
          var style2;
          if (document.getElementById)
            { // Current browsers, i.e. IE5, NS6
              style2 = divs[i].style;
            }
          else if (document.layers)
            { // NS4
              style2 = document.layers[divs[i]].style;
            }
          else
            { // IE4
              style2 = document.all[divs[i]].style;
            }

          if (showhide == 1)
            { // show div
              style2.display = "inline";
            }
          else if (showhide === 0)
            { // hide div
              style2.display = "none";
            }
          else 
            { // toggle div
              if (style2.display == "inline") {
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
 * the user for hours.  So this fUnction transforms the visible            *
 * certlifetime field (in hours) to the hidden RequestedLifetime field     *
 * (in seconds).  It also sets a cooke for the certlifetime field so that  *
 * it can be populated with the new value on the user's next visit.        *
 ***************************************************************************/
function handleLifetime()
{
  /* Get the certlifetime field entered by the user */
  var certlifetimefield = document.getElementById('certlifetime');
  if (certlifetimefield !== null) {
    var certlifetimefieldvalue = parseInt(certlifetimefield.value);
    if (isNaN(certlifetimefieldvalue)) {
      certlifetimefieldvalue = 12;
    }

    /* Try to get the hidden maxlifetime field value, or default to 10 days */
    var maxlifetimefield = document.getElementById('maxlifetime');
    var maxlifetimefieldvalue = 240; 
    if (maxlifetimefield !== null) {
      maxlifetimefieldvalue = parseInt(maxlifetimefield.value);
    }

    /* Make sure the certlifetime is within bounds */
    if (certlifetimefieldvalue < 1) {
      certlifetimefieldvalue = 1;
    }
    if (certlifetimefieldvalue > maxlifetimefieldvalue) {
      certlifetimefieldvalue = maxlifetimefieldvalue;
    }

    /* Set the hidden RequestedLifetime field, in seconds */
    var requestedlifetimefield = document.getElementById('RequestedLifetime'); 
    if (requestedlifetimefield !== null) {
      requestedlifetimefield.value = certlifetimefieldvalue * 3600;
    }

    /* Set the cookie for the certlifetime field for next visit */
    var today  = new Date();
    var expire = new Date();
    expire.setTime(today.getTime() + 365*24*3600000);
    var cookiestr = "certlifetime=" + escape(certlifetimefieldvalue) +
      ";expires=" + expire.toGMTString() + ";path=/;secure";
    document.cookie = cookiestr;
  }

  return true;
}
