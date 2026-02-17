<?php

if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'production' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	return;
}

function vip_do_non_prod_bar() {
	$environment = constant( 'VIP_GO_APP_ENVIRONMENT' );
	if ( 'local' === $environment && 'true' === getenv( 'CODESPACES' ) ) {
		$environment = 'codespaces';
	}
	?>
	<div id="vip-non-prod-bar">
		<span>
			<strong><?php echo esc_html( $environment ); ?></strong>
		</span>
	</div>
	<?php
}

function vip_non_prod_enqueue_scripts() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	if ( apply_filters( 'vip_show_non_prod_bar', true ) ) {
		wp_register_style( 'vip-non-prod-bar', plugins_url( '/assets/nonprod.css', __FILE__ ), [], '1.0' );
		wp_register_script( 'vip-non-prod-bar', plugins_url( '/assets/nonprod.js', __FILE__ ), [], '1.0', true );

		wp_enqueue_style( 'vip-non-prod-bar' );
		wp_enqueue_script( 'vip-non-prod-bar' );

		add_action( 'wp_footer', 'vip_do_non_prod_bar', 10000 );
		add_action( 'admin_footer', 'vip_do_non_prod_bar', 10000 );
	}
}

add_action( 'wp_enqueue_scripts', 'vip_non_prod_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'vip_non_prod_enqueue_scripts' );
