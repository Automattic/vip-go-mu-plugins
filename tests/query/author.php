<?php

/**
 * Tests to make sure querying posts based on various author parameters works as expected.
 *
 * @group query
 * @group author
 */
class Tests_Query_Author extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
	}

	function test_author_with_no_posts() {
		add_action( 'pre_get_posts', '_es_wp_query_set_es_to_true' );
		$user_id = self::factory()->user->create( array( 'user_login' => 'user-a' ) );
		$this->go_to( '/author/user-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_author' );
	}
}
