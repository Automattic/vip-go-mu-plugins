<?php

class SKDieException extends Exception {}
class SKRedirectException extends Exception {}


class PermissionsTest extends WP_UnitTestCase {
	var $user_ids = array();

	function setUp() {
		parent::setUp();

		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
		add_filter( 'wp_redirect', array( $this, 'wp_redirect' ) );

		$this->pluginFile = "scrollkit-wp/scrollkit-wp.php";
		activate_plugin( $this->pluginFile );

		global $scrollkit_wp;
		$this->plugin = $scrollkit_wp;
	}

	function tearDown() {
		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
	}

	function get_wp_die_handler( $handler ) {
		return array( $this, 'wp_die_handler' );
	}

	function wp_die_handler( $message ) {
		throw new SKDieException( $message );
	}

	function wp_redirect() {
		throw new SKRedirectException();
	}

	function testPluginActivates() {
		$this->assertContains( $this->pluginFile, get_option( 'active_plugins', array() ));
	}

	function testSubscriberCantUsePlugin(){
		$subscriber = new WP_User( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$author = new WP_User( $this->factory->user->create( array( 'role' => 'author' ) ) );
		$post_id = $this->factory->post->create( array( 'post_author' => $author->ID, 'post_type' => 'post' ) );

		set_current_user( $subscriber->ID );
		$this->setExpectedException( 'SKDieException' );
		$this->plugin->handle_user_action( 'activate', $post_id);
	}

	function testAuthorCanUsePlugin(){
		$author = new WP_User( $this->factory->user->create( array( 'role' => 'author' ) ) );

		$this->assertEquals(array('author'), $author->roles);
		$post_id = $this->factory->post->create( array( 'post_author' => $author->ID, 'post_type' => 'post' ) );

		wp_set_current_user( $author->ID );
		$this->assertTrue(current_user_can( 'edit_post', $post_id ) );

		$this->setExpectedException( 'SKRedirectException' );
		$this->plugin->handle_user_action( 'activate', $post_id );
	}

	function testAuthorCantEditOthersPosts(){
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_author_id = $this->factory->user->create( array( 'role' => 'author' ) );

		$post_id = $this->factory->post->create( array( 'post_author' => $author_id, 'post_type' => 'post' ) );

		wp_set_current_user( $author_id );
		$this->assertTrue(current_user_can( 'edit_post', $post_id ) );

		wp_set_current_user( $other_author_id );
		$this->assertFalse(current_user_can( 'edit_post', $post_id ) );

		$this->setExpectedException( 'SKDieException' );
		$this->plugin->handle_user_action( 'activate', $post_id );
	}

}
