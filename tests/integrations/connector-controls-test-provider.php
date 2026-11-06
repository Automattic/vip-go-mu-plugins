<?php
/**
 * Test fixture: AI Client provider for Connector Controls tests.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Contracts\ProviderInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

/**
 * AI Client test provider. Each subclass declares its own ID and static mocks.
 */
abstract class Connector_Controls_Test_Provider implements ProviderInterface {
	public static function metadata(): ProviderMetadata {
		return ProviderMetadata::fromArray(
			[
				'id'                   => static::ID,
				'name'                 => 'Test provider',
				'type'                 => 'cloud',
				'authenticationMethod' => 'api_key',
			]
		);
	}

	public static function model( string $model_id, ?ModelConfig $model_config = null ): ModelInterface {
		throw new \LogicException( 'This test does not create models.' );
	}

	public static function availability(): ProviderAvailabilityInterface {
		return static::$availability;
	}

	public static function modelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return static::$model_metadata_directory;
	}
}
