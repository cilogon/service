<?php

require_once("autoloader.php");

/* The csrf token object to set the CSRF cookie and print the hidden */
/* CSRF form element.  Be sure to do "global $csrf" to use it.       */
$csrf = new csrf();

/************************************************************************
 * Function   : printHeader                                             *
 * Parameter  : (1) The text in the window's titlebar                   *
 *              (2) Optional extra text to go in the <head> block       *
 * This function should be called to print out the main HTML header     *
 * block for each web page.  This gives a consistent look to the site.  *
 * Any style changes should go in the cilogon.css file.                 *
 ************************************************************************/
function printHeader($title='',$extra='')
{
    global $csrf;
    $csrf->setTheCookie();

    echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head><title>' . $title . '</title> 
    <meta http-equiv="content-type" 
          content="text/html; charset=iso-8859-1" />
    <script type="text/javascript" src="/include/secutil.js"></script>
    <style type="text/css" media="all">
        @import "/include/cilogon.css";
    </style>
    ';

    if (strlen($extra) > 0) {
        echo $extra;
    }

    echo '
    </head>

    <body id="cilogon-org">
    <div id="containter">
    <div id="logoHeader">
       <h1><span>[Icon]</span></h1>
       <h2><span>CILogon Service</span><span class="raised">CILogon Service</span></h2>
    </div>
     ';
}

/************************************************************************
 * Function   : printFooter                                             *
 * Parameter  : (1) Optional extra text to be output before the closing *
 *                  footer div.                                         *
 * This function should be called to print out the closing HTML block   *
 * for each web page.                                                   *
 ************************************************************************/
function printFooter($footer='') 
{
    if (strlen($footer) > 0) {
        echo $footer;
    }

    echo '
    <br clear="all">
    <div class="footer">
    </div> <!-- Close "footer" div    -->
    </div> <!-- Close "container" div -->
    </body>
    </html>
    ';
}

?>
