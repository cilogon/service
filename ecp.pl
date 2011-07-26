#!/usr/bin/env perl

#########################################################################
# Script      : ecp.pl                                                  #
# Authors     : Terry Fleury <tfleury@illinois.edu>                     #
#               Scott Koranda <skoranda@gmail.com>                      #
# Create Date : July 06, 2011                                           #
# Last Update : July 22, 2011                                           #
#                                                                       #
# This PERL script allows a user to get a certificate or PKCS12         #
# credential from the CILogon Service. It can also get the contents     #
# of any ECP-enabled Service Provider (SP). The script can be used as   #
# an example of how a SAML ECP client works.                            #
#                                                                       #
# Studying this script is not an acceptable replacement for reading     #
# Draft 02 of the ECP profile [ECP] available at:                       #
# http://wiki.oasis-open.org/security/SAML2EnhancedClientProfile        #
#                                                                       #
# This script assumes that the server hosting the IdP has been          #
# configured to require a type of Basic Auth (login and password)       #
# for the ECP location.                                                 #
#                                                                       #
#########################################################################
# NOTE: You must set the OPENSSL_BIN constant below to be the full path #
# of the "openssl" binary on your system.                               #
#########################################################################

use constant { 
    OPENSSL_BIN  =>'/usr/bin/openssl' ,  ### CHANGE THIS IF NECESSARY

    ECP_IDPS_URL =>'https://test.cilogon.org/include/ecpidps.txt' ,
    GET_CERT_URL =>'https://test.cilogon.org/secure/getuser/' ,
    HEADER_ACCEPT=>'text/html; application/vnd.paos+xml' ,
    HEADER_PAOS  =>'ver="urn:liberty:paos:2003-08";"urn:oasis:names:tc:SAML:2.0:profiles:SSO:ecp"' ,
};

######################
# BEGIN MAIN PROGRAM #
######################

our $VERSION = "0.001";
$VERSION = eval $VERSION;

use strict;
use Term::ReadLine;
use Term::UI;
use Getopt::Long qw(:config bundling);
use Pod::Usage;
use LWP;
use HTTP::Cookies;
use URI;
use IPC::Open3;
use File::Spec;
use File::Basename;
use Symbol qw(gensym);

# Handle <Ctrl>+C to reset the terminal to non-bold text
$SIG{INT} = \&resetTerm;

my %opts = ();
my %idps = ();
my $verbose = 0;
my $quiet = 0;
my $term;
my $reply;
my $idpurl = '';
my $idpname = '';
my $idpuser = '';
my $idppass = '';
my $get = '';
my $geturl = '';
my $getstr = '';
my $certreq = '';
my $inkey = '';
my $outkey = '';
my $csr = '';
my $passwd = '';
my $lifetime = 0;
my $vo = '';
my $outputfile = '';
my $urltoget = '';
my $xmlstr = '';
my $idpresp = '';
my $relaystate = '';
my $responseConsumerURL = '';
my $assertionConsumerServiceURL = '';

GetOptions(\%opts, 'help|h|?',
                   'verbose|debug|v|d',
                   'version|V',
                   'quiet|q',
                   'listidps|l',
                   'idpname|n=s',
                   'idpurl|e=s',
                   'idpuser|u=s',
                   'idppass|p=s',
                   'get|g=s',
                   'certreq|c=s',
                   'lifetime|t=i',
                   'inkey|i=s',
                   'outkey|k=s',
                   'vo|O=s',
                   'out|o=s',
                   'password|P=s',
                   'url|U=s') or pod2usage(-verbose=>1) && exit;

# If the user asked for help, print it and then exit.
if (exists $opts{help}) {
    pod2usage(-verbose=>2) && exit;
}

# If the user requested version number, print it and then exit.
if (exists $opts{version}) {
    print "ecp.pl version '" . main->VERSION . "'\n";
    exit;
}

# Fetch the list of IdPs to list them now or search them later.
%idps = fetchIdps();
if (!keys %idps) {  # MAJOR ERROR! No ECP IdPs fetched!
    warn "Error: Unable to fetch the list of IdPs from the CILogon server." if 
        (!$quiet);
    exit 1;
}

# If list IdPs, print them out and then exit.
if (exists $opts{listidps}) {
    foreach my $key (sort keys %idps) {
        print "\e[1m$key\e[0m :\n    $idps{$key}\n";
    }
    exit;
}

# If we made it this far, then we want to get something (like a cert).

# Figure out if we should be verbose or quiet; verbose trumps quiet.
if (exists $opts{quiet}) {
    $quiet = 1;
}
if (exists $opts{verbose}) {
    $verbose = 1;
    $quiet = 0;
}

# Check if the user entered --idpurl with a valid URL
if (exists $opts{idpurl}) {
    $idpurl = trim($opts{idpurl});
    if (!isValidURL($idpurl)) {
        warn "Error: '$idpurl' does not appear to be a " .
             "valid 'https://...' URL." if (!$quiet);
        $idpurl = '';
    }
}

# If no valid --idpurl given, check for a valid --idpname
if ((length($idpurl) == 0) && (exists $opts{idpname})) {
    my $found = 0;
    $idpname = trim($opts{idpname});
    while ((!$found) && ((my $key,my $value) = each %idps)) {
        if ($key =~ /$idpname/i) {
            $found = 1;
            $idpname = $key;
            $idpurl = $value;
        }
    }
    if (!$found) {
        warn "Error: '$idpname' does not appear to be a valid IdP." if 
            (!$quiet);
        $idpname = '';
    }
}

# If neither valid --idpurl nor --idpname given, prompt user
$term = Term::ReadLine->new('readline');
if ((length($idpurl) == 0) && (length($idpname) == 0)) {
    my @idpnames = sort keys %idps;
    push(@idpnames,'Specify the URL of another IdP');
    $reply = $term->get_reply(
             prompt => 'Choose',
             print_me => 'Select an Identity Provider (IdP):',
             choices => \@idpnames,
             default => $idpnames[0]
             );

    if ($reply eq 'Specify the URL of another IdP') {
        $idpurl = '';
        while (!isValidURL($idpurl)) {
            $idpurl = trim($term->readline('Enter the IdP URL: '));
            if (!isValidURL($idpurl)) {
                warn "Error: '$idpurl' does not appear to be a " .
                     "valid 'https://...' URL." if (!$quiet);
            }
        }
    } else {
        $idpname = $reply;
        $idpurl = $idps{$idpname};
    }
}

# Prompt for the IdP username
if (exists $opts{idpuser}) {
    $idpuser = trim($opts{idpuser});
}
while (length($idpuser) == 0) {
    $idpuser = trim($term->readline(
        'Enter a username for the Identity Provider: '));
    if (length($idpuser) == 0) {
        warn "Error: IdP username cannot be empty." if (!$quiet);
    }
}

# Prompt for the IdP password
if (exists $opts{idppass}) {
    $idppass = $opts{idppass};
}
if (length($idppass) == 0) {
    system('stty','-echo') if ($^O !~ /MSWin/i);
    $idppass = $term->readline('Enter a password for the Identity Provider: ');
    if ($^O !~ /MSWin/i) {
        system('stty','echo');
        print "\n";
    }
}

# Print out IdP name, url, and username if verbose is on
if ($verbose) {
    if (length($idpname) == 0) {
        $idpname = 'User Defined';
    }
    print "Using the following Identity Provider:\n";
    print "    \e[1m$idpname\e[0m :\n        $idpurl\n";
    print "Logging in with username '$idpuser'.\n";
}


# Next, figure out the 'get' operation: cert, pkcs12, or url

# Check if the user entered a valid --get parameter (only use 1st letter)
if (exists $opts{get}) {
    my $getopt = $opts{get};
    $get = substr($getopt,0,1);
    if (($get ne 'c') && ($get ne 'p') && ($get ne 'u')) {
        warn "Error: Unknown operation '$getopt'." if (!$quiet);
        $get = '';
    }
}

# Either no --get parameter or not one of [c|p|u], so prompt user
if (length($get) == 0) {
    my @choices = ('Certificate using a certificate signing request',
                   'PKCS12 credential',
                   'URL that you specify');
    $reply = $term->get_reply(
             prompt => 'Choose',
             print_me => 'What do you want to get?',
             choices => \@choices,
             default => $choices[0]
             );

    $get = lc substr($reply,0,1);
}

# If user wants to get a specific URL, check for valid command line option,
# or prompt the user for one.
if ($get eq 'u') {
    $getstr = 'URL';
    if (exists $opts{url}) {
        $geturl = trim($opts{url});
        if (!isValidURL($geturl)) {
            warn "Error: '$geturl' does not appear to be a valid " .
                 "'https://...' URL." if (!$quiet);
            $geturl = '';
        }
    }
    while (!isValidURL($geturl)) {
        $geturl = trim($term->readline('Enter the URL to get: '));
        if (!isValidURL($geturl)) {
            warn "Error: '$geturl' does not appear to be a valid " .
                 "'https://...' URL." if (!$quiet);
        }
    }
}

# If user wants a cert using a CSR, prompt for info to get the csr string
if ($get eq 'c') {
    $getstr = 'certificate';
    # Check to make sure that the openssl binary is available
    if (!checkOpenSSL()) { 
        warn "Error: Unable to execute the OpenSSL command at '" .
            OPENSSL_BIN . "'. Aborting." if (!$quiet);
        exit 1;
    }

    # Check if user specified the CSR filename on the command line
    my $certreqfile = '';
    if (exists $opts{certreq}) {
        $certreqfile = trim($opts{certreq});
        if ($certreqfile eq 'create') {
            $certreqfile = '';  # Create a CSR on-the-fly
        }
    } else { # Didn't specify certreq, so prompt for it
        $reply = $term->get_reply(
                 prompt => 'Enter filename',
                 print_me => "Enter filename containing a certificate signing request,\nor leave blank to create one on-the-fly:",
                 default => ' ',
                 allow => \&blankOrReadable
                 );
        $certreqfile = trim($reply);
    }

    if (length($certreqfile) > 0) { # If valid CSR, read it in
        if (-r $certreqfile) {
            my $reqcmd = OPENSSL_BIN . " req -verify -noout -in $certreqfile";
            my $verify = runCmdGetStderr($reqcmd);
            if ($verify =~ /verify OK/i) {
                my $res = open(CSR,$certreqfile);
                if (defined $res) {
                    while(<CSR>) {
                        $csr .= $_;
                    }
                } else {
                    warn "Error: Unable to read CSR from file " . 
                         "'$certreqfile'." if (!$quiet);
                    $certreqfile = '';
                }
                close CSR;
            } else {
               warn "Error: Unable to verify CSR in '$certreqfile'." if
                   (!$quiet);
               $certreqfile = '';
            }
        } else {
            warn "Error: Unable to read CSR from file '$certreqfile'." if
                (!$quiet);
            $certreqfile = '';
        }
    }

    # Check if we need to create a CSR on-the-fly
    if (length($certreqfile) == 0) {

        if (exists $opts{inkey}) { # Read in private key from file
            $inkey = trim($opts{inkey});
            if (!(-r $inkey)) {
                warn "Error: Unable to read private key from file " . 
                     "'$inkey'." if (!$quiet);
                $inkey = '';
            }
        }

        if (length($inkey) == 0) { # No private key, create one instead

            if (exists $opts{outkey}) { # Verify can write out to file
                $outkey = trim($opts{outkey});
                if (!fileWriteable($outkey)) {
                    warn "Error: Unable to write private key to file " . 
                         "'$outkey'." if (!$quiet);
                    $outkey = '';
                }
            }

            # No private key output file given. Prompt for filename.
            if (length($outkey) == 0) {
                $reply = $term->get_reply(
                         prompt => 'Enter filename',
                         print_me => 'Enter filename for outputting the private key:',
                         default => 'userkey.pem',
                         allow => \&fileWriteable
                         );
                $outkey = trim($reply);
            }
        }

        my $reqcmd = OPENSSL_BIN . ' req -new -subj "/CN=ignore"';
        if (length($inkey) > 0) {
            $reqcmd .= " -key $inkey";
        } else {
            $reqcmd .= " -newkey rsa:2048 -nodes -keyout $outkey";
        }
        $csr = runCmdGetStdout($reqcmd);
        if (length($csr) == 0) {
            warn "Error: Unable to create certificate signing request. " .
                 "Aborting." if (!$quiet);
            exit 1;
        }
    }

    
    print "Using the following certificate signing request (CSR):\n$csr\n" if
        ($verbose);
}

# If user wants PKCS12 credential, prompt for password
if ($get eq 'p') {
    $getstr = 'PKCS12 credential';
    if (exists $opts{password}) {
        $passwd = $opts{password};
        if (length($passwd) < 12) {
            warn "Error: Password must be at least 12 characters long." if
                (!$quiet);
            $passwd = '';
        }
    }
    if (length($passwd) < 12) {
        while (length($passwd) < 12) {
            system('stty','-echo') if ($^O !~ /MSWin/i);
            $passwd = $term->readline('Enter a password for the PKCS12 credential: ');
            if ($^O !~ /MSWin/i) {
                system('stty','echo');
                print "\n";
            }
            if (length($passwd) < 12) {
                warn "Error: Password must be at least 12 characters long." if 
                    (!$quiet);
            }
        }
    }
}

# If getting a certificate or a credential, get the lifetime and VO
if (($get eq 'c') || ($get eq 'p')) {
    if (exists $opts{lifetime}) {
        $lifetime = 0 + $opts{lifetime}; # Convert string to number
        if ($lifetime < 0) { # Check for negative value
            $lifetime = 0;
        }
    }
    if ($lifetime == 0) {  # If no lifetime, then prompt for it
        my $maxlifetime = 9516;
        if ($get eq 'c') {
            $maxlifetime = 277;
        }
        $reply = $term->get_reply(
                 prompt => 'Enter lifetime',
                 print_me => 'Enter an integer value for the ' . $getstr .
                             ' lifetime (in hours):',
                 default => $maxlifetime,
                 allow => \&isPositiveInt
                 );
        $lifetime = 0 + $reply;
    }
    if ($lifetime > $maxlifetime) {
        warn "Warning: Maximum lifetime for $getstr is $maxlifetime hours." if
            (!$quiet);
        $lifetime = $maxlifetime;
    }

    print("The $getstr lifetime = $lifetime hours.\n") if ($verbose);

    # Check if the user specified a "--vo" command line parameter
    if (exists $opts{vo}) {
        $vo = trim($opts{vo});
        print "Using CILogon Virtual Organization '$vo'\n" if ($verbose);
    }
}

# Figure out the URL to get, either for certreq, PKCS12, or geturl
$urltoget = $geturl;
if (($get eq 'c') || ($get eq 'p')) {
    $urltoget = GET_CERT_URL;
}

# If user specified an output file for the certificate, PKCS12 credential,
# or URL, make sure that we can write to it.  Otherwise, ask where to output
# the result of the query, defaulting to STDOUT.
if (exists $opts{out}) {
    $outputfile = trim($opts{out});
    if (!fileWriteable($outputfile)) {
        warn "Error: Specified output file '$outputfile' is not writeable." if 
            (!$quiet);
        $outputfile = '';
    }
}
if (length($outputfile) == 0) {
    $reply = $term->get_reply(
             prompt => 'Enter filename',
             print_me => "Where should the $getstr be written?" ,
             default => 'STDOUT',
             allow => \&fileWriteable
             );
    $outputfile = trim($reply);
}
if ($outputfile =~ /^stdout$/i) {
    $outputfile = ''; # Empty string later means to write to STDOUT
}


#########################################################################
# At this point, we have all of the information from the user we need   #
# to do the operation. Now begins the work of communicating with the    #
# Service Provider ($urltoget) and the Identity Provider ($idpurl).     #
#########################################################################

# Request the target from the SP and include headers indicating ECP.
# Save any cookies from the IdP and/or SP in a cookie_jar.
my $ua = LWP::UserAgent->new();
my $cookie_jar = HTTP::Cookies->new();
$ua->cookie_jar($cookie_jar);
my $headers = HTTP::Headers->new();
$headers->header(Accept => HEADER_ACCEPT ,
                 PAOS   => HEADER_PAOS
                );
$ua->default_headers($headers);
print "First 'get' of ECP URL '$urltoget'... " if ($verbose);
my $response = $ua->get($urltoget);
if ($response->is_success) {
    $xmlstr = $response->decoded_content;
    if ($verbose) {
        print "Succeeded!\n";
        print "##### BEGIN SP RESPONSE #####\n";
        print "$xmlstr \n";
        print "##### END SP RESPONSE #####\n\n";
    }
} else {
    if ($verbose) {
        print "Failed! Error code: " . $response->status_line . "\n";
        print "Try \"curl -H '" . HEADER_ACCEPT . "' -H '" . HEADER_PAOS .
              "' '$urltoget'\" to see error details.\n";
    }
    warn "Error: Unable to get ECP URL '$urltoget'" if (!$quiet);
    exit 1;
}

# Get <ecp:RelayState> element
($xmlstr =~ m#(<ecp:RelayState.*</ecp:RelayState>)#i) && ($relaystate = $1);
if (!$relaystate) {
    warn "Error: No <ecp:RelayState> block in response from '$urltoget'." if 
        (!$quiet);
    exit 1;
}

# Extract the xmlns:S from the S:Envelope and put in <ecp:RelayState> block
my $xmlns = '';
($xmlstr =~ m#<S:Envelope (xmlns:[^>]*)>#) && ($xmlns = $1);
$relaystate =~ s#(xmlns:ecp=[^ ]*)#$1 $xmlns#;

# Get the responseConsumerURL
($xmlstr=~m#responseConsumerURL=\"([^\"]*)\"#i) && ($responseConsumerURL=$1);
if (!$responseConsumerURL) {
    warn "Error: No responseConsumerURL in response from '$urltoget'." if
        (!$quiet);
    exit 1;
}

# Remove the SOAP Header from the SP's response, use the SOAP Body later
if (!($xmlstr =~ s#<S:Header>.*</S:Header>##i)) {
    warn "Error: No SOAP Header in response from '$urltoget'." if (!$quiet);
    exit 1;
}

# Attempt to log in to the IdP with basic authorization
#BEGIN { $ENV{PERL_LWP_SSL_VERIFY_HOSTNAME}=0 }; # HACK FOR WINDOWS OPENSSL
$headers = HTTP::Headers->new();
$headers->authorization_basic($idpuser,$idppass);
$ua->default_headers($headers);
print "Logging in to IdP '$idpurl'... " if ($verbose);
$response = $ua->post($idpurl,Content=>$xmlstr);
if ($response->is_success) {
    $idpresp = $response->decoded_content;
    if ($verbose) {
        print "Succeeded!\n";
        print "##### BEGIN IDP RESPONSE #####\n";
        print "$idpresp\n";
        print "##### END IDP RESPONSE #####\n";
    }
} else {
    print "Failed! Error code: " . $response->status_line . "\n" if ($verbose);
    warn "Error: Unable to log in to IdP '$idpurl'" if (!$quiet);
    exit 1;
}

# Find the AssertionConsumerServiceURL from the response
($idpresp=~m#AssertionConsumerServiceURL=\"([^\"]*)\"#i) && 
    ($assertionConsumerServiceURL=$1);
if (!$assertionConsumerServiceURL) {
    warn "Error: No AssertionConsumerServiceURL in response from '$idpurl'." if
        (!$quiet);
    exit 1;
}

# Make sure responseConsumerURL and assertionConsumerServiceURL are equal
$headers = HTTP::Headers->new();
$ua->default_headers($headers);
if ($responseConsumerURL ne $assertionConsumerServiceURL) {
    warn "Error: responseConsumerURL and assertionConsumerService URL " .
         "are not equal.\n" .
         "responseConsumerURL         = '$responseConsumerURL'\n" .
         "assertionConsumerServiceURL = '$assertionConsumerServiceURL'\n" .
         "Sending SOAP fault to the Service Provider.\n";
    my $soapfault = '<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/"><S:Body><S:Fault><faultcode>S:Server</faultcode><faultstring>responseConsumerURL from SP and assertionConsumerServiceURL from IdP do not match</faultstring></S:Fault></S:Body></S:Envelope>';
    $response = $ua->post($responseConsumerURL,
        Content_Type => 'application/vnd.paos+xml',
        Content => $soapfault
    );
    # No need to check for response since we are quitting anyway.
    exit 1;
}

# Take the response from the IdP, but replace the <ecp:Response> SOAP header
# with the <ecp:RelayState> SOAP header found earlier. Then send this new
# message to the SP.
if (!($idpresp =~ s#(<soap11:Header>).*(</soap11:Header>)#$1$relaystate$2#i)) {
    warn "Error: Could not find <ecp:Response> SOAP header in the " .
         "IdP response." if (!$quiet);
    exit 1;
}

print "Contacting '$assertionConsumerServiceURL'..." if ($verbose);
$response = $ua->post($assertionConsumerServiceURL,
    Content_Type => 'application/vnd.paos+xml',
    Content => $idpresp
);
print "Done!\n" if ($verbose);
# No need to check for response. We only want the (shibboleth) cookie.

# Add a random CSRF cookie for the certificate or PKCs12 credential request.
my $cookiejar = $ua->cookie_jar;
my $uri = URI->new($urltoget);
my $randstr = join('',map { ('a'..'z', 0..9)[rand 36] } (1..10));
$cookiejar->set_cookie(1,'CSRF',$randstr,'/',$uri->host,$uri->port,1,1);

# Final communication with the original $urltoget. Should return a
# certificate, a PKCS12 credential, or the HTML of a particular URL.
print "Finally, attempting to get the $getstr..." if ($verbose);
if ($get eq 'u') {  # 'Get' the user-defined URL
    $response = $ua->get($urltoget);
} else { # Getting a certificate or credential requires 'post' for form vars
    my %formvars;
    $formvars{'CSRF'} = $randstr;
    if (length($vo) > 0) {
        $formvars{'cilogon_vo'} = $vo;
    }
    if ($get eq 'c') {
        $formvars{'submit'} = 'certreq';
        $formvars{'certreq'} = $csr;
        $formvars{'certlifetime'} = $lifetime;
    }
    if ($get eq 'p') {
        $formvars{'submit'} = 'pkcs12';
        $formvars{'p12password'} = $passwd;
        $formvars{'p12lifetime'} = $lifetime;
    }

    $response = $ua->post($urltoget,\%formvars);
}
if ($response->is_success) {
    print "Success!\n" if ($verbose);
    if (length($outputfile) > 0) {
        open(OUTFILE,">$outputfile");
        print OUTFILE $response->decoded_content;
        close OUTFILE;
        print "Output written to '$outputfile'.\n" if ($verbose);
    } else {
        print $response->decoded_content . "\n";
    }
} else {
    print "Failure! Error code: " . $response->status_line . "\n" if ($verbose);
    warn "Error: Unable to get the $getstr. Try the --verbose " .
         "command line option." if (!$quiet);
    exit 1;
}

# Made it this far means success!
exit 0;

####################
# END MAIN PROGRAM #
####################








#########################################################################
=item B<fetchIdps()>

B<Returns:> A hash of IdPs in the form $idps{'idpname'} = 'idpurl'

This subroutine fetches the list of Identity Providers from the CILogon
server, using the ECP_IDPS_URL defined at the top of this file. It returns
a hash where the keys are the "pretty print" names of the IdPs, and the
values are the actual URLs of the IdPs. 

#########################################################################
=cut
sub fetchIdps
{
    my %idps = ();
    my $content;
    my $ua = LWP::UserAgent->new();
    my $response = $ua->get(ECP_IDPS_URL);
    if ($response->is_success) {
        $content = $response->decoded_content;
    }
    if (defined($content)) {
        foreach my $line (split("\n",$content)) {
            chomp($line);
            my($idpurl,$idpname) = split('\s+',$line,2);
            $idps{$idpname} = $idpurl;
        }
    }
    return %idps;
}

#########################################################################
=item B<isValudURL($url)>

B<Parameter:> C<$url> - The URL to test for valid 'https' url.

B<Returns:> 1 if passed-in URL is valid https url, 0 otherwise.

This subroutine takes in a string representing a URL and tests to see
if it is a valid SSL url (i.e. https://..../...). If the URL is valid,
1 is return, otherwise 0 is returned.

#########################################################################
=cut
sub isValidURL
{
    my $url = shift;
    my $retval = 0;
    my $uri = URI->new($url,'https');   # Allow only 'https://'
    if ($uri->scheme) {
        $retval = 1;
    }
    return $retval;
}

#########################################################################
=item B<fileWriteable($filename)>

B<Parameter:> C<$filename> - The name of a file (specified with or without
the full path) to test for write-ability.  Can also be 'STDOUT' which
implies write to <stdout>.

B<Returns:> 1 if passed-in filename is writeable or 'STDOUT', 0 otherwise.

This subroutine takes in a string representing a filename. The filename can
be 'STDOUT', or prefixed with a directory or not (at which point the current
working directory is assumed). It checks to see if the file already exists,
and if so, is the file writeable. Otherwise, it checks the containing
directory to see if a file can be created there. If so, 1 is returned,
otherwise 0 is returned.

#########################################################################
=cut
sub fileWriteable
{
    my $filename = trim(shift);
    my $retval = 0;
    if (length($filename) > 0) {
        if ($filename =~ /^stdout$/i) {
            $retval = 1;
        } elsif (-e $filename) {
            if (-w $filename) {
                $retval = 1;
            }
        } else {
            my $dirname = dirname($filename);
            if (-w $dirname) {
                $retval = 1;
            }
        }
    }
    return $retval;
}

#########################################################################
=item B<blankOrReadable($filename)>

B<Parameter:> C<$filename> - The name of a file (possibly empty) to test for
read-ability.

B<Returns:> 1 if passed-in filename is readable or blank, 0 otherwise.

This subroutine takes in a string representing a filename. The filename can
be prefixed with a directory or not (at which point the current working
directory is assumed). It checks to see if the filename is empty or if the
file can be read. If so, 1 is returned, otherwise 0 is returned. This
subroutine is used by one of the get_reply() calls when prompting the user
for a CSR to read in, blank meaning to create a CSR on-the-fly.

#########################################################################
=cut
sub blankOrReadable
{
    my $filename = trim(shift);
    my $retval = 1;
    if ((length($filename) > 0) && (!(-r $filename))) { 
        $retval = 0;
    }
    return $retval;
}

#########################################################################
=item B<checkOpenSSL()>

B<Returns:> 1 if the OpenSSL binary is available, 0 otherwise.

This subroutine checks to see if the OpenSSL binary (specified by the
OPENSSL_BIN constant at the top of this file) is available. It actually
calls 'openssl version' to make sure that the program really is openssl.

#########################################################################
=cut
sub checkOpenSSL
{
    my $retval = 0;
    if (-x OPENSSL_BIN) {
        my $opensslver = runCmdGetStdout(OPENSSL_BIN . ' version');
        if ($opensslver =~ /^OpenSSL/i) {
            $retval = 1;
        }
    }
    return $retval;
}

#########################################################################
=item B<runCmdGetStdout($cmd)>

B<Parameter:> C<$cmd> - The command to execute.

B<Returns:> The stdout result of executing the command.

This subroutine takes in a string representing a command to execute. Just
the <stdout> of the result of running the command is returned. Taken from
http://faq.perl.org/perlfaq8.html#How_can_I_capture_ST

#########################################################################
=cut
sub runCmdGetStdout
{
    my $cmd = shift;
    my $res = '';
    open(NULL,">",File::Spec->devnull);
    my $pid = open3(gensym,\*PH,">&NULL",$cmd);
    while (<PH>) { 
        $res .= $_; 
    }
    waitpid($pid,0);
    return $res;
}

#########################################################################
=item B<runCmdGetStderr($cmd)>

B<Parameter:> C<$cmd> - The command to execute.

B<Returns:> The stderr result of executing the command.

This subroutine takes in a string representing a command to execute. Just
the <stderr> of the result of running the command is returned. Taken from
http://faq.perl.org/perlfaq8.html#How_can_I_capture_ST

#########################################################################
=cut
sub runCmdGetStderr
{
    my $cmd = shift;
    my $res = '';
    open(NULL,">",File::Spec->devnull);
    my $pid = open3(gensym,">&NULL",\*PH,$cmd);
    while (<PH>) { 
        $res .= $_; 
    }
    waitpid($pid,0);
    return $res;
}

#########################################################################
=item B<isPositiveInt($num)>

B<Parameter:> C<$num> - An integer to check for positivity.

B<Returns:> 1 if passed-in number is positive and an integer, 0 otherwise.

This subroutine takes in a number and checks to see if the number is a
positive integer. This subroutine is used by one of the get_reply() calls
when prompting the user for the lifetime of the credential.

#########################################################################
=cut
sub isPositiveInt
{
    my $num = shift;
    return (($num > 0) && ($num =~ /^\d+\z/));
}

#########################################################################
=item B<trim($str)>

B<Parameter:> C<$str> - A string to trim spaces from.

B<Returns:> The passed-in string with leading and trailing spaces removed.

This subroutine removes leading and trailing spaces from the passed-in
string. Note that the original string is not modified. Rather, a new string
without leading/trailing spaces is returned.

#########################################################################
=cut
sub trim
{
    my $str = shift;
    $str =~ s/^\s+//;
    $str =~ s/\s+$//;
    return $str;
}

#########################################################################
=item B<resetTerm()>

This subroutine is set as the interrupt handler (to catch <CTRL>+C) to 
reset the terminal to 'echo on' and non-bold text.

#########################################################################
=cut
sub resetTerm
{ 
    if ($^O !~ /MSWin/i) {
        print "\e[0m";
        system('stty','echo');
    }
    exit 1;
}



__END__

=head1 NAME

ecp.pl - Download a CILogon credential via ECP (Enhanced Client or Proxy)

=head1 SYNOPSIS

ecp.pl [options]

=head1 DESCRIPTION

B<This program> enables the fetching of a credential from the CILogon
Service via the command line (i.e. not a web browser) using the SAML ECP
(Enhanced Client or Proxy) profile. When executed without any command line
options, you will be prompted for all required information. If you use
command line options, you will be prompted for any missing information. See
the B<EXAMPLES> section below for fully-specified command line option
invocations.

The list of ECP-enabled Identity Providers (IdPs) is maintained on CILogon
servers. You can use your own IdP by specifying it, either via command line
option or when prompted during interactive program execution. If you would
like to add your IdP to the list maintained on the CILogon servers, please
send email to L<help@cilogon.org|mailto:help@cilogon.org>.

=head1 OPTIONS

=over 8

=item B<-h, --help>

Print out the help message and exit.

=item B<-V, --version>

Print out the version number and exit.

=item B<-v, -d, --verbose, --debug>

Output as much informational text as possible. Overrides B<--quiet>.

=item B<-q, --quiet>

Output as little informational text as possible. Overridden by B<--verbose>.

=item B<-l, --listidps>

Print out the list of ECP-enabled Identity Providers (IdPs) available on the
CILogon servers and exit.

=item B<-n> I<idpname>, B<--idpname> I<idpname>

Specify the name of an ECP-enabled Identity Provider (IdP) to use for
authentication.  This name must match one of the available ECP-enabled IdPs.
The program will attempt to find a partial match if you enter a substring of
the full IdP name. Use B<--listidps> to show the list of available IdPs.

=item B<-e> I<idpurl>, B<--idpurl> I<idpurl>

Specify the full URL of an ECP-enabled Identity Provider to use for
authentication. You can specify one of the available ECP-enabled IdPs, or
another ECP-enabled IdP endpoint URL. In the latter case, the ECP-enabled
IdP should be an InCommon member. Otherwise, the CILogon certificate server
may reject the request. If both B<--idpname> and B<--idpurl> are specified,
B<--idpurl> takes precedence.

=item B<-u> I<username>, B<--idpuser> I<username>

Specify the username for logging in to the ECP-enabled Identity Provider.

=item B<-p> I<password>, B<--idppass> I<password>

Specify the password for logging in to the ECP-enabled Identity Provider.

=item B<-g [cE<verbar>pE<verbar>u], --get [certE<verbar>pkcs12E<verbar>url]>

Specify the operation: fetch a certificate using a certificate signing
request, fetch a PKCS12 credential, or fetch the contents of an ECP-enabled
Service Provider URL.

=item B<-c> I<filename>, B<--certreq> I<filename>

When fetching a certificate using a certificate signing request (CSR),
read in the CSR from the specified file. If you specify C<create> as the
I<filename>, a CSR will be generated for you on-the-fly. Note that the
B<openssl> binary must be installed and available in order to generate or
verify the CSR.

=item B<-i> I<filename>, B<--inkey> I<filename>

Read in a private key from file for creating a certificate signing request
on-the-fly. If you do not specify B<--inkey>, it is assumed that you want a
private key to be generated for you. In that case, you can specify
B<--outkey> instead to write the private key to a specific file.

=item B<-k> I<filename>, B<--outkey> I<filename>

Generate a new private key and write it to file when creating a certificate
signing request (CSR) on-the-fly. Use this option if you do not have a
private key for creating the CSR. 

=item B<-t> I<hours>, B<--lifetime> I<hours>

Specify the lifetime of the certificate or credential, in integer hours. 
Maximum lifetime for a certificate is 277 hours (11.5 days). Maximum
lifetime for a PKCS12 credential is 9516 hours (13 months).

=item B<-O> I<virtorg>, B<--vo> I<virtorg>

Specify the name of a CILogon-configured "virtual organization". Note that
the program does NOT prompt for a virtual organization in interactive mode,
so you must specify it with the B<--vo> option.

=item B<-w> I<password>, B<--password> I<password>

Specify a password string to encrypt the private key of the PKCS12
credential. This password must be at least 12 characters in length.

=item B<-U> I<url>, B<--url> I<url>

When fetching the contents of an ECP-enabled Service Provider URL, specify
the URL to fetch.  Be sure to include C<http(s)://> in the URL string.

=item B<-o> I<filename>, B<--out> I<filename>

Specify the destination of the certificate, PKCS12 credential, or contents of
the specified URL.  If you specify C<STDOUT> as the I<filename>, output will
be sent to the terminal.

=back

=head1 EXIT STATUS

0 on success, >0 on error

=head1 EXAMPLES

=over

=item ecp.pl 

Execute the ECP client program in fully-interactive mode. You will be
prompted for all required information.

=item ecp.pl --listidps

Print out the list of available ECP-enabled Identity Providers. This is
useful when using the I<--idpname> command line option in later invocations.

=item ecp.pl --get cert --idpname urbana --idpuser joesmith 
             --idppass mypass --certreq create --outkey userkey.pem 
             --lifetime 240 --out usercert.pem

Get a certificate from the CILogon Service. Authenticate to the Identity
Provider at the University of Illinois at Urbana-Champaign with the username
C<joesmith> and password C<mypass>. Generate a certificate signing request
on-the-fly and output the private key to the file C<userkey.pem>. Set the
lifetime of the certificate to 240 hours (10 days). Output the fetched
certificate to the file C<usercert.pem>.

=item ecp.pl --get cert --idpname urbana --idpuser joesmith 
             --idppass mypass --certreq usercsr.pem --lifetime 168
             --out STDOUT

Get a certificate from the CILogon Service. Authenticate to the Identity
Provider at the University of Illinois at Urbana-Champaign with the username
C<joesmith> and password C<mypass>. Read in a certificate signing request
(CSR) from the file C<usercsr.pem>. (Note that no userkey.pem is involved
here because a private key is needed only for the creation of the CSR.)  Set
the lifetime of the certificate to 168 hours (1 week). Output the fetched
certificate to the terminal.

=item ecp.pl --get pkcs12 --idpname urbana --idpuser joesmith 
             --idppass mypass --password abcdefghijkl --lifetime 8766 
             --out usercred.p12

Get a PKCS12 credential from the CILogon Service.  Authenticate to the
Identity Provider at the University of Illinois at Urbana-Champaign with the
username C<joesmith> and password C<mypass>. Encrypt the private key of the
credential with the password C<abcdefghijkl>. Set the lifetime of the
credential to 8766 hours (1 year). Output the fetched credential to the file
C<usercred.p12>.

=back

=cut
