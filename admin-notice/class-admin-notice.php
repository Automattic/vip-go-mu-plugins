<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice {
	public $message;
	public $conditions;
	public $dismiss_identifier;

	const COOKIE_DELIMETER = '|';

	/**
	 * Create AdminNotice.
	 *
	 * @param string $message The text to be displayed.
	 * @param Admin_Notice_Condition[] $conditions Conditions to pass in order to show the notice.
	 * @param string $dismiss_identifier if provided the dismiss will become dissmisible
	 */
	public function __construct( string $message, array $conditions = [], string $dismiss_identifier = '' ) {
		$position = strpos( $dismiss_identifier, self::COOKIE_DELIMETER );
		if ( false !== $position ) {
			\trigger_error( esc_html( "Admin Notice dissmiss identifier - $dismiss_identifier can't contain delimeter - " . self::COOKIE_DELIMETER ) );
		}

		$this->message = $message;
		$this->conditions = $conditions;
		$this->dismiss_identifier = $dismiss_identifier ? $dismiss_identifier : '';
	}

	public function display() {
		printf( '<div data-vip-admin-notice="%s" class="notice notice-info vip-notice">', esc_html( $this->dismiss_identifier ) );
		printf( '<p>%s</p>', esc_html( $this->message ) );
		if ( $this->dismiss_identifier ) {
			printf( '<button type="button" class="notice-dismiss vip-notice-dismiss"></button>' );
		}
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
