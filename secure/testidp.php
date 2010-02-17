<?php

require_once('../include/autoloader.php');
require_once('../include/shib.php');

/* Read in all of the necessary Shibboleth server variables */
global $shibarray;
$gotShibAttrs = getShibInfo();

/* Figure out what "stage" we are running, based on the button clicked */
$stage = '';
if (isset($_POST['stage'])) {
    $stage = $_POST['stage'];
    if (!csrf::isCookieEqualToForm()) {
        $stage = '';
    }
} elseif (isset($_SESSION['stage'])) {
    $stage = $_SESSION['stage'];
}

/* Set the necessary CSRF cookie for next time a form button is clicked */
$csrf = new csrf();
$csrf->setTheCookie();

echo '
<html>
<head>
  <title>Shibboleth Attributes';
if (isset($_SERVER['SERVER_NAME'])) {
    echo ' - ' .$_SERVER['SERVER_NAME'];
}
echo '</title>
  <META HTTP-EQUIV="Pragma" CONTENT="no-cache">
  <META HTTP-EQUIV="Expires" CONTENT="-1">
</head>

<body>

<h2>Attributes</h2>
<table>
';

foreach ($shibarray as $key => $value)
{
	echo '<tr>
  <td>';
	echo $key;
  echo '</td>
  <td>';
  if (strlen($value) > 0) {
      echo $value;
  } else {
      echo '<font color=#FF0000"><b>Missing</b></font>';
  }
  echo '</td>
</tr>
';
}

echo '</table>
<p/>
';

if (isset($shibarray['Home Page'])) {
    echo '<center><a target="_blank" href="';
    echo $shibarray['Home Page'];
    echo '"><img alt="Go to ';
    echo $shibarray['Home Page'];
    echo '" src="http://www.thumbshots.de/cgi-bin/show.cgi?url=';
    echo $shibarray['Home Page'];
    echo '" border="0" onload="if (this.width>50) this.border=1; this.alt=\'';
    echo $shibarray['Home Page'];
    echo '\';"></a>';
    echo '<a target="_blank" href="http://www.thumbshots.de/"></a></center>';
}

echo '
<p/>
<h2>Result</h2>
';
if (!$gotShibAttrs) {
    echo '
    Unfortunately, some of the required attributes have not been
    released by your school, so your school cannot access our service.
    Please contact your school Identity Provider for more information.
    ';
} else {
    $white = new whitelist();
    $white->read();

    echo '
    Congratulations! All required attributes have been released from your
    school. 
    <p/>
';

    if ($white->exists($shibarray['Identity Provider'])) {
        echo '
        Your school is in our list of Identity Providers,
        so you can <a href="https://cilogon.org/secure/"> continue on to the
        main page</a>.
        ';
    } else {
        echo '
        You can now try to add your Identity Provider to our list so that
        you can use our service. [Button to go here]
        ';
    }
}

echo '
</body>
</html>
';
?>
