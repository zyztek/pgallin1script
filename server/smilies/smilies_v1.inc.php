<?
# 
# @filename smilies_v1.inc.php
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#
###############################################################################
# list of special smilies
###############################################################################

// smilies that should be at the beginning of the list, in the order given here
// can be the full file name or 'bussi' which expands to 'smiley_emoticons_bussi.gif'
$leading = array(
                'smilenew',
                'neutral_new',
                'wink2',
                'biggrin',
                'sad',
                'eek',
                'cool-down',
                'helpnew',
                'frown',
                'irre',
                'langenase2',
                'klugscheisser2',
                'jumpgrin',
                'hecheln',
                'gruebel',
                'gaehn',
                'charly_rofl',
                'confusednew',
                'bravo2',
                'aufsmaul',
                'skeptisch',
                'zaehneknirschen',
                'panik',
                'kuckuck',
                'dead',
                'doh',
                'kolobok-sanduhr',
                'klimpern01',
                'rolleyesnew',
                'teuflisches-nein',
                'teuflisches-ja',
                'rofl3',
                'verwirrt2',
                'verlegen',
                'yes_sad',
                'titten2',
                'tomate',
                'shocking',
                'sic01',
                'thumbs1',
                'down',
                'razz',
                'winken4',
                'wallbash',
                'warn',
                'muede',
                'nicht-lachen02',
                'panik5',
                'papiertuete-kopf',
                'nicken',
                'no',
                'morgaehn',
                'idea2',
                'exclaim2',
                'question2',
                'anmachen',
                'baeh',
                'baeh2',
                'ducken',
                '29a',
                '29b',
                '29c',
                'fressepolieren',
                'bananadancer2',
                'flucht2',
                'hurra2',
                'igitt',
             );

// smilies that should be at the beginning of the list, in the order given here
// can be the full file name or 'bussi' which expands to 'smiley_emoticons_bussi.gif'
$tailing = array(
                'admin_hat_recht.gif',
                'admin_hat_gesprochen.gif',
                'charly_willkommen',
                'dafuer',
                'dagegen',
                'entschuldigung2',
                'wuah',
                'abstimmen',
                'seb_wer_programmieren_kann',
                'closed',
                'erwachsen',
                'motzschild',
                '0_0.gif',
                '0_1.gif',
                '1_0.gif',
                '1_1.gif',
                'evade.gif',
             );

###############################################################################
# get all available images
###############################################################################
function array_contains($arr, $elem) {
    foreach ($arr as $a) {
        if ($a == $elem) {
            return true;
        }
    }
    return false;
}

function get_images_from_dir() {
    $dir = ".";
    // open this directory 
    if ($myDirectory = opendir($dir)) {
        // Making an array containing the files in the current directory:
        while ($file = readdir($myDirectory)){
            if( $file != ".." && $file != "." ){
                $LastModified = filemtime($file);
                $files[] = array($file, $LastModified);
            }
        }
        closedir($myDirectory);
        // sort files by mtime:
        usort($files, 'DateCmp');
        
        foreach ($files as $filearr) {
            $entryName = $filearr[0];
            $ext = get_extension($entryName);
            if ($ext != '.gif' &&
                $ext != '.png' &&
                $ext != '.jpg') {
                continue;
            }
            $dirArray[] = $entryName;
        }
    }

    return $dirArray;
}

function get_images_v1() {
    global $leading; 
    global $tailing; 
    $r1 = array();
    $r2 = array();
    $r3 = array();
    $all = get_images_from_dir();

    // add the leading images, if they exist
    foreach ($leading as $i) {
        $i2 = 'smiley_emoticons_' . $i. '.gif';
        if (array_contains($all, $i) && !array_contains($r1, $i)) {
            $r1[] = $i;
        } else if (array_contains($all, $i2) && !array_contains($r1, $i2)) {
            $r1[] = $i2;
        }
    }

    // add the tailing images, if they exist
    foreach ($tailing as $i) {
        $i2 = 'smiley_emoticons_' . $i. '.gif';
        if (array_contains($all, $i) && !array_contains($r1, $i) && !array_contains($r3, $i)) {
            $r3[] = $i;
        } else if (array_contains($all, $i2) && !array_contains($r1, $i2) && !array_contains($r3, $i2)) {
            $r3[] = $i2;
        }
    }

    // now add the rest, if not already in the array
    foreach ($all as $i) {
        if (!array_contains($r1, $i) && !array_contains($r3, $i)) {
            $r2[] = $i;
        }
    }

    // stitch them together
    foreach ($r1 as $i) $ret[] = $i;
    foreach ($r2 as $i) $ret[] = $i;
    foreach ($r3 as $i) $ret[] = $i;

    return $ret;
}

function return_images_json_v1() {
    $ret = '{ "images": [ ';
    $dirArray = get_images_v1();
    $cnt = count($dirArray);
    for($i = 0; $i < $cnt; $i++) {
        $ret .= ' "' . $dirArray[$i];
        $ret .= '"';
        if ($i+1 < $cnt) {
            $ret .= ', ';
        }
    }
    $ret .= ' ] }';
    print($ret);
}

?>
