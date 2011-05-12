/* 
 * @filename xmlhttprequester.js
 * @author Jan Biniok <jan@biniok.net>
 * @author Thomas Rendelmann <thomas@rendelmann.net>
 * @licence GPL v2
 */

function log(l) {
    // $gametypeScript_gmCompiler.log(l);
}

function $gametypeScript_xmlhttpRequester(unsafeContentWin, chromeWindow, originUrl) {
    this.unsafeContentWin = unsafeContentWin;
    this.chromeWindow = chromeWindow;
    this.originUrl = originUrl;
}

// this function gets called by user scripts in content security scope to
// start a cross-domain xmlhttp request.
//
// details should look like:
// {method,url,onload,onerror,onreadystatechange,headers,data}
// headers should be in the form {name:value,name:value,etc}
// can't support mimetype because i think it's only used for forcing
// text/xml and we can't support that
$gametypeScript_xmlhttpRequester.prototype.contentStartRequest = function(details) {
  log("> $gametypeScript_xmlhttpRequest.contentStartRequest");
  
  if (!$gametypeScript_gmCompiler.apiLeakCheck("$gametypeScript_xmlhttpRequester")) {
      return;
  }

  try {
    // Validate and parse the (possibly relative) given URL.
    var uri = $gametypeScript_gmCompiler.uriFromUrl(details.url, this.originUrl);
    var url = uri.spec;
  } catch (e) {
    // A malformed URL won't be parsed properly.
    throw new Error("Invalid URL: " + details.url);
  }

  // This is important - without it, $gametypeScript_xmlhttpRequest can be used to get
  // access to things like files and chrome. Careful.
  switch (uri.scheme) {
    case "http":
    case "https":
    case "ftp":
        var req = new this.chromeWindow.XMLHttpRequest();
        $gametypeScript_gmCompiler.hitch(this, "chromeStartRequest", url, details, req)();
      break;
    default:
      throw new Error("Disallowed scheme in URL: " + details.url);
  }

  log("< $gametypeScript_xmlhttpRequest.contentStartRequest");

  return {
    abort: function() {
      req.abort();
    }
  };
};

// this function is intended to be called in chrome's security context, so
// that it can access other domains without security warning
$gametypeScript_xmlhttpRequester.prototype.chromeStartRequest =
function(safeUrl, details, req) {
    log("> $gametypeScript_xmlhttpRequest.chromeStartRequest");

    this.setupRequestEvent(this.unsafeContentWin, req, "onload", details);
    this.setupRequestEvent(this.unsafeContentWin, req, "onerror", details);
    this.setupRequestEvent(this.unsafeContentWin, req, "onreadystatechange",
                           details);

    req.mozBackgroundRequest = !!details.mozBackgroundRequest;
    
    req.open(details.method, safeUrl, true, details.user || "", details.password || "");

    if (details.overrideMimeType) {
        req.overrideMimeType(details.overrideMimeType);
    }

    if (details.headers) {
        var headers = details.headers;

        for (var prop in headers) {
            if (Object.prototype.hasOwnProperty.call(headers, prop)) {
                req.setRequestHeader(prop, headers[prop]);
            }
        }
    }

    var body = details.data ? details.data : null;
    if (details.binary) {
        req.sendAsBinary(body);
    } else {
        req.send(body);
    }
    
    log("< $gametypeScript_xmlhttpRequest.chromeStartRequest");
};

// arranges for the specified 'event' on xmlhttprequest 'req' to call the
// method by the same name which is a property of 'details' in the content
// window's security context.
$gametypeScript_xmlhttpRequester.prototype.setupRequestEvent =
function(unsafeContentWin, req, event, details) {
    log("> $gametypeScript_xmlhttpRequester.setupRequestEvent");

    if (details[event]) {
        req[event] = function() {
            log("> $gametypeScript_xmlhttpRequester -- callback for " + event);
            
            var responseState = {
                // can't support responseXML because security won't
                // let the browser call properties on it
                responseText: req.responseText,
                readyState: req.readyState,
                responseHeaders: null,
                status: null,
                statusText: null,
                finalUrl: null
            };
            if (4 == req.readyState && 'onerror' != event) {
                responseState.responseHeaders = req.getAllResponseHeaders();
                responseState.status = req.status;
                responseState.statusText = req.statusText;
                responseState.finalUrl = req.channel.URI.spec;
            }

            // Pop back onto browser thread and call event handler.
            // Have to use nested function here instead of GM_hitch because
            // otherwise details[event].apply can point to window.setTimeout, which
            // can be abused to get increased priveledges.
            new XPCNativeWrapper(unsafeContentWin, "setTimeout()")
            .setTimeout(function(){details[event](responseState);}, 0);
            
            log("< $gametypeScript_xmlhttpRequester -- callback for " + event);
        };
    }

    log("< $gametypeScript_xmlhttpRequester.setupRequestEvent");
};
