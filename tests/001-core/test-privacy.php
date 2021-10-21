<?php

namespace Automattic\VIP\Core\Privacy;

use WP_UnitTestCase;

require_once __DIR__ . '/../../001-core/privacy.php';

class Privacy_Policy_Link_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		// Override the default to make testing easier.
		add_filter( 'vip_show_login_privacy_policy', '__return_true' );
	}

	public function test__skip_vip_link_if_customer_link_set() {
		$post = $this->factory->post->create_and_get();
		update_option( 'wp_page_for_privacy_policy', $post->ID );

		// Should show the custom one
		$expected_link = sprintf( '<div><a class="privacy-policy-link" href="%s">%s</a></div>', get_permalink( $post->ID ), get_the_title( $post->ID ) );

		$actual_link = get_the_privacy_policy_link( '<div>', '</div>' );

		$this->assertEquals( $expected_link, $actual_link );
	}

	public function test__skip_vip_link_if_filtered_to_false() {
		add_filter( 'vip_show_login_privacy_policy', '__return_false', PHP_INT_MAX );

		// Should be empty.
		$expected_link = '';

		$actual_link = get_the_privacy_policy_link( '<div>', '</div>' );

		$this->assertEquals( $expected_link, $actual_link );
	}

	public function test__show_vip_link_when_all_else_fails() {
		$expected_link = '<div>Powered by WordPress VIP<br/><a href="https://automattic.com/privacy/">Privacy Policy</a></div>';

		$actual_link = get_the_privacy_policy_link( '<div>', '</div>' );

		$this->assertEquals( $expected_link, $actual_link );
	}
}
