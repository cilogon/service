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
 * Function  : showUrlDiv                                                  *
 * Parameters: None                                                        *
 * To use this function, put a call to it in "onLoad" like this:           *
 *    <body onLoad="showUrlDiv();" bgcolor="#FFFFFF">                      *
 * This allows you to show an initially hidden <div> tag (or set of <div>  *
 * tags).  You do this by appending the names of <div>s to the URL.        *
 * After the page URL, you can use either "?" (as is typical of form       *
 * submission) or "#" (as is used by <a name="..."> anchors).  By using    *
 * "?" you simply show the named <div>.  By using "#" you can set it up    *
 * to both show the named <div> and jump to the <a name> anchor of the     *
 * same name.  Since this function calls showHideDiv(), you can show a     *
 * bunch of <div>s with a common substring.  In either case, you can show  *
 * multiple <div>s of DIFFERING names by separating them with a comma.     *
 * As an example, let's say you have the following <div>s:                 *
 *    <div id="step01" style="display:none"> ... </div>                    *
 *    <div id="pic03"  style="display:none"><img src="..."></div>          *
 * You can initially show the image by going to "index.html?pic03" or      *
 * show the image and all 'steps' by going to "index.html?step,pic03".     *
 * As an example of using "#", let's say you have the following setup:     *
 *    <a href="javascript:showHideDiv('sect03',-1)                         *
 *       name="sect03" >Info on Section 3...</a>                           *
 *    <div id="sect03" style="display:none">Info goes here...</div>        *
 * You can show this section AND jump to the link by going to              *
 * "index.html#sect03".                                                    *
 ***************************************************************************/
function showUrlDiv()
{
  /* Try to find a "?" or "#" in the URL and split on that        *
   * character.  We assume that the stuff AFTER that character    *
   * will be <div>s we want to show.                              */
  var urlquery = location.href.split("?");  // Check for "?" in URL
  if (!urlquery[1])
    { // No "?", so check for anchor name "#" in URL
      urlquery = location.href.split("#");
    }

  /* If we found either "?" or "#" in URL, then get the names of  *
   * all <div>s (separated by commas ",") and show them.          */
  if (urlquery[1])
    {
      var divs = (urlquery[1]).split(",");
      var i;
      for (i = 0; i < divs.length; i++)
        {
          showHideDiv(divs[i],1);
        }
      /* If the URL contained a hash, jump to that anchor name.   */
      if (location.hash)
        { 
          window.location.hash = location.hash;
        }
    }
}

