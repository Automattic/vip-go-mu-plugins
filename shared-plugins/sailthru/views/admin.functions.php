<?php
/* ------------------------------------------------------------------------ *
 * HORIZON SETUP
 * ------------------------------------------------------------------------ */
function sailthru_initialize_setup_options() {

	// If the section options don't exist, create them.
	if ( false == get_option( 'sailthru_setup_options' ) ) {
		add_option( 'sailthru_setup_options' );
	} // end if

	add_settings_section(
		'sailthru_setup_section',			// ID used to identify this section and with which to register options
		__( 'Sailthru API Setup', 'sailthru-for-wordpress' ),				// Title to be displayed on the administration page
		'sailthru_setup_callback',			// Callback used to render the description of the section
		'sailthru_setup_options'			// Page on which to add this section of options
	);

	/*
		 * Add a new field for selecting the email template to use,
		 * but don't do this until we have an API key & secret to use.
		 */
		$setup = get_option( 'sailthru_setup_options' );

		if ( isset( $setup['sailthru_api_key']) && ! empty ( $setup['sailthru_api_key'] ) &&
				isset( $setup['sailthru_api_secret'] ) && ! empty ( $setup['sailthru_api_secret'] ) ) {

			add_settings_field(
				'sailthru_setup_email_template',
				__( 'WordPress template', 'sailthru-for-wordpress' ),
				'sailthru_setup_email_template_callback',
				'sailthru_setup_options',
				'sailthru_setup_section',
				array(
					'sailthru_setup_options',
					'sailthru_setup_email_template',
					'',
					'sailthru_setup_email_template',
				)
			);

		}
		add_settings_field(
			'sailthru_form_name',					// ID used to identify the field throughout the theme
			__( 'Sailthru field name', 'sailthru-for-wordpress' ),					// The label to the left of the option interface element
			'sailthru_html_text_input_callback',// The name of the function responsible for rendering the option interface
			'sailthru_forms_options',			// The page on which this option will be displayed
			'sailthru_setup_section',			// The name of the section to which this field belongs
			array(								// The array of arguments to pass to the callback. In this case, just a description.
				'sailthru_setup_options',
				'sailthru_form_name',
				'',
				'sailthru_form_name'
			)
		);


		add_settings_field(
			'sailthru_api_key',					// ID used to identify the field throughout the theme
			__( 'Sailthru API Key', 'sailthru-for-wordpress' ),					// The label to the left of the option interface element
			'sailthru_html_text_input_callback',// The name of the function responsible for rendering the option interface
			'sailthru_setup_options',			// The page on which this option will be displayed
			'sailthru_setup_section',			// The name of the section to which this field belongs
			array(								// The array of arguments to pass to the callback. In this case, just a description.
				'sailthru_setup_options',
				'sailthru_api_key',
				'',
				'sailthru_api_key'
			)
		);

		add_settings_field(
			'sailthru_api_secret',
			__( 'Sailthru API Secret', 'sailthru-for-wordpress' ),
			'sailthru_html_text_input_callback',
			'sailthru_setup_options',
			'sailthru_setup_section',
			array(
				'sailthru_setup_options',
				'sailthru_api_secret',
				'',
				'sailthru_api_secret'
			)
		);

		add_settings_field(
			'sailthru_horizon_domain',
			'Sailthru Horizon Domain',
			'sailthru_html_text_input_callback',
			'sailthru_setup_options',
			'sailthru_setup_section',
			array(
				'sailthru_setup_options',
				'sailthru_horizon_domain',
				'',
				'sailthru_horizon_domain'
			)
		);

		add_settings_field(
		    'sailthru_horizon_load_type',
		    'Horizon Loading',
		    'sailthru_horizon_loadtype_callback',
		    'sailthru_setup_options',
		    'sailthru_setup_section',
		    array(
				'sailthru_setup_options',
				'sailthru_horizon_load_type',
				'',
				'sailthru_horizon_load_type'
			)
		);


	// Finally, we register the fields with WordPress
	register_setting(
		'sailthru_setup_options',
		'sailthru_setup_options',
		'sailthru_setup_handler'
	);

} // end sailthru_initialize_setup_options
add_action( 'admin_init', 'sailthru_initialize_setup_options' );

function sailthru_initialize_forms_options() {

	function sailthru_forms_callback( $args ) {

		/*
		** Custom and Extra Sections should be in a first column.
		** Begin the column here. It ends in delete_field()
		*/
		echo '<div class="column-half" id="sailthru-col-left">';
		echo '<h3>Custom fields</h3>';
		echo '<p>Custom fields allow you to collect additional information from the user that can be stored in their Sailthru User Profile. </p>';

		$customfields  = get_option( 'sailthru_forms_options' );
		$key           = get_option( 'sailthru_forms_key' );

		echo '<p><strong>Existing fields</strong></p>';
		echo '<table class="wp-list-table widefat">';
		echo '<thead>';
		echo '<th scope="col" class="manage-column">Display Label</th>';
		echo '<th scope="col" class="manage-column">Field Value</th>';
		echo '<th scope="col" class="manage-column">Field Type</th>';
		echo '</thead>';

		for ( $i = 0; $i < $key; $i++ ) {
			$field_key = $i + 1;
			 if ( ! empty ( $customfields[ $field_key ]['sailthru_customfield_name'] ) ) {
				echo '<tr>';
				echo '<td>'. esc_html($customfields[ $field_key ]['sailthru_customfield_label']).' </td>';
				echo '<td>'. esc_html($customfields[ $field_key ]['sailthru_customfield_name']).' </td>';
				echo '<td>'. esc_html($customfields[ $field_key ]['sailthru_customfield_type']).' </td>';
				echo '</tr>';
			}
		}
		echo '</table>';
		echo '<p>Use the form below to create a custom field library. Each created field will be available in our Sailthru Subscribe widget.</p>';


	}

	function field_type ( $args ) {

		$collection    = $args[0];
		$option_name   = $args[1];
		$default_value = $args[2];
		$html_id       = $args[3];
		$options       = get_option( $collection );
		$value         = '';
		if ( isset( $options[ $option_name ] ) ) {
			$value = $options[ $option_name ];
		} else {
			$value = $default_value;
	}

	// Render the output of the field type selector

		echo '<select id="type" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name ) . ']">
				  <option value="text"' . selected( esc_attr( $value ), 'text' ) . '>Text Field</option>
				  <option value="password"'. selected( esc_attr( $value ), 'password' ) . '>Password</option>
				  <option value="tel"' . selected( esc_attr( $value ), 'tel' ) . '>Telephone</option>
				  <option value="date"' . selected( esc_attr( $value ), 'date' ) . '>Date</option>
				  <option value="hidden"' . selected( esc_attr( $value ), 'hidden' ) . '>Hidden</option>
				  <option value="select"' . selected( esc_attr( $value ), 'select' ) . '>Select</option>
				  <option value="radio"' . selected( esc_attr( $value ), 'radio' ) . '>Radio</option>
				  <option value="checkbox"' . selected( esc_attr( $value ), 'radio' ) . '>Checkbox</option>
			  </select>';
		echo  '<div class="instructions">The type of html form field displayed.</div>';

	}

	function sailthru_create_second_column() {
		/*
		** Delete and Existing Sections should be in a second column.
		** Begin the column here. It ends in views/admin.php (unfortunately)
		*/
		echo '</div><div class="column-half" id="sailthru-col-right">';
		echo '<h3>Delete Fields</h3>';
		echo '<p>Delete a field already created by using the form below.</p>';
	}

	function sailthru_delete_field ( $args ) {
		$customfields  = get_option( 'sailthru_forms_options' );
		$collection    = $args[0];
		$option_name   = $args[1];
		$default_value = $args[2];
		$html_id       = $args[3];
		$options       = get_option( $collection );
		$key           = get_option( 'sailthru_forms_key' );


		echo '<select name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name ) . ']">
				<option value="select">Select...</option>';
				for ( $i = 0; $i < $key; $i++ ) {
					 $field_key = $i + 1;
					 if ( ! empty ( $customfields[ $field_key ]['sailthru_customfield_name'] ) ) {
					 echo '<option value="'.esc_attr($field_key).'" >'.esc_html($customfields[ $field_key ]['sailthru_customfield_label']).'</option>';
					 }
				} //end for loop
		echo '</select>';
		echo '<div>'.submit_button('Delete Field') .'</div>';

	}

	function sailthru_success_field ( $args ) {
		$customfields  = get_option( 'sailthru_forms_options' );
		$collection    = $args[0];
		$option_name   = $args[1];
		$default_value = $args[2];
		$html_id       = $args[3];
		$options       = get_option( $collection );

		if ( empty ( $customfields['sailthru_customfield_success'] ) ) {
			$message = '';
		}
		else{
			$message = $customfields['sailthru_customfield_success'];
		}

		echo '<p>Use the field below to update the message that the user sees after subscribing</p>';
		echo '<p><textarea name="' . esc_attr( $collection ) . '[sailthru_customfield_success]" placeholder="" rows="5" cols="30">'.esc_textarea($message).'</textarea></p>';
		echo '<div>'.submit_button('Update') .'</div>';
	}




	function sailthru_fields() {

		    $customfields = get_option( 'sailthru_forms_options' );
		    $key          = get_option( 'sailthru_forms_key' );

			for ( $i = 0; $i < $key; $i++ ) {
			$field_key = $i + 1;
				if ( ! empty ( $customfields[ $field_key ] ) ) {
					if ( $customfields[ $field_key ]['sailthru_customfield_name'] != '' ) {

						$name_stripped = preg_replace( "/[^\da-z]/i", '_', $customfields[ $field_key ]['sailthru_customfield_name'] );
						//select field
						if ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'select' ) {
					        echo '
					        <label for="custom_' . $name_stripped . '">' . $customfields[ $field_key ]['sailthru_customfield_name'] . ':</label>
							<select name="custom_' . $name_stripped .'" id="sailthru_' . $name_stripped . '_name">';

					        $items = explode( ',', $customfields[ $field_key ]['sailthru_customfield_value'] );
					        foreach( $items as $item ) {
						        $vals = explode( ':', $item );
							    echo '<option value="' . esc_attr($vals[0]) . '">' . esc_html($vals[1]) . '</option>';
						    }
					        echo '</select>';
													}
						//radio field
						elseif ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'radio' ) {

				                $items = explode( ',', $customfields[ $field_key ]['sailthru_customfield_value'] );
				                echo '<label >' . esc_html($customfields[ $field_key ]['sailthru_customfield_name']) . ':</label>';

				                foreach ( $items as $item ) {
				                	$vals = explode( ':', $item );
					                echo '<input type="radio" name="custom_' . esc_attr($name_stripped) . '" value="' . esc_attr($vals[0]) . '"> ' . esc_html($vals[1]);
				                }
						}
						//hidden field
						elseif ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'hidden' ) {
							echo 'hidden field: ' . esc_html($customfields[ $field_key ]['sailthru_customfield_name']).'';
						}
						//field is a text input
						else{

							echo '<div class="sailthru_form_input">';
			                //check if the field is required
							if ( $customfields[ $field_key ]['sailthru_customfield_type'] != 'hidden' ) {
								echo '<br /><label for="custom_' . esc_attr($name_stripped) . '">' . esc_html($customfields[ $field_key ]['sailthru_customfield_name']) . ':</label>';
							}
							echo '<input type="' . esc_attr($customfields[ $field_key ]['sailthru_customfield_type']) . '" name="custom_' . esc_attr($name_stripped) . '" id="sailthru_' . esc_attr($name_stripped) . '_name" />';

		            	} //end text input
					} // end if name ! empty
				} // end if
			}
	}

	function sailthru_value_field ( $args ) {
		$collection    = $args[0];
		$option_name   = $args[1];
		$default_value = $args[2];
		$html_id       = $args[3];
		$options       = get_option( $collection );

		echo '<div class="sailthru_keypair_fields"  id="sailthru_value_fields_block">';
		echo '<input class="selection" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name .'2' ) . ']" type="text" placeholder="display " />';
		echo '<input class="selection" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name.'1' ) . ']" type="text"  placeholder="value"/>';
		echo '<input id="value_amount" type="hidden" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name .'_val' ) . ']" value="1" />';
		echo '</div>';
		echo '<div class="instructions">';
		echo '<a id="add_value" href ="">Add Another</a>';
		echo '<div>';

	}
	function sailthru_attr_field ( $args ) {
		$collection    = $args[0];
		$option_name   = $args[1];
		$default_value = $args[2];
		$html_id       = $args[3];
		$options       = get_option( $collection );

		echo '<div class="sailthru_keypair_fields" id="sailthru_attr_fields_block">';
		echo '<input class="attribute" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name .'1' ) . ']" type="text" placeholder="attribute" />';
		echo '<input class="attribute" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name.'2' ) . ']" type="text"  placeholder="value"/>';
		echo '<input id="attr_amount" type="hidden" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name .'_val' ) . ']" value="2" />';
		echo '</div>';
		echo '<div class="instructions">';
		echo '<a id="add_attr" href ="">Add Another</a>';
		echo '<div>';

	}

	// If the section options don't exist, create them.
	if ( false == get_option( 'sailthru_forms_options' ) ) {
		add_option( 'sailthru_forms_options' );
	} // end if


	$forms = get_option( 'sailthru_forms_options' );

	add_settings_section(
		'sailthru_forms_section',							// ID used to identify this section and with which to register options
		__( '', 'sailthru-for-wordpress' ),	// Title to be displayed on the administration page
		'sailthru_forms_callback',							// Callback used to render the description of the section
		'sailthru_forms_options'							// Page on which to add this section of options
	);

		add_settings_field(
			'sailthru_customfield_type',					// ID used to identify the field throughout the theme
			__( 'Field Type', 'sailthru-for-wordpress' ),	// The label to the left of the option interface element
			'field_type',									// The name of the function responsible for rendering the option interface
			'sailthru_forms_options',						// The page on which this option will be displayed
			'sailthru_forms_section',						// The name of the section to which this field belongs
			array(											// The array of arguments to pass to the callback. In this case, just a description.
				'sailthru_forms_options',
				'sailthru_customfield_type',
				'',
				'sailthru_customfield_type'
			)
		);

		add_settings_field(
			'sailthru_customfield_label',					// ID used to identify the field throughout the theme
			__( 'Display label', 'sailthru-for-wordpress' ),	// The label to the left of the option interface element
			'sailthru_html_text_input_callback',			// The name of the function responsible for rendering the option interface
			'sailthru_forms_options',						// The page on which this option will be displayed
			'sailthru_forms_section',						// The name of the section to which this field belongs
			array(											// The array of arguments to pass to the callback. In this case, just a description.
				'sailthru_forms_options',
				'sailthru_customfield_label',
				'',
				'sailthru_customfield_label',
				'The text in this field is used for the field label.',
			)
		);

		add_settings_field(
			'sailthru_customfield_name',					// ID used to identify the field throughout the theme
			__( 'Field name', 'sailthru-for-wordpress' ),	// The label to the left of the option interface element
			'sailthru_html_text_input_callback',			// The name of the function responsible for rendering the option interface
			'sailthru_forms_options',						// The page on which this option will be displayed
			'sailthru_forms_section',						// The name of the section to which this field belongs
			array(											// The array of arguments to pass to the callback. In this case, just a description.
				'sailthru_forms_options',
				'sailthru_customfield_name',
				'',
				'sailthru_customfield_name',
				'The name used as a var in the Sailthru user profile.',
			)
		);

		add_settings_field(
				'sailthru_customfield_value',				// ID used to identify the field throughout the theme
				__( 'Field values', 'sailthru-for-wordpress' ),					// The label to the left of the option interface element
				'sailthru_value_field',						// The name of the function responsible for rendering the option interface
				'sailthru_forms_options',					// The page on which this option will be displayed
				'sailthru_forms_section',					// The name of the section to which this field belongs
				array(										// The array of arguments to pass to the callback. In this case, just a description.
					'sailthru_forms_options',
					'sailthru_customfield_value',
					'',
					'sailthru_customfield_value',
				)
		);

	add_settings_section(
		'sailthru_adv_section',								// ID used to identify this section and with which to register options
		__( 'Additional HTML Attributes', 'sailthru-for-wordpress' ),	     // Title to be displayed on the administration page
		'sailthru_html_fields_options_callback',													// Callback used to render the description of the section
		'sailthru_forms_options'							// Page on which to add this section of options
	);
		add_settings_field(
				'sailthru_customfield_class',				// ID used to identify the field throughout the theme
				__( 'CSS Class(es)', 'sailthru-for-wordpress' ),	// The label to the left of the option interface element
				'sailthru_html_text_input_callback',		// The name of the function responsible for rendering the option interface
				'sailthru_forms_options',					// The page on which this option will be displayed
				'sailthru_adv_section',						// The name of the section to which this field belongs
				array(										// The array of arguments to pass to the callback. In this case, just a description.
					'sailthru_forms_options',
					'sailthru_customfield_class',
					'',
					'sailthru_customfield_class',
					'Separate multiple css classes using a space'
				)
		);

		add_settings_field(
				'sailthru_customfield_attr',				// ID used to identify the field throughout the theme
				__( 'Data Attributes', 'sailthru-for-wordpress' ),	// The label to the left of the option interface element
				'sailthru_attr_field',						// The name of the function responsible for rendering the option interface
				'sailthru_forms_options',					// The page on which this option will be displayed
				'sailthru_adv_section',						// The name of the section to which this field belongs
				array(										// The array of arguments to pass to the callback. In this case, just a description.
					'sailthru_forms_options',
					'sailthru_customfield_attr',
					'',
					'sailthru_customfield_attr'
				)
		);

	add_settings_section(
		'sailthru_delete_section',							// ID used to identify this section and with which to register options
		__( '', 'sailthru-for-wordpress' ),					// Title to be displayed on the administration page
		'sailthru_create_second_column',					// Callback used to render the description of the section
		'sailthru_forms_options'							// Page on which to add this section of options
	);
		add_settings_field(
				'sailthru_customfield_delete',				// ID used to identify the field throughout the theme
				__( 'Choose one', 'sailthru-for-wordpress' ),// The label to the left of the option interface element
				'delete_field',								// The name of the function responsible for rendering the option interface
				'sailthru_forms_options',					// The page on which this option will be displayed
				'sailthru_delete_section',					// The name of the section to which this field belongs
				array(										// The array of arguments to pass to the callback. In this case, just a description.
					'sailthru_forms_options',
					'sailthru_customfield_delete',
					'',
					'sailthru_customfield_delete'
				)
			);
		add_settings_field(
				'sailthru_customfield_success',				// ID used to identify the field throughout the theme
				__( 'Subscribe Message', 'sailthru-for-wordpress' ),					// The label to the left of the option interface element
				'sailthru_success_field',					// The name of the function responsible for rendering the option interface
				'sailthru_forms_options',					// The page on which this option will be displayed
				'sailthru_delete_section',						// The name of the section to which this field belongs
				array(										// The array of arguments to pass to the callback. In this case, just a description.
					'sailthru_forms_options',
					'sailthru_customfield_success',
					'',
					'sailthru_customfield_success'
				)
		);
	// Finally, we register the fields with WordPress
	register_setting(
		'sailthru_forms_options',
		'sailthru_forms_options',
		'sailthru_forms_handler'
	);

} // end sailthru_initialize_setup_options
add_action( 'admin_init', 'sailthru_initialize_forms_options' );



/* ------------------------------------------------------------------------ *
 * CONCIERGE SETUP
 * ------------------------------------------------------------------------ */
function sailthru_intialize_concierge_options() {

	if ( false == get_option( 'sailthru_concierge_options' ) ) {
		add_option( 'sailthru_concierge_options' );
	} // end if

	add_settings_section(
		'sailthru_concierge_settings_section',			// ID used to identify this section and with which to register options
		__( 'Sailthru Concierge Options', 'sailthru-for-wordpress' ), // Title to be displayed on the administration page
		'sailthru_concierge_options_callback',			// Callback used to render the description of the section
		'sailthru_concierge_options'					// Page on which to add this section of options
	);


		add_settings_field(
			'sailthru_concierge_is_on',
			__( 'Enable Concierge', 'sailthru-for-wordpress' ),
			'sailthru_toggle_feature_callback',
			'sailthru_concierge_options',
			'sailthru_concierge_settings_section',
			array(
				'sailthru_concierge_options',
				'sailthru_concierge_is_on',
				'1',
				'sailthru_concierge_is_on',
				'Yes'
			)
		);

		/*
		 * If Conceirge is not on, let's not show all the options
		 */
		$concierge = get_option( 'sailthru_concierge_options' );

		if ( isset( $concierge['sailthru_concierge_is_on'] ) && $concierge['sailthru_concierge_is_on'] ) {

			add_settings_field(
				'sailthru_concierge_from',
				__( 'Recommended box to display from', 'sailthru-for-wordpress' ),
				'sailthru_concierge_from_callback',
				'sailthru_concierge_options',
				'sailthru_concierge_settings_section',
				array(
					'sailthru_concierge_options',
					'sailthru_concierge_from',
					'top',
					'sailthru_concierge_from'
				)
			);

			add_settings_field(
				'sailthru_concierge_delay',
				__( 'Delay Concierge for ', 'sailthru-for-wordpress' ),
				'sailthru_concierge_delay_callback',
				'sailthru_concierge_options',
				'sailthru_concierge_settings_section',
				array(
					'sailthru_concierge_options',
					'sailthru_concierge_delay',
					'1',
					'sailthru_concierge_delay'
				)
			);


			add_settings_field(
				'sailthru_concierge_threshold',
				__( 'A lower threshold value means the box will display within shorter page', 'sailthru-for-wordpress' ),
				'sailthru_html_text_input_callback',
				'sailthru_concierge_options',
				'sailthru_concierge_settings_section',
				array(
					'sailthru_concierge_options',
					'sailthru_concierge_threshold',
					'',
					'sailthru_concierge_threshold'
				)
			);


			add_settings_field(
				'sailthru_concierge_offsetBottom',
				__( 'Higher the value, recommendation box will offset the window bottom', 'sailthru-for-wordpress' ),
				'sailthru_html_text_input_callback',
				'sailthru_concierge_options',
				'sailthru_concierge_settings_section',
				array(
					'sailthru_concierge_options',
					'sailthru_concierge_offsetBottom',
					'20',
					'sailthru_concierge_offsetBottom'
				)
			);


			add_settings_field(
				'sailthru_concierge_cssPath',
				__( 'Custom CSS path to decorate recommendation box', 'sailthru-for-wordpress' ),
				'sailthru_html_text_input_callback',
				'sailthru_concierge_options',
				'sailthru_concierge_settings_section',
				array(
					'sailthru_concierge_options',
					'sailthru_concierge_cssPath',
					'https://ak.sail-horizon.com/horizon/recommendation.css',
					'sailthru_concierge_cssPath'
				)
			);


			add_settings_field(
				'sailthru_concierge_filter',
				__( 'To only return content tagged a certain way, pass comma separated tags', 'sailthru-for-wordpress' ),
				'sailthru_html_text_input_callback',
				'sailthru_concierge_options',
				'sailthru_concierge_settings_section',
				array(
					'sailthru_concierge_options',
					'sailthru_concierge_filter',
					'',
					'sailthru_concierge_filter'
				)
			);

		} // end if concierge is on

	register_setting(
		'sailthru_concierge_options',					// Settings group. Must match the setting section.
		'sailthru_concierge_options',					// Option name to sanitize and save
		'sailthru_sanitize_text_input'					// Sanitize callback
	);

} // end sailthru_intialize_concierge_options
add_action( 'admin_init', 'sailthru_intialize_concierge_options' );


/* ------------------------------------------------------------------------ *
 * SCOUT SETUP
 * ------------------------------------------------------------------------ */
function sailthru_intialize_scout_options() {

	if ( false == get_option( 'sailthru_scout_options' ) ) {
		add_option( 'sailthru_scout_options' );
	} // end if

	add_settings_section(
		'sailthru_scout_settings_section',
		__( 'Scout Options', 'sailthru-for-wordpress' ),
		'sailthru_scout_options_callback',
		'sailthru_scout_options'
	);

		add_settings_field(
			'sailthru_scout_is_on',
			__( 'Scout Enabled', 'sailthru-for-wordpress' ),
			'sailthru_toggle_feature_callback',
			'sailthru_scout_options',
			'sailthru_scout_settings_section',
			array(
				'sailthru_scout_options',
				'sailthru_scout_is_on',
				'1',
				'sailthru_scout_is_on',
				'Yes'
			)
		);

		/*
		 * If Scout is not on, let's not show all the options
		 */
		$scout = get_option( 'sailthru_scout_options' );

		if ( isset( $scout['sailthru_scout_is_on'] ) &&  $scout['sailthru_scout_is_on'] ) {

			add_settings_field(
				'sailthru_scout_numVisible',
				__( 'The number of items to render at a time', 'sailthru-for-wordpress' ),
				'sailthru_scout_items_callback',
				'sailthru_scout_options',
				'sailthru_scout_settings_section',
				array(
					'sailthru_scout_options',
					'sailthru_scout_numVisible',
					'10',
					'sailthru_scout_numVisible'
				)
			);

			add_settings_field(
				'sailthru_scout_includeConsumed',
				__( 'Include content that has already been consumed by the user?', 'sailthru-for-wordpress' ),
				'sailthru_scout_includeConsumed_callback',
				'sailthru_scout_options',
				'sailthru_scout_settings_section',
				array(
					'sailthru_scout_options',
					'sailthru_scout_includeConsumed',
					'false',
					'sailthru_scout_includeConsumed'
				)
			);

			add_settings_field(
				'sailthru_scout_renderItem',
				__( 'Override rendering function? (Please do not include &lt;p&gt;&lt;/p&gt; tags -- <a href="http://docs.sailthru.com/documentation/products/scout" target="_blank">details here</a>.)', 'sailthru-for-wordpress' ),
				'sailthru_scout_renderItem_callback',
				'sailthru_scout_options',
				'sailthru_scout_settings_section',
				array(
					'sailthru_scout_options',
					'sailthru_scout_renderItem',
					'false',
					'sailthru_scout_renderItem'
				)
			);

		} // end if concierge is on


	register_setting(
		'sailthru_scout_options',
		'sailthru_scout_options',
		'sailthru_sanitize_text_input'
	);

} // end sailthru_intialize_concierge_options
add_action( 'admin_init', 'sailthru_intialize_scout_options' );


 // end sailthru_intialize_forms_options
/* ------------------------------------------------------------------------ *
 * Section Callbacks
 * ------------------------------------------------------------------------ */

/**
 * Provides a simple description for each setup page respectively.
 *
 * It's called from the 'sailthru_initialize_setup_options' function by being passed as a parameter
 * in the add_settings_section function.
 */
function sailthru_setup_callback() {
	echo '<p>Add your Sailthru API key , secret key and your Horizon domain. This can be found on the <a href="https://my.sailthru.com/settings_api">settings page</a> of the Sailthru dashboard.</p><p>Not sure what these are? Contact <a href="mailto:support@sailthru.com">support@sailthru.com</a> ';
} // end sailthru_setup_callback


function sailthru_concierge_options_callback() {
	echo '<p>Concierge is a Horizon-powered on-site recommendation tool, allowing a small "slider" to appear in a user\'s browser window at the end of an article. The slider will suggest another story based on a user\'s interest. </p><p>For full documentation of Concierge features visit our <a href="http://docs.sailthru.com/documentation/products/concierge">documentation</a>.</p>';
} // end sailthru_concierge_options_callback


function sailthru_scout_options_callback() {
	echo '<p>Scout is an on-site tool that displays relevant content to users when viewing a particular page.</p>';
} // end sailthru_scout_options_callback

function sailthru_html_fields_options_callback() {
	echo '<p>Add additional HTML attributes such as CSS classes and data attributes to the form field. These are optional fields to allow theme developers to integrate with their own themes.</p>';
}


/* ------------------------------------------------------------------------ *
 * Field Callbacks
 * ------------------------------------------------------------------------ */
/*
 * The calling function is expected to pass us an array of this format:
 * $args = array(
 * 		0 => 	collection
 * 		1 =>	option_name
 * 		2 =>	default
 *		3 =>	html_id
 * )
 * Echos a properly formatted <input type="text" /> with a value
 */
function sailthru_html_text_input_callback( $args ) {

	$collection    = isset( $args[0] ) ? $args[0] : '';
	$option_name   = isset( $args[1] ) ? $args[1] : '';
	$default_value = isset( $args[2] ) ? $args[2] : '';
	$html_id       = isset( $args[3] ) ? $args[3] : '';
	$hint          = isset( $args[4] ) ? $args[4] : '';
	$options       = get_option( $collection );

	// Make sure the element is defined in the options. If not, we'll use the preferred default
	$value = '';
	if ( isset( $options[ $option_name ] ) ) {
		$value = $options[ $option_name ];
	} else {
		$value = $default_value;
	}

	// Render the output
	echo '<input type="text" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name ) . ']" value="' . esc_attr( $value ) . '" />';
	if (isset($args[4])) {
		echo '<div class="instructions">'.esc_html($args[4]).'</div>';
	}

} // end sandbox_twitter_callback

/**
 * Creates a checkbox for the Horizon JS output type
 *
 */
function sailthru_horizon_loadtype_callback() {

    $options = get_option( 'sailthru_setup_options' );
    $load_type = isset($options['sailthru_horizon_load_type']) ? $options['sailthru_horizon_load_type'] : '';
   echo '<input type="checkbox" id="checkbox_example" name="sailthru_setup_options[sailthru_horizon_load_type]" value="1"' . checked( 1, esc_attr($load_type), false ) . '/>';
   echo 'Use synchronous loading for Horizon';

}

/**
 * Creates a dropdown for the number of scout options
 */
function sailthru_scout_items_callback( $args ) {

	$scout = get_option( 'sailthru_scout_options' );
	$saved_value = isset( $scout['sailthru_scout_numVisible'] )  ? $scout['sailthru_scout_numVisible'] : 5 ;


	$html = '<select name="sailthru_scout_options[sailthru_scout_numVisible]">';

	$i = 0;
	while ( $i <= 40 ) {
		$html .= '<option value="'.$i.'" ' . selected( $saved_value, $i, false ) . '>' . $i . '</option>';
		$i++;
	}
	$html .= '</select>';

	echo $html;

}

/**
 * Creates a Yes/No drop down for Scout whose values are True/False
 */
function sailthru_scout_includeConsumed_callback( $args ) {

	$scout = get_option( 'sailthru_scout_options' );
		$saved_value = isset( $scout['sailthru_scout_includeConsumed'] ) ? $scout['sailthru_scout_includeConsumed'] : '';

	$html = '<select name="sailthru_scout_options[sailthru_scout_includeConsumed]">';
		$html .= '<option value="false" ' . selected( $saved_value, "false", false) . '>No</option>';
		$html .= '<option value="true" ' . selected( $saved_value, "true", false) . '>Yes</option>';
	$html .= '</select>';

	echo $html;

}


/**
 * Just a textbox, but not a general function because we don't (oddly) strip
 * HTML tags.
 */
function sailthru_scout_renderItem_callback( $args ) {

	$scout = get_option( 'sailthru_scout_options' );
		$saved_value = isset( $scout['sailthru_scout_renderItem'] ) ? $scout['sailthru_scout_renderItem'] : '';

	$html = '<textarea name="sailthru_scout_options[sailthru_scout_renderItem]">' . esc_textarea( $saved_value ) . '</textarea>';

	echo $html;

}


/**
 * Creates a Top/Bottom dropdown whose values are top/bottom
 */
function sailthru_concierge_from_callback( $args ) {

	$scout = get_option( 'sailthru_concierge_options' );
	$saved_value = isset( $scout['sailthru_concierge_from'] ) ? $scout['sailthru_concierge_from'] : '' ;

	$html = '<select name="sailthru_concierge_options[sailthru_concierge_from]">';
		$html .= '<option value="top" ' . selected( $saved_value, "top", false) . '>Top</option>';
		$html .= '<option value="bottom" ' . selected( $saved_value, "bottom", false) . '>Bottom</option>';
	$html .= '</select>';

	echo $html;

}

/**
 * Creates a dropdown for the concierge delay
 */
function sailthru_concierge_delay_callback( $args ) {

	$scout = get_option( 'sailthru_concierge_options' );
	$saved_value = isset ( $scout['sailthru_concierge_delay'] ) ? $scout['sailthru_concierge_delay'] : '';

	$html = '<select name="sailthru_concierge_options[sailthru_concierge_delay]">';
		$html .= '<option value="100" ' . selected( $saved_value, "100", false) . '>1 sec</option>';
		$html .= '<option value="200" ' . selected( $saved_value, "200", false) . '>2 secs</option>';
		$html .= '<option value="300" ' . selected( $saved_value, "300", false) . '>3 secs</option>';
		$html .= '<option value="400" ' . selected( $saved_value, "400", false) . '>4 secs</option>';
		$html .= '<option value="500" ' . selected( $saved_value, "500", false) . '>5 secs</option>';
		$html .= '<option value="600" ' . selected( $saved_value, "600", false) . '>6 secs</option>';
		$html .= '<option value="700" ' . selected( $saved_value, "700", false) . '>7 secs</option>';
		$html .= '<option value="800" ' . selected( $saved_value, "800", false) . '>8 secs</option>';
		$html .= '<option value="900" ' . selected( $saved_value, "900", false) . '>9 secs</option>';
		$html .= '<option value="1000" ' . selected( $saved_value, "1000", false) . '>10 secs</option>';
	$html .= '</select>';

	echo $html;

}


/**
 * This function renders the interface elements for toggling a feature on or off.
 *
 * It accepts an array of arguments in the following format:
 * $args = array(
 * 		0 => 	collection
 * 		1 =>	option_name
 * 		2 =>	default
 *		3 =>	html_id
 *		4 =>	label
 * )
 * Echos a properly formatted <input type="checkbox" /> with a value
 */
function sailthru_toggle_feature_callback( $args ) {

	$collection    = $args[0];
	$option_name   = $args[1];
	$default_value = $args[2];
	$html_id       = $args[3];
	$label         = $args[4];

	// Read the options collection
	$options = get_option( $collection );

	// We don't want errors on first run, and since this is
	// only a toggle, we can create this option_name if it
	// doesn't exist.
	if ( ! isset( $options[ $option_name ] ) ) {
		$options[ $option_name ] = 0;	// evalutates to not checked
	}


	// Fully formed checkbox
	$html = '<input type="checkbox" id="' . esc_attr( $html_id ) . '" name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name ) . ']" value="' . esc_attr( $default_value ) . '" ' . checked( 1,  $options[ $option_name ], false ) . '/>';

	// Add alabel next to the checkbox
	$html .= '<label for="' . esc_attr( $html_id ) . '">&nbsp;'  . esc_html( $label ) . '</label>';

	echo $html;

} // end sailthru_toggle_feature_callback





function sailthru_setup_email_template_callback( $args ) {

	$sailthru   = get_option( 'sailthru_setup_options' );
	$api_key    = $sailthru['sailthru_api_key'];
	$api_secret = $sailthru['sailthru_api_secret'];

	//$client = new Sailthru_Client( $api_key, $api_secret );
	$client = new WP_Sailthru_Client( $api_key, $api_secret );

		try {
			if ( $client ) {
				$res = $client->getTemplates();
			}
		}
		catch ( Sailthru_Client_Exception $e ) {
			//silently fail
			return;
		}


	if ( isset( $res['error'] ) ) {
		$tpl =  array();
	} else {
		$tpl = $res['templates'] ;
	}

	$html = sailthru_create_dropdown( $args, $tpl );

	echo $html;

}




/* ------------------------------------------------------------------------ *
 * Setting Callbacks
 * ------------------------------------------------------------------------ */

 /**
 * Sanitization callback for the text inputs.
 * Loops through the incoming option and strips all tags and slashes from the value
 * before serializing it.
 *
 * @params	$input	The unsanitized collection of options.
 *
 * @returns			The collection of sanitized values.
 */
function sailthru_sanitize_text_input( $input ) {

	// Define the array for the updated options
	$output = array();

	if ( is_array( $input ) ) {

		// Loop through each of the options sanitizing the data
		foreach( $input as $key => $val ) {

			if ( isset ( $input[ $key ] ) ) {
				$output[ $key ] = sanitize_text_field( stripslashes( $input[ $key ] ) );
			} // end if

		} // end foreach

	} // end if

	// Return the new collection
	return apply_filters( 'sailthru_sanitize_text_input', $output, $input );

} // end sailthru_sanitize_text_input



/**
 * Sanitize the text inputs, and don't let the horizon
 * domain get saved with either http:// https:// or www
 */
 function sailthru_forms_handler( $input ) {

	$fields = get_option( 'sailthru_forms_options' );
	$output = $fields;
	$key    = get_option( 'sailthru_forms_key' );
		if ( isset( $key ) ) {
			$new_key = $key + 1;
			update_option( 'sailthru_forms_key',$new_key );
		}
		else{
			$new_key = 0;
			add_option( 'sailthru_forms_key',$new_key );
		}
		if ( ! empty( $input['sailthru_customfield_name'] ) ) {
			$output[ $new_key ]['sailthru_customfield_label']    = sanitize_text_field($input['sailthru_customfield_label']);
			$output[ $new_key ]['sailthru_customfield_name']    = sanitize_text_field($input['sailthru_customfield_name']);
			$output[ $new_key ]['sailthru_customfield_type']      = sanitize_text_field($input['sailthru_customfield_type']);
			$output[ $new_key ]['sailthru_customfield_class']     = sanitize_html_class($input['sailthru_customfield_class']);

			if ( ! empty( $input['sailthru_customfield_attr'] ) ) {
			$output[ $new_key ]['sailthru_customfield_attr']      = sanitize_text_field($input['sailthru_customfield_attr']);
			}
			if ( $input['sailthru_customfield_type'] == 'select' || $input['sailthru_customfield_type'] == 'radio' || $input['sailthru_customfield_type'] == 'checkbox' ) {
				$amount = sanitize_text_field($input['sailthru_customfield_value_val']);
					$values = '';
					for( $i = 1; $i <= $amount; $i++ ) {
						if ( $i != $amount ) {
							if ( $i % 2 == 0 ) {
								$values .= sanitize_text_field($input['sailthru_customfield_value'.$i]) .',';
							}
							else{
								$values .= sanitize_text_field($input['sailthru_customfield_value'.$i]) .':';
							}
						}
						else{
							$values .= sanitize_text_field($input['sailthru_customfield_value'.$i]);
						}
					} //end for
					$output[ $new_key ]['sailthru_customfield_value']      = $values;
			}
			if ( $input['sailthru_customfield_type'] == 'hidden' ) {
				$output[ $new_key ]['sailthru_customfield_value'] = sanitize_text_field($input['sailthru_customfield_value2']);
			}
				if ( ! empty( $input['sailthru_customfield_attr1'] ) && ! empty( $input['sailthru_customfield_attr2'] ) ) {
					$amount = $input['sailthru_customfield_attr_val'];
					$values = '';
					for( $i = 1; $i <= $amount; $i++ ) {
						if ( $i != $amount ) {
							if ( $i % 2 == 0 ) {
								$values .= sanitize_text_field($input['sailthru_customfield_attr'.$i]) .',';
							}
							else{
								$values .= sanitize_text_field($input['sailthru_customfield_attr'.$i]) .':';
							}
						}
						else{
							$values .= sanitize_text_field($input['sailthru_customfield_attr'.$i]);
						}
					}
					$output[ $new_key ]['sailthru_customfield_attr']      = $values;
			}
		}// end if empty field name
		if ( $input['sailthru_customfield_delete'] != 'select' ) {
			$output[ $input['sailthru_customfield_delete'] ]['sailthru_customfield_name'] = '';
		}
		$output['sailthru_customfield_success'] = sanitize_text_field($input['sailthru_customfield_success']);


	return $output;

}
function sailthru_setup_handler( $input ) {

	$output = array();

	// api key
	$output['sailthru_api_key'] = filter_var( $input['sailthru_api_key'], FILTER_SANITIZE_STRING );
	$output['sailthru_horizon_load_type'] = filter_var( $input['sailthru_horizon_load_type'], FILTER_SANITIZE_STRING );
	$output['sailthru_horizon_load_type'] = $input['sailthru_horizon_load_type'] == '1' ? $input['sailthru_horizon_load_type'] : false;



	if ( empty( $output['sailthru_api_key'] ) ) {
		add_settings_error( 'sailthru-notices', 'sailthru-api-key-fail', __( 'Sailthru will not function without an API key.' ), 'error' );
	}

	// api secret
	$output['sailthru_api_secret'] = filter_var( $input['sailthru_api_secret'], FILTER_SANITIZE_STRING );
	if ( empty( $output['sailthru_api_secret'] ) ) {
		add_settings_error( 'sailthru-notices', 'sailthru-api-secret-fail', __( 'Sailthru will not function without an API secret.' ), 'error' );
	}

	$output['sailthru_horizon_domain'] = filter_var( $input['sailthru_horizon_domain'], FILTER_SANITIZE_STRING );
	if ( empty( $output['sailthru_horizon_domain'] ) ) {
		add_settings_error( 'sailthru-notices', 'sailthru-horizon-domain-fail', __( 'Please enter your Horizon domain.' ), 'error' );
	} else {

		$output['sailthru_horizon_domain'] = str_ireplace( 'http://', '', $output['sailthru_horizon_domain'] );
		$output['sailthru_horizon_domain'] = str_ireplace( 'https://', '', $output['sailthru_horizon_domain'] );
		$output['sailthru_horizon_domain'] = str_ireplace( 'www.', '', $output['sailthru_horizon_domain'] );

		if ( substr( $output['sailthru_horizon_domain'], -1 ) == '/' ) {
		    $output['sailthru_horizon_domain'] = substr( $output['sailthru_horizon_domain'], 0, -1 );
		}
	}


	/*
	 * Of course we want to vaildate this field,
	 * but don't do this until we have an API key & secret to use.
	 */
	$setup = get_option( 'sailthru_setup_options' );

	if ( isset( $setup['sailthru_api_key'] ) && ! empty( $setup['sailthru_api_key'] ) &&
			isset( $setup['sailthru_api_secret'] ) && ! empty( $setup['sailthru_api_secret'] ) ) {

		// sitewide email template
		$output['sailthru_setup_email_template'] = trim( $input['sailthru_setup_email_template'] );

		if ( empty( $output['sailthru_setup_email_template'] ) ) {
			add_settings_error( 'sailthru-notices', 'sailthru-config-email-template-fail', __( 'Please choose a template to use when sending emails sitewide.' ), 'error' );
		}
	}


	return $output;

}
// end sailthru_setup_handler



/* ------------------------------------------------------------------------ *
 * Utility Functions
 * ------------------------------------------------------------------------ */

/**
 * Create a fully formed <select></select> dropdown
 * out of the arguments provided.
 *
 * @param $args
 * It accepts an array of arguments in the following format:
 * $args = array(
 * 		0 => 	collection
 * 		1 =>	option_name
 * 		2 =>	default
 *		3 =>	html_id
 * )
 *
 * @param $values
 * An array of an array of values.
 * It should take on this format:
 * array(
 *	0 	=> array('thing' => 'value')
 *)
 */
function sailthru_create_dropdown( $args, $values ) {

	$collection  = $args[0];
	$option_name = $args[1];
	$default     = $args[2];	// we're not using this yet
	$html_id     = $args[3];
	$current     = get_option( $collection );

	if ( isset( $current[ $option_name ] ) ) {
		$saved_value = $current[ $option_name ];
	} else {
		$saved_value = '';
	}



	$html = '<select name="' . esc_attr( $collection ) . '[' . esc_attr( $option_name ) . ']" id="' . esc_attr( $html_id ) . '">';

	$html .= '<option value=""> - Choose One - </option>';

	if ( is_array( $values ) ) {
		foreach( $values as $key => $value ) {

			$html .= '<option value="' . esc_attr( $value['name'] ) . '" ' . selected( $saved_value, $value['name'], false) . '>' . esc_attr( $value['name'] ) . '</option>';

		}
	}

	$html .= '</select>';

	return $html;

}

/**
 * This function verifies Sailthru is working by making an API Call to Sailthru
 *
 */
function sailthru_verify_setup() {

  $sailthru   = get_option( 'sailthru_setup_options' );
  $api_key    = $sailthru['sailthru_api_key'];
  $api_secret = $sailthru['sailthru_api_secret'];
  $template   = isset( $sailthru['sailthru_setup_email_template'] ) ? $sailthru['sailthru_setup_email_template'] : '';
  $res        = array();
  $tpl_errors = array();

  if ( $template == '' ) {
	  $res['error'] = true;
	  $res['errormessage'] = 'select a template';

  } else {

  	// now check to see if we can make an API call
  	//$client = new Sailthru_Client( $api_key, $api_secret );
  	$client = new WP_Sailthru_Client( $api_key, $api_secret );
  	$res = $client->getTemplates();

  	if ( !isset( $res['error'] ) ) {
  		// we can make a call, now check the template is configured
  		try {
           	$tpl = $client->getTemplate( $template );
           	$tpl_errors = sailthru_verify_template( $tpl );
           } catch ( Exception $e ) {
           	$tpl_errors = array( 'Request to Sailthru API failed: ' . $e->getMessage() );
           }

  		if ( count( $tpl_errors ) > 0 ) {
  			// add errors to the error message
  			$res['error'] = true;
  			$res['errormessage'] = 'template not configured';
  		} else {
  			$res['error'] = false;
  		}

  	} else {
  		$res['error'] = true;
  		$res['errormessage'] = 'not configured';
  	}

  }

  return $res;
}
// end sailthru_verify_setup


/**
 * This function verifies that the template is coded correctly
 *
 */
function sailthru_verify_template( $tpl ) {

	$errors = array();

	if ( $tpl['subject'] != '{subject}' ) {
		$errors = 'Your template needs to have {subject} as the subject line.';
	}

	if ( ! strstr( $tpl['content_html'], '{body}' ) ) {
		$errors = 'Your template needs to have {body} variable.';
	}

	if ( ! strstr( $tpl['content_text'], '{body}' ) ) {
		$errors = 'Your template needs to have {body} variable.';
	}

	return $errors;
}
