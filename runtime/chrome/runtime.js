/* 
 * @filename runtime.js
 * @author Jan Biniok <jan@biniok.net>
 * @author Thomas Rendelmann <thomas@rendelmann.net>
 * @licence GPL v2
 */

var MF_tabs = {};

var getStringBetweenTags = function(source, tag1, tag2) {
    var b = source.search(tag1);
    if (b == -1) {
        return "";
    }
    if (!tag2) {
        return source.substr(b + tag1.length);
    }
    var e = source.substr(b + tag1.length).search(tag2);
    if (e == -1) {
        return "";
    }
    return source.substr(b + tag1.length, e);
};

chrome.extension.onRequest.addListener(
    function(request, sender, sendResponse) {
        if (request.method == "xhr") {
            var cb = function(req) { sendResponse({data: req});};
            $gametypeScript_gmCompiler.xmlhttpRequest(request.details, cb);
        } else if (request.method == "openInTab") {
            chrome.tabs.create({ url: request.url});
            sendResponse({});
        } else if (request.method == "getTab") {
            if (typeof sender.tab != 'undefined') {
                if (typeof MF_tabs[sender.tab.id] == 'undefined') MF_tabs[sender.tab.id] = { };
                var tab = MF_tabs[sender.tab.id];
                sendResponse({data: tab});
            } else {
                console.log("unable to deliver tab due to empty tabID");
                sendResponse({data: null});
            }
        } else if (request.method == "getTabs") {
            sendResponse({data: MF_tabs});
        } else if (request.method == "saveTab") {
            if (typeof sender.tab != 'undefined') {
                var tab = {};
                for (var k in request.tab) {
                    tab[k] = request.tab[k];
                };
                MF_tabs[sender.tab.id] = tab;
            } else {
                console.log("unable to save tab due to empty tabID");
            }
            sendResponse({});
        } else if (request.method == "onUpdate") {
            if (typeof sender.tab != 'undefined') {
                updateListener(sender.tab.id, {status: "complete"}, sender.tab);
            } else {
                console.log("unable to run scripts on tab due to empty tabID");
            }
            sendResponse({});
        }
    });

chrome.extension.getVersion = function() {

    if (!chrome.extension.version_) {

        try {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", chrome.extension.getURL('manifest.json'), false);
            xhr.send(null);
            var manifest = JSON.parse(xhr.responseText);

            chrome.extension.version_ = manifest.version;
            chrome.extension.updateurl_ = manifest.update_url;

        } catch (e) {
            console.log(e);
            chrome.extension.version_ = 'unknown';
            chrome.extension.updateurl_ = null;
        }
    }

    return chrome.extension.version_;
};

chrome.extension.getID = function() {
    var p = chrome.extension.getURL('/');
    var ida = p.replace(/\//gi, '').split(':');
    return (ida.length < 2) ? '' : ida[1];
};

var versionCmp = function(v1, v2) {
    var a1 = v1.split('.');
    var a2 = v2.split('.');
    var len = a1.length < a2.length ? a1.length : a2.length;

    for (var i=0; i<len; i++) {
        if (a1.length < len) a1[i] = 0;
        if (a2.length < len) a2[i] = 0;
        if (Number(a1[i]) > Number(a2[i])) {
            return true;
        } else if (Number(a1[i]) < Number(a2[i])) {
            return false;
        }
    }

    return null;
};

chrome.extension.newVersion = function() {

    if (!chrome.extension.newversion_) {

        chrome.extension.getVersion();

        if (chrome.extension.updateurl_) {

            try {
                var xhr = new XMLHttpRequest();
                xhr.open("GET", chrome.extension.updateurl_, false);
                xhr.send();

                var a1 = "<app appid='"+chrome.extension.getID()+"'>";
                var a2 = "</app>";
                var a = getStringBetweenTags(xhr.responseText, a1, a2);
                
                var t1 = "codebase='";
                var t2 = "'";
                var t = getStringBetweenTags(a, t1, t2);

                var v1 = "version='";
                var v2 = "'";
                var v = getStringBetweenTags(a, v1, v2);

                chrome.extension.newversion_ = v;

                if (versionCmp(chrome.extension.newversion_, chrome.extension.version_)) {
                    console.log("My version: " + chrome.extension.version_ + " - Remote version:" + chrome.extension.newversion_ + "; trigger update!");
                    chrome.tabs.create({ url: t});
                 }

            } catch (e) {
                console.log(e);
                chrome.extension.newversion_ = "unknown";
            }
        }
    }

    return chrome.extension.newversion_;
};

function Script() {
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

var compilerInit = function() {

    this.gm_emu = function() {

        this.currentTab = null;
        this.window = null;

        this.GM_addStyle = function(css) {
            var style = document.createElement('style');
            style.textContent = css;
            document.getElementsByTagName('head')[0].appendChild(style);
        };

        this.GM_deleteValue = function(name) {
            localStorage.removeItem(name);
        };

        this.GM_listValues = function() {
            var ret = new Array();
            for (var i=0; i<localStorage.length; i++) {
                ret.push(localStorage.key(i));
            }
            return ret;
        };

        this.GM_getValue = function(name, defaultValue) {
            var value = localStorage.getItem(name);
            if (!value)
                return defaultValue;
            var type = value[0];
            value = value.substring(1);
            switch (type) {
              case 'b':
                  return value == 'true';
              case 'n':
                  return Number(value);
              default:
                  return value;
            }
        };
        
        this.GM_log = function(message) {
            console.log(message);
        };

        this.GM_registerMenuCommand = function(name, funk) {
            //todo
        };

        this.GM_openInTab = function(url) {
            chrome.extension.sendRequest({method: "openInTab", url: url}, function(response) {});
        };

        this.GM_setValue = function(name, value) {
            value = (typeof value)[0] + value;
            localStorage.setItem(name, value);
        };

        this.GM_xmlhttpRequest = function(details) {
            chrome.extension.sendRequest({method: "xhr", details: details}, function(response) {
                                             if (details["onload"]) {
                                                 if (response.data.responseXML) response.data.responseXML = unescape(response.data.responseXML);
                                                 details["onload"](response.data);
                                             }
                                         });
        }

        this.GM_getResourceURL = function(file) {
            return "http://pennergame.biniok.net/pics/ingame/" + file;
        }
        
        this.MF_getTab = function(cb) {
            chrome.extension.sendRequest({method: "getTab"}, function(response) {
                                             if (cb) {
                                                 cb(response.data);
                                             }
                                         });
        };

        this.MF_saveTab = function(tab) {
            chrome.extension.sendRequest({method: "saveTab", tab: tab});
        };

        this.MF_getTabs = function(cb) {
            chrome.extension.sendRequest({method: "getTabs"}, function(response) {
                                             if (cb) {
                                                 cb(response.data);
                                             }
                                         });
        };

        this.MF_checkFirstRun = function(firstRunCallback, updateCallback) {
            var verstr = "script-version";
            var ov = GM_getValue(verstr, "");

            var f = function(cv) {
                if (ov == "") {
                    GM_setValue(verstr, cv);
                    firstRunCallback(cv);
                } else if (ov != cv) {
                    GM_setValue(verstr, cv);
                    updateCallback(cv, ov);
                }
            };

            MF_getVersion(f)
            return false;
        };
        
        this.MF_getVersion = function(cb) {
            // will be replaced later
            cb("unknown-chrome");
            return true;
         };

        this.MF_copyToClipboard = function(text) {
            Clipboard = {};
            Clipboard.utilities = {};

            Clipboard.utilities.createTextArea = function(value) {
                var txt = document.createElement('textarea');
                txt.style.position = "absolute";
                txt.style.left = "-100%";

                if (value != null)
                    txt.value = value;
    
                document.body.appendChild(txt);
                return txt;
            };
            Clipboard.copy = function(data) {
                if (data == null) return;
                
                var txt = Clipboard.utilities.createTextArea(data);
                txt.select();
                document.execCommand('Copy');
                document.body.removeChild(txt);
            };

            Clipboard.copy(text);
        };
    };

    this.xmlhttpRequest = function(details, callback) {
        var xmlhttp = new XMLHttpRequest();
        var onload = function() {
            var responseState = {
                responseXML:(xmlhttp.readyState==4 ? (xmlhttp.responseXML ? escape(xmlhttp.responseXML) : null) : ''),
                responseText:(xmlhttp.readyState==4 ? xmlhttp.responseText : ''),
                readyState:xmlhttp.readyState,
                responseHeaders:(xmlhttp.readyState==4 ? xmlhttp.getAllResponseHeaders() : ''),
                status:(xmlhttp.readyState==4 ? xmlhttp.status : 0),
                statusText:(xmlhttp.readyState==4 ? xmlhttp.statusText : '')
            }
            if (callback) {
                callback(responseState);
            }
        }
        xmlhttp.onload = onload;
        xmlhttp.onerror = onload;
        try {
            //cannot do cross domain
            xmlhttp.open(details.method, details.url);
        } catch(e) {
            if(callback) {
                //simulate a real error
                callback({responseXML:'',responseText:'',readyState:4,responseHeaders:'',status:403,statusText:'Forbidden'});
            }
            return;
        }
        if (details.headers) {
            for (var prop in details.headers) {
                xmlhttp.setRequestHeader(prop, details.headers[prop]);
            }
        }
        if (typeof(details.data)!='undefined') {
            xmlhttp.send(details.data);
        } else {
            xmlhttp.send();
        }

    };

    this.contentLoad = function(tab) {

        this.currentTab = tab;
        var href = tab.url;

        if (!$gametypeScriptIncludes(href) || !$gametypeScriptExcludes(href)) {
            return;
        }

        var scripts = [];
        var requires = [];

        var folder = '';

        {
            var GM_emu = '';
            var fn = this.gm_emu.toString().replace("unknown-chrome", chrome.extension.getVersion() + "-chrome");
            GM_emu  = 'var GM_emu = ' + fn + '();\n';
            GM_emu += 'for (var k in GM_emu) { eval("var " + k + " = " + GM_emu[k] + ";"); }';
            GM_emu += 'if (typeof(unsafeWindow) != "undefined") return;';
            GM_emu += 'var unsafeWindow = window;';
            var script = new Script();
            script.name = 'GM_Emulation.jsi';
            script.fileURL = script.name;
            script.textContent = GM_emu;
            script.namespace = $gametypeScriptName;
            requires.push(script);
        }

        for (var i = 0; i < $gametypeScriptFiles.length; i++) {
            var script = new Script();
            script.name = $gametypeScriptFiles[i].name
            script.fileURL = folder + script.name;
            script.textContent = this.getUrlContents(script.fileURL);
            script.namespace = $gametypeScriptName;
            if ($gametypeScriptFiles[i].main) {
                script.requires = requires;
                scripts.push(script);
            } else {
                requires.push(script);
            }
        }

        console.log("run script @ " + tab.url)

        this.injectScript(scripts);
    };

    this.getUrlContents = function(url) {

        var content = '';
        var xhr = new XMLHttpRequest();
        xhr.open("GET", '/' + url, false);
        xhr.send(null);
        content = xhr.responseText;
        return content;
    };

    this.injectScript = function(scripts) {
        var script;
        for (var i = 0; script = scripts[i]; i++) {

            var contents = script.textContent;

            var requires = [];
            var offsets = [];
            var offset = contents.split("\n").length;

            script.requires.forEach(function(req) {
                    var contents = req.textContent;
                    var lineCount = contents.split("\n").length;
                    requires.push(contents);
                    offset += lineCount;
                    offsets.push(offset);
                });
            script.offsets = offsets;

            var scriptSrc = "\n" + // error line-number calculations depend on these
                contents +
                "\n" +
                requires.join("\n") +
                "\n";

            this.evalInSandbox("(function(){"+ scriptSrc +";})()", script); // wrap anyway on early return
        }
    };

    this.evalInSandbox = function(code, script) {
        if (true) {
            chrome.tabs.executeScript(this.currentTab.id, { code: code});
        }
        return true;
    };

    this.findError = function(script, lineNumber){
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
    };
};

/* ### Listener ### */
    
var loadListener = function(tabID, changeInfo, tab) {
};

var updateListener = function(tabID , changeInfo, tab) {
    if (changeInfo.status == 'complete') {
        if (tab.title.search(tab.url + " is not available") != -1) {
            var reload = function() {
                console.log("trigger reload (tabID " + tabID + ") of " + tab.url);
                chrome.tabs.update(tabID, {url: tab.url});
            };
            window.setTimeout(reload, 20000);
        } else {
            var pgSC = new compilerInit();
            pgSC.contentLoad(tab);
        }
    }
};

var $gametypeScript_gmCompiler = new compilerInit();

/* run update check chrome don't want to load basic auth secured pages, so
   we must care about the update !*/

window.setTimeout(function() {chrome.extension.newVersion();}, 20000);
chrome.tabs.onUpdated.addListener(loadListener);
