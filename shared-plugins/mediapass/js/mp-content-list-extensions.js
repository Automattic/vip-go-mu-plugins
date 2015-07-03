MM = typeof MM === 'undefined' ? {} : MM;
MM.WP = typeof MM.WP === 'undefined' ? {} : MM.WP;

MM.WP.ContentListExtensions = function(config) {
	var $   = jQuery;
	
	var cfg = $.extend({},{
		targetAction:  	'content-list-update-protection',
		selectedPosts: 	'input[name="post[]"]:checked',
		before: 		'.tablenav-pages'
	}, config || {});
	
	var actionContainer = "#mediapass-bulk-post-actions",
		before 			= cfg.before,
		selectedPosts 	= cfg.selectedPosts,
		targetAction	= cfg.targetAction,
		actionAttr		= 'mp-protection-action',
		postIdAttr		= 'mp-post-id',
		indContainer  	= '.column-mediapass',
		indClass		= '.mp-post-protection-indicator',
		actClass		= '.mp-post-action-protection';
	
	var getSelectedPosts = function() {
		var selectedIds = [],
			$selectedPosts = $(selectedPosts);
		
		console.log($selectedPosts);
		
		$.each($selectedPosts, function(i,v){
			selectedIds.push( parseInt(v.value) )
		});
	
		return selectedIds;	
	};
	
	var toggleProtectionStatus = function(el){
		var $el = $(el);
		
		var old = $el.attr(actionAttr);
		var newState = old === 'remove' ? 'protect' : 'remove';
		var newText  = old === 'remove' ? 'Enable!' : 'Disable!';
		var newLabel = old === 'remove' ? 'Free' : 'Premium';
		var newImg = old === 'remove' ? 'unprotected_icon.png' : 'protected_icon.png';
		var curImg = old === 'remove' ? 'protected_icon.png' : 'unprotected_icon.png';
		
		var $ind = $el.prev();
		var $img = $ind.prev();
		
		console.log($ind, $img);
		
		var oldImage = $img.attr('src');
		
		$img.attr('src', oldImage.replace(curImg,newImg));
		
		$el.attr(actionAttr,newState);
		$el.text(newText);
		$ind.text(newLabel);
	}
	
	var updateSelectedPosts = function(selectedPostIds, actionRequested, cb) {
		$.post(ajaxurl + '?nonce=' + cfg.nonce,{
			action: targetAction,
			dataType: 'json',
			data: JSON.stringify({
				actionRequested: actionRequested,
				selectedPosts: selectedPostIds
			}),
		}, cb || function() {
			window.location.reload();
		});
	};
	var handleSingleActionSelected = function(postId,action,el){
		updateSelectedPosts([postId],action, function(resp){
			toggleProtectionStatus(el);
			console.log('updated!',resp, postId);
		});
	};
	
	var handleBulkActionSelected = function() {
		var selectedIds = getSelectedPosts(),
			actionRequested = $(this).val();
		
		console.log('bulk action',actionRequested);
		console.log('selected posts', selectedIds);
		
		if( actionRequested !== "none" ) {
			updateSelectedPosts( selectedIds, actionRequested );
		}
	};
	
	var renderBulkActionControl = function(){
		$(actionContainer).each(function(){
			$(this).insertBefore(before);
			$(this).show();
			$('select',this).change(handleBulkActionSelected);
		});
		
		var listRow = ".wp-list-table tr";
		
		$(document).on('mouseenter', listRow, function() {
			$(indClass,this).hide();
			$(actClass,this).show();
		}).on('mouseleave', listRow, function(){
			$(indClass,this).show();
			$(actClass,this).hide();
		});
		
		$(document).on('click', actClass, function(){
			var postId = $(this).attr(postIdAttr),
				action = $(this).attr(actionAttr);
				
			console.log(postId,action);
			
			handleSingleActionSelected(parseInt(postId),action,this);
			
			return false;
		});
	};
	
	return {
		getSelectedPosts: getSelectedPosts,
		renderBulkActionControl: renderBulkActionControl
	};
};

