jQuery( document ).ready( function($) {
	
	//parent window, cross browser
    var win = window.dialogArguments || opener || parent || top;

	//more link
	$('#moreLink a').click( function() {
		for( i=0; i<20; i++ ) {
			$('#row' + i).show();
		}
		$(this).fadeOut();
		return false;
	});
	
	//logout
	$('#storifyLogout').click( function() {
		$('#logoutForm').submit();
		return false;
	});
	
	//insert link
	$('.insertLink a').click( function(){
		html = '<p>' + $(this).parent().siblings('.permalink').text() + '</p>';
		win.tinyMCE.execCommand( "mceInsertContent", false, html );
		tinyMCEPopup.close();
		return false;
	});	
	
	//allow clicking story to triger insert
	$('.story').click( function() {
		$(this).find('.insertLink a').click();
	});
	
	//title fix
	if ( storify.iframe ) {
		win.setTimeout( "jQuery('.mceTop span').html( storify.dialogTitle )", 1 );
	}
	
	$(window).resize( function() { resizeStorifyIframe(); });
	
	resizeStorifyIframe();
	
});

//responsively resizes the iframe and fixes the firefox 100% height bug
function resizeStorifyIframe() {
	
	if ( !jQuery( 'iframe#storify' ) )
		return;
	
	var height = jQuery( 'body' ).height() - 120;
	
	//WP hides the footer on short screens so we can take up more space
	var footer = jQuery( window ).height() - jQuery( '#adminmenuwrap').height();
	
	//screen is shorter than content, so footer is hidden
	if ( footer < 0 )
		height = height - footer;
	
	jQuery( 'iframe#storify' ).height( height );
	
	//if window is minimized horizontally, fold the admin menu
	//if storify folds the menu and and the window is later widended, expand the menu 
	//otherwise, respect user preference
	if ( jQuery( window ).width() < 1240 ) {
		jQuery( 'body' ).addClass( 'folded' );
		storifyFolded = true;
	} else if ( storifyFolded ) {
		jQuery( 'body' ).removeClass( 'folded' );	
	}
	
}

var storifyFolded = false;