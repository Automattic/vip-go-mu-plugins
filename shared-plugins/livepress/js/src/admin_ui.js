/*global lp_strings, Livepress, get_twitter_avatar, LivepressConfig */
jQuery(function () {
	var $api_key = jQuery("#api_key"),
		api_key_button_id = "#api_key_submit",
		filled = false,
		sending_api_key = false;

	if ($api_key.length === 0) {
		return;
	}

	if ( undefined !== window.livepress_twitter_authorized ) {
		var lp_btn = jQuery( '#lp-post-to-twitter-change' ),
			lp_link = jQuery( '#lp-post-to-twitter-change_link' );
		if ( window.livepress_twitter_authorized ) {
			lp_btn.hide();
			lp_link.show();
		} else {
			lp_btn.show();
			lp_link.hide();
		}
	}

	// If there is already an api key, disables the box and displays the remove button
	$api_key.each(function () {
		if (this.value) {
			jQuery(this).attr("disabled", true);
			jQuery(api_key_button_id).attr("value", lp_strings.remove );
			filled = true;
		}
	});

	jQuery(api_key_button_id ).on( 'click', function (e) {
		e.preventDefault();
		if (document.getElementById("api_key").value === "") {
			return false;
		}
		if (sending_api_key === true) {
			return false;
		}
		if (filled) {
			$api_key.attr("disabled", false);
			$api_key.attr("value", "");
			jQuery(api_key_button_id).attr("value", lp_strings.check );
			filled = false;
			jQuery( 'input#submit' ).click();
			return false;
		}

		sending_api_key = true;
		jQuery(api_key_button_id).attr("value", "Sending");

		jQuery.ajax({
			url:      "./admin-ajax.php", //"<?php echo get_option("siteurl") ?>/wp-admin/admin-ajax.php",
			data:     ({
				api_key:     document.getElementById("api_key").value,
				action:      'lp_api_key_validate',
				_ajax_nonce: LivepressConfig.ajax_api_validate_nonce
			}),
			success:  function (msg) {
				if (typeof msg === 'object') {
					var $api_key_status = jQuery('#api_key_status'),
						$opt,
						key;

					if (!msg.error_api_key) {
						$api_key_status
							.html( lp_strings.authenticated )
							.attr("class", "valid_api_key");

						jQuery('.no_key_hidden').show('slow');

						filled = true;
						$api_key.attr("disabled", true);
						jQuery(api_key_button_id).attr("value", "Remove");
					} else {
						$api_key_status
							.html( lp_strings.key_not_valid )
							.attr("class", "invalid_api_key");
						filled = true;
						$api_key.attr("disabled", true);
						jQuery(api_key_button_id).attr("value", "Remove");
					}
					// Update other options in form
					for (key in msg) {
						if (msg.hasOwnProperty(key) && ($opt = jQuery("input[name=" + key + "],textarea[name=" + key + "]")).length) {
							if ($opt.length === 1) {
								if ($opt.is(":checkbox")) {
									$opt.attr("checked", !!(msg[key]));
									$opt.change();
								} else {
									$opt.val(msg[key]);
								}
							} else {
								$opt.val([ msg[key] ]);
							}
						}
					}
				} else {
					jQuery("#api_key_status")
						.html( lp_strings.unexpected )
						.attr("class", "");
					jQuery(api_key_button_id).attr("value", lp_strings.check );
				}
			},
			error:    function (XMLHttpRequest, textStatus, errorThrown) {
				jQuery("#api_key_status")
					.html( lp_strings.connection_problems )
					.attr("class", "");
			},
			complete: function (XMLHttpRequest, textStatus) {
				sending_api_key = false;
			}
		});
		return false;
	});
});

// Im bots check button and im users test message button only works with JS enabled
jQuery(function () {
	var netinfo = {},
		im_hide_edit = function () {
			var $this = jQuery(this),
				net = $this.attr('id').match(/_([a-z]+)/)[1],
				$username = jQuery("#im_bot_username_" + net),
				$password = jQuery("#im_bot_password_" + net);
			if (netinfo[net] === undefined) {
				netinfo[net] = {
					username: $username.val(),
					password: $password.val()
				};
			} else {
				if ($password.next().is('.password-placeholder:visible')) {
					// Placeholder is there, so simulate a focus to put real input
					$password.next().focus();
				}
				$username.val(netinfo[net].username);
				$password.val(netinfo[net].password);
			}

			$username.attr("disabled", true);
			$password.hide();
			jQuery("#im_bot_username_" + net + "_message").hide();
			jQuery("#check_message_" + net).show();
			$this.hide();
			jQuery("#edit_" + net).show();
			jQuery("#check_" + net).show();
		},
		im_show_edit = function () {
			var $this = jQuery(this),
				net = $this.attr('id').match(/_([a-z]+)/)[1],
				$username = jQuery("#im_bot_username_" + net),
				$password = jQuery("#im_bot_password_" + net);

			$username.attr('disabled', false);
			$password.show();
			jQuery("#im_bot_username_" + net + "_message").show();
			jQuery("#check_message_" + net).hide();
			$this.hide();
			jQuery("#cancel_" + net).show();
			jQuery("#check_" + net).hide();
		};

	jQuery(".im_check").show();
	jQuery(".im_edit").click(im_show_edit);
	jQuery(".im_cancel").click(im_hide_edit).each(im_hide_edit);
	jQuery(".im-users-test-message-button").show();
});

// Facebox
jQuery(function () {
	jQuery(".facebox").hide();
	jQuery.facebox.settings.closeImage = LivepressConfig.lp_plugin_url + '/img/facebox/closelabel.gif';
	jQuery.facebox.settings.loadingImage = LivepressConfig.lp_plugin_url + '/img/facebox/loading.gif';

	jQuery('a[rel*=facebox]').facebox({
		loading_image: LivepressConfig.lp_plugin_url + '/img/facebox/loading.gif',
		close_image:   LivepressConfig.lp_plugin_url + '/img/facebox/closelabel.gif'
	});
});

// UI - Show twitter preview avatar button
jQuery(function () {
	if (typeof get_twitter_avatar === 'function') {
		jQuery("#twitter_avatar_preview_button").click(get_twitter_avatar).show();
	}
});

// UI - Aesthesic Effects
jQuery(function () {
	jQuery('.messages').click(function () {
		jQuery(this).fadeOut('fast');
	});

	jQuery(".row:odd").addClass('odd');
	jQuery(".row:even").addClass('even');
});


jQuery(function () {
	jQuery(document).placeholder();

	var tabSwitcher = function (radio, tab) {
		var $radio = jQuery(radio);

		if ($radio.is(":checked")) {
			tab.show();
		} else {
			tab.find("input").each(function () {
				var $input = jQuery(this);
				if ($input.is(":checkbox,:radio")) {
					$input.attr("checked", false);
				}
			});
			tab.hide();
		}
	};

	jQuery("#disable_comments").bind('change', function (e) {
		var show = e.target.checked ? 'none' : 'block';
		jQuery("#comment_live_updates_default").parent().css('display', show);
	});

	jQuery("#avatar_wp").bind('change', function () {
		jQuery("#avatar_twitter").change();
	});


	jQuery("#update_author").bind('change', function () {
		var $tab = jQuery('.tabulation.author_name');
		tabSwitcher(this, $tab);
	});

	jQuery('#twitter_avatar_username').keydown(function (e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			e.stopPropagation();
			if (typeof get_twitter_avatar === 'function') {
				get_twitter_avatar(this);
			}
		}
	});

	jQuery("#avatar_twitter").bind('change', function () {
		var $tab = jQuery('.tabulation.twitter_avatar_choice');
		tabSwitcher(this, $tab);
	});

	jQuery("#include_avatar").bind('change', function () {
		var $tab = jQuery('.tabulation.avatar');
		tabSwitcher(this, $tab);
	});

	jQuery("#timestamp").bind('change', function () {
		var $tab = jQuery('.tabulation.timestamp');
		tabSwitcher(this, $tab);
	});

	jQuery("#comment_notification").bind('change', function () {
		var $tab = jQuery('.tabulation.comment_notification_sound');
		tabSwitcher(this, $tab);
	});

	jQuery("#remote_post").bind('change', function () {
		var $tab = jQuery('.tabulation.remote_post');
		tabSwitcher(this, $tab);
	});

	jQuery('input.im_send_comments').each(function () {
		var $this = jQuery(this),
			net = $this.attr('id').split("_").pop(),
			enabler = function () {
				if (jQuery(this).val() !== "" && jQuery(this).val() !== jQuery(this).attr('placeholder')) {
					$this.attr("disabled", false);
				} else {
					$this.attr("disabled", true);
				}
			};
		jQuery('#im_enabled_user_' + net).change(enabler).trigger('change');
	});
});
