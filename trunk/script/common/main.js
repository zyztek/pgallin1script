/** 
 * @filename main.jsi
 * @author Jan Biniok <jan@biniok.net>
 * @author Thomas Rendelmann <thomas@rendelmann.net>
 * @licence GPL v2
*/

/* ####################### Object registration ########################### */
var gContext = this;

var Registry = new Object();

Registry.init = function() {
    Registry.registry = {};

    Registry.register = function(name, object) {
        var o = new Object();
        o.obj = object;
        o.isClass = typeof object === 'function';
        Registry.registry[name] = o;
    };

    Registry.hasObject = function(name) {
        return Registry.registry[name] != undefined;
    };

    Registry.initObjects = function() {
        for (var name in Registry.registry) {
            if (Registry.registry[name].isClass) {
                // hu! this is a new style class, call the constructor and
                // inject the object with the given name at the global context 
                gContext[name] = new Registry.registry[name].obj();
                // store the created object instead of the class function
                Registry.registry[name].obj = gContext[name];
            }
            if (typeof Registry.registry[name].obj.init === 'function') {
                // call the objects init function, because some objects need
                // other public members or functions of objects that may not
                // exist at the moment of creation
                Registry.registry[name].obj.init();
            }
        };
    };
};

Registry.init();

/* ####################### script initialization ########################### */

function do_script_init() {
    // load all objects
    Registry.initObjects();

    // let's go ... :)
    call_runlevel();
}

/* ####################### runlevels ########################### */

function call_runlevel(runlevel) {
    if (typeof runlevel === 'undefined') runlevel = 0;
    var pending = 1;

    var runlevelCb = function(doAbort, isSync) {
        if (doAbort == true) {
            GM_log("Abbruch beim Starten des Objektes '" + name + "' bei Runlevel " + runlevel);
            return;
        }
        if (--pending == 0) {
            if (runlevel >= 100) {
                return;
            }
            call_runlevel(runlevel + 1);
        }
    };

    for (var name in Registry.registry) {
        var o = Registry.registry[name].obj;
        if (typeof o.onRunlevel === 'function') {
            var ret = o.onRunlevel(runlevel);
            if (typeof ret === 'number' && ret < 0) {
                GM_log("Abbruch beim Starten des Objektes '" + name + "' bei Runlevel " + runlevel);
                return;
            }
        }
        if (typeof o.onRunlevelAsync === 'function') {
            pending++;
            var ret = o.onRunlevelAsync(runlevel, runlevelCb);
            if (typeof ret === 'number' && ret < 0) {
                GM_log("Abbruch beim Starten des Objektes '" + name + "' bei Runlevel " + runlevel);
                return;
            }
        }
    }

    // check for finished runlevel
    runlevelCb(false, true);
}

/* ####################### global stuff ########################### */

function advanced_error_report(fn, url) {
    try {
        fn();
    } catch(e) {
        try{
            var s = '';
            if (e.stack) s += 'Stack\n' + e.stack.replace(/\;/gi, ';\n\t').replace(/\{/gi, '{\n\t').replace(/\}/gi, '}\n\t').replace(/\;\\n\t/gi, ';'); + '\n';
            if (e.description) s += 'Description\n' + e.description + '\n';
            if (e.message) s += 'Message\n' + e.message + '\n';
            try{
                if (url) s+= "Request: " + url + '\n';
            } catch (ee) {}
            try {
                Log.console('Main:' + s);
            } catch (le) {
                GM_log('Main:' + s);
            }
        } catch(ee) {
            try {
                Log.console('Main:' + ee);
            } catch (le) {
                GM_log('Main:' + ee);
            }
        } finally {
            throw(e);
        }
    }
};

var run = function() {
    do_script_init();
};

var load = function() {
    var sm = function() {
        if (typeof BaseLib === 'undefined' || typeof BaseLib.determineSpeedFactor !== 'function') {
            window.setTimeout(sm, 100);
        } else {
            BaseLib.determineSpeedFactor();
        }
    };
    sm();
};

/* give the browser a chance to do something
   before the script starts running */

window.setTimeout(function() { advanced_error_report(run); }, 1);
window.addEventListener('load', function() { advanced_error_report(load); }, false);
window.addEventListener('unload', function() { BaseLib.saveTab(); }, false);
