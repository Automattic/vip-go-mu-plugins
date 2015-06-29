<?php
/*
Plugin Name: Easy Custom Fields
Plugin Script: easy-custom-fields.php
Plugin URI: http://wordpress.org/extend/plugins/easy-custom-fields/
Description: A set of extendable classes for easy Custom Field Handling
Version: 0.6
Author: Thorsten Ott
Author URI: http://automattic.com
*/
/*
Simple Usage example via functions.php: 

wpcom_vip_load_plugin( 'easy-custom-fields' );

$field_data = array (
	'testgroup' => array (				// unique group id
		'fields' => array(				// array "fields" with field definitions
			'field1'	=> array(),		// globally unique field id
			'field2'	=> array(),
			'field3'	=> array(),
		),
	),
);
$easy_cf = new Easy_CF($field_data);

Advanced Usage example via functions.php:

wpcom_vip_load_plugin( 'easy-custom-fields' );

$field_data = array (
	'testgroup' => array (
		'fields' => array(
			'field1'	=> array(),
			'field2'	=> array(),
			'field3'	=> array(),
		),
	),
	'advanced_testgroup' => array (										// unique group id
		'fields' => array(												// array "fields" with field definitions 
			'advanced_field'	=> array(								// globally unique field id
				'label' 		=> 'Advanced Field Description',		// Field Label
				'hint'			=> 'Long Advanced Field description',	// A descriptive hint for the field
				'type' 			=> 'textarea',							// Custom Field Type (see Ref: field_type)
				'class'			=> 'aclass',							// CSS Wrapper class for the field
				'input_class' 	=> 'theEditor',							// CSS class for the input field
				'error_msg' 	=> 'The Advanced Field is wrong' ),		// Error message to show when validate fails
				'validate'		=> 'validatorname',						// Custom Validator (see Ref: validator)
			'advanced_email' => array(
				'label' => 'Email',
				'hint' => 'Enter your email',
				'validate' => 'email', )
		),
		'title' => 'Product Description',	// Group Title
		'context' => 'advanced',			// context as in http://codex.wordpress.org/Function_Reference/add_meta_box
		'pages' => array( 'post', 'page' ),	// pages as in http://codex.wordpress.org/Function_Reference/add_meta_box
	),
);

if ( !class_exists( "Easy_CF_Validator_Email" ) ) {

	class Easy_CF_Validator_Email extends Easy_CF_Validator {
		public function get( $value='' ) {
			return esc_attr( $value );
		}

		public function set( $value='' ) {
			$value = esc_attr( trim( stripslashes( $value ) ) );
			return $value;
		}

		public function validate( $value='' ) {
			if ( empty( $value ) || is_email( $value ) ) 
				return true;
			else
				return false;
		}
	}
}

if ( !class_exists( "Easy_CF_Field_Textarea" ) ) {
	class Easy_CF_Field_Textarea extends Easy_CF_Field {
		public function print_form() {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' :  $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' :  $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] :  $this->_field_data['id'];
			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] :  $this->_field_data['label'];
			$value = $this->get();
			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' :  '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$label_format =
				'<div class="%s">'.
				'<p><label for="%s"><strong>%s</strong></label></p>'.
				'<p><textarea class="%s" style="width: 100%%;" type="text" name="%s">%s</textarea></p>'.
				'%s'.
				'</div>';
			printf( $label_format, $class, $id, $label, $input_class, $id, $value, $hint );
		}
	}
}

$easy_cf = new Easy_CF($field_data);

*/

/*
Note:
If not using auto_init then meta boxes need to be added individually using
add_meta_box( $group_id, $group_title, array( &$easy_cf, 'meta_box_cb' ), $page, $group_context );
and the save methods need to be initialized after adding all meta boxes using
$easy_cf->add_save_method();
*/

if ( !class_exists( "Easy_CF_Field" ) ) {
	/*
	 * Default Field Type
	 * Extend this class in order to add additional field types
	 * Usually it's only necessary to redefine the print_form() method to adapt to different fields
	 */ 
	class Easy_CF_Field {
		protected $validator = null;
		protected $_field_data = array();

		public function __construct( $field_data ) {
			$this->_field_data = $field_data;
			$this->init();
		}

		/*
		 * Output the form for the meta box
		 */
		public function print_form() {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' :  $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' :  $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] :  $this->_field_data['id'];
			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] :  $this->_field_data['label'];
			$value = $this->get();
			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' :  '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$label_format =
				'<div class="%s">'.
				'<p><label for="%s"><strong>%s</strong></label></p>'.
				'<p><input class="%s" style="width: 100%%;" type="text" name="%s" value="%s" /></p>'.
				'%s'.
				'</div>';
			printf( $label_format, $class, $id, $label, $input_class, $id, $value, $hint );
		}

		/*
		 * Get the field value and return it
		 * @param $post_id integer Post Id or empty value to default to the current post
		 * @param $raw boolean Return raw
		 * @return mixed field value
		 */
		public function get( $post_id='', $raw=false ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			} 
			
			$value = get_post_meta( $post_id, $this->_field_data['id'], true );
			if ( is_wp_error( $value ) ) {
				$this->add_admin_notice( sprintf( __( "Could not receive field %s for post_id %s" ), $this->_field_data['id'], $post_id ) );
				return false;
			}
			
			if ( is_callable( array( &$this->validator, 'get' ) ) && false == $raw )
				return $this->validator->get( $value );
			else 
				return $value;
		}
		
		/*
		 * Get the ID for the current Post
		 * @return current post ID
		 */
		public function get_post_id() {
			global $post;
			$post_id = $post->ID;
			return $post_id;
		}

		/*
		 * Set the value for this field
		 * @param $value mixed value to set for the field
		 * @param $post_id integer Post Id or empty value to default to the current post
		 * @return mixed result of update_post_meta()
		 */
		public function set( $value, $post_id='' ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			} 
			
			if ( is_callable( array( &$this->validator, 'set' ) ) )
				$value = $this->validator->set( $value );
			
			$result = update_post_meta( $post_id, $this->_field_data['id'], $value );
			return $result;
		}
		
		/* 
		 * Delete the value
		 * @param $post_id integer Post Id or empty value to default to the current post
		 * @return result of delete_post_meta()
		 */
		public function delete( $post_id='' ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			} 
			$result = delete_post_meta( $post_id, $this->_field_data['id'] );
			return $result;
		}
		
		/* 
		 * Validate the value
		 * @param $value mixed value to verify
		 * @return boolean true on success false on failure
		 */
		public function validate( $value ) {
			if ( is_callable( array( &$this->validator, 'validate' ) ) )
				return $this->validator->validate( $value );
			else
				return true;
		}
		
		/*
		 * Get error message for this field
		 * @return string error message
		 */
		public function get_error_msg() {
			if ( !empty( $this->_field_data['error_msg'] ) )
				return __( $this->_field_data['error_msg'] );
			else if ( !empty( $this->_field_data['label'] ) )
				return sprintf( __( 'Could not validate %s' ), $this->_field_data['label'] );
			else 
				return sprintf( __( 'Could not validate %s' ), $this->_field_data['id'] );
		}
		
		public function setValidator(Easy_CF_Validator $validator)
		{
			$this->validator = $validator;
		}

		protected function init( ) {

		}
	}
}

if ( !class_exists( "Easy_CF_Validator" ) ) {
	/*
	 * Default validator class
	 * makes sure values are sanitized and validated
	 * uses kses for input sanitation and esc_attr for output sanitation
	 * extend to your needs by redefining get(), set(), validate()
	 * classname should be Easy_CF_Validator_ . ucfirst( 'validator_name' ).
	 * eg: Easy_CF_Validator_Email
	 */
	class Easy_CF_Validator {
		public function __construct() {
			$this->init();
		}

		protected function init() {
		}

		/*
		 * Return a sanitized value for output
		 */
		public function get( $value='' ) {
			return esc_attr( $value );
		}

		/* 
		 * Return a sanitized value for writing user submitted data to the database
		 */
		public function set( $value='' ) {
			$value = wp_filter_post_kses( $value );
			$value = trim( stripslashes( $value ) );
			return $value;
		}

		/* 
		 * Validate a value and return true/false
		 */
		public function validate( $value='' ) {
			if ( $this->set( $value ) == trim( stripslashes( $value ) ) ) 
				return true;
			else
				return false;
		}
	}
}

if ( !class_exists( "Easy_CF" ) ) {
	class Easy_CF {

		/**
		 * Stores all initialized field objects
		 */
		protected $field_objects = array();

		/**
		 * Initial data to work with
		 */
		protected $_fields_meta = array();

		private $_field_data = array();
		private $_plugin_prefix = 'ecf';
		private $_admin_notices = array();
		private $_auto_init = true;
		private $_used_fields = array();
		private $_nonce_flag = array();
		private $_fields = array();

		public function __construct($fields_meta, $auto_init=true) {
			$this->_fields_meta = $fields_meta;
			$this->_auto_init = $auto_init;
			$this->_admin_notices = get_transient( $this->_plugin_prefix . '_notices' );
			if ( !is_array( $this->_admin_notices ) ) 
				$this->_admin_notices = array();
			$this->_admin_notices = array_unique( $this->_admin_notices );
			
			if ( is_admin() ) {
				$this->backend_init();
			} else {
				$this->frontend_init();
			}
			
			$this->init();
		}

		public function __destruct() {

		}

		/* Initialize */
		protected function init() {
		}
		
		/*
		 * Callback functions for creating post meta boxes
		 * Can be used as callback in add_meta_box()
		 */
		public function meta_box_cb( $object='', $box='' ) {
			// Run once
			
			if ( ! isset( $this->_nonce_flag[$box['id']] ) || ! $this->_nonce_flag[$box['id']] ) {
				$this->print_nonce( $box['id'] );
				$this->_nonce_flag[$box['id']] = true;
			}
			
			// Generate box contents
			if ( !isset( $this->_field_data[$box['id']] ) ) {
				$this->add_admin_notice( sprintf( __( "No group %s exists" ), $box['id'] ) );
				return false;
			}
			
			if ( empty( $this->_field_data[$box['id']]['fields'] ) ) {
				$this->add_admin_notice( sprintf( __( "Group %s does not have any fields" ), $box['id'] ) );
				return false;
			}
			
			foreach( (array) $this->_field_data[$box['id']]['fields'] as $field_id => $field_data ) {
					$this->{$field_id}->print_form();
			}
		}

		/* 
		 * Initialize backend functions
		 * - register_admin_panel
		 * - admin_header
		 */
		protected function backend_init() {
			add_action( 'admin_menu', array( &$this, 'register_admin_panel' ) );
			add_action( 'admin_head', array( &$this, 'admin_header' ) );
		}

		/*
		 * Initialize frontend functions
		 * - parse field data
		 */
		protected function frontend_init() {
			$this->parse_field_data();
		}
		
		/*
		 * Parse fields and initialize meta boxes and save method for auto init
		 */
		public function register_admin_panel() {
			$this->parse_field_data();
			if ( $this->_auto_init ) {
				$this->add_meta_boxes();
				$this->add_save_method();
			}
		}
		
		/*
		 * Add meta boxes for defined fields
		 */
		public function add_meta_boxes() {
			foreach( (array) $this->_field_data as $group_id => $group_data ) {
				$group_title = ( empty( $group_data['title'] ) ) ? $group_id : $group_data['title'];
				$group_context = ( empty( $group_data['context'] ) ) ? 'advanced' : $group_data['context'];
				$group_pages = ( empty( $group_data['pages'] ) ) ? array( 'post', 'page' ) : $group_data['pages'];
				foreach( (array) $group_pages as $page ) {
					add_meta_box( $group_id, $group_title, array( &$this, 'meta_box_cb' ), $page, $group_context );
				}
			}
		}
		
		/* 
		 * Hook in the save method.
		 * This function depends on initialized fields and should be called after adding meta boxes
		 */
		public function add_save_method() {
			add_action( 'save_post', array( &$this, 'save_post_cb' ), 1, 2 );
		}
		
		/*
		 * Callback function for hooking into save_post action
		 * initialize via $this->add_save_method() or $easy_cf->add_save_method()
		 * or similar add_action( 'save_post', array( &$this, 'save_post_cb' ), 1, 2 ); call
		 */
		public function save_post_cb($post_id, $post) {
			foreach( (array) $this->_used_fields as $box_id => $field_ids ) {
				// bypass fields which are not used in this group
				$access = ( empty( $this->_field_data[$box_id]['pages'] ) ? array( 'post', 'page' ) : (array) $this->_field_data[$box_id]['pages'] );
				if ( !in_array( $post->post_type, $access ) )
					continue;
					
				if ( ( ! isset($_REQUEST[$this->_plugin_prefix . '_' . $box_id . '_nonce']) ) || ( ! wp_verify_nonce( $_REQUEST[$this->_plugin_prefix . '_' . $box_id . '_nonce'], $this->_plugin_prefix . '_' . $box_id . '_nonce' ) ) ) {
					return $post->ID;
				}

				if ( 'revision' == $post->post_type  ) {
					// don't store custom data twice
					return;
				}
					
				$post_type_object = get_post_type_object($_POST['post_type']);
				if ( empty( $post_type_object ) || is_wp_error( $post_type_object ) )
					return $post->ID;
				
				// Is the user allowed to edit the post or page?
				if ( !current_user_can( $post_type_object->cap->edit_posts ) )
					return $post->ID;
				
				// Add values of $my_data as custom fields
				// Let's cycle through the $my_data array!
				
				foreach ( (array) $field_ids as $field_id ) {
					if ( isset( $_POST[$field_id] ) ) {
						$value = $_POST[$field_id];
						if ( !$this->{$field_id}->validate( $value, $post->ID ) ) {
							$this->add_admin_notice( $this->{$field_id}->get_error_msg() );
							continue;
						}
						$this->{$field_id}->set( $value, $post->ID );
					} else {
						// delete blanks
						$this->{$field_id}->delete( $post->ID );
					}
				}				
			}
			return $post->ID;
		}
		
		/*
		 * Add necessary header scripts 
		 * Currently only used for admin notices
		 */
		public function admin_header() {
			// print admin notice in case of notice strings given
			if ( !empty( $this->_admin_notices ) ) {
					add_action('admin_notices' , array( &$this, 'print_admin_notice' ) );
			}
		}
		
		/* 
		 * Output nonce for the group
		 */
		public function print_nonce( $box_id = '' ) {
				echo sprintf( '<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />', $this->_plugin_prefix . '_' . $box_id . '_nonce', wp_create_nonce( $this->_plugin_prefix . '_' . $box_id . '_nonce' ) );
		}

		protected function init_fields( $field_data = array() ) {

		}

		/* 
		 * Validate the field data 
		 * Show admin notices for invalid data and skip initializing these items
		 */
		protected function parse_field_data() {
			$field_data = $this->_fields_meta;
			
			// validate data, make sure to fill $this->_field_data only with validated fields

			foreach( (array) $field_data as $group_id => $group_data ) {
				// check group_id
				if ( !preg_match( "#^[a-zA-Z0-9_\-]+$#msiU", $group_id ) ) {
					$this->add_admin_notice( sprintf( __( "Group %s contains invalid chars use only [a-zA-Z0-9_-]" ), $group_id ) );
					continue;
				}

				// check fields
				if ( !is_array( $group_data['fields'] ) || empty( $group_data['fields'] ) ) {
					$this->add_admin_notice( sprintf( __( "Group %s does not contain any fields" ), $group_id ) );
					continue;
				}

				// check title
				if ( !empty( $group_data['title'] ) && !preg_match( "#^[a-zA-Z0-9_\-\s]+$#miU", $group_data['title'] ) ) {
					$this->add_admin_notice( sprintf( __( "Group Title %s for group %s contains invalid chars use only [a-zA-Z0-9_- ]" ), $group_data['title'], $group_id ) );
					continue;
				}

				// check class
				if ( !empty( $group_data['class'] ) && !preg_match( "#^[a-zA-Z0-9_\-\s]+$#miU", $group_data['class'] ) ) {
					$this->add_admin_notice( sprintf( __( "Group Class %s for group %s contains invalid chars use only [a-zA-Z0-9_- ]" ), $group_data['title'], $group_id ) );
					continue;
				}

				$_fields = array();

				// check fields array
				foreach( (array) $group_data['fields'] as $field_id => $field ) {

					$_fields[$field_id] = array( 'id' => $field_id, 'label' => null, 'hint' => null, 'class' => null, 'type' => null, 'validate' => null, 'error_msg' => null, 'input_class' => null );
					
					// check field id
					if ( empty( $field_id ) ) {
						$this->add_admin_notice( sprintf( "Field id in group %s not set", $group_id ) );
						continue;
					} elseif ( !preg_match( "#^[a-zA-Z0-9_\-]+$#miU", $field_id ) ) {
						$this->add_admin_notice( sprintf( __( "Field id %s for group %s contains invalid chars use only [a-zA-Z0-9_-]" ), $field_id, $group_id ) );
						continue;
					}

					foreach($field as $field_name => $field_value){
	
						// check label
						if ( !empty( $field_value ) && $field_name == 'label' && !preg_match( "#^[a-zA-Z0-9_\-\s:,]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field label %s for group %s contains invalid chars use only [a-zA-Z0-9_-\s:,]" ), $field_value, $group_id ) );
							continue;
						}
	
						// check hint
						if ( !empty( $field_value ) && $field_name == 'hint' && !preg_match( "#^[a-zA-Z0-9_\-\s:/\.,]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field hint %s for group %s contains invalid chars use only [a-zA-Z0-9_-\s:/\.,]" ), $field_value, $group_id ) );
							continue;
						}
	
						// check error_msg
						if ( !empty( $field_value ) && $field_name == 'error_msg' && !preg_match( "#^[a-zA-Z0-9_\-\s:/\.,]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field error_msg %s for group %s contains invalid chars use only [a-zA-Z0-9_-\s:/\.,]" ), $field_value, $group_id ) );
							continue;
						}
	
						// check class
						if ( !empty( $field_value ) && $field_name == 'class' && !preg_match( "#^[a-zA-Z0-9_\-\s]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field class %s for group %s contains invalid chars use only [a-zA-Z0-9_-\s]" ), $field_value, $group_id ) );
							continue;
						}
	
						// check input_class
						if ( !empty( $field_value ) && $field_name == 'input_class' && !preg_match( "#^[a-zA-Z0-9_\-\s]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field input_class %s for group %s contains invalid chars use only [a-zA-Z0-9_-\s]" ), $field_value, $group_id ) );
							continue;
						}
						
						// check type
						if ( !empty( $field_value ) && $field_name == 'type' && !preg_match( "#^[a-zA-Z0-9_]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field type %s for group %s contains invalid chars use only [a-zA-Z0-9_-]" ), $field_value, $group_id ) );
							continue;
						}
	
						// check validate
						if ( !empty( $field_value ) && $field_name == 'validate' && !preg_match( "#^[a-zA-Z0-9_]+$#miU", $field_value ) ) {
							$this->add_admin_notice( sprintf( __( "Field validator %s for group %s contains invalid chars use only [a-zA-Z0-9_-]" ), $field_value, $group_id ) );
							continue;
						}
						
						$_fields[$field_id][$field_name] = $field_value;
					}
				}

				$this->_field_data[$group_id] = array(
					'title'  => ( ! empty( $group_data['title'] ) ) ? $group_data['title'] : null,
					'class'  => ( ! empty( $group_data['class'] ) ) ? $group_data['class'] : null,
					'fields' => $_fields,
					'pages'  => ( ! empty( $group_data['pages'] ) ) ? $group_data['pages'] : null,
					'context'  => ( ! empty( $group_data['context'] ) ) ? $group_data['context'] : null,					
				);
			}


			// initialize classes for validated data
			foreach( (array) $this->_field_data as $group_id => $group_data ) {

				foreach( (array) $group_data['fields'] as $field_id => $field ) {
					$field_class_name = 'Easy_CF_Field_' . ucfirst( $field['type'] );
					if ( !class_exists( $field_class_name ) )
						$field_class_name = 'Easy_CF_Field';

					$validate_class_name = 'Easy_CF_Validator_' . ucfirst( $field['validate'] );
					if ( !class_exists( $validate_class_name ) )
						$validate_class_name = 'Easy_CF_Validator';

					$this->{$field_id} = new $field_class_name( $field );
					$this->{$field_id}->setValidator(new $validate_class_name());
					
					$this->_used_fields[$group_id][] = $field_id;
				}

			}

		}

		protected function add_admin_notice( $message ) {
			$this->_admin_notices[] = $message;
			set_transient( $this->_plugin_prefix . '_notices', $this->_admin_notices, 3600 );
		}

		public function print_admin_notice() {
			?><div id="message" class="updated fade"><h3>Custom Fields:</h3><?php

			foreach( (array) $this->_admin_notices as $notice ) {
				?>
					<p><?php echo $notice ?></p>
				<?php
			}
			?></div><?php
			$this->_admin_notices = array();
			delete_transient( $this->_plugin_prefix . '_notices' );
		}

		//OVERLOADING Field objects access.
		public function __set( $field_id, $object ) {
			if ( ! ( $object instanceof Easy_CF_Field ) ) {
				//TODO whatever error is
				trigger_error( sprintf( __( "Field object for %s needs to be initialized first" ), $field_id ) );
				return false;
			}
			$this->field_objects[$field_id] = $object;
		}

		public function __get( $field_id ) {
			if ( array_key_exists( $field_id, $this->field_objects ) ) {
				return $this->field_objects[$field_id];
			}
		}
		//END OVERLOADING
	}
}
