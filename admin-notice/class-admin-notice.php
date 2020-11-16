<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice {
	public $message;
	public $conditions;
	public $dismiss_identifier;

	const DISMISS_DATA_ATTRIBUTE = 'data-vip-admin-notice';

	/**
	 * Create AdminNotice.
	 *
	 * @param string $message The text to be displayed.
	 * @param Admin_Notice_Condition[] $conditions Conditions to pass in order to show the notice.
	 * @param string $dismiss_identifier if provided the dismiss will become dissmisible
	 */
	public function __construct( string $message, array $conditions = [], string $dismiss_identifier = '' ) {
		$this->message = $message;
		$this->conditions = $conditions;
		$this->dismiss_identifier = $dismiss_identifier;
	}

	public function display() {
		$notice_class = 'notice notice-info vip-notice';

		if ( $this->dismiss_identifier ) {
			$notice_class .= ' is-dismissible';
		}

		printf( '<div %s="%s" class="%s">', esc_html( self::DISMISS_DATA_ATTRIBUTE ), esc_attr( $this->dismiss_identifier ), esc_attr( $notice_class ) );
		printf( '<p>%s</p>', esc_html( $this->message ) );
		printf( '</div>' );
	}

	/**
	 * Validates if the notice should be rendered based on conditions.
	 *
	 * @return bool
	 */
	public function should_render() : bool {
		foreach ( $this->conditions as $condition ) {
			if ( ! $condition->evaluate() ) {
				return false;
			}
		}

		return true;
	}
}
