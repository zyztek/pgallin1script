/* 
 * @filename runtime.js
 * @author Jan Biniok <jan@biniok.net>
 * @author Thomas Rendelmann <thomas@rendelmann.net>
 * @licence GPL v2
 */

function MF_getVersion() {
    return pgScriptVersion + "-userscript";
}

function MF_checkFirstRun(firstRunCallback, updateCallback) {
    var current = pgScriptVersion;
    var ver = GM_getValue("version");
    if (!ver) {
        // first run!
        GM_setValue("version", current);
        if (firstRunCallback) firstRunCallback(current);
    } else if (ver != current) {
        GM_setValue("version", current);
        if (updateCallback) updateCallback(current, ver);
    }
}

function MF_getTab(cb) {
    try {
        TM_getTab(cb);
        return;
    } catch (e) {
    }
    throw "not supported";
}

function MF_saveTab(tab) {
    try {
        TM_saveTab(tab);
        return;
    } catch (e) {
    }
    throw "not supported";
}

function MF_getTabs(cb) {
    try {
        TM_getTabs(cb);
        return;
    } catch (e) {
    }
    throw "not supported";
}
