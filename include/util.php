<?php

/************************************************************************
 * Function  : redirect                                                 *
 * Parameters: (1) The new URL or local file to redirect to             *
 *             (2) The HTTP status code when redirecting (defaults to   *
 *                 301 Moved Permanently)                               *
 * This function redirects the current page to a new page, which can    *
 * be either a full URL or another local document.  Note that this      *
 * function must be called BEFORE you output any HTML tags.             *
 ************************************************************************/
function redirect($to,$code=301)
{
    $location = null;
    $sn = $_SERVER['SCRIPT_NAME'];
    $cp = dirname($sn);
    if (substr($to,0,4)=='http') { // Absolute URL
        $location = $to;
    } else {
        $schema = $_SERVER['SERVER_PORT']=='443'?'https':'http';
        $host = strlen($_SERVER['HTTP_HOST']) ? 
                $_SERVER['HTTP_HOST'] :
                $_SERVER['SERVER_NAME'];
        if (substr($to,0,1)=='/') { // URL on current host
            $location = "$schema://$host$to";
        } elseif (substr($to,0,1)=='.') { // Relative Path
            $location = "$schema://$host/";
            $pu = parse_url($to);
            $cd = dirname($_SERVER['SCRIPT_FILENAME']).'/';
            $np = realpath($cd.$pu['path']);
            $np = str_replace($_SERVER['DOCUMENT_ROOT'],'',$np);
            $location.= $np;
            if ((isset($pu['query'])) && (strlen($pu['query'])>0)) {
                $location.= '?'.$pu['query'];
            }
        }
    }

    $hs = headers_sent();
    if ($hs==false) {
        if ($code==301) {
            header("301 Moved Permanently HTTP/1.1"); // Convert to GET
        } elseif ($code==302) {
            header("302 Found HTTP/1.1"); // Conform re-POST
        } elseif ($code==303) {
            header("303 See Other HTTP/1.1"); // dont cache, always use GET
        } elseif ($code==304) {
            header("304 Not Modified HTTP/1.1"); // use cache
        } elseif ($code==305) {
            header("305 Use Proxy HTTP/1.1");
        } elseif ($code==306) {
            header("306 Not Used HTTP/1.1");
        } elseif ($code==307) {
            header("307 Temorary Redirect HTTP/1.1");
        } else {
            trigger_error("Unhandled redirect() HTTP Code: $code",E_USER_ERROR);
        }
        header("Location: $location");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    } elseif (($hs==true) || ($code==302) || ($code==303)) {
        // todo: draw some javascript to redirect
        $cover_div_style = 'background-color: #ccc; height: 100%; left: 0px; '.
                           'position: absolute; top: 0px; width: 100%;'; 
        echo "<div style='$cover_div_style'>\n";
        $link_div_style = 'background-color: #fff; border: 2px solid #f00; '.
                          'left: 0px; margin: 5px; padding: 3px; ';
        $link_div_style.= 'position: absolute; text-align: center; top: 0px; '.
                          'width: 95%; z-index: 99;';
        echo "<div style='$link_div_style'>\n";
        echo "<p>Please See: <a href='$to'>".htmlspecialchars($location).
             "</a></p>\n";
        echo "</div>\n</div>\n";
    }

    exit(0);
}

/************************************************************************
 * Function  : getServerVar                                             *
 * Parameter : The $_SERVER variable to query.                          *
 * Returns   : The value of the $_SERVER variable or empty string if    *
 *             that variable is not set.                                *
 * This function queries a given $_SERVER variable (which is set by     *
 * the Apache server) and returns the value.                            *
 ************************************************************************/
function getServerVar($serv) {
    $retval = '';
    if (isset($_SERVER[$serv])) {
        $retval = $_SERVER[$serv];
    }
    return $retval;
}

/************************************************************************
 * Function  : getPostVar                                               *
 * Parameter : The $_POST variable to query.                            *
 * Returns   : The value of the $_POST variable or empty string if      *
 *             that variable is not set.                                *
 * This function queries a given $_POST variable (which is set when     *
 * the user submits a form, for example) and returns the value.         *
 ************************************************************************/
function getPostVar($post) 
{ 
    $retval = '';
    if (isset($_POST[$post])) {
        $retval = $_POST[$post];
    }
    return $retval;
}

/************************************************************************
 * Function  : getCookieVar                                             *
 * Parameter : The $_COOKIE variable to query.                          *
 * Returns   : The value of the $_COOKIE variable or empty string if    *
 *             that variable is not set.                                *
 * This function returns the value of a given cookie.                   *
 ************************************************************************/
function getCookieVar($cookie) 
{ 
    $retval = '';
    if (isset($_COOKIE[$cookie])) {
        $retval = $_COOKIE[$cookie];
    }
    return $retval;
}

/************************************************************************
 * Function  : getSessionVar                                            *
 * Parameter : The $_SESSION variable to query.                         *
 * Returns   : The value of the $_SESSION variable or empty string if   *
 *             that variable is not set.                                *
 * This function returns the value of a given PHP Session variable.     *
 ************************************************************************/
function getSessionVar($sess) 
{ 
    $retval = '';
    if (isset($_SESSION[$sess])) {
        $retval = $_SESSION[$sess];
    }
    return $retval;
}

/************************************************************************
 * Function  : startPHPSession                                          *
 * This function starts a secure PHP session and should be called at    *
 * at the beginning of each script before any HTML is output.  It also  *
 * does a trick of setting a 'lastaccess' time so that the $_SESSION    *
 * variable does not expire without warning.                            *
 ************************************************************************/
function startPHPSession()
{
    ini_set('session.cookie_secure',true);
    if (session_id() == "") session_start();
    if ((!isset($_SESSION['lastaccess']) || 
        (time() - $_SESSION['lastaccess']) > 60 )) {
        $_SESSION['lastaccess'] = time();
    }
}

?>
