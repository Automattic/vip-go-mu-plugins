<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice {
	public $message;
	public $conditions;

	/**
	 * Create AdminNotice.
	 *
	 * @param string $message The text to be displayed.
	 * @param Admin_Notice_Condition[] $conditions Conditions to pass in order to show the notice.
	 */
	public function __construct( string $message, array $conditions = [] ) {
		$this->message = $message;
		$this->conditions = $conditions;
	}

	public function display() {
		printf( '<div class="notice notice-info"><p>%s</p></div>', esc_html( $this->message ) );
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
