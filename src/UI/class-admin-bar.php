<?php
/**
 * Parsely tweaks to WordPress admin bar
 *
 * @package Parsely
 * @since 3.2.0
 */

declare(strict_types=1);

namespace Parsely\UI;

use WP_Admin_Bar;
use Parsely\Parsely;
use Parsely\Dashboard_Link;

/**
 * Render Parse.ly related buttons in the WordPress administrator top bar.
 *
 * @since 3.2.0
 */
final class Admin_Bar {
	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	private $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Register admin bar buttons.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function run(): void {
		// Priority 201 to load after Core's admin bar secondary groups (200).
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_parsely_stats_button' ), 201 );
	}

	/**
	 * Adds the `Parse.ly Stats` button on the admin bar when the current object is a post or a page.
	 *
	 * @param WP_Admin_Bar $admin_bar WP_Admin_Bar instance, passed by reference.
	 *
	 * @return void
	 */
	public function admin_bar_parsely_stats_button( WP_Admin_Bar $admin_bar ): void {
		$current_object = $GLOBALS['wp_the_query']->get_queried_object();

		if ( empty( $current_object ) || empty( $current_object->post_type ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $current_object->post_type );
		if ( $post_type_object && $post_type_object->show_in_admin_bar && Dashboard_Link::can_show_link( $current_object, $this->parsely ) ) {
			$admin_bar->add_node(
				array(
					'id'    => 'parsely-stats',
					'title' => __( 'Parse.ly Stats', 'wp-parsely' ),
					'href'  => Dashboard_Link::generate_url( $current_object, $this->parsely->get_api_key(), 'wp-page-single', 'admin-bar' ),
				)
			);
		}
	}
}
