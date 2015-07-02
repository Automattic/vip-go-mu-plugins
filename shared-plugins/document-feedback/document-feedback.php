<?php
/*
Plugin Name: Document Feedback
Plugin URI: http://wordpress.org/extend/plugins/document-feedback/
Description: Close the loop &mdash; get feedback from readers on the documentation you write
Version: 1.3
Author: Daniel Bachhuber, Automattic
Author URI: http://automattic.com/
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( !class_exists( 'Document_Feedback' ) ) {

class Document_Feedback {

	private $data;

	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Document_Feedback;
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	public function __clone() {
		wp_die( __( 'Cheatin’ uh?' ) );
	}

	public function __wakeup() {
		wp_die( __( 'Cheatin’ uh?' ) );
	}

	public function __isset( $key ) {
		return isset( $this->data[$key] );
	}

	public function __get( $key ) {
		return isset( $this->data[$key] ) ? $this->data[$key] : null;
	}

	public function __set( $key, $value ) {
		$this->data[$key] = $value;
	}

	private function __construct() {
		/** Do nothing **/
	}

	/**
	 * Setup actions for the plugin
	 *
	 * @since 1.0
	 */
	private function setup_actions() {

		add_action( 'init',                                        array( $this, 'action_init_initialize_plugin' ) );
		add_action( 'admin_init',                                  array( $this, 'action_admin_init_add_meta_box' ) );
		add_action( 'wp_enqueue_scripts',                          array( $this, 'action_wp_enqueue_scripts_add_jquery' ) );
		add_action( 'admin_enqueue_scripts',                          array( $this, 'action_admin_enqueue_scripts_add_scripts' ) );
		add_action( 'wp_head',                                     array( $this, 'ensure_ajaxurl' ), 11 );
		add_action( 'wp_ajax_document_feedback_form_submission',   array( $this, 'action_wp_ajax_handle_form_submission' ) );
		add_action( 'document_feedback_submitted',                 array( $this, 'set_throttle_transient' ), 10, 2 );
		add_action( 'document_feedback_submitted',                 array( $this, 'send_notification' ), 10, 2 );
		add_filter( 'the_content',                                 array( $this, 'filter_the_content_append_feedback_form' ) );
	}

	/**
	 * Initialize all of the plugin components
	 * Other plugins can register filters to modify how the plugin runs
	 *
	 * @since 1.0
	 */
	function action_init_initialize_plugin() {

		load_plugin_textdomain( 'document-feedback', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Set up all of our plugin options but they can only be modified by filter
		$this->options = array(
				'send_notification'        => true, // Send an email to the author and contributors
				'throttle_limit'           => 3600, // How often (seconds) a user can submit a feedback
				'transient_prefix'		   => 'document_feedback_', // format is prefix . user_id . post_id
			);
		$this->options = apply_filters( 'document_feedback_options', $this->options );
		
		// Prepare the strings used in the plugin
		$this->strings = array(
				'prompt'          => __( "Did this document answer your question?", 'document-feedback' ),
				'accept'          => __( "Yes", 'document-feedback' ),
				'decline'         => __( "No", 'document-feedback' ),
				'prompt_response' => __( "Thanks for responding.", 'document-feedback' ),
				'accept_prompt'   => __( "What details were useful to you?", 'document-feedback' ),
				'decline_prompt'  => __( "What details are you still looking for?", 'document-feedback' ),
				'final_response'  => __( "Thanks for the feedback! We'll use it to improve our documentation.", 'document-feedback' ),
			);
		$this->strings = apply_filters( 'document_feedback_strings', $this->strings );

		// Establish the post types to request feedback on
		$this->post_types = array(
				'page',
			);
		$this->post_types = apply_filters( 'document_feedback_post_types', $this->post_types );
	}

	/**
	 * Hooks and such only to run in the admin
	 *
	 * @since 1.0
	 */
	function action_admin_init_add_meta_box() {

		foreach ( $this->post_types as $post_type ) {
			add_meta_box( 'document-feedback', __( 'Document Feedback', 'document-feedback'), array( $this, 'post_meta_box'), $post_type, 'advanced', 'high');
		}
		
	}

	/**
	 * Add jQuery on relevant pages because we need it
	 *
	 * @since 1.0
	 */
	 function action_wp_enqueue_scripts_add_jquery() {
	 	global $post;
	 	if ( is_singular() && in_array( $post->post_type, $this->post_types ) && is_user_logged_in() )
			wp_enqueue_script( 'jquery' );
	 }
	 /**
	  * Add jQuery admin scripts for pie charts
	  * 
	  * @since 1.0
	  */
	 function action_admin_enqueue_scripts_add_scripts( $hook ) {
	 	if( 'post.php' === $hook ) {
		 	// Load pie chart related scripts
	 		wp_enqueue_script( 'jquery.sparkline', plugins_url( '/js/jquery.sparkline.min.js', __FILE__ ),
	 			array( 'jquery' ), '1.0', true );
	 		
		 	// Custom Document Feedback JS for pies
		 	wp_enqueue_style( 'document-feedback', plugins_url( '/css/document-feedback-admin.css', __FILE__ ) );
	 	}
	 }

	/**
	 * Ensure there's an 'ajaxurl' var for us to reference on the frontend
	 * 
	 * @since 1.0
	 */
	function ensure_ajaxurl() {

		if ( is_admin() || !is_user_logged_in() )
			return;
		
		// Accommodate mapped domains
		if ( home_url() != site_url() )
			$ajaxurl = home_url( '/wp-admin/admin-ajax.php' );
		else
			$ajaxurl = admin_url( 'admin-ajax.php' );

		?>
		<script type="text/javascript">
		//<![CDATA[
		var ajaxurl = '<?php echo esc_js( $ajaxurl ); ?>';
		//]]>
		</script>
		<?php
	}

	/**
	 * Add a post meta box summarizing the feedback given on a document
	 *
	 * @since 1.0
	 */
	function post_meta_box( $post ) {
		$post_id = $post->ID;

		// Get feedback
		$feedback_comments = $this->get_feedback_comments( $post_id ); 
		
		if( 0 < count( $feedback_comments ) ) {

			// Get an array with the count of accept and decline feedback comments
			$feedback_stats = $this->get_feedback_stats( $feedback_comments );
			?>
			<script type="text/javascript">
				jQuery(document).ready( function() {
					// Get feedback results
					var accept = <?php echo esc_js( $feedback_stats['accept'] ); ?>;
					var decline = <?php echo esc_js( $feedback_stats['decline'] ); ?>;
					var feedback_stats = [ accept, decline ];
		
					// Define pie attributes
					var pie_options = {
							type: 'pie',
							sliceColors: ['#009344', '#B63733'],
							width: '230px',
							height: '230px'
					}
	
					// Create the pie
					jQuery('#document-feedback-chart').sparkline( feedback_stats, pie_options );
				} );
			</script>
			<div id="document-feedback-metabox">
				<div class="left">
					<h4>"<?php echo esc_html( $this->strings['prompt'] ); ?>"</h4>
					<div id="document-feedback-chart"></div>
					<div id="document-feedback-legend">
						<div id="document-feedback-legend-accept" class="left"><?php echo esc_html( $this->strings['accept'] ); ?></div>
						<div id="document-feedback-legend-decline" class="right"><?php echo esc_html( $this->strings['decline'] ); ?></div>
					</div>
				</div>
				<div class="right">
					<div id="document-feedback-comment-wrapper">
					<?php 
						$feedback_count = count( $feedback_comments ); 
						for( $i = 0; $i < $feedback_count; $i++ ) { 
							global $comment;
							$comment = $feedback_comments[ $i ];

							if ( empty( $comment->comment_content ) )
								continue;

							?>
						<article class="comment">
							<footer class="comment-meta">
								<div class="comment-author vcard">
								<?php 
									printf( __( '%1$s on %2$s <span class="says">said:</span>', 'document-feedback' ),
										sprintf( '<span class="fn">%s</span>', $comment->comment_author ),
										sprintf( '<time pubdate datetime="%1$s">%2$s</time>',
											get_comment_time( 'c' ),
											/* translators: 1: date, 2: time */
											sprintf( __( '%1$s at %2$s', 'document-feedback' ), get_comment_date(), get_comment_time() )
										)
									);
								?>	
								</div>
							</footer>
				
							<div class="comment-content <?php echo esc_attr( $comment->comment_approved ); ?>">
								<p><?php echo esc_html( $comment->comment_content ); ?></p>
							</div>
						</article>
					<?php 
						unset( $comment );
					} ?>
					</div>
				</div>
			</div>
			<?php
		} else { ?>
			<p><?php _e( 'No feedback has been submitted yet.', 'document-feedback' ); ?></p>
		<?php 
		}
	}
	
	/**
	 * Fetch feedback from the comments table
	 * 
	 * @param int $post_id the post ID for the comments query
	 * 
	 * @since 1.0
	 * 
	 */
	function get_feedback_comments( $post_id ) {

		$comment_args = array(
				'post_id' => $post_id,
				'type'    => 'document-feedback',
				'order'   => 'DESC',
		);
		
		// Fetch the comments with the correct status as a filter to the where clause
  		add_filter( 'comments_clauses', array( $this, 'filter_feedback_comments_clauses' ), 10, 2 );
		$feedback_comments = get_comments( $comment_args );
  		remove_filter( 'comments_clauses', array( $this, 'filter_feedback_comments_clauses' ) );
		
 		return $feedback_comments;
	}
	
	/**
	 * Count the accept and decline feedback
	 * 
	 * @param comments $feedback_comments an array with the comment objects
	 * 
	 * @return array accept and decline comments
	 * 
	 * @since 1.0
	 * 
	 * @todo looping feedback to save two SQL count queries, optimize if needed (run 2 count queries and one select with limit)
	 * 
	 */
	function get_feedback_stats( $feedback_comments ) {
		$accept = 0;
		$decline = 0;

		// Count feedback
		foreach( $feedback_comments as $comment ) {
			if( $comment->comment_approved == 'df-accept' ) {
				$accept++;
			} else if( $comment->comment_approved == 'df-decline' ) {
				$decline++;
			}	
		}
		
		// Array to return with stats
		$feedback_stats = array(
			'accept' => $accept,
			'decline' => $decline,	
		);

		return $feedback_stats;	
	}

	/**
	 * Handle a Document Feedback form submission
	 *
	 * @since 1.0
	 */
	function action_wp_ajax_handle_form_submission() {

		// User must be logged in for all actions
		if ( ! is_user_logged_in() )
			$this->do_ajax_response( 'error', array( 'message' => __( 'You need to be logged in to submit feedback.', 'document-feedback' ) ) );

		// Nonce check
		if ( ! wp_verify_nonce( $_POST['nonce'], 'document-feedback' ) )
			$this->do_ajax_response( 'error', array( 'message' => __( 'Nonce error. Are you sure you are who you say you are?', 'document-feedback' ) ) );

		// Feedback must be left on a valid post
		$post_id = (int)$_POST['post_id'];
		if ( false === ( $post = get_post( $post_id ) ) )
			$this->do_ajax_response( 'error', array( 'message' => __( 'Invalid post for feedback.', 'document-feedback' ) ) );

		// Check that the comment exists if we're passed a valid comment ID
		$comment_id = (int)$_POST['comment_id'];
		if ( $comment_id && ( false === ( $comment = get_comment( $comment_id ) ) ) )
			$this->do_ajax_response( 'error', array( 'message' => __( 'Invalid comment.', 'document-feedback' ) ) );

		// @todo Ensure the user isn't hitting the throttle limit

		$current_user = wp_get_current_user();

		// Form submission for the initial prompt
		// Create a new comment of accept or decline type against the current user
		if ( $_POST['form'] == 'prompt' ) {

			// Set up all of the base data for our comment
			$comment_data = array(
					'comment_post_ID'       => $post_id,
					'comment_author'        => $current_user->display_name,
					'comment_author_email'  => $current_user->user_email,
					'comment_author_url'    => $current_user->user_url,
					'user_id'               => $current_user->ID,
				);
			
			// Set the comment type based on the value of the response
			if ( $_POST['response'] == 'accept' )
				$comment_data['comment_approved'] = 'df-accept';
			if ( $_POST['response'] == 'decline' )
				$comment_data['comment_approved'] = 'df-decline';

			// Document feedbacks are always a special type
			$comment_data['comment_type'] = 'document-feedback';

			$comment_id = wp_insert_comment( $comment_data );

			do_action( 'document_feedback_submitted', $comment_id, $post_id );

			$response = array(
					'message' => 'comment-id-' . $comment_id,
					'comment_id' => $comment_id
				);
			$this->do_ajax_response( 'success', $response );
		}
		// Follow up response form submission
		// Save the message submitted as the message in the comment
		
		$comment = get_comment( $comment_id, ARRAY_A );

		if ( ! $comment )
			$this->do_ajax_response( 'error', array( 'message' => __( 'Invalid comment entry.', 'document-feedback' ) ) );

		if ( (int)$comment['user_id'] != $current_user->ID )
			$this->do_ajax_response( 'error', array( 'message' =>  __( 'Invalid user ID for comment.', 'document-feedback' ) ) );
			
		// Manage comment and update if existing and if the comment author is the same as the feedback author
		$comment['comment_content'] = sanitize_text_field( $_POST['response'] );
		$is_comment_updated = wp_update_comment( $comment );
		if ( ! $is_comment_updated ) 
			$this->do_ajax_response( 'error', array( 'message' => __( 'Comment not updated.', 'document-feedback' ) ) );

		do_action( 'document_feedback_submitted', $comment_id, $post_id );

		// send a happy response
		$response = array(
				'message' => 'final_response',
		);
		$this->do_ajax_response( 'success', $response );
	}

	/**
	 * Do an ajax response
	 *
	 * @param string $status 'success' or 'error'
	 * @param array $data Any additional data
	 */
	private function do_ajax_response( $status, $data = array() ) {

		header('Content-type: application/json');

		$response = array(
				'status' => $status,
			);
		$response = array_merge( $response, $data );
		echo json_encode( $response );
		exit;
	}

	/**
	 * Set the throttle transient
	 *
	 * @since 1.0
	 *
	 */
	public function set_throttle_transient( $comment_id, $post_id ) {

		$comment = get_comment( $comment_id );
		$transient_option = $this->options['transient_prefix'] . $comment->user_id . '_' . $post_id;
		set_transient( $transient_option, $transient_option, $this->options['throttle_limit'] );
	}

	/**
	 * Send the document author a notification when feedback
	 * is submitted
	 *
	 * @since 1.0
	 *
	 * @param int $comment_id The feedback ID
	 * @param int $post_id The post ID for the relevant document
	 */
	public function send_notification( $comment_id, $post_id ) {

		if ( ! $this->options['send_notification'] )
			return;

		// Only send a notification if there was qualitative feedback
		$comment = get_comment( $comment_id );
		if ( ! $comment || empty( $comment->comment_content ) )
			return;

		// Make sure the post exists too
		$post = get_post( $post_id );
		if ( ! $post )
			return;

		$feedback_type = ( 'df-accept' == $comment->comment_approved ) ? __( 'positive', 'document-feedback' ) : __( 'constructive', 'document-feedback' );

		$subject = '[' . get_bloginfo( 'name' ) . '] ' . sprintf( __( "Feedback received on '%s'", 'document-feedback' ), $post->post_title );
		$message = sprintf( __( 'You\'ve received new %1$s feedback from %2$s (%3$s):', 'document-feedback' ), $feedback_type, $comment->comment_author, $comment->comment_author_email ) . PHP_EOL . PHP_EOL;
		$message .= '"' . $comment->comment_content . '"' . PHP_EOL . PHP_EOL;
		$message .= sprintf( __( 'You can view/edit the document here: ', 'document-feedback' ) ) . get_edit_post_link( $post_id, '' );

		$document_author = get_user_by( 'id', $post->post_author );
		$notification_recipients = apply_filters( 'document_feedback_notification_recipients', array( $document_author->user_email ), $comment_id, $post_id );
		foreach( $notification_recipients as $recipient ) {
			wp_mail( $recipient, $subject, $message );
		}
	}

	/**
	 * Append the document feedback form to the document
	 * We're using ob_*() functions to maintain readability of the form
	 *
	 * @since 1.0
	 */
	function filter_the_content_append_feedback_form( $the_content ) {
		global $post;

		if ( !is_singular() || !in_array( $post->post_type, $this->post_types ) || !is_user_logged_in() )
			return $the_content;

		// @todo Show a message if the user submitted a response in the last X minutes
		$current_user = wp_get_current_user();
		$post_id = $post->ID;
		$current_user_id = $current_user->ID;

		// get transient if the user already sent the feedback
		$transient_option = $this->options['transient_prefix'] . $current_user_id . '_' . $post_id;
		$transient = get_transient( $transient_option );

		// display the form if transient is empty
		if ( ! $transient ) {
			// Javascript for the form
			ob_start(); ?>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery('#document-feedback .document-feedback-form input.button').click(function(){
						var button_id = jQuery(this).attr('id');
						var comment_id = jQuery('#document-feedback-comment-id').val();
						var post_id = jQuery('#document-feedback-post-id').val();
						var nonce = jQuery('#document-feedback-nonce').val();
							
						if ( button_id == 'document-feedback-accept-button' ) {
							var form = 'prompt';
							var response = 'accept';
						} else if ( button_id == 'document-feedback-decline-button' ) {
							var form = 'prompt';
							var response = 'decline';
						} else {
							var form = 'response';
							var response = jQuery(this).siblings('.document-feedback-response').val();
						}
						var df_data = {
							action: 'document_feedback_form_submission',
							form: form,
							nonce: nonce,
							response: response,
							post_id: post_id,
							comment_id: comment_id,
						};
						jQuery.post( ajaxurl, df_data, function( response ) {
							var comment_id = response.comment_id;
							if( comment_id === undefined || isNaN( parseInt( comment_id ) ) ) {
								comment_id = 0;
							}
							if( df_data.response === 'accept' ) {
								jQuery('#document-feedback-comment-id').val( comment_id );
								jQuery('#document-feedback .document-feedback-form').hide();
								jQuery('#document-feedback-accept').show();
								jQuery('#document-feedback-decline').hide();
							} else if( df_data.response === 'decline' ) {
								jQuery('#document-feedback-comment-id').val( comment_id );
								jQuery('#document-feedback .document-feedback-form').hide();
								jQuery('#document-feedback-accept').hide();
								jQuery('#document-feedback-decline').show();
							} else if( response.message === 'final_response' ) {
								jQuery('#document-feedback-accept').hide();
								jQuery('#document-feedback-decline').hide();
								jQuery('#document-feedback-success').show();
							}
							return false;
						});
						return false;
					});
				});
			</script>
			<?php
			$script = ob_get_contents();
			ob_end_clean();
	
			// Styles for the form
			ob_start(); ?>
			<style type="text/css">
				#document-feedback {
					border-top: 1px solid #EEE;
					margin-top: 10px;
					padding-top: 10px;
				}
				#document-feedback #document-feedback-accept,
				#document-feedback #document-feedback-decline,
				#document-feedback #document-feedback-success {
					display: none;
				}
				#document-feedback label.block {
					display:block;
				}
				#document-feedback input.medium {
					width: 70%;
				}
				#document-feedback #document-feedback-prompt label {
					margin-right: 20px;
				}
			</style>
			<?php
			$styles = ob_get_contents();
			ob_end_clean();
	
			// Initial prompt
			ob_start(); ?>
			<div id="document-feedback-success"><?php echo esc_html ($this->strings['final_response'] ); ?></div>
			<form id="document-feedback-prompt" class="document-feedback-form" method="POST" action="">
				<label><?php echo esc_html( $this->strings['prompt'] ); ?></label>
				<input type="submit" class="button" id="document-feedback-accept-button" name="document-feedback-accept-button" value="<?php echo esc_attr( $this->strings['accept'] ); ?>" />
				<input type="submit" class="button" id="document-feedback-decline-button" name="document-feedback-decline-button" value="<?php echo esc_attr( $this->strings['decline'] ); ?>" />
			</form>
			<?php
			$prompt = ob_get_contents();
			ob_end_clean();
	
			// Follow-up accept question
			ob_start(); ?>
			<form id="document-feedback-accept" class="document-feedback-form" method="POST" action="">
				<label class="block" for="document-feedback-accept-response"><?php echo esc_html( $this->strings['prompt_response'] . ' ' . $this->strings['accept_prompt'] ); ?></label>
				<input type="text" class="medium document-feedback-response" id="document-feedback-accept-response" name="document-feedback-accept-response" />
				<input type="submit" class="button document-feedback-submit-response" name="submit" value="<?php _e( 'Send feedback', 'document-feedback' ); ?>" />
			</form>
			<?php
			$accept = ob_get_contents();
			ob_end_clean();
	
			// Follow-up decline question
			ob_start(); ?>
			<form id="document-feedback-decline" class="document-feedback-form" method="POST" action="">
				<label class="block" for="document-feedback-decline-response"><?php echo esc_html( $this->strings['prompt_response'] . ' ' . $this->strings['decline_prompt'] ); ?></label>
				<input type="text" class="medium document-feedback-response" id="document-feedback-decline-response" name="document-feedback-decline-response" />
				<input type="submit" class="button document-feedback-submit-response" name="submit" value="<?php _e( 'Send feedback', 'document-feedback' ); ?>" />
			</form>
			<?php
			$decline = ob_get_contents();
			ob_end_clean();
	
			// Other data to store in a hidden fashion
			ob_start(); ?>
			<input type="hidden" id="document-feedback-post-id" value="<?php the_id(); ?>" />
			<input type="hidden" id="document-feedback-comment-id" value="0" />
			<?php wp_nonce_field( 'document-feedback', 'document-feedback-nonce' ); ?>
			<?php
			$data = ob_get_contents();
			ob_end_clean();
	
			return $the_content . $script . $styles . '<div id="document-feedback">' . $prompt . $accept . $decline . $data . '</div>';
		} else {
			ob_start(); ?>
			<div id="document-feedback-success-sent"><?php echo esc_html ($this->strings['final_response'] ); ?></div>
			
			<?php $data = ob_get_contents();
			ob_end_clean();
			
			return $the_content . '<div id="document-feedback">' . $data . '</div>';
		}
	}
	
	/**
	 * Filter the feedback comments - add accept and decline clauses as comment_approved
	 * 
	 * @since 1.0
	 * 
	 */
	function filter_feedback_comments_clauses( $clauses, $query ) {
		$expected_type_clause = "( comment_approved = '0' OR comment_approved = '1' )";
		// filter if we are looking for the feedback comments
		if( isset( $clauses['where'] ) && false !== strpos( $clauses['where'], $expected_type_clause ) ) {
			$correct_type_clause = "comment_approved IN ( 'df-accept', 'df-decline' ) ";
			
			$clauses['where'] = str_replace( $expected_type_clause , $correct_type_clause, $clauses['where'] );
		}

		return $clauses;
	}
}

}

function Document_Feedback() {
	return Document_Feedback::get_instance();
}
add_action( 'plugins_loaded', 'Document_Feedback' );
