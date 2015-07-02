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

			add_filter( 'sf_message', array( $this, 'default_message' ), 10, 3 );

			// Compose message to socialflow via ajax
			add_action( 'wp_ajax_sf-compose', array( $this, 'ajax_compose' ) );

			// Get single message via ajax request
			add_action( 'wp_ajax_sf-get-message', array( $this, 'sf_get_message' ) );

			// Get custom attachment media
			add_action( 'wp_ajax_sf_get_custom_message_image', array( $this, 'ajax_attachment_media' ) );

			// Add media 
			add_filter( 'tiny_mce_before_init', array( $this, 'bind_editor_update' ) );

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
		add_action( 'media_row_actions', array( $this, 'row_actions' ), 10, 2 );
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
		if ( !( $socialflow->is_authorized() AND $socialflow->options->get( 'post_type' ) ) )
			return;

		foreach ( $socialflow->options->get( 'post_type' ) as $type ) {

			// Meta boxes for attachments are too narrow
			// and you can send attachment only from attachments media list
			if ( 'attachment' == $type )
				continue;

			add_meta_box( 'socialflow', __( 'SocialFlow', 'socialflow' ), array( $this, 'meta_box' ), $type, 'side', 'high', array( 'post_page' => true ) );
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
		$accounts = $socialflow->options->get( 'show' ) ? $socialflow->accounts->get( $socialflow->options->get( 'show' ), $post->post_type ) : array();

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
		$grouped_accounts = $socialflow->accounts->group_by( 'global_type', $accounts, true );

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

		// no need to save post meta inside schedule scenario
		if ( ! ( 'future' == $previous_status && 'publish' == $post_status ) ) {
			$this->save_meta( $post->ID );
		}

		// Compose to socialflow 
		// Check if send now is checked
		if ( get_post_meta( $post->ID, 'sf_compose_now', true ) && 'publish' == $post_status ) {

			$result = $this->compose( $post->ID );

			// If message compose fails and post was inteded to be published
			// set post status as draft
			if ( is_wp_error( $result ) AND 'publish' == $post_status AND 'publish' != $previous_status ) {

				// Set post status to draft
				$post->post_status = 'draft';
				wp_update_post( $post );

				// Redirect user to approptiate message
				$location = add_query_arg( 'message', 20, get_edit_post_link( $post->ID, 'url' ) );
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

		// Compose media feature
		update_post_meta( $post_id, 'sf_compose_media', absint( isset( $data['compose_media'] ) ) );

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

		// Custom image or one of attachments
		if ( isset( $data['is_custom_image'] ) ) {
			foreach ( $data['is_custom_image'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_is_custom_image_' . $key, absint( $value ) );
			}
		}
		if ( isset( $data['custom_image'] ) ) {
			foreach ( $data['custom_image'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_custom_image_' . $key, sanitize_text_field( $value ) );
			}
		}
		if ( isset( $data['custom_image_filename'] ) ) {
			foreach ( $data['custom_image_filename'] as $key => $value ) {
				update_post_meta( $post_id, 'sf_custom_image_filename_' . $key, sanitize_text_field( $value ) );
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

		$is_compose_media = get_post_type($post_id) == 'attachment' ? true : absint( get_post_meta( $post_id, 'sf_compose_media', true ) );
		$media = get_post_meta( $post_id, 'sf_media', true );

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

			if ( $is_compose_media && 'linkedin' == $type )
				continue;

			// Custom image is switch for each account type (should be checked against each account type)
			$is_custom_image = absint( get_post_meta( $post_id, 'sf_is_custom_image_' . $type, true ) );

			// Add settings from saved meta
			$advanced[ $account_id ] = isset( $advanced_meta[ $user_id ] ) ? $advanced_meta[ $user_id ] : array();

			// add account message with permalink
			if ( !empty( $message ) AND ( 'twitter' == $type OR ( $is_compose_media AND get_post_type($post_id) !== 'attachment' ) ) ) {
				$advanced[ $account_id ]['message'] =  $message . ' ' . get_permalink( $post_id );
			}
			else {

				$advanced[ $account_id ]['message'] = $message;
			}

			// Retrieve some specific account data depending on account type
			if ( in_array( $type, array( 'facebook', 'google_plus', 'linkedin' ) ) ) {
				$advanced[ $account_id ]['content_attributes'] = array();
				$advanced[ $account_id ]['content_attributes']['link'] = get_permalink( $post_id );				
			}

			// Dont send title and description for media compose
			if ( !$is_compose_media && in_array( $type, array( 'facebook', 'linkedin' ) ) ) {
				if ( get_post_meta( $post_id, 'sf_title_' . $type, true ) )
					$advanced[ $account_id ]['content_attributes']['name'] = esc_html( get_post_meta( $post_id, 'sf_title_' . $type, true ) );
				if ( get_post_meta( $post_id, 'sf_description_' . $type, true ) )
					$advanced[ $account_id ]['content_attributes']['description'] = wp_trim_words(esc_html( get_post_meta( $post_id, 'sf_description_' . $type, true ) ), 150, ' ...' );
			}

			// Maybe attach custom image
			if ( 'linkedin' !== $type && $is_custom_image && $image = get_post_meta( $post_id, 'sf_custom_image_' . $type , true ) ) {
				$advanced[ $account_id ]['media_thumbnail_url'] = $image;
				$advanced[ $account_id ]['media_filename'] = get_post_meta( $post_id, 'sf_custom_image_filename_' . $type , true );
			}
			elseif ( 'linkedin' !== $type && $is_compose_media && $media ) {
				$advanced[ $account_id ]['media_thumbnail_url'] = $media['medium_thumbnail_url'];
				$advanced[ $account_id ]['media_filename'] = $media['filename'];
			}
			elseif ( $image = get_post_meta( $post_id, 'sf_image_' . $type , true ) ) {
				$advanced[ $account_id ]['content_attributes']['picture'] = $image;
			}


			// Temporary fix for twitter media messages
			// if ( 'twitter' == $type && get_post_type($post_id) == 'attachment' && $image = get_post_meta( $post_id, 'sf_custom_image_facebook' , true ) ) {
			// 	$advanced[ $account_id ]['media_thumbnail_url'] = $image;
			// 	$advanced[ $account_id ]['media_filename'] = get_post_meta( $post_id, 'sf_custom_image_filename_facebook' , true );
			// }

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
		$post = get_post( $post_id );

		$thumbnail = get_the_post_thumbnail( $post_id, 'full' );
		if ( $thumbnail )
			echo '<div class="slide">'. $thumbnail .'</div>';

		if ( empty( $post_content ) )
			return;

		$post_content = stripslashes( $post_content );
		$regex = '/<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1/im';

		if ( !preg_match_all( $regex, $post_content, $images ) )
			return;

		foreach ( $images[2] as $image ) {
			echo '<div class="slide"><img src="'. esc_url( $image ) .'" /></div>';
		}
	}

	/**
	 * To retrieve image for attachment we need to make api call first
	 * @param  int $attachment_id Attachment id
	 * @return string             Attachment image
	 */
	function get_attachment_image( $attachment_id ) {
		$media = $this->get_attachment_media( $attachment_id );

		if ( empty( $media ) ) {
			return '';
		}

		return '<img src="'. esc_url( $media['medium_thumbnail_url'] ) .'" alt="">';
	}

	/**
	 * Retrieve custom media object for requested attachment
	 * @param  [type] $attachment_id [description]
	 * @return [type]                [description]
	 */
	function get_attachment_media( $attachment_id ) {
		global $socialflow;

		if ( $media = get_post_meta( $attachment_id, 'sf_media', true ) ) {
			return $media;
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );

		if ( !$image ) {
			return false;
		}

		$media = $socialflow
			->get_api()
			->add_media( $image[0] );

		if ( is_wp_error( $media ) )
			return false;

		update_post_meta( $attachment_id, 'sf_media', $media );

		// media already presents so we can call recursievly
		return $media;
	}

	/**
	 * Response to ajax request for attachment media
	 * Can be requested as media attach request
	 * @return void
	 */
	function ajax_attachment_media() {
		if ( !isset( $_POST['attachment_id'] ) ) {
			die(0);
		}

		$media = $this->get_attachment_media( absint( $_POST['attachment_id'] ) );

		if ( ! $media ){
			die(0);
		}

		if ( isset( $_POST['attach_to_post'] ) ) {
			update_post_meta( absint( $_POST['attach_to_post'] ), 'sf_media', $media );
		}

		wp_send_json( $media );
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
	 * After editor loaded we need to notify parent document body about this
	 * @to-do add support for tinyMCE editor < 4
	 * @param  array $config Mce editor config
	 * @return array         Mce editor config
	 */
	function bind_editor_update( $config ) {
		global $socialflow;
		$screen = get_current_screen();

		if ( !( is_admin() && $screen->parent_base == 'edit' && in_array( $screen->post_type, $socialflow->options->get( 'post_type' )) ) )
			return $config;

		$config['setup'] = "function (editor) {
			editor.on('init', function(event) {
				parent.window.jQuery('body').trigger('wp-tinymce-loaded');
			});
		}";

		return $config;
	}

	/**
	 * Default socialflow message 
	 * @param  string $message SocialFlow message
	 * @param  string $type    Default type
	 * @param  object $post    Post object
	 * @return string          Default message
	 */
	function default_message( $message, $type, $post ) {
		if ( !empty( $message ) )
			return $message;

		if ( 'attachment' === $post->post_type ) {
			if ( !empty( $post->post_content ) )
				$message = $post->post_content;
			elseif ( !empty( $post->post_excerpt ) )
				$message = $post->post_excerpt;
			else
				$message = $post->post_title;
		}

		return $message;
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

		// Post must be published and post type enabled in plugin options
		if ( !$this->is_post_enabled( $post ) )
			return $actions;

		$url = add_query_arg( array( 
			'action' => 'sf-composeform', 
			'post' => $post->ID,
			'width' => '740'
		), admin_url( '/admin-ajax.php' ) );
		$actions['sf-compose-action'] = '<a class="thickbox" href="' .  esc_url( $url ) . '" title="' . esc_attr__( 'Send to SocialFlow', 'socialflow' ) . '">' . esc_attr__( 'Send to SocialFlow', 'socialflow' ) . '</a>';

		return $actions;
	}

	/**
	 * Check if socialflow is enabled for current post
	 * @param  Object  $post WP_Post object
	 * @return boolean       Enabled status
	 */
	function is_post_enabled( $post ) {
		global $socialflow;

		// Post type must be enabled in plugin options
		if ( !in_array( $post->post_type, $socialflow->options->get( 'post_type', array() ) ) )
			return false;

		// Include only image attachments
		if ( 'attachment' == $post->post_type )
			return strpos( $post->post_mime_type, 'image' ) !== false;

		// All other post types must be published
		return $post->post_status === 'publish';
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