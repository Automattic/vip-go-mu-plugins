<?php
/**
 * A Shortcodes Addon for CampTix
 *
 * Implements a set of shortcodes that make something useful from the
 * CampTix data. Not all shortcodes are ready for production.
 *
 * @since 1.2
 */

class CampTix_Addon_Shortcodes extends CampTix_Addon {

	/**
	 * Runs during camptix_init, @see CampTix_Addon
	 */
	function camptix_init() {
		add_action( 'save_post', array( $this, 'save_post' ) );
		add_action( 'shutdown', array( $this, 'shutdown' ) );
		add_shortcode( 'camptix_attendees', array( $this, 'shortcode_attendees' ) );
		add_shortcode( 'camptix_stats', array( $this, 'shortcode_stats' ) );
		add_shortcode( 'camptix_private', array( $this, 'shortcode_private' ) );
		add_action( 'template_redirect', array( $this, 'shortcode_private_template_redirect' ) );
	}

	function log( $message, $post_id = 0, $data = null, $module = 'shortcode' ) {
		global $camptix;
		return $camptix->log( $message, $post_id, $data, $module );
	}

	/**
	 * Runs when a post is saved.
	 */
	function save_post( $post_id ) {
		// Only real attendee posts.
		if ( wp_is_post_revision( $post_id ) || 'tix_attendee' != get_post_type( $post_id ) )
			return;

		// Only non-draft attendees.
		$post = get_post( $post_id );
		if ( $post->post_status == 'draft' )
			return;

		// Signal to update the last modified time ( see $this->shutdown )
		$this->update_last_modified = true;
	}

	/**
	 * Runs during shutdown, right before php stops execution.
	 */
	function shutdown() {
		global $camptix;

		if ( ! isset( $this->update_last_modified ) || ! $this->update_last_modified )
			return;

		// Bump the last modified time if we've been told to ( see $this->save_post )
		$camptix->update_stats( 'last_modified', time() );
	}

	/**
	 * Callback for the [camptix_attendees] shortcode.
	 */
	function shortcode_attendees( $attr ) {
		global $post, $camptix;

		$attr = shortcode_atts( array(
			'order' => 'ASC',
			'orderby' => 'title',
			'posts_per_page' => 10000,
			'tickets' => false,
			'columns' => 3,
			'questions' => '',
		), $attr, 'camptix_attendees' );

		$camptix_options = $camptix->get_options();

		// Lazy load the camptix js.
		wp_enqueue_script( 'camptix' );

		$start = microtime(true);

		// Serve from cache if cached copy is fresh.
		$transient_key = md5( 'tix-attendees' . serialize( $attr ) );
		if ( false !== ( $cached = get_transient( $transient_key ) ) ) {
			if ( ! is_array( $cached ) )
				return $cached; // back-compat

			// Compare the cached time to the last modified time from stats.
			elseif ( $cached['time'] > $camptix->get_stats( 'last_modified' ) )
				return $cached['content'];
		}

		// Cache for a month if archived or less if active.
		$cache_time = ( $camptix_options['archived'] ) ? DAY_IN_SECONDS * 30 : HOUR_IN_SECONDS;
		$query_args = array();
		ob_start();

		// @todo validate atts here
		if ( ! in_array( strtolower( $attr['order'] ), array( 'asc', 'desc' ) ) )
			$attr['order'] = 'asc';

		if ( ! in_array( strtolower( $attr['orderby'] ), array( 'title', 'date' ) ) )
			$attr['orderby'] = 'title';

		if ( $attr['tickets'] ) {
			$attr['tickets'] = array_map( 'intval', explode( ',', $attr['tickets'] ) );
			if ( count( $attr['tickets'] ) > 0 ) {
				$query_args['meta_query'] = array( array(
					'key' => 'tix_ticket_id',
					'compare' => 'IN',
					'value' => $attr['tickets'],
				) );
			}
		}

		$attr['posts_per_page'] = absint( $attr['posts_per_page'] );

		$questions = $this->get_questions_from_titles( $attr['questions'] );

		$paged = 0;
		$printed = 0;
		do_action( 'camptix_attendees_shortcode_init' );
		?>

		<div id="tix-attendees">
			<ul class="tix-attendee-list tix-columns-<?php echo absint( $attr['columns'] ); ?>">
				<?php
					while ( true && $printed < $attr['posts_per_page'] ) {
						$paged++;
						$attendee_args = apply_filters( 'camptix_attendees_shortcode_query_args', array_merge(
							array(
								'post_type'      => 'tix_attendee',
								'posts_per_page' => 200,
								'post_status'    => array( 'publish', 'pending' ),
								'paged'          => $paged,
								'order'          => $attr['order'],
								'orderby'        => $attr['orderby'],
								'fields'         => 'ids', // ! no post objects
								'cache_results'  => false,
							),
							$query_args
						), $attr );
						$attendees = get_posts( $attendee_args );

						if ( ! is_array( $attendees ) || count( $attendees ) < 1 )
							break; // life saver!

						// Disable object cache for prepared metadata.
						$camptix->filter_post_meta = $camptix->prepare_metadata_for( $attendees );

						foreach ( $attendees as $attendee_id ) {
							$attendee_answers = (array) get_post_meta( $attendee_id, 'tix_questions', true );
							if ( $printed >= $attr['posts_per_page'] )
								break;

							// Skip attendees marked as private.
							$privacy = get_post_meta( $attendee_id, 'tix_privacy', true );
							if ( $privacy == 'private' )
								continue;

							echo '<li>';

							$first = get_post_meta( $attendee_id, 'tix_first_name', true );
							$last = get_post_meta( $attendee_id, 'tix_last_name', true );

							echo get_avatar( get_post_meta( $attendee_id, 'tix_email', true ) );
							?>

							<div class="tix-field tix-attendee-name">
								<?php echo $GLOBALS['camptix']->format_name_string( '<span class="tix-first">%first%</span> <span class="tix-last">%last%</span>', esc_html( $first ), esc_html( $last ) ); ?>
							</div>

							<?php foreach ( $questions as $question ) :
								if ( ! empty ( $attendee_answers[ $question->ID ] ) ) : ?>
									<div class="tix-field tix-<?php echo esc_attr( $question->post_name ); ?>">
										<?php echo esc_html( $attendee_answers[ $question->ID ] ); ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>

							<?php
							do_action( 'camptix_attendees_shortcode_item', $attendee_id );
							echo '</li>';

							// clean_post_cache( $attendee_id );
							// wp_cache_delete( $attendee_id, 'posts');
							// wp_cache_delete( $attendee_id, 'post_meta');
							$printed++;

						} // foreach

						$camptix->filter_post_meta = false; // cleanup
					} // while true
				?>
			</ul>
		</div>
		<br class="tix-clear" />
		<?php
		$this->log( sprintf( __( 'Generated attendees list in %s seconds', 'camptix' ), microtime(true) - $start ) );
		wp_reset_postdata();
		$content = ob_get_contents();
		ob_end_clean();
		set_transient( $transient_key, array( 'content' => $content, 'time' => time() ), $cache_time );
		return $content;
	}

	/**
	 * Get full `tix_question` posts from their corresponding titles
	 *
	 * @param string $titles Pipe-separated list of `tix_question` titles.
	 *
	 * @return array Array of question post objects matched to the given titles.
	 */
	protected function get_questions_from_titles( $titles ) {
		/** @var $camptix CampTix_Plugin */
		global $camptix;

		if ( empty( $titles ) ) {
			return array();
		}

		$slugs         = array_map( 'sanitize_title', explode( '|', $titles ) );
		$all_questions = $camptix->get_all_questions();
		$questions     = array();

		foreach ( $slugs as $slug ) {
			$matched = wp_list_filter( $all_questions, array( 'post_name' => $slug ) );

			if ( ! empty( $matched ) ) {
				$questions = array_merge( $questions, $matched );
			}
		}

		return $questions;
	}

	/**
	 * Callback for the [camptix_attendees] shortcode.
	 */
	function shortcode_stats( $atts ) {
		global $camptix;
		
		return isset( $atts['stat'] ) ? $camptix->get_stats( $atts['stat'] ) : '';
	}

	/**
	 * Executes during template_redirect, watches for the private
	 * shortcode form submission, searches attendees, sets view token cookies.
	 *
	 * @see shortcode_private
	 */
	function shortcode_private_template_redirect() {
		global $camptix;

		// Indicates this function did run, nothing more.
		$this->did_shortcode_private_template_redirect = 1;

		if ( isset( $_POST['tix_private_shortcode_submit'] ) ) {
			$email      = isset( $_POST['tix_email'] )      ? trim( stripslashes( $_POST['tix_email'] ) )      : '';

			// Remove cookies if a previous one was set.
			if ( isset( $_COOKIE['tix_view_token'] ) ) {
				setcookie( 'tix_view_token', '', time() - 60*60, COOKIEPATH, COOKIE_DOMAIN, false );
				unset( $_COOKIE['tix_view_token'] );
			}

			if ( empty( $email ) ) {
				return $camptix->error( __( 'Please enter the e-mail address that was used to register for your ticket.', 'camptix' ) );
			}

			if ( ! is_email( $email ) )
				return $camptix->error( __( 'The e-mail address you have entered does not seem to be valid.', 'camptix' ) );

			$attendees = get_posts( array(
				'posts_per_page' => 50, // sane enough?
				'post_type' => 'tix_attendee',
				'post_status' => 'publish',
				'meta_query' => array(
					array(
						'key' => 'tix_email',
						'value' => $email,
					),
				),
			) );

			if ( $attendees ) {
				$attendee = $attendees[0];

				$view_token = $this->generate_view_token_for_attendee( $attendee->ID );
				setcookie( 'tix_view_token', $view_token, time() + 60*60*48, COOKIEPATH, COOKIE_DOMAIN, false );
				$_COOKIE['tix_view_token'] = $view_token;

				foreach ( $attendees as $attendee ) {
					update_post_meta( $attendee->ID, 'tix_view_token', $view_token );
					$count = get_post_meta( $attendee->ID, 'tix_private_form_submit_count', true );
					if ( ! $count ) $count = 0;
					$count++;
					update_post_meta( $attendee->ID, 'tix_private_form_submit_count', $count );
					add_post_meta( $attendee->ID, 'tix_private_form_submit_entry', $_SERVER );
					add_post_meta( $attendee->ID, 'tix_private_form_submit_ip', @$_SERVER['REMOTE_ADDR'] );
					$this->log( sprintf( 'Viewing private content using %s', @$_SERVER['REMOTE_ADDR'] ), $attendee->ID, $_SERVER );
				}
			} else {
				$this->log( __( 'The information you have entered is incorrect. Please try again.', 'camptix' ) );
			}
		}
	}

	/**
	 * [camptix_private] shortcode callback, depends on the template redirect
	 * part to set cookies, looks for attendee by post by view token, compares
	 * requested ticket ids and shows content or login form.
	 *
	 * @see shortcode_private_template_redirect
	 * @see shortcode_private_login_form
	 * @see shortcode_private_display_content
	 */
	function shortcode_private( $atts, $content ) {
		global $camptix;

		if ( ! isset( $this->did_shortcode_private_template_redirect ) )
			return __( 'An error has occurred.', 'camptix' );

		// Lazy load the camptix js.
		wp_enqueue_script( 'camptix' );

		// Don't cache this page.
		if ( ! defined( 'DONOTCACHEPAGE' ) )
			define( 'DONOTCACHEPAGE', true );

		$args = shortcode_atts( array(
			'ticket_ids' => null,
		), $atts );

		$can_view_content = false;
		$error = false;

		// If we have a view token cookie, we cas use that to search for attendees.
		if ( isset( $_COOKIE['tix_view_token'] ) && ! empty( $_COOKIE['tix_view_token'] ) ) {
			$view_token = $_COOKIE['tix_view_token'];
			$attendees_params = apply_filters( 'camptix_private_attendees_parameters', array(
				'posts_per_page' => 50, // sane?
				'post_type' => 'tix_attendee',
				'post_status' => 'publish',
				'meta_query' => array(
					array(
						'key' => 'tix_view_token',
						'value' => $view_token,
					),
				),
			) );
			$attendees = get_posts( $attendees_params );

			// Having attendees is one piece of the puzzle.
			// Making sure they have the right tickets is the other.
			if ( $attendees ) {
				$attendee = $attendees[0];

				// Let's try and recreate the view token and see if it was generated for this user.
				$expected_view_token = $this->generate_view_token_for_attendee( $attendee->ID );
				if ( $expected_view_token != $view_token ) {
					$camptix->error( __( 'Looks like you logged in from a different computer. Please log in again.', 'camptix' ) );
					$error = true;
				}

				/** @todo: maybe cleanup the nested ifs **/
				if ( ! $error ) {
					if ( $args['ticket_ids'] )
						$args['ticket_ids'] = array_map( 'intval', explode( ',', $args['ticket_ids'] ) );
					else
						$can_view_content = true;

					// If at least one ticket is found, break.
					if ( $args['ticket_ids'] ) {
						foreach ( $attendees as $attendee ) {
							if ( in_array( get_post_meta( $attendee->ID, 'tix_ticket_id', true ), $args['ticket_ids'] ) ) {
								$can_view_content = true;
								break;
							}
						}
					}

					if ( ! $can_view_content && isset( $_POST['tix_private_shortcode_submit'] ) ) {
						$camptix->error( __( 'Sorry, but your ticket does not allow you to view this content.', 'camptix' ) );
					}
				}

			} else {
				 if ( isset( $_POST['tix_private_shortcode_submit'] ) )
					$camptix->error( __( 'Sorry, but your ticket does not allow you to view this content.', 'camptix' ) );
			}
		}

		if ( $can_view_content && $attendee ) {
			if ( isset( $_POST['tix_private_shortcode_submit'] ) )
				$camptix->info( __( 'Success! Enjoy your content!', 'camptix' ) );

			return $this->shortcode_private_display_content( $atts, $content );
		} else {
			if ( ! isset( $_POST['tix_private_shortcode_submit'] ) && ! $error )
				$camptix->notice( __( 'The content on this page is private. Please log in using the form below.', 'camptix' ) );

			return $this->shortcode_private_login_form( $atts, $content );
		}
	}

	/**
	 * [camptix_private] shortcode, displays the login form.
	 */
	function shortcode_private_login_form( $atts, $content ) {
		$email = isset( $_POST['tix_email'] ) ? $_POST['tix_email'] : '';
		ob_start();

		if ( isset( $atts['logged_out_message'] ) )
			echo wpautop( $atts['logged_out_message'] );
		?>
		<div id="tix">
			<?php do_action( 'camptix_notices' ); ?>
			<form method="POST" action="<?php add_query_arg( null, null ); ?>#tix">
				<input type="hidden" name="tix_private_shortcode_submit" value="1" />
				<input type="hidden" name="tix_post_id" value="<?php the_ID(); ?>" />
				<table class="tix-private-form">
					<tr>
						<th class="tix-left" colspan="2"><?php _e( 'Have a ticket? Sign in', 'camptix' ); ?></th>
					</tr>
					<tr>
						<td class="tix-left"><?php _e( 'E-mail', 'camptix' ); ?></td>
						<td class="tix-right"><input name="tix_email" value="<?php echo esc_attr( $email ); ?>" type="text" /></td>
					</tr>
				</table>
				<p class="tix-submit">
					<input type="submit" value="<?php esc_attr_e( 'Login &rarr;', 'camptix' ); ?>">
					<br class="tix-clear">
				</p>
			</form>
		</div>
		<?php

		if ( isset( $atts['logged_out_message_after'] ) ) {
			// support calling a callback function, for situations where advanced HTML, scripting, etc is desired
			if ( $atts['logged_out_message_after'] == 'callback' ) {
				do_action( 'camptix_logged_out_message_after' );
			} else {
				echo wpautop( $atts['logged_out_message_after'] );
			}
		}

		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * [camptix_private] shortcode, this part displays the actual content in a #tix div
	 * with notices.
	 */
	function shortcode_private_display_content( $atts, $content ) {
		ob_start();
		echo '<div id="tix">';
		do_action( 'camptix_notices' );

		echo do_shortcode( $content );

		echo '</div>';
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	function generate_view_token_for_attendee( $attendee_id ) {
		$email = get_post_meta( $attendee_id, 'tix_email', true );
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';

		$view_token = md5( 'tix-view-token-' . strtolower( $email . $ip ) );
		return $view_token;
	}
}

// Register this class as a CampTix Addon.
camptix_register_addon( 'CampTix_Addon_Shortcodes' );