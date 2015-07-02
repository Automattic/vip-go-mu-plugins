/*jslint vars:true */
/*global LivepressConfig, lp_strings, Dashboard, Livepress, tinyMCE, OORTLE, twttr */
Dashboard.Twitter = Livepress.ensureExists(Dashboard.Twitter);

Dashboard.Twitter.terms = [];
Dashboard.Twitter.tweets = [];
if (Dashboard.Twitter.twitter === undefined) {
	Dashboard.Twitter = jQuery.extend(Dashboard.Twitter, (function () {
		var tweetTrackerPaused = 0;
		var $paneHolder = jQuery('#lp-pane-holder');
		var twitter = Dashboard.Twitter;
		var tweetCounter = 0;

		var liveCounter = Dashboard.Helpers.createLiveCounter('#tab-link-live-twitter-search a'); // '#twitter-search-mark');
		var tweetContainer = "#lp-twitter-results";
		var tweetHolder = "#lp-hidden-tweets";
		var getTweetTarget = function(){
			return jQuery(tweetTrackerPaused>0 ? tweetHolder : tweetContainer);
		};

		var binders = (function () {
			var bindRemoveButtons = function (elToBind, type) {

				// Bind term close action, removing exisitng action 1st to avoid double firing
				elToBind.off( 'click' ).on( 'click', function () {
					var container = jQuery(this).parent('.lp-' + type),
						text = container.find(".lp-" + type + "-text").text(),
						id = container.attr('id');

					// Dashboard.Helpers.disableAndDisplaySpinner(jQuery(this));
					if (type === "term") {
						twitter.removeTerm(id, text);
					} else if (type === "tweet") {
						// Remove the first @ character
						text = text.substr(1, text.length);

						twitter.removeTweet(id, text);
					}
				});
			};

			var tweet_player_id = "#lp-tweet-player";
			var play = function () {
				tweetTrackerPaused--;
				if (tweetTrackerPaused <= 0) {
					tweetTrackerPaused = 0;
					liveCounter.disable();
					twitter.appendGatheredTweets();

					jQuery(tweet_player_id).attr('title', lp_strings.click_pause_tweets ).removeClass('paused');
					jQuery(tweetContainer).removeClass('paused');
					jQuery('#pausedmsg').hide();
				}
			};

			var pause = function () {
				tweetTrackerPaused++;
				if (tweetTrackerPaused === 1) {
					liveCounter.enable();

					jQuery(tweet_player_id).attr('title', lp_strings.click_copy_tweets ).addClass('paused');
					jQuery(tweetContainer).addClass('paused');
					jQuery('#pausedmsg').show();
				}
			};

			return {
				bindCleaners: function () {
					//var $tweetCleaner = $paneHolder.find('a.lp-tweet-cleaner');
					//var $termCleaner = $paneHolder.find('a.lp-term-cleaner');

					// Clear all tweet terms
					$paneHolder.on( 'click', 'a.lp-tweet-cleaner', function (e) {
						e.preventDefault();
						e.stopPropagation();
						// Dashboard.Helpers.disableAndDisplaySpinner(jQuery(this));
						twitter.removeAllTweets();
						return false;
					});

					// Clear all terms
					$paneHolder.on( 'click','.lp-term-cleaner a', function (e) {
						e.preventDefault();
						e.stopPropagation();
						// Dashboard.Helpers.disableAndDisplaySpinner(jQuery(this));
						twitter.removeAllTerms();
						if (jQuery(this).attr('data-action') === 'clear') {
							jQuery(tweetContainer).html('');
							jQuery(tweetHolder).html('');
						}
					});
				},


				bindAddTermInput: function () {
					/* Twitter search terms */
					var termAddInputAction = function (e) {
						e.preventDefault();
						e.stopPropagation();
						var term = jQuery("#live-search-query").val();
						if (term.length > 0) {
							twitter.addTerm(term);
						}
					}, meta = jQuery('#screen-meta');

					meta.on('click', '#live-search-column input.button-secondary', termAddInputAction);

					meta.on('keydown', '#live-search-column #live-search-query', function (e) {
						if (e.keyCode === 13) {
							termAddInputAction(e);
						}
					});
				},

				bindStaticSearchButton: function () {
					var $searchBox = jQuery('#lp-new-static-search');
					var $searchButton = jQuery('#lp-on-top input.new_static_search');

					var newStaticSearchAction = function (e) {
						e.preventDefault();
						e.stopPropagation();
						var query = jQuery("#lp-new-static-search").val();
						if (query.length > 0) {
							twitter.renderStaticSearchResults(query);
							$searchBox.val('');
						}
					};

					$searchButton.bind('click', newStaticSearchAction);

					$searchBox.keydown(function (e) {
						if (e.keyCode === 13) {
							newStaticSearchAction(e);
						}
					});
				},

				bindAddGuestBloggerInput: function () {
					var guestBloggerAddAction = function (e) {
							e.preventDefault();
							e.stopPropagation();
							var username = jQuery('#new-twitter-account').val();
							if (username.length > 0) {
								twitter.addGuestBlogger(username);
							}
						},
						meta = jQuery('#screen-meta');

					meta.on('click', '#remote-authors input.termadd', guestBloggerAddAction);

					meta.on('keydown', '#remote-authors #new-twitter-account', function (e) {
						if (e.keyCode === 13) {
							guestBloggerAddAction(e);
						}
					});

					meta.on('click', '#remote-authors .cleaner', function(e) {
						e.preventDefault();
						e.stopPropagation();
						twitter.removeAllTweets();
						return false;
					});

					meta.find(".lp-tweet-cleaner").hide();
				},

				bindRemoveTermButtons: function () {
					bindRemoveButtons(jQuery('.lp-term-clean-button'), 'term');
				},

				bindRemoveTweetButtons: function () {
					bindRemoveButtons(jQuery('.lp-tweet-clean-button'), 'tweet');
				},

				bindTweetPlayer: function () {
					jQuery(tweet_player_id).click(function (e) {
						e.preventDefault();
						e.stopPropagation();
						return jQuery(this).hasClass("paused") ? play() : pause();
					});
				},

				bindTweetMouse: function () {
					jQuery(tweetContainer).hover(pause, play);
				},

				init: function () {
					//this.bindCleaners();
					this.bindAddTermInput();
					this.bindStaticSearchButton();
					this.bindAddGuestBloggerInput();
					this.bindRemoveTweetButtons();
					this.bindRemoveTermButtons();
					this.bindTweetPlayer();
					this.bindTweetMouse();
				}
			};
		}());

		var prevTweetWasEven = false;
		var formatTweet = function (tweet) {
			var tweetDiv = jQuery("<div id=tweet-" + tweet.id + " class='comment-item " + ( prevTweetWasEven ? 'odd' : 'even' ) + "'></div>");
			var createdAt = tweet.created_at.parseGMT();
			var avatar = jQuery("<img class='avatar avatar-32 photo' width='32' height='32' />").attr('src', tweet.avatar_url).attr('alt', tweet.author);
			var authorDiv = jQuery("<div class='lp-comment-author author'>")
				.append(avatar)
				.append(jQuery("<span class=\"lp-tweet-author\"></span>")
				.text(tweet.author))
				.append(jQuery("<span class=\"lp-tweet-date\">" + createdAt + "</span>"));

			tweetDiv.append(authorDiv);
			tweetDiv.data('term', tweet.term);

			prevTweetWasEven = !prevTweetWasEven;

			var contentDiv = jQuery("<div class='lp-comment comment'></div>").append(jQuery("<p>" + tweet.text + "</p>").autolink());
			contentDiv.find('a').attr("target", "_blank");

			var rowActions = jQuery("<div class='row-actions'></div>");
			var postLink = jQuery("<span class='post'><a href='#' title='" + lp_strings.copy_tweets + "'>" + lp_strings.send_to_editor + "</a><span>");
			rowActions.append(postLink);
			postLink.bind('click', function (e) {
				e.preventDefault();
				e.stopPropagation();

				// Find the Livepress Real-Time tabgit checkout tinymce_targeting
				var t, i, el,
					editors = tinyMCE.editors;
				for ( i = 0; i <= tinyMCE.editors.length; i++ ) {
					el = editors[ i ];
					if ( -1 !== el['id'].indexOf( 'livepress' ) ) {
						t = el;
						break;
					}
				}
				var created_at = new Date(tweet.created_at);
				var textToAppend = "[embed]http://twitter.com/"+tweet.author+"/status/"+tweet.id+"[/embed]\n";

				t.setContent(t.getContent() + textToAppend);

				// Enable the update button.
				var pushBtn = jQuery('.livepress-newform' ).find('input.button-primary');
				pushBtn.removeAttr( 'disabled' );
			});
			contentDiv.append(rowActions);
			tweetDiv.append(contentDiv);

			return tweetDiv;
		};


		var pushTweet = function (container, tweet) {
			if (container.find("#tweet-" + tweet.id).length > 0) {
				return;
			}
			var spinner = container.find( '.lp-spinner' );
			if ( spinner.length > 0 ) {
				spinner.remove();
			}

			var formatedTweet = formatTweet(tweet);
			formatedTweet.hide();
			container.prepend(formatedTweet);
			container.find("div.comment-item:gt(200)").remove();
			formatedTweet.slideDown();
		};

		return {
			twitter:                       this, // for convenience
			liveCounter:                   liveCounter,
			new_tweet_for_search_callback: function (tweet) {
				var tweetsDiv = getTweetTarget();

				tweet.id = tweet.id_str;
				pushTweet(tweetsDiv, tweet);

				if (liveCounter.enabled) {
					liveCounter.increment();
				}
			},

			follow_twitter_search: function (term) {
				var topic = "|livepress|tweet-search|" + term;
				OORTLE.instance.subscribe(topic, this.new_tweet_for_search_callback);
			},

			unfollow_twitter_search: function (term) {
				var topic = "|livepress|tweet-search|" + term;
				OORTLE.instance.unsubscribe(topic, this.new_tweet_for_search_callback);
			},

			// FIXME: we shouldn't reload all of the terms, just add the neccessary ones.
			refresh_terms:           function () {
				var terms = Dashboard.Twitter.terms;
				var termHtml = "";

				for (var i = 0; i < terms.length; i += 1) {
					termHtml += '<div class="lp-term" id="term-' + i + '"><a class="dashicons dashicons-dismiss lp-term-clean-button" title="' + lp_strings.remove_term + '"></a><span class="lp-term-text">' + terms[i] + '</span></div>';
				}

				termHtml += '<div class="clear"></div>';
				jQuery('#lp-twitter-search-terms').html(termHtml);

				if (jQuery('#twitter-search-pane').is('.lp-pane-active')) {
					jQuery("#lp-on-top").show();
				}
				binders.bindRemoveTermButtons();

				liveCounter.reset();
				if ( 0 === terms.length ) {
					jQuery(tweetContainer).html(''); // No more terms, clear the container
				} else {
					jQuery(tweetContainer).html("<div class='lp-spinner'></div>"); // Still terms, show spinner
				}
			},

			refresh_tweets: function () {
				var tweets = Dashboard.Twitter.tweets;
				var tweetHtml = "";
				for (var i = 0; i < tweets.length; i += 1) {
					tweetHtml += '<li id="tweet-' + i + '" class="' + ((i % 2 === 1) ? 'odd' : 'even') + ' lp-tweet">';
					tweetHtml += '<span class="lp-tweet-text">@' + tweets[i] + '</span>';
					tweetHtml += '<a class="lp-tweet-clean-button" title="' + lp_strings.remove_account + '">' + lp_strings.remove_lower + '</a>';
					tweetHtml += '</li>';
				}
				jQuery('#lp-account-list').html(tweetHtml);
				if (tweets.length) {
					jQuery(".lp-tweet-cleaner").show();
				}
				else {
					jQuery(".lp-tweet-cleaner").hide();
				}

				var $authors = jQuery( document.getElementById( 'livepress-authors_num' ) ),
					$author_label = $authors.siblings( '.label' );
				var label = ( 1 === tweets.length ) ? lp_strings.remove_author : lp_strings.remove_authors;

				$authors.html( tweets.length );
				$author_label.text( label );
				binders.bindRemoveTweetButtons();
			},

			/**
			 * Handles adding terms or tweet to accurate widget in the dashboard.
			 *
			 * @param {String} name Term to search or twitter account to follow.
			 * @param {Array} collection Array with tweets or terms
			 * @param {Object} options
			 *  dontPostToLivepress - if true, term/tweet is only added to widget without
			 *    sending it to livepress.
			 *  afterPushFunction - launched after adding tweet/term to widget,
			 *    after ajax returns with success
			 *  ajaxRequest - ajax for communication with livepress
			 *  inputToClean - jquery object of input field which should be cleared
			 * @return void
			 */
			addTermOrTweet: function (name, collection, options) {
				var defaults = {
					dontPostToLivepress: false,
					afterPushFunction:   function () {
					},
					ajaxRequest:         function () {
					},
					inputToClean:        null
				};
				options = jQuery.extend({}, defaults, options);
				if (options.inputToClean) {
					options.inputToClean.val('');
				}

				name = name.toLowerCase();
				if (jQuery.inArray(name, collection) !== -1) {
					return;
				}

				var addToCollection = function (env) {
					if (typeof(env) === "string") {
						env = JSON.parse(env);
					}

					if (env) {
						if (env.success) {
							collection.push(name);
							options.afterPushFunction();
						} else {
							if (env.errors) {
								if ( 'undefined' !== typeof( options.afterPushFailedFunction ) ){
									options.afterPushFailedFunction( env.errors );
								}
								Dashboard.Helpers.handleErrors(env.errors);
								if (options.inputToClean) {
									Dashboard.Helpers.enableAndHideSpinner(options.inputToClean);
								}
							}
						}
					}
				};

				if (options.dontPostToLivepress) {
					addToCollection({
						success: true
					});
				} else {
					// Dashboard.Helpers.disableAndDisplaySpinner(options.inputToClean);
					options.ajaxRequest(name, 'add', addToCollection);
				}
			},


			handleRemovingFromCollection: function (id, collection, name) {
				var number = id.replace(name + "-", "");
				collection.splice(number, 1);
				if (name === "tweet") {
					this.refresh_tweets(collection);
				} else {
					this.refresh_terms();
				}
			},

			addTerm: function (name, dontPostToLivepress) {
				var $newTermInput = jQuery("#live-search-query");
				var options = {
					dontPostToLivepress: dontPostToLivepress,
					afterPushFunction:   function () {
						twitter.follow_twitter_search(name);
						twitter.refresh_terms();
						Dashboard.Helpers.enableAndHideSpinner($newTermInput);
					},
					ajaxRequest:         twitter.postTweetSearch,
					inputToClean:        $newTermInput
				};

				this.addTermOrTweet(name, Dashboard.Twitter.terms, options);
			},

			removeTerm: function (id, name) {
				twitter.postTweetSearch(name, 'remove', function (env) {
					env = JSON.parse(env);
					if (env && env.success) {
						twitter.unfollow_twitter_search(name);
						twitter.handleRemovingFromCollection(id, Dashboard.Twitter.terms, 'term');

						jQuery(tweetContainer+' .comment-item').filter(function () {
							return jQuery.data(this, 'term') === name;
						}).remove();
					}
				});
			},

			unsubscribeTwitterChannels: function () {
				this.removeAllTerms(true);
			},

			/**
			 * Removes all terms from being tracked and unfollows their channels
			 *
			 * @param {Boolean} locallyOnly if true no ajax will be sent to livepress with
			 *  request to remove terms from being tracked
			 */
			removeAllTerms: function (locallyOnly) {
				var unfollowThemAll = function () {
					jQuery.each(Dashboard.Twitter.terms, function (i, term) {
						twitter.unfollow_twitter_search(term);
					});

					Dashboard.Twitter.terms = [];
					twitter.refresh_terms();
				};

				if (locallyOnly) {
					unfollowThemAll();
				} else {
					// Dashboard.Helpers.disableAndDisplaySpinner($paneHolder.find('a.lp-tweet-cleaner'));
					var callback = function (env) {
						env = JSON.parse(env);
						if (env && env.success) {
							unfollowThemAll();
						}
					};
					this.postTweetSearch('', 'clear', callback);
				}
			},

			/**
			 * Request search results from twitter and push them to pane
			 *
			 * @param {String} query    - Search query
			 */
			renderStaticSearchResults: function (query, target, limit) {
				if (!target) {
					target = function () {
						var container = jQuery('#lp-static-search-results');
						container.html('');
						return container;
					};
				}

				// Add a spinner to take care of notifications
				var container = target(),
					$spinner = jQuery( "<div class='lp-spinner'></div>" );

				// Only add the spinner if it doesn't exist
				if ( 0 === container.find( '.lp-spinner' ).length ) {
					$spinner.appendTo( container );
				}
			},

			/**
			 * Sends ajax with twitter search terms to follow or remove
			 *
			 * @param {String} term        Twitter search term
			 * @param {String} action_type Should be 'add', 'remove' or 'clear'
			 * @param {function} success   Function to be run on success callback
			 * @returns Always true
			 */
			postTweetSearch:          function (term, action_type, success) {
				jQuery.post(
					"admin-ajax.php",
					{
						_ajax_nonce:      LivepressConfig.ajax_twitter_search_nonce,
						action:           'lp_twitter_search_term',
						term:             term,
						action_type:      action_type,
						post_id:          LivepressConfig.post_id
					},
					success
				);
				if (action_type === 'add') {
					// Run single round of static search to populate stub results
					Dashboard.Twitter.renderStaticSearchResults(term, getTweetTarget, 5);

					twitter.refresh_terms();
				}
				return true;
			},

			// Auto tweets
			checkIfAutoTweetPossible: function () {
				if (!LivepressConfig.remote_post) {
					$paneHolder.find(".autotweet-container").hide();
					jQuery("#autotweet-blocked .warning").show();
				} else {
					$paneHolder.find(".autotweet-container").show();
					jQuery("#autotweet-blocked .warning").hide();
				}
			},

			addGuestBlogger: function (username, dontPostToLivepress) {
				var $newTweetInput = jQuery("#new-twitter-account"),
					$errorField    = jQuery( '#termadderror' );
				username = username.replace("@", '');
				var options = {
					dontPostToLivepress: dontPostToLivepress,
					afterPushFunction:   function () {
						twitter.refresh_tweets();
						Dashboard.Helpers.enableAndHideSpinner($newTweetInput);
					},
					afterPushFailedFunction: function( err ){
						$errorField.show();
						$errorField.find('#errmsg').html( err.username.replace( '[', '' ).replace( ']', '' ) );
						setTimeout( function(){ $errorField.fadeOut( 750 ); }, 2000 );
					},
					ajaxRequest:         this.postTwitterFollow,
					inputToClean:        $newTweetInput
				};

				this.addTermOrTweet(username, Dashboard.Twitter.tweets, options);
			},

			removeTweet: function (id, username) {
				var twitter = this;
				this.postTwitterFollow(username, 'remove', function (env) {
					env = JSON.parse(env);
					if (env && env.success) {
						twitter.handleRemovingFromCollection(id, Dashboard.Twitter.tweets, "tweet");
					}
				});
			},

			removeAllTweets: function () {
				this.postTwitterFollow('', 'clear', function (env) {
					env = JSON.parse(env);
					if (env && env.success) {
						Dashboard.Twitter.tweets = [];
						twitter.refresh_tweets();
					}
				});
			},

			appendGatheredTweets: function () {
				var tweets = jQuery(tweetHolder+" .comment-item");
				var results = jQuery(tweetContainer);
				tweets.hide();
				results.prepend(tweets);
				tweets.slideDown();
			},

			postTwitterFollow: function (username, action_type, success) {
				jQuery.post(
					"admin-ajax.php",
					{
						_ajax_nonce:      LivepressConfig.ajax_twitter_follow_nonce,
						action:           'lp_twitter_follow',
						username:         username,
						action_type:      action_type,
						post_id:          LivepressConfig.post_id
					},
					success
				);
				return true;
			},

			conditionallyEnable: function() {
				if ( 0 >= this.terms.length ) {
					this.liveCounter.disable();
				} else {
					this.liveCounter.enable();
				}
			},

			init: function () {
				liveCounter.enable();
				this.checkIfAutoTweetPossible();
				binders.init();
			}
		};
	}()));
}
