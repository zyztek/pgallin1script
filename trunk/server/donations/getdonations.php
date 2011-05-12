<?
# 
# @filename getdonations.php
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
    error_reporting('E_NONE');
}

$params = $_POST;
if (empty($params)) {
    $params = $_GET;
}

function getMainUrl($link) {
    $urlarr=preg_split('/change_please/', $link);
    if (count($urlarr) != 2) return "";
    $url = str_replace('/', '', str_replace('http://', '', $urlarr[0]));
    return $url;
}

function checkAuth() {

    global $params;
    
    $auth = array();
    $auth['change.pennergame.de'] = 'FILLME';
    $auth['muenchen.pennergame.de'] = 'FILLME';
    $auth['change.bumrise.com'] = 'FILLME';
    $auth['change.serserionline.com'] = 'FILLME';
    $authString = "FILLME";
 
    $url = getMainUrl($params["link"]);
    if ($params["auth"] == "") return false;
    if ($params["auth"] == $authString) return true;
    if ($url == "") return false;
    if ($auth[$url] == $params["auth"]) return true;
    return false;
}

if (!empty($params) &&
    !checkAuth()){
    header('HTTP/1.1 403 Forbidden');
    ?>
        <!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
        <html><body><h1>Authentication for <? printf(getMainUrl($params["link"])) ?> failed.</h1></body></html>
    <?
    exit;
} 

?>
<!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en'>
<head>
<meta http-equiv='content-type' content='text/html; charset=UTF-8' />
<title>Get Donations</title>
</head>
<body>
<center>
<h1>Get Donations for Pennergame and similar</h1>
<?

if (empty($params) || $params["link"] == "" || !checkAuth()) {
?>

    <form method="post">
    <table>
        <tr>
            <td>Count:</td>
            <td><input type='text' name='count' /></td>
        </tr>
        <tr>
            <td>Needed:</td>
            <td><input type='text' name='needed' /></td>
        </tr>
        <tr>
            <td>Link:</td>
            <td><input type='text' name='link' /></td>
        </tr>
        <tr>
            <td>Authentication:</td>
            <td><input type='text' name='auth' /></td>
        </tr>
        <tr>
            <td colspan="2"><center><input type='submit' value='donate' /></center></td>
        </tr>
    </table>
    </form>
    
    </center>

<?
} else {
    // build the command
    $command = 'bash -c "';
    if ($params["count"] != "") {
        $command = $command . "COUNT=" . $params["count"] . " ";
    }
    if ($params["needed"] != "") {
        $command = $command . "NEEDED=" . $params["needed"] . " ";
    }
    //$command = $command . "USETHREADS=1 ";
    $command = $command . "USEWGET=1 ";
    $command = $command . "LINK='" . $params["link"] . "' ";
    $command = $command . ' ./getdonations.pl"';

    // execute it
    $out = array();

    if ($dbg) print $command . "<br>";
    
    exec($command, $out, $retval);
    
    if ($retval == 0) {
        print("Started donations.");
    } else {
        print("Starting donations failed!");
    }
?>
    <script type="text/javascript">
        function goBack() {
            var s = "" + window.location;
            var p = s.lastIndexOf('?');
            if (p != -1) {
                s = s.substr(0, p);
            }
            window.location = s;
        }
    </script>

    <br><br><a href="#" onclick="javascript: goBack()">zur&uuml;ck</a>
<?
}
?>

</body>
</html>

