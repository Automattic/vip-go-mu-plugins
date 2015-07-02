/*global LivepressConfig, lp_strings, Livepress, switchEditors, console, Collaboration */
/*jslint vars:true */
var Dashboard = Dashboard || {};

Dashboard.Controller = Dashboard.Controller || function () {
	var itsOn = false;
	var $paneHolder = jQuery( '#lp-pane-holder' );
	var $paneBookmarks = jQuery( '#lp-pane-holder .pane-bookmark' );
	var $hintText = $paneHolder.find( '.taghint' );
	var $hintedInput = $paneHolder.find( '.lp-input' );
	var $searchTabs = jQuery( '#twitter-search-subtabs li' );

	var init = function () {

		if ( Dashboard.Comments !== undefined ) {
			if ( LivepressConfig.disable_comments ) {
				jQuery( "#bar-controls .comment-count" ).hide();
				$paneHolder.find( 'div[data-pane-name="Comments"]' ).hide();
				Dashboard.Comments.disable();
			} else {
				Dashboard.Comments.init();
			}
		}

		// Wait until livepress.connected before initializing Twitter
		jQuery(window).on( 'connected.livepress', function() {
			Dashboard.Twitter.init();
			Dashboard.Twitter.conditionallyEnable();
		});

		/* Hints */
		$hintedInput.bind( 'click', function () {
			$hintText = jQuery( this ).parent( "div" ).find( '.taghint' );
			$hintText.css( "visibility", "hidden" );
		} );

		$hintText.bind( 'click', function () {
			var input = jQuery( this ).siblings( "input.lp-input" );
			input.focus();
			input.click();
		} );

		$hintedInput.bind( 'blur', function () {
			if ( jQuery( this ).val() === '' ) {
				$hintText.css( "visibility", "visible" );
			}
		} );

		// Add the new page tab
		var tab_markup = '<a id="content-livepress-html" class="hide-if-no-js wp-switch-editor switch-livepress-html"><span class="icon-livepress-logo"></span> Real-Time Text</a><a id="content-livepress" class="hide-if-no-js wp-switch-editor switch-livepress active"><span class="icon-livepress-logo"></span> Real-Time</a>';
		jQuery( tab_markup ).insertAfter( '#content-tmce' );
	};

	// handle ON/OFF button
	var live_switcher = function ( evt ) {
		var target = jQuery( evt.srcElement || evt.target ), publish = jQuery( '#publish' ), switchWarning = jQuery( '#lp-switch-panel .warning' );

		itsOn = itsOn ? false : true;

		Dashboard.Helpers.saveEEState( itsOn.toString() );

		if ( itsOn ) {
			switchWarning.hide();
			publish.data( 'publishText', publish.val() );
			if ( publish.val() === "Update" ) {
				publish.val( lp_strings.save_and_refresh );
			} else {
				publish.val( lp_strings.publish_and_refresh );
			}
			publish.removeClass( "button-primary" ).addClass( "button-secondary" );
			jQuery( window ).trigger( 'start.livepress' );

			// Unbind the editor tab click - only showing live editor when live
			jQuery( '#wp-content-editor-tools' ).unbind();
		} else {
			switchWarning.show();
			publish.val( publish.data( 'publishText' ) ).removeClass( "button-secondary" ).addClass( "button-primary" );
			jQuery( window ).trigger( 'stop.livepress' );

			if ( target.hasClass( 'switch-html' ) ) {
				switchEditors.go( 'content', 'html' );
			} else if ( target.hasClass( 'switch-tmce' ) ) {
				switchEditors.go( 'content', 'tmce' );
			}
		}

		$paneHolder.toggleClass( 'scroll-pane' );
	};

	jQuery( '#wp-content-editor-tools' ).on( 'click', '#content-livepress', live_switcher );
	jQuery( '#poststuff' ).on( 'click', '.secondary-editor-tools .switch-tmce, .secondary-editor-tools .switch-html', live_switcher );
	Dashboard.Helpers.setupLivePressTabs();  //Set up the LivePress tabs after brief pause

	var switchToPane = function( currPane ) {
		Dashboard.Twitter.conditionallyEnable();
		Dashboard.Comments.conditionallyEnable();
		currPane.find( 'span.count-update' ).hide();
	};

	jQuery( '.blogging-tools-tabs ul li' ).on( 'click', function() {
		var $this = jQuery( this );
		if ( $this.is( '.active' ) === false ) {
			switchToPane( $this );
		}
	});

	init();

	// Switch to live if the live class was added to the livepress_status_meta_box
	if ( jQuery( '#livepress_status_meta_box' ).hasClass( 'live' ) ) {
		live_switcher( {srcElement: null} );
	}

};

function DHelpers() {
	var SELF = this,
		pane_errors = document.getElementById( 'lp-pane-errors' ),
		$pane_errors = jQuery( pane_errors );

	function LiveCounter( container ) {
		var SELF = this;

		SELF.enable = function() {
			SELF.counterContainer = jQuery( container ).siblings( '.count-update' );
			SELF.count = 0;
			SELF.enabled = true;
			SELF.counterContainer.show();
		};

		SELF.reset = function() {
			if ( 'undefined' === typeof SELF.counterContainer ) {
				return;
			}
			SELF.count = 0;
			SELF.counterContainer.text( '0' );
		};

		SELF.disable = function() {
			if ( 'undefined' === typeof SELF.counterContainer ) {
				return;
			}
			SELF.enabled = false;
			SELF.count = 0;
			SELF.counterContainer.text( '0' ).hide();
		};

		SELF.increment = function( num ) {
			if ( 'undefined' === typeof SELF.counterContainer ) {
				return;
			}
			SELF.count += num || 1;
			SELF.counterContainer.text( SELF.count );
		};

		SELF.isEnabled = function() {
			return SELF.enabled;
		};
	}

	SELF.saveEEState = function ( state ) {
		var postId = LivepressConfig.post_id;
		Livepress.storage.set( 'post-' + postId + '-eeenabled', state );
	};

	SELF.getEEState = function () {
		if ( jQuery.getUrlVar( 'action' ) === 'edit' ) {
			var postId = LivepressConfig.post_id;
			if ( ! postId ) {
				return false;
			}

			if ( Livepress.storage.get( 'post-' + postId + '-eeenabled' ) === 'true' ) {
				return true;
			}
		}

		return false;
	};

	SELF.hideAndMark = function ( el ) {
		el.hide().addClass( 'spinner-hidden' );
		return(el);
	};

	SELF.disableAndDisplaySpinner = function ( elToBlock ) {
		var $spinner = jQuery( "<div class='lp-spinner'></div>" );
		if ( elToBlock.is( "input" ) ) {
			elToBlock.attr( "disabled", true );
			var $addButton = this.hideAndMark( elToBlock.siblings( ".button" ) );
			$spinner.css( 'float', $addButton.css( "float" ) );
		}
		elToBlock.after( $spinner );
	};

	SELF.enableAndHideSpinner = function ( elToShow ) {
		elToShow.attr( "disabled", false );
		elToShow.siblings( ".button" ).show();
		elToShow.siblings( '.lp-spinner' ).remove();
	};

	SELF.setSwitcherState = function ( state ) {
		jQuery( document.getElementById( 'live-switcher' ) )
			.removeClass( state === 'connected' ? 'disconnected' : 'connected' )
			.addClass( state );
		// Trigger that communications connected or disconnected
		jQuery( window ).trigger( state + '.livepress' );
	};

	SELF.handleErrors = function ( errors ) {
		console.log( errors );
		$pane_errors.html('');
		$pane_errors.hide();
		jQuery.each( errors, function ( field, error ) {
			var error_p = document.createElement( 'p' );
			error_p.className = 'lp-pane-error ' + field;
			error_p.innerHtml = error;
			$pane_errors.append( error_p );
		} );
		$pane_errors.show();
	};

	SELF.clearErrors = function ( selector ) {
		if ( null !== pane_errors ) {
			jQuery( pane_errors.querySelectorAll( selector ) ).remove();
		}
	};

	SELF.hideErrors = function () {
		$pane_errors.hide();
	};

	SELF.createLiveCounter = function ( container ) {
		return new LiveCounter( container );
	};

	/**
	 * Ensure Live Blogging Tools & Real Time open/closed when post is live/not live
	 */
	SELF.setupLivePressTabs = function () {
		if ( jQuery('#livepress_status_meta_box').hasClass( 'live' ) ) {

			// Post marked live, switch on the tabs if closed.
			if ( jQuery( '.livepress-update-form' ).not(':visible') ) { // Is the LivePress live update form hidden?
				jQuery( '#content-livepress' ).trigger( 'click' ); // If so, click the tab to open it.
			}

			// Open the Live Blogging Tools area if not already open.
			if ( 'true' !== jQuery( 'a#blogging-tools-link' ).attr( 'aria-expanded' ) ) { // Is the live blogging closed?
				jQuery( 'a#blogging-tools-link' ).trigger( 'click' ); // If so, click the tab to open it.
			}

		} else {

			// Post not marked as live, switch off the tabs if open.
			if ( jQuery( '.livepress-update-form' ).is(':visible') ) { // Is the LivePress live update form visible?
				jQuery( 'a.switch-tmce' ).trigger( 'click' ); // If so, click the tab to close it.
			}

			// Close the Live Blogging Tools area if  already open.
			if ( 'true' === jQuery( 'a#blogging-tools-link' ).attr( 'aria-expanded' ) ) { // Is the live blogging open?
				jQuery( 'a#blogging-tools-link' ).trigger( 'click' ); // If so, click the tab to close it.
			}
		}
	};

}

Dashboard.Helpers = Dashboard.Helpers || new DHelpers();
