#!/usr/bin/perl -w

# 
# @filename getuserinfo.pl
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#
use strict;
use warnings;

use LWP::Simple;

my $dbg = 0;
my $uid = $ENV{USERID} || die "hmm, uid should be given!!";
my $game = $ENV{GAME} || die "hmm, gametype should be given!!";
my $retry = $ENV{RETRIES} || 3;

my $mainurl;
my $apiurl;

if ($game eq "dossergame") {
    $mainurl = "http://www.dossergame.co.uk/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "pennergame") {
    $mainurl = "http://www.pennergame.de/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "pg_muenchen") {
    $mainurl = "http://muenchen.pennergame.de/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "pg_malle") {
    $mainurl = "http://malle.pennergame.de/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "pg_berlin") {
    $mainurl = "http://berlin.pennergame.de/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "bumrise") {
    $mainurl = "http://www.bumrise.com/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "serserionline") {
    $mainurl = "http://www.serserionline.com/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "mendigogame") {
    $mainurl = "http://www.mendigogame.es/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "clodogame") {
    $mainurl = "http://www.clodogame.fr/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "menelgame") {
    $mainurl = "http://www.menelgame.pl/";
    $apiurl = $mainurl . "dev/api/";
} else {
    die "Please specify a valid game!\n"
}

sub processApi($) {
    my @arr = @{$_[0]};
    my @parr;
    for (my $i=0; $i<=$#arr; $i++) {
	($dbg == 1 ) and print STDERR "process user id:".$arr[$i]."\n";
	my $html;
	my $furl = $apiurl . "user." . $arr[$i] . ".xml";
	($dbg == 1 ) and print STDERR $furl . "\n";
	for (my $r=$retry; $r>0 and !defined ($html); $r--) {
	    $html = get($furl);
	}
	if (!defined ($html)) {
	    ($dbg == 1 ) and print STDERR "Ohr neeee\n";
	} else {
	    ($dbg == 1 ) and print $html."\n";
	    my @points;
	    push @points, $html =~ /\<points>(.*?)\<\/points>/g;
	    my @names;
	    push @names, $html =~ /\<name>(.*?)\<\/name>/g;
	    my @ids;
	    push @ids, $html =~ /\<id>(.*?)\<\/id>/g;
	    my %hash = 
	      (id => $arr[$i],
	       points => $points[0],
	       name => $names[0],
	       gid => $ids[1],
	       gname => $names[1]);
	    push(@parr, \%hash);
	}
    }
    return @parr;
}

sub printarr($) {
    my @arr = @{$_[0]};
    for (my $i=0; $i<=$#arr; $i++) {
	my %hash = %{$arr[$i]};
	print 'name:' . $hash{name} . ' id:' . $hash{id} . ' points:' . $hash{points} . "\n";
    }
}

sub printarr_table($) {
    my @arr = @{$_[0]};
    for (my $i=0; $i<=$#arr; $i++) {
	my %hash = %{$arr[$i]};
	print '<td>' . $game . '</td><td><a target="_blank" href="http://anonym.to/?' . $mainurl . 'profil/id:' . $hash{id} . '/">'. $hash{name} . '</a></td><td>' . $hash{id} . '</td><td>' . $hash{points} . "</td>\n";
    }
}

my @pennerids;
$pennerids[0] = $uid;

my @arr = processApi(\@pennerids);
printarr_table(\@arr);


