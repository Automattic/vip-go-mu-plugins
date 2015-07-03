var wpDebugBar;

(function($) {

var api;

wpDebugBar = api = {
	// The element that we will pad to prevent the debug bar
	// from overlapping the bottom of the page.
	body: undefined,

	init: function() {
		// If we're not in the admin, pad the body.
		api.body = $(document.body);

		api.toggle.init();
		api.tabs();
		api.actions.init();
	},

	isVisible: function() {
		return api.body.hasClass( 'debug-bar-visible' );
	},

	toggle: {
		init: function() {
			$('#wp-admin-bar-debug-bar').click( function(e) {
				e.preventDefault();
				api.toggle.visibility();
			});
		},
		visibility: function( show ) {
			show = typeof show == 'undefined' ? ! api.isVisible() : show;

			// Show/hide the debug bar.
			api.body.toggleClass( 'debug-bar-visible', show );

			// Press/unpress the button.
			$(this).toggleClass( 'active', show );
		}
	},

	tabs: function() {
		var debugMenuLinks = $('.debug-menu-link'),
			debugMenuTargets = $('.debug-menu-target');

		debugMenuLinks.click( function(e) {
			var t = $(this);

			e.preventDefault();

			if ( t.hasClass('current') )
				return;

			// Deselect other tabs and hide other panels.
			debugMenuTargets.hide().trigger('debug-bar-hide');
			debugMenuLinks.removeClass('current');

			// Select the current tab and show the current panel.
			t.addClass('current');
			// The hashed component of the href is the id that we want to display.
			$('#' + this.href.substr( this.href.indexOf( '#' ) + 1 ) ).show().trigger('debug-bar-show');
		});
	},

	actions: {
		init: function() {
			var actions = $('#debug-bar-actions');

			// Close the panel with the esc key if it's open
			// 27 = esc
			$(document).keydown( function( e ) {
				var key = e.key || e.which || e.keyCode;
				if ( 27 != key || ! api.isVisible() )
					return;

				e.preventDefault();
				return api.actions.close();
			});

			$('.maximize', actions).click( api.actions.maximize );
			$('.restore',  actions).click( api.actions.restore );
			$('.close',    actions).click( api.actions.close );
		},
		maximize: function() {
			api.body.removeClass('debug-bar-partial');
			api.body.addClass('debug-bar-maximized');
		},
		restore: function() {
			api.body.removeClass('debug-bar-maximized');
			api.body.addClass('debug-bar-partial');
		},
		close: function() {
			api.toggle.visibility( false );
			console.log( 'boo');
		}
	}
};

wpDebugBar.Panel = function() {

};

$(document).ready( wpDebugBar.init );

})(jQuery);