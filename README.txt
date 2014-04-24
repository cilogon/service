#!/bin/bash

#############################################################################
#                        ABOUT THIS DIRECTORY                               
#                        --------------------
# This directory contains the PHP code for the CILogon Service Provider.
# You can check out the files to the /var/www/html/ directory on your server 
# using thefollowing commands:
#
# sudo su -
# cd /var/www/
# cvs -d:pserver:anonymous@cilogon.cvs.sourceforge.net:/cvsroot/cilogon \
#      login
# cvs -d:pserver:anonymous@cilogon.cvs.sourceforge.net:/cvsroot/cilogon \
#      export -D now -d html service/html
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

cat <<HELPTEXT

This script should be executed after a fresh checkout/export from CVS. 
It creates symbolic links for proper functionality of the CILogon Service.
It assumes files have been placed in the /var/www/html/ directory. 

Type "proceed" to execute commands, or anything else to exit:
HELPTEXT

read proceed
case $proceed in 
  proceed ) ;;
  *       ) echo "Exiting." ; exit 1;;
esac

if [ ! -f /var/www/html/index-site.php ] ; then
  echo "Error! Could not find /var/www/html/index-site.php. Exiting."
  exit 1;
fi
echo 'cd /var/www/html/'
cd /var/www/html/
echo 'rm -f index.php'
rm -f index.php
echo 'ln -sf index-site.php index.php'
ln -sf index-site.php index.php
echo 'rm -f logo'
rm -f logo
echo 'ln -sf images/cilogon-ci-80-w.png logo'
ln -sf images/cilogon-ci-80-w.png logo
echo 'ln -sf gridshib-ca-2.0.1 gridshib-ca'
ln -sf gridshib-ca-2.0.1 gridshib-ca
echo 'cd /var/www/html/skin/globusonline2/'
cd /var/www/html/skin/globusonline2/
echo 'rm -f logo_globus.png'
rm -f logo_globus.png
echo 'rm -f skin.css'
rm -f skin.css
echo 'ln -s ../globusonline/logo_globus.png .'
ln -s ../globusonline/logo_globus.png .
echo 'ln -s ../globusonline/skin.css .'
ln -s ../globusonline/skin.css .
echo 'cd /var/www/html/delegate/'
cd /var/www/html/delegate/
echo 'rm -f index.php'
rm -f index.php
echo 'ln -sf index-site.php index.php'
ln -sf index-site.php index.php
echo 'cd /var/www/html/'
cd /var/www/html/

filename=`basename $0`

echo "Done! You can now delete this $filename file."

exit 0
