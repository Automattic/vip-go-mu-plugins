<?php
/**
 * SocialFlow Accounts class
 *
 * @package SocialFlow
 */
class SocialFlow_Accounts {
	
	/**
	 * Active accounts ids
	 *
	 * @since 2.0
	 * @access private
	 * @var array
	 */
	var $active;

	/**
	 * Account ids from last query
	 *
	 * @since 2.0
	 * @access private
	 * @var array
	 */
	var $last;

	/**
	 * Default order for available account types
	 * 
	 * @var array
	 */
	static $type_order = array( 'twitter', 'facebook', 'google_plus', 'linkedin' );

	/**
	 * PHP5 Constructor
	 */
	function __construct(){}

	/**
	 * Retrieve array of accounts
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @param array filter query
	 * @return mixed ( array | bool ) Return array of accounts or false if none matched
	 * can also return single account if client_account_id is passed instead of query array
	 */
	function get( $query = array(), $post_type = 'post' ) {
		global $socialflow;

		$accounts = $socialflow->options->get( 'accounts', array() );

		// For attachments return accounts with specific types only
		if ( 'attachment' == $post_type ) {
			foreach ( $accounts as $key => $account ) {
				if ( !in_array( $account['account_type'], array( 'twitter', 'facebook_page', 'google_plus_page' ) ) )
					unset( $accounts[ $key ] );
			}
		}

		// return all acconts if empty query passed
		if ( empty( $query ) )
			return $accounts;

		// return single account if $query is int - client_account_id
		if ( is_int( $query ) ) {
			if ( array_key_exists( $query, $accounts ) ) {
				return $accounts[ $query ];
			} else {
				return false;
			}
		}

		// Check if array of account ids was passed
		if ( isset( $query[0] ) && is_int( $query[0] ) ) {
			if ( $intersect = array_intersect( array_keys( $accounts ), array_values( $query ) ) ) {
				foreach ( $accounts as $key => $value ) {
					if ( !in_array( $key, $intersect ) )
						unset( $accounts[ $key ] );
				}
				return $accounts;
			}
			return false;
		}

		// loop through query attributes and unset not matching accounts
		foreach ( $accounts as $key => $account ) {
			// check current account to match all qeuries
			foreach ( $query as $check ) {

				// To-Do add different comparison operators

				// break loop if query doesn't match
				if ( 
					!isset( $check[ 'key' ] ) OR !is_string( $check[ 'key' ] ) OR
					!isset( $account[ $check[ 'key' ] ] ) OR 
					( !is_array( $check[ 'value' ] ) AND !is_array( $account[ $check[ 'key' ] ] ) AND $account[ $check[ 'key' ] ] != $check[ 'value' ] )  OR
					( is_array( $check[ 'value' ] ) AND !is_array( $account[ $check[ 'key' ] ] ) AND !in_array( $account[ $check[ 'key' ] ], $check[ 'value' ] ) )
				) {
					unset( $accounts[ $key ] );
					break;
				}
			}
		}

		if ( empty( $accounts ) )
			$accounts = false;

		return $accounts;
	}

	/**
	 * Get active accounts
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @param string return accounts or only ids
	 * @return mixed ( array | bool ) array of accounts is returned if active attribute isset
	 */
	function get_active( $fields = 'all' ) {

		if ( ! $this->isset_active() )
			return false;

		if ( 'ids' == $fields )
			return $this->active;

		return $this->get( array(
			'client_account_id' => $this->active
		));
	}

	/**
	 * Set active accounts
	 *
	 * @since 2.1
	 * @access public
	 * @deprecated not used in plugin anymore
	 *
	 * @param array of query arguments passed to get() method
	 * @return bool accounts were found and active ids were set
	 */
	function set_active( $query = array() ) {

		// Get accounts by query
		$accounts = $this->get( $query );

		// $active atribute needs only ids
		if ( false != $accounts ) {
			$accounts = array_keys( $accounts );
		}

		return $accounts;
	}

	/**
	 * Check if active attribute isset
	 *
	 * @since 2.1
	 * 
	 * @return bool
	 */
	function isset_active() {
		return isset( $this->active );
	}

	/**
	 * Retrieve single account display name
	 * 
	 * @since 2.1
	 * @access public
	 *
	 * @param mixed ( array | int ) single account or account_id
	 * @param bool add type prefix or not
	 * @return string account display name
	 */
	function get_display_name( $account = array(), $add_prefix = true ) {
		$name = $prefix = '';

		// Get account if account id was passed
		$account = is_int( $account ) ? self::get( $account ) : $account;

		$type = self::get_global_type( $account );

		if ( empty( $type ) )
			return __( 'Missing account', 'socialflow' );

		// Retrieve account name depending on account type
		switch ( $type ) {
			case 'facebook':
				$name = $account['name'];
				$prefix = __('Facebook Wall ', 'socialflow');
				break;
			case 'twitter':
				$name = $account['screen_name'];
				$prefix = __('Twitter', 'socialflow') . ' @';
				break;
			case 'google_plus':
				$name = $account['name'];
				$prefix = __('Google+ ', 'socialflow');
				break;
			case 'linkedin':
				$name = $account['name'];
				$prefix = __('LinkedIn ', 'socialflow');
				break;
			default:
				$name = $account['name'];
				break;
		}

		return $add_prefix ? $prefix . $name : $name;
	}

	/**
	 * Group accounts by type
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param array accounts to group
	 * @return array grouped accounts
	 */
	function group_by( $type = 'global_type', $accounts = array(), $order = false ) {
		if ( empty( $accounts ) )
			return $accounts;

		$new = array();
		foreach ($accounts as $key => $account) {
			// Define
			$type = self::get_global_type($account);

			if (isset($new[$type]))
				$new[$type][] = $account;
			else
				$new[$type] = array($account);
		}
		$accounts = $new;

		if ( false == $order )
			return $accounts;

		$types = array_intersect( array_flip( self::$type_order ), array_keys( $accounts ) );

		return array_replace( $types, $accounts );
	}


	/**
	 * User Friendly type title
	 * @param  string $type Account type
	 * @return string       Account type title
	 */
	static function get_type_title( $type ) {
		switch ( $type ) {
			case 'google_plus':
				return 'Google+';
				break;

			case 'linkedin':
				return 'LinkedIn';
				break;

			default:
				return ucfirst( $type );
				break;
		}
	}

	/**
	 * Get global account type
	 * @param mixed account
	 * @return string account type
	 *
	 * @since 1.0
	 * @access public
	 */
	function get_global_type( $account ) {
		$type = is_array( $account ) ? $account['account_type'] : '';

		if ( strpos( $type, 'twitter' ) !== false )
			$type = 'twitter';
		elseif ( strpos( $type, 'facebook' ) !== false )
			$type = 'facebook';
		elseif ( strpos( $type, 'google_plus' ) !== false )
			$type = 'google_plus';
		elseif ( strpos( $type, 'linked_in' ) !== false )
			$type = 'linkedin';

		return $type;
	}

	/**
	 * Send message to accounts
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @param array of additional data for each account, array keys are client_account_id's
	 * @return mixed ( bool | object ) true on success and WP_Error on failure
	 */
	function compose( $data = array() ) {
		global $socialflow;

		// Validate data
		$data = self::valid_compose_data( $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$api = $socialflow->get_api();

		// We have valid api object and valid data,
		// but we still need to collect statuses from socialflow
		$statuses = $errors = $success = array();

		// Loop through data and send message to appropriate account
		foreach ( self::get( array_keys( $data ) ) as $account_id => $account ) {
			$statuses[] = $api->add_message( $account['service_user_id'], $account['account_type'], $data[ $account_id ], $account_id );
		}

		$errors = $success = array();

		// Find all errors in statuses
		foreach ( $statuses as $status ) {
			// Collect error statuses
			if ( is_wp_error( $status ) ) {
				$errors[] = $status;
			}
			// Collect success statuses
			else {
				$success[] = $status;
			}
		}

		if ( !empty( $errors ) ) {
			return $socialflow->join_errors( $errors );
		}

		return $success;
	}

	/**
	 * Validate passed data
	 * check for required variables and add some additional data
	 *
	 * @since 2.1
	 * @access private
	 *
	 * @param array
	 * @return mixed valid data or WP_Error object if some errors were found
	 */
	function valid_compose_data( $data ) {
		global $socialflow;
		$errors = array();

		// In fact passed data can't be empty but we will still check in this too
		if ( empty( $data ) ) {
			return new WP_Error( 'empty_data', __( 'Empty send data was passed', 'socialflow' ) );
		}

		$valid_data = array();

		foreach ( $data as $account_id => $values ) {

			$account = self::get( $account_id );
			$account_type = self::get_global_type( $account );

			// check for required fields
			if ( empty( $values['message'] ) && $account_type !== 'google_plus' ) {
				$errors[] = new WP_Error( 'empty_message:', __( '<b>Error:</b> Message field is required for: <i>%s</i>.' ), array( $account_id ) );
			}

			// Reset total message length
			$total_len = 0;

			// Add message to valid data array
			$valid_data[ $account_id ]['message'] = self::valid_text( $values['message'], 'message', $total_len );

			// Add publish option
			$valid_data[ $account_id ]['publish_option'] = $values['publish_option'];

			// Validate some passed data
			switch ( $values['publish_option'] ) {
				case 'schedule':
					if ( empty( $values['scheduled_date'] ) ) {
						$errors[] = new WP_Error( 'empty_scheduled_date:', __( '<b>Error:</b> Scheduled date is required for schedule publish option for: <i>%s.</i>' ), array( $account_id ) );
					} else {

						$values['scheduled_date'] = ( current_time('timestamp') > strtotime( $values['scheduled_date'] ) ) ? strtotime( "+1 minute", current_time('timestamp') ) : strtotime( $values['scheduled_date'] );

						$valid_data[ $account_id ]['scheduled_date'] = date( 'Y-m-d H:i:s O', $values['scheduled_date'] - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
					}
					break;
				case 'optimize':

					// Set optimize start/end date depending on optimize_period
					if ( $values['optimize_period'] == 'range' ) {

						// Start and end dates are not required
						if ( !empty( $values['optimize_start_date'] ) ) {

							$values['optimize_start_date'] = ( current_time('timestamp') > strtotime( $values['optimize_start_date'] ) ) ? strtotime( '+1 minute', current_time( 'timestamp' ) ) : strtotime( $values['optimize_start_date'] );
							$valid_data[ $account_id ]['optimize_start_date'] = date( 'Y-m-d H:i:s O', $values['optimize_start_date'] - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
						}
						if ( !empty( $values['optimize_end_date'] ) ) {

							$values['optimize_end_date'] = ( current_time('timestamp') > strtotime( $values['optimize_end_date'] ) ) ? strtotime( '+10 minute', current_time( 'timestamp' ) ) : strtotime( $values['optimize_end_date'] );
							$valid_data[ $account_id ]['optimize_end_date'] = date('Y-m-d H:i:s O', $values['optimize_end_date'] - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
						}

						// Validate optimize period
						if ( !empty( $values['optimize_end_date'] ) AND !empty( $values['optimize_start_date'] ) ) {
							if ( $values['optimize_end_date'] < $values['optimize_start_date'] ) {
								$errors[] = new WP_Error( 'invalid_optimize_period:', __( '<b>Error:</b> Invalid optimize period for <i>%s.</i>' ), array( $account_id ) );
							}
						}

					} elseif ( $values['optimize_period'] != 'anytime' ) {
						$valid_data[ $account_id ]['optimize_start_date'] = date( 'Y-m-d H:i:s O', strtotime( '+1 minute', current_time( 'timestamp', get_option( 'gmt_offset' ) ) ) );
						$valid_data[ $account_id ]['optimize_end_date'] = date( 'Y-m-d H:i:s O', strtotime( '+' . $values['optimize_period'], current_time( 'timestamp', get_option( 'gmt_offset' ) ) ) );
					}

					$valid_data[ $account_id ]['must_send'] = isset( $values['must_send'] ) ? absint( $values['must_send'] ) : 0;
					break;
			}

			// check for special fields
			if ( isset( $values['content_attributes'] ) ) {

				if ( isset( $values['content_attributes']['name'] ) ) {
					$values['content_attributes']['name'] = self::valid_text( $values['content_attributes']['name'], 'name', $total_len );
				}
				if ( isset( $values['content_attributes']['description'] ) ) {
					$values['content_attributes']['description'] = self::valid_text( $values['content_attributes']['description'], 'description', $total_len );
				}

				$valid_data[ $account_id ]['content_attributes'] = json_encode( $values['content_attributes'] );
			}

			// Custom image
			if ( isset( $values['media_thumbnail_url'] ) ) {
				$valid_data[ $account_id ]['media_thumbnail_url'] = $values['media_thumbnail_url'];

				if ( isset( $values['media_filename'] ) ) {
					$valid_data[ $account_id ]['media_filename'] = $values['media_filename'];
				}
			}

			// add additianal fields
			$valid_data[ $account_id ]['created_by']    = get_user_option( 'user_email', get_current_user_id() );
			$valid_data[ $account_id ]['shorten_links'] = absint( $socialflow->options->get( 'shorten_links' ) );
		}

		// Return error instead of valid data
		if ( !empty( $errors ) ) {
			return $socialflow->join_errors( $errors );
		}

		return $valid_data;
	}

	/**
	 * Validate text before sending to SocialFlow
	 * @param  string $text input text
	 * @return string       validated text
	 */
	function valid_text( $text, $name, &$total_len = 0 ) {
		global $socialflow;

		// Decode html entities
		$text = wp_specialchars_decode( $text, ENT_QUOTES );

		switch ( $name ) {
			case 'message':
				$text = $socialflow->trim_chars( $text, 4200 );
				break;
			case 'name' :
				$text = $socialflow->trim_chars( $text, 500 );
				break;
			case 'description' :
				$text = $socialflow->trim_chars( $text, 5000 - $total_len );
				break;
			// no default case
		}

		$total_len += strlen( $text );

		return $text;
	}
}