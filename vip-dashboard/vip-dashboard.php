<?php
/*
 * Plugin Name: VIP Dashboard
 * Plugin URI: https://wpvip.com
 * Description: WordPress VIP Go Dashboard
 * Author: Scott Evans, Filipe Varela, Pau Argelaguet
 * Version: 3.0.0
 * Author URI: https://wpvip.com
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

	// Loading components
	require_once 'components/header.php';
	require_once 'components/widget-welcome.php';
	require_once 'components/widget-contact.php';

	// Enable menu for all sites using a VIP and a8c sites.
	add_action( 'admin_menu', 'wpcom_vip_admin_menu', 5 );
	add_action( 'admin_menu', 'wpcom_vip_rename_vip_menu_to_dashboard', 50 );
}
add_action( 'plugins_loaded', 'vip_dashboard_init' );

/**
 * Register master stylesheet
 *
 * @return void
 */
function vip_dashboard_admin_styles() {
	wp_register_style( 'vip-dashboard-style', plugins_url( '/assets/css/style.css', __FILE__ ), [], '1.0' );
	wp_enqueue_style( 'vip-dashboard-style' );
}

/**
 * Register master JavaScript
 *
 * @return void
 */
function vip_dashboard_admin_scripts() {
	wp_register_script( 'vip-dashboard-script', plugins_url( '/assets/js/vip-dashboard.js', __FILE__ ), array( 'jquery' ), '3.0', true );
	wp_enqueue_script( 'vip-dashboard-script' );
}

/**
 * Output the dashboard page, an empty div for React to initialise against
 *
 * @return void
 */
function vip_dashboard_page() {
	?>
	<main role="main">
		<?php render_vip_dashboard_header(); ?>

		<div class="widgets-area">
			<?php render_vip_dashboard_widget_welcome(); ?>
			<?php render_vip_dashboard_widget_contact(); ?>

			<div class="widget">
				<h2 class="widget__title">Block Index</h2>

				<?php
					$es_host    = \Automattic\VIP\Search\Search::instance()->get_current_host();
					$index_name = \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();
					$index_url  = sprintf( '%s/%s', $es_host, $index_name );

					$block_types       = vip_block_index_get_indexed_block_types( $index_name, $index_url );
					$block_type_counts = vip_block_index_get_block_type_counts( $block_types, $index_url );

				?>

				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Block Type' ); ?></th>
							<th><?php esc_html_e( 'Count' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $block_type_counts as $block_type => $count ) { ?>
							<tr>
								<td><code><?php echo esc_html( $block_type ); ?></code></td>
								<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
								<td><button class="button button-primary" onclick="findBlockIndexPosts(<?php echo esc_attr( wp_json_encode( $block_type ) ); ?>)"><?php esc_html_e( 'Find Posts' ); ?></button></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<div class="vip-block-index-results"></div>

				<script>
				function findBlockIndexPosts( blockType ) {
					const apiUrl = <?php echo wp_json_encode( rest_url( 'vip-block-index/v1/block-posts/' ) ); ?> + blockType;

					const result = fetch(apiUrl)
						.then(response => response.json())
						.then(data => {
							const posts = data.map(post => {
								return `<li><a href="${post.post_edit_url}">${post.post_title}</a> (${post.block_count} use${post.block_count === 1 ? '' : 's' })</li>`;
							});

							const html = `<h3 style="margin-top: 1rem">Posts with <code>${blockType}</code>:</h3><ul>${posts.join('')}</ul>`;
							document.querySelector('.vip-block-index-results').innerHTML = html;
						});
				}
				</script>
			</div>
		</div>
	</main>
	<?php
}

function vip_block_index_get_indexed_block_types( $index_name, $index_url ) {
	// Get previously indexed block names from ES mapping
	//
	// We could also query server-side registered blocks with:
	//     \WP_Block_Type_Registry::get_instance()->get_all_registered()
	// However, properties stored in the ES mapping will be more accurate as they include any block types
	// ever indexed, including client-side or left-over blocks that no longer exist in site code.
	// This is because parse_blocks() can identify non-server-side-registered blocks.

	$mapping_url              = sprintf( '%s/_mapping', $index_url );
	$mapping_response         = vip_safe_wp_remote_get( $mapping_url );
	$mapping                  = json_decode( wp_remote_retrieve_body( $mapping_response ), true );
	$indexed_block_properties = $mapping[ $index_name ]['mappings']['properties']['vip_block_index_counts']['properties'] ?? [];

	return array_keys( $indexed_block_properties );
}

function vip_block_index_get_block_type_counts( $block_types, $index_url ) {
	// Create a query to collect aggregate counts for each block type
	$aggs = [];
	foreach ( $block_types as $block_type ) {
		$aggregatrion_field = sprintf( 'vip_block_index_counts.%s', $block_type );

		$aggs[ $block_type ] = [
			'sum' => [
				'field' => $aggregatrion_field,
			],
		];
	}

	// Query for aggregate counts
	$aggregates_query = [
		'profile' => false,
		'aggs'    => $aggs,
		'from'    => 0,
		'size'    => 0,
	];

	$search_url         = sprintf( '%s/_search', $index_url );
	$aggregate_response = wp_remote_post( $search_url, [
		'body'    => wp_json_encode( $aggregates_query ),
		'headers' => [
			'Content-Type' => 'application/json',
		],
	] );

	$aggregate_result        = json_decode( wp_remote_retrieve_body( $aggregate_response ), true );
	$block_type_aggregations = $aggregate_result['aggregations'] ?? [];

	// Flatten aggregation results into associative array and remove 0 values, e.g.
	// [
	//     "core/paragraph" => 21,
	//     "core/media-text": 5,
	//     "core/freeform": 2,
	// }
	$block_type_counts = array_reduce( array_keys( $block_type_aggregations ), function ( $carry, $block_type ) use ( $block_type_aggregations ) {
		$block_count = $block_type_aggregations[ $block_type ]['value'] ?? 0;

		if ( $block_count > 0 ) {
			$carry[ $block_type ] = $block_count;
		}

		return $carry;
	}, []);

	// Sort by block count, highest count to lowest
	arsort( $block_type_counts );

	return $block_type_counts;
}

function vip_block_index_get_block_type_posts( $block_type, $index_url ) {
	$block_count_field = sprintf( 'vip_block_index_counts.%s', $block_type );
	$block_posts_query = [
		'profile' => false,
		'query'   => [
			'bool' => [
				'filter' => [
					[
						'exists' => [
							'field' => $block_count_field,
						],
					],
				],
			],
		],
		'_source' => [ $block_count_field ],
		'sort'    => [
			[ $block_count_field => 'desc' ],
		],
		'from'    => 0,
		'size'    => 1000,
	];

	$search_url           = sprintf( '%s/_search', $index_url );
	$block_posts_response = wp_remote_post( $search_url, [
		'body'    => wp_json_encode( $block_posts_query ),
		'headers' => [
			'Content-Type' => 'application/json',
		],
	] );

	$block_posts_result     = json_decode( wp_remote_retrieve_body( $block_posts_response ), true );
	$block_count_posts_hits = $block_posts_result['hits']['hits'] ?? [];

	$block_posts = array_map( function ( $hit ) use ( $block_type ) {
		$post_id          = $hit['_id'];
		$post             = get_post( $post_id );
		$post_block_count = $hit['_source']['vip_block_index_counts'][ $block_type ] ?? 0;

		return [
			'post_edit_url' => admin_url( sprintf( 'post.php?post=%d&action=edit', $post_id ) ),
			'post_title'    => $post->post_title,
			'block_count'   => $post_block_count,
		];
	}, $block_count_posts_hits );

	return $block_posts;
}

function vip_block_index_register_test_endpoint() {
	register_rest_route( 'vip-block-index/v1', '/block-posts/(?P<blockName>[a-zA-Z0-9-/]+)', [
		'methods'             => 'GET',
		'callback'            => 'vip_block_index_register_get_block_type_posts',
		'permission_callback' => '__return_true',
	] );
}
add_action( 'rest_api_init', 'vip_block_index_register_test_endpoint' );

function vip_block_index_register_get_block_type_posts( WP_REST_Request $request ) {
	$block_name = $request['blockName'];

	$es_host    = \Automattic\VIP\Search\Search::instance()->get_current_host();
	$index_name = \ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();
	$index_url  = sprintf( '%s/%s', $es_host, $index_name );

	return vip_block_index_get_block_type_posts( $block_name, $index_url );
}


/**
 * Support/Contact form handler - sent from React to admin-ajax
 *
 * @return void
 */
function vip_contact_form_handler() {

	if ( ! isset( $_POST['body'], $_POST['subject'], $_GET['_wpnonce'] ) ) {
		$return = array(
			'status'  => 'error',
			'message' => __( 'Please complete all required fields.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'vip-dashboard' ) ) {
		$return = array(
			'status'  => 'error',
			'message' => __( 'Security check failed. Make sure you should be doing this, and try again.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	$vipsupportemailaddy  = 'vip-support@wordpress.com';
	$cc_headers_to_kayako = '';

	$current_user = wp_get_current_user();

	$name  = ( ! empty( $_POST['name'] ) ) ? stripslashes( wp_strip_all_tags( $_POST['name'] ) ) : $current_user->display_name;
	$email = ( ! empty( $_POST['email'] ) ) ? stripslashes( wp_strip_all_tags( $_POST['email'] ) ) : $current_user->user_email;

	if ( ! is_email( $email ) ) {
		$return = array(
			'status'  => 'error',
			'message' => __( 'Please enter a valid email for your ticket.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	$subject  = ( ! empty( $_POST['subject'] ) ) ? stripslashes( wp_strip_all_tags( $_POST['subject'] ) ) : '';
	$priority = ( ! empty( $_POST['priority'] ) ) ? stripslashes( wp_strip_all_tags( $_POST['priority'] ) ) : 'Medium';

	$ccemail       = ( ! empty( $_POST['cc'] ) ) ? stripslashes( wp_strip_all_tags( $_POST['cc'] ) ) : '';
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
			'status'  => 'error',
			'message' => __( 'Please enter a descriptive subject for your ticket.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	if ( '' === $_POST['body'] ) {
		$return = array(
			'status'  => 'error',
			'message' => __( 'Please enter a detailed description of your issue.', 'vip-dashboard' ),
		);
		echo wp_json_encode( $return );
		die();
	}

	if ( 'Emergency' === $priority ) {
		$subject = sprintf( '[%s] %s', $priority, $subject );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$content = stripslashes( $_POST['body'] ) . "\n\n--- Ticket Details --- \n";

	if ( $priority ) {
		$content .= "\nPriority: " . $priority;
	}
	$content .= "\nUser: " . $current_user->user_login . ' | ' . $current_user->display_name;

	// VIP DB.
	$theme    = wp_get_theme();
	$content .= "\nSite Name: " . get_bloginfo( 'name' );
	$content .= "\nSite URLs: " . site_url() . ' | ' . admin_url();
	$content .= "\nTheme: " . get_option( 'stylesheet' ) . ' | ' . $theme->get( 'Name' );

	// send date and time.
	// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- ISO 8601 date includes the TZ info
	$content .= sprintf( "\n\nSent from %s on %s", home_url(), date( 'c', time() ) );

	// Filter from name/email. NOTE - not un-hooking the filter because we die() immediately after wp_mail()
	add_filter( 'wp_mail_from', function () use ( $email ) {
		return $email;
	}, PHP_INT_MAX );

	add_filter( 'wp_mail_from_name', function () use ( $name ) {
		return $name;
	}, PHP_INT_MAX );

	$headers = "From: \"$name\" <$email>\r\n";
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
	if ( wp_mail( $vipsupportemailaddy, $subject, $content, $headers . $cc_headers_to_kayako ) ) {
		$return = array(
			'status'  => 'success',
			'message' => __( 'Your support request is on its way, we will be in touch soon.', 'vip-dashboard' ),
		);

		echo wp_json_encode( $return );
		die();

	} else {
		$manual_link = vip_echo_mailto_vip_hosting( __( 'Please send in a request manually.', 'vip-dashboard' ), false );
		$return      = array(
			'status'  => 'error',
			// translators: 1 - manual email link
			'message' => sprintf( __( 'There was an error sending the support request. %1$s', 'vip-dashboard' ), $manual_link ),
		);

		echo wp_json_encode( $return );
		die();
	}
}
add_action( 'wp_ajax_vip_contact', 'vip_contact_form_handler' );

/**
 * Generate a manual email link if the send fails
 *
 * @param string $linktext the text for the link.
 * @param bool   $echo echo or return.
 * @return string
 */
function vip_echo_mailto_vip_hosting( $linktext = 'Send an email to VIP Hosting.', $echo = true ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables
	$current_user = wp_get_current_user();

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
	$email .= 'IP Address: ' . ( $_SERVER['REMOTE_ADDR'] ?? 'N/A' ) . "\n"; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$email .= 'Server: ' . php_uname( 'n' ) . "\n";
	$email .= 'Browser: ' . ( $_SERVER['HTTP_USER_AGENT'] ?? '-' ) . "\n";  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- OK for text/plain
	$email .= 'Platform: VIP Go';

	$url = add_query_arg( array(
		'subject' => rawurlencode( __( 'Descriptive subject please', 'vip-dashboard' ) ),
		'body'    => rawurlencode( $email ),
	), 'mailto:vip-support@wordpress.com' );

	$html = '<a href="' . esc_url( $url ) . '">' . esc_html( $linktext ) . '</a>';

	if ( $echo ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- properly sanitized above
		echo $html;
	}

	return $html;
	// phpcs:enable
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
	add_filter( 'menu_order', 'wpcom_vip_menu_order' );
}

/**
 * Rename the first (auto-added) entry in the Dashboard. Kinda hacky, but the menu doesn't have any filters
 *
 * @return void
 */
function wpcom_vip_rename_vip_menu_to_dashboard() {
	global $submenu;

	if ( isset( $submenu['vip-dashboard'][0][0] ) ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
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
