<?php
add_action( 'save_post', 'tinypass_post_saved' );
add_action( 'add_meta_boxes', 'tinypass_add_meta_boxes' );

function tinypass_add_meta_boxes() {
	add_meta_box( 'tinypass_page_options', '<img src="' . TINYPASS_FAVICON . '">&nbsp;' . __( 'Tinypass Options' ), 'tinypass_page_options_display', 'page', 'side' );
}

/**
 * Page integration - when a page is saved check for the checkbox to enable/disable Tinypass
 */
function tinypass_post_saved( $postID ) {

	if ( isset( $_REQUEST['tp-post-save-nonce'] ) && wp_verify_nonce( $_REQUEST['tp-post-save-nonce'], 'tp-post-save-nonce' ) ) {
		tinypass_include();
		$storage = new TPStorage();

		$ps = $storage->getPostSettings( $postID );

		if ( isset( $_POST['tinypass'] ) && isset( $_POST['tinypass']['enabled'] ) ) {
			$ps->setEnabled( true );
		} else {
			$ps->setEnabled( false );
		}

		$storage->savePostSettings( $postID, $ps );
	}
}

/**
 * Display the enable toggle on the page edit page
 */
function tinypass_page_options_display( $post ) {
	tinypass_include();
	$storage = new TPStorage();
	$ps = $storage->getPostSettings( $post->ID );
	?>

	<?php wp_nonce_field( 'tp-post-save-nonce', 'tp-post-save-nonce' ); ?>
	<input type="checkbox" name="tinypass[enabled]" id="tp_page_enabled" <?php checked( $ps->isEnabled() ) ?> ><label for="tp_page_enabled">&nbsp;Protect this page<label>

		<?php
		}