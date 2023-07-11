<?php
/**
 * Base class for Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use InvalidArgumentException;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Disabling due to enums.

/**
 * Enum which represent all possible statuses for the client integration via VIP.
 *
 * These should be in sync with the statuses available on the backend.
 */
abstract class Client_Integration_Status {
	const BLOCKED = 'blocked';
}

/**
 * Enum which represent all possible statuses for the site integration via VIP.
 *
 * These should be in sync with the statuses available on the backend.
 */
abstract class Site_Integration_Status {
	const ENABLED  = 'enabled';
	const DISABLED = 'disabled';
	const BLOCKED  = 'blocked';
}

/**
 * Abstract base class for all integration implementations.
 *
 * @private
 */
abstract class Integration {
	/**
	 * Slug of the integration.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * An optional configuration array for this integration, added during activation.
	 *
	 * @var array
	 */
	private array $customer_config = [];

	/**
	 * Configuration provided by VIP for setting up the integration on platform.
	 *
	 * @var array {
	 *   'client'        => array<string, string>,
	 *   'site'          => array<string, mixed>,
	 *   'network_sites' => array<string, array<string, mixed>>,
	 * }
	 *
	 * @example
	 * array(
	 *  'client'        => array( 'status' => 'blocked' ),
	 *  'site'          => array(
	 *      'status' => 'enabled',
	 *      'config'  => array(),
	 *   ),
	 *  'network_sites' => array (
	 *      1 => array (
	 *          'status' => 'disabled',
	 *          'config'  => array(),
	 *      ),
	 *      2 => array (
	 *          'status' => 'enabled',
	 *          'config'  => array(),
	 *      ),
	 *  )
	 * );
	 */
	private array $vip_config = [];

	/**
	 * A boolean indicating if this integration is activated by customer.
	 *
	 * @var bool
	 */
	protected bool $is_active_by_customer = false;

	/**
	 * Name of the filter which we will be used to pass the config from platform to integration.
	 *
	 * As of now there is no default so each integration will define its own filter in their class.
	 *
	 * @var string
	 */
	protected string $vip_config_filter_name = '';

	/**
	 * Constructor.
	 *
	 * @param string $slug Slug of the integration.
	 */
	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	/**
	 * Activates this integration with an optional configuration value.
	 *
	 * @param array $config An associative array of configuration values for the integration.
	 *
	 * @private
	 */
	public function activate( array $config = [] ): void {
		$this->is_active_by_customer = true;
		$this->customer_config       = $config;
	}

	/**
	 * Returns true if this integration has been activated.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active(): bool {
		if ( $this->is_active_by_customer ) {
			return true;
		}

		return $this->is_active_by_vip();
	}

	/**
	 * Returns true and passed available config if the integration is active by VIP.
	 *
	 * @return bool
	 *
	 * @private
	 */
	public function is_active_by_vip(): bool {
		$vip_config = $this->get_vip_config_from_file();
		if ( ! is_array( $vip_config ) ) {
			return false;
		}

		$this->vip_config = $vip_config;

		// Return false if client is blocked.
		if ( $this->get_value_from_vip_config( 'client', 'status' ) === Client_Integration_Status::BLOCKED ) {
			return false;
		}

		$site_status = $this->get_value_from_vip_config( 'site', 'status' );

		// Return false if site is blocked.
		if ( Site_Integration_Status::BLOCKED === $site_status ) {
			return false;
		}

		// If enabled on network site then set credentials via filter and return true.
		if ( is_multisite() && $this->get_value_from_vip_config( 'network_sites', 'status' ) === Site_Integration_Status::ENABLED ) {
			$have_config = $this->get_value_from_vip_config( 'network_sites', 'config' ) !== '';

			if ( '' !== $this->vip_config_filter_name && $have_config ) {
				add_filter( $this->vip_config_filter_name, function() {
					return $this->get_value_from_vip_config( 'network_sites', 'config' );
				} );
			}

			return true;
		}

		// If enabled on site then set credentials via filter and return true.
		if ( Site_Integration_Status::ENABLED === $site_status ) {
			$have_config = $this->get_value_from_vip_config( 'site', 'config' ) !== '';

			if ( '' !== $this->vip_config_filter_name && $have_config ) {
				add_filter( $this->vip_config_filter_name, function() {
					return $this->get_value_from_vip_config( 'site', 'config' );
				} );
			}

			return true;
		}

		return false;
	}

	/**
	 * Get config provided by VIP from file.
	 *
	 * @return null|mixed
	 *
	 * @private
	 */
	public function get_vip_config_from_file() {
		$config_file_path = ABSPATH . 'config/integrations-config/' . $this->slug . '-config.php';

		if ( ! is_readable( $config_file_path ) ) {
			return null;
		}

		return require_once $config_file_path;
	}

	/**
	 * Get config value based on given type and key.
	 *
	 * @param string $config_type Type of the config whose data is needed i.e. client, site, network-sites etc.
	 * @param string $key Key of the config from which we have to extract the data.
	 *
	 * @return string|array
	 *
	 * @throws InvalidArgumentException Exception if invalid argument is passed.
	 *
	 * @private
	 */
	public function get_value_from_vip_config( string $config_type, string $key ) {
		if ( ! in_array( $config_type, [ 'client', 'site', 'network_sites' ], true ) ) {
			throw new InvalidArgumentException( 'Config type must be one of client, site and network_sites.' );
		}

		if ( ! isset( $this->vip_config[ $config_type ] ) ) {
			return '';
		}

		// Look for key inside client or site config.
		if ( 'network_sites' !== $config_type && isset( $this->vip_config[ $config_type ][ $key ] ) ) {
			return $this->vip_config[ $config_type ][ $key ];
		}

		// Look for key inside network-sites config.
		$blog_id = get_current_blog_id();
		if ( 'network_sites' === $config_type && isset( $this->vip_config[ $config_type ][ $blog_id ] ) ) {
			if ( isset( $this->vip_config[ $config_type ][ $blog_id ][ $key ] ) ) {
				return $this->vip_config[ $config_type ][ $blog_id ][ $key ];
			}
		}

		return '';
	}

	/**
	 * Return the customer configuration for this integration.
	 *
	 * @return array<mixed>
	 *
	 * @private
	 */
	public function get_customer_config(): array {
		return $this->customer_config;
	}

	/**
	 * Get slug of the integration.
	 *
	 * @private
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Abstract base for integration functionality.
	 * Implement custom action and filter calls to load integration here.
	 *
	 * For plugins / integrations that can be added to customer repos, 
	 * the implementation should hook into plugins_loaded and check if 
	 * the plugin is already loaded first.
	 * 
	 * @param array $config Configuration for this integration.
	 *
	 * @private
	 */
	abstract public function load( array $config ): void;
}
