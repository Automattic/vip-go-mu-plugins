<?php

class VIP_Go_Jetpack_Test extends WP_UnitTestCase {
    function get_max_queue_size_data() {
        return [
            // Too small
            [
                1,
                10000,
            ],

            // Too big
            [
                10000000,
                100000,
            ],

            // Just right
            [
                10000,
                10000,
            ],

            // Within the range - not modified
            [
                20000,
                20000,
            ],

            // Not set
            [
                null,
                10000,
            ],

            // A string
            [
                'apples',
                10000,
            ],

            // Integer as a string (parses as int and returns it if within range)
            [
                '30000',
                30000,
            ],
        ];
    }

    /**
	 * @dataProvider get_max_queue_size_data
	 */
	public function test__max_queue_size_filter( $input, $expected ) {
		$result = get_option( 'jetpack_site_settings_max_queue_size' );

		$this->assertEquals( $result, $expected );
    }

    function get_max_queue_lag_data() {
        return [
            // Too small
            [
                1,
                7200,
            ],

            // Too big
            [
                10000000,
                21600,
            ],

            // Just right
            [
                7200,
                7200,
            ],

            // Within the range - not modified
            [
                10000,
                10000,
            ],

            // Not set
            [
                null,
                7200,
            ],

            // A string
            [
                'apples',
                7200,
            ],

            // Integer as a string (parses as int and returns it if within range)
            [
                '15000',
                15000,
            ],
        ];
    }

    /**
	 * @dataProvider get_max_queue_lag_data
	 */
	public function test__max_queue_lag_filter( $input, $expected ) {
		$result = get_option( 'jetpack_site_settings_max_queue_lag' );

		$this->assertEquals( $result, $expected );
    }

    public function test__jp_sync_settings_constants_defined() {
        $this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_LOWER_LIMIT' ) );
        $this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_UPPER_LIMIT' ) );

        $this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_LOWER_LIMIT' ) );
        $this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_UPPER_LIMIT' ) );
    }
}
