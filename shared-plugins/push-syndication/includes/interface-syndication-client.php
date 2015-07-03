<?php

interface Syndication_Client {

	/**
	 * Return Client Data
	 * @return array array( 'id' => (string) $transport_name, 'modes' => array( 'push', 'pull' ), 'name' => (string) $name );
	 */
	public static function get_client_data();
	 
	/**
	 * Creates a new post in the slave site.
	 *
	 * @param   int  $post_ID  The post ID to push.
	 *
	 * @return  boolean true on success false on failure.
	 */
	public function new_post( $post_ID );

	/**
	 * Edits an existing post in the slave site.
	 *
	 * @param   int  $post_ID  The post ID to push.
	 * @param   int  $ext_ID   Slave post ID to edit.
	 *
	 * @return  boolean true on success false on failure.
	 */
	public function edit_post( $post_ID, $ext_ID );

	/**
	 * Deletes an existing post in the slave site.
	 *
	 * @param   int  $ext_ID  Slave post ID to delete.
	 *
	 * @return  boolean true on success false on failure.
	 */
	public function delete_post( $ext_ID );

    /**
     * Retrieves a single post from a slave site.
     *
     * @param   int  $ext_ID  Slave post ID to retrieve.
     *
     * @return  boolean true on success false on failure.
     */
    public function get_post( $ext_ID );

    /**
     * Retrieves a list of posts from a slave site.
     *
     * @param   array   $args  Arguments when retrieving posts.
     *
     * @return  boolean true on success false on failure.
     */
    public function get_posts( $args = array() );

	/**
	 * Test the connection with the slave site.
	 *
	 * @return  boolean  true on success false on failure.
	 */
	public function test_connection();

	/**
	 * Checks whether the given post exists in the slave site.
	 *
	 * @param   int  $ext_ID  Slave post ID to check.
	 *
	 * @return  boolean  true on success false on failure.
	 */
	public function is_post_exists( $ext_ID );

	/**
	 * Display the client settings for the slave site.
	 *
	 * @param   object  $site  The site object to display settings.
	 */
	public static function display_settings( $site );

	/**
	 * Save the client settings for the slave site.
	 *
	 * @param   int  $site_ID  The site ID to save settings.
	 *
	 * @return  boolean  true on success false on failure.
	 */
	public static function save_settings( $site_ID );

}