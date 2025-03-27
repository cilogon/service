#!/bin/bash

# Make sure we are in the script directory
SCRIPT_HOME=$(dirname $(realpath "$0"))
CURR_DIR=`pwd`
if [ "${SCRIPT_HOME}" != "${CURR_DIR}" ] ; then
    echo "Please run this script from the $SCRIPT_HOME directory."
    exit
fi

# Check if the service-lib repo exists at the same dir level
if [ ! -e "../service-lib/composer.json" ] ; then
    echo "Please run the following commands to check out the service-lib"
    echo "repository at the same directory level as this repository."
    echo "    pushd .."
    echo "    git clone git@github.com:cilogon/service-lib.git"
    echo "    popd"
    echo "Then run this script again."
    exit
fi

# Create symlink to service-lib if not exist
mkdir -p vendor/cilogon
pushd vendor/cilogon &> /dev/null
if [ ! -L "service-lib" ] ; then
    ln -s ../../../service-lib .
fi
popd &> /dev/null

# Generate the gettext .pot file from .php source files.
# Then look for translation .po files and merge them in.
find -L . -iname '*.php' | xargs xgettext --default-domain=cilogon --from-code=UTF-8 --output=cilogon-i18n.pot
find -L . -name '*.po' | xargs -I{} msgmerge -U {} cilogon-i18n.pot
