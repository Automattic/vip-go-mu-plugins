<?php

namespace Automattic\VIP\Utils;

class WPComVIP_Restrictions {
	protected static $instance = null;

	public static function instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'wp_insert_post_data', [ $this, 'restrict_post_author' ], PHP_INT_MAX );
		add_filter( 'wp_insert_attachment_data', [ $this, 'restrict_post_author' ], PHP_INT_MAX );
		add_filter( 'wp_dropdown_users_args', [ $this, 'restrict_post_author_dropdown' ], PHP_INT_MAX, 2 );
		add_filter( 'rest_user_query', [ $this, 'hide_wpcomvip' ], PHP_INT_MAX );
		add_filter( 'coauthors_edit_ignored_authors', [ $this, 'restrict_post_author_dropdown_coauthors' ], PHP_INT_MAX );
	}

	public function restrict_post_author( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$wpcomvip = get_user_by( 'login', 'wpcomvip' );

		if ( false !== $wpcomvip && isset( $data['post_author'] ) && $data['post_author'] == $wpcomvip->ID ) {
			$current_user_id     = get_current_user_id();
			$data['post_author'] = $current_user_id == $wpcomvip->ID ? 0 : $current_user_id;
		}

		return $data;
	}

	public function restrict_post_author_dropdown( $query_args, $parsed_args ) {
		if ( ! is_array( $query_args ) || ! is_array( $parsed_args ) || ! isset( $parsed_args['name'] ) || 'post_author_override' !== $parsed_args['name'] ) {
			return $query_args;
		}

		return $this->exclude_wpcomvip( $query_args );
	}

	public function hide_wpcomvip( $query_args ) {
		if ( ! is_array( $query_args ) ) {
			return $query_args;
		}

		return $this->exclude_wpcomvip( $query_args );
	}

	private function exclude_wpcomvip( array $query_args ): array {
		$wpcomvip = get_user_by( 'login', 'wpcomvip' );
		if ( false !== $wpcomvip ) {
			if ( empty( $query_args['exclude'] ) ) {
				$list = [ $wpcomvip->ID ];
			} else {
				$exclude = is_array( $query_args['exclude'] ) ? $query_args['exclude'] : (string) $query_args['exclude'];
				$list    = wp_parse_id_list( $exclude );
				$list[]  = $wpcomvip->ID;
			}

			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			$query_args['exclude'] = $list;
		}

		return $query_args;
	}

	public function restrict_post_author_dropdown_coauthors( $ignored_authors ) {
		if ( ! is_array( $ignored_authors ) ) {
			$ignored_authors = [ $ignored_authors ];
		}

		$wpcomvip = get_user_by( 'login', 'wpcomvip' );
		if ( false !== $wpcomvip ) {
			$ignored_authors[] = $wpcomvip->user_nicename;
		}
	
		return $ignored_authors;
	}
}
