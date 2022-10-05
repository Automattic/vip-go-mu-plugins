<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice {
	public $message;
	public $conditions;
	public $dismiss_identifier;
	public $notice_class;

	const DISMISS_DATA_ATTRIBUTE = 'data-vip-admin-notice';
	/**
	 * The list of allowed core supported notices
	 * @see https://developer.wordpress.org/reference/hooks/admin_notices/
	 */
	const ALLOWED_NOTICE_CLASSES = [
		'info',
		'warning',
		'error',
		'success',
	];

	/**
	 * Create AdminNotice.
	 *
	 * @param string $message The text to be displayed.
	 * @param Admin_Notice_Condition[] $conditions Conditions to pass in order to show the notice.
	 * @param string $dismiss_identifier if provided the dismiss will become dissmisible
	 */
	public function __construct( string $message, array $conditions = [], string $dismiss_identifier = '', string $notice_class = 'info' ) {
		$this->message            = $message;
		$this->conditions         = $conditions;
		$this->dismiss_identifier = $dismiss_identifier;
		$this->notice_class       = in_array( $notice_class, self::ALLOWED_NOTICE_CLASSES, true ) ? $notice_class : 'info';
	}

	public function display() {
		$notice_class = sprintf( 'notice notice-%s vip-notice', $this->notice_class );

		if ( $this->dismiss_identifier ) {
			$notice_class .= ' is-dismissible';
		}

		printf( '<div %s="%s" class="%s">', esc_html( self::DISMISS_DATA_ATTRIBUTE ), esc_attr( $this->dismiss_identifier ), esc_attr( $notice_class ) );
		printf( '<p>%s</p>', wp_kses(
			$this->message,
			[
				'a' => [
					'href'   => [],
					'title'  => [],
					'target' => [],
				],
			]
		) );
		printf( '</div>' );
	}

	/**
	 * Validates if the notice should be rendered based on conditions.
	 *
	 * @return bool
	 */
	public function should_render() : bool {
		if ( ! $this->cap_condition_exist() && ! is_super_admin() ) {
			// If there's no Capability_Condition defined, default to not showing the notices for non-super admins
			return false;
		}

		foreach ( $this->conditions as $condition ) {
			if ( ! $condition->evaluate() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a Capability_Condition is explicitly defined
	 */
	public function cap_condition_exist() {
		foreach ( $this->conditions as $condition ) {
			if ( $condition instanceof \Automattic\VIP\Admin_Notice\Capability_Condition ) {
				return true;
			}
		}

		return false;
	}
}
