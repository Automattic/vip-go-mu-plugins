<?php

class WPCOM_VIP_Jetpack_Connection_Controls {

	/**
	 * Get the current status of the Jetpack connection.
	 *
	 * @return mixed bool|WP_Error True if JP is properly connected, WP_Error otherwise.
	 */
	public static function jetpack_is_connected() {
		// The Jetpack::is_active() method just checks if there are user/blog tokens in the database.
		if ( ! Jetpack::is_active() || ! Jetpack_Options::get_option( 'id' ) ) {
			return new WP_Error( 'jp-cxn-pilot-not-active', 'Jetpack is not currently active.' );
		}

		$is_vip_connection = WPCOM_VIP_MACHINE_USER_EMAIL === Jetpack::get_master_user_email();
		if ( ! $is_vip_connection ) {
			return new WP_Error( 'jp-cxn-pilot-not-vip-owned', sprintf( 'The connection is not owned by "%s".', WPCOM_VIP_MACHINE_USER_LOGIN ) );
		}

		$is_connected = self::test_jetpack_connection();
		if ( is_wp_error( $is_connected ) ) {
			return $is_connected;
		}

		return true;
	}

	/**
	 * Tests the active connection.
	 *
	 * Does a two-way test to verify that the local site can communicate with remote Jetpack/WP.com servers and that Jetpack/WP.com servers can talk to the local site.
	 * Modified version of https://github.com/Automattic/jetpack/blob/cdfe559613c989875050642189664f2cdafbd651/class.jetpack-cli.php#L120
	 *
	 * @return mixed bool|WP_Error True if test connection succeeded, WP_Error otherwise.
	 */
	private static function test_jetpack_connection() {
		$response = Jetpack_Client::wpcom_json_api_request_as_blog(
			sprintf( '/jetpack-blogs/%d/test-connection', Jetpack_Options::get_option( 'id' ) ),
			Jetpack_Client::WPCOM_JSON_API_VERSION
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'jp-cxn-pilot-test-fail', sprintf( 'Failed to test connection (#%s: %s)', $response->get_error_code(), $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return new WP_Error( 'jp-cxn-pilot-empty-body', 'Failed to test connection (empty response body).' );
		}

		$result = json_decode( $body );
		$is_connected = isset( $result->connected ) ? (bool) $result->connected : false;
		if ( ! $is_connected ) {
			return new WP_Error( 'jp-cxn-pilot-not-connected', 'Connection test failed (WP.com does not think this site is connected or there are authentication or other issues).' );
		}

		return true;
	}
}
