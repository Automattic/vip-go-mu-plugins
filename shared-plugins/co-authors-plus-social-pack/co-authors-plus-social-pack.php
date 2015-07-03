<?php
/*
Plugin Name: Co-Authors Plus Social Pack
Description: Co-Authors Plus add-on pack with profile fields for popular social media services and the ability to allow attributing posts to their Authors when shared using Jetpack
Version: 0.1
Author: Automattic
Author URI: http://automattic.com
Copyright: 2013 Shared and distributed by Automattic

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class CoAuthors_Plus_Social_Pack {
	public $coauthors_plus;

	/**
	 * Public constructor
	 *
	 * Performs general initialization, such as registering the needed filters
	 */
	function __construct(){
		global $coauthors_plus;

		if ( $coauthors_plus instanceof coauthors_plus ) {
			$this->coauthors_plus = $coauthors_plus;
		} else {
			add_action( 'admin_notices', array( $this, 'action_admin_notices_missing_coauthors_plus' ) );
		}

		add_filter( 'jetpack_sharing_twitter_via', 		array( $this, 'filter_jetpack_sharing_twitter_via' ), 		10, 2 );
		add_filter( 'jetpack_sharing_twitter_related', 	array( $this, 'filter_jetpack_sharing_twitter_related' ), 	10, 2 );

		add_filter( 'coauthors_guest_author_fields', 	array( $this, 'filter_coauthors_guest_author_fields' ), 	10, 2 );

		add_action( 'add_meta_boxes', 					array( $this, 'action_add_meta_boxes' ), 					20, 2 );
	}

	/**
	 * Register the metaboxes used for Guest Authors Social settings
	 */
	function action_add_meta_boxes() {		
		if ( ! $this->coauthors_plus instanceof coauthors_plus || ! $this->coauthors_plus->guest_authors instanceof CoAuthors_Guest_Authors )
			return;

		if ( get_post_type() == $this->coauthors_plus->guest_authors->post_type )
			add_meta_box( 'coauthors-manage-guest-author-social', __( 'Social', 'co-authors-plus' ), array( $this, 'metabox_manage_guest_author_social' ), $this->coauthors_plus->guest_authors->post_type, 'normal', 'default' );
	}

	/**
	 * Notify admin if Co Authors Plus is missing
	 */
	function action_admin_notices_missing_coauthors_plus() {
		?>
			<div class="error">
				<p><?php _e( '<strong>Co Authors Plus Social Pack</strong> requires <strong>Co Authors Plus</strong> to be installed and enabled.' ); ?></p>
			</div>
		<?php
	}

	/**
	 * Hook into Jetpack Sharing's jetpack_sharing_twitter_via filter to alter the ' - via @username' portion of the shared Tweet
	 *
	 * Jetpack Sharing attempts to use the Twitter account in a Publicize connection as the 'via'. If that fails, it falls back
	 * to @wordpressdotcom
	 *
	 * We can do better - this (optionally) will attribute the Tweet to the first co author with a Twitter username and the setting
	 * enabled
	 * 
	 * @param  string $via The Twitter username to label the Tweet as 'from'
	 * @param  int $post_id The post id for the post being shared
	 * @return string The Twitter username to use in 'via' instead
	 */
	public function filter_jetpack_sharing_twitter_via( $via, $post_id ) {
		if ( ! function_exists( 'get_coauthors' ) )
			return $via;

		$coauthors = get_coauthors( $post_id );

		if ( ! is_array( $coauthors ) || empty( $coauthors ) )
			return $via;

		foreach ( $coauthors as $coauthor ) {
			if ( ! isset( $coauthor->twitter ) || empty( $coauthor->twitter ) || ! (int) $coauthor->enable_twitter_via )
				continue;

			return $coauthor->twitter;
		}

		return $via;
	}

	/**
	 * Hook into Jetpack Sharing's Twitter related accounts filter to add all authors as 'related' accounts
	 * when shared to Twitter.
	 *
	 * Once a post is shared to Twitter, Twitter presents the user with a list of Related/Recommended accounts - this filter
	 * gives us the ability to tell Twitter exactly which accounts to suggest - in this case, the post's Authors
	 * 
	 * @param  array $related Array of related Twitter usernames
	 * @param  int $post_id The id of the post being shared
	 * @return array The array of Twitter usernames to suggest as related / recommended
	 */
	public function filter_jetpack_sharing_twitter_related( $related, $post_id ) {
		if ( ! function_exists( 'get_coauthors' ) )
			return $via;

		$coauthors = get_coauthors( $post_id );

		if ( ! is_array( $coauthors ) || empty( $coauthors ) )
			return $related;

		foreach ( $coauthors as $coauthor ) {
			if ( ! isset( $coauthor->twitter ) || empty( $coauthor->twitter ) || ! (int) $coauthor->enable_twitter_related )
				continue;

			$related[ $coauthor->twitter ] = $coauthor->description;
		}

		return $related;
	}

	/**
	 * Hook into Co Authors Plus's filter_coauthors_guest_author_fields to add new fields
	 * to the Guest Author (profile) edit page
	 * 
	 * @param  array $fields_to_return The current Guest Author fields
	 * @param  array $groups           The field groups
	 * @return array                   The filtered array of fields
	 */
	public function filter_coauthors_guest_author_fields( $fields_to_return, $groups ) {
		if ( 'social' === $groups[0] || 'all' === $groups[0] ){
			$fields_to_return['twitter'] = array(
				'key'      			=> 'twitter',
				'label'    			=> __( 'Twitter Username', 'co-authors-plus' ),
				'group'    			=> 'social',
				'sanitize_function'	=> array( $this, 'sanitize_twitter' )
			);

			$sharing_disabled 				= false;
			$requires_jetpack_message 		= '';

			$enable_twitter_via_label 		= __( 'Attribute Twitter Shares to Author', 'co-authors-plus' );
			$enable_twitter_related_label	= __( 'Show Author as \'Related\' in Twitter Shares', 'co-authors-plus' );

			if ( ! class_exists( 'Jetpack' ) ) {
				$sharing_disabled 				= true;
				$requires_jetpack_message 		= __( '(Requires Jetpack)' );

				$enable_twitter_via_label 		.= ' ' . $requires_jetpack_message;
				$enable_twitter_related_label 	.= ' ' . $requires_jetpack_message;
			}

			$fields_to_return['enable_twitter_via'] = array(
				'key'      			=> 'enable_twitter_via',
				'label'    			=>  $enable_twitter_via_label,
				'type'				=> 'checkbox',
				'group'    			=> 'social',
				'disabled'			=> $sharing_disabled,
				'sanitize_function'	=> 'intval'
			);

			$fields_to_return['enable_twitter_related'] = array(
				'key'      			=> 'enable_twitter_related',
				'label'    			=> $enable_twitter_related_label,
				'type'				=> 'checkbox',
				'group'    			=> 'social',
				'disabled'			=> $sharing_disabled,
				'sanitize_function'	=> 'intval'
			);

			$fields_to_return['facebook'] = array(
				'key'      			=> 'facebook',
				'label'    			=> __( 'Facebook', 'co-authors-plus' ),
				'group'    			=> 'social'
			);

			$fields_to_return['google_plus'] = array(
				'key'      			=> 'google_plus',
				'label'    			=> __( 'Google+', 'co-authors-plus' ),
				'group'    			=> 'social'
			);
		}

		return apply_filters( 'coauthors_social_fields', $fields_to_return );
	}

	/**
	 * Metabox for saving or updating a Guest Author Social settings
	 */
	function metabox_manage_guest_author_social() {
		global $post;

		$fields = $this->coauthors_plus->guest_authors->get_guest_author_fields( 'social' );

		echo '<table class="form-table"><tbody>';

		foreach( $fields as $field ) {
			$pm_key 	= $this->coauthors_plus->guest_authors->get_post_meta_key( $field['key'] );
			$value 		= get_post_meta( $post->ID, $pm_key, true );
			$type 		= isset( $field['type'] ) ? $field['type'] : 'text';
			$disabled 	= isset( $field['disabled'] ) ? ( (bool) $field['disabled'] ) : false;

			echo '<tr><th>';
			echo '<label for="' . esc_attr( $pm_key ) . '">' . esc_html( $field['label'] ) . '</label>';
			echo '</th><td>';

			if ( 'checkbox' == $type ) {
				echo '<input type="checkbox" name="' . esc_attr( $pm_key ) . '" value="1" ' . checked( (bool) $value, true, false ) . ' ' . disabled( $disabled, true, false ) . ' />';
			} else {
				echo '<input type="text" name="' . esc_attr( $pm_key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" ' . disabled( $disabled, true, false ) . ' />';
			}
			
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Strip the @ from Twitter usernames for consistency
	 * 
	 * @param  string $twitter_username The Twitter username to sanitize
	 * @return string                   The sanitized Twitter username, with @ removed
	 */
	function sanitize_twitter( $twitter_username ) {
		$twitter_username = sanitize_text_field( $twitter_username );

		return ltrim( $twitter_username, '@' );
	}
}

$coauthors_plus_social_pack;

function coauthors_plus_social_pack_init() {
	global $coauthors_plus_social_pack;

	$coauthors_plus_social_pack = new CoAuthors_Plus_Social_Pack();
}

// Must come in init, so Co Authors Plus is guaranteed to be already instantiated
add_action( 'init', 'coauthors_plus_social_pack_init' );