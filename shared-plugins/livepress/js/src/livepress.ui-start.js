/*global OORTLE, LivepressConfig, Livepress, console */

if (jQuery !== undefined) {
	jQuery.ajax = (function (jQajax) {
		return function () {
			if (OORTLE !== undefined && OORTLE.instance !== undefined && OORTLE.instance) {
				OORTLE.instance.flush();
			}
			return jQajax.apply(this, arguments);
		};
	}(jQuery.ajax));
}
/**
 * Underscore throttle
 */
  // Returns a function, that, when invoked, will only be triggered at most once
  // during a given window of time. Normally, the throttled function will run
  // as much as it can, without ever going more than once per `wait` duration;
  // but if you'd like to disable the execution on the leading edge, pass
  // `{leading: false}`. To disable execution on the trailing edge, ditto.
  // A (possibly faster) way to get the current timestamp as an integer.
var unow = Date.now || function() {
    return new Date().getTime();
  };

var throttle = function(func, wait, options) {
    var context, args, result;
    var timeout = null;
    var previous = 0;
    if (!options){ options = {}; }
    var later = function() {
      previous = options.leading === false ? 0 : unow();
      timeout = null;
      result = func.apply(context, args);
      if (!timeout) { context = args = null; }
    };
    return function() {
      var now = unow();
      if (!previous && options.leading === false) { previous = now; }
      var remaining = wait - (now - previous);
      context = this;
      args = arguments;
      if (remaining <= 0 || remaining > wait) {
        clearTimeout(timeout);
        timeout = null;
        previous = now;
        result = func.apply(context, args);
        if (!timeout) { context = args = null; }
      } else if (!timeout && options.trailing !== false) {
        timeout = setTimeout(later, remaining);
      }
      return result;
    };
  };

Livepress.Ready = function () {

	var $lpcontent, $firstUpdate, $livepressBar, $heightOfFirstUpdate, $firstUpdateContainer, diff,
		hooks = {
			post_comment_update:  Livepress.Comment.attach,
			before_live_comment:  Livepress.Comment.before_live_comment,
			should_attach_comment:Livepress.Comment.should_attach_comment,
			get_comment_container:Livepress.Comment.get_comment_container,
			on_comment_update:    Livepress.Comment.on_comment_update
		};

	// Add update permalink to each timestamp
	jQuery.each(
		jQuery('.livepress-update'),
		function(){
			var timestamp = jQuery(this).find('abbr.livepress-timestamp');
			timestamp.wrap('<a href="' + Livepress.getUpdatePermalink(jQuery(this).attr('id')) + '" ></a>');
			console.log( LivepressConfig.update_format );
			if ( 'timeago' === LivepressConfig.timestamp_format ) {
				jQuery('abbr.livepress-timestamp').timeago().attr( 'title', '' );
			} else {
				jQuery('.lp-bar abbr.livepress-timestamp').timeago();
				jQuery('abbr.livepress-timestamp').attr( 'title', '' );
			}
		}
	);

	if ( jQuery( '.lp-status' ).hasClass( 'livepress-pinned-header' ) ) {

		jQuery( '.livepress_content' ).find( '.livepress-update:first' ).addClass( 'pinned-first-live-update' );
		// Adjust the positioning of the first post to pin it to the top
		var adjustTopPostPositioning = function() {

			window.console.log( 'adjust top' );
			$lpcontent    = jQuery( '.livepress_content' );
			$firstUpdate  = $lpcontent.find( '.pinned-first-live-update' );
			// keep at the top of the list
			$firstUpdate.detach().prependTo( $lpcontent );
			// remove meta, tags, author
			$firstUpdate.find( '.livepress-meta, .live-update-livetags, .live-update-authors' ).hide();
			$firstUpdateContainer = $lpcontent.parent();
			$firstUpdate.css( 'marginTop', 0 );
			$livepressBar = jQuery( '#livepress' );
			$livepressBar.css( 'marginTop', 0 );
			diff = $firstUpdate.offset().top - $firstUpdateContainer.offset().top;
			$heightOfFirstUpdate = ( $firstUpdate.outerHeight() + 20 );
			$firstUpdate.css( {
				'margin-top': '-' + ( diff + $heightOfFirstUpdate ) + 'px',
				'position': 'absolute',
				'width' : ( $livepressBar.outerWidth() ) + 'px'
			} );
			$livepressBar.css( { 'margin-top': $heightOfFirstUpdate + 'px' } );
		};

		adjustTopPostPositioning();

		// Adjust the top position whenever the post is updated so it fits properly
		jQuery( document ).on( 'live_post_update', function(){
			console.log ('live_post_update triggered' );
			setTimeout( adjustTopPostPositioning, 50 );
			// Rerun in 2 seconds to account fo resized embeds
			//setTimeout( adjustTopPostPositioning, 2000 );
		});

		// Adjust the top positioning whenever the browser is resized to adjust sizing correctly
		jQuery( window ).on( 'resize', throttle ( function() {
			adjustTopPostPositioning();
		}, 500 ) );
	}
	return new Livepress.Ui.Controller(LivepressConfig, hooks);
};
