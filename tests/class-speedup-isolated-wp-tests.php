<?php

use PHPUnit\Event\TestRunner\Started;
use PHPUnit\Event\TestRunner\StartedSubscriber;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\TextUI\Configuration\Configuration;

// phpcs:disable WordPress.NamingConventions.ValidVariableName
// phpcs:disable WordPress.NamingConventions.ValidFunctionName

class SpeedUp_Isolated_WP_Tests {
	public function executeBeforeFirstTest(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		putenv( 'WP_TESTS_SKIP_INSTALL=1' );
	}

	public function bootstrap( Configuration $configuration, Facade $facade ): void {
		$facade->registerSubscriber(new class( $this ) implements StartedSubscriber {
			private SpeedUp_Isolated_WP_Tests $thisClass;

			public function __construct( SpeedUp_Isolated_WP_Tests $thisClass ) {
				$this->thisClass = $thisClass;
			}

			public function notify( Started $event ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				$this->thisClass->executeBeforeFirstTest();
			}
		});
	}
}
