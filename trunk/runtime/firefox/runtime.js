/* 
 * @filename runtime.js
 * @author Jan Biniok <jan@biniok.net>
 * @author Thomas Rendelmann <thomas@rendelmann.net>
 * @licence GPL v2
 */

function alert(msg) {
  Cc["@mozilla.org/embedcomp/prompt-service;1"]
    .getService(Ci.nsIPromptService)
    .alert(null, $gametypeScriptName + " alert", msg);
}

function $gametypeScript() {
  this.observers = [];

  this.downloadURL = null; // Only for scripts not installed
  this.tempFile = null; // Only for scripts not installed
  this.basedir = null;
  this.filename = null;

  this.name = null;
  this.namespace = null;
  this.description = null;
  this.enabled = true;
  this.includes = [];
  this.excludes = [];
  this.requires = [];
  this.resources = [];
  this.unwrap = false;
}

const $gametypegmSvcFilename = Components.stack.filename;

var $gametypeScript_gmCompiler={

// getUrlContents adapted from Greasemonkey Compiler
// http://www.letitblog.com/code/python/greasemonkey.py.txt
// used under GPL permission
//
// most everything else below based heavily off of Greasemonkey
// http://greasemonkey.devjavu.com/
// used under GPL permission

generateDataURI : function(filewpath) {
        function urlToPath (aPath) {
            if (!aPath || !/^file:/.test(aPath))
                return ;
            var rv;
            var ph = Components.classes["@mozilla.org/network/protocol;1?name=file"]
            .createInstance(Components.interfaces.nsIFileProtocolHandler);
            rv = ph.getFileFromURLSpec(aPath).path;
            return rv;
        }

        function chromeToPath (aPath) {
            if (!aPath || !(/^chrome:/.test(aPath)))
                return; //not a chrome url
            var rv;
   
            var ios = Components.classes['@mozilla.org/network/io-service;1'].getService(Components.interfaces["nsIIOService"]);
            var uri = ios.newURI(aPath, "UTF-8", null);
            var cr = Components.classes['@mozilla.org/chrome/chrome-registry;1'].getService(Components.interfaces["nsIChromeRegistry"]);
            rv = cr.convertChromeURL(uri).spec;

            if (/^file:/.test(rv))
                rv = urlToPath(rv);
            else
                rv = urlToPath("file://"+rv);
            
            return rv;
        }

        var file = Components.classes["@mozilla.org/file/local;1"].createInstance(Components.interfaces.nsILocalFile);
        file.initWithPath(chromeToPath(filewpath));
        var contentType = Components.classes["@mozilla.org/mime;1"]
                              .getService(Components.interfaces.nsIMIMEService)
                              .getTypeFromFile(file);
        var inputStream = Components.classes["@mozilla.org/network/file-input-stream;1"]
                              .createInstance(Components.interfaces.nsIFileInputStream);

        inputStream.init(file, 0x01, 0600, 0);
        var stream = Components.classes["@mozilla.org/binaryinputstream;1"]
                              .createInstance(Components.interfaces.nsIBinaryInputStream);
        stream.setInputStream(inputStream);
        var encoded = btoa(stream.readBytes(stream.available()));
        return "data:" + contentType + ";base64," + encoded;
},

getUrlContents: function(aUrl){
    var    ioService=Components.classes["@mozilla.org/network/io-service;1"]
        .getService(Components.interfaces.nsIIOService);
    var    scriptableStream=Components
        .classes["@mozilla.org/scriptableinputstream;1"]
        .getService(Components.interfaces.nsIScriptableInputStream);
    var unicodeConverter=Components
        .classes["@mozilla.org/intl/scriptableunicodeconverter"]
        .createInstance(Components.interfaces.nsIScriptableUnicodeConverter);
    unicodeConverter.charset="UTF-8";

    var    channel=ioService.newChannel(aUrl, null, null);
    var    input=channel.open();
    scriptableStream.init(input);
    var    str=scriptableStream.read(input.available());
    scriptableStream.close();
    input.close();

    try {
        return unicodeConverter.ConvertToUnicode(str);
    } catch (e) {
        return str;
    }
},

isGreasemonkeyable: function(url) {
    var scheme=Components.classes["@mozilla.org/network/io-service;1"]
        .getService(Components.interfaces.nsIIOService)
        .extractScheme(url);
    return (
        (scheme == "http" || scheme == "https" || scheme == "file") &&
        !/hiddenWindow\.html$/.test(url)
    );
},

contentLoad: function(e) {
    var unsafeWin=e.target.defaultView;
    if (unsafeWin.wrappedJSObject) unsafeWin=unsafeWin.wrappedJSObject;

    var unsafeLoc=new XPCNativeWrapper(unsafeWin, "location").location;
    var href=new XPCNativeWrapper(unsafeLoc, "href").href;

    if (
        $gametypeScript_gmCompiler.isGreasemonkeyable(href)
        && $gametypeScriptIncludes(href)
        && $gametypeScriptExcludes(href)
    ) {
        var scripts = [];
        var requires = [];

        for (var i = 0; i < $gametypeScriptFiles.length; i++) {
            var script = new $gametypeScript();
            script.name = $gametypeScriptFiles[i].name
            script.fileURL = 'chrome://' + $gametypeScriptName + '/content/' + script.name;;
            script.textContent = $gametypeScript_gmCompiler.getUrlContents(script.fileURL);
            script.namespace = $gametypeScriptName;
            if ($gametypeScriptFiles[i].main) {
                script.requires = requires;
                scripts.push(script);
            } else {
                requires.push(script);
            }
        }

        $gametypeScript_gmCompiler.injectScript(scripts, href, unsafeWin, window);
    }
},

injectScript: function(scripts, url, unsafeContentWin, chromeWin) {

    var sandbox, script, logger, storage, xmlhttpRequester, console;
    var safeWin=new XPCNativeWrapper(unsafeContentWin);

    for (var i = 0; script = scripts[i]; i++) {
        sandbox=new Components.utils.Sandbox(safeWin);
        storage=new $gametypeScript_ScriptStorage();

        xmlhttpRequester=new $gametypeScript_xmlhttpRequester(unsafeContentWin, window, url);
        logger = new $gametypeScript_ScriptLogger(script);
        console = new $gametypeScript_console(script);

        sandbox.window=safeWin;
        sandbox.document=sandbox.window.document;
        sandbox.unsafeWindow=unsafeContentWin;

        // patch missing properties on xpcnw
        sandbox.XPathResult=Components.interfaces.nsIDOMXPathResult;

        // add our own APIs
        sandbox.GM_addStyle=function(css) { $gametypeScript_gmCompiler.addStyle(sandbox.document, css) };
        sandbox.GM_deleteValue=$gametypeScript_gmCompiler.hitch(storage, "deleteValue");
        sandbox.GM_setValue=$gametypeScript_gmCompiler.hitch(storage, "setValue");
        sandbox.GM_getValue=$gametypeScript_gmCompiler.hitch(storage, "getValue");
        sandbox.GM_listValues=$gametypeScript_gmCompiler.hitch(storage, "listValues");
        sandbox.GM_openInTab=$gametypeScript_gmCompiler.hitch(this, "openInTab", unsafeContentWin);
        sandbox.GM_xmlhttpRequest=$gametypeScript_gmCompiler.hitch(xmlhttpRequester, "contentStartRequest");
        sandbox.GM_log=$gametypeScript_gmCompiler.hitch(logger, "log");
        sandbox.GM_getResourceURL = $gametypeScript_gmCompiler.hitch(this, "getResourceURL");
        sandbox.console = console;

        //unsupported
        sandbox.GM_registerMenuCommand=function(){};
        sandbox.GM_getResourceText=function(){};

        //add more, not GM related, functions
        sandbox.MF_getTab =$gametypeScript_gmCompiler.hitch(this, "getTab", sandbox.document);
        sandbox.MF_saveTab =$gametypeScript_gmCompiler.hitch(this, "saveTab", sandbox.document);
        sandbox.MF_getTabs =$gametypeScript_gmCompiler.hitch(this, "getTabs", sandbox.document);
        sandbox.MF_checkFirstRun = $gametypeScript_gmCompiler.hitch(this, "checkFirstRun", unsafeContentWin);
        sandbox.MF_getVersion = $gametypeScript_gmCompiler.hitch(this, "getVersion");
        sandbox.MF_copyToClipboard = $gametypeScript_gmCompiler.hitch(this, "copyToClipboard", unsafeContentWin);

        sandbox.__proto__=sandbox.window;

        var contents = script.textContent;

        var requires = [];
        var offsets = [];
        var offset = contents.split("\n").length;

        script.requires.forEach(function(req){
                                    var contents = req.textContent;
                                    var lineCount = contents.split("\n").length;
                                    requires.push(contents);
                                    offset += lineCount;
                                    offsets.push(offset);
                                })
        script.offsets = offsets;

        var scriptSrc = "\n" + // error line-number calculations depend on these
            contents +
            "\n" +
            requires.join("\n") +
            "\n";
        if (!script.unwrap)
            scriptSrc = "(function(){"+ scriptSrc +"})()";
        if (!this.evalInSandbox(scriptSrc, url, sandbox, script) && script.unwrap) {
            this.evalInSandbox("(function(){"+ scriptSrc +"})()",
                               url, sandbox, script); // wrap anyway on early return
        }
    }
},

evalInSandbox: function(code, codebase, sandbox, script) {
    if (!(Components.utils && Components.utils.Sandbox)) {
        var e = new Error("Could not create sandbox.");
        $gametypeScript_gmCompiler.logError(e, 0, e.fileName, e.lineNumber);
    } else {
        try {
            // workaround for https://bugzilla.mozilla.org/show_bug.cgi?id=307984
            var lineFinder = new Error();
            Components.utils.evalInSandbox(code, sandbox);
        } catch (e) {
            if ("return not in function" == e.message) // pre-0.8 GM compat:
                return false; // this script depends on the function enclosure

            // try to find the line of the actual error line
            var line = e.lineNumber;
            if (4294967295 == line) {
                // Line number is reported as max int in edge cases.  Sometimes
                // the right one is in the "location", instead.  Look there.
                if (e.location && e.location.lineNumber) {
                    line = e.location.lineNumber;
                } else {
                    // Reporting max int is useless, if we couldn't find it in location
                    // either, forget it.  Value of 0 isn't shown in the console.
                    line = 0;
                }
            }

            if (line) {
                var err = this.findError(script, line - lineFinder.lineNumber - 1);
                $gametypeScript_gmCompiler.logError(
                            e, // error obj
                            0, // 0 = error (1 = warning)
                            err.uri,
                            err.lineNumber
                            );
            } else {
                $gametypeScript_gmCompiler.logError(
                            e, // error obj
                            0, // 0 = error (1 = warning)
                            script.fileURL,
                            0
                            );
            }
        }
    }
    return true; // did not need a (function() {...})() enclosure.
},

uriFromUrl: function(url, baseUrl) {
    var ioService = Components.classes["@mozilla.org/network/io-service;1"]
                                     .getService(Components.interfaces.nsIIOService);
    var baseUri = null;
    if (baseUrl) baseUri = $gametypeScript_gmCompiler.uriFromUrl(baseUrl);
    try {
        return ioService.newURI(url, null, baseUri);
    } catch (e) {
        return null;
    }
},

findError: function(script, lineNumber){
    var start = 0;
    var end = 1;

    for (var i = 0; i < script.offsets.length; i++) {
        end = script.offsets[i];
        if (lineNumber < end) {
            return {
            uri: script.requires[i].fileURL,
                    lineNumber: (lineNumber - start)
                    };
        }
        start = end;
    }

    return {
        uri: script.fileURL,
        lineNumber: (lineNumber - end)
    };
},

openInTab: function(unsafeContentWin, url) {
    var tabBrowser = getBrowser(), browser, isMyWindow = false;
    for (var i = 0; browser = tabBrowser.browsers[i]; i++)
        if (browser.contentWindow == unsafeContentWin) {
            isMyWindow = true;
            break;
        }
    if (!isMyWindow) return;

    var loadInBackground, sendReferrer, referrer = null;
    loadInBackground = tabBrowser.mPrefs.getBoolPref("browser.tabs.loadInBackground");
    sendReferrer = tabBrowser.mPrefs.getIntPref("network.http.sendRefererHeader");
    if (sendReferrer) {
        var ios = Components.classes["@mozilla.org/network/io-service;1"]
                            .getService(Components.interfaces.nsIIOService);
        referrer = ios.newURI(content.document.location.href, null, null);
     }
     tabBrowser.loadOneTab(url, referrer, null, null, loadInBackground);
 },

saveTab: function(doc, sTab) {
    var f = function(tab) {
        tab.pgs = sTab;
    };
    $gametypeScript_gmCompiler.getTabByDoc(doc, f);
},

getTabs: function(doc, cb) {
    var f = function(tabs) {
        var ret = [];
        for (var i=0; i<tabs.length; i++) {
            var t = tabs[i];
            if (typeof t.pgs === 'undefined') t.pgs = {};
            ret.push(t.pgs);
        }
        cb(ret);
    };
    $gametypeScript_gmCompiler.getTabByDoc(null, f);
},

getTab: function(doc, cb) {
    var f = function(t) {
        if (typeof t.pgs === 'undefined') t.pgs = {};
        cb(t.pgs);
    };

    $gametypeScript_gmCompiler.getTabByDoc(doc, f);
},

getTabByDoc: function(doc, cb) {
    var tabs = new Array();
    for (var wm = Components.classes["@mozilla.org/appshell/window-mediator;1"].getService(Components.interfaces.nsIWindowMediator), e = wm.getEnumerator("navigator:browser"); e.hasMoreElements();) {
        var win = e.getNext();
        if (!doc) {
            for (var i = 0; i < win.gBrowser.tabContainer.childNodes.length; i++) {
                tabs.push(win.gBrowser.tabContainer.childNodes[i]);
            }
        } else {
            var idx = win.gBrowser.getBrowserIndexForDocument(doc);
            if (idx > -1) {
                cb(win.gBrowser.tabContainer.childNodes[idx]);
                return;
            }
        }
        // continue checking the rest of the browsers...
    }

    if (!doc) {
        cb(tabs);
    } else {
        // not found
        cb(null);
    }
    return null;
},

getResourceURL : function(resource) {
        if (resource.search(/\.\.\//) != -1 ||
            resource.search(/\/\.\./) != -1 ||
            resource.search(/ /) != -1) {
            throw new Error("Invalid Resource: " + resource);
        }
        var fileURL = 'chrome://' + $gametypeScriptName + '/content/resources/' + resource;
        return $gametypeScript_gmCompiler.generateDataURI(fileURL);
},

apiLeakCheck: function(apiName) {
    var stack = Components.stack;

    do {
        // Valid stack frames for GM api calls are: native and js when coming from
        // chrome:// URLs and the greasemonkey.js component's file:// URL.
        if (2 == stack.language) {
            // NOTE: In FF 2.0.0.0, I saw that stack.filename can be null for JS/XPCOM
            // services. This didn't happen in FF 2.0.0.11; I'm not sure when it
            // changed.
            if (stack.filename != null &&
                stack.filename != $gametypegmSvcFilename &&
                stack.filename.substr(0, 6) != "chrome" &&
                stack.filename.substr(0, 6) != "resour") {
                $gametypeScript_gmCompiler.log("Greasemonkey access violation: unsafeWindow " +
                                      "cannot call " + apiName + ". (" + stack.filename + ")");
                return false;
            }
        }

        stack = stack.caller;
    } while (stack);
    return true;
},

hitch: function(obj, meth) {
    if (!obj[meth]) {
        throw "method '" + meth + "' does not exist on object '" + obj + "'";
    }

    var staticArgs = Array.prototype.splice.call(arguments, 2, arguments.length);

    return function() {
        // make a copy of staticArgs (don't modify it because it gets reused for
        // every invocation).
        var args = Array.prototype.slice.call(staticArgs);

        // add all the new arguments
        Array.prototype.push.apply(args, arguments);

        // invoke the original function with the correct this obj and the combined
        // list of static and dynamic arguments.
        return obj[meth].apply(obj, args);
    };
},

addStyle:function(doc, css) {
    var head, style;
    head = doc.getElementsByTagName('head')[0];
    if (!head) { return; }
    style = doc.createElement('style');
    style.type = 'text/css';
    style.innerHTML = css;
    head.appendChild(style);
},

onLoad: function() {
    var appcontent=window.document.getElementById("appcontent");
    if (appcontent && !appcontent.greased_$gametypeScript_gmCompiler) {
        appcontent.greased_$gametypeScript_gmCompiler=true;
        appcontent.addEventListener("DOMContentLoaded", $gametypeScript_gmCompiler.contentLoad, false);
    }
},

onUnLoad: function() {
    //remove now unnecessary listeners
    window.removeEventListener('load', $gametypeScript_gmCompiler.onLoad, false);
    window.removeEventListener('unload', $gametypeScript_gmCompiler.onUnLoad, false);
    window.document.getElementById("appcontent")
        .removeEventListener("DOMContentLoaded", $gametypeScript_gmCompiler.contentLoad, false);
},

checkFirstRun: function(firstRunCallback, updateCallback) {
    var Prefs = Components.classes["@mozilla.org/preferences-service;1"]
                .getService(Components.interfaces.nsIPrefService);
    Prefs = Prefs.getBranch("extensions." + $gametypeScriptName + "gmCompiler_.");

    var f = function(current){
        var ver = -1, firstrun = true;
        try {
            ver = Prefs.getCharPref("version");
            firstrun = Prefs.getBoolPref("firstrun");
        } catch(e) {
            //nothing
        } finally {
            if (firstrun){
                Prefs.setBoolPref("firstrun",false);
                Prefs.setCharPref("version",current);
                if (firstRunCallback) firstRunCallback(current);
            }

            if (ver!=current && !firstrun){ // !firstrun ensures that this section does not get loaded if its a first run.
                Prefs.setCharPref("version",current);
                if (updateCallback) updateCallback(current, ver);
            }
        }
    };
    $gametypeScript_gmCompiler.getVersion(f);
},

copyToClipboard: function(unsafeContentWin, text) {
    var tc = text.replace(/\n\n/g, '\n');
    // netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");

    var clipboardHelper = Components.classes["@mozilla.org/widget/clipboardhelper;1"]
                         .getService(Components.interfaces.nsIClipboardHelper);
    clipboardHelper.copyString(tc);
},

getVersion: function(callback) {
    var ascope = { };
    var ffid = "-firefox";
    if (typeof(Components.classes["@mozilla.org/extensions/manager;1"]) != 'undefined') {
        var extMan = Components.classes["@mozilla.org/extensions/manager;1"].getService(Components.interfaces.nsIExtensionManager);
        var current = extMan.getItemForID($gametypeScriptGuid).version;
        callback(current + ffid);
        return true;
    }

    if (typeof(Components.utils) != 'undefined' && typeof(Components.utils.import) != 'undefined') {
        Components.utils.import("resource://gre/modules/AddonManager.jsm", ascope);
        ascope.AddonManager.getAddonByID($gametypeScriptGuid, function (addon) { callback(addon.version + ffid); } );
        return true;
    }
    return null;
},

log: function(message, force) {

    var $gametypeScript_consoleService = Components.classes["@mozilla.org/consoleservice;1"]
                                      .getService(Components.interfaces.nsIConsoleService);
    $gametypeScript_consoleService.logStringMessage(message);
},

logError: function(e, opt_warn, fileName, lineNumber) {
  var consoleService = Components.classes["@mozilla.org/consoleservice;1"]
                                  .getService(Components.interfaces.nsIConsoleService);

  var consoleError = Components.classes["@mozilla.org/scripterror;1"]
                                .createInstance(Components.interfaces.nsIScriptError);

  var flags = opt_warn ? 1 : 0;

  // third parameter "sourceLine" is supposed to be the line, of the source,
  // on which the error happened.  we don't know it. (directly...)
  consoleError.init(e.message, fileName, null, lineNumber,
                    e.columnNumber, flags, null);

  consoleService.logMessage(consoleError);
}

}; //object $gametypeScript_gmCompiler

function $gametypeScript_ScriptStorage() {
    this.prefMan=new $gametypeScript_PrefManager();
}
$gametypeScript_ScriptStorage.prototype.deleteValue = function(name) {
    return this.prefMan.remove(name);
}
$gametypeScript_ScriptStorage.prototype.setValue = function(name, val) {
    this.prefMan.setValue(name, val);
}
$gametypeScript_ScriptStorage.prototype.getValue = function(name, defVal) {
    return this.prefMan.getValue(name, defVal);
}
$gametypeScript_ScriptStorage.prototype.listValues = function() {
    return this.prefMan.listValues();
}

function $gametypeScript_ScriptLogger(script) {
  var namespace = script.namespace;

  if (namespace.substring(namespace.length - 1) != "/") {
    namespace += "/";
  }

  this.prefix = [namespace, script.name, ": "].join("");
}

$gametypeScript_ScriptLogger.prototype.log = function(message) {
  $gametypeScript_gmCompiler.log(this.prefix + message, true);
};

function $gametypeScript_console(script) {
  // based on http://www.getfirebug.com/firebug/firebugx.js
  var names = [
    "debug", "warn", "error", "info", "assert", "dir", "dirxml",
    "group", "groupEnd", "time", "timeEnd", "count", "trace", "profile",
    "profileEnd"
  ];

  for (var i=0, name; name=names[i]; i++) {
    this[name] = function() {};
  }

  // Important to use this private variable so that user scripts can't make
  // this call something else by redefining <this> or <logger>.
  var logger = new $gametypeScript_ScriptLogger(script);
  this.log = function() {
    logger.log(
      Array.prototype.slice.apply(arguments).join("\n")
    );
  };
}

$gametypeScript_console.prototype.log = function() {
};

window.addEventListener('load', $gametypeScript_gmCompiler.onLoad, false);
window.addEventListener('unload', $gametypeScript_gmCompiler.onUnLoad, false);
