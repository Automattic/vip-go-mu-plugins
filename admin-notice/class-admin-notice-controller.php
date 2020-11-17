<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice_Controller {
	private $all_notices = [];

	const DISMISS_USER_META = 'dismissed_vip_notices';
	const DISMISS_NONCE_ACTION = 'dismiss_notice';
	const DISMISS_IDENTIFIER_KEY = 'identifier';

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
		wp_enqueue_script( 'vip-admin-notice-script', plugins_url( '/js/script.js', __FILE__ ), [], '1.0' );
		wp_localize_script( 'vip-admin-notice-script', 'dismissal_data', [
			'nonce' => wp_create_nonce( self::DISMISS_NONCE_ACTION ),
			'data_attribute' => Admin_Notice::DISMISS_DATA_ATTRIBUTE,
			'identifier_key' => self::DISMISS_IDENTIFIER_KEY,
		] );
	}

	public function dismiss_vip_notice() {
		check_ajax_referer( self::DISMISS_NONCE_ACTION );

		if ( isset( $_POST[ self::DISMISS_IDENTIFIER_KEY ] ) ) {
			update_user_meta( get_current_user_id(), self::DISMISS_USER_META, sanitize_text_field( $_POST[ self::DISMISS_IDENTIFIER_KEY ] ) );
		}
		die();
	}
}
