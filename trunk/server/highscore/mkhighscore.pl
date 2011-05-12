#!/usr/bin/perl -w
# 
# @filename mkhighscore.pl
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#
use strict;
use warnings;

use LWP::Simple;

my $dbg = $ENV{DBG} || 0;
my $retry = $ENV{RETRIES} || 3;
my $pages = $ENV{PAGES} || 150;
my $game = $ENV{GAME} || "dossergame";

my $mainurl;
my $hsurl;
my $apiurl;

if ($game eq "dossergame") {
    $mainurl = "http://www.dossergame.co.uk/";
    $hsurl = $mainurl . "highscore/user/";
    $apiurl = $mainurl . "dev/api/";
} elsif ($game eq "pennergame") {
    $mainurl = "http://www.pennergame.de/";
    $hsurl = $mainurl . "highscore/user/";
    $apiurl = $mainurl . "dev/api/";
} else {
    die "Please specify a valid game!\n"
}

my $filename = $game."_highscore";

sub processHighscore($) {
    my $pages = shift;
    my @arr;
    for (my $i=1; $i<=$pages; $i++) {

	my @ids;
	($dbg == 1 ) and print STDERR "process highscore page:".$i."\n";
	my $html;
	for (my $r=$retry; $r>0 and !defined ($html); $r--) {
	    $html = get($hsurl . $i . "/");
	}
	if (!defined ($html)) {
	    ($dbg == 1 ) and print STDERR "Ohr neeee\n";
	} else {
	    push @ids, $html=~ /\/profil\/id:(.*?)\//g;
	    @arr =  (@arr, @ids);
	}
    }
    return @arr;
}

sub processApi($) {
    my @arr = @{$_[0]};
    my @parr;
    for (my $i=0; $i<=$#arr; $i++) {
	($dbg == 1 ) and print STDERR "process user id:".$arr[$i]."\n";
	my $html;
	for (my $r=$retry; $r>0 and !defined ($html); $r--) {
	    $html = get($apiurl . "user." . $arr[$i] . ".xml");
	}
	if (!defined ($html)) {
	    ($dbg == 1 ) and print STDERR "Ohr neeee\n";
	} else {
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

sub printarr_json($) {
    my @arr = @{$_[0]};
    open(OUTFILE, ">highscore.json.tmp") || die "Could not create highscore.json\n";
    print OUTFILE '{"highscore": [';
    for (my $i=0; $i<=$#arr; $i++) {
	my %hash = %{$arr[$i]};
	print OUTFILE '{"id":' . $hash{id} . ',"p":' . $hash{points} . '}';
	if ($i<=$#arr) {
	    print OUTFILE ',';
	}
    }
    print OUTFILE ']}' . "\n";
    close(OUTFILE);
    system("mv -f highscore.json.tmp " . $filename . ".json");
}

sub printarr_html($) {
    my @arr = @{$_[0]};
    open(OUTFILE, ">highscore.html.tmp") || die "Could not create highscore.html\n";
    print OUTFILE "<html>\n<head><title>Highscore</title></head>\n";
    print OUTFILE "<body>\n";
    print OUTFILE "<div style=\"font-family: Verdana, Helvetica, Arial, sans-serif; font-size: 8px;\">\n";
    print OUTFILE "<table  cellspacing=\"5\" style=\"border-style:solid; border-width:1px; font-size: 10px;\">\n";
    print OUTFILE "<tr><th>Platz</th><th>Name</th><th>Punkte</th><th>Bande</th><th></th></tr>\n";
    for (my $i=0; $i<=$#arr; $i++) {
	my %hash = %{$arr[$i]};
	my $name = '<a target="_blank" href="http://anonym.to/?' . $mainurl . 'profil/id:' . $hash{id} . '/">' . $hash{name} . '</a>';
	my $gang = '';
	if ($hash{gid} != 0) {
	    $gang = '<a target="_blank" href="http://anonym.to/' . $mainurl . 'profil/bande:' . $hash{gid} . '/">' . $hash{gname} . '</a>';
	}
	my $fight = '<a target="_blank" href="http://anonym.to/' . $mainurl . 'fight/?to=' . $hash{name} . '/"><img border="0" src="http://media.pennergame.de/img/att.gif"></a>';
	print OUTFILE "<tr><td>" . ($i + 1) . "</td>";
	print OUTFILE "<td>" . $name . "</td>";
	print OUTFILE "<td>" . $hash{points} . "</td>";
	print OUTFILE "<td>" . $gang . "</td>\n";
	print OUTFILE "<td>" . $fight . "</td></tr>\n";
    }
    print OUTFILE "</table></div>\n</body>\n</html>\n";
    close(OUTFILE);
    system("mv -f highscore.html.tmp " . $filename . ".html");
}

sub printarr($) {
    my @arr = @{$_[0]};
    printarr_json(\@arr);
    printarr_html(\@arr);
}

sub sortPenners($) {
    return sort { $b->{'points'} <=> $a->{'points'} } @{$_[0]};
}

my @pennerids = processHighscore($pages);
my @penners = processApi(\@pennerids);
my @pennersort = sortPenners(\@penners);
printarr(\@pennersort);
