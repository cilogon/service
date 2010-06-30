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

