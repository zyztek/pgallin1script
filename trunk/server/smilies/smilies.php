<?php
# 
# @filename smilies.php
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#
include('smilies_v1.inc.php');
include('smilies_v2.inc.php');

###############################################################################
# globals
###############################################################################
$params = $_POST;
if (empty($params)) {
    $params = $_GET;
}

$referer = $_SERVER['HTTP_REFERER'];


###############################################################################
# helpers
###############################################################################
function DateCmp($a, $b)
{
    return strnatcasecmp($b["1"], $a["1"]); //reversed to order from newest to oldest, change to ($a["1"], $b["1"]) to do oldest to newest
#  return ($a[1] < $b[1]) ? -1 : 0;
}

function get_extension($filename) {
    $len = strlen($filename);
    if ($len <= 4) return "";
    return strtolower(substr($filename, $len-4));
}

function set_headers($header) {
    $offset = 3600 * 24;
    $expire = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";

    header($expire);
    header('Cache-Control: max-age='.$offset.', must-revalidate');

    if ($header) {
        header($header);
    }
}

###############################################################################
# get all available images
###############################################################################
// TODO

###############################################################################
# get content of one image
###############################################################################
function return_image($filename) {
    $fc = file_get_contents($filename);
    if (!$fc) {
        print('Cannot open file: ' . $filename);
        return;
    }

    $ext = get_extension($filename);
    $mt = 'Content-Type:image/';
    if ($ext == '.gif') {
        $mt .= 'gif';
    } else if ($ext == '.jpg') {
        $mt .= 'jpeg';
    } else if ($ext == '.png') {
        $mt .= 'png';
    } else {
        print('Invalid image file: ' . $filename);
        return;
    }

    set_headers($mt);
    print($fc);
}

function return_image_base64($filename) {
    $fc = file_get_contents($filename);
    if (!$fc) {
        print('Cannot open file: ' . $filename);
        return;
    }

    $ext = get_extension($filename);
    $mt = 'Content-Type:image/';
    if ($ext == '.gif') {
        $mt .= 'gif';
    } else if ($ext == '.jpg') {
        $mt .= 'jpeg';
    } else if ($ext == '.png') {
        $mt .= 'png';
    } else {
        print('Invalid image file: ' . $filename);
        return;
    }

    set_headers();
    print($mt . "," . base64_encode($fc));
}

###############################################################################
# access information
###############################################################################
function writeInfo() {
    global $referer; 
    $fh = fopen("info.txt", 'w') or die("can't open file");
    fwrite($fh, $referer . "\n\n");
    foreach (array_keys($_SERVER) as $key) {
        $stringData = $key . ":" . $_SERVER[$key] . "\n";
        fwrite($fh, $stringData);
    }
    fclose($fh);
}

function addInfo($data) { 
    $fh = fopen("info2.txt", 'a') or die("can't open file");
    fwrite($fh, $data . "\n");
    fclose($fh);
}

###############################################################################
# main script entry
###############################################################################
if ($referer != "" && 
    !ereg ("pennergame.de", $referer) &&
    !ereg ("dossergame.co.uk", $referer) &&
    !ereg ("clodogame.fr", $referer) &&
    !ereg ("menelgame.pl", $referer) &&
    !ereg ("mendigogame.es", $referer) &&
    !ereg ("bumrise.com", $referer) &&
    !ereg ("serserionline.com", $referer)) {
    addInfo($referer . " - " . $_SERVER['HTTP_USER_AGENT']);
    header("LOCATION: " . "http://www.greensmilies.com/smile/smiley_emoticons_stevieh_tricked.gif");
} else {
    if ($referer == "") {
        addInfo($referer . " - " . $_SERVER['HTTP_USER_AGENT']);
    }
    if ($params["info"] != "") {
        writeInfo();
    }
    if ($params["list"] != "") {
        return_images_json_v1();
    } else if ($params["listv2"] != "") {
        return_images_json_v2();
    } else if ($params["image"] != "") {
        return_image($params["image"]);
    } else if ($params["image_b64"] != "") {
        return_image_base64($params["image_b64"]);
    } else {
        print('no command specified');
    }
}
?>
