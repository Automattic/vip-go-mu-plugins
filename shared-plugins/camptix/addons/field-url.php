<?php
/**
 * URL Field Addon for CampTix
 *
 * @see field-twitter.php
 */
class CampTix_Addon_URL_Field extends CampTix_Addon {

	/**
	 * Runs during camptix_init, @see CampTix_Addon
	 */
	function camptix_init() {
		global $camptix;
		add_filter( 'camptix_question_field_types', array( $this, 'question_field_types' ) );
		add_action( 'camptix_attendees_shortcode_init', array( $this, 'attendees_shortcode_init' ) );
		add_action( 'camptix_question_field_url', array( $this, 'question_field_url' ), 10, 2 );
		add_action( 'camptix_attendees_shortcode_item', array( $this, 'attendees_shortcode_item' ), 10, 1 );
	}

	function question_field_types( $types ) {
		return array_merge( $types, array(
			'url' => 'URL (public)',
		) );
	}

	/**
	 * A url input for a question.
	 */
	function question_field_url( $name, $value ) {
		?>
		<input name="<?php echo esc_attr( $name ); ?>" type="url" value="<?php echo esc_attr( $value ); ?>" />
		<?php
	}

	function attendees_shortcode_init() {
		global $camptix;
		$this->questions = $camptix->get_all_questions();
	}

	function attendees_shortcode_item( $attendee_id ) {
		foreach ( $this->questions as $question ) {
			if ( get_post_meta( $question->ID, 'tix_type', true ) != 'url' )
				continue;

			$answers = (array) get_post_meta( $attendee_id, 'tix_questions', true );
			if ( ! isset( $answers[ $question->ID ] ) )
				continue;

			$url = esc_url_raw( trim( $answers[ $question->ID ] ) );
			if ( $url ) {
				$parsed = parse_url( $url );
				$label = $parsed['host'];
				if ( isset( $parsed['path'] ) )
					$label .= untrailingslashit( $parsed['path'] );

				if ( substr( $label, 0, 4 ) == 'www.' )
					$label = substr( $label, 4 );

				printf( '<a class="tix-field tix-attendee-url" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
			}
		}
	}
}

// Register this class as a CampTix Addon.
camptix_register_addon( 'CampTix_Addon_URL_Field' );