<?php

/**
 * Plugin Name: Private Sites
 * Plugin URI: https://wpvip.com
 * Description: Add site privacy features
 * Version: 1.0
 * Author: Automattic
 * Author URI: automattic.com
 * License: GPL2
 *
 * @package vip/private-sites
 */

namespace Automattic\VIP\Security;

class Private_Sites {
	private static $instance;

	const FEEDBOT_USER_AGENT = 'wp.com feedbot';

	public static function instance() {
		if ( ! ( static::$instance instanceof Private_Sites ) ) {
			static::$instance = new Private_Sites();
			static::$instance->init();
		}

		return static::$instance;
	}

	public static function has_privacy_restrictions() {
		return self::is_jetpack_private();
	}

	public static function is_jetpack_private() {
		// If constant is defined and is set to `false`, bypass any other logic; site has opted out
		$is_opted_out = defined( 'VIP_JETPACK_IS_PRIVATE' ) && false === constant( 'VIP_JETPACK_IS_PRIVATE' );
		if ( $is_opted_out ) {
			return false;
		}

		$by_constant        = defined( 'VIP_JETPACK_IS_PRIVATE' ) && true === constant( 'VIP_JETPACK_IS_PRIVATE' );
		$by_basic_auth      = defined( 'WPCOM_VIP_BASIC_AUTH' ) && true === constant( 'WPCOM_VIP_BASIC_AUTH' );
		$by_ip_restrictions = defined( 'WPCOM_VIP_IP_ALLOW_LIST' ) && true === constant( 'WPCOM_VIP_IP_ALLOW_LIST' );

		// For now, this is only enabled on sites that have defined the constant
		return $by_constant || $by_basic_auth || $by_ip_restrictions;
	}

	public function init() {
		if ( ! self::is_jetpack_private() ) {
			return;
		}

		add_filter( 'jetpack_active_modules', array( $this, 'filter_jetpack_active_modules' ) );
		add_filter( 'jetpack_get_available_modules', array( $this, 'filter_jetpack_get_available_modules' ) );
		add_filter( 'jetpack_sync_before_enqueue_added_option', array( $this, 'filter_blog_public_option_for_sync' ) );
		add_filter( 'jetpack_sync_before_enqueue_updated_option', array( $this, 'filter_blog_public_option_for_sync' ) );
		add_filter( 'jetpack_sync_before_enqueue_deleted_option', array( $this, 'filter_blog_public_option_for_sync' ) );
		add_filter( 'jetpack_sync_before_send_jetpack_full_sync_options', array( $this, 'filter_blog_public_option_for_full_sync' ), 11 );

		$this->disable_core_feeds();
		$this->block_unnecessary_access();
	}

	/**
	 * Feeds must be disabled in JP Private mode to prevent WP.com from subscribing to the content
	 */
	public function disable_core_feeds() {
		add_action( 'do_feed', array( $this, 'action_do_feed' ), -1 );
		add_action( 'do_feed_rdf', array( $this, 'action_do_feed' ), -1 );
		add_action( 'do_feed_rss', array( $this, 'action_do_feed' ), -1 );
		add_action( 'do_feed_rss2', array( $this, 'action_do_feed' ), -1 );
		add_action( 'do_feed_atom', array( $this, 'action_do_feed' ), -1 );
	}

	/*
	 * Block the entire site for the feedbot
	 *
	 * Blocks the entire site, including custom feeds, to the WP.com reader
	 */
	public function block_unnecessary_access() {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			// Don't block xml-rpc requests for Jetpack's sake
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== stripos( $_SERVER['HTTP_USER_AGENT'], self::FEEDBOT_USER_AGENT ) ) {
			wp_die( 'Feeds are disabled in Jetpack Private Mode', 403 );
		}
	}

	public function action_do_feed() {
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( vip_is_jetpack_request() || ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== stripos( $_SERVER['HTTP_USER_AGENT'], self::FEEDBOT_USER_AGENT ) ) ) {
			wp_die( 'Feeds are disabled in Jetpack Private Mode', 403 );
		}
	}

	public function filter_jetpack_active_modules( $modules ) {
		if ( ! is_array( $modules ) || empty( $modules ) ) {
			return $modules;
		}

		return array_values( array_diff( $modules, [
			'json-api',
			'enhanced-distribution',
			'search',
		] ) );
	}

	public function filter_jetpack_get_available_modules( $modules ) {
		if ( ! is_array( $modules ) || empty( $modules ) ) {
			return $modules;
		}

		unset( $modules['json-api'] );
		unset( $modules['enhanced-distribution'] );

		return $modules;
	}

	/**
	 * Filter the blog_public option when syncing to JP
	 *
	 * @param array $args {
	 *  Sync values.
	 * @type string Option name.
	 * @type mixed Old value.
	 * @type mixed New value.
	 * }
	 *
	 * @return array
	 */
	public function filter_blog_public_option_for_sync( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		if ( 'blog_public' === $args[0] ) {
			$args[2] = '-1';
		}

		return $args;
	}

	/**
	 * Filter the blog_public option when syncing to JP
	 *
	 * @param array $args {
	 *  Sync option values.
	 * @type string Option name.
	 * @type mixed Value.
	 * }
	 *
	 * @return array
	 */
	public function filter_blog_public_option_for_full_sync( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$args['blog_public'] = '-1';

		return $args;
	}
}
