<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice_Controller {
	const DISMISS_USER_META      = 'dismissed_vip_notices';
	const DISMISS_NONCE_ACTION   = 'dismiss_notice';
	const DISMISS_IDENTIFIER_KEY = 'identifier';

	public static $stale_dismiss_cleanup_value = 1; // Value to compare <= against rand( 1, 100 ). 1 should result in roughly 1 in 100 chance.

	private $all_notices = [];

	public function add( Admin_Notice $notice ) {
		array_push( $this->all_notices, $notice );
	}

	public function display_notices() {
		$dismissed_notices = get_user_meta( get_current_user_id(), self::DISMISS_USER_META );

		$filtered_notices = array_filter( $this->all_notices, function ( $notice ) use ( $dismissed_notices ) {
			if ( $notice->dismiss_identifier && in_array( $notice->dismiss_identifier, $dismissed_notices ) ) {
				return false;
			}
			return $notice->should_render();
		});

		foreach ( $filtered_notices as $notice ) {
			$notice->display();
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'vip-admin-notice-script', plugins_url( '/js/script.js', __FILE__ ), [], '1.0', true );
		wp_localize_script( 'vip-admin-notice-script', 'dismissal_data', [
			'nonce'          => wp_create_nonce( self::DISMISS_NONCE_ACTION ),
			'data_attribute' => Admin_Notice::DISMISS_DATA_ATTRIBUTE,
			'identifier_key' => self::DISMISS_IDENTIFIER_KEY,
		] );
	}

	public function dismiss_vip_notice() {
		check_ajax_referer( self::DISMISS_NONCE_ACTION );

		if ( isset( $_POST[ self::DISMISS_IDENTIFIER_KEY ] ) ) {
			add_user_meta( get_current_user_id(), self::DISMISS_USER_META, sanitize_text_field( $_POST[ self::DISMISS_IDENTIFIER_KEY ] ) );
		}
		die();
	}

	public function maybe_clean_stale_dismissed_notices() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- rand() is OK here, no need to use slower wp_rand()
		if ( self::$stale_dismiss_cleanup_value >= rand( 1, 100 ) ) {
			$this->clean_stale_dismissed_notices();
		}
	}

	public function clean_stale_dismissed_notices() {
		$dismissed_notices = get_user_meta( get_current_user_id(), self::DISMISS_USER_META );
		if ( ! is_array( $dismissed_notices ) ) {
			return;
		}

		$registered_notice_dismiss_identifiers = [];
		foreach ( $this->all_notices as $registered_notice ) {
			if ( $registered_notice->dismiss_identifier ) {
				$registered_notice_dismiss_identifiers[] = $registered_notice->dismiss_identifier;
			}
		}

		foreach ( $dismissed_notices as $dismissed_notice ) {
			if ( ! in_array( $dismissed_notice, $registered_notice_dismiss_identifiers ) ) {
				// Notice identifier is no longer in registered notices therefore it is safe to remove it
				delete_user_meta( get_current_user_id(), self::DISMISS_USER_META, $dismissed_notice );
			}
		}
	}
}
