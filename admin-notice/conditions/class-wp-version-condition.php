<?php

namespace Automattic\VIP\Admin_Notice;

class WP_Version_Condition implements Condition {

	private $minimum_version;
	private $maximum_version;

	/**
	 * __construct
	 *
	 * @param  ?string $minimum_version - version on which condition should start passing (inclussive)
	 * @param  ?string $maximum_version - version on which condition should no longer pass (exclusive)
	 * @return WP_Version_Condition
	 */
	public function __construct( ?string $minimum_version, string $maximum_version = null ) {
		$this->minimum_version = $minimum_version;
		$this->maximum_version = $maximum_version;
	}

	public function evaluate() {
		$current_version = $GLOBALS['wp_version'];

		if ( $this->minimum_version && version_compare( $current_version, $this->minimum_version, '<' ) ) {
			return false;
		}

		if ( $this->maximum_version && version_compare( $current_version, $this->maximum_version, '>=' ) ) {
			return false;
		}

		return true;
	}
}
