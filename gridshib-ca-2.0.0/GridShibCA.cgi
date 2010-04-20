#!/usr/bin/perl -T -w -I /usr/local/gridshib-ca-2.0.0/perl
######################################################################
#
# This file is part of the GriShib-CA distribution.  Copyright
# 2006-2009 The Board of Trustees of the University of
# Illinois. Please see LICENSE at the root of the distribution.
#
######################################################################

use GridShibCA::ErrorHandler qw(handleError);
use GridShibCA::Exception qw(:try);
use GridShibCA::WebApp;

try
{
    my $webapp = GridShibCA::WebApp->new();
    $webapp->handleRequest();
}
otherwise
{
    # If we get here, it means something really bad happened
    # (misconfiguration or programming error). Do our best to handle
    # it gracefully.
    my $ex = shift;
    handleError("Failed to handle request",
		-exception=>$ex);
    # Does not return
 };

exit(0);
### Local Variables: ***
### mode:perl ***
### End: ***

