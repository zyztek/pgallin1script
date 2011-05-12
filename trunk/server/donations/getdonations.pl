#!/usr/bin/perl -w

# 
# @filename getdonations.pl
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#

use strict;
use warnings;

use LWP;
#use threads;

###############################################################################
# global stuff
###############################################################################

my $dbg = $ENV{DBG} || 0;
my $proxy_test = $ENV{TEST} || 0;
my $dont_fork = $ENV{NOFORK} || 0;
my $use_threads = $ENV{USETHREADS} || 0;
my $use_wget = $ENV{USEWGET} || 0;
my $retry = $ENV{RETRIES} || 1;
my $timeout = $ENV{TIMEOUT} || 5;
my $count = 0;
my $needed = $ENV{NEEDED} || 60; #use 60 just to be sure...
my $link = $ENV{LINK} || die "donation link needed!";

my $use_fork = ($dont_fork == 0 and $use_threads == 0 and $proxy_test == 0) ;
my $pid = 0;

if ($use_fork) {
    $pid = fork();

    if (not defined $pid) {
	print "cannot fork.\n";
	$use_fork = 0;
    } elsif ($pid != 0) {
	# parent process
	exit(0);
    }
}

my $ret = 0;
my $suc = 0;
my $f_referer = 'referers.txt';
my $f_proxy = 'proxy.txt';
my $f_useragent = 'useragents.txt';

open(FHPROXY, $f_proxy);
my (@proxylines) = <FHPROXY>;
open(FHREFERER, $f_referer);
my (@reflines) = <FHREFERER>;
open(FHUSERAGENT, $f_useragent);
my (@ualines) = <FHUSERAGENT>;

###############################################################################
# low level http hit - using wget
###############################################################################

sub getGetCmd($$$$$$$) {
    my $proxy = shift;
    my $timeout = shift;
    my $retry = shift;
    my $referer = shift;
    my $useragent = shift;
    my $link = shift;
    my $silence = shift;

    my $silence2 = $silence ? " > /dev/null 2>&1" : "";

    return "bash -c \"export http_proxy=http://$proxy/; wget -T$timeout -t$retry -O/dev/null --referer '$referer' --user-agent '$useragent' '$link'".$silence2.";\"" . $silence2 . ";";
}

sub donateWget($$$$$$$) {
    my $proxy = shift;
    my $timeout = shift;
    my $retry = shift;
    my $referer = shift;
    my $useragent = shift;
    my $link = shift;
    my $silence = shift;

    my $cmd = getGetCmd($proxy, $timeout, $retry, $referer, $useragent, $link, $silence);
    return system ($cmd);
}

###############################################################################
# low level http hit - using perl's built-in methods
###############################################################################

sub donatePerl($$$$$$$) {
    my $proxy = shift;
    my $timeout = shift;
    my $retry = shift;
    my $referer = shift;
    my $useragent = shift;
    my $link = shift;
    my $silence = shift;

    my $browser = LWP::UserAgent->new;
    $browser->proxy('http', "http://$proxy/");
    $browser->timeout($timeout);
    $browser->agent($useragent);

    my $try = 0;
    my $response;
    do {
        $response = $browser->get($link, 'Referer' => $referer);
        if (!$silence) { print $response->status_line."\n"; }
    } while (!$response->is_success && $try++ < $retry);

    return $response->is_success ? 0 : 1;
}

###############################################################################
# a HTTP hit
###############################################################################

sub donate($$$$$$$) {
    my $proxy = shift;
    my $timeout = shift;
    my $retry = shift;
    my $referer = shift;
    my $useragent = shift;
    my $link = shift;
    my $silence = shift;

    if ($use_wget) {
        return donateWget($proxy, $timeout, $retry, $referer, $useragent, $link, $silence);
    } else {
        return donatePerl($proxy, $timeout, $retry, $referer, $useragent, $link, $silence);
    }
}

###############################################################################
# test proxies
###############################################################################

sub testProxies {
    my $count = shift;
    for (my $c = 0; $c < $count; $c++) {
        my $r = $reflines[rand $#reflines];
        my $p = $proxylines[$c];
        my $u = $ualines[rand $#ualines];

        $r =~ s/[\n|\r|\t| ]//g;
        $p =~ s/[\n|\r|\t| ]//g;
        $u =~ s/[\n|\r|\t| ]//g;

        $ret = donate($p, $timeout, $retry, $r, $u, $link, 1);
	($dbg == 1) and print STDERR "test: " . ($c+1) ." / $count ### ret = " . $ret . "\n";
	if ($ret == 0) {
	    print "$p\n";
	} elsif ($ret == 2) {
	    print STDERR "CTRL-C detected!!!\n";
	    exit 128;
	}
    }
}

###############################################################################
# get us some donations ;-)
###############################################################################

sub getDonations {
    my $pid = shift;
    my $count = shift;
    my $needed = shift;
    print "\n### process:$pid ### count:$count ### needed:$needed ###\n\n";
    for (my $c = 0; $c < $count and ($suc < $needed); $c++) {
        my $r = $reflines[rand $#reflines];
        my $p = $proxylines[rand $#proxylines];
        my $u = $ualines[rand $#ualines];

        $r =~ s/[\n|\r|\t| ]//g;
        $p =~ s/[\n|\r|\t| ]//g;
        $u =~ s/[\n|\r|\t| ]//g;

	print "\n### process:$pid ### suc:$suc ### using -> proxy: $p referer: $r useragent: $u\n\n";

        $ret = donate($p, $timeout, $retry, $r, $u, $link, 0);

	print "\n### ret: $ret\n\n";

	if ($ret == 0) {
	    $suc++;
	} elsif ($ret == 2) {
	    print STDERR "CTRL-C detected!!!\n";
	    exit 128;
	}
    }
}

sub createWorkerProcesses($$$) {
    my $workers = shift;
    my $countPerWorker = shift;
    my $hitsPerWorker = shift;

    print "Creating $workers worker processes ...\n";

    for (my $c = 0; $c < $workers; $c++) {
        my $pid = fork();
        if ($pid == 0) {
            # child - do donate
            getDonations($c, $countPerWorker, $hitsPerWorker);
            exit(0);
        }
    }
}

sub createWorkerThreads($$$) {
    my $workers = shift;
    my $countPerWorker = shift;
    my $hitsPerWorker = shift;

    print "Creating $workers worker threads ...\n";

    #my @threads;
    #for (my $c = 0; $c < $workers; $c++) {
    #    push(@threads, threads->new(\&getDonations, $c, $countPerWorker, $hitsPerWorker));
    #}
    #foreach my $myThread(@threads) {
    #    $myThread->join;
    #}

    print "... worker threads finished\n";
}

###############################################################################
# main entry point
###############################################################################

if ($proxy_test == 0) {
    $count = $ENV{COUNT} || 240;
    my $hitsPerWorker = 1;
    my $countPerWorker = (($count / $needed * 15) / 10) * $hitsPerWorker;
    my $workers = ($needed + $hitsPerWorker - 1) / $hitsPerWorker;

    if ($use_threads) {
	createWorkerThreads($workers, $countPerWorker, $hitsPerWorker);
    } elsif ($use_fork) {
	createWorkerProcesses($workers, $countPerWorker, $hitsPerWorker);
    } else {
	getDonations(0, $count, $needed);
    }
} else {
    $count = $ENV{COUNT} || $#proxylines + 1;
    testProxies($count);
}
