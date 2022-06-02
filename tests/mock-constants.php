<?php

namespace Automattic\Test {
	use InvalidArgumentException;

	abstract class Constant_Mocker {
		private static $constants = [
			'ABSPATH' => '/tmp/wordpress',
		];

		public static function clear(): void {
			self::$constants = [
				'ABSPATH' => '/tmp/wordpress',
			];
		}

		public static function define( string $constant, $value ): void {
			if ( isset( self::$constants[ $constant ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Constant "%s" is already defined', $constant ) );
			}

			self::$constants[ $constant ] = $value;
		}

		public static function defined( string $constant ): bool {
			return isset( self::$constants[ $constant ] );
		}

		public static function constant( string $constant ) {
			if ( ! isset( self::$constants[ $constant ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Constant "%s" is not defined', $constant ) );
			}

			return self::$constants[ $constant ];
		}
	}
}

namespace Automattic\VIP\Security {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}

namespace Automattic\VIP\Utils {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}

namespace Automattic\VIP {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}

namespace Automattic\VIP\Files\Acl {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}

namespace Automattic\VIP\Cache {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}

namespace Automattic\VIP\Feature {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}

namespace Automattic\VIP\Search {
	use Automattic\Test\Constant_Mocker;

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}

	function define( $constant, $value ) {
		return Constant_Mocker::define( $constant, $value );
	}
}

namespace Automattic\VIP\Helpers\WP_CLI_DB {
	use Automattic\Test\Constant_Mocker;

	function define( $constant, $value ) {
		return Constant_Mocker::define( $constant, $value );
	}

	function defined( $constant ) {
		return Constant_Mocker::defined( $constant );
	}

	function constant( $constant ) {
		return Constant_Mocker::constant( $constant );
	}
}
