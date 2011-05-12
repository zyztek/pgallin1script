<?php
# 
# @filename script-compiler.php
# @author Jan Biniok <jan@biniok.net>
# @author Thomas Rendelmann <thomas@rendelmann.net>
# @licence GPL v2
#

###############################################################################
# global settings
###############################################################################

$dbg = 0;

$changelogpath = "../versions/";
$changelog = "changelog.xml";
$content = "content/";
$path = "../script/";
$imagepath = "../resources/";
$ff_rt_path = "../runtime/firefox/";
$us_rt_path = "../runtime/user_script/";
$cr_rt_path = "../runtime/chrome/";
$ff_build_path = "./firefox/";
$cr_build_path = "./chrome/";

###############################################################################
# internal data and functions
###############################################################################

function file_array($path, $exclude = ".|..|.*", $recursive = false) {
    $path = rtrim($path, "/") . "/";
    $folder_handle = opendir($path);
    $exclude_array = explode("|", $exclude);
    $result = array();
    while(false !== ($filename = readdir($folder_handle))) {
        if(!in_array(strtolower($filename), $exclude_array)) {
            if(is_dir($path . $filename . "/")) {
                                // Need to include full "path" or it's an infinite loop
                if($recursive) $result[] = file_array($path . $filename . "/", $exclude, true);
            } else {
                $result[] = $path . $filename;
            }
        }
    }
    return $result;
}

function getHeaderData($file) {
    global $data;
    global $rev;
    
    $scriptcontent = file_get_contents($file);

    //continue build data .. grok values from script
    $m=array();
    $scriptData=preg_split('/[\n\r]+/', $scriptcontent);

    foreach ($scriptData as $line) {
        $m=array();
        if (preg_match('/^name\b(.*)/', $line, $m)) {
            $data['name']=trim($m[1]);
        }
        if (preg_match('/^description\b(.*)/', $line, $m)) {
            $data['description']=trim($m[1]);
        }
        if (preg_match('/^include\b(.*)/', $line, $m)) {
            $data['include'][]=trim($m[1]);
        }
        if (preg_match('/^exclude\b(.*)/', $line, $m)) {
            $data['exclude'][]=trim($m[1]);
        }
        if (preg_match('/^version\b(.*)/', $line, $m)) {
            $data['version']=trim($m[1]).'.'.$rev;
        }
        if (preg_match('/^author\b(.*)/', $line, $m)) {
            $data['creator']=trim($m[1]);
        }
        if (preg_match('/^updateurl\b(.*)/', $line, $m)) {
            $data['updateHomeUrl']=trim($m[1]);
        }
        if (preg_match('/^changelogurl\b(.*)/', $line, $m)) {
            $data['changelogHomeUrl']=trim($m[1]);
        }
        if (preg_match('/^guid\b(.*)/', $line, $m)) {
            $data['guid']=trim($m[1]);
        }
        if (preg_match('/^appid\b(.*)/', $line, $m)) {
            $data['appid']=trim($m[1]);
        }
        if (preg_match('/^namespace\b(.*)/', $line, $m)) {
            $data['homepage']=trim($m[1]);
        }
    }

    //make short name from name
    $data['shortname']=substr(
        preg_replace('/[^a-zA-Z]/', '', $data['name']),
        0, 32
    );

    //convert includes/excludes
    $data['include_array']=$data['include'];
    $data['exclude_array']=$data['exclude'];
    $data['include']=array_map('convertToRegExp', $data['include']);
    $data['exclude']=array_map('convertToRegExp', $data['exclude']);

    //js-ify includes/excludes
    if (empty($data['include'])) {
        $data['include']='true';
    } else {
        $data['include']='( /'.implode('/.test(href) || /', $data['include']).'/.test(href) )';
    }
    if (empty($data['exclude'])) {
        $data['exclude']='true';
    } else {
        $data['exclude']='!( /'.implode('/.test(href) || /', $data['exclude']).'/.test(href) )';
    }

    return $data;
}

function insertValues(&$str, $data) {
    foreach ($data as $k=>$v) {
        $str=str_replace(
            '$'.$k, $v, $str
        );
    }
    return $str;
}

function convertToRegExp($str) {
    $str=preg_replace('/([][\\/.?^$+{\|)(])/', '\\\\\1', $str);
    $str=str_replace('*', '.*', $str);
    return $str;
}

$fileCache = null;

function readAndCompress($fp, &$retval) {
    global $dbg;
    global $fileCache;

    if ($fileCache["$fp"]) {
        return $fileCache["$fp"];
    }

    if (jscompressEnable() && $_POST["compression"] == "yes") {
        $ausgabe = array();

        exec('bash ./compress.sh '.$fp, $ausgabe, $retval);

        if ($dbg == 1) {
            printf('./compress.sh '.$fp.'<br><br>');
        }

        if ($retval != 0) {
            return null;
        }
        $ret = implode("\n", $ausgabe);
    } else {
        $ret = file_get_contents($fp);
    }
    $fileCache["$fp"] = $ret;
    return $ret;
}

/**
 * Class to dynamically create a zip file (archive)
 *
 * @author Rochak Chauhan
 * http://www.phpclasses.org/browse/package/2322.html
 */
class CreateZip  {
    var $compressedData = array();
    var $centralDirectory = array();
    var $endOfCentralDirectory = "\x50\x4b\x05\x06\x00\x00\x00\x00";
    var $oldOffset = 0;
    function addDirectory($directoryName){$directoryName=str_replace("\\","/",$directoryName);$feedArrayRow="\x50\x4b\x03\x04";$feedArrayRow.="\x0a\x00";$feedArrayRow.="\x00\x00";$feedArrayRow.="\x00\x00";$feedArrayRow.="\x00\x00\x00\x00";$feedArrayRow.=pack("V",0);$feedArrayRow.=pack("V",0);$feedArrayRow.=pack("V",0);$feedArrayRow.=pack("v",strlen($directoryName));$feedArrayRow.=pack("v",0);$feedArrayRow.=$directoryName;$feedArrayRow.=pack("V",0);$feedArrayRow.=pack("V",0);$feedArrayRow.=pack("V",0);$this->compressedData[]=$feedArrayRow;$newOffset=strlen(implode("",$this->compressedData));$addCentralRecord="\x50\x4b\x01\x02";$addCentralRecord.="\x00\x00";$addCentralRecord.="\x0a\x00";$addCentralRecord.="\x00\x00";$addCentralRecord.="\x00\x00";$addCentralRecord.="\x00\x00\x00\x00";$addCentralRecord.=pack("V",0);$addCentralRecord.=pack("V",0);$addCentralRecord.=pack("V",0);$addCentralRecord.=pack("v",strlen($directoryName));$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("v",0);$ext="\x00\x00\x10\x00";$ext="\xff\xff\xff\xff";$addCentralRecord.=pack("V",16);$addCentralRecord.=pack("V",$this->oldOffset);$this->oldOffset=$newOffset;$addCentralRecord.=$directoryName;$this->centralDirectory[]=$addCentralRecord;}
    function addFile($data,$directoryName){$directoryName=str_replace("\\","/",$directoryName);$feedArrayRow="\x50\x4b\x03\x04";$feedArrayRow.="\x14\x00";$feedArrayRow.="\x00\x00";$feedArrayRow.="\x08\x00";$feedArrayRow.="\x00\x00\x00\x00";$uncompressedLength=strlen($data);$compression=crc32($data);$gzCompressedData=gzcompress($data);$gzCompressedData=substr(substr($gzCompressedData,0,strlen($gzCompressedData)-4),2);$compressedLength=strlen($gzCompressedData);$feedArrayRow.=pack("V",$compression);$feedArrayRow.=pack("V",$compressedLength);$feedArrayRow.=pack("V",$uncompressedLength);$feedArrayRow.=pack("v",strlen($directoryName));$feedArrayRow.=pack("v",0);$feedArrayRow.=$directoryName;$feedArrayRow.=$gzCompressedData;$feedArrayRow.=pack("V",$compression);$feedArrayRow.=pack("V",$compressedLength);$feedArrayRow.=pack("V",$uncompressedLength);$this->compressedData[]=$feedArrayRow;$newOffset=strlen(implode("",$this->compressedData));$addCentralRecord="\x50\x4b\x01\x02";$addCentralRecord.="\x00\x00";$addCentralRecord.="\x14\x00";$addCentralRecord.="\x00\x00";$addCentralRecord.="\x08\x00";$addCentralRecord.="\x00\x00\x00\x00";$addCentralRecord.=pack("V",$compression);$addCentralRecord.=pack("V",$compressedLength);$addCentralRecord.=pack("V",$uncompressedLength);$addCentralRecord.=pack("v",strlen($directoryName));$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("v",0);$addCentralRecord.=pack("V",32);$addCentralRecord.=pack("V",$this->oldOffset);$this->oldOffset=$newOffset;$addCentralRecord.=$directoryName;$this->centralDirectory[]=$addCentralRecord;}
    function getZippedfile(){$data=implode("",$this->compressedData);$controlDirectory=implode("",$this->centralDirectory);return$data.$controlDirectory.$this->endOfCentralDirectory.pack("v",sizeof($this->centralDirectory)).pack("v",sizeof($this->centralDirectory)).pack("V",strlen($controlDirectory)).pack("V",strlen($data))."\x00\x00";}
    function forceDownload($archiveName){$headerInfo='';if(ini_get('zlib.output_compression')){ini_set('zlib.output_compression','Off');}$data=$this->getZippedFile();header("Pragma:public");header("Expires:0");header("Cache-Control:must-revalidate,post-check=0,pre-check=0");header("Cache-Control:private",false);header("Content-Type:application/zip");header("Content-Disposition:attachment;filename={$archiveName};");header("Content-Transfer-Encoding:binary");header("Content-Length:".strlen($data));print("$data");exit;}
}

$data=array(
    'guid'         => '',
    'appid'        => '',
    'shortname'    => uniqid('script'),
    'name'         => 'Compiled User Script',
    'description'  => '',
    'creator'      => 'Anonymous',
    'homepage'     => '',
    'version'      => '0.1',
    'gametype'      => 'pg',
    'include'      => array(),
    'exclude'      => array(),
    'minVersion'   => '3.5',
    'maxVersion'   => '4.99.*',
    'updateHomeUrl'    => '',
    'changelogHomeUrl' => '',
    'iconUrl'      => 'content/resources/main.ico',
    'crMinVersion' => '5.0.0.0',
);

###############################################################################
# code for rendering the UI
###############################################################################

function jscompressEnable() {
    $files = file_array('.');
    $have_sh = false;
    $have_jar = false;
    $comp = "./yuicompressor";
    
    foreach ($files as $file) {
        if ($file == "./compress.sh") {
            $have_sh = true;
        }
        if (strncmp($file, $comp, strlen($comp)) == 0 && substr($file, strlen($file) - 4, 4) == ".jar") {
            $have_jar = true;
        }
    }

    return $have_sh && $have_jar;
}

###############################################################################
# code to create shared components
###############################################################################

function createChangelog($file, $path, $keep) {
    $content = file_get_contents($path.$file);

    if ('AIO' != $keep) $skip[] = 'AIO'; else $skip[] = '!AIO';
    if ('MIO' != $keep) $skip[] = 'MIO'; else $skip[] = '!MIO';
    if ('SIO' != $keep) $skip[] = 'SIO'; else $skip[] = '!SIO';

    // skip these tags
    foreach ($skip as $tag) {
        $o = '['.$tag.']';
        $c = '[/'.$tag.']';

        while (($p1 = strpos(strtoupper($content), strtoupper($o))) != 0 &&  ($p2 = strpos(strtoupper($content), strtoupper($c))) != 0 && $p2 > $p1) {
            $content = substr($content, 0, $p1) . substr($content, $p2 + strlen($c));
        }
    }

    // keep these tags, but without[<foo>]
    $o = '['.$keep.']';
    $c = '[/'.$keep.']';

    while (($p1 = strpos(strtoupper($content), strtoupper($o))) != 0 &&  ($p2 = strpos(strtoupper($content), strtoupper($c))) != 0 && $p2 > $p1) {
        $content = substr($content, 0, $p1) . substr($content, $p1 + strlen($o), $p2 - $p1 - strlen($o)) . substr($content, $p2 + strlen($c));
    }

    return $content;
}

function createChangelogHtml($changelog) {
    global $data;
    // TODO: format this a little bit nicer :-)
    $xml = new SimpleXMLElement($changelog);
    $tmpl=file_get_contents("changelog.html.in");

    $cl = "";

    // heading
    $cl .= "<h2>Willkommen bei " . $data['shortname'] . " " . $data['version'] . "</h2>";
    $cl .= "<p>" . $xml->description . "</p>";

    // features
    $cl .= "<h3>Highlights</h3>\n";
    $cl .= "<ul>\n";
    foreach ($xml->features->feature as $feature) {
        $cl .= "<li>" . $feature . "</li>\n";
    }
    $cl .= "</ul>\n";

    // current version
    $cl .= "<h3>Neuerungen</h3>\n";
    $cl .= "<p><b>Version " . $xml->changelog->current->script->version . "</b> (" . $xml->changelog->current->script->date ."):</p>\n";
    $cl .= "<ul>\n";
    foreach ($xml->changelog->current->script->changes->change as $change) {
        $cl .= "<li>" . $change . "</li>\n";
    }
    $cl .= "</ul>\n";
    $cl .= "<p>Also schnell aktualisieren :)</p>\n";

    // history
    $cl .= "<h3>###### History ######</h3>\n";
    foreach ($xml->changelog->history->script as $old) {
        $cl .= "<b>" . $old->version . "</b>";
        if ($old->date) {
            $cl .= " (" . $old->date . ")";
        }
        $cl .= "<ul>";
        foreach ($old->changes->change as $change) {
            $cl .= "<li>" . $change . "</li>\n";
        }
        $cl .= "</ul>";
    }

    $ret = str_replace("**##include_changelog##**", $cl, $tmpl);
    
    return $ret;
}

function create_preferences_js($jsifiles) {
    $prefs_content=<<<EOF
var \$gametypeScriptGuid = "{\$guid}";
var \$gametypeScriptName = "\$shortname";
var \$gametypeScriptVersion = "\$version";
var \$gametypeScriptIncludes = function(href) { return \$include; };
var \$gametypeScriptExcludes = function(href) { return \$exclude; };

var \$gametypeScriptFiles = [
**##include_other_scripts##**
{ 'main': true, 'name': 'main.js' },
];
EOF;

    $cinc = "";
    $includejsi = "{ 'main': false, 'name': '**##filename##**' },\n";
    foreach ($jsifiles as $jsi) {
        $c = str_replace("**##filename##**", $jsi, $includejsi);
        $cinc .= $c;
    }
    return str_replace("**##include_other_scripts##**", $cinc, $prefs_content);
}

###############################################################################
# code to generate a firefox extension (.xpi)
###############################################################################

function add_default_files_to_xpi($jsifiles) {
    global $ff_rt_path;
    global $ff_build_path;
    
    $xpi = array();

    // generate 'preferences.js' file
    $xpi['content/preferences.js'] = create_preferences_js($jsifiles);

    // include template files
    $xpi['chrome.manifest']=file_get_contents($ff_build_path . "chrome.manifest.in");
    $xpi['install.rdf']=file_get_contents($ff_build_path . "install.rdf.in");
    $xpi['content/script-overlay.xul']=file_get_contents($ff_build_path . "script-overlay.xul.in");

    // include '*.js' files
    $xpi['content/prefman.js']=file_get_contents($ff_rt_path . "prefman.js");
    $xpi['content/xmlhttprequester.js']=file_get_contents($ff_rt_path . "xmlhttprequester.js");
    $xpi['content/runtime.js']=file_get_contents($ff_rt_path . "runtime.js");

    return $xpi;
}

function create_rdf_file() {
    global $ff_build_path;
    return file_get_contents($ff_build_path . "script.rdf.in");
}

function createChangelogFirefox($changelog) {
    global $ff_build_path;

    $xml = new SimpleXMLElement($changelog);
    $tmpl = file_get_contents($ff_build_path . "changelog.xml.in");

    $cl = "";
    foreach ($xml->changelog->current->script->changes->change as $change) {
        $cl .= "<p> - " . $change . "</p>\n";
    }
    
    $ret = str_replace("**##include_version##**", $xml->changelog->current->script->version, $tmpl);
    $ret = str_replace("**##include_changelog##**", $cl, $ret);
    return $ret;
}

function createXpi($content, $data, $changelogData, $files, $imgFiles) {
    global $dbg;
    global $changelog;
    global $imagepath;

    //stuff the files that will go in the xpi into an array
    $xpi = array();

    $jsifiles = array();

    foreach ($files as $filep) {
        $fileparts=preg_split('/\//', $filep);
        $file = $fileparts[sizeof($fileparts)-1];
        
        $fullfile = $content.$file;
        $f = readAndCompress($filep, $retval);
        if ($retval != 0) {
            $ret['err'] = "YuiCompressor Error at file:".$file;
            return $ret;
        }
        $xpi[$fullfile] = $f;

        if (substr($file , strrpos($file, '.') +1) == "jsi") {
            $jsifiles[] = $file;
        }

        // remove hints to full version in case of a light version
        $cleantcontent = str_replace($data['origname'], $data['shortname'], $xpi[$fullfile]);
        $cleanname = str_replace($data['origname'], $data['shortname'], $fullfile);
        unset($xpi[$fullfile]);
        $xpi[$cleanname] = $cleantcontent;
    }

    foreach ($imgFiles as $filep) {
        $fileparts=preg_split('/\//', $filep);
        $file = $fileparts[sizeof($fileparts)-1];
        $fullfile = $content."resources/".$file;
        $xpi[$fullfile] = file_get_contents($filep);
    }

    if ($dbg == 1) {
        print '<pre>'; var_dump(array_keys($xpi)); print '</pre>';
    }

    $xpi = array_merge($xpi, add_default_files_to_xpi($jsifiles));

    if ($dbg == 1) {
        print '<pre>'; var_dump(array_keys($xpi)); print '</pre>';
    }


    foreach (array_keys($xpi) as $k) {
        $xpi[$k]=insertValues($xpi[$k], $data);
    }

    $xpiFile=new CreateZip();
    $xpiFile->addDirectory('chrome/');
    foreach ($xpi as $k=>$v) {
        // ignore changelog file
        if (strpos($k, $changelog) == 0) {
            $xpiFile->addFile($v, $k);
        }
    }

    $ret['name'] = $data["shortname"];
    $ret['file'] = $xpiFile->getZippedfile();
    $ret['rdf'] = insertValues(create_rdf_file(), $data);
    $ret['xml'] = createChangelogFirefox($changelogData);

    return $ret;
}

###############################################################################
# code to generate a chrome extension (.crx)
###############################################################################

function add_default_files_to_crx($jsifiles) {
    global $cr_rt_path;
    global $cr_build_path;
    
    $crx = array();

    // generate 'preferences.js' file
    $crx['preferences.js'] = create_preferences_js($jsifiles);

    // include template files
    $crx['manifest.json']=file_get_contents($cr_build_path . "manifest.json.in");

    // include key files
    $crx['chrome.pem']=file_get_contents($cr_build_path . $data['gametype'] . "/chrome.pem");

    // include runtime files
    $crx['background.html']=file_get_contents($cr_rt_path . "background.html");
    $crx['runtime.js']=file_get_contents($cr_rt_path . "runtime.js");
    $crx['script.js']=file_get_contents($cr_rt_path . "script.js");

    return $crx;
}

function create_chrome_xml_file() {
    global $cr_build_path;
    return file_get_contents($cr_build_path . "script.xml.in");
}

function createCrx($data, $files, $imgFiles) {
    global $dbg;
    global $cr_build_path;

    //stuff the files that will go in the crx into an array
    $crx = array();

    $jsifiles = array();

    foreach ($files as $filep) {
        $fileparts=preg_split('/\//', $filep);
        $file = $fileparts[sizeof($fileparts)-1];

        $f = readAndCompress($filep, $retval);
        if ($retval != 0) {
            $ret['err'] = "YuiCompressor Error at file:".$file;
            return $ret;
        }
        $crx[$file] = $f;

        if (substr($file , strrpos($file, '.') +1) == "jsi") {
            $jsifiles[] = $file;
        }

        // remove hints to full version in case of a light version
        $cleantcontent = str_replace($data['origname'], $data['shortname'], $crx[$file]);
        $cleanname = str_replace($data['origname'], $data['shortname'], $file);
        unset($crx[$file]);
        $crx[$cleanname] = $cleantcontent;
    }

    foreach ($imgFiles as $filep) {
        $fileparts=preg_split('/\//', $filep);
        $file = $fileparts[sizeof($fileparts)-1];
        $fullfile = "resources/".$file;
        $crx[$fullfile] = file_get_contents($filep);
    }

    if ($dbg == 1) {
        print '<pre>'; var_dump(array_keys($crx)); print '</pre>';
    }

    $crx = array_merge($crx, add_default_files_to_crx($jsifiles));

    if ($dbg == 1) {
        print '<pre>'; var_dump(array_keys($crx)); print '</pre>';
    }

    foreach (array_keys($crx) as $k) {
        $crx[$k]=insertValues($crx[$k], $data);
    }

    $crxFile=new CreateZip();
    foreach ($crx as $k=>$v) {
        // ignore changelog file
        $crxFile->addFile($v, $k);
    }

    /************ sign the .crx ***************/
    $key = $cr_build_path.$data['gametype'] . '/chrome.pem';
    $ziptmp = tempnam(sys_get_temp_dir(), 'chrome.tmp');
    $sigtmp = tempnam(sys_get_temp_dir(), 'signature.tmp');
    $keytmp = tempnam(sys_get_temp_dir(), 'key.tmp');
    
    $crxFile = $crxFile->getZippedfile();
    file_put_contents($ziptmp, $crxFile);

    exec('openssl sha1 -sign '.$key.' -out '.$sigtmp.' '.$ziptmp, $out, $retval);
    if ($retval != 0) {
        $ret['err'] = "Error signing file";
        return $ret;
    }
    $signature = file_get_contents($sigtmp);

    exec('openssl rsa -pubout -inform PEM -outform DER -in '.$key.' -out '.$keytmp, $out, $retval);
    if ($retval != 0) {
        $ret['err'] = "Error creating DER key";
        return $ret;
    }
    $derkey = file_get_contents($keytmp);

    $hdr = 'Cr24'.pack('V*', 2 /* version */, strlen($derkey), strlen($signature)).$derkey.$signature;

    $ret['name'] = $data["shortname"];
    $ret['file'] = $hdr.$crxFile;
    $ret['xml'] = insertValues(create_chrome_xml_file(), $data);

    return $ret;
}

###############################################################################
# code to generate a user script (.user.js)
###############################################################################

function createUserScript($data, $files, $imgFiles) {
    global $us_rt_path;
    $script = array();

    $scriptcontent=<<<EOF
// ==UserScript==
// @name           \$shortname
// @version        \$version
// @namespace      \$homepage
// @author         \$creator
// @description    \$description
**##include_resources##**
**##include_includes##**
**##include_excludes##**
// ==/UserScript==

var \$gametypeScriptName = "\$shortname";
var \$gametypeScriptVersion = "\$version";


EOF;

    $resources = "";
    $includes = "";
    $excludes = "";

    foreach ($imgFiles as $filep) {
        $fileparts=preg_split('/\//', $filep);
        $file = $fileparts[sizeof($fileparts)-1];
        $resources .= '// @resource       '.$file." ".$data['homepage']."/pics/".$file."\n";
    }
    foreach ($data['include_array'] as $i) {
        $includes .= '// @include        '.$i."\n";
    }
    foreach ($data['exclude_array'] as $i) {
        $excludes .= '// @exclude        '.$i."\n";
    }

    $scriptcontent = str_replace("**##include_resources##**", $resources, $scriptcontent);
    $scriptcontent = str_replace("**##include_includes##**", $includes, $scriptcontent);
    $scriptcontent = str_replace("**##include_excludes##**", $excludes, $scriptcontent);

    $scriptcontent .= file_get_contents($us_rt_path."runtime.js");
    $scriptcontent .= "\n";

    $src = "";

    foreach ($files as $filep) {
        $fileparts=preg_split('/\//', $filep);
        $file = $fileparts[sizeof($fileparts)-1];

        $f = readAndCompress($filep, $retval);
        if ($retval != 0) {
            $script['err'] = "YuiCompressor Error at file:".$file;
            return $script;
        }

        // remove hints to full version in case of a light version
        $f = str_replace($data['origname'], $data['shortname'], $f);
        if (substr($file , strrpos($file, '.') +1) == "jsi") {
            $src .= $f;
        } else if (substr($file , strrpos($file, '.') +1) == "js") {
            $mainScript = $f;
        }
    }
    if ($mainScript) {
        $src = $mainScript . $src;
    }

    $scriptcontent .= $src;

    $script['script'] = insertValues($scriptcontent, $data);
    return $script;
}

###############################################################################
# generate the resulting .zip file for a certain script type (aio, mio, etc.)
###############################################################################

function create_script_type($fullZip, $files, $imgFiles, $changelogTag) {
    global $content;
    global $path;
    global $data;
    global $changelog;
    global $changelogpath;

    $name = $data['shortname'];
    $md5 = md5($name);

    // create changelog
    $changelogData = createChangelog($changelog, $changelogpath . $data['gametype'] . '/', $changelogTag);

    // create firefox stuff
    if ($_POST["build_ff"] == "yes") {
        $xpiData = createXpi($content, $data, $changelogData, $files, $imgFiles);
        if ($xpiData['err']) {
            $fullZip = new CreateZip();
            $fullZip->addFile($xpiData['err'], $name."Error.txt");
            return $fullZip;
        }
    }

    // create chrome stuff
    if ($_POST["build_chrome"] == "yes") {
        $crxData = createCrx($data, $files, $imgFiles);
        if ($crxData['err']) {
            $fullZip = new CreateZip();
            $fullZip->addFile($crxData['err'], $name."Error.txt");
            return $fullZip;
        }
    }

    // create user script stuff
    if ($_POST["build_script"] == "yes") {
        $userScriptData = createUserScript($data, $files, $imgFiles);
        if ($userScriptData['err']) {
            $fullZip = new CreateZip();
            $fullZip->addFile($userScriptData['err'], $name."Error.txt");
            return $fullZip;
        }
    }

    // add the files
    $fullZip->addFile($changelogData, $md5."/changelog/".$name."_changelog.xml");
    $fullZip->addFile(createChangelogHtml($changelogData), $md5."/changelog/".$name.".html");

    if ($xpiData) {
        $fullZip->addFile($xpiData['file'], $md5."/xpi/".$name.".xpi");
        $fullZip->addFile($xpiData['rdf'], $md5."/xpi/".$name.".rdf");
        $fullZip->addFile($xpiData['xml'], $md5."/xpi/".$name.".xml");
    }

    if ($crxData) {
        $fullZip->addFile($crxData['file'], $md5."/crx/".$name.".crx");
        $fullZip->addFile($crxData['xml'], $md5."/crx/".$name.".xml");
    }

    if ($userScriptData) {
        $fullZip->addFile($userScriptData['script'], $md5."/user_script/".$name.".user.js");
    }

    return $fullZip;
}

###############################################################################
# generate the resulting .zip file
###############################################################################

function create_script() {
    global $path;
    global $imagepath;
    global $data;
    
    $fullZip = new CreateZip();
    # NOTE: include common folder first to allow deriving from these classes!
    $common = file_array($path . "common" . "/");
    $jsfiles = file_array($path . $data['gametype'] . "/");
    $files = array_merge($common, $jsfiles);

    $commonImg = file_array($imagepath . "common" . "/");
    $gameImg = file_array($imagepath . $data['gametype'] . "/");
    $imgFiles = array_merge($commonImg, $gameImg);

    $data['origname'] = $data['shortname'];

    $md5 = md5($data['shortname']);
    $data['updateUrl']   = $data['updateHomeUrl'] . $md5 . '/xpi/';;
    $data['crUpdateUrl'] = $data['updateHomeUrl'] . $md5 . '/crx/';
    $data['changelogUrl'] = $data['changelogHomeUrl'] . $md5 . '/xpi/';

    // create all files for allin1
    if ($_POST["build_aio"] == "yes") {
        $fullZip = create_script_type($fullZip, $files, $imgFiles, 'AIO');
    }

    // create all files for manyin1
    foreach (array('bot.jsi', 'gotya.jsi', 'donations.jsi') as $v) {
        foreach ($files as $i => $value) {
            if ($v == $files[$i]) {
                unset($files[$i]);
            }
        }
    }
    $data['shortname'] = preg_replace('/All/', 'Many', $data['origname']);
    $md5 = md5($data['shortname']);
    $data['updateUrl']   = $data['updateHomeUrl'] . $md5 . '/xpi/';;
    $data['crUpdateUrl'] = $data['updateHomeUrl'] . $md5 . '/crx/';
    $data['changelogUrl'] = $data['changelogHomeUrl'] . $md5 . '/xpi/';
    
    if ($_POST["build_mio"] == "yes") {
        $fullZip = create_script_type($fullZip, $files, $imgFiles, 'MIO');
    }

    // create all files for somein1
    foreach (array('bottlesell.jsi', 'supersearch.jsi', 'dailytask.jsi', 'lockmanager.jsi', 'adblock.jsi') as $v) {
        foreach ($files as $i => $value) {
            if ($v == $files[$i]) {
                unset($files[$i]);
            }
        }
    }

    $data['shortname'] = preg_replace('/All/', 'Some', $data['origname']);
    $md5 = md5($data['shortname']);
    $data['updateUrl']   = $data['updateHomeUrl'] . $md5 . '/xpi/';;
    $data['crUpdateUrl'] = $data['updateHomeUrl'] . $md5 . '/crx/';
    $data['changelogUrl'] = $data['changelogHomeUrl'] . $md5 . '/xpi/';

    if ($_POST["build_sio"] == "yes") {
        $fullZip = create_script_type($fullZip, $files, $imgFiles, 'SIO');
    }

    return $fullZip;
}

###############################################################################
# compiler main entry point
###############################################################################

$rev = exec("svn info|grep Revision|sed 's/Revision: //g'");

if (!empty($_POST)) {

    if ($dbg == 1) {
        error_reporting(E_ALL);
        ini_set("display_errors", 1);
        ini_set("docref_root", "http://nz2.php.net/manual/");
        ini_set("docref_ext", ".php");
    } else {
        error_reporting(0);
    }

    //undo magic quotes if necessary
    if (get_magic_quotes_gpc()) {
        $_POST=array_map('stripslashes', $_POST);
    }
    
    foreach (array(
                   'guid', 'appid', 'creator', 'homepage', 'version', 'gametype', 'minVersion', 'maxVersion', 'updateHomeUrl', 'changelogHomeUrl', 'iconUrl', 'crMinVersion'
                   ) as $k) {
        if (!empty($_POST[$k])) $data[$k]=$_POST[$k];
    }

    $data = getHeaderData($changelogpath.$data['gametype']."/metadata");

    $fullZip = create_script();
    $fullZip->forceDownload($data['gametype']."Script.zip");

} else {

?>
<!doctype html public '-//w3c//dtd xhtml 1.1//en' 'http://www.w3.org/tr/xhtml11/dtd/xhtml11.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en'>
<head>
<meta http-equiv='content-type' content='text/html; charset=UTF-8' />
<title>Pennergame AllInOne Script compiler</title>
<style type='text/css'>
body {
    font-family: Helvetica, Verdana, Sans-Serif;
}
input, textarea {
    width: 90%;
}
</style>
</head>
<script language="javascript" type="text/javascript">
<!--
function apply_build_targets() {
    var ff = document.getElementById('bt_build_ff');
    var us = document.getElementById('bt_build_script');
    var ch = document.getElementById('bt_build_chrome');
    var d = !ff.checked;
    document.getElementById('ff_minVersion').disabled = d;
    document.getElementById('ff_maxVersion').disabled = d;
    var d = !ch.checked;
    document.getElementById('cr_minVersion').disabled = d;
}
-->
</script>
<body>
<? jscompressEnable(); ?>
<h2 style="text-align: center;">Pennergame AllInOne Script compiler</h2>
<form method='post'<? if ($dbg == 0) printf(' target="builder"'); ?>>
<table width='100%'>
<col width='20%' />
<col width='80%' />
<tr><td><b><center>Gametype</center></b></td><td></td></tr>
<tr>
    <td>Pennergame</td>
    <td><input type="radio" name="gametype" value="pg" style="text-align:left; width:20px;" checked /></td>
</tr>
<tr>
    <td>Knastv&ouml;gel</td>
    <td><input type="radio" name="gametype" value="kv" style="text-align:left; width:20px;"></td>
</tr>
<tr><td><b><center>Build Versions</center></b></td><td></td></tr>
<tr>
    <td>Create AllIn1</td>
    <td><input type='checkbox' id='bv_build_aio' name='build_aio' value='yes' style="text-align:left; width:20px;" checked /></td>
</tr>
<tr>
    <td>Create ManyIn1</td>
    <td><input type='checkbox' id='bv_build_mio' name='build_mio' value='yes' style="text-align:left; width:20px;" checked /></td>
</tr>
<tr>
    <td>Create SomeIn1</td>
    <td><input type='checkbox' id='bv_build_sio' name='build_sio' value='yes' style="text-align:left; width:20px;" checked /></td>
</tr>
<tr><td><b><center>Build Targets</center></b></td><td></td></tr>
<tr>
    <td>Create Firefox Extension</td>
    <td><input type='checkbox' id='bt_build_ff' name='build_ff' value='yes' style="text-align:left; width:20px;" checked onclick="apply_build_targets()" /></td>
</tr>
<tr>
    <td>Create Chrome Extension</td>
    <td><input type='checkbox' id='bt_build_chrome' name='build_chrome' value='yes' style="text-align:left; width:20px;" checked onclick="apply_build_targets()" /></td>
</tr>
<tr>
    <td>Create User Script</td>
    <td><input type='checkbox' id='bt_build_script' name='build_script' value='yes' style="text-align:left; width:20px;" checked onclick="apply_build_targets()" /></td>
</tr>
<tr><td><b><center>General Settings</center></b></td><td></td></tr>
<tr>
    <td>Compression:</td>
    <td><input type='checkbox' id='all_compression' name='compression' value='yes' style="text-align:left; width:20px;"
        <? if (jscompressEnable() == true) { printf("checked"); } ?> <? if (jscompressEnable() != true) { printf("disabled"); } ?>
        /></td>
</tr>
<tr>
    <td>Revision:</td>
    <td><input type='text' disabled value='<? printf($rev) ?>'></td>
</tr>
<tr><td><b><center>Firefox Settings</center></b></td><td></td></tr>
<tr>
    <td>Firefox min version:</td>
    <td><input type='text' id='ff_minVersion' name='minVersion' value='3.5' /></td>
</tr>
<tr>
    <td>Firefox max version:</td>
    <td><input type='text' id='ff_maxVersion' name='maxVersion' value='4.99.*' /></td>
</tr>
<tr><td><b><center>Chrome Settings</center></b></td><td></td></tr>
<tr>
    <td>Chrome min version:</td>
    <td><input type='text' id='cr_minVersion' name='crMinVersion' value='5.0.0.0' /></td>
</tr>
<tr>
    <td></td>
    <td><input type='submit' value='Compile' /></td>
</tr>
<tr>
    <td colspan="2"><br><span>Note: In order to compress your scripts you need to get a copy of <a href="http://developer.yahoo.com/yui/compressor/">YuiCompressor</a> and copy it to the compilers folder. Additionally the file compress.sh from SVN is needed.<span></td>
</tr>
</table>
</form>

<iframe src='about:blank' width='98%' height='0' frameborder='0' name='builder'></iframe>

<script language="javascript" type="text/javascript">
<!--
apply_build_targets();
-->
</script>
</body>
</html>
<? } ?>

