<?php

/**
 * Payment Method Class for CampTix
 *
 * @since 1.2
 */
abstract class CampTix_Payment_Method extends CampTix_Addon {
	public $id = false;
	public $name = false;
	public $description = false;
	public $supported_currencies = false;
	public $supported_features = array(
		'refund-single' => false,
		'refund-all' => false,
	);

	/**
	 * Constructor
	 */
	function __construct() {
		/** @var $camptix CampTix_Plugin */
		global $camptix;

		parent::__construct();

		add_filter( 'camptix_available_payment_methods', array( $this, '_camptix_available_payment_methods' ) );
		add_filter( 'camptix_validate_options', array( $this, '_camptix_validate_options' ) );
		add_filter( 'camptix_get_payment_method_by_id', array( $this, '_camptix_get_payment_method_by_id' ), 10, 2 );

		$payment_method = get_class( $this );

		if ( ! $this->id ) {
			wp_die( "ID not specified in $payment_method." );
		}

		if ( ! $this->name ) {
			wp_die( "Name not specified in $payment_method." );
		}

		if ( ! $this->description ) {
			wp_die( "Description not specified in $payment_method." );
		}

		if ( ! is_array( $this->supported_currencies ) || count( $this->supported_currencies ) < 1 ) {
			wp_die( "Supported currencies not specified in $payment_method." );
		}

		$this->camptix_options = $camptix->get_options();
	}

	/**
	 * Handle calls to inaccessible methods
	 *
	 * In the past, this class duplicated some methods from the CampTix_Plugin class, and had wrappers that would
	 * do nothing except call public methods in the CampTix_Plugin class. That is unnecessary and undesirable,
	 * though, as those methods should just be called directly. Those methods were removed, and this method was added
	 * to maintain backwards-compatibility with any addons that are still calling the methods on this class.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed the function result, or false on error.
	 */
	public function __call( $name, $arguments ) {
		/** @var $camptix Camptix_Plugin */
		global $camptix;

		// Whitelist the methods we want to use to avoid unintentionally calling CampTix_Plugin methods in case of typos, etc
		$camptix_methods = array( 'payment_result', 'redirect_with_error_flags', 'error_flag', 'get_tickets_url', 'log', 'field_text', 'field_checkbox', 'field_yesno' );

		if ( in_array( $name, $camptix_methods ) ) {
			// Set a default value for the log $module parameter
			if ( 'log' == $name && empty( $arguments[4] ) ) {
				$arguments[4] = 'payment';
			}

			return call_user_func_array( array( $camptix, $name ), $arguments );
		} else {
			trigger_error( sprintf( 'Call to undefined method %s::%s()', get_class( $this ), $name ), E_USER_ERROR );
		}
	}

	/**
	 * Check if the payment method supports the given currency
	 *
	 * @param string $currency
	 *
	 * @return bool
	 */
	function supports_currency( $currency ) {
		return in_array( $currency, $this->supported_currencies );
	}

	/**
	 * Check if the payment method supports the given feature
	 *
	 * @param string $feature
	 *
	 * @return bool
	 */
	function supports_feature( $feature ) {
		return array_key_exists( $feature, $this->supported_features ) ? $this->supported_features[ $feature ] : false;
	}

	/**
	 * Get the payment gateway object for the given ID
	 *
	 * @param CampTix_Payment_Method $payment_method
	 * @param string                 $id
	 *
	 * @return CampTix_Payment_Method
	 */
	function _camptix_get_payment_method_by_id( $payment_method, $id ) {
		if ( $this->id == $id ) {
			$payment_method = $this;
		}

		return $payment_method;
	}

	/**
	 * Render the section header markup on the Payment screen
	 */
	function _camptix_settings_section_callback() {
		echo '<p>' . $this->description . '</p>';
		printf( '<p>' . __( 'Supported currencies: <code>%s</code>.', 'camptix' ) . '</p>', implode( '</code>, <code>', $this->supported_currencies ) );
	}

	/**
	 * Render the markup for the Enabled button
	 *
	 * @param array $args
	 */
	function _camptix_settings_enabled_callback( $args = array() ) {
		/** @var $camptix CampTix_Plugin */
		global $camptix;

		if ( in_array( $this->camptix_options['currency'], $this->supported_currencies ) ) {
			$camptix->field_yesno( $args );
		} else {
			_e( 'Disabled', 'camptix' );

			?>

			<p class="description">
				<?php printf(
					__( '%s is not supported by this payment method.', 'camptix' ),
					'<code>' . $this->camptix_options['currency'] . '</code>'
				); ?>
			</p>

			<?php
		}
	}

	/**
	 * Validate options if they were submitted for this payment method
	 *
	 * @param array $camptix_options
	 *
	 * @return array
	 */
	function _camptix_validate_options( $camptix_options ) {
		$post_key = "camptix_payment_options_{$this->id}";
		$option_key = "payment_options_{$this->id}";

		if ( ! isset( $_POST[ $post_key ] ) ) {
			return $camptix_options;
		}

		$input = $_POST[ $post_key ];
		$output = $this->validate_options( $input );
		$camptix_options[ $option_key ] = $output;

		return $camptix_options;
	}

	/**
	 * Validate new option values before saving
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	function validate_options( $input ) {
		return array();
	}

	/**
	 * Handle the checkout process
	 *
	 * @param string $payment_token
	 *
	 * @return int A payment status, e.g., PAYMENT_STATUS_CANCELLED, PAYMENT_STATUS_COMPLETED, etc
	 */
	abstract function payment_checkout( $payment_token );

	/**
	 * Handle the refund process
	 *
	 * @param string $payment_token
	 *
	 * @return int A payment status, e.g., PAYMENT_STATUS_CANCELLED, PAYMENT_STATUS_COMPLETED, etc
	 */
	function payment_refund( $payment_token ) {
		/** @var $camptix Camptix_Plugin  */
		global $camptix;

		$refund_data = array();
		$camptix->log( __FUNCTION__ . ' not implemented in payment module.', 0, null, 'refund' );

		return $this->payment_result( $payment_token, CampTix_Plugin::PAYMENT_STATUS_REFUND_FAILED, $refund_data );
	}

	/**
	 * Send a request for a refund to the payment gateway API
	 *
	 * @param string $payment_token
	 *
	 * @return array
	 */
	function send_refund_request( $payment_token ) {
		/** @var $camptix Camptix_Plugin  */
		global $camptix;

		$result = array(
			'token' => $payment_token,
			'status' => CampTix_Plugin::PAYMENT_STATUS_REFUND_FAILED,
			'refund_transaction_id' => null,
			'refund_transaction_details' => array()
		);

		$camptix->log( __FUNCTION__ . ' not implemented in payment module.', 0, null, 'refund' );
		return $result;
	}

	/**
	 * Register settings for the Payment screen
	 */
	function payment_settings_fields() {
	}

	/**
	 * Add the current payment method to the list of available methods
	 *
	 * @param array $payment_methods
	 *
	 * @return array
	 */
	function _camptix_available_payment_methods( $payment_methods ) {
		if ( $this->id && $this->name && $this->description ) {
			$payment_methods[ $this->id ] = array(
				'name' => $this->name,
				'description' => $this->description,
			);
		}

		return $payment_methods;
	}

	/**
	 * Get the order for the given payment token
	 *
	 * @param string $payment_token
	 *
	 * @return array
	 */
	function get_order( $payment_token = false ) {
		if ( ! $payment_token ) {
			return array();
		}

		$attendees = get_posts( array(
			'posts_per_page' => 1,
			'post_type'      => 'tix_attendee',
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => 'tix_payment_token',
					'compare' => '=',
					'value'   => $payment_token,
					'type'    => 'CHAR',
				),
			),
		) );

		if ( ! $attendees ) {
			return array();
		}

		return $this->get_order_by_attendee_id( $attendees[0]->ID );
	}

	/**
	 * Get the order for the given attendee
	 *
	 * @param int $attendee_id
	 *
	 * @return array
	 */
	function get_order_by_attendee_id( $attendee_id ) {
		$order = (array) get_post_meta( $attendee_id, 'tix_order', true );

		if ( $order ) {
			$order['attendee_id'] = $attendee_id;
		}

		return $order;
	}

	/**
	 * Get an escaped field name for a setting
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	function settings_field_name_attr( $name ) {
		return esc_attr( "camptix_payment_options_{$this->id}[{$name}]" );
	}

	/**
	 * Add a setting field
	 *
	 * @param string $option_name
	 * @param string $title
	 * @param string $callback
	 * @param string $description
	 */
	function add_settings_field_helper( $option_name, $title, $callback, $description = '' ) {
		add_settings_field(
			'camptix_payment_' . $this->id . '_' . $option_name,
			$title,
			$callback,
			'camptix_options',
			'payment_' . $this->id,
			array(
				'name' => $this->settings_field_name_attr( $option_name ),
				'value' => $this->options[ $option_name ],
				'description' => $description,
		) );
	}

	/**
	 * Get this payment method's options
	 *
	 * @return array
	 */
	function get_payment_options() {
		$payment_options = array();
		$option_key = "payment_options_{$this->id}";

		if ( isset( $this->camptix_options[ $option_key ] ) ) {
			$payment_options = (array) $this->camptix_options[ $option_key ];
		}

		return $payment_options;
	}
}
