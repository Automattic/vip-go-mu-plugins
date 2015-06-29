(function($){

	$.fn.post_selection_ui = function() {

		return this.each(function() {
			var $selectedIDs, $selectionBox, $selectedPosts, $spinner, postIn, order, orderby, autoload, max_posts, is_full, post_type, post_status, update_box, ajax_request, add_post, restore_post, remove_all_posts, remove_post, switch_to_tab, PostsTab, searchTab, listTab, $searchInput, name;

			$selectionBox = $(this);

			$thisID = $selectionBox.attr('id');
			if($selectionBox.hasClass('psu-active')){
				return false;
			}
			$selectedIDs = $selectionBox.children('input');
			$selectedPosts = $selectionBox.find('.psu-selected');
			max_posts = parseInt($selectionBox.data('cardinality'));
			post_type = $selectionBox.data('post_type');
			post_status = $selectionBox.data('post_status');
			autoload = Boolean($selectionBox.data('infinite-scroll'));
			order = $selectionBox.data('order');
			orderby = $selectionBox.data('orderby');
			name = $selectedIDs.attr('name');
			postIn = $selectionBox.data('post-in');

			$selectionBox.addClass('psu-active');

			$spinner = $('<img>', {
				'src': PostSelectionUI.spinner,
				'class': 'psu-spinner'
			});

			restore_post = function($tr){
				$tr.find('.psu-col-order').remove();
				$tr.find('td.psu-col-delete').addClass('psu-col-create').removeClass('psu-col-delete')
				   .find('a').attr('title', 'Add');

				$selectionBox.find('table.psu-results tbody').append($tr);
			}

			remove_all_posts = function(ev){
				if (!confirm(PostSelectionUI.clearConfirmMessage))
					return;

				$selectedPosts.find('tbody tr').each(function(){
					restore_post($(this));
				});

				update_box();
				ev.preventDefault();
			}

			remove_post = function(ev){
				var $self = $(ev.target),
					$tr = $self.closest('tr');

				restore_post($tr);

				update_box();
				ev.preventDefault();
			}

			add_post = function(ev) {
				var $self, $tr;
				if(is_full())
					return false;
				$self = $(ev.target);
				$tr = $self.closest('tr');

				var psu_item_selected_event = jQuery.Event('psu_item_selected');
				$selectionBox.trigger(psu_item_selected_event, [{post_id : $tr.data('post_id'), target : $tr}]);
				if(!psu_item_selected_event.isDefaultPrevented() ) {
					var handle = $selectedPosts.find('tbody.sortable').length ? '<td class="psu-col-order">&nbsp;</td>' : '';
					$tr.appendTo($selectedPosts).append(handle)
					.find('td.psu-col-create').removeClass('psu-col-create').addClass('psu-col-delete')
					.find('a').attr('title', 'Remove');

					update_box();
				}

				ev.preventDefault();
				return false;
			}

			update_box = function() {
				//update id list
				ids = [];
				$selectedPosts.find('tbody tr').each(function(){
					ids.push($(this).data('post_id'));
				});
				$selectedIDs.val(ids.join(','));

				//update views
				if (0 == $selectedPosts.find('tbody tr').length) {
					$selectedPosts.hide();
				} else {
					$selectedPosts.show();
				}

				if (is_full()) {
					$selectionBox.find('.psu-add-posts').hide();
				} else {
					$selectionBox.find('.psu-add-posts').show();
				}
			}

			is_full = function() {
				return (max_posts > 0 && $selectedPosts.find('tbody tr').length >= max_posts);
			}


			switch_to_tab = function(){
				var $tab;
				$tab = $(this);
				$selectionBox.find('.wp-tab-bar li').removeClass('wp-tab-active');
				$tab.addClass('wp-tab-active');
				$selectionBox.find('.tabs-panel').hide().end().find($tab.data('ref')).show().find(':text').focus();
				return false;
			}


			$selectionBox.delegate('th.psu-col-delete a', 'click', remove_all_posts).delegate('td.psu-col-delete a', 'click', remove_post).delegate('td.psu-col-create a', 'click', add_post).delegate('.wp-tab-bar li', 'click', switch_to_tab);


			ajax_request = function(data, callback){
				data.action = 'psu_box';
				data._ajax_nonce = PostSelectionUI.nonce;
				data.post_type = post_type;
				data.exclude = $selectedIDs.val();
				data.order = order;
				data.orderby = orderby;
				data.post_status = post_status;
				data.name = name;
				data.include = postIn;
				return $.getJSON(ajaxurl + '?' + $.param(data), callback);
			}

			$selectedPosts.find('tbody.sortable').sortable({
				handle: 'td.psu-col-order',
				helper: function(e, ui){
					ui.children().each(function(){
						var $this;
						$this = $(this);
						return $this.width($this.width());
					});
					return ui;
				},
				update: update_box
			});

			PostsTab = (function(){
				PostsTab.displayName = 'PostsTab';
				var prototype = PostsTab.prototype, constructor = PostsTab;

				function PostsTab(selector){
					this.tab = $selectionBox.find(selector);
					this.init_pagination_data();
					this.tab.delegate('.psu-prev, .psu-next', 'click', __bind(this, this.change_page));
					this.data = {};
				}

				prototype.init_pagination_data = function(){
					this.current_page = this.tab.find('.psu-current').data('num') || 1;
					this.total_pages = this.tab.find('.psu-total').data('num') || 1;

					if( autoload && this.tab.hasClass('psu-tab-list') ){
						this.tab.find('.psu-next').remove();
						this.tab.find('.psu-prev').remove();

						if ( this.current_page < this.total_pages ) {
							this.infinite_scroll(this.current_page);
						}

					} else {

						if(this.current_page > 1){
							this.tab.find('.psu-prev').removeClass('inactive');
						} else {
							this.tab.find('.psu-prev').addClass('inactive');
						}
						if(this.current_page == this.total_pages){
							this.tab.find('.psu-next').addClass('inactive');
						} else {
							this.tab.find('.psu-next').removeClass('inactive');
						}
					}

					return this.total_pages;
				}

				prototype.change_page = function(ev){
					var $navButton, new_page;
					$navButton = $(ev.target);
					new_page = this.current_page;
					if ($navButton.hasClass('inactive'))
						return false;
					if ($navButton.hasClass('psu-prev')) {
						new_page--;
					} else {
						new_page++;
					}
					this.find_posts(new_page);
					return false;
				}

				prototype.find_posts = function(new_page){
					this.data.paged = new_page ? new_page > this.total_pages ? this.current_page : new_page : this.current_page;
					$spinner.appendTo(this.tab.find('.psu-navigation'));
					return ajax_request(this.data, __bind(this, this.update_rows));
				}


				prototype.update_rows = function(response){
					$spinner.remove();
					if (!response.rows) {
						return this.tab.append($('<div class="psu-notice">').html(response.msg));
					}

					if ( autoload && this.tab.hasClass('psu-tab-list') ){
						this.tab.find('.psu-navigation, .psu-notice').remove();
						this.tab.append('<hr/>' + response.rows);
					} else {
						this.tab.find('.psu-results, .psu-navigation, .psu-notice').remove();
						this.tab.append(response.rows);
					}

					return this.init_pagination_data();
				}

				prototype.infinite_scroll =  function(page){
					var	$box = this,
						$this = this.tab,
						updating = false,
						height = $this.prop('scrollHeight') - $this.height()
					;
					$this.scroll(function () {
						var	scroll = $this.scrollTop(),
							isScrolledToEnd = (scroll >= (height - 50))
						;

						if (isScrolledToEnd && !updating) {
							updating = true;
							$box.find_posts(page+1);
						}
					});
				}
				return PostsTab;
			}());

			searchTab = new PostsTab('.psu-tab-search');
			listTab = new PostsTab('.psu-tab-list');
			$searchInput = $selectionBox.find('.psu-tab-search :text');
			$searchInput.keypress(function(ev){
				if (13 === ev.keyCode) {
					return false;
				}
			}).keyup(function(ev){
				var delayed;
				if (undefined !== delayed) {
					clearTimeout(delayed);
				}
				return delayed = setTimeout(function(){
					var searchStr;
					searchStr = $searchInput.val();
					if ('' == searchStr || searchStr === searchTab.data.s) {
						return;
					}
					searchTab.data.s = searchStr;
					$spinner.insertAfter($searchInput).show();
					return searchTab.find_posts(1);
				}, 400);
			});

			update_box(); //make sure inputs match what screen currently shows
		});
	} //end $.fn.post_selection_ui

	var setVal, clearVal;

	if (!$('<input placeholder="1" />')[0].placeholder) {
		setVal = function(){
			var $this;
			$this = $(this);
			if (!$this.val()) {
				$this.val($this.attr('placeholder'));
				$this.addClass('psu-placeholder');
			}
		};
		clearVal = function(){
			var $this;
			$this = $(this);
			if ($this.hasClass('psu-placeholder')) {
				$this.val('');
				$this.removeClass('psu-placeholder');
			}
		};
		$('.psu-search input[placeholder]').each(setVal).focus(clearVal).blur(setVal);
	}

	function __bind(me, fn){return function(){return fn.apply(me, arguments)}}

	if($('#widgets-right').is('*')){
		$('#widgets-right .psu-box').post_selection_ui();
	} else {
		$('.psu-box').post_selection_ui();
	}

	//work around for first creation of widget
	if(typeof(wpWidgets) === 'object') {
		var oldSave = __bind(wpWidgets, wpWidgets.fixLabels);

		wpWidgets.fixLabels = function(widget) {
			oldSave(widget);
			if(typeof console != 'undefined'){
				console.log(widget);
			}
			widget.find('.psu-box').post_selection_ui();
		};
	}
})(jQuery);