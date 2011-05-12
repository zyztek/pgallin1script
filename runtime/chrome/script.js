/* 
 * @filename script.js
 * @author Jan Biniok <jan@biniok.net>
 * @author Thomas Rendelmann <thomas@rendelmann.net>
 * @licence GPL v2
 */

// send request when content script is loaded.. this happens just once ;)
chrome.extension.sendRequest({ method: "onUpdate" }, function(response) {});
