<?php
namespace My\Application;

// Load the VIP Vary_Cache class
require_once WP_CONTENT_DIR . '/mu-plugins/cache/class-vary-cache.php';

use Automattic\VIP\Cache\Vary_Cache;

// Register the `beta` group
Vary_Cache::register_group( 'beta' );

// Display the opt-in banner / button.
add_action( 'wp_footer', function () {
	// If the user is already in the beta group, don't show the opt-in.
	$is_user_in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );
	if ( $is_user_in_beta ) {
		return;
	}

	// If the user isn't in the beta group, let's show the opt-in button.
	?>
	<div style="position:fixed; bottom:0; left:0; right:0; padding:20px; background:#333; color:#e5e5e5;">
		<form method="POST">
			We have some new features that we need your help testing!
			<button name="beta-optin" value="yes">Enable them!</button>
		</form>
	</div>
	<?php
} );

// Handle the opt-in request.
add_action( 'init', function() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['beta-optin'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$beta = sanitize_key( $_POST['beta-optin'] );
		if ( 'yes' === $beta ) {
			Vary_Cache::set_group_for_user( 'beta', 'yes' );
		}

		// Redirect back to the same page (per the POST-REDIRECT-GET pattern).
		// Please note the use of the `vip_vary_cache_did_send_headers` action.
		add_action( 'vip_vary_cache_did_send_headers', function() {
			wp_safe_redirect( add_query_arg( '' ) );
			exit;
		} );
	}
} );

// Display the Like button for beta users.
add_filter( 'the_content', function( $content ) {
	$is_user_in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );
	if ( $is_user_in_beta ) {
		$like_button = '<p><a href="#" class="like-button">❤️ Like this post</a></p>';
		$content    .= $like_button;
	}
	return $content;
} );
