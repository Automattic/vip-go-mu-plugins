<?php

namespace Yoast\PHPUnitPolyfills;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version as PHPUnit_Version;

if ( \class_exists( 'Yoast\PHPUnitPolyfills\Autoload', false ) === false ) {

	/**
	 * Custom autoloader.
	 */
	class Autoload {

		/**
		 * Loads a class.
		 *
		 * @param string $class The name of the class to load.
		 *
		 * @return bool
		 */
		public static function load( $class ) {
			// Only load classes belonging to this library.
			if ( \stripos( $class, 'Yoast\PHPUnitPolyfills' ) !== 0 ) {
				return false;
			}

			switch ( $class ) {
				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertNumericType':
					self::loadAssertNumericType();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\ExpectException':
					self::loadExpectException();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertFileDirectory':
					self::loadAssertFileDirectory();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\ExpectExceptionObject':
					self::loadExpectExceptionObject();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertIsType':
					self::loadAssertIsType();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertStringContains':
					self::loadAssertStringContains();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertEqualsSpecializations':
					self::loadAssertEqualsSpecializations();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException':
					self::loadExpectPHPException();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\ExpectExceptionMessageMatches':
					self::loadExpectExceptionMessageMatches();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertFileEqualsSpecializations':
					self::loadAssertFileEqualsSpecializations();
					return true;

				case 'Yoast\PHPUnitPolyfills\Polyfills\AssertionRenames':
					self::loadAssertionRenames();
					return true;

				case 'Yoast\PHPUnitPolyfills\TestCases\TestCase':
					self::loadTestCase();
					return true;

				case 'Yoast\PHPUnitPolyfills\TestListeners\TestListenerDefaultImplementation':
					self::loadTestListenerDefaultImplementation();
					return true;

				/*
				 * Handles:
				 * - Yoast\PHPUnitPolyfills\Helpers\AssertAttributeHelper
				 * - Yoast\PHPUnitPolyfills\TestCases\XTestCase
				 * - Yoast\PHPUnitPolyfills\TestListeners\TestListenerSnakeCaseMethods
				 */
				default:
					$file = \realpath(
						__DIR__ . \DIRECTORY_SEPARATOR
						. 'src' . \DIRECTORY_SEPARATOR
						. \strtr( \substr( $class, 23 ), '\\', \DIRECTORY_SEPARATOR ) . '.php'
					);

					if ( \file_exists( $file ) === true ) {
						require_once $file;
						return true;
					}
			}

			return false;
		}

		/**
		 * Load the AssertNumericType polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertNumericType() {
			if ( \method_exists( Assert::class, 'assertNan' ) === false ) {
				// PHPUnit < 5.0.0.
				require_once __DIR__ . '/src/Polyfills/AssertNumericType.php';
				return;
			}

			// PHPUnit >= 5.0.0.
			require_once __DIR__ . '/src/Polyfills/AssertNumericType_Empty.php';
		}

		/**
		 * Load the ExpectException polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadExpectException() {
			/*
			 * Alias the PHPUnit 4/5 Exception class used to its PHPUnit >= 6 name.
			 *
			 * This allow for catching the potential exceptions thrown by the methods
			 * polyfilled in a cross-version compatible way.
			 *
			 * {@internal The `class_exists` wrappers are needed to play nice with
			 * PHPUnit bootstrap files of test suites implementing this library
			 * which may be creating cross-version compatibility in a similar manner.}}
			 */
			if ( \class_exists( 'PHPUnit_Framework_Exception' ) === true
				&& \class_exists( 'PHPUnit\Framework\Exception' ) === false
			) {
				\class_alias( 'PHPUnit_Framework_Exception', 'PHPUnit\Framework\Exception' );
			}

			if ( \method_exists( TestCase::class, 'expectException' ) === false ) {
				// PHPUnit < 5.2.0.
				require_once __DIR__ . '/src/Polyfills/ExpectException.php';
				return;
			}

			// PHPUnit >= 5.2.0.
			require_once __DIR__ . '/src/Polyfills/ExpectException_Empty.php';
		}

		/**
		 * Load the AssertFileDirectory polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertFileDirectory() {
			if ( \method_exists( Assert::class, 'assertIsReadable' ) === false ) {
				// PHPUnit < 5.6.0.
				require_once __DIR__ . '/src/Polyfills/AssertFileDirectory.php';
				return;
			}

			// PHPUnit >= 5.6.0.
			require_once __DIR__ . '/src/Polyfills/AssertFileDirectory_Empty.php';
		}

		/**
		 * Load the ExpectExceptionObject polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadExpectExceptionObject() {
			if ( \method_exists( TestCase::class, 'expectExceptionObject' ) === false ) {
				// PHPUnit < 6.4.0.
				require_once __DIR__ . '/src/Polyfills/ExpectExceptionObject.php';
				return;
			}

			// PHPUnit >= 6.4.0.
			require_once __DIR__ . '/src/Polyfills/ExpectExceptionObject_Empty.php';
		}

		/**
		 * Load the AssertIsType polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertIsType() {
			if ( \method_exists( Assert::class, 'assertIsArray' ) === false ) {
				// PHPUnit < 7.5.0.
				require_once __DIR__ . '/src/Polyfills/AssertIsType.php';
				return;
			}

			// PHPUnit >= 7.5.0.
			require_once __DIR__ . '/src/Polyfills/AssertIsType_Empty.php';
		}

		/**
		 * Load the AssertStringContains polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertStringContains() {
			if ( \method_exists( Assert::class, 'assertStringContainsString' ) === false ) {
				// PHPUnit < 7.5.0.
				require_once __DIR__ . '/src/Polyfills/AssertStringContains.php';
				return;
			}

			// PHPUnit >= 7.5.0.
			require_once __DIR__ . '/src/Polyfills/AssertStringContains_Empty.php';
		}

		/**
		 * Load the AssertEqualsSpecializations polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertEqualsSpecializations() {
			if ( \method_exists( Assert::class, 'assertEqualsWithDelta' ) === false ) {
				// PHPUnit < 7.5.0.
				require_once __DIR__ . '/src/Polyfills/AssertEqualsSpecializations.php';
				return;
			}

			// PHPUnit >= 7.5.0.
			require_once __DIR__ . '/src/Polyfills/AssertEqualsSpecializations_Empty.php';
		}

		/**
		 * Load the ExpectPHPException polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * Includes aliasing any PHPUnit native classes needed for this functionality
		 * which aren't available under their namespaced name in PHPUnit 4.x/5.x.
		 *
		 * @return void
		 */
		public static function loadExpectPHPException() {
			/*
			 * Alias the PHPUnit 4/5 Error classes to their PHPUnit >= 6 name.
			 *
			 * {@internal The `class_exists` wrappers are needed to play nice with
			 * PHPUnit bootstrap files of test suites implementing this library
			 * which may be creating cross-version compatibility in a similar manner.}}
			 */
			if ( \class_exists( 'PHPUnit_Framework_Error' ) === true
				&& \class_exists( 'PHPUnit\Framework\Error\Error' ) === false
			) {
				\class_alias( 'PHPUnit_Framework_Error', 'PHPUnit\Framework\Error\Error' );
			}

			if ( \class_exists( 'PHPUnit_Framework_Error_Warning' ) === true
				&& \class_exists( 'PHPUnit\Framework\Error\Warning' ) === false
			) {
				\class_alias( 'PHPUnit_Framework_Error_Warning', 'PHPUnit\Framework\Error\Warning' );
			}

			if ( \class_exists( 'PHPUnit_Framework_Error_Notice' ) === true
				&& \class_exists( 'PHPUnit\Framework\Error\Notice' ) === false
			) {
				\class_alias( 'PHPUnit_Framework_Error_Notice', 'PHPUnit\Framework\Error\Notice' );
			}

			if ( \class_exists( 'PHPUnit_Framework_Error_Deprecated' ) === true
				&& \class_exists( 'PHPUnit\Framework\Error\Deprecated' ) === false
			) {
				\class_alias( 'PHPUnit_Framework_Error_Deprecated', 'PHPUnit\Framework\Error\Deprecated' );
			}

			if ( \method_exists( TestCase::class, 'expectErrorMessage' ) === false ) {
				// PHPUnit < 8.4.0.
				require_once __DIR__ . '/src/Polyfills/ExpectPHPException.php';
				return;
			}

			// PHPUnit >= 8.4.0.
			require_once __DIR__ . '/src/Polyfills/ExpectPHPException_Empty.php';
		}

		/**
		 * Load the ExpectExceptionMessageMatches polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadExpectExceptionMessageMatches() {
			if ( \method_exists( TestCase::class, 'expectExceptionMessageMatches' ) === false ) {
				// PHPUnit < 8.4.0.
				require_once __DIR__ . '/src/Polyfills/ExpectExceptionMessageMatches.php';
				return;
			}

			// PHPUnit >= 8.4.0.
			require_once __DIR__ . '/src/Polyfills/ExpectExceptionMessageMatches_Empty.php';
		}

		/**
		 * Load the AssertFileEqualsSpecializations polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertFileEqualsSpecializations() {
			if ( \method_exists( Assert::class, 'assertFileEqualsIgnoringCase' ) === false ) {
				// PHPUnit < 8.5.0.
				require_once __DIR__ . '/src/Polyfills/AssertFileEqualsSpecializations.php';
				return;
			}

			// PHPUnit >= 8.5.0.
			require_once __DIR__ . '/src/Polyfills/AssertFileEqualsSpecializations_Empty.php';
		}

		/**
		 * Load the AssertionRenames polyfill or an empty trait with the same name
		 * if a PHPUnit version is used which already contains this functionality.
		 *
		 * @return void
		 */
		public static function loadAssertionRenames() {
			if ( \method_exists( Assert::class, 'assertMatchesRegularExpression' ) === false ) {
				// PHPUnit < 9.1.0.
				require_once __DIR__ . '/src/Polyfills/AssertionRenames.php';
				return;
			}

			// PHPUnit >= 9.1.0.
			require_once __DIR__ . '/src/Polyfills/AssertionRenames_Empty.php';
		}

		/**
		 * Load the appropriate TestCase class based on the PHPUnit version being used.
		 *
		 * @return void
		 */
		public static function loadTestCase() {
			if ( \class_exists( PHPUnit_Version::class ) === false
				|| \version_compare( PHPUnit_Version::id(), '8.0.0', '<' )
			) {
				// PHPUnit < 8.0.0.
				require_once __DIR__ . '/src/TestCases/TestCasePHPUnitLte7.php';
				return;
			}

			// PHPUnit >= 8.0.0.
			require_once __DIR__ . '/src/TestCases/TestCasePHPUnitGte8.php';
		}

		/**
		 * Load the appropriate TestListenerDefaultImplementation trait based on the PHPUnit version being used.
		 *
		 * @return void
		 */
		public static function loadTestListenerDefaultImplementation() {
			if ( \class_exists( PHPUnit_Version::class ) === false ) {
				/*
				 * Alias one particular PHPUnit 4/5 class to its PHPUnit >= 6 name.
				 *
				 * All other classes needed are part of the forward-compatibility layer.
				 *
				 * {@internal The `class_exists` wrappers are needed to play nice with
				 * PHPUnit bootstrap files of test suites implementing this library
				 * which may be creating cross-version compatibility in a similar manner.}}
				 */
				if ( \class_exists( 'PHPUnit_Framework_Warning' ) === true
					&& \class_exists( 'PHPUnit\Framework\Warning' ) === false
				) {
					\class_alias( 'PHPUnit_Framework_Warning', 'PHPUnit\Framework\Warning' );
				}

				// PHPUnit < 6.0.0.
				require_once __DIR__ . '/src/TestListeners/TestListenerDefaultImplementationPHPUnitLte5.php';
				return;
			}

			if ( \version_compare( PHPUnit_Version::id(), '7.0.0', '<' ) ) {
				// PHPUnit 6.0.0 < 7.0.0.
				require_once __DIR__ . '/src/TestListeners/TestListenerDefaultImplementationPHPUnit6.php';
				return;
			}

			// PHPUnit >= 7.0.0.
			require_once __DIR__ . '/src/TestListeners/TestListenerDefaultImplementationPHPUnitGte7.php';
		}
	}

	\spl_autoload_register( __NAMESPACE__ . '\Autoload::load' );
}
