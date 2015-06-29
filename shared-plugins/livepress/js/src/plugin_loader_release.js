/*jslint white: false, vars:true, nomen: false */
/*global LivepressConfig, jQuery, document */
var Livepress = Livepress || {};
(function () {
	var lpEnabled = false;
	var lpLoad = function () {
		if (lpEnabled) {
			return;
		}
		lpEnabled = true;
		Livepress.CSSQueue = [];
		var mode = 'min';
		if (LivepressConfig.debug) {
			mode = 'full';
		}
		Livepress.JSQueue = [(jQuery === undefined ? 'jquery://' : ''), 'wpstatic://js/' + '/livepress-release.full.js?v=' + LivepressConfig.ver];
		var loader = document.createElement('script');
		loader.setAttribute('id', 'LivePress-loader-script');
		loader.setAttribute('src', LivepressConfig.wpstatic_url + 'js/livepress_loader.' + mode + '.js?v=' + LivepressConfig.ver);
		loader.setAttribute('type', 'text/javascript');
		document.getElementsByTagName('head').item(0).appendChild(loader);
	};

	if (LivepressConfig.page_type === 'home' || LivepressConfig.page_type === 'single') {
		lpLoad();
	}
}());
