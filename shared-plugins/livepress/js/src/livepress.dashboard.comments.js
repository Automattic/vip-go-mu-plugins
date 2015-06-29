/*jslint vars:true */
/*global lp_strings, Dashboard, console, Collaboration, OORTLE, Livepress, tinyMCE, LivepressConfig */
if (Dashboard.Comments === undefined) {
	Dashboard.Comments = (function () {
		var comments_on_hold = [];
		var hold_comments = false;
		var prev_comment_was_even = true;
		var disabled = false;

		var new_comment_post_topic = function () {
			return Collaboration.post_topic() + "_new_comment";
		};

		var approved_comment_post_topic = function () {
			return Collaboration.post_topic() + "_comment";
		};


		var liveCounter = Dashboard.Helpers.createLiveCounter('#tab-link-live-comments a');

		var new_comment_callback = function (data) {
			if (hold_comments) {
				comments_on_hold.push(data);
			} else {
				var commentDiv = jQuery("#lp-comment-" + data.comment_id);
				if (commentDiv.length <= 0 && data.comment_id !== undefined) {
					commentDiv = Dashboard.Comments.Builder.createCommentDiv(data, prev_comment_was_even);
					prev_comment_was_even = !prev_comment_was_even;
					var contentDiv = commentDiv.find(".comment");
					var rowActions = Dashboard.Comments.Builder.prepareCommentActions(data.comment_id, data);
					contentDiv.append(rowActions);
					OORTLE.Livepress.LivepressHUD.sumToComments(1);
					if ( ! jQuery( '#tab-link-live-comments' ).hasClass( 'active' ) ) {
						if ( ! liveCounter.enabled ) {
							liveCounter.enable();
						}
						liveCounter.increment();
					}
				} else {
					commentDiv.removeClass("approved").removeClass("unapproved");
				}

				commentDiv.addClass(data.status);
			}

			var placeholder = document.getElementById( 'lp-new-comments-notice' );
			if ( null !== placeholder ) {
				jQuery( placeholder ).remove();
			}
		};

		var approved_comment_callback = function (data) {
			console.log("Collaboration.approved_comment_callback");
			data.status = 'approved';
			new_comment_callback(data);
		};

		var bind_pause_on_hover = function () {
			jQuery('#live-comments-pane').hover(function (e) {
				hold_comments = true;
				liveCounter.reset();
				liveCounter.enable();
			}, function (e) {
				hold_comments = false;
				var data;
				while ((data = comments_on_hold.shift())) {
					new_comment_callback(data);
				}
				liveCounter.disable();
			});
		};

		return {
			conditionallyEnable: function() {
				if ( 0 >= jQuery('#lp-comments-results').has('.comment-item').length ) {
					this.liveCounter.disable();
				} else {
					this.liveCounter.reset();
				}
			},

			unsubscribe_comment_channels: function () {
				if (disabled) {
					return;
				}
				OORTLE.instance.unsubscribe(new_comment_post_topic(), new_comment_callback);
				OORTLE.instance.unsubscribe(approved_comment_post_topic(), approved_comment_callback);
			},

			subscribe_comment_channels: function (msgId) {
				if (disabled) {
					return;
				}
				// Live Comments - unnapproved
				var opt = msgId ? {
					last_id: msgId
				} : {
					fetch_all: true
				};

				OORTLE.instance.subscribe(new_comment_post_topic(), new_comment_callback, opt);
				// Live Comments - approved
				opt = msgId ? {
					last_id: msgId
				} : {
					fetch_all: true
				};
				OORTLE.instance.subscribe(approved_comment_post_topic(), approved_comment_callback, opt);
				liveCounter.reset();
				liveCounter.disable();
			},

			clear_container_and_count: function (env) {
				if (disabled) {
					return;
				}
				// clear comments container and count of comments
				OORTLE.Livepress.LivepressHUD.updateComments(0);
				if (env.comments.length) {
					jQuery("#lp-comments-results").html('');
					jQuery.each(env.comments, function (i, comment) {
						new_comment_callback(comment);
					});
				}
				this.subscribe_comment_channels(env.comment_msg_id);
			},

			get_comments_number_from_wp: function () {
				if (disabled) {
					return;
				}
				var params = {};
				params.action = 'lp_collaboration_comments_number';
				params._ajax_nonce = LivepressConfig.ajax_lp_collaboration_comments;
				params.post_id = LivepressConfig.post_id;
				jQuery.ajax({
					url:      LivepressConfig.site_url + '/wp-admin/admin-ajax.php',
					type:     'post',
					dataType: 'json',
					data:     params,

					success: function (data, textStatus) {
						console.log("Collaboration.comments_number -- " + data);
						OORTLE.Livepress.LivepressHUD.updateComments(data);
					}
				});
			},

			liveCounter: liveCounter,

			disable: function () {
				disabled = true;
			},

			init: function () {
				if (disabled) {
					return;
				}
				bind_pause_on_hover();
			}
		};
	}());
}

/**
 * Contains functions which build new comment div to be later displayed
 * in real-time editor dashboard under comments tab
 */
if (Dashboard.Comments.Builder === undefined) {
	Dashboard.Comments.Builder = (function () {
		var createCommentDiv = function (data, even) {
			if (data.comment_id === undefined) {
				return null;
			}
			var commentDiv = jQuery("<div id='lp-comment-" + data.comment_id + "' class='comment-item " + ( even ? 'even' : 'odd' ) + "'></div>");
			var url = data.comment_url;

			var timestamp = data.comment_gmt.parseGMT();

			var createdAtEl;

			if (data.status === "approved") {
				createdAtEl = jQuery("<a href='" + url + "' target='_blank'>" + timestamp + "</a>");
				createdAtEl.addClass('lp-comment-link');
			} else {
				createdAtEl = jQuery("<span>" + timestamp + "</span>");
			}

			// create comment's author div
			var authorDiv = jQuery("<div class='lp-comment-author author'>");
			authorDiv.append(data.avatar_url);
			if (data.author_url) {
				var authorLink = jQuery("<a></a>").text(data.author).attr("href", data.author_url);
				authorDiv.append(jQuery("<strong></strong>").append(authorLink));
			} else {
				authorDiv.append(jQuery("<strong>" + data.author + "</strong>"));
			}
			authorDiv.append("<br/>").append(createdAtEl);
			commentDiv.append(authorDiv);

			var commentsContainer = jQuery("#lp-comments-results");

			var contentDiv = jQuery("<div class='lp-comment comment'></div>");
			contentDiv.append(jQuery("<p>" + data.content + "</p>"));
			commentDiv.append(contentDiv);
			commentsContainer.prepend(commentDiv);
			return commentDiv;
		};

		var prepareCommentActions = function (commentId, data) {
			var commentLink = function (klass, link, skipPipe) {
				var span = jQuery("<span></span>").addClass(klass);
				if (!skipPipe) {
					span.text(" | ");
				}
				span.append(link);
				return span;
			};

			var href = location.href,
				nonces = jQuery('#blogging-tool-nonces');
			var linkForSpamAndTrash = href.substring(0, href.lastIndexOf('/')) + "/edit-comments.php";
			var defaultAjaxData = {
				_ajax_nonce:      LivepressConfig.ajax_comment_nonce,
				id:               commentId
			};

			var linkTag = function (title, href, text) {
				return jQuery("<a></a>").attr("href", href).text(text).attr('title', title);
			};

			var postAndCommentIds = "&p=" + LivepressConfig.post_id + "&c=" + commentId + "&_wpnonce=" + nonces.data( 'live-comments' );
			var linkAction = function (action) {
				return "comment.php?action=" + action + postAndCommentIds;
			};

			var rowActions = jQuery("<div class='row-actions'></div>");

			var postLink = linkTag( lp_strings.post_link, "#", lp_strings.send_to_editor );
			rowActions.append(commentLink('post', postLink, true));
			var approveLink = linkTag( lp_strings.approve_comment, linkAction('approvecomment'), lp_strings.approve );
			rowActions.append(commentLink('approve', approveLink));
			var unapproveLink = linkTag( lp_strings.unapprove_comment, linkAction("unapprovecomment"), lp_strings.unapprove );
			rowActions.append(commentLink('unapprove', unapproveLink));
			var spamLink = linkTag( lp_strings.mark_as_spam, linkAction("spamcomment"), lp_strings.spam );
			rowActions.append(commentLink('spam', spamLink));
			var trashLink = linkTag( lp_strings.move_comment_trash, linkAction("trashcomment"), lp_strings.trash );
			rowActions.append(commentLink('trash', trashLink));

			var removeComment = function () {
				jQuery("#lp-comment-" + commentId).css({
					'background-color': 'rgb(255, 170, 170)'
				}).fadeOut("slow", function () {
						jQuery(this).remove();
						OORTLE.Livepress.LivepressHUD.sumToComments(-1);
					});
			};

			var bindApprove = function (link) {
				link.click(function (e) {
					e.preventDefault();
					e.stopPropagation();

					jQuery.post("admin-ajax.php", jQuery.extend({}, defaultAjaxData, {
						'new':    'approved',
						action:   'lp_dim_comment',
						dimClass: 'unapproved'
					}),
						function( returnnonce ) {
							jQuery.post("admin-ajax.php", jQuery.extend({}, defaultAjaxData, {
								'new':       'approved',
								action:      'dim-comment',
								dimClass:    'unapproved',
								_ajax_nonce: returnnonce.data.approve_comment_nonce
							}),
							function () {
								jQuery("#lp-comment-" + commentId).removeClass("unapproved").addClass("approved");
							} );
						}
					);
				});
			};

			var bindUnapprove = function (link) {
				link.click(function (e) {
					e.preventDefault();
					e.stopPropagation();
					jQuery.post("admin-ajax.php", jQuery.extend({}, defaultAjaxData, {
						'new':    'unapproved',
						action:   'lp_dim_comment',
						dimClass: 'unapproved'
					}),
						function( returnnonce ) {
							jQuery.post("admin-ajax.php", jQuery.extend({}, defaultAjaxData, {
								'new':       'unapproved',
								action:      'dim-comment',
								dimClass:    'unapproved',
								_ajax_nonce: returnnonce.data.approve_comment_nonce
							}),
							function () {
								jQuery("#lp-comment-" + commentId).removeClass("approved").addClass("unapproved");
							} );
						}
					);
				});
			};

			bindApprove(approveLink);
			bindUnapprove(unapproveLink);

			spamLink.click(function (e) {
				e.preventDefault();
				e.stopPropagation();

				jQuery.post("admin-ajax.php", jQuery.extend({}, defaultAjaxData, {
						'new':    'unapproved',
						action:   'lp_dim_comment',
						dimClass: 'unapproved'
					} ),
					function( returnnonce ) {
						jQuery.post("admin-ajax.php",
							jQuery.extend({}, defaultAjaxData,
								{
									action: 'delete-comment',
									spam:   1,
									comment_status: 'all',
									_url:   linkForSpamAndTrash,
									_ajax_nonce: returnnonce.data.delete_comment_nonce
								}),
							removeComment
						);
					});
			});

			trashLink.click(function (e) {
				e.preventDefault();
				e.stopPropagation();

				jQuery.post("admin-ajax.php", jQuery.extend({}, defaultAjaxData, {
						'new':    'unapproved',
						action:   'lp_dim_comment',
						dimClass: 'unapproved'
					}),
					function( returnnonce ) {
						jQuery.post("admin-ajax.php",
							jQuery.extend({}, defaultAjaxData,
								{
									action: 'delete-comment',
									trash:  1,
									comment_status: 'all',
									_url:   linkForSpamAndTrash,
									_ajax_nonce: returnnonce.data.delete_comment_nonce
								}),
							removeComment
						);
					});
			});

			postLink.bind('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var t = tinyMCE.activeEditor;
				var textToAppend = "\n" + data.author + ": <blockquote>" + data.content + "</blockquote>";
				t.setContent(t.getContent() + textToAppend);
				return false;
			});

			return rowActions;
		};

		return {
			createCommentDiv:      createCommentDiv,
			prepareCommentActions: prepareCommentActions
		};
	}());
}
