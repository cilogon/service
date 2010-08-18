<?php

require_once('include/util.php');
require_once('include/autoloader.php');
require_once('include/content.php');

printHeader('CILogon Service Example Buttons');
printPageHeader('Examples of CILogon Service Buttons and Links');

?>


<div class="boxed">

<h1>Summary</h1>

<p>
This page provides several examples of form buttons and links that website
administrators can include in their sites to link to the CILogon Service.
CILogon provides two distinct web endpoints. In the examples that follow,
be sure to use the appropriate URL (and any needed parameters) to suit your
needs.
</p>

<ol>
  <li><strong><a target="_blank"
  href="https://cilogon.org/">https://cilogon.org/</a></strong> -  The main
  CILogon Service site is for use by end-users to obtain a certificate for
  secure access to <a target="_blank" href="http://www.nsf.gov/">NSF</a>
  <a target="_blank" href="http://www.nsf.gov/oci">CyberInfrastructure</a>.
  </li>
  <li><strong><a target="_blank"
  href="https://cilogon.org/delegate/">https://cilogon.org/delegate/</a></strong>
  - The CILogon Delegation Service is for use by Community Portals to obtain
    certificates on behalf of their users. In order to correctly use the
    CILogon Delegation Service, a Community Portal must pass a parameter
    "<tt>oauth_token=...</tt>" as part of the URL.
  </li>
</ol>

Where appropriate, the HTML has been stylized using "inline" CSS.  You can
move the contents of the "<tt>style=...</tt>" parameter to an included
external CSS file if you are more comfortable with that method of coding.

<hr>

<h1>Form "Submit" Buttons</h1>

<p>
If you use a <tt>&lt;form&gt;</tt> to route users from your site to the
CILogon Service, you can use some of the following as examples.  Note that
you will need to set the "<tt>action=...</tt>" and "<tt>method=...</tt>"
parameters appropriately for your site.  If your site's pages are generated
server-side (say via PHP or ASP), you can set the target for the
"<tt>action</tt>" parameter to be one of your site's pages, and then
redirect to the appropriate CILogon Service URL.  Otherwise, you can set the
target for the "<tt>action</tt>" parameter to be the CILogon Service URL
directly.  Note that for the CILogon Delegation Service, the URL
<em>must</em> contain the "<tt>oauth_token=...</tt>" parameter, so your
website must somehow generate the correct HTML.
</p>

<p>
For the purposes of these examples, we will use the end-user CILogon Service
URL as the "<tt>action</tt>" parameter. 
</p>

<h3>Text-Only Button</h3>

<table width="100%" cellspacing="0" cellpadding="10" 
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
    <form action="#" method="post">
    <input type="submit" name="cisubmit1" id="cisubmit1" 
      value="CILogon Service" 
      title="Click to get a certificate via the CILogon Service."
      style="font-family:sans-serif; font-style:italic; font-weight:bold; 
      font-size:110%; color:#030; background-color:#aba; cursor:help;" 
      onclick="return false;"
      />
    </form>
    </td>
    <td>
    <form action="#" method="post">
    <textarea cols="68" rows="6">
<form action="#" method="post">
<input type="submit" name="cisubmit1" id="cisubmit1" 
value="CILogon Service" 
title="Click to get a certificate via the CILogon Service."
style="font-family:sans-serif; font-style:italic; font-weight:bold; 
font-size:110%; color:#030; background-color:#aba; cursor:help;" />
</form></textarea>
    </form>
    </td>
  </tr>
</table>

<h3>Image Button</h3>

<table width="100%" cellspacing="0" cellpadding="10" 
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
    <form action="#" method="post">
    <input type="image" name="cisubmit2" id="cisubmit2" 
      src="https://cilogon.org/images/cilogon-ci-24-w.png"
      alt="CILogon Service" 
      title="Click to get a certificate via the CILogon Service."
      style="border:3px outset #363; cursor:help;" 
      onclick="return false;"
      />
    </form>
    </td>
    <td>
    <form action="#" method="post">
    <textarea cols="68" rows="6">
<form action="#" method="post">
<input type="image" name="cisubmit2" id="cisubmit2" 
src="https://cilogon.org/images/cilogon-ci-24-w.png" 
alt="CILogon Service" 
title="Click to get a certificate via the CILogon Service."
style="border:3px outset #363; cursor:help;" />
</form></textarea>
    </td>
  </tr>
</table>

<h3>More Images For Buttons</h3>

<table cellpadding="5" width="100%"
  style="border-width:1px; border-spacing:1px;
              border-style:solid; border-collapse:collapse;">
  <tr style="border-width:1px; border-style:solid">
    <th>Height</th>
    <th>Log On With</th>
    <th>CILogon</th>
    <th>Icon</th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">16 px</th>
    <th><img src="images/cilogon-logon-16-w.png"
             title="https://cilogon.org/images/cilogon-logon-16-w.png"/></th>
    <th><img src="images/cilogon-ci-16-w.png"
             title="https://cilogon.org/images/cilogon-ci-16-w.png"/></th>
    <th><img src="images/cilogon-logo-16x16.png"
             title="https://cilogon.org/images/cilogon-logo-16x16.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-16-g.png"
             title="https://cilogon.org/images/cilogon-logon-16-g.png"/></th>
    <th><img src="images/cilogon-ci-16-g.png"
             title="https://cilogon.org/images/cilogon-ci-16-g.png"/></th>
    <th><img src="images/cilogon-logo-16x16-b.png"
             title="https://cilogon.org/images/cilogon-logo-16x16-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">24 px</th>
    <th><img src="images/cilogon-logon-24-w.png" 
         title="https://cilogon.org/images/cilogon-logon-24-w.png"/></th>
    <th><img src="images/cilogon-ci-24-w.png" 
         title="https://cilogon.org/images/cilogon-ci-24-w.png"/></th>
    <th><img src="images/cilogon-logo-24x24.png" 
         title="https://cilogon.org/images/cilogon-logo-24x24.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-24-g.png" 
         title="https://cilogon.org/images/cilogon-logon-24-g.png"/></th>
    <th><img src="images/cilogon-ci-24-g.png" 
         title="https://cilogon.org/images/cilogon-ci-24-g.png"/></th>
    <th><img src="images/cilogon-logo-24x24-b.png" 
         title="https://cilogon.org/images/cilogon-logo-24x24-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">32 px</th>
    <th><img src="images/cilogon-logon-32-w.png" 
         title="https://cilogon.org/images/cilogon-logon-32-w.png"/></th>
    <th><img src="images/cilogon-ci-32-w.png" 
         title="https://cilogon.org/images/cilogon-ci-32-w.png"/></th>
    <th><img src="images/cilogon-logo-32x32.png" 
         title="https://cilogon.org/images/cilogon-logo-32x32.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-32-g.png" 
         title="https://cilogon.org/images/cilogon-logon-32-g.png"/></th>
    <th><img src="images/cilogon-ci-32-g.png" 
         title="https://cilogon.org/images/cilogon-ci-32-g.png"/></th>
    <th><img src="images/cilogon-logo-32x32-b.png" 
         title="https://cilogon.org/images/cilogon-logo-32x32-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">40 px</th>
    <th><img src="images/cilogon-logon-40-w.png" 
         title="https://cilogon.org/images/cilogon-logon-40-w.png"/></th>
    <th><img src="images/cilogon-ci-40-w.png" 
         title="https://cilogon.org/images/cilogon-ci-40-w.png"/></th>
    <th><img src="images/cilogon-logo-40x40.png" 
         title="https://cilogon.org/images/cilogon-logo-40x40.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-40-g.png" 
         title="https://cilogon.org/images/cilogon-logon-40-g.png"/></th>
    <th><img src="images/cilogon-ci-40-g.png" 
         title="https://cilogon.org/images/cilogon-ci-40-g.png"/></th>
    <th><img src="images/cilogon-logo-40x40-b.png" 
         title="https://cilogon.org/images/cilogon-logo-40x40-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">48 px</th>
    <th><img src="images/cilogon-logon-48-w.png" 
         title="https://cilogon.org/images/cilogon-logon-48-w.png"/></th>
    <th><img src="images/cilogon-ci-48-w.png" 
         title="https://cilogon.org/images/cilogon-ci-48-w.png"/></th>
    <th><img src="images/cilogon-logo-48x48.png" 
         title="https://cilogon.org/images/cilogon-logo-48x48.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-48-g.png" 
         title="https://cilogon.org/images/cilogon-logon-48-g.png"/></th>
    <th><img src="images/cilogon-ci-48-g.png" 
         title="https://cilogon.org/images/cilogon-ci-48-g.png"/></th>
    <th><img src="images/cilogon-logo-48x48-b.png" 
         title="https://cilogon.org/images/cilogon-logo-48x48-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">64 px</th>
    <th><img src="images/cilogon-logon-64-w.png" 
         title="https://cilogon.org/images/cilogon-logon-64-w.png"/></th>
    <th><img src="images/cilogon-ci-64-w.png" 
         title="https://cilogon.org/images/cilogon-ci-64-w.png"/></th>
    <th><img src="images/cilogon-logo-64x64.png" 
         title="https://cilogon.org/images/cilogon-logo-64x64.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-64-g.png" 
         title="https://cilogon.org/images/cilogon-logon-64-g.png"/></th>
    <th><img src="images/cilogon-ci-64-g.png" 
         title="https://cilogon.org/images/cilogon-ci-64-g.png"/></th>
    <th><img src="images/cilogon-logo-64x64-b.png" 
         title="https://cilogon.org/images/cilogon-logo-64x64-b.png"/></th>
  </tr>
</table>

<hr>

</div>


<?php
printFooter();
?>
