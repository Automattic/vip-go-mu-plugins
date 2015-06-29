/*global LivepressConfig */
var Livepress = Livepress || {};

/**
 * Returns object or {} depending if it exists
 * @param object
 */
Livepress.ensureExists = function (object) {
	if (object === undefined) {
		object = {};
	}
	return object;
};

/***************** Utility functions *****************/

// Prevent extra calls to console.log from throwing errors when the console is closed.
var console = console || { log: function () { } };

// Get the permalink for a given update
// The url is used for sharing with an anchor link to the update and
// works for Open Graph and Twitter Cards:
Livepress.getUpdatePermalink = function (update_id) {
	var re = /livepress-update-([0-9]+)/,
		id = re.exec(update_id)[1],
		lpup = '',
		post_link = jQuery(location).attr('protocol') + '//' + jQuery(location).attr('host') + jQuery(location).attr('pathname'),
		query = jQuery(location).attr('search'),
		pid;

	// Check url depending on permalink (default / nice urls)
	if ( null !== ( pid = query.match(/\?p=[0-9]+/) ) ){
		post_link += pid + "&";
	} else {
		post_link += "?";
	}

	lpup += "lpup=";
	return post_link + lpup + id + "#" + update_id;
};
Livepress.updateShortlinksCache = window.LivepressConfig.shortlink || {};
Livepress.getUpdateShortlink = function (upd) {
	var re = /livepress-update-([0-9]+)/,
	update_id = re.exec(upd)[1];
	if( !( update_id in Livepress.updateShortlinksCache ) ) {

		return jQuery.ajax({
			url: window.LivepressConfig.ajax_url,
			xhrFields: {
				withCredentials: true
			},
			type: 'post',
			async: false,
			dataType: 'json',
			data: {
				'action': 'lp_update_shortlink',
				'post_id': window.LivepressConfig.post_id,
				'_ajax_nonce': LivepressConfig.lp_update_shortlink_nonce,
				'update_id': update_id
			}
		}).promise();


	} else {
		return Livepress.updateShortlinksCache[update_id];
	}
};

/*
 * Parse strings date representations into a real timestamp.
 */
String.prototype.parseGMT = function (format) {
	var date = this,
		formatString = format || "h:i:s a",
		parsed,
		timestamp;

	parsed = Date.parse(date.replace(/-/gi, "/"), "Y/m/d H:i:s");
	timestamp = new Date(parsed);

	// fallback to original value when invalid date
	if (timestamp.toString() === "Invalid Date") {
		return this.toString();
	}

	timestamp = timestamp.format(formatString);
	return timestamp;
};

/*
 * Needed for the post update
 */
String.prototype.replaceAll = function (from, to) {
	var str = this;
	str = str.split(from).join(to);
	return str;
};

// Ensure we have a twitter handler, even when the page starts with no embeds
// because they may be added later. Corrects issue where twitter embeds failed on live posts when
// original page load contained no embeds.
if ( 'undefined' === typeof window.twttr ) {
	window.twttr = (function (d,s,id) {
						var t, js, fjs = d.getElementsByTagName(s)[0];
						if (d.getElementById(id)) { return; } js=d.createElement(s); js.id=id;
						js.src="https://platform.twitter.com/widgets.js"; fjs.parentNode.insertBefore(js, fjs);
						return window.twttr || (t = { _e: [], ready: function(f){ t._e.push(f); } });
					}(document, "script", "twitter-wjs"));
}
