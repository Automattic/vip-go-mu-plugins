<?php
/**
 * Holds SocialFlow useful methods
 * This class is extended by SocialFlow class
 *
 * @package SocialFlow
 */


/**
 * Plugin Methods class
 *
 * This class holds useful methods commonly used. And is a parent class for SocialFlow class
 *
 * @since 0.1
 */
class SocialFlow_Methods {

	/**
	 * Check if user has authorized plugin
	 * 
	 * @since 2.1
	 * @access public
	 *
	 * @return bool authorized or not
	 */
	function is_authorized() {
		global $socialflow;

		return (bool) $socialflow->options->get( 'access_token', false );
	}

	/**
	 * Get SocialFlow api object, if necessary create new api object
	 * Arguments are not required, and if nothing is passed key and secret will be retrieved from options
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @param string oauth token key
	 * @param string oauth token secret
	 * @return object ( WP_SocialFlow | WP_Error ) return WP_SocialFlow object on success and WP_Error on failure
	 */
	function get_api( $token = '', $secret = '' ) {
		global $socialflow;

		// Maybe create new api object 
		if ( !isset( $socialflow->api ) ) {
			// Include api library
			require_once( SF_ABSPATH . '/libs/class-wp-socialflow.php' );

			if ( $socialflow->options->get( 'access_token' ) AND ( empty( $token ) OR empty( $secret ) ) ) {
				$tokens = $socialflow->options->get( 'access_token' );
				$token = $tokens['oauth_token'];
				$secret = $tokens['oauth_token_secret'];
			}

			// Catch error
			$socialflow->api = new WP_SocialFlow( SF_KEY, SF_SECRET, $token, $secret );
		}

		return $socialflow->api;
	}

	/**
	 * Get new view object
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string file name
	 * @param array of global data available in template
	 * @return object Plugin_View
	 */
	function get_view( $file = NULL, array $data = NULL ) {
		$view = new SF_Plugin_View( $file, $data );

		// Set directories
		$view->setAbspath( SF_ABSPATH . '/' );
		$view->setViewsDirname( 'views' );
		$view->render();

		return $view;
	}

	/**
	 * Get new view object
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string file name
	 * @param array of global data available in template
	 * @return object Plugin_View
	 */
	function render_view( $file = NULL, array $data = NULL ) {
		echo $this->get_view( $file, $data );
	}

	/**
	 * Parse statuses and return one WP_Error object of statuses 
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string serialized statuses
	 * @return object WP_Error with all statuses
	 */
	function parse_status( $statuses = '' ) {
		$return = new WP_Error;

		if ( empty( $statuses ) )
			return $return;

		$statuses = maybe_unserialize( $statuses );
		if ( !is_array( $statuses ) )
			return  $return;

		// Loop throug all statuses and explode each
		foreach ( $statuses as $code => $status ) {

			// Add Error messages
			$messages = is_array( $status[0] ) ? array_map( 'base64_decode', $status[0] ) : array();
			foreach ( $messages as $message )
				$return->add( $code, $message );

			// Add Error data if needed
			if ( $status[1] )
				$return->add_data( is_array( $status[1] ) ? array_map( 'base64_decode', $status[1] ) : base64_decode( $status[1] ), $code );
		}
		return $return;
	}

	/**
	 * Compress WP_Error object to string
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param object WP_Error $statuses
	 * @return string compressed statuses
	 */
	function compress_status( $statuses = '' ) {
		if ( !is_wp_error( $statuses ) )
			return '';
		$return = array();
		$codes = $statuses->get_error_codes();
		foreach ( $codes as $code ) {
			// Get all messages
			$messages = $statuses->get_error_messages( $code ) ? array_map( 'base64_encode', $statuses->get_error_messages( $code ) ) : '';
			// Get all data
			$data = '';
			if ( $statuses->get_error_data( $code ) )
				$data = is_array( $statuses->get_error_data( $code ) ) ? array_map( 'base64_encode', $statuses->get_error_data( $code ) ) : base64_encode( $statuses->get_error_data( $code ) );
			// compress data and messages
			$return[ $code ] = array( $messages, $data );
		}
		return maybe_serialize( $return );
	}

	/**
	 * Join array of statuses into one status
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param array of WP_Errors objects $statuses
	 * @param ( object | array of object ) $join_status second status to join may be single WP_Error object or array of WP_Error objects
	 * @return object WP_Error 
	 */
	function join_errors( $statuses = array(), $join_status = null ) {
		$return = new WP_Error;

		// If multiple arguments were passed join different wp errors
		if ( !empty( $join_status ) ) {
			if ( is_array( $statuses ) )
				$statuses[] = $join_status;
			else
				$statuses = array( $statuses, $join_status );
		}

		if ( empty( $statuses ) )
			return $return;

		// Loop through statuses
		foreach ( $statuses as $status ) :
			// Skip empty statuses
			if ( !is_wp_error( $status ) OR !$status->get_error_codes() )
				continue;

			foreach ( $status->get_error_codes() as $code ) :
				// Add messages first
				$messages = $status->get_error_messages( $code );

				// we need only unique messages
				if ( in_array( $code, $return->get_error_codes() ) )
					$messages = array_diff( $messages, $return->get_error_messages( $code ) );
				// add messages if they present
				if ( !empty( $messages ) )
					foreach ( $messages as $message )
						$return->add( $code, $message );

				// Add code data
				$data = $status->get_error_data( $code );

				// Join return data and our data
				if ( !empty( $data ) AND $return->get_error_data( $code ) ) {
					// add new data according to return data type
					if ( is_array( $return->get_error_data( $code ) ) ) {
						// passed data is array
						$data = array_merge( $data, $return->get_error_data( $code ) );
					}
					elseif ( is_array( $data ) ) {
						$data[] = $return->get_error_data( $code );
					}
					elseif ( is_array( $return->get_error_data( $code ) ) ) {
						$data = array_push( $return->get_error_data( $code ), $data );
					}
					elseif ( is_string( $data ) AND is_string( $return->get_error_data( $code ) ) ) {
						$data = $return->get_error_data( $code ) . $data;
					}
				}

				if ( !empty( $data ) )
					$return->add_data( $data, $code );

			endforeach; // Loop for each code inside status

		endforeach; // Loop for each passed statuses

		return $return;
	}

	/**
	 * Merges arrays recursively, replacing duplicate string keys
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param arrays to merge
	 * @return array merged
	 */
	function array_merge_recursive() {
		$args = func_get_args();

		$result = array_shift( $args );

		foreach ( $args as $arg ) {
			foreach ( $arg as $key => $value ) {
				// Renumber numeric keys as array_merge() does.
				if ( is_numeric( $key ) ) {
					if ( !in_array( $value, $result ) )
						$result[] = $value;
				}
				// Recurse only when both values are arrays.
				elseif ( array_key_exists( $key, $result ) && is_array( $result[$key] ) && is_array( $value ) ) {
					$result[$key] = self::array_merge_recursive( $result[$key], $value );
				}
				// Otherwise, use the latter value.
				else {
					$result[$key] = $value;
				}
			}
		}
		return $result;
	}

	/**
	 * Save Errors
	 *
	 * @param object wp_error object to save
	 * @param string error key
	 * @return object wp_error
	 */
	function save_errors( $key, $error ) {
		global $socialflow;

		$socialflow->errors[ $key ] = $error;

		// Remove previous errors for this key
		delete_transient( 'sf_error_' . $key );

		// Save transient
		$status = set_transient( 'sf_error_' . $key, $error, 60*5 );

		return $error;
	}

	/**
	 * Clear errors
	 */
	function clear_errors( $key ) {
		delete_transient( 'sf_error_' . $key );
	}

	/**
	 * Retrieve error by key
	 *
	 * @param string error key
	 * @return mixed ( object WP_Error | false ) 
	 */
	function get_errors( $key = '' ) {
		global $socialflow;
		
		if ( isset( $socialflow->errors[ $key ] ) ) {
			return $socialflow->errors[ $key ];
		}
		else {
			// Try to get error from transient
			$error = get_transient( 'sf_error_' . $key );
			if ( $error ) {
				$socialflow->errors[ $key ] = $error;
				return $error;
			}
		}

		return false;
	}

	/**
	 * Check if we are on passed SocialFlow settings page
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string page name to check for
	 * @return bool is target page
	 */
	function is_page( $pagename ) {
		global $current_screen;

		$cur_page = '';

		if ( isset( $_POST ) AND isset( $_POST['socialflow-page'] ) ) {
			$cur_page = $_POST['socialflow-page'];
		} elseif ( isset( $_GET ) AND isset( $_GET['socialflow'] ) ) {
			$cur_page = $_GET['page'];
		} elseif ( strpos( $current_screen->id, 'socialflow_page_' ) === 0 ) {
			$cur_page = substr( $current_screen->id, strlen( 'socialflow_page_' ) );
		}

		return $cur_page == $pagename ;
	}

	/**
	 * Before output errors
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param object - WP_Error
	 * @return object - filtered errors
	 */
	function filter_errors( $errors ) {
		global $socialflow;

		if ( is_wp_error( $errors ) AND $errors->get_error_messages() ) {
			
			$filtered_errors = new WP_Error();

			// loop through passed errors object codes filter messages and add them to the new object
			foreach ( $errors->get_error_codes() as $code ) :
				
				foreach ( $errors->get_error_messages( $code ) as $message ) :
					if ( 'http_request_failed' == $code ) {
						$filtered_errors->add( $code, __( '<b>Error:</b> Server connection timed out. Please, try again.', 'socialflow' ) );
						break;
					}

					// Check if there is some error data
					if ( $data = $errors->get_error_data( $code ) ) {

						// Error data may contain accounts ids
						if ( is_array( $data ) AND $accounts = $socialflow->accounts->get( $data ) ) {

							// Get string with accounts display names
							$names = array();
							foreach ( $accounts as $account ) {
								$names[] = $socialflow->accounts->get_display_name( $account );
							}

							// Add formatted error message
							if ( strpos( $message, '%s' ) ) {
								$filtered_errors->add( $code, sprintf( $message, implode( ', ', $names ) ) );
							} else {
								$filtered_errors->add( $code, $message . ' (' . implode( ', ', $names ) .') ' );
							}

						} elseif ( absint( $data ) AND $name = $socialflow->accounts->get_display_name( absint( $data ) ) ) {

							if ( strpos( $message, '%s' ) ) {
								$filtered_errors->add( $code, sprintf( $message, $name ) );
							} else {
								$filtered_errors->add( $code, $message . ' (' . $name .') ' );
							}
							
						} else {
							
							// Add as it is
							$filtered_errors->add( $code, $message, $data );
							
						} // Not accounts data
						
					} else {
						
					}

				endforeach; // Messages loop

			endforeach; // Error codes loop

			$errors = $filtered_errors;

		} // Is WP_Error and messages present

		return $errors;
	}

	/**
	 * Cut Strings (detects words)
	 *
	 * @return string
	 */
	function trim_chars( $string, $max_length = 5000 ){
		$string = strip_tags($string);
		if (strlen($string) > $max_length){
			$string = substr($string, 0, $max_length);
			$pos = strrpos($string, " ");
			if($pos === false) {
					return substr($string, 0, $max_length)."...";
			}
				return substr($string, 0, $pos)."...";
		}else{
			return $string;
		}
	}
}