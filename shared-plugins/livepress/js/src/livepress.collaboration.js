/*jslint nomen:true, plusplus:true */
/*global LivepressConfig, lp_strings, Livepress, Dashboard, console, OORTLE, LivepressConfig */
var Collaboration = Livepress.ensureExists(Collaboration);
Collaboration.chat_topic_id = function () {
	var topic = Collaboration.post_topic() + "_chat";
	console.log("Collaboration.chat_topic evaluation -- " + topic);
	return topic;
};

Collaboration.Chat = {
	enabled:   false,
	nextShade: false,
	html:      [
		'<div>',
		'<div id="chat-messages">',
		'<div id="inner-chat-messages"></div>',
		'</div>',
		'<div id="chat-form-message"><hr />',
		'<form>',
		'<input id="chat_message" type="text" name="chat_message" style="width:240px;" />',
		'<input id="chat-submit" type="submit" class="button" value="' + lp_strings.submit + '" />',
		'</form></div>',
		'</div>'
	].join(''),

	message_html: function (msg) {
		this.nextShade = !this.nextShade;
		var shade = this.nextShade ? ' shaded' : '';
		return '<div class="message' + shade + '">' +
			msg +
			'</div>';
	},

	/**
	 *  Adds a message to the #inner-chat-messages
	 *
	 *  @param  {Array}    data    First element should be the author name and second his message
	 */
	add_message: function (data) {
		var $chat = jQuery('#chat-messages'),
			$myDiv = jQuery('div#inner-chat-messages'),
			msg = '<strong>' + data[0] + '</strong>: ' + data[1];

		//jQuery('div#chat-messages').append(this.message_html(msg));
		$myDiv.append(Collaboration.Chat.message_html(msg));
		$chat.attr({
			scrollTop: $chat.attr("scrollHeight")
		});
	},

	/**
	 *  Sends a chat message to be published, can be used as an event callback or directly
	 *
	 *  @param  {String}  msg Message to be published, if not given, gets from the #chat_message
	 */
	send_message: function (msg) {
		if (typeof msg !== "string") {
			var $mes = jQuery('#chat_message');
			if ($mes.length && $mes.val() !== '') {
				msg = $mes.val();
				$mes.focus().val('');
			}
		}
		if (msg) {
			OORTLE.instance.publish(Collaboration.chat_topic_id(), [Collaboration.Author, msg]);
		}
		return false;
	},

	initialize: function () {
		if (!this.enabled) {
			var resizeMe = function () {
				var $livepress_chat_window = jQuery('#livepress-chat-window'),
					$dialogue = $livepress_chat_window.find('div:first'),
					$chats = jQuery('#chat-messages'),
					$chatinput = jQuery('#chat_message'),
					bw = jQuery('#chat-submit').width();

				$chats.height($livepress_chat_window.height() - jQuery('#chat-form-message').height() + 5);
				$chatinput.width($dialogue.width() - bw - 20);
			};

			console.log(this, this.html);
			jQuery('<div id="livepress-chat-window" title="' + lp_strings.live_press + ' <span>' + lp_strings.live_chat + '</span>">' + this.html + '</div>')
				.dialog({
					/*position: 'right',*/
					width:      330,
					minWidth:   130,
					minHeight:  115,
					close:      function () {
						Collaboration.Chat.destroy();
					},
					resize:     resizeMe,
					dialogopen: resizeMe
				});
		}

		Collaboration.start(this);

		if (!Collaboration.Connected) {
			jQuery('input#chat-submit').disabled = true;
		}
	},

	create: function () {
		var $chat_submit = jQuery('input#chat-submit');
		$chat_submit.disabled = false;
		$chat_submit.click(this.send_message);

		OORTLE.instance.subscribe(Collaboration.chat_topic_id(), this.add_message);

		Collaboration.Chat.send_message("Im in!");
	},

	destroy: function () {
		this.enabled = false;

		jQuery("div#livepress-chat-window").remove();
		OORTLE.instance.unsubscribe(Collaboration.chat_topic_id(), this.add_message);
	}
};

Collaboration.Edit = Livepress.ensureExists(Collaboration.Edit);

jQuery.extend(Collaboration.Edit, {
	update_live_posts_number: function () {
		var length = jQuery("#livepress-canvas")
			.find(".livepress-update") // Find the update divs
			.not( ".pinned-first-livepress-update>.livepress-update" ) // Exclude the pinned update, if any
			.filter( function( index ) { return ( 0 === jQuery( this ).find(".livepress-update").length ); } )
			.length;
		OORTLE.Livepress.LivepressHUD.updateLivePosts(length);
	},

	editing_post_callback: function (data) {
		data = JSON.parse(data);

		if ( null !== data ) {
			try { window.twttr.widgets.load( data ); } catch( e ) {}
			OORTLE.Livepress.mergeLiveCanvasData(data);
		}
	}
});

Collaboration.post_topic = function () {
	return "|livepress|" + LivepressConfig.site_url + "|post-" + LivepressConfig.post_id;
};

Collaboration.edit_post_topic = function () {
	return Collaboration.post_topic() + "_edit";
};


jQuery.extend(Collaboration, {
	Connected: false,
	Author:    LivepressConfig.current_user,

	start: function (mode) {
		if (mode.enabled) {
			return;
		}
		mode.enabled = true;

		if (this.Connected) {
			mode.create();
		} else {
			this.initialize();
		}
	}
});

jQuery.extend(Collaboration.Edit, {
	enabled:                false,
	editor_dom_manipulator: null,
	runOnce:                false,

	initialize: function (args) {
		this.startArgs = args;
		Collaboration.start(this);

		this.refresh(this);
	},

	refresh: function (instance) {
		var args = null;
		if (!instance.runOnce) {
			instance.runOnce = true;

			if (instance.startArgs !== undefined) {
				args = instance.startArgs;
			}
		}

		instance.get_live_edition_data(args);

		/*setTimeout(function() {
			instance.refresh(instance);
		}, 15000);*/
	},

	create: function () {
		// Set the oortle manipulator to add changes in tinyMCE chunks of live updates.
		this.editor_dom_manipulator = new Livepress.DOMManipulator(jQuery("#content_ifr").contents().find("body"));
		if (this.startArgs !== undefined && this.startArgs) {
			this.get_live_edition_data(this.startArgs);
		} else {
			this.get_live_edition_data();
		}
		this.update_live_posts_number();

		// Collaborative editing...
		var opt = LivepressConfig.post_edit_msg_id ? {last_id: LivepressConfig.post_edit_msg_id} : {fetch_all: true};
		OORTLE.instance.subscribe(Collaboration.edit_post_topic(), this.editing_post_callback, opt);
		// Readers online
		OORTLE.instance.subscribe("|subcount" + Collaboration.post_topic(), this.readers_callback);
	},

	destroy: function () {
		this.enabled = false;
		OORTLE.instance.unsubscribe(Collaboration.edit_post_topic(), this.editing_post_callback);
		Dashboard.Comments.unsubscribe_comment_channels();
		OORTLE.instance.unsubscribe(Collaboration.post_topic(), this.readers_callback);
		OORTLE.instance.unsubscribe("|subcount" + Collaboration.post_topic(), this.readers_callback);
		Dashboard.Twitter.unsubscribeTwitterChannels();
	},

	get_live_edition_data: function (args) {
		var handleGuestBloggers, handle_terms, success, error, params;

		handleGuestBloggers = function (guestBloggers) {
			if (guestBloggers === undefined) {
				return;
			}
			jQuery.each(guestBloggers, function (i, account) {
				Dashboard.Twitter.addGuestBlogger(account, true);
			});
		};

		handle_terms = function (terms) {
			var getContainer, i;

			jQuery.each(terms, function (i, term) {
				Dashboard.Twitter.addTerm(term, true);
			});

			getContainer = function () {
				return jQuery('#lp-twitter-results');
			};

			for (i = 0; i < Dashboard.Twitter.terms.length; i++) {
				Dashboard.Twitter.renderStaticSearchResults(Dashboard.Twitter.terms[i], getContainer, 5);
			}
		};

		success = function (env) {
			if (env) {
				Dashboard.Comments.clear_container_and_count(env.comments);
				handleGuestBloggers(env.guest_bloggers);
				handle_terms(env.terms);
			}
		};

		error = function () {
			// Fetch just comment count, of list of comments can't be retrived
			Dashboard.Comments.get_comments_number_from_wp();
			// subscribe anyway if timeout or other error
			Dashboard.Comments.subscribe_comment_channels();
		};

		if (!args) {
			params = {
				action:           'lp_collaboration_get_live_edition_data',
				_ajax_nonce:      LivepressConfig.ajax_get_live_edition_data,
				post_id:          LivepressConfig.post_id
			};
			jQuery.ajax({
				type:     "GET",
				dataType: "json",
				url:      LivepressConfig.site_url + '/wp-admin/admin-ajax.php',
				data:     params,
				success:  success,
				error:    error
			});
		} else {
			success(args);
		}
	},

	readers_callback: function (data) {
		console.log("Collaboration.readers_callback data -- " + data);
		OORTLE.Livepress.LivepressHUD.updateReaders(data.count);
	}
});

Collaboration.Modes = [Collaboration.Chat, Collaboration.Edit];

Collaboration.connected = function () {
	var i;

	if (!Collaboration.Connected) {
		Collaboration.Connected = true;

		for (i = 0; i < Collaboration.Modes.length; i++) {
			if (Collaboration.Modes[i].enabled) {
				Collaboration.Modes[i].create();
			}
		}

		Dashboard.Helpers.clearErrors('*');
		Dashboard.Helpers.hideErrors();
		Dashboard.Helpers.setSwitcherState('connected');

		console.log("Collaboration.connected");
	}
};

Collaboration.disconnected = function () {
	var i;

	if (Collaboration.Connected) {
		Collaboration.Connected = false;

		for (i = 0; i < Collaboration.Modes.length; i++) {
			Collaboration.Modes[i].destroy();
		}

		Dashboard.Helpers.handleErrors({ disconnected: Collaboration.errorMessages.disconnected });
		Dashboard.Helpers.setSwitcherState('disconnected');

		console.log("Collaboration.disconnected");
	}
};

Collaboration.reconnect = function () {
	if (!this.Connected) {
		Dashboard.Helpers.clearErrors('.connection');
		OORTLE.instance.reconnect();
	}
};

Collaboration.errorMessages = {
	max_connection_attempts_reached: '<strong>' + lp_strings.warning + '</strong> ' + lp_strings.connection_just_lost + lp_strings.connection_lost, // It's really Math.rand
	server_list_empty:               '<strong>' + lp_strings.warning + '</strong> ' + lp_strings.connection_just_lost + lp_strings.connect_again,
	disconnected:                    '<strong>' + lp_strings.warning + '</strong> ' + lp_strings.connection_just_lost,
	message_order_broken:            '<strong>' + lp_strings.warning + '</strong> ' + lp_strings.sync_lost,
	cache_empty:                     '<strong>' + lp_strings.warning + '</strong> ' + lp_strings.collabst_sync_lost,
	cache_miss:                      '<strong>' + lp_strings.warning + '</strong> ' + lp_strings.collab_sync_lost
};

Collaboration.onError = function (key, arg) {
	var errors = {};
	if (Collaboration.errorMessages.hasOwnProperty(key)) {
		errors[key] = Collaboration.errorMessages[key].replace("{1}", arg);
	} else {
		errors[key] = arg + "[" + key + "]";
	}
	Dashboard.Helpers.handleErrors(errors);
	Dashboard.Helpers.setSwitcherState('disconnected');
	console.log("Collaboration.onError", arguments);
};

Collaboration.initialize = function () {
	if(!OORTLE.instance || !OORTLE.server_list_location) {
		window.setTimeout(Collaboration.initialize, 100); // OORTLE not loaded? try a bit
	} else
	if (!this.Connected) {
		OORTLE.instance.attachEvent('connected', Collaboration.connected);
		OORTLE.instance.attachEvent('state_changed', function (oldState, newState) {
			if (newState === 'connected') {
				Collaboration.connected();
			}
		});
		OORTLE.instance.attachEvent('disconnected', Collaboration.disconnected);
		OORTLE.instance.attachEvent('error', Collaboration.onError);

		OORTLE.instance.connect();
	}
};
