<?php
# 
# @filename gangfights.php
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#

error_reporting(0);
ini_set("display_errors", 0);

include('../common/json.inc.php');
include('../common/mutex.inc.php');

###############################################################################
# globals
###############################################################################
$params = $_POST;
if (empty($params)) {
    $params = $_GET;
}

$jsoncodec = new Services_JSON();

###############################################################################
# helper functions
###############################################################################
function checkAuth() {
    global $params;
    
    $auth = array();
    $authString = "FILLME";
 
    if ($params["auth"] == "") return false;
    if ($params["auth"] == $authString) return true;
    return false;
}

function parseJson($json) { 
    global $jsoncodec;
    return $jsoncodec->decode($json);
} 

###############################################################################
# main script functionality
###############################################################################
function readStats($filename) {
    $ret = array();
    if (($handle = fopen($filename, "r")) != false) {
        while (($line = fgets($handle)) != false) {
            $ret[] = trim($line);
        }
        fclose($handle);
    }
    return $ret;
}

function writeStats($filename, $stats) {
    if (($handle = fopen($filename, "w")) != false) {
        for ($i = 0; $i < count($stats); $i++) {
            fputs($handle, $stats[$i]."\n");
        }
        fclose($handle);
    }
}

function setStats($filename, $statsjson) {
    $oldstats = readStats($filename);
    $stats = parseJson($statsjson);
    $cnt = 0;
    if (is_array($stats)) {
        $cnt = count($stats);
    }

    for ($i = 0; $i < $cnt; $i++) {
        $s = $stats[$i];
        $add = true;
        for ($j = 0; $j < count($oldstats); $j++) {
            if ($oldstats[$j] == $s) {
                $add = false;
                break;
            }
        }
        if ($add) {
            $oldstats[] = $s;
        }
    }

    writeStats($filename, $oldstats);
}

function getStats($filename) {
    $stats = readStats($filename);
    $cnt = count($stats);

    print "{ \"stats\": [ ";
    for ($i = 0; $i < $cnt; $i++) {
        $s = str_replace('"', '\\"', $stats[$i]);
        print '"'.$s.'"';
        if ($i+1 < $cnt) print ", ";
    }
    print " ] }";
}

function mainFunc($doSet) {
    global $params;
    $gid = $params['gid'];
    if (!$gid) $gid = 0;
    $filename = $params['gametype'] . '_' . $params['id'] . '_' . $gid . '.stats';
    $mtx = new Mutex($filename);

    $mtx->removeOldLock();
    if (!$mtx->lock()) {
        header('HTTP/1.1 500 Internal Server Error');
        ?>
            <!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
            <html><body><h1>Internal Server Error.</h1></body></html>
        <?
        exit;
    }

    if ($doSet) {
        setStats($filename, $params['stats']);
    }
    getStats($filename);

    $mtx->unlock();
}

###############################################################################
# main script entry
###############################################################################

if (!empty($params) &&
    !checkAuth()){
    header('HTTP/1.1 403 Forbidden');
    ?>
        <!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
        <html><body><h1>Authentication failed.</h1></body></html>
    <?
    exit;
} 

if (!empty($params) && ($params['action'] == 'get' || $params['action'] == 'set')) {
    mainFunc($params['action'] == 'set');
} else {
?>

    <form method="post">
    <table>
        <tr>
        <td>Gametype:</td>
            <td><input type='text' name='gametype' /></td>
        </tr>
        <tr>
            <td>ID:</td>
            <td><input type='text' name='id' /></td>
        </tr>
        <tr>
            <td>Gang ID:</td>
            <td><input type='text' name='gid' /></td>
        </tr>
        <tr>
            <td>Text:</td>
            <td><textarea name='stats'></textarea></td>
        </tr>
        <tr>
            <td>Action:</td>
            <td><input type="radio" name="action" value="set">Set<input type="radio" name="action" value="get">Get</td>
        </tr>
        <tr>
            <td>Authentication:</td>
            <td><input type='text' name='auth' /></td>
        </tr>
        <tr>
            <td colspan="2"><center><input type='submit' value='Send' /></center></td>
        </tr>
    </table>
    </form>


<?
}

?>
