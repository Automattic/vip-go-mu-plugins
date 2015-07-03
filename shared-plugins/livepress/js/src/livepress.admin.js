/*jslint vars:true */
/*global LivepressConfig, lp_strings, Livepress, Dashboard, Collaboration, tinymce, tinyMCE, console, confirm, switchEditors, livepress_merge_confirmation, LivepressConfig, _wpmejsSettings, FB  */

/**
 * Global object into which we add our other functionality.
 *
 * @namespace
 * @type {Object}
 */
var OORTLE = OORTLE || {};

/**
 * Container for Livepress administration functionality.
 *
 * @memberOf Livepress
 * @namespace
 * @type {Object}
 */
Livepress.Admin = Livepress.Admin || {};

/**
 * Object that checks the live update status of a given post asynchronously.
 *
 * @memberOf Livepress.Admin
 * @namespace
 * @constructor
 * @requires LivepressConfig
 */
Livepress.Admin.PostStatus = function () {
	var CHECK_WAIT_TIME = 4, // Seconds
		MESSAGE_DISPLAY_TIME = 10, // Seconds
		SELF = this,
		spin = false;

	/**
	 * Add a spinner to the post form.
	 *
	 * @private
	 */
	function add_spin () {
		if (spin) {
			return;
		}
		spin = true;
		var $spinner = jQuery("<div class='lp-spinner'></div>")
			.attr('id', "lp_spin_img");
		jQuery("form#post").before($spinner);
	}

	/**
	 * Add a notice to alert the user of some change.
	 *
	 * This implementation is based in the edit-form-advanced.php html structure
	 * @param {String} msg Message to display to the user.
	 * @param {String} kind Type of message. Used as a CSS class for styling.
	 * @private
	 */
	function message (msg, kind) {
		kind = kind || "lp-notice";
		var $el = jQuery('<p/>').text(msg),
			$container = jQuery('<div class="lp-message ' + kind + '"></div>');
		$container.append($el);
		jQuery("#post").before($container);
		setTimeout(function () {
			$container.fadeOut(2000, function () {
				jQuery(this).remove();
			});
		}, MESSAGE_DISPLAY_TIME * 1000);
	}

	/**
	 * Remove the spinner added by add_spin().
	 *
	 * @private
	 */
	function remove_spin () {
		spin = false;
		jQuery('#lp_spin_img').remove();
	}

	/**
	 * Add an error message for the user.
	 *
	 * @param {String} msg Error message text.
	 * @private
	 */
	function error (msg) {
		message(msg, "lp-error");
	}
    SELF.error = error;

	/**
	 * Add a warning message for the user.
	 *
	 * @param {String} msg Warning message text.
	 * @private
	 */
	function warning (msg) {
		message(msg, "lp-warning");
	}

	/**
	 * Set the status of the post live update attempt.
	 *
	 * @param {String} status Status code.
	 * @private
	 */
	function set_status (status) {
		if (status === "completed") {
			message("Update was published live to users.");
			remove_spin();
		} else if (status === "failed") {
			warning("Update was published NOT live.");
			remove_spin();
		} else if (status === "lp_failed") {
			error("Can't get update status from LivePress.");
			remove_spin();
		} else if (status === "empty" || "0" === status) {
			remove_spin();
		} else if (status === "-1") {
			error("Wrong AJAX nonce.");
			remove_spin();
		} else {
			setTimeout(SELF.check, CHECK_WAIT_TIME * 1000);
		}
	}

	/**
	 * Check the current post's update status.
	 *
	 * @requires LivepressConfig#ajax_nonce
	 * @requires LivepressConfig#post_id
	 */
	SELF.check = function () {
		var nonces = jQuery('#blogging-tool-nonces');
		add_spin();
		jQuery.ajax({
			url:     LivepressConfig.ajax_url,
			data:    {
				livepress_action: true,
				_ajax_nonce:      LivepressConfig.ajax_status_nonce,
				post_id:          LivepressConfig.post_id,
				action:           'lp_status'
			},
			success: set_status,
			error:   function () {
				error("Can't get update status from blog server.");
				remove_spin();
			}
		});
	};

	jQuery( window ).on( 'livepress.post_update', function () {
		add_spin();
		setTimeout(SELF.check, CHECK_WAIT_TIME * 1000);
	} );
};

/**
 * Object containing the logic to post updates to Twitter.
 *
 * @memberOf Livepress.Admin
 * @namespace
 * @constructor
 */
Livepress.Admin.PostToTwitter = function () {
	var sending_post_to_twitter = false,
		msg_span_id = 'post_to_twitter_message',
		check_timeout,
		oauth_popup,
		TIME_BETWEEN_CHECK_OAUTH_REQUESTS = 5;

	/**
	 * Show an OAuth popup if necessary.
	 *
	 * @param {String} url OAuth popup url.
	 * @private
	 */
	function show_oauth_popup( url ) {
		try {
			oauth_popup.close();
		} catch (exc) {
		}
		oauth_popup = window.open( url, '_blank', 'height=600,width=600' );
		jQuery("#post_to_twitter_url")
			.html('<br /><a href="' + url + '">' + url + '</a>')
			.click(function (e) {
				try {
					oauth_popup.close();
				} catch (exc) {
				}
				oauth_popup = undefined;
				oauth_popup = window.open( url, '_blank', 'height=600,width=600' );
				return false;
			})
			.show();
	}

	/**
	 * Check the current status of Twitter OAuth authorization.
	 *
	 * Will poll the Twitter API asynchronously and display an error if needed.
	 *
	 * @param {Number} attempts Number of times to check authorization status.
	 * @private
	 */
	function check_oauth_authorization_status(attempts) {
		var $msg_span = jQuery('#' + msg_span_id),
			times = attempts || 1,
			params = {
						action:      'lp_check_oauth_authorization_status',
						_ajax_nonce: LivepressConfig.ajax_check_oauth
					};

		$msg_span.html( lp_strings.checking_auth + '... (' + times + ')');

		jQuery.ajax({
			url:   "./admin-ajax.php",
			data:  params,
			type:  'post',
			async: true,

			error:   function (XMLHttpRequest) {
				$msg_span.html( lp_strings.failed_to_check );
			},
			success: function (data) {
				if (data.status === 'authorized') {
					$msg_span.html(
						lp_strings.sending_twitter + ': <strong>' + data.username + '</strong>.');
					jQuery("#lp-post-to-twitter-change_link").show();
					jQuery("#lp-post-to-twitter-change").hide();
				} else if (data.status === 'unauthorized') {
					handle_twitter_deauthorization();
				} else if (data.status === 'not_available') {
					var closed = false;
					if (oauth_popup !== undefined) {
						try {
							closed = oauth_popup.closed;
						} catch (e) {
							if (e.name.toString() === "ReferenceError") {
								closed = true;
							} else {
								throw e;
							}
						}
					}
					if (oauth_popup !== undefined && closed) {
						handle_twitter_deauthorization();
					} else {
						$msg_span.html( lp_strings.status_not_avail );
						check_timeout = setTimeout(function () {
							check_oauth_authorization_status(++times);
						}, TIME_BETWEEN_CHECK_OAUTH_REQUESTS * 1000);
					}
				} else {
					$msg_span.html( lp_strings.internal_error );
				}
			}
		});
	}

	/**
	 * Update post on Twitter using current OAuth credentials.
	 *
	 * @param {Boolean} change_oauth_user Change the user making the request.
	 * @param {Boolean} disable Disable OAuth popup.
	 * @returns {Boolean} True when sending, False if already in the process of sending.
	 * @private
	 */
	function update_post_to_twitter(change_oauth_user, disable) {
		var $msg_span,
			params;

		if (sending_post_to_twitter) {
			return false;
		}
		sending_post_to_twitter = true;

		$msg_span = jQuery('#' + msg_span_id);

		params = {};
		params.action = 'lp_post_to_twitter';
		params._ajax_nonce = LivepressConfig.ajax_lp_post_to_twitter;
		params.enable = document.getElementById('lp-remote').checked;
		if (change_oauth_user) {
			params.change_oauth_user = true;
		}

		jQuery("#post_to_twitter_message_change_link").hide();
		jQuery("#post_to_twitter_url").hide();

		jQuery.ajax({
			url:   "./admin-ajax.php",
			data:  params,
			type:  'post',
			async: true,

			error:    function (XMLHttpRequest) {
				if (XMLHttpRequest.status === 409) {
					$msg_span.html('Already ' + ((params.enable) ? 'enabled.' : 'disabled.'));
				} else if (XMLHttpRequest.status === 502) {
					if (XMLHttpRequest.responseText === "404") {
						$msg_span.html( lp_strings.noconnect_twitter );
					} else {
						$msg_span.html(lp_strings.noconnect_livepress );
					}
				} else {
					$msg_span.html(
						lp_strings.failed_unknown + ' (' + lp_strings.return_code + ': ' + XMLHttpRequest.status + ')'
					);
				}
				clearTimeout(check_timeout);
			},
			success:  function (data) {
				if (params.enable) {
					console.log('OAuth url: ', data);
					$msg_span.html(
						lp_strings.new_twitter_window
					);
					show_oauth_popup(data);
					check_timeout = setTimeout(check_oauth_authorization_status,
						TIME_BETWEEN_CHECK_OAUTH_REQUESTS * 1000);
				} else {
					clearTimeout(check_timeout);
					if (disable !== true) {
						$msg_span.html('');
					}
				}
			},
			complete: function () {
				sending_post_to_twitter = false;
			}
		});

		return true;
	}

	/**
	 * If Twitter access is unauthorized, show the user an approripate message.
	 *
	 * @private
	 */
	function handle_twitter_deauthorization() {
		var $msg_span = jQuery( '#' + msg_span_id );
		$msg_span.html( lp_strings.twitter_unauthorized );
		update_post_to_twitter( false, true );
	}

	/**
	 * Universal callback function used inside click events.
	 *
	 * @param {Event} e Event passed by the click event.
	 * @param {Boolean} change_oauth_user Flag to change the current OAuth user.
	 * @private
	 */
	function callback( e, change_oauth_user ) {
		e.preventDefault();
		e.stopPropagation();
		update_post_to_twitter( change_oauth_user );
	}

	jQuery( "#post_to_twitter" ).on( 'click', function ( e ) {
		callback( e, false );
	} );

	jQuery( "#lp-post-to-twitter-change, #lp-post-to-twitter-change_link" ).on( 'click', function ( e ) {
		callback( e, true );
	} );

	jQuery( "#lp-post-to-twitter-not-authorized" ).on( 'click', function ( e ) {
		jQuery( '#' + msg_span_id ).html(
			lp_strings.new_twitter_window
		);
		show_oauth_popup( this.href );
		check_timeout = window.setTimeout( check_oauth_authorization_status, TIME_BETWEEN_CHECK_OAUTH_REQUESTS * 1000 );
		return false;
	} );
};

/**
 * Object containing the logic responsible for the Live Blogging Tools palette.
 *
 * @memberOf Livepress.Admin
 * @namespace
 * @constructor
 * @requires OORTLE.Livepress.LivepressHUD.init
 * @requires Collaboration.Edit.initialize
 */
Livepress.Admin.Tools = function () {
	var tools_link_wrap = document.createElement( 'div' );
	tools_link_wrap.className = 'hide-if-no-js screen-meta-toggle';
	tools_link_wrap.setAttribute( 'id', 'blogging-tools-link-wrap' );

	var tools_link = document.createElement( 'a' );
	tools_link.className = 'show-settings';
	tools_link.setAttribute( 'id', 'blogging-tools-link' );
	tools_link.setAttribute( 'href', '#blogging-tools-wrap' );

	var tools_wrap = document.createElement( 'div' );
	tools_wrap.className = 'hidden';
	tools_wrap.setAttribute( 'id', 'blogging-tools-wrap' );

	var $tools_link_wrap = jQuery( tools_link_wrap ),
		$tools_wrap = jQuery( tools_wrap );

	/**
	 * Asynchronously load the markup for the Live Blogging Tools palette from the server and inject it into the page.
	 *
	 * @private
	 */
	function getTabs () {
		jQuery.ajax({
			url:     LivepressConfig.ajax_url,
			data:    {
				action:  'lp_get_blogging_tools',
				_ajax_nonce: LivepressConfig.ajax_render_tabs,
				post_id: LivepressConfig.post_id
			},
			type:    'post',
			success: function (data) {
				tools_wrap.innerHTML = data;

				jQuery('.blogging-tools-tabs').on('click', 'a', function (e) {
					var link = jQuery(this), panel;

					e.preventDefault();

					if (link.is('.active a')) {
						return false;
					}

					jQuery('.blogging-tools-tabs .active').removeClass('active');
					link.parent('li').addClass('active');

					panel = jQuery(link.attr('href'));

					jQuery('.blogging-tools-content').not(panel).removeClass('active').hide();
					panel.addClass('active').show();

					return false;
				});

				wireupDefaults();
			}
		});
	}

  // If we found the Facebook plugin on PHP, load the Facebook js for
  // admin panel post embedding:
  function checkFacebook(){
    if(LivepressConfig.facebook === 'yes'){
      if ( typeof(FB) !== 'undefined'){
        FB.XFBML.parse();
      } else {
        var fb_script = "//connect.facebook.net/" + LivepressConfig.locale + "/all.js";
        jQuery.getScript(fb_script, function(){
          window.FB.init();
          FB.XFBML.parse();
        });
      }
    }
  }

	/**
	 * Configure event handlers for default Live Blogging Tools tabs.
	 *
	 * @private
	 */
	function wireupDefaults () {
		var nonces = jQuery('#blogging-tool-nonces'),
			SELF   = this,
			promises;

		// Save and refresh when clicking the first update sticky toggle
		jQuery( '#pinfirst' ).on( 'click', function() {
			jQuery( 'form#post' ).submit();
		});

		jQuery('#tab-panel-live-notes').on('click', 'input[type="submit"]', function (e) {
			var submit = jQuery( this ),
				status = jQuery( '.live-notes-status' );
			e.preventDefault();

			jQuery.ajax({
				url:     LivepressConfig.ajax_url,
				data:    {
					action:     'lp_update-live-notes',
					post_id:    LivepressConfig.post_id,
					content:    jQuery('#live-notes-text').val(),
					ajax_nonce: nonces.data('live-notes')
				},
				type:    'post',
				success: function (data) {
					status.show();

					setTimeout( function() {
						status.addClass( 'hide-fade' );

						setTimeout( function() {
							status.hide().removeClass( 'hide-fade' );
						}, 3000 );
					}, 2000 );
				}
			});
		});

		jQuery('#lp-pub-status-bar').on('click', 'a.toggle-live', function (e) {
			e.preventDefault();

			// If already live, warn user
			if ( jQuery( '#livepress_status_meta_box' ).hasClass( 'live' ) ) {
				if ( confirm( lp_strings.confirm_switch ) ){
					Dashboard.Twitter.removeAllTerms();
					Dashboard.Twitter.removeAllTweets();
					promises = toggleLiveStatus();
					jQuery.when( promises ).done( function() {
						// When toggle complete, redirect to complete the merge
						jQuery( 'form#post' )
							.attr( 'action', jQuery( 'form#post' ).attr( 'action' ) +
								'?post=' + LivepressConfig.post_id + '&action=edit' +
								'&merge_action=merge_children&merge_noonce=' + nonces.data( 'merge-post' ) )
							.submit();
					});
				}
			} else {
				// Transitioning to live
				promises = toggleLiveStatus();
				jQuery.when( promises ).done( function() {
					setTimeout( function(){
						jQuery( '#publish' ).click();
					}, 50);
				});
			}

	});

	/**
	 * Toggle live status with server
	 */
	function toggleLiveStatus() {
		var promise1,
			promise2;

		promise1 = jQuery.ajax({
			url:     LivepressConfig.ajax_url,
			data:    {
				action:     'lp_update-live-status',
				post_id:    LivepressConfig.post_id,
				ajax_nonce: nonces.data('live-status')
			},
			type:    'post'
		});

		promise2 = jQuery.ajax({
			url:      LivepressConfig.ajax_url,
			data:     {
				action:  'lp_update-live-comments',
				post_id: LivepressConfig.post_id,
				_ajax_nonce: LivepressConfig.ajax_update_live_comments
			},
			type:     'post',
			dataType: 'json'
		});

		// Return a promise combining callbacks
		return ( jQuery.when( promise1, promise2 ) );
	}
}
	/**
	 * Render the loaded default tabs into the UI.
	 */
	this.render = function () {
		var element = document.getElementById( 'blogging-tools-link-wrap' );
		if ( null !== element ) {
			element.parentNode.removeChild( element );
		}

		element = document.getElementById( 'blogging-tools-link' );
		if ( null !== element ) {
			element.parentNode.removeChild( element );
		}

		element = document.getElementById( 'blogging-tools-wrap' );
		if ( null !== element ) {
			element.parentNode.removeChild( element );
		}

		//tools_link.innerText = 'Live Blogging Tools';
		$tools_link_wrap.append( tools_link );
		jQuery( tools_link ).html( '<span class="icon-livepress-logo"></span> ' + lp_strings.tools_link_text );
		$tools_link_wrap.insertAfter('#screen-options-link-wrap');

		$tools_wrap.insertAfter('#screen-options-wrap');

		getTabs();

		checkFacebook();

		jQuery( window ).trigger( 'livepress.blogging-tools-loaded' );
	};
};

function update_embeds(){
  var settings = {};
  if ( typeof _wpmejsSettings !== 'undefined' ) {
    settings.pluginPath = _wpmejsSettings.pluginPath;
  }
  settings.success = function (mejs) {
    var autoplay = mejs.attributes.autoplay && 'false' !== mejs.attributes.autoplay;
    if ( 'flash' === mejs.pluginType && autoplay ) {
      mejs.addEventListener( 'canplay', function () {
        mejs.play();
      }, false );
    }
  };
  jQuery( ".wp-audio-shortcode, .wp-video-shortcode" ).not('.mejs-container').mediaelementplayer( settings );

  if ( typeof(FB) !== 'undefined'){
    FB.XFBML.parse();
  }
}


jQuery(function () {
	var pstatus = new Livepress.Admin.PostStatus(),
		post_to_twitter = new Livepress.Admin.PostToTwitter();

	pstatus.check();

	/**
	 * Title: LivePress Plugin Documentation
	 * Details the current LivePress plugin architecture (mostly internal objects).
	 * This plugin works by splitting a blog entry into micro posts (identified by special div tags),
	 * receiving new micro posts from other authors (ajax) while allowing the logged-in
	 * user to post micro post updates (ajax).
	 *
	 * Authors:
	 * Mauvis Ledford <switchstatement@gmail.com>
	 * Filipe Giusti <filipegiusti@gmail.com>
	 *
	 * Section: (global variables)
	 * These are variables accessible in the global Window namespace.
	 *
	 * method: tinyMCE.activeEditor.execCommand('livepress-activate')
	 * Enables Livepress mode. Hides original TinyMCE area and displays LiveCanvas area. (Note that actual method call depends on <namespace>.)
	 *
	 * method: tinyMCE.activeEditor.execCommand('livepress-deactivate')
	 * Disables Livepress mode. Redisplays original TinyMCE area. Hides LiveCanvas area. (Note that actual method call depends on <namespace>.)
	 *
	 * Events: global jQuery events.
	 * See full list. LivePress events let you tie arbitrary code execution to specific happenings.
	 *
	 * start.livepress       -  _(event)_ Triggered when LivePress is starting.
	 * stop.livepress        -  _(event)_ Triggered when LivePress is stopping.
	 * connected.livepress    -  _(event)_ Triggered when LivePress is connected.
	 * disconnected.livepress -  _(event)_ Triggered when LivePress is disconnected.
	 * livepress.post_update -  _(event)_ Triggered when a new post update is made.
	 *
	 * Example: To listen for a livepress event run the following.
	 *
	 * >  $j(document).bind('stop.livepress', function(){
	 * >    alert(1);
	 * >  });
	 */

	/**
	 * Custom animate plugin for animating LivePressHUD.
	 *
	 * @memberOf jQuery
	 * @param {Number} newInt New integer to which we are animating.
	 */
	jQuery.fn.animateTo = function (newInt) {
		var incremental = 1,
			$this = jQuery(this),
			oldInt = parseInt($this.text(), 10),
			t,
			ani;

		if (oldInt === newInt) {
			return;
		}

		if (newInt < oldInt) {
			incremental = incremental * -1;
		}

		ani = (function () {
			return function () {
				oldInt = oldInt + incremental;
				$this.text(oldInt);
				if (oldInt === newInt) {
					clearInterval(t);
				}
			};
		}());
		t = setInterval(ani, 50);
	};

	if (OORTLE.Livepress === undefined && window.tinymce !== undefined) {
		/**
		 * Global Livepress object inside the OORTLE namespace. Distinct from the global window.Livepress object.
		 *
		 * @namespace
		 * @memberOf OORTLE
		 * @type {Object}
		 */
		OORTLE.Livepress = (function () {
			var LIVEPRESS_DEBUG = false,
				LiveCanvas,
				$liveCanvas,
				/**
				 * Object that controls the Micro post form that replaces (hides) the original WordPress TinyMCE form.
				 * It is blue instead of grey and posts (ajax) to the LiveCanvas area.
				 *
				 * @private
				 */
					microPostForm,

				/** Plugin namespace used for DOM ids and CSS classes. */
					namespace = 'livepress',
				/** Name of the current plugin. Must also be the name of the plugin directory. */
					pluginName = 'livepress-wp',
				i18n = tinyMCE.i18n,
				/** Default namespace of the current plugin. */
					pluginNamespace = 'en.livepress.',
				/** Relative folder for the TinyMCE plugin. */
					path = LivepressConfig.lp_plugin_url + 'tinymce/',

				/** Base directory from where the Livepress TinyMCE plugin loads. */
					d = tinyMCE.baseURI.directory,

				/** All configurable options. */
					config = LivepressConfig.PostMetainfo,

				$eDoc,
				$eHTML,
				$eHead,
				$eBody,

				livepressHUD,
				inittedOnce,
				username,
				Helper,
				livePressChatStarted = false,
				placementOrder = config.placement_of_updates,

				$j = jQuery,
				$ = document.getElementById,

				extend = tinymce.extend,
				each = tinymce.each,

				livepress_init;

			window.eeActive = false;

			// local i18n object, merges into tinyMCE.i18n
			each({
				// HUD
				'start':      lp_strings.start_live,
				'stop':       lp_strings.stop_live,
				'readers':    lp_strings.readers_online,
				'updates':    lp_strings.lp_updates_posted,
				'comments':   lp_strings.comments,

				// TOGGLE
				'toggle':     lp_strings.click_to_toggle,
				'title':      lp_strings.real_time_editor,
				'toggleOff':  lp_strings.off,
				'toggleOn':   lp_strings.on,
				'toggleChat': lp_strings.private_chat
			}, function (val, name, obj) {
				i18n[pluginNamespace + name] = val;
			});

			/**
			 * Creates a stylesheet link for the specified file.
			 *
			 * @param {String} file Filename of the CSS to include.
			 * @return {Object} jQuery object containing the new stylesheet reference.
			 */
			function $makeStyle (file) {
				return $j('<link rel="stylesheet" type="text/css" href="' + path + 'css/' + file + '.css' + '?' + (+new Date()) + '" />');
			}

			/**
			 * Retrieve a string from the i18n object.
			 *
			 * @param {String} string Key to pull from the i18n object.
			 * @returns {String} Value stored in the i18n object.
			 */
			function getLang (string) {
				return i18n[pluginNamespace + string];
			}

			/**
			 * Informs you of current live posters, comments, etc.
			 *
			 * @namespace
			 * @memberOf OORTLE.Livepress
			 * @returns {Object}
			 * @type {LivepressHUD}
			 * @constructor
			 */
			function LivepressHUD () {
				var $livepressHud,
					$posts;

				/** Hide the HUD. */
				this.hide = function () {
					$livepressHud.slideUp('fast');
				};

				/** Hide the HUD. */
				this.show = function () {
					$livepressHud.slideDown('fast');
				};

				/**
				 * Initialize the HUD object.
				 *
				 * @see LivepressHUD#show
				 */
				this.init = function () {
					$livepressHud = $j('.inner-lp-dashboard');
					$posts = $j('#livepress-updates_num');
					this.show();
				};

				/**
				 * Update the number of readers subscribed to the post.
				 *
				 * @param {Number} number New number of readers.
				 * @returns {Object} jQuery object containing the readers display element.
				 */
				this.updateReaders = function (number) {
					var label = ( 1 === number ) ? lp_strings.persons_online : lp_strings.people_online,
						$readers = $j('#livepress-online_num');
					$readers.siblings( '.label' ).text( label );
					return $readers.text(number);
				};

				/**
				 * Update the number of live updates.
				 *
				 * @param {Number} number New number of updates.
				 * @returns {Object} jQuery object containing the updates display element.
				 */
				this.updateLivePosts = function (number) {
					if ($posts.length === 0) {
						$posts = $j('#livepress-updates_num');
					}

					return $posts.text(number);
				};

				/**
				 * Update the number of comments.
				 *
				 * @param {Number} number New number of comments.
				 * @return {Object} jQuery object containing the comments display element.
				 */
				this.updateComments = function (number) {
					var label = ( 1 === number ) ? lp_strings.comment : lp_strings.comments,
						$comments = $j('#livepress-comments_num');

					$comments.siblings( '.label' ).text( label );

					return $comments.text(number);
				};

				/**
				 * Add to the total number of comments.
				 *
				 * @param {Number} number Number to add to the current number of comments.
				 */
				this.sumToComments = function (number) {
					var $comments = $j('#livepress-comments_num'),
						actual = parseInt($comments.text(), 10 ),
						count = actual + number,
						label = ( 1 === count ) ? lp_strings.comment : lp_strings.comments;

					$comments.siblings( '.label' ).text( label );
					$comments.text( count );
				};

				return this;
			}

			jQuery(window).on('start.livechat', function () {
				if (!livePressChatStarted) {
					livePressChatStarted = true;

					$j('<link rel="stylesheet" type="text/css" href="' + LivepressConfig.lp_plugin_url + 'css/collaboration.css' + '?' + (+new Date()) + '" />').appendTo('head');

					// load custom version of jQuery.ui if it doesn't contain the dialogue plugin
					if (!jQuery.fn.dialog) {
						$j.getScript(path + 'jquery.ui.js', function () {
							$makeStyle('flick/jquery-ui-1.7.2.custom').appendTo('head');
							// Out of first LP release
							//          Collaboration.Chat.initialize();
						});
						//} else {
						// Out of first LP release
						//        Collaboration.Chat.initialize();
					}
				} else {
					console.log('already loaded chat before');
					// Out of first LP release
					//      Collaboration.Chat.initialize();
				}
			});

			$makeStyle('outside').appendTo('head');

			/**
			 * Initialize the Livepress HUD.
			 */
			livepress_init = function () {

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

				// On first init we load in external and internal styles
				// on secondary called we just disable / enable
				if (!inittedOnce) {
					inittedOnce = true;


					var activeEditor = tinyMCE.activeEditor;
					if (activeEditor) {
						$eDoc = $j(tinyMCE.activeEditor.getDoc());
						$eHTML = $eDoc.find('html:first');
						$eHead = $eDoc.find('head:first');
						$eBody = $eDoc.find('body:first');
					} else {
						$eDoc = $eHTML = $eHead = null;
					}

					if (LivepressConfig.debug !== undefined && LivepressConfig.debug) {
						$j(document).find('html:first').addClass('debug');
						if ($eDoc !== null) {
							$eDoc.find('html:first').addClass('debug');
						}
					}

				}

				OORTLE.Livepress.LivepressHUD.show();

				setTimeout(LiveCanvas.init, 1);

				$j(document.body).add($eHTML).addClass(namespace + '-active');
			};

			var toggleTabs = function( $buttonDiv ) {
				$buttonDiv.first().find( 'a' ).each( function () {
					jQuery( this ).toggleClass( 'active' );
				});
			};

			jQuery(window).on('start.livepress', function () {
				window.eeActive = true;
				window.eeTextActive = false;
				// switch to visual mode if in html mode
				if (!tinyMCE.editors.content) {
					switchEditors.go('content', 'tmce');
				}

				addEditorTabControlListeners( jQuery( '.wp-editor-tabs' ), 'content', '.wp-editor-tabs' );

				// remove the old visual editor
				jQuery( '.wp-editor-tabs > a.switch-tmce' ).remove();

				// A timeout is used to give TinyMCE a time to invoke itself and refresh the DOM.
				setTimeout(livepress_init, 5);
			});

			jQuery(window).on('stop.livepress', function () {
				window.eeActive = false;
				OORTLE.Livepress.LivepressHUD.hide();
				// Stops the livepress collaborative edit
				Collaboration.Edit.destroy();

				$j(document.body).add($eHTML).removeClass(namespace + '-active');
				LiveCanvas.hide();
			});

			/**
			 * Add listeners for the text/html Real-time tabs
			 */
			function addEditorTabControlListeners( $buttonDiv, editorID, toggleFinder, editor ) {

				$buttonDiv.find( 'a.switch-livepress' ).off( 'click' ).on( 'click', function( e ) {
					if ( jQuery( this ).hasClass( 'active' ) ){
						return;
					}
					if ( 'undefined' === typeof editor ) {
						switchEditors.go( 'content', 'toggle');
					} else {
						editor.show();
					}
					window.eeTextActive = false;
					toggleTabs( ( ( 'undefined' === typeof( toggleFinder ) || '' === toggleFinder ) ? $buttonDiv : jQuery( toggleFinder ) ) );
					return false;
				});

				$buttonDiv.find( 'a.switch-livepress-html' ).off( 'click' ).on( 'click', function( e ) {
					if ( jQuery( this ).hasClass( 'active' ) ){
						return;
					}
					switchEditors.go( editorID, 'html');
					window.eeTextActive = true;
					toggleTabs( ( ( 'undefined' === typeof( toggleFinder ) || '' === toggleFinder ) ? $buttonDiv : jQuery( toggleFinder ) ) );
					return false;
				});
			}

			/**
			 * Generic helper object for the real-time editor.
			 *
			 * @returns {Object}
			 * @constructor
			 * @memberOf OORTLE.Livepress
			 * @private
			 */
			function InnerHelper () {
				var SELF = this,
					NONCES = jQuery( '#livepress-nonces' ),
					hasRegex = new RegExp("\\[livepress_metainfo[^\\]]*]"),
					onlyRegex = new RegExp("^\\s*\\[livepress_metainfo[^\\]]*]\\s*$");

				SELF.getGravatars = function() {
					// Set up the gravatar array
					var gravatars = [];
					for ( var i = 0, len = lp_strings.lp_gravatars.length; i < len; i++ ) {
						gravatars[ lp_strings.lp_gravatars[ i ].id ] = lp_strings.lp_gravatars[ i ].avatar;
					}
					return gravatars;
				};

				SELF.getGravatarLinks = function() {
					// Set up the gravatar array
					var links = [];
					for ( var i = 0, len = lp_strings.lp_avatar_links.length; i < len; i++ ) {
						links[ lp_strings.lp_avatar_links[ i ].id ] = lp_strings.lp_avatar_links[ i ].link;
					}
					return links;
				};


				SELF.gravatars   = SELF.getGravatars();
				SELF.avatarLinks = SELF.getGravatarLinks();

				/**
				 * Dispatch a specific action to the server asynchronously.
				 *
				 * @param {String} action Server-side action to invoke.
				 * @param {String} content Content in the current editor window.
				 * @param {Function} callback Function to invoke upon success or failure.
				 * @param {Object} additional Additional parameters to send with the request
				 * @return {Object} jQuery.
				 */
				SELF.ajaxAction = function (action, content, callback, additional) {
					return jQuery.ajax({
						type:     "POST",
						url:      LivepressConfig.ajax_url,
						data:     jQuery.extend({
							action:  action,
							content: content,
							post_id: LivepressConfig.post_id
						}, additional || {}),
						dataType: "json",
						success:  callback,
						error:    function (jqXHR, textStatus, errorThrown) {
							callback.apply(jqXHR, [undefined, textStatus, errorThrown]);
						}
					});
				};

				/**
				 * Start the Real-Time Editor.
				 *
				 * @param {String} content Content in the current editor window.
				 * @param {Function} callback Function to invoke upon success or failure,
				 * @returns {Object} jQuery
				 */
				SELF.doStartEE = function (content, callback) {
					return SELF.ajaxAction('start_ee', content, callback);
				};

				/**
				 * Append a new post update.
				 *
				 * @param {String} content Content to append.
				 * @param {Function} callback Function to invoke after processing.
				 * @param {String} title Title of the update.
				 * @returns {Object} jQuery
				 */
				SELF.appendPostUpdate = function (content, callback, title) {
					var args = (title === undefined) ? {} : {title: title};
					args.liveTags    = this.getCurrentLiveTags();
					args._ajax_nonce = NONCES.data('append-nonce');

					jQuery('.peeklink span, .peekmessage').removeClass('hidden');
					jQuery('.peek').addClass('live');
					jQuery( window ).trigger( 'livepress.post_update' );
					window.setTimeout( update_embeds, 3000 );
					return SELF.ajaxAction( 'lp_append_post_update', content, callback, args);
				};

				/**
				 * Modify a specific post update.
				 *
				 * @param {string} updateId ID of the update to change.
				 * @param {String} content Content with which to modify the update.
				 * @param {Function} callback Function to invoke after processing.
				 * @returns {Object} jQuery
				 */
				SELF.changePostUpdate = function (updateId, content, callback) {
					var nonce = NONCES.data('change-nonce');
					return SELF.ajaxAction('lp_change_post_update', content, callback, {update_id: updateId, _ajax_nonce: nonce});
				};

				/**
				 * Remove a post update.
				 *
				 * @param {String} updateId ID of the update to remove.
				 * @param {Function} callback Function to invoke after processing.
				 * @returns {Object} jQuery
				 */
				SELF.deletePostUpdate = function (updateId, callback) {
					var nonce = NONCES.data('delete-nonce');
					return SELF.ajaxAction('lp_delete_post_update', "", callback, {update_id: updateId, _ajax_nonce: nonce});
				};

				/**
				 * Get text from the editor, but preprocess it the same way WordPress does first.
				 *
				 * @param {Object} editor TinyMCE editor instance.
				 * @returns {String} Preprocessed text from editor.
				 */
				SELF.getProcessedContent = function (editor) {
					if ( undefined === editor ) {
						return;
					}

					// Switch to the rich editor before saving
					if ( window.eeTextActive ){
						switchEditors.go('content', 'tmce');
					}

					var originalContent = editor.getContent();
					originalContent = originalContent.replace( '<p><br data-mce-bogus="1"></p>', '' );
					// Remove the toolbar/dashicons present on images/galleries since WordPress 3.9 and embeds since 4.0
					originalContent = originalContent.replace( '<div class="toolbar"><div class="dashicons dashicons-edit edit"></div><div class="dashicons dashicons-no-alt remove"></div></div>', '' );

					if ( '' === originalContent.trim() ) {
						editor.undoManager.redo();

						originalContent = editor.getContent( { format: 'raw' } ).replace( '<p><br data-mce-bogus="1"></p>', '' );

						if ( '' === originalContent.trim() ) {
							return;
						}
					}

					/**
					 * Add the live update header bar with timestamp (if option checked)
					 * and update header (if header entered).
					 */
					var editorID    = editor.editorContainer.id,
						$activeForm  = jQuery( '#' + editorID ).closest( '.livepress-update-form' );

					/**
					 * Update header
					 */
					var liveUpdateHeader = $activeForm.find( '.liveupdate-header' ),
						processed = switchEditors.pre_wpautop(originalContent),

						/**
						 * Live Tags Support
						 */
						// Add tags if present
						$livetagField = $activeForm.find( '.livepress-live-tag' ),
						liveTags = SELF.getCurrentLiveTags( $activeForm ),
						authors  = SELF.getCurrentAuthors( $activeForm ),

						// Save any new tags back to the taxonomy via ajax
						newTags = jQuery( liveTags ).not( LivepressConfig.live_update_tags ).get(),
						$timestampCheckbox = jQuery('.livepress-timestamp-option input[type="checkbox"]'),
						includeTimestamp = ( 0 === $timestampCheckbox.length ) || $timestampCheckbox.is(':checked');

					// Save the existing tags back to the select2 field
					if ( 0 !== newTags.length ) {
						LivepressConfig.live_update_tags = LivepressConfig.live_update_tags.concat( newTags );
						$livetagField.select2( { tags: LivepressConfig.live_update_tags } );
						jQuery.ajax({
							method: 'post',
							url:     LivepressConfig.ajax_url,
							data:    {
								action:           'lp_add_live_update_tags',
								_ajax_nonce:      LivepressConfig.lp_add_live_update_tags_nonce,
								new_tags:         JSON.stringify( newTags ),
								post_id:          LivepressConfig.post_id
							},
							success: function() {
								console.log( 'Added new tags to taxonomy' );
							}
						});
					}
					// edge case if you have backslahes
					processed = processed.replaceAll( '\\', '\\\\' );
					// Wrap the inner update
					if ( -1 === processed.search( 'livepress-update-inner-wrapper' ) ) { /* don't double add */
						processed = '<div class="livepress-update-inner-wrapper">\n\n' + processed + '\n\n</div>';
					}

					/**
					 * Next, construct the transmitted update based on the update format
					 */
					processed = SELF.newMetainfoShortcode( includeTimestamp, liveUpdateHeader ) +
								( ( 0 !== liveTags.length ) ? SELF.getLiveTagsHTML( liveTags ) : '' ) +
								processed;

					if ( 'default' !== LivepressConfig.update_format ) {
						processed += ( ( 0 !== authors.length ) ? SELF.getAuthorsHTML( authors ) : '' );
					}

					return processed;
				};

				/**
				 * Get the list of live tags added on this update
				 */
				SELF.getCurrentLiveTags = function( $activeForm ) {
					if ( 'undefined' === typeof $activeForm ) {
						$activeForm  = jQuery( '.livepress-update-form' );
					}
					return $activeForm.find( '.livepress-live-tag' ).select2( 'val' );
				};

				/**
				 * Get the list of authors added on this update
				 */
				SELF.getCurrentAuthors = function( $activeForm ) {
					return $activeForm.find( '.liveupdate-byline' ).select2( 'data' );
				};

				/**
				 * Wrap the avatar image or name in a link
				 */
				SELF.linkToAvatar = function( html, userid ) {
					if ( 'undefined' !== typeof SELF.avatarLinks[ userid ] && '' !== SELF.avatarLinks[ userid ] ) {
						return '<a href="' + SELF.avatarLinks[ userid ] + '" target="_blank">' + html + '</a>';
					}
				};

				/**
				 * Get the html for displaying the authors for this live update.
				 *
				 * @param  Array  Authors array of authors for this update.
				 *
				 * @return String HTML to display the authors.
				 *
				 */
				SELF.getAuthorsHTML = function( authors ){
					var toReturn = '';

					if ( 0 === authors ) {
						return '';
					}
					// Start the tags div
					toReturn += '\n<div class="live-update-authors">';

					// add each of the authors as a span
					jQuery.each( authors, function(){
						toReturn += '<span class="live-update-author live-update-author-' +
						this.text.replace(/\s/g, '-').toLowerCase() + '">';
						toReturn += '<span class="lp-authorID">' + this.id + '</span>';
						if ( 'undefined' !== typeof SELF.gravatars[ this.id ] && '' !== SELF.gravatars[ this.id ] ) {
							toReturn += '<span class="live-author-gravatar">' + SELF.linkToAvatar( SELF.gravatars[ this.id ], this.id ) +  '</span>';
						}

						toReturn += '<span class="live-author-name">' + SELF.linkToAvatar( this.text, this.id ) + '</span></span>';
					} );

					// Close the divs tag
					toReturn += '</div>';

					return toReturn;

				};

				/**
				 * Get the html for the live update tags.
				 *
				 * @param  Array An array of tags
				 *
				 * @return The HTML to append to the live update.
				 *
				 */
				SELF.getLiveTagsHTML = function( liveTags ) {
					var toReturn = '';

					if ( 0 === liveTags ) {
						return '';
					}
					// Start the tags div
					toReturn += '<div class="live-update-livetags">';

					// add each of the tags as a span
					jQuery.each( liveTags, function(){
						toReturn += '<span class="live-update-livetag live-update-livetag-' +
						this + '">' +
						this + '</span>';
					} );

					// Close the divs tag
					toReturn += '</div>';

					return toReturn;
				};

				/**
				 * Gets the metainfo shortcode.
				 *
				 * @param {Boolean} showTimstamp Flag to determine whether to show the timestamp.
				 * @returns {String} Metainfo shortcode.
				 */
				SELF.newMetainfoShortcode = function (showTimstamp, $liveUpdateHeader) {
					var metainfo = "[livepress_metainfo",
						d,
						utc,
						server_time;

					// Used stored meta information when editing an existing update
					var show_timestmp =  $liveUpdateHeader.data( 'show_timestmp' );
						console.log( 'show_timestmp - ' + show_timestmp );

					if ( '1' === show_timestmp ) {
						var time      = $liveUpdateHeader.data( 'time' ),
							timestamp = $liveUpdateHeader.data( 'timestamp' );

						metainfo += ' show_timestmp="1"';

						if ( 'undefined' !== typeof time ) {
							metainfo += ' time="' + time + '"';
						}

						if ( 'undefined' !== typeof timestamp ) {
							metainfo += ' timestamp="' + timestamp + '"';
						}
					} else {
						if( showTimstamp ) {
							metainfo += ' show_timestmp="1"';
							d = new Date();
							utc = d.getTime() + (d.getTimezoneOffset() * 60000); // Minutes to milisec
							server_time = utc + (3600000 * LivepressConfig.blog_gmt_offset); // Hours to milisec
							server_time = new Date(server_time);
							if (LivepressConfig.timestamp_template) {
								if (window.eeActive) {
									metainfo += ' time="' + server_time.format( LivepressConfig.timestamp_template ) + '"';
									metainfo += ' timestamp="' + d.toISOString() + '"';
								} else {
									metainfo += ' POSTTIME ';
								}
							}
						}
					}

					if (LivepressConfig.has_avatar) {
						metainfo += ' has_avatar="1"';
					}

					if ( 'undefined' !== typeof $liveUpdateHeader.val() && '' !== $liveUpdateHeader.val() ) {
						metainfo += ' update_header="' + encodeURI( decodeURI( $liveUpdateHeader.val() ) ) + '"';
					}
					metainfo += "]";

					return metainfo;
				};

				/**
				 * Tests if metainfo shortcode included in text.
				 *
				 * @param {String} text Text to test.
				 * @return {Boolean} Whether or not meta info is included in the shortcode.
				 */
				SELF.hasMetainfoShortcode = function (text) {
					return hasRegex.test(text);
				};

				/**
				 * Tests if text is ''only'' the metainfo shortcode.
				 *
				 * @param {String} text Text to test.
				 * @return {Boolean} Whether or not text is only the metainfo shortcode.
				 */
				SELF.hasMetainfoShortcodeOnly = function (text) {
					return onlyRegex.test(text);
				};
			}

			Helper = new InnerHelper();

			/**
			 * Class that creates and controls TinyMCE-enabled form.
			 * - called by the LiveCanvas whenever a new post needs to be added or edited.
			 * - It converts selected element into a tinymce area and adds buttons to performs particular actions.
			 * - It currently has two modes "new" or "editing".
			 *
			 * @param {String} mode Either 'editing' or 'new.' If 'new,' will create a new TinyMCE form after element el.
			 * @param {String} el DOM identifier of element to be removed.
			 * @returns {Object} New Selection object
			 * @memberOf OORTLE.Livepress
			 * @constructor
			 * @private
			 */
			function Selection ( mode, el ) {
				var SELF = this;

				SELF.handle = namespace + '-tiny' + (+new Date());

				SELF.mode = mode;

				if (SELF.mode === 'new') {
					SELF.originalContent = "";
					if (tinyMCE.onRemoveEditor !== undefined) {
						/** As of WordPress 3.9, TinyMCE is upgraded to version 4 and the
						/*  old action methods are deprecated. This check ensures compatibility.
						**/
						if ( '3' === tinymce.majorVersion ){
							tinyMCE.onRemoveEditor.add( function (mgr, ed) {
								if ((ed !== SELF) && (tinyMCE.editors.hasOwnProperty(SELF.handle))) {
									try {
										tinyMCE.execCommand('mceFocus', false, SELF.handle);
									} catch (err) {
										console.log("mceFocus error", err);
									}
								}
								return true;
							});
						} else {
							tinyMCE.on( 'RemoveEditor', function (mgr, ed) {
								if ((ed !== SELF) && (tinyMCE.editors.hasOwnProperty(SELF.handle))) {
									try {
										tinyMCE.execCommand('mceFocus', false, SELF.handle);
										tinyMCE.editors[SELF.handle].focus();
									} catch (err) {
										console.log("mceFocus error", err);
										console.log( SELF.handle );
									}
								}
								return true;
							});
						}
					}
				} else if (SELF.mode === 'editing' || SELF.mode === 'deleting') {
					// saving old content in case of reset
					var $el = $j(el);
					SELF.originalHtmlContent = $el.data("originalHtmlContent") || $el;
					SELF.originalContent = $el.data("originalContent") || $el.data("nonExpandedContent") || '';
					SELF.originalId = $el.data('originalId');
					SELF.originalUpdateId = $el.attr('id');
					if (SELF.mode === 'deleting') {
						SELF.newEditContent = null; // Display to user content to be removed by update
					} else {
						SELF.newEditContent = $el.data("nonExpandedContent"); // Display to user current content
					}
				}

				// See switchEditors.go('tinymce') -- wpautop should be processed before running RichEditor
				SELF.newEditContent = SELF.newEditContent ? switchEditors.wpautop(SELF.newEditContent) : SELF.newEditContent;
				SELF.formLayout = SELF.formLayout.replace('{content}', SELF.newEditContent || SELF.originalContent || '');
				SELF.formLayout = SELF.formLayout.replace('{handle}', SELF.handle);
				SELF.$form = $j(SELF.formLayout)
					.find('div.livepress-form-actions:first').on( 'click', function (e) {
						SELF.onFormAction(e);
					} )
					.end()
					// running it this way is required since we don't have Function.bind
					.on( 'submit', function () {
						SELF.te.show();
						SELF.onSave();
						SELF.resetFormFieldStates( SELF );
						return false;
					} );
				SELF.$form.attr('id', SELF.originalUpdateId);
				SELF.$form.data('originalId', SELF.originalId);
				SELF.$form.addClass('livepress-update-form');
				if (mode === 'new') {
					SELF.$form.find('input.button:not(.primary)').remove();
					SELF.$form.find('a.livepress-delete').remove();
					SELF.$form.addClass(namespace + '-newform');
					if ($j('#post input[name=original_post_status]').val() !== 'publish') {
						SELF.$form.find('input.published').remove();
						SELF.$form.find('.quick-publish').remove();
					} else {
						SELF.$form.find('input.notpublished').remove();
					}
				} else {
					SELF.$form.find('.primary.button-primary').remove();
					SELF.$form.find('.livepress-timestamp-option').remove();
					if (mode === 'deleting') {
						SELF.$form.addClass(namespace + '-delform');
					}
				}
				// Add tag support to the form
				SELF.$form
					.find( 'input.livepress-live-tag' )
					.select2( {
						placeholder:     lp_strings.live_tags_select_placeholder,
						tokenSeparators: [','],
						tags:            LivepressConfig.live_update_tags
					});

				// Add byline support to the form
				SELF.$form
					.find( 'input.liveupdate-byline' )
					.select2( {
						tokenSeparators: [','],
						tags: lp_strings.lp_authors,
						initSelection: function ( element, callback ) {
							var data = { id: element.data( 'id' ), text: element.data( 'name' ) };
							callback( data );
						}
					});

				return this;
			}

			extend(Selection.prototype, {
				/*
				 * variable: mode
				 * The mode of this Selection object (currently either 'new' or 'editing' or 'deleting')
				 */
				mode:        null,

				/*
				 * variable: formLayout
				 */
				formLayout:  [
					'<form>',
					'<div class="livepress-update-header"><input type="text" placeholder="' + lp_strings.live_update_header + '"value="" autocomplete="off" class="liveupdate-header" name="liveupdate-header" id="liveupdate-header" /></div>',
					'<div class="editorcontainer">',
					'<textarea id="{handle}">{content}</textarea>',
					'</div>',
					'<div class="editorcontainerend"></div>',
					'<div class="livepress-form-actions">',
					'<div class="livepress-live-tags">' +
						'<input type="hidden" style="width:95%;" class="livepress-live-tag" />' +
					'</div>',
					'<div class="livepress-byline">' + lp_strings.live_update_byline + ' <input type="text" data-name="' + LivepressConfig.author_display_name + '" data-id="' + LivepressConfig.author_id + '" autocomplete="off" style="width: 70%; float: right; margin-left: 5px;" class="liveupdate-byline" name="liveupdate-byline" id="liveupdate-byline" /></div>',
					'<div class="livepress-timestamp-option"><input type="checkbox" checked="checked" /> ' + lp_strings.include_timestamp + '</div>',
					'<br /><a href="#" class="livepress-delete" data-action="delete">' + lp_strings.delete_perm + '</a>',
					'<span class="quick-publish">' + lp_strings.ctrl_enter + '</span>',
					'<input class="livepress-cancel button button-secondary" type="button" value="' + lp_strings.cancel + '" data-action="cancel" />',
					'<input class="livepress-update button button-primary" type="submit" value="' + lp_strings.save + '" data-action="update" />',
					'<input class="livepress-submit published primary button-primary" type="submit" value="' + lp_strings.push_update + '" data-action="update" />',
					'<input class="livepress-submit notpublished primary button-primary" type="submit" value="' + lp_strings.add_update + '" data-action="update" />',
					'</div>',
					'<div class="clear"></div>',
					'</form>'
				].join(''),

				/**
				 * Reset all the form buttons and fields to their default status,
				 * called when pushing a live update.
				 */
				resetFormFieldStates: function( SELF ) {
					SELF.$form.find( '.livepress-live-tag' ).select2( 'val', '' ).select2( {
						placeholder:     lp_strings.live_tags_select_placeholder,
						tokenSeparators: [','],
						tags:            LivepressConfig.live_update_tags
					});
					SELF.$form.find( '.liveupdate-header' ).val( '' );
					if ( "true" !== LivepressConfig.use_default_author ) {
						SELF.$form.find( '.liveupdate-byline' ).select2( 'val', '' );
					}

				},

				/*
				 * function: enableTiny
				 */
				enableTiny:  function (optClass) {
					var te,
						selection = this,
						SELF=this,
						/** As of WordPress 3.9, TinyMCE is upgraded to version 4 and the
						/*  old action methods are deprecated. This check ensures compatibility.
						**/
						mceAddCommand = ( '3' === tinymce.majorVersion ) ? 'mceAddControl' : 'mceAddEditor';

					// initialize default editor at required selector
					tinyMCE.execCommand( mceAddCommand, false, this.handle );
					te = tinyMCE.editors[this.handle];

					if (!te) {
						console.log("Unable to get editor for " + this.handle);
						return;
					}
					te.dom.loadCSS(path + 'css/inside.css' + '?' + (+new Date()));
					// only our punymce editors have this class, used for additional styling
					te.dom.addClass(te.dom.getRoot(), 'livepress_editor');
					if (optClass) {
						te.dom.addClass(te.dom.getRoot(), 'livepress-' + optClass + '_editor');
						te.dom.addClass(te.dom.getRoot().parentNode, 'livepress-' + optClass + '_editor');
					}
					if (config.has_avatar) {
						te.dom.addClass(te.dom.getRoot(), 'livepress-has-avatar');
					}

					/**
					 * Pull out existing header, authors and tags when editing
					 * an existing update
					 */
					var $domcontent = jQuery( te.dom.getRoot() ),
						editor      = te.editorContainer.id,
						$activeForm = jQuery( '#' + editor ).closest( '.livepress-update-form' ),
						content     = $domcontent.html();

					/**
					 * Handle the livepress_metainfo shortcode if present, including headline and timestamp
					 */
					if ( Helper.hasMetainfoShortcode( content ) ) {

						/**
						 * Remove the meta information from the content
						 */
						var	contentParts = content.split( '[livepress_metainfo' );

						if ( 'undefined' !== typeof contentParts[1] ) {
							content = contentParts[0] + contentParts[1].split(']')[1];
						}
						var metaInfo = contentParts[1].split(']')[0];

						// Extract the header from the metainfo shortcode
						var headerChunks = metaInfo.split( 'update_header="' ),
							$formHeader = $activeForm.find( '.liveupdate-header' );

						// If a header was found, add it to the editor
						if ( 'undefined' !== typeof headerChunks[1] ) {
							var header = headerChunks[1].split( '"' )[0];
							$formHeader.val( decodeURI( header ) );
						}

						// Extract the show_timestmp setting
						headerChunks = metaInfo.split( 'show_timestmp="' );
						if ( 'undefined' !== typeof headerChunks[1] ) {
							var show_timestmp = headerChunks[1].split( '"' )[0];
							$formHeader.data( 'show_timestmp', show_timestmp );
						} else {
							// default is on
							$formHeader.data( 'show_timestmp', '1' );
						}

						// Extract the time setting
						headerChunks = metaInfo.split( 'time="' );
						if ( 'undefined' !== typeof headerChunks[1] ) {
							var time = headerChunks[1].split( '"' )[0];
							$formHeader.data( 'time', time );
						}

						// Extract the timestamp setting
						headerChunks = metaInfo.split( 'timestamp="' );
						if ( 'undefined' !== typeof headerChunks[1] ) {
							var timestamp = headerChunks[1].split( '"' )[0];
							console.log( 'storing timestamp ' + timestamp );
							$formHeader.data( 'timestamp', "" + timestamp );
						}

					}

					/**
					 * Authors
					 */
					var theAuthors = [],
						authors = jQuery( content ).find( '.live-update-author' );

					// If no authors, use whats in the main author field
					if ( 0 === authors.length ) {
						var $a = SELF.$form.find( 'input.liveupdate-byline' );
						if ( '' !== $a.data( 'name' )  ) {
							theAuthors = [{
								'id':   $a.data( 'id' ),
								'text': $a.data( 'name' )
							}];
						}
					} else {
						jQuery( authors ).each( function( i, a ) {
							var $a = jQuery( a ),
							theAuthor = {
								'id':   $a.find( '.lp-authorID' ).text(),
								'text': $a.find( '.live-author-name' ).text()
							};
							theAuthors.push( theAuthor );
						});
					}
					$activeForm.find( '.liveupdate-byline' ).select2( 'data', theAuthors );

					var $content = jQuery( jQuery.parseHTML( content ) );

					/**
					 * Livetags
					 */
					var theTags = [],
						tags = $content.find( '.live-update-livetag' );

					jQuery( tags ).each( function( i, a ) {
						theTags.push( jQuery( a ).text() );
					});

					$activeForm.find( '.livepress-live-tag' ).select2( 'val', theTags );

					// Clear the tags and authors from the content
					content = $domcontent.find( '.livepress-update-inner-wrapper' ).html();

					// Reset the editor with the cleaned content
					$domcontent.html( content );
					/**
					 * Add onchange and ctrl-click event handlers for the editor
					 */
					SELF.setupEditorEventHandlers( te, selection );
					SELF.te = te;
					//SELF.setupEditorTabs( te, $activeForm, mceAddCommand );
					return te;
				},

				/**
				 * Set up the editor tab interactions
				 */
				setupEditorTabs: function( te, $activeForm, mceAddCommand ) {

					var $editorTabs = $activeForm.find( '.wp-editor-tabs' );
					$editorTabs.find( 'a.switch-livepress' ).off( 'click' ).on( 'click', function( e ) {
						if ( jQuery( this ).hasClass( 'active' ) ){
							return;
						}
						switchEditors.go( te.id, 'toggle');
						window.eeTextActive = false;
						$editorTabs.find( 'a' ).toggleClass( 'active' );
						return false;
					});

					$editorTabs.find( 'a.switch-livepress-html' ).off( 'click' ).on( 'click', function( e ) {
						if ( jQuery( this ).hasClass( 'active' ) ){
							return;
						}
						switchEditors.go( te.id, 'toggle');
						window.eeTextActive = true;

						$editorTabs.find( 'a' ).toggleClass( 'active' );
						return false;
					});


				},


				/**
				 * Handle key events, save on ctrl-enter
				 */
				handleKeyEvent: function( selection, te, e, ed ) {
					var SELF = this;

					if (e.ctrlKey && e.keyCode === 13) {
						e.preventDefault();
						if ( '3' === tinymce.majorVersion ){
							ed.undoManager.undo();
						}
						te.show();
						selection.onSave();
						SELF.resetFormFieldStates( SELF );
					}

				},

				/**
				 * Set up the event handlers for an editor
				 */
				setupEditorEventHandlers: function( te, selection ) {
					var SELF = this;
					/** As of WordPress 3.9, TinyMCE is upgraded to version 4 and the
					/*  old action methods are deprecated. This check ensures compatibility.
					**/
					if ( '3' === tinymce.majorVersion ){
						te.onKeyDown.add( function (ed, e) {
							SELF.handleKeyEvent( selection, te, e, ed );
						} );
					} else {
						te.on( 'KeyDown', function ( e ) {
							SELF.handleKeyEvent( selection, te, e );
						} );
					}
					jQuery( '.livepress-update-form' ).on( 'keydown', 'textarea', function( e ) {
						SELF.handleKeyEvent( selection, te, e );
					} );
				},

				/*
				 * function: disableTiny
				 */
				disableTiny: function () {
					tinyMCE.execCommand('mceRemoveControl', false, this.handle);
				},

				/*
				 * function: stash
				 */
				stash:       function () {
					this.disableTiny();
					this.$form.remove();
				},

				/**
				 * Synchronize content from Real-Time Editor to hidden normal editor.
				 * Copies full state of currently open editor, including open and not saved yet.
				 */
				syncData:       function () { if(false) {
					var newContent = "",
						addCnt,
						t;

					$liveCanvas.find('div.inside:first').children().each(function () {
						var $this = $j(this),
							cnt = $this.data('nonExpandedContent'),
							handle,
							te;

						if (!cnt) {
							handle = $this.find("textarea").attr('id');
							te = tinyMCE.editors[handle];
							if (handle) {
								if (te.dom.hasClass(te.dom.getRoot(), 'livepress-del_editor')) {
									cnt = "";
								} else {
									cnt = Helper.getProcessedContent(te);
								}
							}
							if (cnt !== "" && cnt.substr(-1) !== ">" && cnt.substr(-1) !== "\n") {
								cnt += "\n";
							}
						}
						newContent += cnt;
					});

					// Handle non-saved update
					addCnt = Helper.getProcessedContent( tinyMCE.editors[ microPostForm.handle ] );
					if ( undefined !== addCnt && ! Helper.hasMetainfoShortcodeOnly( addCnt ) ) {
						if (placementOrder === 'bottom') {
							newContent = newContent + "\n\n" + addCnt;
						} else {
							newContent = addCnt + "\n\n" + newContent;
						}
					}

					// We take over tinyMCE.editors.content namespace due to wordpress's hardcoding
					t = tinyMCE.editors.originalContent || tinyMCE.editors.content;
					t.setContent(switchEditors.wpautop(newContent), {format: 'raw'});
				}},

				/*
				 * function: mergeData
				 * merge data from external Edit info with current liveCanvas
				 */
                mergeIncrementalData: function (update) {
                    var $target, gen, $block, dom = new Livepress.DOMManipulator(""),
                        $inside_first = $liveCanvas.find('div.inside:first');
                    switch(update.op) {
                        case "append":
                        case "prepend":
                            // add at top or bottom
                            $target = $inside_first.find('div#livepress-update-'+update.id);
                            if($target.length > 0) {
                                // ignore already added update
                                return;
                            }
                            $block = $j(update.prefix + update.proceed + update.suffix);
							$block.data("nonExpandedContent", update.content);
							$block.data("originalContent", update.orig);
							$block.data("originalHtmlContent", update.origproc);
							$block.data("originalId", update.id);
							$block.attr("editStyle", "");
                            dom.process_twitter($block[0], update.proceed);
                            if(update.op==="append") {
                                $block.appendTo( $inside_first );
                            } else {
                                $block.prependTo( $inside_first );
                            }
                            Collaboration.Edit.update_live_posts_number();
                            break;
                        case "replace":
                            // update update
                            $target = $inside_first.find('div#livepress-update-'+update.id);
                            if($target.length < 1) {
                                // could happen if update come for already deleted update
                                // f.e., in scenario: add update, edit it, delete it, refresh
                                return;
                            }
                            gen = $target.data("lpg");
                            if (update.lpg <= gen) {
                                // will ignore update for own changes, and old updates
                                // received on first page load.
                                return;
                            }
                            $block = $j(update.prefix + update.proceed + update.suffix);
							$block.data("nonExpandedContent", update.content);
							$block.data("originalContent", update.orig);
							$block.data("originalHtmlContent", update.origproc);
							$block.data("originalId", update.id);
							$block.attr("editStyle", "");
                            dom.process_twitter($block[0], update.proceed);
                            $target.replaceWith($block);
                            break;
                        case "delete":
                            // remove update
                            $target = $inside_first.find('div#livepress-update-'+update.id).remove();
                            Collaboration.Edit.update_live_posts_number();
                            break;
                        default:
                            console.log("Unknown incremental operation", update.op, update);
                    }
                    return;
                },
				mergeData:      function (regions) {
                    if("op" in regions) {
                        return this.mergeIncrementalData(regions);
                    }
					var r, c, nc, reg, $block, curr = [], currlink = {}, reglink = {},
						$inside_first = $liveCanvas.find('div.inside:first');
					// Get list of currently visible regions
					$inside_first.children().each(function () {
						var $this = $j(this),
							cnt = $this.data('nonExpandedContent'),
							handle = $this.data('originalId');
						currlink[handle] = curr.length;
						curr[curr.length] = {'id': handle, 'cnt': cnt, 'handle': $this};
					});
					for (r = 0; r < regions.length; r++) {
						reglink[regions[r].id] = r;
					}
					// Merge list with incoming list
					for (r = 0, c = 0; r < regions.length && c < curr.length;) {
						if (regions[r].id === curr[c].id) {
							// nothing
							r++;
							c++;
						} else if (regions[r].id in currlink) {
							nc = currlink[regions[r].id];
							// from c to nc-1 regions are removed
							for (; c < nc; c++) {
								curr[c].handle.remove();
								curr[c].handle = undefined;
							}
							r++;
							c++;
						} else {
							// new region added just before c
							reg = regions[r];
							$block = $j(reg.prefix + reg.proceed + reg.suffix);
							$block.data("nonExpandedContent", reg.content);
							$block.data("originalContent", reg.orig);
							$block.data("originalHtmlContent", reg.origproc);
							$block.data("originalId", reg.id);
							$block.attr("editStyle", "");
							$block.insertBefore(curr[c].handle);
							r++; // left c untouched
						}
					}
					// Remove all regions not existed in received update
					for (; c < curr.length; c++) {
						curr[c].handle.remove();
					}
					// Append all new regions
					for (; r < regions.length; r++) {
						reg = regions[r];
						$block = $j(reg.prefix + reg.proceed + reg.suffix);
						$block.data("nonExpandedContent", reg.content);
						$block.data("originalContent", reg.orig);
						$block.data("originalHtmlContent", reg.origproc);
						$block.data("originalId", reg.id);
						$block.attr("editStyle", "");
						$inside_first.append($block);
					}
					Collaboration.Edit.update_live_posts_number();
				},

				/*
				 * function: onFormAction
				 */
				onFormAction:   function (e) {
					var val = e.target.getAttribute("data-action");
					if (val === 'cancel') {
						this.onCancel();
						return false;
					} else if (val === 'delete') {
						e.preventDefault();
						this.onDelete();
						return false;
					}
					// skipped (must be a save)...
				},

				/*
				 * function: displayContent
				 * replaces given element with formatted text update
				 */
				displayContent: function (el) {
					var $newPost = $j(this.originalHtmlContent);
					$newPost.data("nonExpandedContent", this.originalContent);
					$newPost.data("originalContent", this.originalHtmlContent);
                    $newPost.data("originalId", this.originalId);
					$newPost.attr('editStyle', ''); // on cancel, disable delete mode
					try { window.twttr.widgets.load($newPost[0]); } catch ( e ) {}
					$newPost.insertAfter(el);
					el.remove();
					this.addListeners($newPost);
					$liveCanvas.data('mode', '');
					return false;
				},

				/*
				 * function: onSave
				 * Modifies livepress-tiny.
				 */
				onSave:         function () {
					// First, we need to be sure we're toggling the update indicator if they're disabled
					var $bar = jQuery('#lp-pub-status-bar');

					// If the bar has this class, it means updates are disabled ... so don't do anything
					if (!$bar.hasClass('no-toggle')) {
						$bar.removeClass('not-live').addClass('live');

						$bar.find('a.toggle-live span').removeClass('hidden');
						$bar.find('.icon').addClass('live').removeClass('not-live');
						$bar.find('.first-line').find('.lp-on').removeClass('hidden');
						$bar.find('.first-line').find('.lp-off').addClass('hidden');
						$bar.find('.second-line').find('.inactive').addClass('hidden');
						$bar.find('.recent').removeClass('hidden');
					}

					var newContent = Helper.getProcessedContent(tinyMCE.editors[this.handle]);
					// Check, is update empty
					var hasTextNodes = $j('<div>').append(newContent).contents().filter(function () {
						return this.nodeType === 3 || this.innerHtml !== '' || this.innerText !== '';
					}).length > 0;
					var onlyMeta = Helper.hasMetainfoShortcodeOnly(newContent);
					if ((!hasTextNodes && $j(newContent).text().trim() === '') || onlyMeta) {
						if (this.mode === 'new') {
							var afterTitleUpdate = function (res) {
								return false;
							};
							// If new update empty -- just send title update
							Helper.appendPostUpdate("", afterTitleUpdate, $j('#title').val());
							return false;
						} else {
							// If user tries to save empty update -- that means that he want to delete it
							return this.onDelete();
						}
					} else {
						var $spinner = $j("<div class='lp-spinner'></div>");
						var $spin = $spinner;
						var afterUpdate = (function (self) {
							return function (region) {
                                var $target = $liveCanvas.find('div#livepress-update-'+region.id);
                                // Check, what faster: AJAX answer or oortle publish
                                if ($target.length > 0) {
                                    $spin.remove();
                                } else {
                                    self.originalHtmlContent = region.prefix + region.proceed + region.suffix;
                                    self.originalContent = region.content;
                                    self.originalId = region.id;
                                    self.displayContent($spin);
                                    Collaboration.Edit.update_live_posts_number();
                                }
							};
						}(this));
						// Save of non-empty update can be:
						// 1) append from new post form
						if (this.mode === 'new' /*&& this.handle == microPostForm.handle*/) {
							var action = placementOrder === 'bottom' ? 'appendTo' : 'prependTo';
							$spinner[action]($liveCanvas.find('div.inside:first'));
							tinyMCE.editors[this.handle].setContent("");
							Helper.appendPostUpdate(newContent, afterUpdate, $j('#title').val());
						} else
						// 2) save of newly appended text somewhere [TODO]
						/*if(this.mode === 'new') {
						} else*/
						// 3) change of already existent update
						{
							tinyMCE.execCommand('mceRemoveControl', false, this.handle);
							$spinner.insertAfter(this.$form);
							$spinner.data("nonExpandedContent", newContent); // Make sure syncData works even while spinner active
							this.$form.remove();
							Helper.changePostUpdate(this.originalId, newContent, afterUpdate);
						}
					}

					if ( 'new' !== this.mode ){
						tinyMCE.remove( tinyMCE.editors[this.handle] );
					}

					// Switch back to the text editor if set
					if ( window.eeTextActive ){
						switchEditors.go( 'content', 'html' );
					}


					return false;
				},

				/*
				 * function: onCancel
				 * Modifies livepress-tiny.
				 */
				onCancel:       function () {
					console.log('onCancel()');
					var newContent = Helper.getProcessedContent(tinyMCE.editors[this.handle]);
					var check = true;
					if (this.mode !== 'deleting' && newContent !== this.originalContent) {
						check = confirm( lp_strings.confirm_cancel );
					}
					if (check) {
						tinyMCE.execCommand('mceRemoveControl', false, this.handle);
						this.displayContent(this.$form);
						tinyMCE.remove( tinyMCE.editors[this.handle] );
					}
					return false;
				},

				/*
				 * function: onDelete
				 * Modifies livepress-tiny.
				 */
				onDelete:       function () {
					var check = confirm( lp_strings.confirm_delete );
					if (check) {
						tinyMCE.execCommand('mceRemoveControl', false, this.handle);
						tinyMCE.remove( tinyMCE.editors[this.handle] );
						var $spinner = $j("<div class='lp-spinner'></div>");
						$spinner.insertAfter(this.$form);
						this.$form.remove();

						Helper.deletePostUpdate(this.originalId, function () {
							$spinner.remove();
							Collaboration.Edit.update_live_posts_number();
						});
					}
					return false;
				},

				/*
				 * function: addListeners
				 * Adds hover and click events to new / edited posts.
				 */
				addListeners:   function ($el) {
					$el.hover(LiveCanvas.onUpdateHoverIn, LiveCanvas.onUpdateHoverOut);
					// Not trigger editing on mebedded elements.
					$el.find('div').not('.livepress-meta').find('a').on( 'click', function (ev) {
						ev.stopPropagation();
						ev.preventDefault();
					} );
				}

			});


			/*
			 * namespaces: LiveCanvas
			 * _(object)_ Object that controls the LiveCanvas area.
			 */
			LiveCanvas = (function () {
				var inittedOnce = 0;
				var initXhr = null;

				/*
				 * variable: $liveCanvas
				 * _(private)_ jQuery liveCanvas DOM element.
				 */
				$liveCanvas = $j([
					'<div id="livepress-canvas" class="">',
					'<h3><span id="livepress-updates_num">-</span> ' + lp_strings.updates + '</h3>',
					"<div class='inside'></div>",
					"</div>"
				].join(''));

				/*
				 * function: isEditing
				 * _(private)_
				 */
				var isEditing = function () {
					return $liveCanvas.data('mode') === 'editing';
				};

				/*
				 * function: onUpdateHoverIn
				 * _(public)_
				 */
				var onUpdateHoverIn = function () {
					if (!isEditing()) {
						$j(this).addClass('hover');
					}
				};

				/*
				 * function: showMicroPostForm
				 * _(public)_ Builds and enabled main micro post form.
				 */
				var showMicroPostForm = function (cnt) {
					console.log('Show micro post form');
					// if MicroPost form doesn't exist
					// add new micropost form
					microPostForm = new Selection('new', cnt);
					$liveCanvas.before(microPostForm.$form);
					microPostForm.enableTiny('main');

					// because (add) media buttons are hard coded to use wordpress TinyMCE.editors['content']
					// we must switch the references while our editor is active
					if (!tinyMCE.editors.originalContent) {
						tinyMCE.editors.originalContent = tinyMCE.editors.content;
						tinyMCE.editors.content = tinyMCE.editors[microPostForm.handle];
					}
				};

				var addPost = function (data) {
					console.log('addPost(data)');
					if (data.replace(/<p>\s*<\/p>/gi, "") === '') {
						return false;
					}
					var $newPost = $j(data);
					var action = (LivepressConfig.PostMetainfo.placement_of_updates === 'bottom' ? 'appendTo' : 'prependTo');

					$newPost.hide()[action]($liveCanvas.find('div.inside:first')).animate(
						{
							"height":  "toggle",
							"opacity": "toggle"
						},
						"slow",
						function () {
							$j(this).removeAttr('style');
						});
					return false;
				};

				/*
				 * function: hideMicroPostForm
				 * _(public)_
				 */
				var hideMicroPostForm = function () {
					if (microPostForm) {
						microPostForm.stash();
					}
					// because (add) media buttons are hard coded to use wordpress TinyMCE.editors['content']
					// we must switch the references while our editor is active
					tinyMCE.editors.content = tinyMCE.editors.originalContent;
					delete tinyMCE.editors.originalContent;
				};

				/*
				 * function: hide
				 * _(public)_
				 */
				var hide = function () {
					if (initXhr) {
						var xhr = initXhr;
						xhr.abort();
						initXhr = null;
					} else {
						$j('#post-body-content .livepress-newform,.secondary-editor-tools').hide();
						microPostForm.syncData();
						hideMicroPostForm();
						$liveCanvas.hide();
						$j('#postdivrich').show();
					}
				};

				/*
				 * funtion: mergeData
				 * _(public)_
				 */
				var mergeData = function (regions) {
					if (microPostForm !== undefined) {
						microPostForm.mergeData(regions);
					}
				};

				/*
				 * function: onUpdateHoverOut
				 * _(public)_
				 */
				var onUpdateHoverOut = function () {
					if (!isEditing()) {
						$j(this).removeClass('hover');
					}
				};

				/*
				 * function: livePressDisableCheck
				 * _(private)_ Called when disabling live blog, checks if there are unsaved editors open.
				 */
				var livePressDisableCheck = function () {
					if (isEditing()) {
						if (!confirm( lp_strings.discard_unsaved )) {
							return false;
						}
					}

					hide();
					return false;
				};

				/*
				 * function: init
				 * _(public)_ Hides original canvas and create live one.
				 */
				var init = function () {
					var postdivrich = document.getElementById( 'postdivrich' ),
						$postdivrich = jQuery( postdivrich );

					if (!window.eeActive) {
						return;
					} // break initialization cycle in case of interrupt
					if (!tinyMCE.editors.length || tinyMCE.editors.content === undefined || !tinyMCE.editors.content.initialized) {
						window.setTimeout(init, 50); // tinyMCE not initialized? try a bit later
						return;
					}
					if (!inittedOnce && !$j.fn.live) {
						$j.fn.live = $j.fn.livequery;
						// set a listener on disabling live blog since
						// we need to run checks before turning it off now that it's on
						$j( document.getElementById( 'live-blog-disable' ) ).on( 'click', livePressDisableCheck );
					}

					// hide original tinymce area
					$postdivrich.hide();

					var spinner = document.createElement( 'div' ),
						$spinner = jQuery( spinner );

					var spin_livecanvas = document.createElement( 'div' );
					spin_livecanvas.className = 'lp-spinner';
					spin_livecanvas.setAttribute( 'id', 'lp_spin_livecanvas' );
					spinner.appendChild( spin_livecanvas );
					var spin_p = document.createElement( 'p' );
					spin_p.style.textAlign = 'center';
					spin_p.innerText = lp_strings.loading_content + '...';
					spinner.appendChild( spin_p );

					var titlediv = document.getElementById( 'titlediv' );
					if ( titlediv.nextSibling ) {
						titlediv.parentNode.insertBefore( spinner, titlediv.nextSibling );
					} else {
						titlediv.parentNode.appendChild( spinner );
					}

					// get content from existing "old" tinymce editor
					var originalContent = Helper.getProcessedContent(tinyMCE.editors.content);

					// regions contains:
					//    orig -- last saved (visible to users) content
					//    user -- content from currently edit content
					//            will be omitted, if not differs
					// every part is array, where every element = region:
					//    id -- ID of region
					//    prefix -- wrapping prefix tag
					//    suffix -- wrapping end tag
					//    content -- original content
					//    proceed -- filtered resulting html
					// initial regions analyse:
					//    we should find:
					//    1. New region prepended
					//    2. New region appended
					//    3. Regions matching, not changed
					//    4. Regions matching, changed
					var analyseStartEE = function (in_regions) {
						var orig = in_regions.orig, user = in_regions.user;
						if (!orig) {
							orig = [];
						}
						if (!user) {
							return {"prepend": [], "append": [], "changed": [], "deleted": [], "regions": orig};
						}
						var prepend = [], append = [], changed = [], deleted = [], regions = [];
						var o = 0, c = 0, state = 0, i;
						// Discover head changes
						for (o = 0; o < orig.length && !state; o++) {
							var id = orig[o].id;
							for (c = 0; c < user.length && !state; c++) {
								if (id === user[c].id) {
									state = 1;
									if (o === 0 && c === 0) {
										// start of arrays equals, nothing was prepended
										state = 1; // do nothing, make JSLint happy
									}
									else if (o === 0 && c === 1) {
										// there one update appended
										prepend[prepend.length] = user[0].id;
										user[0].orig = "";
										user[0].origproc = "";
										regions[regions.length] = user[0];
									}
									else if (o === 1 && c === 1) {
										// there edit conflict: first update fully rewritten.
										prepend[prepend.length] = user[0].id;
										deleted[deleted.length] = orig[0].id;
										user[0].orig = "";
										user[0].origproc = "";
										regions[regions.length] = user[0];
										orig[0].orig = orig[0].content;
										orig[0].origproc = orig[0].prefix + orig[0].proceed + orig[0].suffix;
										orig[0].content = "";
										regions[regions.length] = orig[0];
									}
									else {
										// some weird was happend with post: lot of content changed.
										// better to reload editor page...
										for (i = 0; i < o; i++) {
											deleted[deleted.length] = orig[i].id;
											orig[i].orig = orig[i].content;
											orig[i].proc = orig[i].prefix + orig[i].proceed + orig[i].suffix;
											orig[i].content = "";
											regions[regions.length] = orig[i];
										}
										for (i = 0; i < c; i++) {
											prepend[prepend.length] = user[i].id;
											user[i].orig = "";
											user[i].origproc = "";
											regions[regions.length] = user[i];
										}
									}
									o--;
									c--;
								}
							}
						}
						// Discover body changes
						while (o < orig.length && c < user.length) {
							if (orig[o].id !== user[c].id) {
								// ids not match, possible some middle conflict
								// solve it by find next same IDs (if any)
								var no, nc, s = 0;
								for (no = o; no < orig.length && !s; no++) {
									var ni = orig[no].id;
									for (nc = c; nc < user.length && !s; nc++) {
										if (user[nc].id === ni) {
											// found equals match
											// all between match from user is "changed" (appended in middle)
											for (s = c; s < nc; s++) {
												changed[changed.length] = user[s].id;
												user[s].orig = "";
												user[s].origproc = "";
												regions[regions.length] = user[s];
											}
											// all between match from orig is deleted.
											for (s = o; s < no; s++) {
												deleted[deleted.length] = orig[s].id;
												orig[s].orig = orig[s].content;
												orig[s].origproc = orig[s].prefix + orig[s].proceed + orig[s].suffix;
												orig[s].content = "";
												regions[regions.length] = orig[s];
											}
											// found, continue
											s = 1;
										}
									}
								}
								if (s) {
									o = no - 1;
									c = nc - 1;
								} else {
									break;
								}
							}
							if (orig[o].content !== user[c].content) {
								changed[changed.length] = orig[o].id;
							}
							user[c].orig = orig[o].content;
							user[c].origproc = orig[o].prefix + orig[o].proceed + orig[o].suffix;
							regions[regions.length] = user[c];
							// Advance
							o++;
							c++;
						}
						// end of equals body. anything left in user are appended,
						// anything left in orig are deleted (conflict?)
						for (; c < user.length; c++) {
							append[append.length] = user[c].id;
							user[c].orig = "";
							user[c].origproc = "";
							regions[regions.length] = user[c];
						}
						for (; o < orig.length; o++) {
							deleted[deleted.length] = orig[o].id;
							orig[o].orig = orig[o].content;
							orig[o].origproc = orig[o].prefix + orig[o].proceed + orig[o].suffix;
							orig[o].content = "";
							regions[regions.length] = orig[o];
						}
						return {"prepend": prepend, "append": append, "changed": changed, "deleted": deleted, "regions": regions};
					};
					var startError = function (error) {
						var ps = new Livepress.Admin.PostStatus();
                        ps.error(error);
						$j('#post-body-content .livepress-newform,.secondary-editor-tools').hide();
						$liveCanvas.hide();
						$postdivrich.show();
						$spinner.remove();
						Collaboration.Edit.destroy();
						var $ls = jQuery('#live-switcher');
						if ($ls.hasClass('on')) {
							$ls.trigger('click');
						}
						return;
					};
					var initAfterStartEE = function (regions, textError, errorThrown) {
						if (this !== initXhr && errorThrown !== initXhr) {
							// Got complete on aborted ajax call
							return;
						}
						if ( 'undefined' === typeof regions ) {
							return;
						}
						var blogContent = "", i = 0;
						if (regions === undefined && 'parsererror' !== textError && 'error' !== textError ) {
							return startError("Error: " + textError + " : " + errorThrown);
						}
						if (regions.edit_uuid) {
							LivepressConfig.post_edit_msg_id = regions.edit_uuid;
						}
						// Start the livepress collaborative edit
						if ("editStartup" in regions) {
							Collaboration.Edit.initialize(regions.editStartup);
						} else {
							Collaboration.Edit.initialize();
						}
						var ee = analyseStartEE(regions);
						// set this content in the livecanvas area but remove
						// livepress-update-stub tags
						// set events
						var $inside_first = $liveCanvas.find('div.inside:first').html("");
						var pr = 0, ap = 0, ch = 0, de = 0, sty = "";
						var microContent = "";
						for (i = 0; i < ee.regions.length; i++) {
							var reg = ee.regions[i];
							if (pr < ee.prepend.length && reg.id === ee.prepend[pr]) {
								// prepended block
								if (!pr && !ap) { // first prepended block come to new edit area
									microContent = reg.content;
									pr++;
									continue; // do not apply it
								}
								sty = "new"; // display block as new (not saved yet)
							} else if (ap < ee.append.length && reg.id === ee.append[ap]) {
								// appended block
								if (!pr && !ap) { // first appended block come to new edit area
									microContent = reg.content;
									ap++;
									continue; // do not apply it
								}
								sty = "new"; // display block as new (not saved yet)
							} else if (ch < ee.changed.length && reg.id === ee.changed[ch]) {
								// changed block
								sty = "edit"; // display block as edited
								ch++;
							} else if (de < ee.deleted.length && reg.id === ee.deleted[de]) {
								// deleted block
								sty = "del"; // display block as deleted
								de++;
							} else {
								// published non touched block
								sty = "";
							}
							var proc = reg.prefix + reg.proceed + reg.suffix;
							var origproc = reg.origproc===undefined ? proc : reg.origproc;
							var $block = $j(proc);
							var orig = reg.orig===undefined ? reg.content : reg.orig;
							$block.data("nonExpandedContent", reg.content);
							$block.data("originalContent", orig);
							$block.data("originalHtmlContent", origproc);
							$block.data("originalId", reg.id);
							if (sty === "new") {
								// FIXME: add some kind of support for that
								return startError( lp_strings.double_added );
							}
							$block.attr("editStyle", sty);
							try { window.twttr.widgets.load($block[0]); }
							catch(exc) { }
							$inside_first.append($block);
						}
						$inside_first
							.find('div.livepress-update')
							.hover(onUpdateHoverIn, onUpdateHoverOut);

						$spinner.remove();
						if (!inittedOnce) {
							$liveCanvas.insertAfter('#titlediv');

							var canvas = document.getElementById( 'livepress-canvas' ),
                                $canvas = $j( canvas );

							// live listeners, bound to canvas (since childs are recreated/added/removed dynamically)
							// @todo ensure only one editor open at opnce, disable click when one editor active
							$canvas.on( 'click',  'div.livepress-update', function (ev) {

								var $target = $j(ev.target);
								if ($target.is("a,input,button,textarea") || $target.parents("a,input,button,textarea").length > 0) {
									return true; // do not handle click event from children links (needed to fix live)
								}
								if (!isEditing()) {
									var style = this.getAttribute( 'editStyle' );

									var Sel = new Selection( 'del' === style ? 'deleting' : 'editing', this);
									Sel.$form.insertAfter( this );
									var tab_markup = '<div class="livepress-inline-editor-tabs wp-editor-tabs"><a id="content-livepress-html" class="hide-if-no-js wp-switch-editor switch-livepress-html"><span class="icon-livepress-logo"></span> Real-Time Text</a><a id="content-livepress" class="hide-if-no-js wp-switch-editor switch-livepress active"><span class="icon-livepress-logo"></span> Real-Time</a></div>';

									var editor = Sel.enableTiny( style );
									editor.show();
									jQuery( tab_markup ).prependTo( Sel.$form );
									// Clone the media button
									jQuery( '.wp-media-buttons' )
										.first()
										.clone( true )
										.prependTo( Sel.$form )
										.css( 'margin-left', '10px' )
										.find( 'a' )
										.attr( 'data-editor', editor.id );

									addEditorTabControlListeners( Sel.$form.parent().find( '.livepress-inline-editor-tabs' ), editor.id, '', editor );
									jQuery( this ).remove();
								}
							});
							$canvas.on( 'click', 'div.livepress-update a', function (ev) {
								ev.stopPropagation();
							} );

							//showMicroPostForm();
						} else {
							$j('#post-body-content .livepress-newform').show();
							$liveCanvas.show();
						}

						var $sec = $j('.secondary-editor-tools');
						if (!$sec.length) {
							//copy media buttons from orig tinymce
							jQuery('#wp-content-editor-tools')
								.clone(true)
								.insertAfter('#titlediv')
								.addClass('secondary-editor-tools')
								// This next line undoes WP 4.0 editor-expand (sticky toolbar/media button)
								.css( { position: "relative", top: "auto", width: "auto" } )
								.find('#content-tmce, #content-html')
								.each(function () {
									jQuery(this)
										.removeAttr('id')
										.removeAttr('onclick')
										.on('click', function () {

										});
								});
						} else {
							$sec.show();
						}

						showMicroPostForm(microContent);
						Collaboration.Edit.update_live_posts_number();
						window.setTimeout(update_embeds, 1000);

						// first micro post
						var $currentPosts = $liveCanvas.find('div.livepress-update:first');

						/**
						 * Add the Live Post Header feature
						 */
						if ( jQuery( '#livepress_status_meta_box' ).hasClass( 'pinned-header' ) ) {

							// Add the 'Live Post Header' box
							$currentPosts
								.wrap( '<div class="pinned-first-livepress-update"></div>' )
								.before( '<div class="pinned-first-livepress-update-header">' +
									'<div class="dashicons dashicons-arrow-down"></div>' +
									lp_strings.live_post_header +
									'</div>');
							// Move the box to above the Update Count
							$liveCanvas.find( '.pinned-first-livepress-update' )
								.prependTo( $liveCanvas )
								.find( '.livepress-update:first' )
								.find( '.livepress-delete')
								.hide();

							$liveCanvas.find( '.pinned-first-livepress-update div.livepress-update' ).hide();

							/**
							 * Add handlers for expanding/contracting the pinned post header
							 */
							// Expand/Show the Live Post Header when triangle is clicked
							$liveCanvas.on( 'click', '.pinned-first-livepress-update-header', function( el ) {
								var $firstUpdate = $liveCanvas.find( '.pinned-first-livepress-update div.livepress-update' ),
									$icon        = $liveCanvas.find( '.dashicons' );

								if ( $firstUpdate.is(":visible") ) {
									$firstUpdate.slideUp( 'fast' );
									$icon.removeClass( 'dashicons-arrow-up' ).addClass( 'dashicons-arrow-down' );
								} else {
									$firstUpdate.slideDown( 'fast' );
									$icon.removeClass( 'dashicons-arrow-down' ).addClass( 'dashicons-arrow-up' );
								}
							});
						}

						var $pub = jQuery('#publish');
						$pub.on( 'click', function () {
							LiveCanvas.hide();
							return true;
						} );

						inittedOnce = 1;
						initXhr = null;
					};
					initXhr = Helper.doStartEE(originalContent, initAfterStartEE);
				};

				/*
				 * function: get
				 * _(public)_
				 */
				var get = function () {
					return $liveCanvas.find('div.inside:first');
				};

				return {
					init:              init,
					get:               get,
					hide:              hide,
					mergeData:         mergeData,
					onUpdateHoverIn:   onUpdateHoverIn,
					onUpdateHoverOut:  onUpdateHoverOut,
					showMicroPostForm: showMicroPostForm,
					hideMicroPostForm: hideMicroPostForm,
					addPost:           addPost
				};
			}());

			// creates live blogging activation tools
			Livepress.Ready = function () {
				OORTLE.Livepress.Dashboard = new Dashboard.Controller();
				OORTLE.Livepress.LivepressHUD.init();
				// Hide the screen option for LivePress live status
				jQuery('.metabox-prefs label[for=livepress_status_meta_box-hide]').hide();
			};

			return {
				LivepressHUD:        new LivepressHUD(),
				startLiveCanvas:     LiveCanvas.init,
				getLiveCanvas:       LiveCanvas.get,
				mergeLiveCanvasData: LiveCanvas.mergeData
			};
		}());
	}

	/**
	 * Optimized code for twitter intents.
	 *
	 * @see <a href="http://dev.twitter.com/pages/intents">External documentation</a>
	 */
	(function () {
		if (window.__twitterIntentHandler) {
			return;
		}
		var intentRegex = /twitter\.com(\:\d{2,4})?\/intent\/(\w+)/,
			windowOptions = 'scrollbars=yes,resizable=yes,toolbar=no,location=yes',
			width = 550,
			height = 420,
			winHeight = screen.height,
			winWidth = screen.width;

		function handleIntent (e) {
			e = e || window.event;
			var target = e.target || e.srcElement,
				m, left, top;

			while (target && target.nodeName.toLowerCase() !== 'a') {
				target = target.parentNode;
			}

			console.log('handle intent');

			if (target && target.nodeName.toLowerCase() === 'a' && target.href) {
				m = target.href.match(intentRegex);
				if (m) {
					left = Math.round((winWidth / 2) - (width / 2));
					top = 0;

					if (winHeight > height) {
						top = Math.round((winHeight / 2) - (height / 2));
					}

					window.open(target.href, 'intent', windowOptions + ',width=' + width +
						',height=' + height + ',left=' + left + ',top=' + top);
					e.returnValue = false;
					if (e.preventDefault) {
						e.preventDefault();
					}
				}
			}
		}

		if (document.addEventListener) {
			document.addEventListener('click', handleIntent, false);
		} else if (document.attachEvent) {
			document.attachEvent('onclick', handleIntent);
		}
		window.__twitterIntentHandler = true;
	}());

	/**
	 * Once all the other code is loaded, we check to be sure the user is on the post edit screen and load the new Live
	 * Blogging Tools palette if they are.
	 */
	if (LivepressConfig.current_screen !== undefined && LivepressConfig.current_screen.base === 'post' ) {
		var live_blogging_tools = new Livepress.Admin.Tools();
		live_blogging_tools.render();
	}
});
