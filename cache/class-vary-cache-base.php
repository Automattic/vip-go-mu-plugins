<?php

namespace Automattic\VIP\Cache;

interface Vary_Cache_Base {
	/**
	 * Check if the user is in nocache mode.
	 *
	 * Should only be used after the `init` hook.
	 *
	 * @return boolean
	 */
	public static function is_user_in_nocache();

	/**
	 * Add nocache cookie for the user.
	 *
	 * This bypasses all requests from the VIP Cache.
	 *
	 * @return boolean|WP_Error
	 */
	public static function set_nocache_for_user();

	/**
	 * Clears the nocache cookie for the user.
	 *
	 * Restores caching behaviour for all future requests.
	 *
	 * @return boolean|WP_Error
	 */
	public static function remove_nocache_for_user();

	/**
	 * Convenience function to init the class.
	 *
	 */
	public static function load();

	/**
	 * Convenience function to reset the class.
	 *
	 * Primarily used to unit tests.
	 */
	public static function unload();

	/**
	 * Set request to indicate the request will vary on one or more groups.
	 *
	 * @param  array $groups  One or more groups to vary on.
	 * @return boolean
	 */
	public static function register_groups( array $groups );

	/**
	 * Set request to indicate the request will vary on a group.
	 *
	 * Convenience version of `register_groups`.
	 *
	 * @param  string $group A group to vary on.
	 * @return boolean
	 */
	public static function register_group( string $group );

	/**
	 * Assigns the user to given group and optionally a value for that group. E.g. location=US
	 *
	 * @param  string $group  Group name to vary the request on.
	 * @param  string $value A value for the group.
	 * @return WP_Error|boolean
	 */
	public static function set_group_for_user( $group, $value );

	/**
	 * Checks if the request has a group cookie matching a given group, regardless of segment value.
	 *
	 * @param  string $group Group name.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group( $group );

	/**
	 * Checks if the request has a group cookie matching a given group and segment. e.g. 'dev-group', 'yes'
	 *
	 * @param  string $group Group name.
	 * @param  string $segment Which segment within the group to check.
	 *
	 * @return bool   True on success. False on failure.
	 */
	public static function is_user_in_group_segment( $group, $segment );

	/**
	 * Returns the associated groups for the request.
	 *
	 * @return array  user's group-value pairs
	 */
	public static function get_groups();

	/**
	 * Sets the context of the the group segmentation to be encrypted or not.
	 *
	 * @return WP_Error|null
	 */
	public static function enable_encryption();

	/**
	 * Returns the encryption flag
	 *
	 * @return bool true if encryption is set for this request
	 */
	public static function is_encryption_enabled();

	/**
	 * Parses our nocache and group cookies.
	 */
	public static function parse_cookies();

	/**
	 * Adjust the default cookie expiry.
	 *
	 * @param int $expiry Seconds in the future when the cookie should expire (e.g. MONTH_IN_SECONDS). Must be more than 1 hour.
	 */
	public static function set_cookie_expiry( int $expiry );

	/**
	 * Sends headers (if needed).
	 */
	public static function send_headers();
}
