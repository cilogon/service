#!/usr/bin/env perl

#########################################################################
#                                                                       #
# Script      : ecp.pl                                                  #
# Authors     : Terry Fleury <tfleury@illinois.edu>                     #
# Create Date : July 06, 2011                                           #
# Last Update : December 12, 2014                                       #
#                                                                       #
# This PERL script allows a user to get an end-user X.509 certificate   #
# or PKCS12 credential from the CILogon Service. It can also get the    #
# contents of any ECP-enabled Service Provider (SP). The script can be  #
# used as an example of how a SAML ECP client works.                    #
#                                                                       #
# Studying this script is not an acceptable replacement for reading     #
# Draft 02 of the ECP profile [ECP] available at:                       #
# http://wiki.oasis-open.org/security/SAML2EnhancedClientProfile        #
#                                                                       #
# This script assumes that the server hosting the IdP has been          #
# configured to require a type of Basic Auth (username and password)    #
# for the ECP location.                                                 #
#                                                                       #
#########################################################################
#                                                                       #
# NOTES ON THE CONSTANTS BELOW:                                         #
#                                                                       #
# * You must set the OPENSSL_BIN constant below to be the full path     #
#   of the "openssl" binary on your system.                             #
#                                                                       #
# * The ECP_IDPS_URL points to a text file listing ECP-enabled IdPs.    #
#   This file is maintained by CILogon. If you wish to use your own     #
#   list of ECP-enabled IdPs, the format of the file is very simple:    #
#       https://example.com/idp/profile/SAML2/SOAP/ECP Example IdP      #
#   The first string is the IdP's ECP endpoint. Then a space. The rest  #
#   of the line is a text description which will appear in the list of  #
#   ECP-enabled IdPs. Put one entry per line. For a local file, you     #
#   would set "ECP_IDPS_URL => 'file:///path/to/local/file.txt'".       #
#                                                                       #
# * The DEFAULT_IDP is the pretty-print name of the IdP that will be    #
#   selected by default.                                                #
#                                                                       #
# * GET_CERT_URL is the CILogon endpoint for fetching a certificate or  #
#   PKCS12 credential.                                                  #
#                                                                       #
# * The ECP_MAPFILE is the location on disk of a file that can map      #
#   PAM_USER names to IDPUSER names. This is used when the "--pam"      #
#   command line option is specified. The file consists of lines where  #
#   the first entry is the PAM_USER username, and the rest of the line  #
#   consists of command line options that should override the default   #
#   options. For example, to map PAM_USER username of jsmith to         #
#   the ProtectNetwork IdP username joesmith, add the following line    #
#   to the ecp-mapfile:                                                 #
#       jsmith --idpuser joesmith --idpname ProtectNetwork              #
#                                                                       #
#########################################################################

use constant { 
    OPENSSL_BIN  =>'/usr/bin/openssl' ,  ### CHANGE THIS IF NECESSARY
    ECP_IDPS_URL =>'https://test.cilogon.org/include/ecpidps.txt' ,
    DEFAULT_IDP  =>'University of Illinois at Urbana-Champaign' ,
    GET_CERT_URL =>'https://test.cilogon.org/secure/getcert/' ,
    ECP_MAPFILE  =>'/etc/ecp-mapfile' ,
    HEADER_ACCEPT=>'text/html; application/vnd.paos+xml' ,
    HEADER_PAOS  =>'ver="urn:liberty:paos:2003-08";"urn:oasis:names:tc:SAML:2.0:profiles:SSO:ecp"' ,
};

######################
# BEGIN MAIN PROGRAM #
######################

our $VERSION = "0.025";
$VERSION = eval $VERSION;

use strict;
use Term::ReadLine;
use Term::UI;
use Getopt::Long qw(:config bundling);
use Pod::Usage;
use LWP;
use Crypt::SSLeay;
use HTTP::Cookies;
use URI;
use IPC::Open3;
use File::Basename;
use File::Spec;
use File::Temp qw(tempfile);
use Symbol qw(gensym);

# Handle <Ctrl>+C to reset the terminal to non-bold text
$SIG{INT} = \&resetTerm;

# Declare variables for command line options
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
my $genrsa = '';
my $certreq = '';
my $inkey = '';
my $outkey = '';
my $outkeyfh;
my $outkeystdout = 0;
my $keyfile = '';
my $csr = '';
my $passwd = '';
my $tfpass = '';
my $lifetime = 0;
my $vo = '';
my $outputfile = '';
my $urltoget = '';
my $xmlstr = '';
my $idpresp = '';
my $relaystate = '';
my $responseConsumerURL = '';
my $assertionConsumerServiceURL = '';

# Scan @ARGV for valid command line options
%opts = getCmdLineOpts();

# If the user asked for help, print it and then exit.
if (exists $opts{help}) {
    pod2usage(-verbose=>2) && exit;
}

# If the user requested version number, print it and then exit.
if (exists $opts{version}) {
    print "ecp.pl version '" . main->VERSION . "'\n";
    exit;
}
 
# Check if the user wants to bypass SSL hostname verification
if (exists $opts{skipssl}) {
    $ENV{PERL_LWP_SSL_VERIFY_HOSTNAME} = 0;
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

# If the user entered --pam, then check we are doing pam_exec. Make sure
# that the PAM_TYPE is "auth". Check for the existence of the ECP_MAPFILE.
# If found, check for the PAM_USER username. If found, manipulate @ARGV with
# the configured command line options and call GetOptions again.
if ((exists $opts{pam}) && ($ENV{PAM_TYPE} eq 'auth')) {
    my $res = open(MAP,ECP_MAPFILE);
    if (defined $res) {
        my %ecphash = map { split(/\s+/,$_,2); } <MAP>;
        close MAP;
        if (defined $ecphash{$ENV{PAM_USER}}) {
            @ARGV = ();
            @ARGV = split(/\s+/,$ecphash{$ENV{PAM_USER}});
            %opts = ();
            %opts = getCmdLineOpts();
        }
    }
    $opts{proxyfile} = 1;
    $opts{certreq} = "create";
    if (!exists $opts{lifetime}) {
        $opts{lifetime} = 277;
    }
    if (!exists $opts{idpuser}) {
        $opts{idpuser} = $ENV{PAM_USER};
    }
    if (!exists $opts{idppass}) {
        my $passwd = <STDIN>;
        chop $passwd;
        $opts{idppass} = $passwd;
    }
}

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

# If no valid --idpurl given, check for a valid --idpname, use partial match
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
    # Find the array position of the default IdP
    my %idpidx;
    @idpidx{@idpnames} = (0..$#idpnames);
    my $defidp = $idpidx{ DEFAULT_IDP.'' };
    push(@idpnames,'Specify the ECP endpoint URL of another ECP-enabled IdP');
    $reply = $term->get_reply(
             prompt   => 'Choose',
             print_me => 'Select an Identity Provider (IdP):',
             choices  => \@idpnames,
             default  => $idpnames[$defidp]
             );

    if ($reply eq 'Specify the ECP endpoint URL of another ECP-enabled IdP') {
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

# If the '--proxyfile' command line option is set, two things happen:
# (1) the Globus proxy filename is used as the $outputfile, and
# (2) the '--get' operation is set to 'cert'.
# Thus, any other '--out' or '--get' command line parameters are ignored.
if (exists $opts{proxyfile}) {
    $opts{out} = getProxyFilename();
    $opts{get} = 'c';
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
             prompt   => 'Choose',
             print_me => 'What do you want to get?',
             choices  => \@choices,
             default  => $choices[0]
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
                 prompt   => 'Enter filename',
                 print_me => 'Enter filename containing a certificate ' .
                             'signing request,' . "\n" . 
                             'or leave blank to create one on-the-fly:',
                 default  => ' ',
                 allow    => \&blankOrReadable
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

        if (length($inkey) > 0) { # Read in key from specified file
            $keyfile = $inkey;
        } else { # No private key to read in, create one instead
            if (exists $opts{outkey}) { # Verify can write out to file
                $outkey = trim($opts{outkey});
                if (!fileWriteable($outkey)) {
                    warn "Error: Unable to write private key to file " . 
                         "'$outkey'." if (!$quiet);
                    $outkey = '';
                }
            }

            # No private key output file specified. If '--proxyfile' wasn't
            # specified, prompt for outkey filename.
            if ((length($outkey) == 0) && (!exists $opts{proxyfile})) {
                $reply = $term->get_reply(
                         prompt   => 'Enter filename',
                         print_me => 'Enter filename for outputting the private key:',
                         default  => 'userkey.pem',
                         allow    => \&fileWriteable
                         );
                $outkey = trim($reply);
            }

            # If still no outkey filename and '--proxyfile' was specified,
            # or if STDOUT was given as the outkey filename, write the 
            # key to a temp file.
            if (((length($outkey) == 0) && (exists $opts{proxyfile})) || 
                ($outkey =~ /^(stdout|-)$/i)) {
                if ($outkey =~ /^(stdout|-)$/i) {
                    $outkeystdout = 1; # Print key to stdout at the very end
                }
                ($outkeyfh,$outkey) = 
                    tempfile(UNLINK=>1,TMPDIR=>1,SUFFIX=>'.pem');
            } else {
                open($outkeyfh,">",$outkey);
            }

            my $genrsacmd = OPENSSL_BIN . ' genrsa 2048';
            $genrsa = runCmdGetStdout($genrsacmd);
            if (length($genrsa) > 0) {
                print $outkeyfh $genrsa;
                close $outkeyfh;
                chmod 0600, $outkey;
                $keyfile = $outkey;
            } else {
                warn "Error: Unable to create private key in '$outkey'. " .
                     "Aborting." if (!$quiet);
                exit 1;
            }
        }

        my $reqcmd = OPENSSL_BIN . ' req -new -subj "/CN=ignore"' .
                     " -key $keyfile";
        $csr = runCmdGetStdout($reqcmd);
        if (length($csr) == 0) {
            warn "Error: Unable to create certificate signing request. " .
                 "Aborting." if (!$quiet);
            exit 1;
        }
    }

    print "Using the following certificate signing request (CSR):\n$csr" if
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

# If getting a certificate or a credential, get the lifetime, 
# and check for VO and two-factor passcode command line options
if (($get eq 'c') || ($get eq 'p')) {
    my $maxlifetime = (($get eq 'c') ? 277 : 9516);
    if (exists $opts{lifetime}) {
        $lifetime = 0 + $opts{lifetime}; # Convert string to number
        if ($lifetime < 0) { # Check for negative value
            $lifetime = 0;
        }
    }
    if ($lifetime == 0) {  # If no lifetime, then prompt for it
        $reply = $term->get_reply(
                 prompt   => 'Enter lifetime',
                 print_me => 'Enter an integer value for the ' . $getstr .
                             ' lifetime (in hours):',
                 default  => $maxlifetime,
                 allow    => \&isPositiveInt
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
        print "Using CILogon Virtual Organization '$vo'.\n" if ($verbose);
    }

    # Check if the user specified a "--twofactor" command line parameter
    if (exists $opts{twofactor}) {
        $tfpass = trim($opts{twofactor});
        print "Using two-factor passcode '$tfpass'.\n" if ($verbose);
    }
}

# Figure out the URL to get, either for certreq, PKCS12, or geturl
$urltoget = $geturl;
if (($get eq 'c') || ($get eq 'p')) {
    $urltoget = GET_CERT_URL;
}

# If user specified an output file for the certificate, PKCS12 credential,
# or URL, make sure that we can write to it.  Otherwise, ask where to output
# the result of the query.
if (exists $opts{out}) {
    $outputfile = trim($opts{out});
    if (!fileWriteable($outputfile)) {
        warn "Error: Specified output file '$outputfile' is not writeable." if 
            (!$quiet);
        $outputfile = '';
    }
}
if (length($outputfile) == 0) {
    my $defaultout = (($get eq 'p') ? 'usercred.p12' : 'STDOUT');
    $reply = $term->get_reply(
             prompt   => 'Enter filename',
             print_me => "Where should the $getstr be written?",
             default  => $defaultout,
             allow    => \&fileWriteable
             );
    $outputfile = trim($reply);
}
if ($outputfile =~ /^(stdout|-)$/i) {
    $outputfile = ''; # Empty string later means to write to STDOUT
}


#########################################################################
# At this point, we have all of the information from the user we need   #
# to do the operation. Now begins the work of communicating with the    #
# Service Provider ($urltoget) and the Identity Provider ($idpurl).     #
#########################################################################

# Request the target from the SP and include headers indicating ECP.
# The SP should initially respond with a SOAP message.
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
        print "##### END SP RESPONSE #####\n";
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

# Get <ecp:RelayState> element from the SP's SOAP response
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
$headers = HTTP::Headers->new();
$headers->authorization_basic($idpuser,$idppass);
$ua->default_headers($headers);
print "Logging in to IdP '$idpurl' with \n$xmlstr\n... " if ($verbose);
$response = $ua->post($idpurl,Content_Type=>'text/xml',Content=>$xmlstr);
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

# Find the AssertionConsumerServiceURL from the IdP's response
($idpresp=~m#AssertionConsumerServiceURL=\"([^\"]*)\"#i) && 
    ($assertionConsumerServiceURL=$1);
if (!$assertionConsumerServiceURL) {
    warn "Error: No AssertionConsumerServiceURL in response from '$idpurl'." if
        (!$quiet);
    exit 1;
}

# Make sure responseConsumerURL and assertionConsumerServiceURL are equal.
# If not, send SOAP fault to the SP and exit.
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
# message to the SP's assertionConsumerServiceURL.
if (!($idpresp =~ s#(<soap11:Header>).*(</soap11:Header>)#$1$relaystate$2#i)) {
    warn "Error: Could not find <ecp:Response> SOAP header in the " .
         "IdP response." if (!$quiet);
    exit 1;
}

print "Contacting '$assertionConsumerServiceURL' with \n$idpresp\n..." if ($verbose);
$response = $ua->post($assertionConsumerServiceURL,
    Content_Type => 'application/vnd.paos+xml',
    Content => $idpresp
);
print "Done!\n" if ($verbose);
# No need to check for response. We only want the (shibboleth) cookie.

# Add a random CSRF cookie for the certificate or PKCs12 credential request.
# This random CSRF value must also be posted to the CILogon service (as a
# <form> value) to pass the CILogon Service's CSRF check.
my $cookiejar = $ua->cookie_jar;
my $uri = URI->new($urltoget);
my $randstr = join('',map { ('a'..'z', 0..9)[rand 36] } (1..10));
$cookiejar->set_cookie(1,'CSRF',$randstr,'/',$uri->host,$uri->port,1,1);

# Final communication with the original $urltoget. Should return a
# certificate, a PKCS12 credential, or the HTML of a particular URL.
print "Finally, attempting to get the $getstr..." if ($verbose);
my $authOK = 0;
my %formvars;
do {
    if ($get eq 'u') {  # 'Get' the user-defined URL
        $response = $ua->get($urltoget);
    } else { # Getting a certificate or credential requires 'post' for form vars
        $formvars{'CSRF'} = $randstr; # Add CSRF <form> value to match cookie
        if (length($vo) > 0) {
            $formvars{'cilogon_vo'} = $vo;
        }
        if (length($tfpass) > 0) {
            $formvars{'tfpasscode'} = $tfpass;
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
        $authOK = 1;
        print "Success!\n" if ($verbose);
        if (length($outputfile) > 0) {
            # If a certificate was fetched (not a PKCS12 or other) and 
            # either (a) the outputfile is the same as keyfile OR (b) the
            # user specified the '--proxyfile' command line option, read in
            # the keyfile, then output cert followed by contents of keyfile
            # so that cert is before key in the resulting file.
            my $keystr = '';
            if ($get eq 'c') {
                if (($outputfile eq $keyfile) || (exists $opts{proxyfile})) {
                    my $res = open(KEYFILE,$keyfile);
                    if (defined $res) {
                        while(<KEYFILE>) {
                            $keystr .= $_;
                        }
                    } else { # This shouldn't happen, but just in case.
                        warn "Error: Unable to read key from file " . 
                             "'$keyfile'." if (!$quiet);
                        $keystr = '';
                    }
                    close KEYFILE;
                }
            }
            open(OUTFILE,">$outputfile");
            print OUTFILE $response->decoded_content;
            # If we read in a key file, write it out after the cert.
            if (length($keystr) > 0) {
                print OUTFILE "\n$keystr";
            }
            close OUTFILE;
            # For Globus proxy file, max permissions is 600
            if (exists $opts{proxyfile}) {
                chmod 0600, $outputfile;
            }
            print "Output written to '$outputfile'.\n" if ($verbose);
        } else {
            print $response->decoded_content . "\n";
        }
        if ($outkeystdout == 1) {
            print $genrsa;
        }
    } else {
        # Check for "401 Unauthorized", which means two-factor is enabled
        if ($response->code == 401) {
            # Two-factor prompt is in the "realm" field. Must urlDecode it.
            my $realm;
            my $headerauth = $response->header('WWW-Authenticate');
            ($headerauth =~ /realm="(.*)"$/) && ($realm = urlDecode($1));

            # Prompt for the passcode
            $tfpass = '';
            print "\n" . $response->message . ".\n" . $realm . "\n";
            while (length($tfpass) == 0) {
                $tfpass = trim($term->readline());
                if (length($tfpass) == 0) {
                    warn "Error: Passcode cannot be empty." if (!$quiet);
                }
            }
            # Since $authOK is still false, loop to try to get the URL again
        } else {
            # Some other server error code = failure
            if ($verbose) {
                print "Failure! Error code: " . $response->status_line . "\n";
                if (length($response->decoded_content) > 0) {
                    print $response->decoded_content . "\n";
                }
            }
            warn "Error: Unable to get the $getstr. Try the --verbose " .
                 "command line option." if (!$quiet);
            exit 1;
        }
    }
} until ($authOK);

# Made it this far means success!
exit 0;

####################
# END MAIN PROGRAM #
####################






#########################################################################
# Subroutine: getCmdLineOpts()                                          #
# Returns   : A hash of command line options read from @ARGV using      #
#             GetOptions() (from Getopt::Long).                         #
# This subroutine scans the @ARGV array for command line options and    #
# return any found in a hash. This is a function since GetOptions       #
# needs to be called more than once in the main program.                #
#########################################################################
sub getCmdLineOpts
{
    my %options = ();
    GetOptions(\%options, 'help|h|?',
                          'verbose|debug|v|d',
                          'version|V',
                          'quiet|q',
                          'skipssl|s',
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
                          'twofactor|T=s',
                          'proxyfile|1',
                          'pam|m',
                          'url|U=s') or pod2usage(-verbose=>1) && exit;
    return %options;
}

#########################################################################
# Subroutine: fetchIdps()                                               #
# Returns   : A hash of IdPs in the form $idps{'idpname'} = 'idpurl'    #
# This subroutine fetches the list of Identity Providers from the       #
# CILogon server, using the ECP_IDPS_URL defined at the top of this     #
# file. It returns a hash where the keys are the "pretty print" names   #
# of the IdPs, and the values are the actual URLs of the IdPs.          #
#########################################################################
sub fetchIdps
{
    my %idps = ();
    my $content;
    my $ua = LWP::UserAgent->new();
    my $response = $ua->get(ECP_IDPS_URL);
    if ($response->is_success) {
        $content = $response->decoded_content;
    } else {
        warn $response->status_line;
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
# Subroutine: isValudURL($url)                                          #
# Parameter : $url - The URL to test for valid 'https' url.             #
# Returns   : 1 if passed-in URL is valid https url, 0 otherwise.       #
# This subroutine takes in a string representing a URL and tests to see #
# if it is a valid SSL url (i.e. https://..../...). If the URL is       #
# valid, 1 is return, otherwise 0 is returned.                          #
#########################################################################
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
# Subroutine: fileWriteable($filename)                                  #
# Parameter : $filename - The name of a file (specified with or without #
#             the full path) to test for write-ability.  Can also be    #
#             'STDOUT' or '-' which imply write to <stdout>.            #
# Returns   : 1 if passed-in filename is writeable or 'STDOUT'/'-',     #
#             0 otherwise.                                              #
# This subroutine takes in a string representing a filename. The        #
# filename can be 'STDOUT' or '-', or prefixed with a directory or not  #
# (at which point the current working directory is assumed). It checks  #
# to see if the file already exists, and if so, is the file writeable.  #
# Otherwise, it checks the containing directory to see if a file can be #
# created there. If so, 1 is returned, otherwise 0 is returned.         #
#########################################################################
sub fileWriteable
{
    my $filename = trim(shift);
    my $retval = 0;
    if (length($filename) > 0) {
        if ($filename =~ /^(stdout|-)$/i) {
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
# Subroutine: blankOrReadable($filename)                                #
# Parameter : $filename - The name of a file (possibly empty) to test   #
#             for read-ability.                                         #
# Returns   : 1 if passed-in filename is readable or blank,             #
#             0 otherwise.                                              #
# This subroutine takes in a string representing a filename. The        #
# filename can be prefixed with a directory or not (at which point the  #
# current working directory is assumed). It checks to see if the        #
# filename is empty or if the file can be read. If so, 1 is returned,   #
# otherwise 0 is returned. This subroutine is used by one of the        #
# get_reply() calls when prompting the user for a CSR to read in,       #
# blank meaning to create a CSR on-the-fly.                             #
#########################################################################
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
# Subroutine: checkOpenSSL()                                            #
# Returns   : 1 if the OpenSSL binary is available, 0 otherwise.        #
# This subroutine checks to see if the OpenSSL binary (specified by the #
# OPENSSL_BIN constant at the top of this file) is available. It        #
# actually calls 'openssl version' to make sure that the program        #
# really is openssl.                                                    #
#########################################################################
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
# Subroutine: runCmdGetStdout($cmd)                                     #
# Parameter : $cmd - The command to execute.                            #
# Returns   : The stdout result of executing the command.               #
# This subroutine takes in a string representing a command to execute.  #
# Only the <stdout> of the result of running the command is returned.   #
# Taken from http://faq.perl.org/perlfaq8.html#How_can_I_capture_ST     #
#########################################################################
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
# Subroutine: runCmdGetStderr($cmd)                                     #
# Parameter : $cmd - The command to execute.                            #
# Returns   : The stderr result of executing the command.               #
# This subroutine takes in a string representing a command to execute.  #
# Only the <stderr> of the result of running the command is returned.   #
# Taken from http://faq.perl.org/perlfaq8.html#How_can_I_capture_ST     #
#########################################################################
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
# Subroutine: isPositiveInt($num)                                       #
# Parameter : $num - An integer to check for positivity.                #
# Returns   : 1 if passed-in number is positive and an integer,         #
#             0 otherwise.                                              #
# This subroutine takes in a number and checks to see if the number is  #
# a positive integer. This subroutine is used by one of the get_reply() #
# calls when prompting the user for the lifetime of the credential.     #
#########################################################################
sub isPositiveInt
{
    my $num = shift;
    return (($num > 0) && ($num =~ /^\d+\z/));
}

#########################################################################
# Subroutine: trim($str)                                                #
# Parameter : $str - A string to trim spaces from.                      #
# Returns   : The passed-in string with leading and trailing spaces     #
#             removed.                                                  #
# This subroutine removes leading and trailing spaces from the          #
# passed-in string. Note that the original string is not modified.      #
# Rather, a new string without leading/trailing spaces is returned.     #
#########################################################################
sub trim
{
    my $str = shift;
    $str =~ s/^\s+//;
    $str =~ s/\s+$//;
    return $str;
}

#########################################################################
# Subroutine: resetTerm()                                               #
# This subroutine is set as the interrupt handler (to catch <CTRL>+C)   #
# to reset the terminal to 'echo on' and non-bold text.                 #
#########################################################################
sub resetTerm
{ 
    if ($^O !~ /MSWin/i) {
        print "\e[0m";
        system('stty','echo');
    }
    exit 1;
}

#########################################################################
# Subroutine: urlDecode()                                               #
# Parameter : A string encoded with PHP 'urlencode()'                   #
# Returns   : The decoded string, with '+' replaced by ' ' (space), and #
#             URL entities decoded to their base representations.       #
# This subroutine decodes a string that was encoded with the PHP        #
# function 'urlencode'.                                                 #
#########################################################################
sub urlDecode {
    my $url = shift;
    $url =~ tr/+/ /;
    $url =~ s/%([a-fA-F0-9]{2,2})/chr(hex($1))/eg;
    $url =~ s/<!--(.|\n)*-->//g;
    return $url;
}

#########################################################################
# Subroutine: getProxyFilename()                                        #
# Returns   : The full path and filename of the Globus credential file. #
# This subroutine calculates the full path and filename where Globus    #
# would check for a credential. It first checks the X509_USER_PROXY     #
# environment variable. If not set, it tries to use the UID for the     #
# user in the system temporary directory (expect for Win32 systems).    #
# If still not set, it uses the username in the system temporary        #
# directory.                                                            #
#########################################################################
sub getProxyFilename
{
    my $retval    = '';
    my $proxyname = "x509up_u";
    my $realuid   = $<;
    delete $ENV{'TMPDIR'};
    my $tmpdir    = File::Spec->tmpdir();

    # First, check the environment variable X509_USER_PROXY
    my $envvalue = $ENV{'X509_USER_PROXY'};
    if (length($envvalue) > 0) {
        $retval = $envvalue;
    } 
    
    # Next, try the temp directory plus UID for non-Win32 systems
    # (Can't do this on Win32 systems since $< always returns 0)
    if ((length($retval) == 0) && ($^O ne 'MSWin32')) {
        $retval = File::Spec->catfile($tmpdir,$proxyname.$realuid);
    }

    # As a last resort, use temp directory plus username
    if (length($retval) == 0) {
        my $username = lc(getlogin || getpwuid($realuid) || 'nousername');
        $retval = File::Spec->catfile($tmpdir,$proxyname.'_'.$username);
    }

    return $retval;
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

=item B<-s, --skipssl>

Do not do SSL/HTTPS hostname verification for the Identity Provider and the
Service Provider. This is useful if your OpenSSL CA certificate directory
is not configured, or if you use the B<--get url> option to fetch an
C<https> URL protected by a self-signed SSL server certificate.

=item B<-l, --listidps>

Print out the list of ECP-enabled Identity Providers (IdPs) available on the
CILogon servers and exit.

=item B<-n> I<idpname>, B<--idpname> I<idpname>

Specify the name of an ECP-enabled Identity Provider (IdP) to use for
authentication.  This name must match one of the available ECP-enabled IdPs.
The program will attempt to find a partial match if you enter a substring of
the full IdP name. Use B<--listidps> to show the list of available IdPs.

=item B<-e> I<idpurl>, B<--idpurl> I<idpurl>

Specify the full ECP endpoint URL of an ECP-enabled Identity Provider (IdP)
to use for authentication. You can specify the URL of one of the available
ECP-enabled IdPs, or another ECP-enabled IdP endpoint URL. In the latter
case, the ECP-enabled IdP should be an InCommon member. Otherwise, the
CILogon certificate server may reject the request. If both B<--idpname> and
B<--idpurl> are specified, B<--idpurl> takes precedence.

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

When creating a certificate signing request (CSR) on-the-fly, generate a new
private key and write it to file . Use this option if you do not have a
private key for creating the CSR. If you specify C<STDOUT> or C<-> as the
I<filename>, output will be sent to the terminal (after the certificate).
Note that this option is not necessary when using the B<--proxyfile> option
since the key will be written to the resulting Globus proxy file.

=item B<-t> I<hours>, B<--lifetime> I<hours>

Specify the lifetime of the certificate or credential, in integer hours. 
Maximum lifetime for a certificate is 277 hours (11.5 days). Maximum
lifetime for a PKCS12 credential is 9516 hours (13 months).

=item B<-O> I<virtorg>, B<--vo> I<virtorg>

Specify the name of a CILogon-configured "virtual organization". Note that
the program does NOT prompt for a virtual organization in interactive mode,
so you must specify it with the B<--vo> command line option.

=item B<-w> I<password>, B<--password> I<password>

Specify a password string to encrypt the private key of the PKCS12
credential. This password must be at least 12 characters in length.

=item B<-T> I<passcode>, B<--twofactor> I<passcode>

Specify a passcode for two-factor authentication. If two-factor
authentication had been previously enabled for your account (via the web
interface), use this value to validate the two-factor authentication step.
If you specify '0' (zero) as the I<passcode>, two-factor authentication will 
be disabled for your account.

=item B<-U> I<url>, B<--url> I<url>

When fetching the contents of an ECP-enabled Service Provider URL, specify
the URL to fetch.  Be sure to include C<http(s)://> in the URL string.

=item B<-o> I<filename>, B<--out> I<filename>

Specify the destination of the certificate, PKCS12 credential, or contents of
the specified URL.  If you specify C<STDOUT> or C<-> as the I<filename>,
output will be sent to the terminal.

=item B<-1>, B<--proxyfile>

This option will write the certificate and key to the Globus proxy
credential filename. This is typically something like I</tmp/x509up_u500> on
*nix systems or I<%TEMP%\x509_up_u_johndoe> on Windows systems. If the
environment variable X509_USER_PROXY is set, that value will be used
instead. Specifying this option overrides the B<--out> option, and sets the
B<--get cert> option. Note that the program does not prompt for the
B<--proxyfile> option, so you must specify it on the command line.

=item B<-m>, B<--pam>

This option can be used by the PAM (Pluggable Authentication Module)
system's B<pam_exec> module to authenticate a user using ECP. Using the
B<--pam> command line option automatically enables the B<--proxyfile>,
B<--certreq> C<create>, and B<--lifetime> C<277> command line options. The
B<--idpuser> I<username> value is set by the B<PAM_USER> environment
variable. The B<--idppass> I<password> value is read from STDIN (i.e., the
user is prompted for the IdP password).  One of B<--idpname> or B<--idpurl>
option MUST be specified in the pam.d configuration file. An example PAM
configuration line:

=over

auth sufficient pam_exec.so expose_authtok /usr/local/bin/ecp.pl --pam --idpurl https://shibboleth.illinois.edu/idp/profile/SAML2/SOAP/ECP

=back

C<auth> is the only supported PAM_TYPE. The C<expose_authtok> option is
mandatory to prompt the user for a password and give the ecp.pl script
access to that password. In this example, an ECP endpoint is specified using
the B<--idpurl> command line option. 

The B<--pam> option also supports an optional C<ecp-mapfile> (defaults to 
I</etc/ecp-mapfile>) which can override the command line options specified in
the pam.d configuration file on a user-by-user basis. This can be used to
map the local B<PAM_USER> username to a different IdP URL and IdP username,
for example. This file contains lines where the first entry of the line is
the B<PAM_USER> username, and the rest of the line contains command line
options for that user. For example, to map the local username jsmith to the
ProtectNetwork IdP username joesmith, add the following line to
I</etc/ecp-mapfile>:

=over

jsmith --idpname ProtectNetwork --idpuser joesmith

=back

Note that if the B<PAM_USER> name is found in the ecp-mapfile, one of
B<--idpname> or B<--idpurl> MUST be specified since all options from the
pam.d configuration file are ignored.

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

=item ecp.pl --proxyfile --idpname urbana --certreq create --lifetime 12

Get a certificate from the CILogon Service. Authenticate to the Identity
Provider at the University of Illinois at Urbana-Champaign, prompting the
user for username and password. Generate a certificate signing request
on-the-fly. Set the lifetime of the certificate to 12 hours. Output the
fetched certificate and private key to the Globus proxy file location (e.g.,
/tmp/x509up_u500).

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
