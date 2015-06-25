<?php
/**
 * Holds the SocialFlow Post class
 * is responsible for adding meta boxes and fires message compose
 * adds some changes in admin posts interface
 *
 * @package SocialFlow
 * @since 2.0
 */
class SocialFlow_Post {

	/**
	 * Hold post options
	 * @var array
	 * @since 2.1
	 */
	var $js_settings = array();

	/**
	 * PHP5 constructor
	 * Add actions and filters
	 *
	 * @since 2.0
	 * @access public
	 */
	function __construct() {

		if ( is_admin() ) {

			// Add posts columns
			add_action( 'admin_init', array( $this, 'manage_posts_columns' ) );

			// Add meta box
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box') );

			// Ajax response with thumbnails
			add_action( 'wp_ajax_sf_attachments', array( $this, 'ajax_post_attachments' ) );

			// Output compose form on ajax call
			add_action( 'wp_ajax_sf-composeform', array( $this, 'ajax_compose_form' ) );

			// Compose message to socialflow via ajax
			add_action( 'wp_ajax_sf-compose', array( $this, 'ajax_compose' ) );

			// Get single message via ajax request
			add_action( 'wp_ajax_sf-get-message', array( $this, 'sf_get_message' ) );

			// Add new updated message
			add_filter( 'post_updated_messages', array($this, 'post_updated_messages') );

			// Ouput js settings object if necessary
			add_action( 'admin_footer', array( $this, 'post_settings' ) );
		}

		// Add save action
		// Meta data is saved and message composition may be processed
		add_action( 'transition_post_status', array( $this, 'transition_post_status'), 1, 3 );
	}

	/**
	 * Add socialflow features to admin interface
	 *
	 * @since 2.0
	 * @access public
	 */
	function manage_posts_columns() {
		global $socialflow;
		
		// Loop through all active post_types and add custom columns
		if ( $socialflow->options->get( 'post_type' ) ) {
			foreach ( $socialflow->options->get( 'post_type' ) as $post_type ) {
				add_filter( 'manage_'. $post_type .'_posts_columns' , array( $this, 'add_column' ) );
				add_action( 'manage_'. $post_type .'_posts_custom_column' , array( $this, 'custom_column' ), 10, 2 );
			}
		}

		// Add send action to posts list table 
		add_action( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/**
	 * Add socialflow meta box to posts
	 *
	 * @since 2.0
	 * @access public
	 */
	public function add_meta_box() {
		global $socialflow;

		// Don't add meta box if user is not authorized or no post types selected
		if ( $socialflow->is_authorized() AND $socialflow->options->get( 'post_type' ) ) {
			foreach ( $socialflow->options->get( 'post_type' ) as $type ) {
				add_meta_box( 'socialflow', __( 'SocialFlow', 'socialflow' ), array( $this, 'meta_box' ), $type, 'side', 'high', array( 'post_page' => true ) );
			}
		}
	}

	/**
	 * Display Meta box
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param object - current post
	 */
	function meta_box( $post ) {
		global $socialflow;

		// Retrieve only enabled accounts
		$accounts = $socialflow->options->get( 'show' ) ? $socialflow->accounts->get( $socialflow->options->get( 'show' ) ) : array();

		$socialflow->render_view( 'meta-box', array(
			'accounts' => $accounts,
			'post' => $post,
			'SocialFlow_Post' => $this
		));
	}

	/**
	 * Display compose form
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param object - current post
	 * @param array - enabled accounts
	 */
	function display_compose_form( $post, $accounts ) {
		global $socialflow;

		// get active accounts and group by type
		$grouped_accounts = $socialflow->accounts->group_by( 'global_type', $accounts ); 

		// Get compose now value, if post was not publish yet get value from options
		$compose_now = ( 'auto-draft' == $post->post_status ) ? $socialflow->options->get( 'compose_now' ) : get_post_meta( $post->ID, 'sf_compose_now', true );

		// Enable compose form js
		$this->js_settings['initForm'] = true;

		$view = $socialflow->render_view( 'form/post', array(
			'grouped_accounts' => $grouped_accounts,
			'post' => $post,
			'SocialFlow_Post' => $this,
			'compose_now' => $compose_now,
			'accounts' => $accounts
		));
	}

	/**
	 * Display socialflow messages
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param object current post
	 */
	function display_messages( $post ) {
		global $socialflow;

		// If errors present render error view
		if ( $errors = $socialflow->get_errors( $post->ID ) ) {
			$socialflow->render_view( 'form/messages', array(
				'errors' => $socialflow->filter_errors( $errors )
			));
		}

		$socialflow->render_view( 'stats/full', $this->get_view_stat_data( $post->ID ) );
	}

	/**
	 * Get data for statistics view
	 *
	 * @since 2.0 
	 * @access public
	 *
	 * @param int current post id
	 * @return array of public arguments for stats view
	 */
	function get_view_stat_data( $post_id ) {
		global $socialflow;

		// Get statuses
		$statuses = get_post_meta( $post_id, 'sf_success', true );

		if ( is_array( $statuses ) AND !empty( $statuses ) ) {

			// Reorder success messages by date
			krsort( $statuses );

			// Get last success publish data
			$date = array_keys( $statuses );
			$date = array_shift( $date );
		} else {
			$statuses = $date = '';
		}

		return array(
			'form_messages' => $statuses,
			'last_sent'     => $date,
			'post_id'       => $post_id
		);
	}

	/**
	 * Maybe send message to socialflow,
	 * or simply update socialflow meta
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string - current post status
	 * @param string - previous post status
	 * @param object - current post object
	 */
	function transition_post_status( $post_status, $previous_status, $post ) {
		global $socialflow;

		// Doing autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;

		// Check if we are dealing with revision
		if ( 'revision' == $post->post_type )
			return;

		// Form is not submited inside cron job for scheduled posts 
		// thats why we are skipping some validations
		if ( !( defined( 'DOING_CRON' ) && DOING_CRON && 'future' == $previous_status && 'publish' == $post_status ) ) {

			// Verify nonce
			if ( ! isset( $_POST['socialflow_nonce'] ) || ! wp_verify_nonce( $_POST['socialflow_nonce'], SF_ABSPATH ) )
				return;

			// Prevent multiple form submission
			if ( get_post_meta( $post->ID, 'socialflow_nonce', true ) !== $_POST['socialflow_nonce'] )
				return;
			delete_post_meta( $post->ID, 'socialflow_nonce' );

			// Check if user has enough capabilities
			if ( ! current_user_can( 'edit_post', $post->ID ) )
				return;
		}

		// Prevent action duplication
		remove_action( 'transition_post_status', array( $this, 'transition_post_status'), 1, 3 );

		$post_id = $post->ID;

		// no need to save post meta inside schedule scenario
		if ( ! ( 'future' == $previous_status && 'publish' == $post_status ) ) {

			// Save socialflow meta data 
			$this->save_meta( $post_id );
		}

		// Compose to socialflow 
		// Check if send now is checked
		if ( get_post_meta( $post_id, 'sf_compose_now', true ) && 'publish' == $post_status ) {

			$result = $this->compose( $post_id );

			// If message compose fails and post was inteded to be published
			// set post status as draft
			if ( is_wp_error( $result ) AND 'publish' == $post_status AND 'publish' != $previous_status ) {

				// Set post status to draft
				$post->post_status = 'draft';
				wp_update_post( $post );

				// Redirect user to approptiate message
				$location = add_query_arg( 'message', 20, get_edit_post_link( $post_id, 'url' ) );
				wp_redirect( $location );
				exit;
			}
		}
	}

	/**
	 * Collect socialflow _POST meta and save it
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param int - current post id
	 */
	function save_meta( $post_id ) {
		global $socialflow;

		$data = $_POST['socialflow'];

		// Compose now variable
		update_post_meta( $post_id, 'sf_compose_now', absint( isset( $data['compose_now'] ) ) );

		// Collect and save all socialflow messages
		if ( isset( $data['message'] ) ) {
			foreach ( $data['message'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_message_' . $key, trim( sanitize_text_field( $value ) ) );
			}
		}

		// Collect and save all socialflow titles
		if ( isset( $data['title'] ) ) {
			foreach ( $data['title'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_title_' . $key, trim( sanitize_text_field( $value ) ) );
			}
		}

		// Collect and save all socialflow descriptions
		if ( isset( $data['description'] ) ) {
			foreach ( $data['description'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_description_' . $key, trim( sanitize_text_field( $value ) ) );
			}
		}

		// Collect and save all images
		if ( isset( $data['image'] ) ) {
			foreach ( $data['image'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_image_' . $key, trim( sanitize_text_field( $value ) ) );
			}
		}

		// Save ids of enabled accounts
		$send = isset( $data['send'] ) ? array_map( 'absint', $data['send'] ) : array(  );
		update_post_meta( $post_id, 'sf_send_accounts', $send );

		// Loop through all accounts and collect accounts specific data
		$advanced = array();
		$accounts = $socialflow->accounts->get();
		foreach ( $accounts as $user_id => $account ) {
			if ( isset( $data[ $user_id ] ) ) {
				$advanced[ $user_id ] = $this->get_user_advanced_options( $account, $data );
			}
		}

		// save all advanced settings
		update_post_meta( $post_id, 'sf_advanced', $advanced );
	}

	/**
	 * Retrieve associative array of advanced user options
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param array client service account
	 * @param array passed advanced account data
	 * @return array of filtered data
	 */
	function get_user_advanced_options( $account, $data = array() ) {
		global $socialflow;

		$account = is_int( $account ) ? $socialflow->filter_accounts( array( 'client_service_id' => $account ) ) : $account;

		$data = isset( $data[ $account['client_service_id'] ] ) ? $data[ $account['client_service_id'] ] : array();

		return array_map( 'sanitize_text_field', array(
			'publish_option'      => isset( $data['publish_option'] ) ? sanitize_text_field( $data['publish_option'] ) : $socialflow->options->get( 'publish_option' ),
			'must_send'           => isset( $data['must_send'] ) ? absint( $data['must_send'] ) : absint( $socialflow->options->get( 'must_send' ) ),
			'optimize_period'     => isset( $data['optimize_period'] ) ? sanitize_text_field( $data['optimize_period'] ) : $socialflow->options->get( 'optimize_period' ),
			'optimize_start_date' => isset( $data['optimize_start_date'] ) ? sanitize_text_field( $data['optimize_start_date'] ) : $socialflow->options->get( 'optimize_start_date' ),
			'optimize_end_date'   => isset( $data['optimize_end_date'] ) ? sanitize_text_field( $data['optimize_end_date'] ) : $socialflow->options->get( 'optimize_end_date' ),
			'scheduled_date'      => isset( $data['scheduled_date'] ) ? sanitize_text_field( $data['scheduled_date'] ) : ''
		));
	}

	/**
	 * This function either calls 
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param int current post ID
	 * @return mixed ( bool | WP_Error ) return true on success or WP_Error object on failure
	 */
	function compose( $post_id ) {
		global $socialflow;
		$errors = new WP_Error;

		// Get array of enabled accounts
		$enabled = get_post_meta( $post_id, 'sf_send_accounts', true );
		if ( empty( $enabled ) ) {
			$errors->add( 'empty_accounts', __( '<b>Error:</b> No accounts were selected', 'socialflow' ) );
			return $socialflow->save_errors( $post_id, $errors );
		}

		$advanced_meta = get_post_meta( $post_id, 'sf_advanced', true );

		// Get all accounts ids to include
		foreach ( $advanced_meta as $key => $value ) {
			if ( !in_array( $key, $enabled ) ) {

				// remove disabled account
				unset( $advanced_meta[ $key ] );
			}
		}

		// get checked accounts
		$accounts = $socialflow->accounts->get( array( 
			array(
				'key' => 'client_service_id',
				'value' => array_keys( $advanced_meta )
			)
		));

		// Get global settings
		$shorten_links = absint( $socialflow->options->get( 'shorten_links' ) );

		// loop through active accounts and add messages to additional data
		foreach ( $accounts as $account_id => $account ) :
			$user_id = $account['client_service_id'];
			$type = $socialflow->accounts->get_global_type( $account );
			
			// Check for not empty message
			$message = get_post_meta( $post_id, 'sf_message_' . $type, true ) ? 
				trim( get_post_meta( $post_id, 'sf_message_' . $type, true ) ) : 
				trim( get_post_meta( $post_id, 'sf_message_' . $user_id, true ) );


			// Add settings from saved meta
			$advanced[ $account_id ] = isset( $advanced_meta[ $user_id ] ) ? $advanced_meta[ $user_id ] : array();

			// add account message with permalink
			$advanced[ $account_id ]['message'] = ( 'twitter' == $type AND !empty( $message ) ) ? $message . ' ' . get_permalink( $post_id ) : $message;

			// Retrieve some specific account data depending on account type
			if ( 'facebook' == $type || 'google_plus' == $type ) {
				$advanced[ $account_id ]['content_attributes'] = array();
				$advanced[ $account_id ]['content_attributes']['link'] = get_permalink( $post_id );

				if ( 'facebook' == $type ) {
					if ( get_post_meta( $post_id, 'sf_title_facebook', true ) )
						$advanced[ $account_id ]['content_attributes']['name'] = esc_html( get_post_meta( $post_id, 'sf_title_facebook', true ) );
					if ( get_post_meta( $post_id, 'sf_description_facebook', true ) )
						$advanced[ $account_id ]['content_attributes']['description'] = wp_trim_words(esc_html( get_post_meta( $post_id, 'sf_description_facebook', true ) ), 150, ' ...' );
					if ( get_post_meta( $post_id, 'sf_image_facebook', true ) )
						$advanced[ $account_id ]['content_attributes']['picture'] = get_post_meta( $post_id, 'sf_image_facebook', true );
				}
				
			}

			// add current user display name and email to send queue
			$advanced[ $account_id ]['created_by'] = get_user_option( 'display_name', get_current_user_id() ) .' <'. get_user_option( 'user_email', get_current_user_id() ) .'>';

		endforeach;

		// Check if errors occured during data collection
		if ( $errors->get_error_codes() ) {
			return $socialflow->save_errors( $post_id, $errors );
		}

		// Send prepared data to accounts object
		$result = $socialflow->accounts->compose( $advanced );

		if ( is_wp_error( $result ) ) {
			return $socialflow->save_errors( $post_id, $result );
		}
		else {
			$success = get_post_meta( $post_id, 'sf_success', true ) ? get_post_meta( $post_id, 'sf_success', true ) : array();

			// create new arry item for current message
			$success[ current_time( 'mysql' ) ] = array();

			// $result is array of messages, so we need to create success array to hold account success messages
			foreach ( $result as $message ) {
				$success[ current_time( 'mysql' ) ][ $message['client_service_id'] ] = array(
					'status' => sanitize_text_field( $message['status'] ),
					'content_item_id' => sanitize_text_field( $message['content_item_id'] ),
					'is_published' => sanitize_text_field( $message['is_published'] ) 
				);
			}

			// Set zero compose now meta
			update_post_meta( $post_id, 'sf_compose_now', 0 );
			// store all succefully send account ids
			update_post_meta( $post_id, 'sf_success', $success );

			// Clear errors for this post
			$socialflow->clear_errors( $post_id );

			return true;
		}

	}

	/**
	 * Output list of images attached to requested post id
	 *
	 * @since 2.0
	 * @access public
	 *
	 */
	function ajax_post_attachments() {
		// Retrieve args from ajax request
		$this->post_attachments( absint( $_POST['ID'] ), $_POST['content'] );
		exit;
	}

	/**
	 * Retrieve images from post content
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param int - current post ID
	 * @param string - current post content
	 */
	function post_attachments( $post_id, $post_content = '' ) {
		if ( empty( $post_content ) )
			return;

		$post_content = stripslashes( $post_content );
		$regex = '/<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1/im';

		if ( !preg_match_all( $regex, $post_content, $images ) )
			return;

		foreach ( $images[2] as $image ) {
			?><div class="slide"><img src="<?php echo esc_url( $image ); ?>" /></div><?php
		}
	}

	/**
	 * Output compose form as a response to ajax call
	 * This is a callback for ajax action
	 *
	 * @access public
	 * @since 2.2
	 */
	function ajax_compose_form() {
		global $socialflow;

		$post = get_post( absint( $_GET['post'] ) );

		$socialflow->render_view( 'form/ajax', array(
			'post' => $post,
			'SocialFlow_Post' => $this
		));
		exit;
	}

	/**
	 * Callback function for ajax compose call
	 *
	 * @since 2.1
	 * @access public
	 *
	 */
	function ajax_compose() {
		global $socialflow;

		$post_id = absint( $_POST['post_id'] );

		// Verify nonce
		if ( ! isset( $_POST['socialflow_nonce'] ) || ! wp_verify_nonce( $_POST['socialflow_nonce'], SF_ABSPATH ) )
			return;

		// Check if user has enough capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;

		// Add compose now variable
		$_POST['socialflow']['compose_now'] = 1;

		// Save socialflow meta data 
		$this->save_meta( $post_id );

		$this->compose( $post_id );
		
		// Check if there are any success messages and return updated messages block
		ob_start();
		$this->display_messages( get_post( absint( $post_id ) ) );
		$messages = ob_get_clean();

		if ( $socialflow->get_errors( $post_id ) ) {
			$status = 0;
			$ajax_messages = '<p class="sf-error">' . __( '<b>Errors</b> occurred. View messages block for more information.', 'socialflow' ) . '</p>';
		} else {
			$status = 1;
			$ajax_messages = '<p class="success">' . __( 'Message was successfully sent. View statistics block for more information.', 'socialflow' ) . '</p>';
		}

		wp_send_json( array(
			'messages' => $messages,
			'status' => $status,
			'ajax_messages' => $ajax_messages
		));
	}

	/**
	 * Get single message from SocialFlow api
	 * This is a callback for ajax call
	 * 
	 * @since 2.2
	 * @access public
	 * @return void result is outputted as html
	 */
	function sf_get_message() {
		global $socialflow;

		// Get arguments
		$message_id = absint( $_GET['id'] );
		$post_id = absint( $_GET['post_id'] );
		$date = esc_attr( $_GET['date'] );
		$account_id = absint( $_GET['account_id'] );

		$api = $socialflow->get_api();
		$message = $api->view_message( $message_id );

		$status = '';

		if ( is_wp_error( $message ) ) {
			// we need only message

			if ( 'http_request_failed' == $message->get_error_code() )
				$status = __( '<b>Error:</b> Server connection timed out. Please, try again.', 'socialflow' );
			else
				$status = $message->get_error_message();

		} else {
			// update post messages 
			$success = get_post_meta( $post_id, 'sf_success', true );
			$status = $message['status'];

			if ( $message['is_deleted'] )
				$status .= ' <i class="deleted">'. __( 'deleted', 'socialflow' ) .'</i>';

			if ( !empty( $success ) AND isset( $success[ $date ][ $account_id ] ) ) {

				// Update message status
				$success[ $date ][ $account_id ]['status'] = $status;

				// Update message is_published attr
				$success[ $date ][ $account_id ]['is_published'] = $message['is_published'];
				$status .= ' ';
				$status .= ( 0 == $message['is_published'] ) ? __( 'In Queue', 'socialflow' ) : __( 'Published', 'socialflow' );

				update_post_meta( $post_id, 'sf_success', $success );
			}
		}

		echo $status;
		exit;
	}
	
	/**
	 * Add SocialFlow custom column heading
	 *
	 * Callback for "manage_{$post_type}_posts_columns" hook
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param array of list columns
	 * @return array of filtered list columns
	 */
	function add_column( $columns ) {
		$columns[ 'socialflow' ] = __( 'SocialFlow', 'socialflow' );
		return $columns;
	}

	/**
	 * Add SocialFlow custom column content
	 *
	 * Callback for "manage_{$post_type}_posts_custom_column" hook
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string column key
	 * @param int current post id
	 */
	function custom_column( $column, $post_id ) {
		global $socialflow;

		if ( 'socialflow' == $column ) {
			// if sf_compose == 0 than message was already composed
			if ( get_post_meta( $post_id, 'sf_success', true ) ) {
				echo '<img class="js-sf-extended-stat-toggler" src="'. plugins_url( 'assets/images/success.gif', SF_FILE ) .'" width="12" height="12" title="'. __( 'Successfully sent', 'socialflow' ) .'" alt="'. __( 'Successfully sent', 'socialflow' ) .'" />';

				// Render compact stats table
				$socialflow->render_view( 'stats/compact', $this->get_view_stat_data( $post_id ) );
			} elseif ( 'publish' !== get_post_status( $post_id ) && ( get_post_meta( $post_id, 'sf_message_facebook', true ) || get_post_meta( $post_id, 'sf_message_twitter', true ) ) ) {
				echo '<img src="'. plugins_url( 'assets/images/notice.gif', SF_FILE ) .'" width="12" height="12" title="'. __( 'SocialFlow data filled', 'socialflow' ) .'" alt="'. __( 'SocialFlow data filled', 'socialflow' ) .'" />';
			}
		}
	}

	/**
	 * Add action link to for composing message right from posts list table
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @param array of post actions
	 * @param object current post
	 * @return array filtered actions
	 */
	function row_actions( $actions, $post ) {
		global $socialflow;
		// Post must be published and post type enabled in plugin options
		if ( 'publish' == $post->post_status AND in_array( $post->post_type, $socialflow->options->get( 'post_type', array() ) ) ) {
			$url = add_query_arg( array( 
				'action' => 'sf-composeform', 
				'post' => $post->ID,
				'width' => '740'
			), admin_url( '/admin-ajax.php' ) );
			$actions['sf-compose-action'] = '<a class="thickbox" href="' .  $url . '" title="' . esc_attr__( 'Send to SocialFlow', 'socialflow' ) . '">' . __( 'Send to SocialFlow', 'socialflow' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Add new updated messages
	 * @param  array $messages
	 * @return array $messages
	 */
	function post_updated_messages( $messages ){
		global $socialflow;

		// Add message only for enabled post types
		if ( $socialflow->options->get( 'post_type' ) ) {
			foreach ( $socialflow->options->get( 'post_type' ) as $type ) {
				$messages[ $type ][20] = __( '<b>Notice:</b> ' . $type . ' was not published, because some errors occurred when sending messages to SocialFlow. <a href="#socialflow">View More.</a>');
			}
		}

		return $messages;
	}

	/**
	 * Pass post settings js object if necessary
	 * 
	 * @since  2.1
	 */
	function post_settings() {
		global $socialflow;

		$this->js_settings['postType'] = $socialflow->options->get( 'post_type' );
		wp_localize_script( 'socialflow-admin', 'optionsSF', $this->js_settings );
	}
}