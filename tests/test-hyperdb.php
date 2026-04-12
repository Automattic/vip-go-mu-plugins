<?php

/**
 * Tests HyperDB integration behavior in the mu-plugins checkout.
 */

class Test_HyperDB extends WP_UnitTestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ex_mysql_ping_uses_a_query_based_health_check(): void {
		if ( ! extension_loaded( 'mysqli' ) ) {
			$this->markTestSkipped( 'The mysqli extension is required for this test.' );
		}

		$db_config_file = tempnam( sys_get_temp_dir(), 'hyperdb-config-' );

		if ( false === $db_config_file ) {
			$this->fail( 'Failed to create a temporary HyperDB config file.' );
		}

		$this->assertNotFalse( file_put_contents( $db_config_file, "<?php\n" ) );

		define( 'DB_CONFIG_FILE', $db_config_file );

		try {
			require_once WPMU_PLUGIN_DIR . '/drop-ins/hyperdb/db.php';

			$hyperdb = new hyperdb();
			$dbh     = $hyperdb->ex_mysql_connect( DB_HOST, DB_USER, DB_PASSWORD, false );

			$this->assertInstanceOf( mysqli::class, $dbh );
			$this->assertTrue( $hyperdb->ex_mysql_select_db( DB_NAME, $dbh ) );
			$this->assertTrue( $hyperdb->ex_mysql_ping( $dbh ) );

			$hyperdb->ex_mysql_close( $dbh );
		} finally {
			if ( file_exists( $db_config_file ) ) {
				unlink( $db_config_file );
			}
		}
	}
}
