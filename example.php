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

<hr />

<h1>Making/Using Buttons for CILogon</h1>

<p>
There are several ways a web designer can link to the CILogon site.  In the
examples below, you will find examples for <tt>&lt;form&gt;</tt> coding and
for simple hyperlinks (<tt>&lt;a href="..."&gt;</tt>).
</p>
<p>
If you use a <tt>&lt;form&gt;</tt> to route users from your site to the
CILogon Service, you will need to set the "<tt>action=...</tt>" and
"<tt>method=...</tt>" parameters appropriately for your site.  
If your site's pages are generated
server-side (say via PHP or ASP), you can set the target for the
"<tt>action</tt>" parameter to be one of your site's pages, and then
redirect to the appropriate CILogon Service URL.  Otherwise, you can set the
target for the "<tt>action</tt>" parameter to be the CILogon Service URL
directly.  Note that for the CILogon Delegation Service, the URL
<em>must</em> contain the "<tt>oauth_token=...</tt>" parameter, so your
website must somehow generate the correct HTML.
</p>
<p>
If you use a hyperlink to route users to the CILogon site, you simply put
the URL of the CILogon site endpoint into the <tt>href="..."</tt> parameter.
Again note that for the CILogon Delegation Service, the URL <em>must</em>
contain the "<tt>oauth_token=...</tt> parameter, so your website will
probably need to generate the HTML output dynamically.
</p>

<p>
For the purposes of these examples, we will use the end-user CILogon Service
URL as the "<tt>action</tt>" parameter. 
</p>

<h2>Text-Only Button</h2>

This example shows a form "submit" button that has been stylized with
colors used by the CILogon site.

<table width="100%" cellspacing="0" cellpadding="10" 
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
    <form action="#" method="post">
    <input type="submit" name="cisubmit1" id="cisubmit1" 
      value="CILogon Service" 
      title="Click to get a certificate via the CILogon Service."
      style="font-family:arial,sans-serif; font-style:italic; 
      font-weight:bold; font-size:large; color:#030; 
      background-color:#aba; cursor:help;" 
      onclick="return false;"
      />
    </form>
    </td>
    <td>
    <form action="#" method="post">
    <textarea cols="68" rows="8">
&lt;form action="#" method="post"&gt;
&lt;input type="submit" name="cisubmit1" id="cisubmit1" 
value="CILogon Service" 
title="Click to get a certificate via the CILogon Service."
style="font-family:arial,sans-serif; font-style:italic; 
font-weight:bold; font-size:large; color:#030; 
background-color:#aba; cursor:help;" /&gt;
&lt;/form&gt;</textarea>
    </form>
    </td>
  </tr>
</table>

<h2>Image Button</h2>

This example shows another form "submit" button, this time using the type
"<tt>image</tt>" to show an icon rather than text.  Additional buttons/icons
are available at the bottm of the page.

<table width="100%" cellspacing="0" cellpadding="10" 
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
    <form action="#" method="post">
    <input type="image" name="cisubmit2" id="cisubmit2" 
      src="https://cilogon.org/images/cilogon-logon-32-g.png"
      alt="CILogon Service" 
      title="Click to get a certificate via the CILogon Service."
      style="cursor:help;" 
      onclick="return false;"
      />
    </form>
    </td>
    <td>
    <form action="#" method="post">
    <textarea cols="68" rows="7">
&lt;form action="#" method="post"&gt;
&lt;input type="image" name="cisubmit2" id="cisubmit2" 
src="https://cilogon.org/images/cilogon-ci-32-g.png" 
alt="CILogon Service" 
title="Click to get a certificate via the CILogon Service."
style="cursor:help;" /&gt;
&lt;/form&gt;</textarea>
</form>
    </td>
  </tr>
</table>

<h2>Basic Hyperlink</h2>

<p>
You can put an image and some text inside a standard &lt;a href="..."&gt;
tag if you want a basic link.  Here is one example using a CILogon icon and
some CSS stylized text.
</p>

<table width="100%" cellspacing="0" cellpadding="10" 
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
      <a href="https://cilogon.org/" 
      style="font-family:arial,sans-serif; font-style:italic;
      font-weight:bold; font-size:x-large; color:#696;"><img 
      style="border-style:none; vertical-align:middle"
      src="https://cilogon.org/images/cilogon-logo-24x24-b.png"
      alt="CILogo"/>Get Certificate</a>
    </td>
  <td>
    <form action="#" method="post">
    <textarea cols="68" rows="6">
&lt;a href="https://cilogon.org/" 
style="font-family:arial,sans-serif; font-style:italic;
font-weight:bold; font-size:x-large; color:#696;"&gt;&lt;img 
style="border-style:none; vertical-align:middle"
src="https://cilogon.org/images/cilogon-logo-24x24-b.png"
alt="CILogo"&gt;Get Certificate&lt;/a&gt;</textarea>
    </form>
  </td>
  </tr>
</table>

<h2>Hyperlink In A &lt;div&gt;</h2>

<p>
Here is another example putting both the icon image and the associated text
inside a <tt>&lt;div&gt;</tt> element, and then adding a border/frame
decoration to make it look like a button.
</p>

<table width="100%" cellspacing="0" cellpadding="10" 
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
      <div style="border: 5px outset #696; cursor:pointer;
      display:inline-block; padding:5px;" 
      onclick="location.href='http://cilogon.org'">
      <a href="https://cilogon.org" 
      style="font-family:arial,sans-serif; font-style:italic;
      font-weight:bold; font-size:x-large; color:#363;
      text-decoration:none;">
        <img src="https://cilogon.org/images/cilogon-logo-32x32.png"
             alt="CILogo" style="vertical-align:middle"/> CILogon Service</a>
      </div>
    </td>
    <td>
    <form action="#" method="post">
    <textarea cols="68" rows="9">
&lt;div style="border: 5px outset #696; cursor:pointer; padding:5px;
display:inline-block;" onclick="location.href='http://cilogon.org'"&gt;
&lt;a href="https://cilogon.org" 
style="font-family:arial,sans-serif; font-style:italic;
font-weight:bold; font-size:x-large; color:#363; 
text-decoration:none;"&gt;&lt;img alt="CILogo"
src="https://cilogon.org/images/cilogon-logo-32x32.png"
style="vertical-align:middle"/&gt; CILogon Service&lt;/a&gt;
&lt;/div&gt;</textarea>
    </form>
    </td>
  </tr>
</table>


<hr />

<h1>More Images For Buttons</h1>

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
    <th><img src="images/cilogon-logon-16-w.png" alt="CILogo"
             title="https://cilogon.org/images/cilogon-logon-16-w.png"/></th>
    <th><img src="images/cilogon-ci-16-w.png" alt="CILogo"
             title="https://cilogon.org/images/cilogon-ci-16-w.png"/></th>
    <th><img src="images/cilogon-logo-16x16.png" alt="CILogo"
             title="https://cilogon.org/images/cilogon-logo-16x16.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-16-g.png" alt="CILogo"
             title="https://cilogon.org/images/cilogon-logon-16-g.png"/></th>
    <th><img src="images/cilogon-ci-16-g.png" alt="CILogo"
             title="https://cilogon.org/images/cilogon-ci-16-g.png"/></th>
    <th><img src="images/cilogon-logo-16x16-b.png" alt="CILogo"
             title="https://cilogon.org/images/cilogon-logo-16x16-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">24 px</th>
    <th><img src="images/cilogon-logon-24-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-24-w.png"/></th>
    <th><img src="images/cilogon-ci-24-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-24-w.png"/></th>
    <th><img src="images/cilogon-logo-24x24.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-24x24.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-24-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-24-g.png"/></th>
    <th><img src="images/cilogon-ci-24-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-24-g.png"/></th>
    <th><img src="images/cilogon-logo-24x24-b.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-24x24-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">32 px</th>
    <th><img src="images/cilogon-logon-32-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-32-w.png"/></th>
    <th><img src="images/cilogon-ci-32-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-32-w.png"/></th>
    <th><img src="images/cilogon-logo-32x32.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-32x32.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-32-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-32-g.png"/></th>
    <th><img src="images/cilogon-ci-32-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-32-g.png"/></th>
    <th><img src="images/cilogon-logo-32x32-b.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-32x32-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">40 px</th>
    <th><img src="images/cilogon-logon-40-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-40-w.png"/></th>
    <th><img src="images/cilogon-ci-40-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-40-w.png"/></th>
    <th><img src="images/cilogon-logo-40x40.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-40x40.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-40-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-40-g.png"/></th>
    <th><img src="images/cilogon-ci-40-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-40-g.png"/></th>
    <th><img src="images/cilogon-logo-40x40-b.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-40x40-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">48 px</th>
    <th><img src="images/cilogon-logon-48-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-48-w.png"/></th>
    <th><img src="images/cilogon-ci-48-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-48-w.png"/></th>
    <th><img src="images/cilogon-logo-48x48.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-48x48.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-48-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-48-g.png"/></th>
    <th><img src="images/cilogon-ci-48-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-48-g.png"/></th>
    <th><img src="images/cilogon-logo-48x48-b.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-48x48-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">64 px</th>
    <th><img src="images/cilogon-logon-64-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-64-w.png"/></th>
    <th><img src="images/cilogon-ci-64-w.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-64-w.png"/></th>
    <th><img src="images/cilogon-logo-64x64.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-64x64.png"/></th>
  </tr>
  <tr>
    <th><img src="images/cilogon-logon-64-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logon-64-g.png"/></th>
    <th><img src="images/cilogon-ci-64-g.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-ci-64-g.png"/></th>
    <th><img src="images/cilogon-logo-64x64-b.png" alt="CILogo"
         title="https://cilogon.org/images/cilogon-logo-64x64-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th> 5000 px</th>
    <th colspan="2">Resize this giant 5000x5000 icon to fit your site:</th>
    <th><a
    href="https://cilogon.org/images/cilogon-logo-5000x5000.png">View</a></th>
  </tr>
</table>

</div>


<?php
printFooter();
?>
