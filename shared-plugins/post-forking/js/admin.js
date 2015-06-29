jQuery( document ).ready( function( $ ){
	$( '#branches' ).change( function(){
		$(location).attr( 'href', 'post.php?post=' + $(this).val() + '&action=edit' );
	});
});
