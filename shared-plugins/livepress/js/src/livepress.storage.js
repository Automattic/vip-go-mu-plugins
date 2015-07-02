/*global Livepress */

/**
* storage.js - Simple namespaced browser storage.
*
* Creates a window.Storage function that gives you an easy API to access localStorage,
* with fallback to cookie storage. Each Storage object is namespaced:
*
* var foo = Storage('foo'), bar = Storage('bar');
* foo.set('test', 'A'); bar.set('test', 'B');
* foo.get('test'); // 'A'
* bar.remove('test');
* foo.get('test'); // still 'A'
*
* Requires jQuery.
* Based on https://github.com/jbalogh/zamboni/blob/master/media/js/zamboni/storage.js
* Everything clever written by Chris Van.
*/
var internalStorage = (function () {
	var cookieStorage = {
			expires: 30,
			get: function ( key ) {
			return jQuery.cookie( key );
			},

			set: function ( key, value ) {
				return jQuery.cookie( key, value, {path: "/", expires: this.expires} );
			},

			remove: function ( key ) {
				return jQuery.cookie( key, null );
			}
		};

	var engine = cookieStorage;
	try {
		if ( 'localStorage' in window && window['localStorage'] !== null ) {
			engine = window.localStorage;
		}
	} catch ( e ) {
		}
	return function ( namespace ) {
		if ( !namespace ) {
			namespace = '';
		}

		return {
			get: function ( key, def ) {
				return engine.getItem( namespace + "-" + key );
			},

			set: function ( key, value ) {
				return engine.setItem( namespace + "-" + key, value );
			},

			remove: function ( key ) {
				return engine.remoteItem( namespace + "-" + key);
			}
		};
	};
})();

Livepress.storage = (function () {
	var storage = new internalStorage('Livepress' );
	return {
		get: function (key, def) {
			var val = storage.get(key);
			return (val === null || typeof val === 'undefined' ) ? def : val;
		},
		set: function (key, value) {
			return storage.set(key, value);
		}
	};
}());
