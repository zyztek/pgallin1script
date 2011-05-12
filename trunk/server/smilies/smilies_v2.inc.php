<?
# 
# @filename smilies_v2.inc.php
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#

function return_images_json_v2() {
    $ret = '{ "images": [ ';
    $images = file("smilies.txt");
    $cnt = count($images);
    for ($i = 0; $i < $cnt; $i++) {
        $ret .= ' "' . trim($images[$i]) . '"';
        if ($i+1 < $cnt) {
            $ret .= ', ';
        }
    }
    $ret .= ' ] }';
    print($ret);
}

?>
