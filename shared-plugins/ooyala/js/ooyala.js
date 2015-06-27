if (!window.OV) {
	window.OV = {};
}

OV.Popup = function()  {
	
	function switchTabs(whichTab) {
			jQuery('.ov-tabs li a').removeClass('current');
			jQuery('#ov-tab-'+ whichTab +' a').addClass('current');
			jQuery('.ov-content').hide();
			jQuery('#ov-content-' + whichTab).show();
	}
	
	function setThumbnail( image, $link ) {
		
		var data = {
				action: 'ooyala_set',
				ooyala: 'thumbnail',
				img: image,
				postid: postId,
				_wpnonce: ajax_nonce_ooyala
		};
		
		var win = window.dialogArguments || opener || parent || top;	
		
		jQuery.post( ajaxurl, data, function(response) {
			$link.text( setPostThumbnailL10n.setThumbnail );
			if ( response == '0' ) {
				alert( setPostThumbnailL10n.error );
			} else {
				$link.text( setPostThumbnailL10n.done );
				$link.fadeOut( 2000 );
				win.jQuery('#postimagediv .inside').html(response);			}
		});
	}

	
	function insertShortcode( vid, player ) {

		var shortcode = '[ooyala code="' + vid + '" player_id="' + player + '"]';
		
		var win = window.dialogArguments || opener || parent || top;
		var isVisual = (typeof win.tinyMCE != "undefined") && win.tinyMCE.activeEditor && !win.tinyMCE.activeEditor.isHidden();	
		if (isVisual) {
			win.tinyMCE.activeEditor.execCommand('mceInsertContent', false, shortcode);
		} else {
			var currentContent = jQuery('#content', window.parent.document).val();
			if ( typeof currentContent == 'undefined' )
			 	currentContent = '';		
			jQuery( '#content', window.parent.document ).val( currentContent + shortcode );
		}
		self.parent.tb_remove();
	}
	
	return {
		ooyalaRequest: function( what, searchTerm, searchField, pageId ) {

			if ( 'paging' == what ) {
				previousRequest = jQuery('#response-div').data('previousRequest');
				searchTerm = jQuery('#ov-search-term').val();//previousRequest.searchTerm;
				searchField = jQuery('#ov-search-field').val();//previousRequest.searchField;
				what = previousRequest.what;
			}

			searchTerm = ( searchTerm == '' ) ? '' : searchTerm;
			searchField = ( searchField == '' ) ? '' : searchField;
			pageId =  ( pageId == '' ) ? '0' : pageId;
			postId = ( postId == '' ) ? '0' : postId;


			//Let's store this search in case we get a subsequent paging request
			jQuery('#response-div').data( 'previousRequest', {searchTerm: searchTerm, what: what, searchField: searchField});

			var data = {
					action: 'ooyala_request',
					ooyala_ids: jQuery('#ooyala-ids').val(),
					ooyala: what,
					key_word: searchTerm,
					search_field: searchField,
					pageid: pageId,
			};
			jQuery.get( ajaxurl, data, function(response) {
					var latestLink = '<span id="latest-link">(<a href="#" id="ov-last-few">'+ooyalaL10n.latest_videos+'</a>)</span>';
					var title = (data.ooyala == 'search') ?  ooyalaL10n.search_results + latestLink : ooyalaL10n.latest_videos;
					var htmlTitle = '<h3 class="media-title">'+title+'</h3>';
					jQuery('#response-div').html(htmlTitle + response);
			});
		},
		resizePop: function () {
			try {
				//Thickbox won't resize for some reason, we are manually doing it here
				var totalWidth = jQuery('body', window.parent.document).width();
				var totalHeight = jQuery('body', window.parent.document).height();
				var isIE6 = typeof document.body.style.maxHeight === "undefined";
				
				jQuery('#TB_window, #TB_iframeContent', window.parent.document).css('width', '768px');
				jQuery('#TB_window', window.parent.document).css({ left: (totalWidth-768)/2 + 'px', top: '23px', position: 'absolute', marginLeft: '0' });
				if ( ! isIE6 ) { // take away IE6
					jQuery('#TB_window, #TB_iframeContent', window.parent.document).css('height', (totalHeight-73) + 'px');
				}
			} catch(e) {
				if (debug) {
					console.log("resizePop(): " + e);
				}
			}
		},
		init: function () { 
			// Scroll to top of page
			window.parent.scroll(0,0);
			jQuery('#ov-tab-ooyala a').click(function () {
				switchTabs('ooyala');
				return false;
			});
			jQuery('#ov-tab-local a').click(function () {
				switchTabs('local');
				return false;
			});
			jQuery('#ov-tab-upload a').click(function () {
				switchTabs('upload');
				return false;
			});
			jQuery('#ov-search-button').click(function () {
				jQuery('#ooyala-ids').val('');
				OV.Popup.ooyalaRequest('search', jQuery('#ov-search-term').val(), jQuery('#ov-search-field').val() );
				return false;
			});
			jQuery('#ov-last-few').live('click', function () {
				OV.Popup.ooyalaRequest('last_few');
				jQuery('#ov-content-upload h3').text('Lastest videos');
				return false;
			});
			jQuery('.ooyala-paging').live( 'click', function(e) {
				e.preventDefault();
				pageId = jQuery(this).attr('href').substring(1);
				OV.Popup.ooyalaRequest( 'paging','', '', pageId );
				return false;
			});
			jQuery('.ooyala-item div a.use-shortcode').live( 'click', function(e) {
				e.preventDefault();
				id = jQuery(this).attr('title');
				jQuery('#ooyala_vid').val(id);
			});	
			jQuery('.ooyala-item div a.use-featured').live( 'click', function(e) {
				var $link = jQuery(this);
				e.preventDefault();
				id = jQuery(this).parent().prev('a').attr('title');
				image = jQuery(this).parent().prev('a').children('img').attr('src');
				jQuery('a.use-featured').text(ooyalaL10n.use_as_featured).show();
				$link.text(setPostThumbnailL10n.saving);
				setThumbnail( image, $link );
			});
			
			jQuery('#ooyala-insert').click( function() {
				var vid = jQuery('#ooyala_vid').val();
				if ( vid != '')
					insertShortcode( vid, jQuery('#ooyala_player_id').val() );
				return false;
			});
			
			jQuery('#ooyala-close').click( function(e) {
				e.preventDefault();
				self.parent.tb_remove();
				return false;
			});
			
		}
	};
}();

/* Uploader Functions */

function ooyalaOnFileSelected(file) { 
	jQuery('#ooyala_file_name').val( file.name ); 
} 
function ooyalaOnProgress(event) { 
	jQuery('#ooyala-status').html( (parseInt(event.ratio * 10000) / 100) + '%' );  
} 
function ooyalaOnUploadComplete() { 
	jQuery('#ooyala-status').html( ooyalaL10n.done );
	jQuery('#uploadButton').attr('disabled', false);
} 
function ooyalaOnUploadError(text) { 
	jQuery('#ooyala-status').html( ooyalaL10n.upload_error +': ' + text ); 
} 

function ooyalaStartUpload() { 
	try { 
  		ooyalaUploader.setTitle( jQuery('#ooyala_file_name').val() );
		ooyalaUploader.setDescription( jQuery('#ooyala_description').val() ); 
		var errorText = ooyalaUploader.validate();  
  		if (errorText) {  
    		alert(errorText);  
    		return false;  
  		}  
		jQuery('#uploadButton').attr('disabled', true);
  		ooyalaUploader.upload(); 
	} catch(e) {  
  		alert(e); 
	} 
	return false; 
}