<?php

if ( ! defined( 'WPCOM_SANDBOXED' ) || ! WPCOM_SANDBOXED ) {
	return;
}

function wpcom_do_sandbox_bar() {
	if ( is_user_logged_in() && ! is_admin_bar_showing() ) {
		return;
	}

	if ( apply_filters( 'wpcom_show_sandbox_bar', false ) ) {
		$template   = get_template();
		$stylesheet = get_stylesheet();
		$theme      = ( $stylesheet != $template ) ? sprintf( '%s => %s', $stylesheet, $template ) : $template;

		$debug_info = array(
			'sandbox' => str_replace( '.wordpress.com', '', php_uname( 'n' ) ),
			'theme'   => $theme,
			'your ip' => $_SERVER['REMOTE_ADDR'] ?? '', // phpcs:ignore
		);

		$debug_info = apply_filters( 'wpcom_sandbox_bar_debug_info', $debug_info );

		?>
		<div id="wpcom-sandboxed-bar">
			<?php foreach ( $debug_info as $debug_name => $debug_value ) : ?>
				<span>
					<strong><?php echo esc_html( $debug_name ); ?></strong>
					<?php echo esc_html( $debug_value ); ?>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

function wpcom_sandbox_enqueue_scripts() {
	if ( is_user_logged_in() && ! is_admin_bar_showing() ) {
		return;
	}

	if ( apply_filters( 'wpcom_show_sandbox_bar', false ) ) {
		wp_register_style( 'wpcom-sandbox-bar', plugins_url( '/assets/sandbox.css', __FILE__ ), [], '1.0' );
		wp_register_script( 'wpcom-sandbox-bar', plugins_url( '/assets/sandbox.js', __FILE__ ), [], '1.0', true );

		wp_enqueue_style( 'wpcom-sandbox-bar' );
		wp_enqueue_script( 'wpcom-sandbox-bar' );
	}

	if ( apply_filters( 'wpcom_show_sandbox_bar', false ) ) {
		wp_register_style( 'wpcom-sandboxed-bar', plugins_url( '/assets/sandboxed.css', __FILE__ ), [], '1.0' );
		wp_register_script( 'wpcom-sandboxed-bar', plugins_url( '/assets/sandboxed.js', __FILE__ ), [], '1.0', true );

		wp_enqueue_style( 'wpcom-sandboxed-bar' );
		wp_enqueue_script( 'wpcom-sandboxed-bar' );

		add_action( 'wp_footer', 'wpcom_do_sandbox_bar', 100 );
		add_action( 'admin_footer', 'wpcom_do_sandbox_bar', 100 );
	}
}

add_action( 'wp_enqueue_scripts', 'wpcom_sandbox_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'wpcom_sandbox_enqueue_scripts' );


add_action( 'wp_footer', 'wpcom_do_sandbox_bar', 100 );
add_action( 'admin_footer', 'wpcom_do_sandbox_bar', 100 );

add_filter( 'wpcom_show_sandbox_bar', '__return_true' );

/**
 * Filters plugin URLs to use sandboxed hostnames.
 *
 * @param string $url The complete URL to the plugins directory including scheme and path.
 *
 * @return string Filtered URL using sandboxed hostname.
 */
function wpvip_filter_sandbox_plugins_url( $url ) {
	/** @var array<string,string> */
	global $sandbox_vhosts;
	$host = $_SERVER['HTTP_HOST'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( empty( $host ) || ! is_array( $sandbox_vhosts ) ) {
		// Not a k8s sandbox or valid host, bail.
		return $url;
	}
	/*
	 * $sandbox_vhosts is something like ['subdomain.uuid.sbx-sid.ingress-api.vip-ditto.k8s.dfw.vipv2.net' => 'subdomain.go-vip.net']
	 * `sandbox-hosts-config.php` replaces the "sandbox" (key) domain with the "original" (value) domain in `$_SERVER['HTTP_HOST']`.
	 * In this filter, we need to reverse that for the given `$url`
	 *
	 * 1. We need to look up the key matching `$_SERVER['HTTP_HOST']` in `$sandbox_vhosts`
	 * 2. We need to replace `://{$_SERVER['HTTP_HOST']}` with `://{$key}`  in `$url`
	 */
	if ( in_array( $host, $sandbox_vhosts, true ) ) {
		$flipped = array_flip( $sandbox_vhosts );
		$key     = $flipped[ $host ];
		$url     = str_replace( '://' . $host, '://' . $key, $url );
	}

	return $url;
}
add_filter( 'plugins_url', 'wpvip_filter_sandbox_plugins_url', 1000 );
