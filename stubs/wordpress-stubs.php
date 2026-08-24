<?php
/**
 * WordPress stubs.
 *
 * Note: Unable to use the full php-stubs/wordpress-stubs composer package as it interferes with WP_Object_Cache typings.
 * Since this plugin doesn't need many stubs, we just pull what's needed from https://github.com/php-stubs/wordpress-stubs.
 */

/**
 * Adds a callback function to a filter hook.
 *
 * @param string   $hook_name     The name of the filter to add the callback to.
 * @param callable $callback      The callback to be run when the filter is applied.
 * @param int      $priority      Optional. Used to specify the order in which the functions
 *                                associated with a particular filter are executed.
 *                                Lower numbers correspond with earlier execution,
 *                                and functions with the same priority are executed
 *                                in the order in which they were added to the filter. Default 10.
 * @param int      $accepted_args Optional. The number of arguments the function accepts. Default 1.
 * @return true Always returns true.
 */
function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {}

/**
 * Calls the callback functions that have been added to a filter hook.
 *
 * @param string $hook_name The name of the filter hook.
 * @param mixed  $value     The value to filter.
 * @param mixed  ...$args   Additional parameters to pass to the callback functions.
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filters($hook_name, $value, ...$args) {}

/**
 * Determines whether a site is the main site of the current network.
 *
 * @since 3.0.0
 * @since 4.9.0 The `$network_id` parameter was added.
 *
 * @param int $site_id    Optional. Site ID to test. Defaults to current site.
 * @param int $network_id Optional. Network ID of the network to check for.
 *                        Defaults to current network.
 * @return bool True if $site_id is the main site of the network, or if not
 *              running Multisite.
 */
function is_main_site($site_id = \null, $network_id = \null) {}

/**
 * If Multisite is enabled.
 *
 * @since 3.0.0
 *
 * @return bool True if Multisite is enabled, false otherwise.
 */
function is_multisite() {}

/**
 * Returns a comma-separated string or array of functions that have been called to get
 * to the current point in code.
 *
 * @since 3.4.0
 *
 * @see https://core.trac.wordpress.org/ticket/19589
 *
 * @param string $ignore_class Optional. A class to ignore all function calls within - useful
 *                             when you want to just give info about the callee. Default null.
 * @param int    $skip_frames  Optional. A number of stack frames to skip - useful for unwinding
 *                             back to the source of the issue. Default 0.
 * @param bool   $pretty       Optional. Whether you want a comma separated string instead of
 *                             the raw array returned. Default true.
 * @return string|array Either a string containing a reversed comma separated trace or an array
 *                      of individual calls.
 */
function wp_debug_backtrace_summary($ignore_class = \null, $skip_frames = 0, $pretty = \true) {}

/**
 * Converts a number of bytes to the largest unit the bytes will fit into.
 * @since 2.3.0
 * @since 6.0.0 Support for PB, EB, ZB, and YB was added.
 *
 * @param int|string $bytes    Number of bytes. Note max integer size for integers.
 * @param int        $decimals Optional. Precision of number of decimal places. Default 0.
 * @return string|false Number string on success, false on failure.
 */
function size_format($bytes, $decimals = 0) {}

/**
 * Converts float number to format based on the locale.
 *
 * @since 2.3.0
 *
 * @param float $number   The number to convert based on locale.
 * @param int   $decimals Optional. Precision of the number of decimal places. Default 0.
 * @return string Converted number in string format.
 */
function number_format_i18n($number, $decimals = 0) {}

/**
 * Escaping for HTML blocks.
 *
 * @since 2.8.0
 *
 * @param string $text
 * @return string
 */
function esc_html($text) {}

/**
 * Escaping for HTML attributes.
 *
 * @since 2.8.0
 *
 * @param string $text
 * @return string
 */
function esc_attr($text) {}

/**
 * Escapes single quotes, `"`, `<`, `>`, `&`, and fixes line endings.
 *
 * Escapes text strings for echoing in JS. It is intended to be used for inline JS
 * (in a tag attribute, for example `onclick="..."`). Note that the strings have to
 * be in single quotes. The {@see 'js_escape'} filter is also applied here.
 *
 * @since 2.8.0
 *
 * @param string $text The text to be escaped.
 * @return string Escaped text.
 */
function esc_js($text) {}
