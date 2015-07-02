<?php
/*
 * Livepress
 * live blog wp-cli commands.
 */
// code by Paul Bearne


if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( class_exists( 'WP_CLI_Command' ) ) {

	$blogging_tools;


	/**
	 * Livepress CLI
	 */
	class Livepress_CLI extends WP_CLI_Command {

		/**
		 * Make a post into a live blogging post
		 *
		 * <Post ID>
		 *  : ID of the post to make into a live blog.
		 *
		 * [--update] : over-ride the confirm prompt
		 *
		 * ## EXAMPLES
		 *
		 *     wp livepress create 1
		 *
		 * @synopsis
		 */
		public function create( $args, $assoc_args = null ) {

			$current_post_id = null;
			if ( ! isset( $args[0] ) ) {
				WP_CLI::error( 'Post ID Missing' );
				return;
			}else {
				$current_post_id = $args[0];
			}
			$blogging_tools = $this->get_blogging_tools();

			if ( true === $blogging_tools->get_post_live_status( $current_post_id ) ) {
				WP_CLI::error( 'Post  with ID: ' . $current_post_id . ' is already a live blog post' );
				return;
			}

			$blogging_tools->set_post_live_status( $current_post_id, true );
			WP_CLI::success( 'Post with ID:' . $current_post_id . ' is now a live blog' );
		}

		/**
		 * Removes live blogging from a post
		 *
		 * <Post ID>
		 *  : ID of the post to remove live blogging from.
		 *
		 *
		 * ## EXAMPLES
		 *
		 *     wp livepress remove 1
		 *
		 * @synopsis
		 */
		public function remove( $args, $assoc_args = null ) {
			$current_post_id = null;
			if ( ! isset( $args[0] ) ) {
				WP_CLI::error( 'Post ID Missing' );
				return;
			}else {
				$current_post_id = $args[0];
			}

			$blogging_tools = $this->get_blogging_tools();

			if ( false === $blogging_tools->get_post_live_status( $current_post_id ) ) {
				WP_CLI::error( 'Post  with ID: ' . $current_post_id . ' is not a live blog post' );
				return;
			}

			// copied from livepress-administration.php / function deactivate_livepress()
			$lp_updater = LivePress_PF_Updates::get_instance();
			// Merge children posts
			$lp_updater->merge_children( $current_post_id );
			// Turn off live
			$blogging_tools->set_post_live_status( $current_post_id, false );

			WP_CLI::success( 'Post with ID:' . $current_post_id . ' is now a NOT a live blog' );

		}


		/**
		 * Converts all currently live blogging posts into static posts
		 *
		 * All the live blogs are copied into the parent post content to form an archive
		 *
		 * *
		 * ## EXAMPLES
		 *
		 *     wp livepress remove_all
		 *
		 * @synopsis
		 */
		public function remove_all() {

			$blogging_tools = $this->get_blogging_tools();
			$live_posts = $blogging_tools->get_all_live_posts();

			foreach ( $live_posts as $posts ){
				$this->remove( array( $posts ) );
			}

		}


		private function get_blogging_tools(){
			if ( null === $this->blogging_tools ){
				$this->blogging_tools = new LivePress_blogging_tools();
			}
			return $this->blogging_tools;
		}
	}

	WP_CLI::add_command( 'livepress', 'Livepress_CLI' );

}