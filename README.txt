#!/bin/bash

#############################################################################
#                        ABOUT THIS DIRECTORY
#                        --------------------
# This directory contains the PHP code for the CILogon Service Provider.
# You can check out the files to the /var/www/html/ directory on your server
# using thefollowing commands:
#
# sudo -i
# cd /var/www/
# cvs -d:pserver:anonymous@cilogon.cvs.sourceforge.net:/cvsroot/cilogon \
#      login
# ### Password is empty ###
# cvs -d:pserver:anonymous@cilogon.cvs.sourceforge.net:/cvsroot/cilogon \
#      export -D now -d html service/html
#
# If you prefer to be able to keep the files current via 'cvs update',
# you can replace the last cvs command above with this one:
#
# cvs -d:pserver:anonymous@cilogon.cvs.sourceforge.net:/cvsroot/cilogon \
#     checkout -P -d html service/html
#
# This cvs command creates 'CVS' directories which allow for 'cvs update'
# commands to keep the code up-to-date. Note that if you choose this method,
# you should configure your httpd server NOT to serve files in the CVS
# directories. This can be done in Apache httpd by adding the following
# lines to your httpd.conf file:
#
# RedirectMatch 404 /\\.(svn|git|hg|bzr|cvs)(/|$)
# RedirectMatch 404 "(?:.*)/(?:CVS|RCS|_darcs)(?:/.*)?$"
#
# Once you have checked out the files, you need to create some symlinks for
# proper functionality. This file contains the script necessary to do so.
# Simply 'execute' the file as follows:
#
# sudo sh /var/www/html/README.txt
#
#############################################################################

# Check script is running as 'root' user
if [ "$(id -u)" != "0" ] ; then
    BOLD=`tput bold`
    NORM=`tput sgr0`
    echo "${BOLD}This script must be run as root.${NORM}" 1>&2
    exit 1
fi

if [ ! -f /var/www/html/index-site.php ] ; then
  echo "Error! Could not find /var/www/html/index-site.php. Exiting."
  exit 1;
fi
echo 'chown -R root:root /var/www/html/'
chown -R root:root /var/www/html/
echo 'cd /var/www/html/'
cd /var/www/html/
echo 'composer install --no-plugins --no-scripts'
composer install --no-plugins --no-scripts
echo 'cd /var/www/html/delegate/'
echo 'cd /var/www/html/'
cd /var/www/html/
echo 'chmod 775 include'
chmod 775 include
echo 'chgrp apache include'
chgrp apache include
echo 'mkdir -p /var/www/virthosts/crl'
mkdir -p /var/www/virthosts/crl

filename=`basename $0`

echo "Done! You can now delete this $filename file."
echo
echo "NOTE: You should also create /var/www/html/include/idplist.xml"
echo "      by running /etc/cron.hourly/idplist.cron ."

exit 0
