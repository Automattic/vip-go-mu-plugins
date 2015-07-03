<?php
/**
 * Allows event organizers to track which attendees showed up to the event.
 */

class CampTix_Track_Attendance extends CampTix_Addon {
	/**
	 * Init
	 */
	public function camptix_init() {
		// Individual Editing
		add_action( 'camptix_attendee_submitdiv_misc',               array( $this, 'render_attendance_checkbox' ) );
		add_action( 'save_post',                                     array( $this, 'save_attendance_data' ), 10, 2 );

		// Bulk Editing
		add_filter( 'manage_tix_attendee_posts_columns',             array( $this, 'add_custom_columns' ) );
		add_action( 'manage_tix_attendee_posts_custom_column',       array( $this, 'render_custom_columns' ), 10, 2 );
		add_action( 'admin_footer-edit.php',                         array( $this, 'render_client_side_templates' ) );
		add_action( 'wp_ajax_tix_mark_as_attended',                  array( $this, 'bulk_mark_as_attended' ) );

		// Reporting
		add_filter( 'camptix_summary_fields',                        array( $this, 'add_summary_field' ) );
		add_action( 'camptix_summarize_by_attendance',               array( $this, 'summarize_by_attendance' ), 10, 2 );
		add_filter( 'camptix_attendee_report_extra_columns',         array( $this, 'add_extra_report_columns' ) );
		add_filter( 'camptix_attendee_report_column_value_attended', array( $this, 'add_report_value_attended' ), 10, 2 );
	}

	/**
	 * Render the 'Attended the event' checkbox on the Attendee post.
	 *
	 * @param WP_Post $attendee
	 */
	public function render_attendance_checkbox( $attendee ) {
		?>

		<p>
			<input id="tix_attended_<?php esc_attr( $attendee->ID ); ?>" name="tix_attended" type="checkbox" <?php checked( get_post_meta( $attendee->ID, 'tix_attended', true ) ); ?> />
			<label for="tix_attended_<?php esc_attr( $attendee->ID ); ?>"><?php _e( 'Attended the event', 'camptix' ); ?></label>
		</p>

		<?php
	}

	/**
	 * Save the value of the 'Attended the event' checkbox on the Attendee post.
	 *
	 * @param int     $attendee_id
	 * @param WP_Post $attendee
	 */
	public function save_attendance_data( $attendee_id, $attendee ) {
		if ( wp_is_post_revision( $attendee_id ) || 'tix_attendee' != get_post_type( $attendee_id ) ) {
			return;
		}

		$nonce_action = 'update-post_' . $attendee_id;

		if ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $nonce_action ) ) {
			if ( isset( $_POST['tix_attended'] ) && 'on' == $_POST['tix_attended'] ) {
				update_post_meta( $attendee_id, 'tix_attended', true );
			} else {
				delete_post_meta( $attendee_id, 'tix_attended' );
			}
		}
	}

	/**
	 * Add the 'Attended the event' field to the Summarize dropdown.
	 *
	 * @param array $fields
	 * @return array
	 */
	public function add_summary_field( $fields ) {
		$fields['attendance'] = __( 'Attended the event', 'camptix' );

		return $fields;
	}

	/**
	 * Count the number of ticket holders who attended the event.
	 *
	 * @param array   $summary
	 * @param WP_Post $attendee
	 */
	public function summarize_by_attendance( &$summary, $attendee ) {
		/** @var $camptix CampTix_Plugin */
		global $camptix;

		if ( get_post_meta( $attendee->ID, 'tix_attended', true ) ) {
			$camptix->increment_summary( $summary, __( 'Attended', 'camptix' ) );
		}
	}

	/**
	 * Add the 'Attended the event' column to the attendee export
	 *
	 * @param array $extra_columns
	 * @return array
	 */
	public function add_extra_report_columns( $extra_columns ) {
		$extra_columns['attended'] = __( 'Attended the event', 'camptix' );

		return $extra_columns;
	}

	/**
	 * Set the value for the 'Attended the Event' column for the given attendee in the attendee export
	 *
	 * @param string $value
	 * @param WP_Post $attendee
	 * @return string
	 */
	public function add_report_value_attended( $value, $attendee ) {
		return get_post_meta( $attendee->ID, 'tix_attended', true ) ? 'Yes' : 'No';
	}

	/**
	 * Add extra columns to the Attendees screen.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$columns = array_merge( array( 'attended' => __( 'Attended', 'camptix' ) ), $columns );

		return $columns;
	}

	/**
	 * Render custom columns on the Attendees screen.
	 *
	 * @param string $column
	 * @param int $attendee_id
	 */
	public function render_custom_columns( $column, $attendee_id ) {
		switch ( $column ) {
			case 'attended':
				$attendee = get_post( $attendee_id );

				if ( 'publish' != $attendee->post_status ) {
					break;
				}

				if ( get_post_meta( $attendee_id, 'tix_attended', true ) ) {
					_e( 'Attended', 'camptix' );
				} else {
					?>

					<a
						href="#"
						data-attendee-id="<?php echo esc_attr( $attendee_id ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'tix_mark_attended_' . $attendee_id ) ); ?>"
						class="tix-mark-attended button">

						<?php _e( 'Mark as attended', 'camptix' ); ?>
					</a>

					<?php
				}

				break;
		}
	}

	/**
	 * Render the templates used by JavaScript
	 */
	public function render_client_side_templates() {
		if ( 'tix_attendee' != $GLOBALS['typenow'] ) {
			return;
		}

		?>

		<script type="text/html" id="tmpl-tix-attendance-spinner">
			<div class="spinner"></div>
		</script>

		<script type="text/html" id="tmpl-tix-attendance-confirmed">
			<?php _e( 'Attended', 'camptix' ); ?>
		</script>

		<script type="text/html" id="tmpl-tix-mark-as-attended">
			<a
				href="#"
				data-attendee-id="{{data.attendee_id}}"
				data-nonce="{{data.nonce}}"
				class="tix-mark-attended button">
				<?php _e( 'Mark as attended', 'camptix' ); ?>
			</a>
		</script>

		<?php
	}

	/**
	 * AJAX handler to mark a ticket holder as having actually attended the event.
	 */
	public function bulk_mark_as_attended() {
		if ( empty( $_REQUEST['action'] ) || empty( $_REQUEST['attendee_id'] ) || empty( $_REQUEST['nonce'] ) ) {
			wp_send_json_error( array( 'error' => 'Required parameters not set.' ) );
		}

		$attendee_id = absint( $_REQUEST['attendee_id'] );

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'tix_mark_attended_' . $attendee_id ) || ! current_user_can( 'edit_post', $attendee_id ) ) {
			wp_send_json_error( array( 'error' => 'Permission denied.' ) );
		}

		$attendee = get_post( $attendee_id );

		if ( ! is_a( $attendee, 'WP_Post' ) || 'tix_attendee' != $attendee->post_type ) {
			wp_send_json_error( array( 'error' => 'Invalid attendee.' ) );
		}

		update_post_meta( $attendee_id, 'tix_attended', true );
		wp_send_json_success();
	}
}

// Register this class as a CampTix Addon.
camptix_register_addon( 'CampTix_Track_Attendance' );
