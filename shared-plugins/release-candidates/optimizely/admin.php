<?php
/**
 * PHP controller for plugin configuration page. The page itself is rendered in config.php  
 */

/**
 * Display admin notices for the plugin.
 */
function optimizely_admin_notices() {
	if ( ! get_option( 'optimizely_token' ) && ! isset( $_POST['submit'] ) ):
		?>
		<div id="optimizely-warning" class="updated fade">
			<p><strong><?php echo sprintf(
				'%s <a href="https://app.optimizely.com/tokens" target="_blank">%s</a> %s <a href="admin.php?page=optimizely-config#tabs-2">%s</a>.',
				esc_html__( 'Optimizely is almost ready. You must first add your', 'optimizely' ),
				esc_html__( 'API Token', 'optimizely' ),
				esc_html__( 'in the', 'optimizely' ),
				esc_html__( 'configuration tab', 'optimizely' )
			);?></strong></p>
		</div>
		<?php
	endif;
	if ( get_option( 'optimizely_token' ) && ! get_option( 'optimizely_project_id' ) && ! isset( $_POST['submit'] ) ):
		?>
		<div id="optimizely-warning" class="updated fade">
			<p><strong><?php esc_html_e( 'Optimizely is almost ready. You must choose a project', 'optimizely' ) ?>.</strong> 
			</p>
		</div>
		<?php
	endif;
}
add_action( 'admin_notices', 'optimizely_admin_notices' );

/**
 * Add Optimizely to the admin menu.
 */
function optimizely_admin_menu() {
	add_menu_page( __( 'Optimizely', 'optimizely' ), __( 'Optimizely', 'optimizely' ), 'manage_options', 'optimizely-config', 'optimizely_conf', plugin_dir_url( __FILE__ ) . 'images/optimizely-icon.png' );
}
add_action( 'admin_menu', 'optimizely_admin_menu' );

/**
 * Add action links for Optimizely.
 * @param array $links
 * @param string $file
 * @return array
 */
function optimizely_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname( __FILE__ ) . '/optimizely.php' ) ) {
		$links[] = '<a href="admin.php?page=optimizely-config">' . esc_html__( 'Settings', 'optimizely' ) . '</a>';
	}
	
	return $links;
}
add_filter( 'plugin_action_links', 'optimizely_plugin_action_links', 10, 2 );

/**
 * Update the Optimizely configuration.
 */
function optimizely_conf() {
	if ( isset( $_POST['submit'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			die( __( 'Cheatin&#8217; uh?', 'optimizely' ) );
		}
		
		// Check the nonce
		check_admin_referer( OPTIMIZELY_NONCE );

		// Sanitize values
		$token = sanitize_text_field( $_POST['token'] );
		$project_id = sanitize_text_field( $_POST['project_id'] );
		$num_variations = sanitize_text_field( $_POST['optimizely_num_variations'] );
		$optimizely_post_types = array_map( 'sanitize_text_field', $_POST['optimizely_post_types'] );
		$optimizely_visitor_count = str_replace( ',', '', sanitize_text_field( $_POST['optimizely_visitor_count'] ) );
		$project_name = sanitize_text_field( stripcslashes( $_POST['project_name'] ) );
		$variation_template = sanitize_text_field( stripcslashes( $_POST['variation_template' ] ) );
		$activation_mode = sanitize_text_field( $_POST['optimizely_activation_mode' ] );
		$conditional_activation_code = sanitize_text_field( stripcslashes( $_POST['conditional_activation_code' ] ) );
		$optimizely_url_targeting_type = sanitize_text_field( $_POST['optimizely_url_targeting_type'] );
		$optimizely_url_targeting = sanitize_text_field( $_POST['optimizely_url_targeting'] );

		// Either save or delete/set a default if empty for each value
		if ( empty( $token ) ) {
			delete_option( 'optimizely_token' );
		} else {
			update_option( 'optimizely_token', $token );
		}

		if ( empty( $project_id ) ) {
			delete_option( 'optimizely_project_id' );
		} else {
			update_option( 'optimizely_project_id', $project_id );
		}

		if ( empty( $num_variations ) ) {
			delete_option( 'optimizely_num_variations' );
		} else {
			update_option( 'optimizely_num_variations', $num_variations );
		}

		if ( empty( $optimizely_post_types ) ) {
			update_option( 'optimizely_post_types', '' );
		} else {
			$post_type_string = '';
			foreach ( $optimizely_post_types as $post_type ) {
				$post_type_string = $post_type_string . $post_type . ',';
			}
			update_option( 'optimizely_post_types', trim( $post_type_string, ',' ) );
		}

		if ( empty( $project_name ) ) {
			delete_option( 'optimizely_project_name' );
		} else {
			update_option( 'optimizely_project_name', $project_name );
		}

		if ( empty( $variation_template ) ) {
			update_option( 'optimizely_variation_template', OPTIMIZELY_DEFAULT_VARIATION_TEMPLATE );
		} else {
			update_option( 'optimizely_variation_template', $variation_template );
		}

		if ( empty( $conditional_activation_code ) ) {
			update_option( 'optimizely_conditional_activation_code', OPTIMIZELY_DEFAULT_CONDITIONAL_TEMPLATE );
		} else {
			update_option( 'optimizely_conditional_activation_code', $conditional_activation_code );
		}

		if ( empty( $activation_mode ) ) {
			delete_option( 'optimizely_activation_mode', 'immediate' );
		} else {
			update_option( 'optimizely_activation_mode', $activation_mode );
		}

		if ( empty( $optimizely_url_targeting ) ) {
			delete_option( 'optimizely_url_targeting', get_site_url() );
		} else {
			update_option( 'optimizely_url_targeting', $optimizely_url_targeting );
		}

		if ( empty( $optimizely_url_targeting_type ) ) {
			delete_option( 'optimizely_url_targeting_type', 'substring' );
		} else {
			update_option( 'optimizely_url_targeting_type', $optimizely_url_targeting_type );
		}

		?>
		<div id="message" class="updated fade"><p><strong><?php esc_html_e( 'Settings saved', 'optimizely' ) ?>.</strong></p></div>
		<?php
	}

	// Display the config form.
	include( dirname( __FILE__ ) . '/config.php' );
}