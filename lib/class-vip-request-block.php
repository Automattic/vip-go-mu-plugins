<?php
/**
 * Convenience class to quickly block unwanted clients at origin (not edges) based on a user agent, IP, or a header.
 *
 * 🛑 THIS IS LOADED EARLY, PURE PHP ONLY, CORE IS NOT AVAILABLE YET!!! 🛑
 *
 * Example usage, somewhere in `vip-config/vip-config.php`:
 *
 * To block by IP:
 * VIP_Request_Block::ip( '13.37.13.37' );
 *
 * To block by User Agent:
 * VIP_Request_Block::ua( 'Fuzz Faster U Fool v1.337' );
 *
 * To block by header:
 * VIP_Request_Block::header( 'x-my-header', 'my-header-value' );
 */
class VIP_Request_Block {
	/**
	 * Block a specific IP based either on true-client-ip, falling back to x-forwarded-for
	 *
	 * 🛑 BE CAREFUL: blocking a reverse proxy IP instead of the client's IP will result in legitimate traffic being blocked!!!
	 * 🛑 ALWAYS: use `whois {IP}` to look up the IP before making the changes.
	 *
	 * @param string $value target IP address to be blocked.
	 */
	public static function ip( string $value ) {
		// Don't try to block if the passed value is not a valid IP.
		if ( ! filter_var( $value, FILTER_VALIDATE_IP ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'VIP Request Block: The value passed is not a correct IP address: ' . $value );
			return;
		}

		// This is explicit because we only want to try x-forwarded-for if the true-client-ip is not set.
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		if ( isset( $_SERVER['HTTP_TRUE_CLIENT_IP'] ) && $value === $_SERVER['HTTP_TRUE_CLIENT_IP'] ) {
			return self::block_and_log( $value, 'true-client-ip' );
		}

		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$position = stripos( $_SERVER['HTTP_X_FORWARDED_FOR'], $value );
			if ( false !== $position ) {
				return self::block_and_log( $value, 'x-forwarded-for' );
			}
		}
	}

	/**
	 * Block by exact match of the user agent header
	 *
	 * @param string $user_agent target user agent to be blocked.
	 * @return void
	 */
	public static function ua( string $user_agent ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		if ( $user_agent === $_SERVER['HTTP_USER_AGENT'] ) {
			self::block_and_log( $user_agent, 'user-agent' );
		}
	}

	/**
	 * Block by exact match for an arbitrary header.
	 *
	 * @param string $header HTTP header.
	 * @param string $value header value.
	 * @return void
	 */
	public static function header( string $header, string $value ) {
		// Normalize the header to what PHP exposes in $_SERVER:
		// will catch both x-my-header or HTTP_X_MY_HEADER passed as a $header arg.
		$key = str_replace( '-', '_', strtoupper( $header ) );
		$key = sprintf( 'HTTP_%s', str_ireplace( 'HTTP_', '', $key ) );

		if ( isset( $_SERVER[ $key ] ) && $value === $_SERVER[ $key ] ) {
			self::block_and_log( $value, $header );
		}
	}

	/**
	 * Block the request and error_log for audit purposes.
	 *
	 * @param string $value value of the header for a block.
	 * @param string $criteria header field used for a block.
	 * @return void
	 */
	public static function block_and_log( string $value, string $criteria ) {
		http_response_code( 403 );
		header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );

		fastcgi_finish_request();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( 'VIP Request Block: request was blocked based on "%s" with value of "%s"', $criteria, $value ) );
		exit;
	}
}
