/*global LivepressConfig, lp_client_strings, Livepress, console */

jQuery(function () {
	Livepress.Comment = (function () {
		var sending = false;

		var set_comment_status = function (status) {
			var $status = jQuery('#oortle-comment-status');
			if ($status.length === 0) {
				jQuery('#submit').after("<span id='oortle-comment-status'></span>");
				$status = jQuery('#oortle-comment-status');
			}
			$status.text(status);
		};

		var unblock_comment_textarea = function (eraseText) {
			var comment_textarea = jQuery("#comment");
			comment_textarea.attr("disabled", false);

			if (eraseText) {
				comment_textarea.val('');
				jQuery("#cancel-comment-reply-link").click();
			}
		};

		var send = function () {
			try {
				if (sending) {
					return false;
				}
				sending = true;

				var $btn = jQuery('#submit');
				var btn_text = $btn.attr("value");
				$btn.attr("value", lp_client_strings.sending + '...' );
				$btn.attr("disabled", true);
				jQuery("textarea#comment").attr("disabled", true);
				set_comment_status("");

				var params = {};
				var form = document.getElementById('commentform') || document.getElementById('comment-form');
				params.comment_post_ID = form.comment_post_ID.value;
				if (typeof(form.comment_parent) !== 'undefined') {
					params.comment_parent = form.comment_parent.value;
				}
				params.comment = form.comment.value;
				form.comment.value = '';
				// FIXME: this won't work when accepting comments without email and name fields
				// sent author is same as comment then. Ex. author:	test!@ comment:	test!@
				params.author = form.elements[0].value;
				params.email = form.elements[1].value;
				params.url = form.elements[2].value;
				params._wp_unfiltered_html_comment = (form._wp_unfiltered_html_comment !== undefined) ? form._wp_unfiltered_html_comment.value : '';
				params.redirect_to = '';
				params.livepress_update = 'true';
				params.action = 'lp_post_comment';
				params._ajax_nonce = LivepressConfig.ajax_comment_nonce;

				Livepress.sounds.play("commented");

				jQuery.ajax({
					url:     LivepressConfig.site_url + '/wp-admin/admin-ajax.php',
					type:    'post',
					dataType:'json',
					data:    params,
					error:   function (request, textStatus, errorThrown) {

						console.log("comment response: " + request.status + ' :: ' + request.statusText);
						console.log("comment ajax failed: %s", textStatus);
						set_comment_status( lp_client_strings.comment_status + ": " + request.responseText );
						unblock_comment_textarea(false);
					},
					success: function (data, textStatus) {
						// TODO: Improve display message that send successed.
						set_comment_status( lp_client_strings.comment_status + ": " + data.msg);
						unblock_comment_textarea(data.code === "200");
					},
					complete:function (request, textStatus) {
						$btn = jQuery('#submit');
						sending = false;
						$btn.attr("value", btn_text);
						$btn.attr("disabled", false);
					}
				});
			} catch (error) {
				console.log("EXCEPTION: %s", error);
				set_comment_status( lp_client_strings.sending_error );
			}

			return false;
		};

		var attach = function () {
			jQuery('#submit').click(send);
		};

		// WP only: we must hide new comment form before making any modifications to dom tree
		// otherwise wp javascripts which handle cancel link won't work anymore
		// we check if new comment is of same author and if user didn't modify it's contents meanwhile
		var before_live_comment = function (comment_data) {
			var comment_textarea = jQuery("#comment");
			if (comment_data.ajax_nonce === LivepressConfig.ajax_nonce && comment_textarea.val() === comment_data.content) {
				unblock_comment_textarea(true);
			}
		};

		var should_attach_comment = function (config) {
			var page_number = config.comment_page_number;
			if (config.comment_order === "asc") {
				return(page_number === 0 || page_number === config.comment_pages_count);
			} else {
				return(page_number <= 1);
			}
		};

		var get_comment_container = function (comment_id) {
			return jQuery("#comment-" + comment_id).parent().attr("id");
		};

		var on_comment_update = function (data, manipulator) {
			var manipulator_options = {
				custom_scroll_class:'#comment-' + data.comment_id
			};
			if (data.comment_parent === '0') {
				manipulator.update(data.diff, manipulator_options);
			} else { // updating threaded comment
				manipulator.update(data.comments_counter_only_diff, manipulator_options);

				var new_comment = jQuery(data.comment_html);
				// we want new comment to be animated as usual by DOMmanipuator.js
				new_comment.addClass('oortle-diff-inserted-block').hide();
				var parent = jQuery("#comment-" + data.comment_parent);
				var children = parent.children(".children");
				if (children.length === 0) {
					children = jQuery("<ul>").addClass("children").appendTo(parent);
				}
				children.append(new_comment);
				manipulator.display(true);
			}

			return true;
		};

		if (!LivepressConfig.disable_comments) {
			attach();
		}

		return {
			send:                 send,
			attach:               attach,
			before_live_comment:  before_live_comment,
			should_attach_comment:should_attach_comment,
			get_comment_container:get_comment_container,
			on_comment_update:    on_comment_update
		};
	}());
});
