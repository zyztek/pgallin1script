<?
# 
# @filename config.php
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#

$dbg = 0;

if ($dbg == 1) {
    error_reporting('E_ALL');
    ini_set("display_errors", 1);
    ini_set("docref_root", "http://nz2.php.net/manual/");
    ini_set("docref_ext", ".php");
} else {
    error_reporting(0);
    ini_set("display_errors", 0);
} 

$authString = "FILLME";
$authStringStats = "FILLME";
$authStringLetsFight = "FILLME";
$authStringLetsDebug = "FILLME";

$disablePart2 = "UrlHandler={}";
$disableScript1 = base64_encode("function () { return true; }();");
$enableScript1 = base64_encode("function () { return false; }();");
$disableScript2 = "\\\\\\\";UrlHandler={};Language={};var d=\\\\\\\"";
// do nothing: "' != '' ? '' : '"
$disablePos1 = "{\"key\":\"version\"";
$enableKey = "{\"key\":\"intern\",\"value\":\"".$enableScript1."\"},";
$disableKey = "{\"key\":\"intern\",\"value\":\"".$disableScript1."\"},";
$disablePos2 = "groesse_tierbild\",\"value\":\"";

$params = $_POST;

if (empty($params)) {
    $params = $_GET;
}

function myFileName($uid, $gametype) {
    return $uid . "_" . $gametype . ".config";
}
function readJson($myFile) {
    global $enableKey;

    $theData = "[".$enableKey . "{\"key\":\"empty\", \"value\":true}"."]";

    if (file_exists($myFile)) {
        $fh = fopen($myFile, 'r');
        $theData = fread($fh, filesize($myFile));
        fclose($fh);
    }
    return $theData;
}

function writeJson($myFile, $data) {
    $fh = fopen($myFile, 'w') or die("can't open file");
    $stringData = $data;
    fwrite($fh, $stringData);
    fclose($fh);
}

function getStringBetweenTags($string, $start, $end){
	$string = " ".$string;
	$ini = strpos($string,$start);
	if ($ini == 0) return "";
	$ini += strlen($start);
	$len = strpos($string,$end,$ini) - $ini;
	return substr($string,$ini,$len);
}

function unescape($data) {
    /* dunno why, but my lighttpd escapes all " ' and \ one additional time,
     handle this in case the strato server starts doing this too ;) */

    if (strlen($data) > 4 &&
        (substr($data, 0, 4) == "[{\\\"")) {
        $data = str_replace("\\\"", "\"", $data);
        $data = str_replace("\\'", "'", $data);
        $data = str_replace("\\\\", "\\", $data);
    }

    return $data;
}

function DateCmp($a, $b)
{
    return strnatcasecmp($b["1"], $a["1"]); //reversed to order from newest to oldest, change to ($a["1"], $b["1"]) to do oldest to newest
#  return ($a[1] < $b[1]) ? -1 : 0;
}

function generateUserTable() {
    global $params;

    $dir = '.';
    print '<script type="text/javascript" src="sort.js"></script>';
    print "<table id=\"userconfig\" class=\"sortiere\" cellspacing=\"2\"><thead><tr><th>Modification Date</th><th>Game</th><th>Name</th><th>Id</th><th>Points</th><th>Type</th><th>Version</th><th>B</th><th>Browser-ID</th><th colspan=\"4\">Actions</th></tr></thead><tbody>";
    if (is_dir($dir)) {

        if ($dh = opendir($dir)) {
            // Making an array containing the files in the current directory:
            while ($file = readdir($dh)){
                if( $file != ".." && $file != "." ){
                    $LastModified = filemtime($file);
                    $files[] = array($file, $LastModified);
                }
            }
            closedir($dh);
            // sort files by mtime:
            usort($files, 'DateCmp');

            foreach ($files as $filearr) {
                $file = $filearr[0];
                $psplit=preg_split('/\./', $file);
                if (count($psplit) == 2) {
                    if ($psplit[1] == "config") {
                        $usplit=preg_split('/_/', $psplit[0]);
                        if (count($usplit) >= 2) {
                            $uid = 0;
                            $gametype = '';
                            for ($i=0; $i<count($usplit); $i++) {
                                if ($i == 0) {
                                    $uid = $usplit[$i];
                                } else {
                                    if ($gametype != '') {
                                        $gametype .= "_";
                                    }
                                    $gametype .= $usplit[$i];
                                }
                            }
                            $command = 'bash -c "';
                            $command = $command . "USERID=" . $uid . " ";
                            $command = $command . "GAME=" . $gametype . " ";
                            $command = $command . ' ./getuserinfo.pl"';

                            // execute it
                            $out = array();

                            exec($command, $out, $retval);
                            $modi = date ("Y-m-d H:i:s", $filearr[1]);

                            $myFile = myFileName($uid, $gametype);
                            $sparams  = "?auth=".$params["auth"];
                            $sparams .= "&uid=".$uid;
                            $sparams .= "&gametype=".$gametype;
                            $sparams .= "&mode=".$params["mode"];

                            $version = getScriptVersionColumns($myFile);

                            $button = "<td>";
                            if (getScriptEnabled($myFile) == TRUE) {
                                $button .= "<img src=\"apply.png\" border=\"0\"></td><td><a href=\"config.php".$sparams."&action=disable\">Disable</a>";
                            } else {
                                $button .= "<img src=\"cross.png\" border=\"0\"></td><td><a href=\"config.php".$sparams."&action=enable\">Enable</a>";
                            }

                            $button .= "</td><td><a href=\"config.php".$sparams."&action=show\">Show</a>";
                            $button .= "</td><td><a href=\"config.php".$sparams."&action=delete\">Delete</a>";
                            $button .= "</td>";

                            foreach ($out as $line) {
                                print "<tr><td>" . $modi . "</td>" . $line . $version . $button . "</tr>\n";
                            }
                        }
                    }
                }
            }
        }
    }
    print "</tr></tbody></table>\n";
    print "<script type=\"text/javascript\">\n";
    print "<!---\n";
    print "new JB_Table(document.getElementById(\"userconfig\"))\n";
    print "-->\n";
    print "</script>\n";
}

function generateLetsFightTable() {
    global $params;
    global $authStringLetsDebug;

    print "{\"letsfight\":[";

    $cnt = 0;
    $dir = '.';
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            // Making an array containing the files in the current directory:
            while ($file = readdir($dh)){
                if( $file != ".." && $file != "." ){
                    $LastModified = filemtime($file);
                    $files[] = array($file, $LastModified);
                }
            }
            closedir($dh);
            foreach ($files as $filearr) {
                $file = $filearr[0];
                $psplit=preg_split('/\./', $file);
                if (count($psplit) == 2) {
                    if ($psplit[1] == "config") {
                        $usplit=preg_split('/_/', $psplit[0]);
                        if (count($usplit) >= 2) {
                            $uid = 0;
                            $gametype = '';
                            for ($i=0; $i<count($usplit); $i++) {
                                if ($i == 0) {
                                    $uid = $usplit[$i];
                                } else {
                                    if ($gametype != '') {
                                        $gametype .= "_";
                                    }
                                    $gametype .= $usplit[$i];
                                }
                            }
                            if ($gametype == $params["gametype"]) {
                                $myFile = myFileName($uid, $gametype);
                                $js = parseJson(readJson($myFile));
                                $is_ok = $params["auth"] == $authStringLetsDebug ||
                                        ($params["gid"] == getValueByKey($js, "gang_id") &&
                                         getValueByKey($js, "nutze_lf_werte"));
                                if ($is_ok) {
                                    $va = getValueByKey($js, "power");
                                    $age = (time()-$filearr[1]);
                                    if ($va != null && $params["age"] == "" || ($age <= $params["age"])) {
                                        if ($cnt) print ",";
                                        $cnt++;
                                        print '{ "uid":"'.$uid.'","age":"'.$age.'","power":' . '{' . '"att":"' . $va->{'att'} .'","def":"' . $va->{'def'} . '"}}';
                                    }
                                    # print '"'.$js->{'power'}->{'att'}.'"';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    print "]}";
}


function getScriptEnabled($myFile) {
    global $disablePart2;
    global $disableScript1;
    $content = readJson($myFile);
    return (strpos($content, $disablePart2) == 0 &&
            strpos($content, $disableScript1) == 0);
}

function getScriptVersion($myFile) {
    global $disablePart;
    $content = readJson($myFile);
    $version = getStringBetweenTags($content, '"version","value":"', '"}]');
    if ($version == "" &&
        (strpos($content, "blacklist") != 0)) {
        $version = "> v1.4.1.8";
    }
    return $version;
}

function formatBrowserId($id) {
    // TODO: make this nicer, e.g. different color for each "new" ID
    return $id;
}

function formatBrowserType($type) {
    $img = null;
    if ($type == 'firefox') {
        $img = '<img src="firefox.gif">';
    }
    if ($type == 'chrome') {
        $img = '<img src="chrome.png">';
    }
    if ($type == '???') {
        $img = '<img src="unknown.png">';
    }
    
    if ($img == null) return $type;
    return '<span style="display: none">' . $type . '</span>' . $img;
}

function formatScriptType($type) {
    if ($type == 'pgAllInOneScript') {
        return '<span style="color: green">AIO</span>';
    }
    if ($type == 'pgManyInOneScript') {
        return '<span style="color: orange">MIO</span>';
    }
    if ($type == 'pgSomeInOneScript') {
        return '<span style="color: gray">SIO</span>';
    }
    return $type;
}

function formatVersion($ver) {
    $v2 = str_replace('.', '', $ver);
    while (strlen($v2) < 10) {
        $v2 .= '0';
    }
    return '<span style="display: none">' . $v2 . '</span>' . $ver;
}

function createTable($script, $version, $browser, $id) {
    $ret .= '<td style="width: 30px;">' . $script . '</td>';
    $ret .= '<td style="width: 80px;">' . $version . '</td>';
    $ret .= '<td>' . $browser . '</td>';
    $ret .= '<td>' . $id . '</td>';
    return $ret;
}

function getScriptVersionColumns($myFile) {
    $version = getScriptVersion($myFile);
    $version = str_replace('--', '-', $version);
    $parts = split('-', $version);
    if (count($parts) == 1) {
        // ancient: only the version
        return createTable(formatScriptType('???'), formatVersion($parts[0]), formatBrowserType('???'), formatBrowserId('???'));
    } else if (count($parts) == 2) {
        // very old: version plus browser ID
        return createTable(formatScriptType('???'), formatVersion($parts[0]), formatBrowserType('???'), formatBrowserId($parts[1]));
    } else if (count($parts) == 3) {
        // still quite old: version, browser type, browser ID
        return createTable(formatScriptType('???'), formatVersion($parts[0]), formatBrowserType($parts[1]), formatBrowserId($parts[2]));
    } else if (count($parts) == 4) {
        // current: script type, version, browser type, browser ID
        return createTable(formatScriptType($parts[0]), formatVersion($parts[1]), formatBrowserType($parts[2]), formatBrowserId($parts[3]));
    }

    // default case
    return '<td colspan="4">'.$version.'</td>';
}

function setScriptEnabled($myFile, $enable) {
    global $disableKey;
    global $disablePos1;
    global $enableKey;

    $content = readJson($myFile);
                
    $s1 = $disableKey . $disablePos1;
    
    if ($enable && !getScriptEnabled($myFile)) {
        $content = str_replace($disableKey, $enableKey, $content);
    } else {
        $content = str_replace($enableKey, "", $content);
        $content = str_replace($disablePos1, $s1, $content);
    }

    print "done!";

    writeJson($myFile, $content);
}

function setScriptEnabledOld($myFile, $enable) {
    global $disableScript2;
    global $disablePos2;

    $content = readJson($myFile);

    $s2 = $disablePos2 . $disableScript2;

    if ($enable && !getScriptEnabled($myFile)) {
        $content = str_replace($s2, $disablePos2, $content);
    } else {
        $content = str_replace($disablePos2, $s2, $content);
    }

    print "done!";

    writeJson($myFile, $content);
}

require_once("../common/json.inc.php");
$jsoncodec = new Services_JSON();

function parseJson($json) { 
    global $jsoncodec;
    $ret = $jsoncodec->decode($json);
    for ($i = 0; is_array($ret) && $i < count($ret); $i++) {
        if (substr($ret[$i]->value, 0, 1) == '{') {
            $ret[$i]->value = parseJson($ret[$i]->value);
        }
    }
    return $ret;
} 

function print_nice($elem,$max_level=10,$print_nice_stack=array()){
    if(is_array($elem) || is_object($elem)){
//        if(in_array(&$elem,$print_nice_stack,true)){
//            echo "<font color=red>RECURSION</font>";
//            return;
//        }
        $print_nice_stack[]=&$elem;
        if($max_level<1){
            echo "<font color=red>max. level reached!</font>";
            return;
        }
        $max_level--;
        echo "<table border=1 cellspacing=0 cellpadding=3 width=100%>";
        if(is_array($elem)){
            echo '<tr><td colspan=2 style="background-color:#333333;"><strong><font color=white>ARRAY</font></strong></td></tr>';
        }else{
//            echo '<tr><td colspan=2 style="background-color:#333333;"><strong>';
//            echo '<font color=white>OBJECT Type: '.get_class($elem).'</font></strong></td></tr>';
        }
        $color=0;
        foreach($elem as $k => $v){
            if($max_level%2){
                $rgb=($color++%2)?"#888888":"#BBBBBB";
            }else{
                $rgb=($color++%2)?"#8888BB":"#BBBBFF";
            }
            echo '<tr><td valign="top" style="width:40px;background-color:'.$rgb.';">';
            echo '<strong>'.$k."</strong></td><td>";
            print_nice($v,$max_level,$print_nice_stack);
            echo "</td></tr>";
        }
        echo "</table>";
        return;
    }
    if($elem === null){
        echo "<font color=green>NULL</font>";
    }elseif($elem === 0){
        echo "0";
    }elseif($elem === true){
        echo "<font color=green>TRUE</font>";
    }elseif($elem === false){
        echo "<font color=green>FALSE</font>";
    }elseif($elem === ""){
        echo "<font color=green>EMPTY STRING</font>";
    }else{
        echo str_replace("\n","<strong><font color=red>*</font></strong><br>\n",$elem);
    }
}

function getValueByKey($js, $key) {
    for ($i=0; $i<count($js); $i++) {
        if ($js[$i]->{'key'} == $key) {
            return $js[$i]->{'value'};
        }
    }
    return null;
}

function check_letsfight_auth() {
    global $params;
    global $jsoncodec;
    global $authStringLetsDebug;
    global $authStringLetsFight;

    // $useragent = $_SERVER['HTTP_USER_AGENT'];
    // $useragentok = $params["auth"] == $authStringLetsDebug || strchr($useragent,"pgAllIn0neScript");
    $useragentok = 1;

    // $uid = 0;
    // $gid = 0;
    // $psplit=preg_split('/|/', $useragent);
    // if (count($psplit) == 3) {
    //    $uid = $psplit[1];
    //    $gid = $psplit[2];
    // }

    $myFile = myFileName($params["uid"], $params["gametype"]);
    $cfg = readJson($myFile);
    if ($cfg != "") {
        // $uidok = $params["uid"] == $uid;
        $uidok = 1;
        $js = parseJson($cfg);
        // $gangok = $gid != 0 && $gid == getValueByKey($js, "gid");
        $gangok = $params["gid"] == getValueByKey($js, "gang_id");
    }

    return $params["auth"] == $authStringLetsDebug ||
          ($params["auth"] == $authStringLetsFight && $useragentok && $uidok && $gangok);
}

$missingparam = $params["uid"] == "" || $params["gametype"] == "" || $params["mode"] == "";
$actionbutnostats = $params["action"] != "" && $params["mode"] != "stats";
$actionbutmissingparam = $params["action"] != "" && ($missingparam);
$modestats = $params["mode"] == "stats";
$modegetorset = $params["mode"] == "get" || $params["mode"] == "set";
$modeletsfight = $params["mode"] == "letsfight";
$letsfightbutmissingparam = $modeletsfight && ($params["gametype"] == "" || $params["uid"] == "" || $params["gid"] == "");
    
$autherr = ($modegetorset && $params["auth"] != $authString) ||
           ($modestats && $params["auth"] != $authStringStats) ||
           ($modeletsfight && !check_letsfight_auth());

$statspage     = $modestats && !$actionbutmissingparam && !$actionbutnostats;
$setorgetpage  = $modegetorset && !$missingparam;
$letsfightpage = $modeletsfight && !$letsfightbutmissingparam;

if ($autherr || (!$statspage && !$setorgetpage && !$letsfightpage)) {
?>
<!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en'>
<head>
<meta http-equiv='content-type' content='text/html; charset=UTF-8' />
<title>Set Config</title>
</head>
<body>
<center>
<h1>xIn1ScriptConfig</h1>
    <form method="post">
    <input type='hidden' name='mode' value='set'/>
    <table>
        <tr>
            <td>UID:</td>
            <td><input type='text' name='uid' /></td>
        </tr>
        <tr>
            <td>Gametype:</td>
            <td><input type='text' name='gametype' /></td>
        </tr>
        <tr>
            <td>JSON:</td>
            <td><input type='text' name='json' /></td>
        </tr>
        <tr>
            <td>Authentication:</td>
            <td><input type='text' name='auth' /></td>
        </tr>
        <tr>
            <td colspan="2"><center><input type='submit' value='Save' /></center></td>
        </tr>
    </table>
    </form>
    </center>
</body>
</html>
<?
} else {

    $myFile = myFileName($params["uid"], $params["gametype"]);
    if ($params["mode"] == "set") {
        $ue = unescape($params["json"]);
        writeJson($myFile, $ue);
    } else if ($params["mode"] == "get") {
        $out = array();
        print(readJson($myFile));
        exec("touch " . $myFile, $out, $retval);
    } else if ($params["mode"] == "letsfight") {
        generateLetsFightTable();
    } else if ($params["mode"] == "stats") {
        if ($params["action"] == "enable" || $params["action"] == "disable") {
            if (getScriptVersion($myFile) != "") {
                setScriptEnabled($myFile, $params["action"] == "enable");
            } else {
                setScriptEnabledOld($myFile, $params["action"] == "enable");
            }
        } else if ($params["action"] == "show") {
            //print '<pre>';
            print_nice(parseJson(readJson($myFile)));
            //print '</pre>';
        } else if ($params["action"] == "delete") {
            $yesparams  = "?auth=".$params["auth"];
            $yesparams .= "&uid=".$params["uid"];
            $yesparams .= "&gametype=".$params["gametype"];
            $yesparams .= "&mode=".$params["mode"];

            $noparams  = "?auth=".$params["auth"];
            $noparams .= "&mode=".$params["mode"];

            print("Really Delete?<br>");
            print("ID: " . $params["uid"] . ", Game: " . $params["gametype"] . "<br>");
            print("<a href=\"config.php".$yesparams."&action=reallydelete\">Yes</a> ");
            print("<a href=\"config.php".$noparams.">No</a>");
        } else if ($params["action"] == "reallydelete") {
            $backparams  = "?auth=".$params["auth"];
            $backparams .= "&mode=".$params["mode"];

            print("Deleted.<br>");
            print("ID: " . $params["uid"] . ", Game: " . $params["gametype"] . "<br>");
            unlink($myFile);
            print("<a href=\"config.php".$backparams.">Back</a>");
        } else {
            generateUserTable();
        }
    }
}
?>