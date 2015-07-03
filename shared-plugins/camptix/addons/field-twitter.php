<?php
/**
 * Twitter Field Addon for CampTix
 *
 * There are three main steps to adding some public field for CampTix. The first
 * step is to filter the field types and add your own type. The second step is to
 * add a control action for that type, you can use existing CampTix controls, or
 * define your own. The third and (optional) final step would be to add the field
 * value to your attendees shortcode output.
 */
class CampTix_Addon_Twitter_Field extends CampTix_Addon {

	/**
	 * Runs during camptix_init, see CampTix_Addon
	 */
	function camptix_init() {
		global $camptix;
		add_filter( 'camptix_question_field_types', array( $this, 'question_field_types' ) );
		add_action( 'camptix_attendees_shortcode_init', array( $this, 'attendees_shortcode_init' ) );
		add_action( 'camptix_question_field_twitter', array( $camptix, 'question_field_text' ), 10, 2 );
		add_action( 'camptix_attendees_shortcode_item', array( $this, 'attendees_shortcode_item' ), 10, 1 );
	}

	function question_field_types( $types ) {
		return array_merge( $types, array(
			'twitter' => 'Twitter (public)',
		) );
	}

	function attendees_shortcode_init() {
		global $camptix;
		$this->questions = $camptix->get_all_questions();
	}

	function attendees_shortcode_item( $attendee_id ) {
		foreach ( $this->questions as $question ) {
			if ( get_post_meta( $question->ID, 'tix_type', true ) != 'twitter' )
				continue;

			$answers = (array) get_post_meta( $attendee_id, 'tix_questions', true );
			if ( ! isset( $answers[ $question->ID ] ) )
				continue;

			$value = trim( $answers[ $question->ID ] );
			$matches = array();
			$screen_name = false;

			// We allow "username", "@username" and "http://twitter.com/username" values.
			if ( preg_match( '#^@?([a-z0-9_]+)$#i', $value, $matches ) )
				$screen_name = $matches[1];
			elseif ( preg_match( '#^(https?://)?(www\.)?twitter\.com/(\#!/)?([a-z0-9]+)$#i', $value, $matches ) )
				$screen_name = $matches[4];

			if ( $screen_name ) {
				$url = 'http://twitter.com/' . $screen_name;
				printf( '<a class="tix-field tix-attendee-twitter" href="%s">@%s</a>', esc_url( $url ), esc_html( $screen_name ) );
			}
		}
	}
}

// Register this addon, creates an instance of this class when necessary.
camptix_register_addon( 'CampTix_Addon_Twitter_Field' );