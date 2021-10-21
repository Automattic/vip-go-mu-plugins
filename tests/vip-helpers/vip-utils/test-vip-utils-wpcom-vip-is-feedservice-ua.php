<?php

class WPCOM_VIP_Utils_Wpcom_Vip_Is_Feedservice_UA_Test extends WP_UnitTestCase {

	public function wpcom_vip_is_feedservice_ua_data() {
		return [
			[ '', false ],
			[ 'feed', false ],
			[ null, false ],
			[ 'feedburner', true ],
			[ 'feedburner2', true ],
			[ 'feedvalidator', true ],
			[ 'MediafedMetrics', true ],
			[ 'mediafedmetrics', true ],
		];
	}

	/**
	 * @dataProvider wpcom_vip_is_feedservice_ua_data
	 */
	public function test__wpcom_vip_is_feedservice_ua( $agent, $expected ) {
		//phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		$_SERVER['HTTP_USER_AGENT'] = $agent;
		//phpcs:enable

		$this->assertEquals( $expected, wpcom_vip_is_feedservice_ua() );
	}

}
