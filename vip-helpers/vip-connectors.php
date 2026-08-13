<?php

namespace Automattic\VIP\Helpers;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

function update_ai_connectors(): void {
	if ( ! function_exists( 'wp_get_connectors' ) || ! function_exists( 'vip_get_env_var' ) || ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry   = AiClient::defaultRegistry();
	$connectors = array_filter(
		wp_get_connectors(),
		fn ( $connector ) =>
			isset( $connector['authentication']['method'] )
			&& 'api_key' === $connector['authentication']['method']
			&& ! empty( $connector['authentication']['env_var_name'] )
			&& false === getenv( $connector['authentication']['env_var_name'] )
	);

	foreach ( $connectors as $id => $connector ) {
		$env_var_name = $connector['authentication']['env_var_name'];
		$value        = vip_get_env_var( $env_var_name );
		if ( is_string( $value ) && ! empty( $value ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
			putenv( sprintf( '%s=%s', $env_var_name, $value ) );
			$registry->setProviderRequestAuthentication(
				$id,
				new ApiKeyRequestAuthentication( $value )
			);
		}
	}
}

if ( defined( 'ABSPATH' ) ) {
	add_action( 'init', __NAMESPACE__ . '\\update_ai_connectors', 16 );
}
