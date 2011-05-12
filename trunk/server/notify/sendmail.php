<?

$dbg = 1;

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

function checkAuth() {
    global $params;
    
    $auth = array();
    $authString = "FILLME";
 
    if ($params["auth"] == "") return false;
    if ($params["auth"] == $authString) return true;
    return false;
}

if (!empty($params) &&
    !checkAuth()){
    header('HTTP/1.1 403 Forbidden');
    ?>
        <!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
        <html><body><h1>Authentication failed.</h1></body></html>
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
<h1>Send Log-Messages from pgAllInOneScript</h1>
<?

if (empty($params) || $params["mail"] == "" || !checkAuth()) {
?>

    <form method="post">
    <table>
        <tr>
        <td>Recipient::</td>
            <td><input type='text' name='mail' /></td>
        </tr>
        <tr>
            <td>Subject:</td>
            <td><input type='text' name='subject' /></td>
        </tr>
        <tr>
            <td>Text:</td>
            <td><input type='text' name='text' /></td>
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
    
    </center>

<?
} else {

    $to = $params["mail"];
    $subject = $params["subject"];
    $from = 'notification@biniok.net';
    $message = $params["text"];

    $retval = mail($to, $subject, $message, "From: $from");

    if ($retval) {
        print("Mail sent.");
    } else {
        print("Mail NOT sent!");
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

