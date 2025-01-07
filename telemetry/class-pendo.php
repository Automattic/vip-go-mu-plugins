<?php
/**
 * Telemetry: Pendo class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * This class comprises the mechanics of including Pendo tracking.
 */
class Pendo {

	/**
	 * The suffix for all visitor and account properties.
	 *
	 * @var string
	 */
	protected string $property_suffix;

	/**
	 * @param array<string, mixed> Visitor properties.
	 */
	private array $visitor_properties = [];

	/**
	 * @param array<string, mixed> Visitor properties.
	 */
	private array $account_properties = [];

	/**
	 * Pendo constructor.
	 * 
	 */
	public function __construct() {
		$this->property_suffix    = '_wordpress';
		$this->visitor_properties = Pendo\get_base_properties_of_pendo_user( $this->property_suffix );
		// TODO: Populate with a new constant to get the Salesforce ID of the customer.
		$this->account_properties = [];
	}

	/**
	 * Inserts the Pendo tracking JavaScript code into the page.
	 *
	 * If the event doesn't pass validation, it gets silently discarded.
	 *
	 * @return bool|WP_Error True if recording the event succeeded.
	 *                       False if telemetry is disabled.
	 *                       WP_Error if recording the event failed.
	 */
	public function include_script(): bool|WP_Error {
		if ( $this->is_enabled() ) {
			$this->output_pendo_agent();
			$this->output_pendo_ini();
		}
		return true;
	}
	
	private function is_enabled(): bool {
		if ( [] === $this->visitor_properties || [] === $this->account_properties ) {
			//Don't enable if we can't track an actual visitor or account.
			return false;
		}
		if ( ( false === WPCOM_IS_VIP_ENV || true === WPCOM_SANDBOXED ) ) {
			//Limit tracking to production.
			return false;
		}
		if ( defined( 'VIP_IS_FEDRAMP' ) && true === VIP_DISABLE_PENDO ) {
			//Don't track in FedRAMP environments.
			return false;
		}
		// TODO: Check on a new constant to see if Pendo is disabled by the org.
		return false;
	}
	private function output_pendo_agent(): void {
		wp_enqueue_script( 'vip-pendo-agent-script', plugins_url( '/pendo/pendo-agent.js', __FILE__ ), [ 'common' ], '0.4', true );
	}
	private function output_pendo_ini(): void {
		wp_enqueue_script( 'vip-pendo-ini-script', plugins_url( '/pendo/pendo-ini.js', __FILE__ ), [ 'common' ], '0.4', true );
		$variables = 'const vip_pendo = ' . wp_json_encode( [
			'visitor' => $this->visitor_properties,
			'account' => $this->account_properties,
		] ) . '; ';
		wp_add_inline_script( 'vip-pendo-ini-script', $variables, 'before' );
	}
}
