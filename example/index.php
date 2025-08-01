<?php

// error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../config.secrets.php';

use CILogon\Service\Content;

Content::printHeader('CILogon Service Example Buttons');
?>


<h1>Examples of CILogon Buttons and Links</h1>

<p>
This page provides several examples of form buttons and links that website
designers can include in their sites to link to the CILogon Service.  The
various images may also be included in presentations.
</p>
<p>
CILogon provides several services. In the examples that follow, be
sure to use the appropriate URL (and any additional parameters) to suit your
needs.
</p>

<ol>
  <li><strong><a target="_blank"
  href="https://cilogon.org/">https://cilogon.org/</a></strong> -  The main
  CILogon Service site is for use by end-users to view the user attributes
  asserted by their chosen Identity Provider.
  </li>
  <li><strong><a target="_blank"
  href="https://cilogon.org/.well-known/openid-configuration">https://cilogon.org/.well-known/openid-configuration</a></strong>
  - The CILogon OpenID Connect (OIDC) Discovery Endpoint is for use by
    OAuth2 / OIDC clients. In order to use the CILogon OIDC service,
    you must first register your OIDC client at <a target="_blank"
    href="https://cilogon.org/oauth2/register">https://cilogon.org/oauth2/register</a>.
    You will need to know the callback URLs for your OIDC client as well
    as the scopes/claims used by your application. For more information about
    the CILogon OIDC Service, see the information on <a target="_blank"
    href="https://www.cilogon.org/oidc">CILogon OpenID Connect (OIDC)</a>.
  </li>
  <li><strong><a target="_blank"
  href="https://cilogon.org/device/">https://cilogon.org/device/</a></strong>
  - The CILogon Device endpoint is used for Device Authorization Grant Flow
    (RFC 8628) which enables devices without a web browser to get OAuth2/OIDC
    tokens. For details, please visit <a target="_blank"
    href="https://www.cilogon.org/device">https://www.cilogon.org/device</a>.
  </li>
</ol>

Where appropriate, the HTML has been stylized using "inline" CSS.  You can
move the contents of the "<samp>style=...</samp>" parameter to an included
external CSS file if you are more comfortable with that method of coding.

<hr />

<h1>Making/Using Buttons for CILogon</h1>

<p>
There are several ways a web designer can link to the CILogon site.  In the
examples below, you will find examples for <samp>&lt;form&gt;</samp> coding and
for simple hyperlinks (<samp>&lt;a href="..."&gt;</samp>).
</p>
<p>
If you use a <samp>&lt;form&gt;</samp> to route users from your site to the
CILogon Service, you will need to set the "<samp>action=...</samp>" and
"<samp>method=...</samp>" parameters appropriately for your site.
If your site can process <samp>&lt;form&gt;</samp> submission,
you can set the target for the "<samp>action</samp>" parameter to be one of your
site's pages, and then redirect to the appropriate CILogon Service URL.
Otherwise, you can set the target for the "<samp>action</samp>" parameter to be
the CILogon Service URL directly.
</p>
<p>
If you use a hyperlink to route users to the CILogon site, you simply put
the URL of the CILogon site endpoint into the <samp>href="..."</samp> parameter.
</p>

<p>
For the purposes of these examples, we will assume that the current page can
process forms, so the "<samp>action</samp>" parameter will be "<samp>#</samp>".  For
the anchors (hyperlinks), we will use the end-user CILogon Service
URL (https://cilogon.org) in the "<samp>href</samp>" parameter.
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
      title="Click to uses the CILogon Service."
      style="font-family:Arial,sans-serif; font-style:italic;
      font-weight:bold; font-size:large; color:#030;
      background-color:#aca; cursor:help;"
      onclick="return false;"
      />
    </form>
    </td>
    <td align="right">
    <form action="#" method="post">
    <textarea cols="68" rows="8">
&lt;form action="#" method="post"&gt;
&lt;input type="submit" name="cisubmit1" id="cisubmit1"
value="CILogon Service"
title="Click to use the CILogon Service."
style="font-family:Arial,sans-serif; font-style:italic;
font-weight:bold; font-size:large; color:#030;
background-color:#aca; cursor:help;" /&gt;
&lt;/form&gt;</textarea>
    </form>
    </td>
  </tr>
</table>

<hr style="width:70%"/>

<h2>Image Button</h2>

This example shows another form "submit" button, this time using the type
"<samp>image</samp>" to show an icon rather than text.  Additional buttons/icons
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
      title="Click to use the CILogon Service."
      style="cursor:help;"
      onclick="return false;"
      />
    </form>
    </td>
    <td align="right">
    <form action="#" method="post">
    <textarea cols="68" rows="7">
&lt;form action="#" method="post"&gt;
&lt;input type="image" name="cisubmit2" id="cisubmit2"
src="https://cilogon.org/images/cilogon-ci-32-g.png"
alt="CILogon Service"
title="Click to use the CILogon Service."
style="cursor:help;" /&gt;
&lt;/form&gt;</textarea>
</form>
    </td>
  </tr>
</table>

<hr style="width:70%"/>

<h2>Basic Hyperlink</h2>

<p>
You can put an image and some text inside a standard <samp>&lt;a
href="..."&gt;</samp> tag if you want a basic link.  Here is one example using
a CILogon icon and some CSS stylized text.
</p>

<table width="100%" cellspacing="0" cellpadding="10"
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
      <a href="https://cilogon.org/"
      style="font-family:Arial,sans-serif; font-style:italic;
      font-weight:bold; font-size:x-large; color:#696;"><img
      style="border-style:none; vertical-align:middle"
      src="https://cilogon.org/images/cilogon-logo-24x24-b.png"
      alt=""/>Visit CILogon.org</a>
    </td>
    <td align="right">
      <form action="#" method="post">
      <textarea cols="68" rows="6">
&lt;a href="https://cilogon.org/"
style="font-family:Arial,sans-serif; font-style:italic;
font-weight:bold; font-size:x-large; color:#696;"&gt;&lt;img
style="border-style:none; vertical-align:middle"
src="https://cilogon.org/images/cilogon-logo-24x24-b.png"
alt=""&gt;Visit CILogon.org&lt;/a&gt;</textarea>
      </form>
    </td>
  </tr>
</table>

<hr style="width:70%"/>

<h2>Hyperlink In A &lt;div&gt;</h2>

<p>
Here is another example putting both the icon image and the associated text
inside a <samp>&lt;div&gt;</samp> element, and then adding a border/frame
decoration to make it look like a button.  Notice the addition of the
<samp>onclick</samp> method (which requires JavaScript to be enabled) to the
<samp>&lt;div&gt;</samp> to make the entire "button" clickable.
</p>

<table width="100%" cellspacing="0" cellpadding="10"
  style="border-width:0; border-spacing:0;
         border-style:solid; border-collapse:collapse;">
  <tr>
    <td>
      <div style="border: 5px outset #696; cursor:pointer;
      display:inline-block; padding:3px;"
      onclick="location.href='https://cilogon.org'">
      <a href="https://cilogon.org"
      style="font-family:Arial,sans-serif; font-style:italic;
      font-weight:bold; font-size:28px; color:#363;
      text-decoration:none;">
        <img src="https://cilogon.org/images/cilogon-logo-32x32.png"
             alt="" style="vertical-align:middle"/>&nbsp;CILogon&nbsp;Service</a>
      </div>
    </td>
    <td align="right">
    <form action="#" method="post">
    <textarea cols="68" rows="9">
&lt;div style="border: 5px outset #696; cursor:pointer; padding:3px;
display:inline-block;" onclick="location.href='https://cilogon.org'"&gt;
&lt;a href="https://cilogon.org"
style="font-family:Arial,sans-serif; font-style:italic;
font-weight:bold; font-size:28px; color:#363;
text-decoration:none;"&gt;&lt;img alt=""
src="https://cilogon.org/images/cilogon-logo-32x32.png"
style="vertical-align:middle"/&gt;&amp;nbsp;CILogon&amp;nbsp;Service&lt;/a&gt;
&lt;/div&gt;</textarea>
    </form>
    </td>
  </tr>
</table>


<hr />

<h1>More Images For Buttons</h1>

<p>
Below are buttons you may use on your website.  You can either download
these images and put them on your site, or you may reference them directly
from the cilogon.org site.  Hover your mouse cursor over each image
to see the URL for the image.
</p>

<p>
The last entry in the table is a link to a huge 5000 x 5000 icon that you
can resize down to a size suitable for your site.  Right-click on the "View"
link to save to your local computer.
</p>

<p>
The font used for the "CILogon" text is <a target="_blank"
href="https://learn.microsoft.com/en-us/typography/font-list/arial">Arial</a>,
with font-weight <strong>bold</strong>, and font-style <em>italic</em>.
</p>

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
    <th><img src="../images/cilogon-logon-16-w.png" alt=""
             title="https://cilogon.org/images/cilogon-logon-16-w.png"/></th>
    <th><img src="../images/cilogon-ci-16-w.png" alt=""
             title="https://cilogon.org/images/cilogon-ci-16-w.png"/></th>
    <th><img src="../images/cilogon-logo-16x16.png" alt=""
             title="https://cilogon.org/images/cilogon-logo-16x16.png"/></th>
  </tr>
  <tr>
    <th><img src="../images/cilogon-logon-16-g.png" alt=""
             title="https://cilogon.org/images/cilogon-logon-16-g.png"/></th>
    <th><img src="../images/cilogon-ci-16-g.png" alt=""
             title="https://cilogon.org/images/cilogon-ci-16-g.png"/></th>
    <th><img src="../images/cilogon-logo-16x16-b.png" alt=""
             title="https://cilogon.org/images/cilogon-logo-16x16-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">24 px</th>
    <th><img src="../images/cilogon-logon-24-w.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-24-w.png"/></th>
    <th><img src="../images/cilogon-ci-24-w.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-24-w.png"/></th>
    <th><img src="../images/cilogon-logo-24x24.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-24x24.png"/></th>
  </tr>
  <tr>
    <th><img src="../images/cilogon-logon-24-g.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-24-g.png"/></th>
    <th><img src="../images/cilogon-ci-24-g.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-24-g.png"/></th>
    <th><img src="../images/cilogon-logo-24x24-b.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-24x24-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">32 px</th>
    <th><img src="../images/cilogon-logon-32-w.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-32-w.png"/></th>
    <th><img src="../images/cilogon-ci-32-w.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-32-w.png"/></th>
    <th><img src="../images/cilogon-logo-32x32.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-32x32.png"/></th>
  </tr>
  <tr>
    <th><img src="../images/cilogon-logon-32-g.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-32-g.png"/></th>
    <th><img src="../images/cilogon-ci-32-g.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-32-g.png"/></th>
    <th><img src="../images/cilogon-logo-32x32-b.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-32x32-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">40 px</th>
    <th><img src="../images/cilogon-logon-40-w.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-40-w.png"/></th>
    <th><img src="../images/cilogon-ci-40-w.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-40-w.png"/></th>
    <th><img src="../images/cilogon-logo-40x40.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-40x40.png"/></th>
  </tr>
  <tr>
    <th><img src="../images/cilogon-logon-40-g.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-40-g.png"/></th>
    <th><img src="../images/cilogon-ci-40-g.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-40-g.png"/></th>
    <th><img src="../images/cilogon-logo-40x40-b.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-40x40-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">48 px</th>
    <th><img src="../images/cilogon-logon-48-w.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-48-w.png"/></th>
    <th><img src="../images/cilogon-ci-48-w.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-48-w.png"/></th>
    <th><img src="../images/cilogon-logo-48x48.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-48x48.png"/></th>
  </tr>
  <tr>
    <th><img src="../images/cilogon-logon-48-g.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-48-g.png"/></th>
    <th><img src="../images/cilogon-ci-48-g.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-48-g.png"/></th>
    <th><img src="../images/cilogon-logo-48x48-b.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-48x48-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th rowspan="2">64 px</th>
    <th><img src="../images/cilogon-logon-64-w.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-64-w.png"/></th>
    <th><img src="../images/cilogon-ci-64-w.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-64-w.png"/></th>
    <th><img src="../images/cilogon-logo-64x64.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-64x64.png"/></th>
  </tr>
  <tr>
    <th><img src="../images/cilogon-logon-64-g.png" alt=""
         title="https://cilogon.org/images/cilogon-logon-64-g.png"/></th>
    <th><img src="../images/cilogon-ci-64-g.png" alt=""
         title="https://cilogon.org/images/cilogon-ci-64-g.png"/></th>
    <th><img src="../images/cilogon-logo-64x64-b.png" alt=""
         title="https://cilogon.org/images/cilogon-logo-64x64-b.png"/></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th> 5000 px</th>
    <th colspan="2">Resize this giant 5000x5000 icon to fit your site:</th>
    <th><a
    href="https://cilogon.org/images/cilogon-logo-5000x5000.png">View</a></th>
  </tr>
  <tr style="border-width:1px; border-top-style:solid">
    <th> SVG </th>
    <th colspan="2">Scalable Vector Graphics (SVG) file:</th>
    <th><a
    href="https://cilogon.org/images/cilogon-logo.svg">View</a></th>
  </tr>
</table>



<?php
Content::printFooter();
?>
