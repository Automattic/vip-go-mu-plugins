<?php
/*
 * Plugin Name: VIP Dashboard
 * Plugin URI: http://wpvip.com
 * Description: WordPress VIP Go Dashboard
 * Author: Scott Evans, Filipe Varela
 * Version: 2.1.0
 * Author URI: http://wpvip.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vip-dashboard
 * Domain Path: /languages/
*/

/**
 * Boot the new VIP Dashboard
 *
 * @return void
 */
function vip_dashboard_init() {

	if ( ! is_admin() ) {
		return;
	}

	// Enable menu for all sites using a VIP and a8c sites.
	add_action( 'admin_menu', 'wpcom_vip_admin_menu', 5 );
	add_action( 'admin_menu', 'wpcom_vip_rename_vip_menu_to_dashboard', 50 );

}
add_action( 'plugins_loaded', 'vip_dashboard_init' );

/**
 * Register master stylesheet (compiled via gulp)
 *
 * @return void
 */
function vip_dashboard_admin_styles() {
	wp_register_style( 'vip-dashboard-style', plugins_url( '/assets/css/style.css', __FILE__ ) , '1.0' );
	wp_enqueue_style( 'vip-dashboard-style' );
}

/**
 * Register master JavaScript (compiled via gulp)
 *
 * @return void
 */
function vip_dashboard_admin_scripts() {
	wp_register_script( 'vip-dashboard-script', plugins_url( '/assets/js/vip-dashboard.js', __FILE__ ), array( 'jquery' ), '1.0', true );
	wp_enqueue_script( 'vip-dashboard-script' );
}

/**
 * Output the dashboard page, an empty div for React to initialise against
 *
 * @return void
 */
function vip_dashboard_page() {

	$current_user = wp_get_current_user();
	$name         = $current_user->display_name;
	$email        = $current_user->user_email;
	$ajaxurl      = add_query_arg( array( '_wpnonce' => wp_create_nonce( 'vip-dashboard' ) ), untrailingslashit( admin_url( 'admin-ajax.php' ) ) );
	?>
	<div id="app"
		data-ajaxurl="<?php echo esc_url( $ajaxurl ); ?>"
		data-asseturl="<?php echo esc_attr( plugins_url( '/assets/', __FILE__ ) ); ?>"
		data-email="<?php echo esc_attr( $email ); ?>"
		data-name="<?php echo esc_attr( $name ); ?>"
		data-adminurl="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
	></div>
	<?php
}

/**
 * Support/Contact form handler - sent from React to admin-ajax
 *
 * @return void
 */
function vip_contact_form_handler() {

	if ( ! isset( $_POST['body'], $_POST['subject'], $_GET['_wpnonce'] ) ) {
		$return = array(
			'status' => 'error',
			'message' => __( 'Please complete all required fields.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'vip-dashboard' ) ) {
		$return = array(
			'status' => 'error',
			'message' => __( 'Security check failed. Make sure you should be doing this, and try again.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	$vipsupportemailaddy  = 'vip-support@wordpress.com';
	$cc_headers_to_kayako = '';

	$sendemail    = true;
	$emailsent    = false;
	$current_user = wp_get_current_user();

	$name          = ( ! empty( $_POST['name'] ) ) ? strip_tags( stripslashes( $_POST['name'] ) ) : $current_user->display_name;
	$email         = ( ! empty( $_POST['email'] ) ) ? strip_tags( stripslashes( $_POST['email'] ) ) : $current_user->user_email;

	if ( ! is_email( $email ) ) {
		$return = array(
			'status' => 'error',
			'message' => __( 'Please enter a valid email for your ticket.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	$subject       = ( ! empty( $_POST['subject'] ) ) ? strip_tags( stripslashes( $_POST['subject'] ) ) : '';
	$group         = ( ! empty( $_POST['type'] ) ) ? strip_tags( stripslashes( $_POST['type'] ) ) : 'Technical';
	$priority      = ( ! empty( $_POST['priority'] ) ) ? strip_tags( stripslashes( $_POST['priority'] ) ) : 'Medium';

	$ccemail       = ( ! empty( $_POST['cc'] ) ) ? strip_tags( stripslashes( $_POST['cc'] ) ) : '';
	$temp_ccemails = explode( ',', $ccemail );
	$temp_ccemails = array_filter( array_map( 'trim', $temp_ccemails ) );
	$ccemails      = array();

	if ( ! empty( $temp_ccemails ) ) {
		foreach ( array_values( $temp_ccemails ) as $value ) {
			if ( is_email( $value ) ) {
				$ccemails[] = $value;
			}
		}
	}
	$ccemails = apply_filters( 'vip_contact_form_cc', $ccemails );

	if ( count( $ccemails ) ) {
		$cc_headers_to_kayako .= 'CC: ' . implode( ',', $ccemails ) . "\r\n";
	}

	if ( empty( $subject ) ) {
		$return = array(
			'status' => 'error',
			'message' => __( 'Please enter a descriptive subject for your ticket.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	if ( '' === $_POST['body'] ) {
		$return = array(
			'status' => 'error',
			'message' => __( 'Please enter a detailed description of your issue.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	if ( 'Emergency' === $priority ) {
		$subject = sprintf( '[%s] %s', $priority, $subject );
	}
	$content = stripslashes( $_POST['body'] ) . "\n\n--- Ticket Details --- \n";

	if ( $priority ) {
		$content .= "\nPriority: " . $priority;
	}
	$content .= "\nUser: " . $current_user->user_login . ' | ' . $current_user->display_name;

	// VIP DB.
	$theme = wp_get_theme();
	$content .= "\nSite Name: " . get_bloginfo( 'name' );
	$content .= "\nSite URLs: " . site_url() . ' | ' . admin_url();
	$content .= "\nTheme: " . get_option( 'stylesheet' ) . ' | ' . $theme->get( 'Name' );

	// added for VIPv2.
	$content .= "\nPlatform: VIP Go";

	// send date and time.
	$content .= sprintf( "\n\nSent from %s on %s", home_url(), date( 'c', current_time( 'timestamp', 1 ) ) );

	// Filter from name/email. NOTE - not un-hooking the filter because we die() immediately after wp_mail()
	add_filter( 'wp_mail_from', function() use ( $email ) {
		return $email;
	});

	add_filter( 'wp_mail_from_name', function() use ( $name ) {
		return $name;
	});

	$headers = "From: \"$name\" <$email>\r\n";
	if ( wp_mail( $vipsupportemailaddy, $subject, $content, $headers . $cc_headers_to_kayako ) ) {
		$return = array(
			'status' => 'success',
			'message' => __( 'Your support request is on its way, we will be in touch soon.', 'vip-dashboard' ),
		);

		echo wp_json_encode( $return );
		die();

	} else {
		$manual_link = vip_echo_mailto_vip_hosting( __( 'Please send in a request manually.', 'vip-dashboard' ), false );
		$return = array(
			'status' => 'error',
			'message' => sprintf( __( 'There was an error sending the support request. %1$s', 'vip-dashboard' ),  $manual_link ),
		);

		echo wp_json_encode( $return );
		die();
	}

	die();
}
add_action( 'wp_ajax_vip_contact', 'vip_contact_form_handler' );

/**
 * Generate a manual email link if the send fails
 *
 * @param string $linktext the text for the link.
 * @param bool   $echo echo or return.
 * @return html
 */
function vip_echo_mailto_vip_hosting( $linktext = 'Send an email to VIP Hosting.', $echo = true ) {

	$current_user = get_currentuserinfo();

	$name = '';
	if ( isset( $_POST['name'] ) ) {
		$name = sanitize_text_field( $_POST['name'] );
	} elseif ( isset( $current_user->display_name ) ) {
		$name = $current_user->display_name;
	}

	$useremail = '';
	if ( isset( $_POST['email'] ) && is_email( $_POST['email'] ) ) {
		$useremail = sanitize_email( $_POST['email'] );
	} elseif ( isset( $current_user->user_email ) ) {
		$name = $current_user->user_email;
	}

	$email  = "\n\n--\n";
	$email .= 'Name: ' . $name . "\n";
	$email .= 'Email: ' . $useremail . "\n";
	$email .= 'URL: ' . home_url() . "\n";
	$email .= 'IP Address: ' . $_SERVER['REMOTE_ADDR'] . "\n";
	$email .= 'Server: ' . php_uname( 'n' ) . "\n";
	$email .= 'Browser: ' . $_SERVER['HTTP_USER_AGENT'] . "\n";
	$email .= 'Platform: VIP Go';

	$url = add_query_arg( array( 'subject' => __( 'Descriptive subject please', 'vip-dashboard' ), 'body' => rawurlencode( $email ) ), 'mailto:vip-support@wordpress.com' );

	// $url not escaped on output as email formatting is borked by esc_url:
	// https://core.trac.wordpress.org/ticket/31632
	$html = '<a href="' . $url . '">' . esc_html( $linktext ) . '</a>';

	if ( $echo ) {
		echo $html;
	}

	return $html;
}

/**
 * Create admin menu, enqueue scripts etc
 *
 * @return void
 */
function wpcom_vip_admin_menu() {
	/**
	 * Limit access to the VIP Menu to users with this capability.
	 *
	 * @param string  $vip_page_cap The cap to use; default is `publish_posts`.
	 */
	$vip_page_cap = apply_filters( 'vip_dashboard_page_cap', 'publish_posts' );

	if ( ! current_user_can( $vip_page_cap ) ) {
		return;
	}

	$vip_page_slug = 'vip-dashboard';

	$page = add_menu_page( __( 'VIP Dashboard' ), __( 'VIP' ), $vip_page_cap, $vip_page_slug, 'vip_dashboard_page', 'dashicons-tickets' );

	add_action( 'admin_print_styles-' . $page, 'vip_dashboard_admin_styles' );
	add_action( 'admin_print_scripts-' . $page, 'vip_dashboard_admin_scripts' );

	add_filter( 'custom_menu_order', '__return_true' );
	add_filter( 'menu_order',        'wpcom_vip_menu_order' );
}

/**
 * Rename the first (auto-added) entry in the Dashboard. Kinda hacky, but the menu doesn't have any filters
 *
 * @return void
 */
function wpcom_vip_rename_vip_menu_to_dashboard() {
	global $submenu;

	if ( isset( $submenu['vip-dashboard'][0][0] ) ) {
		$submenu['vip-dashboard'][0][0] = __( 'Dashboard' );
	}
}

/**
 * Set the menu order for the VIP Dashboard
 *
 * @param  array $menu_ord order of menu.
 * @return array
 */
function wpcom_vip_menu_order( $menu_ord ) {

	if ( empty( $menu_ord ) ) {
		return false;
	}

	$vip_order     = array();
	$previous_item = false;

	$vip_dash  = 'vip-dashboard';
	$dash_menu = 'index.php';

	foreach ( $menu_ord as $item ) {
		if ( $dash_menu === $previous_item ) {
			$vip_order[] = $vip_dash;
			$vip_order[] = $item;
			unset( $menu_ord[ $vip_dash ] );
		} elseif ( $item !== $vip_dash ) {
			$vip_order[] = $item;
		}

		$previous_item = $item;
	}

	return $vip_order;
}
