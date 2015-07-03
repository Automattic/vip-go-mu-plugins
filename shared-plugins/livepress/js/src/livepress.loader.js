/*jslint plusplus:true, vars:true */
/*global LivepressConfig, console, jQuery, document, navigator */
var Livepress = Livepress || {};

(function () {
	var loader = function () {
		var scripts = [],
			styles = [],
			agent = navigator.userAgent.toLowerCase(),
			gecko_version,
			seq_load, i;

		gecko_version = agent.match(new RegExp("rv:(\\d+)\\.\\d+"));
		seq_load = (agent.indexOf("khtml") !== -1) ||
			(navigator.appName === 'Microsoft Internet Explorer') ||
			(agent.indexOf("gecko") !== -1) && (parseInt(gecko_version[1], 10) >= 2);

		if (Livepress.JSQueue !== undefined) {
			scripts = scripts.concat(Livepress.JSQueue);
		}
		if (Livepress.CSSQueue !== undefined) {
			styles = styles.concat(Livepress.CSSQueue);
		}

		//DEBUG Lines are included only in debugging version. They are completely removed from release code
		if (LivepressConfig.debug !== undefined && LivepressConfig.debug) { //DEBUG

			var run = encodeURIComponent("jQuery(function(){Livepress.Ready()})"); //DEBUG
			scripts = scripts.concat([ //DEBUG
				'static://oortle.full.js?rnd=' + Math.random(), //DEBUG
				'static://oortle_dynamic.js?run=' + run + '&rnd=' + Math.random() //DEBUG
			]); //DEBUG
		} else //DEBUG
		{
			scripts = scripts.concat([
				'static://oortle/' + LivepressConfig.oover[0] + '/oortle.min.js',
				'static://' + LivepressConfig.oover[1] + '/cluster_settings.js?v=' + LivepressConfig.oover[2]
			]);
		}

		var getPath = function (url) {
			var m = url.match(/^([a-z]+):\/\/(.*)$/);

			if (m.length) {
				if (LivepressConfig[m[1] + '_url'] !== undefined) { // Translate if url mapping defined for it
					var prefix = LivepressConfig[m[1] + "_url"];
					if (prefix.substr(-1) !== "/") {
						prefix += "/";
					}
					url = prefix + m[2];
				}
			}
			return url;
		};
		var loadStyle = function (idx) {
			if (idx >= styles.length) {
				return;
			}
			var tag = document.createElement('link');
			tag.setAttribute('id', 'OORTLEstyle' + idx);
			tag.setAttribute('type', 'text/css');
			tag.setAttribute('rel', 'stylesheet');
			tag.setAttribute('href', getPath(styles[idx]));
			document.getElementsByTagName("head").item(0).appendChild(tag);
			return true;
		};
		var loadScript = function (idx, only) {
			console.log( 'loadScript' );
			if (idx >= scripts.length) {
				return false;
			}
			if (!scripts[idx]) {
				if (only) {
					return false;
				}
				return loadScript(idx + 1);
			}
			var oortleScript = document.createElement('script');
			oortleScript.setAttribute('id', 'OORTLEscript' + idx);
			oortleScript.setAttribute('type', 'text/javascript');
			oortleScript.setAttribute('src', getPath(scripts[idx]));
			if (seq_load) {
				if (typeof(oortleScript.onreadystatechange) !== "undefined") {
					oortleScript.onreadystatechange = function () {
						if (this.readyState === "loaded" || this.readyState === "complete") {
							this.onreadystatechange = function () {
							};
							loadScript(idx + 1);
						}
					};
				} else {
					oortleScript.onload = oortleScript.onerror = function () {
						loadScript(idx + 1);
					};
				}
			}
			document.getElementsByTagName("head").item(0).appendChild(oortleScript);
			return true;
		};
		for (i = 0; i < styles.length; i++) {
			loadStyle(i);
		}
		for (i = 0; i < scripts.length; i++) {
			if (!loadScript(i, true)) {
				continue;
			} // skip empty lines
			if (seq_load) {
				break;
			}
		}
	};
	if (typeof jQuery === 'undefined') {
		loader();
	} // If jQuery not defined, we called as loader for whole plugin
	else {
		jQuery(loader);
	} // Otherwise, we called as loader for only external part
}());
