<?php

    /*
     * Grab the settings from $instance and fill out default
     * values as needed.
     */
    $title      = empty( $instance['title'] ) ? ' ' : apply_filters( 'widget_title', $instance['title'] );
    if ( ! empty( $instance['sailthru_list'] ) ) {
	    if ( is_array( $instance['sailthru_list'] ) )
	    {
	        $sailthru_list = implode( ',', $instance['sailthru_list'] );
	    } else {
	        $sailthru_list = $instance['sailthru_list'];
	    }
	}
	else{
		$sailthru_list = '';
	}

    // display options
    $customfields = get_option( 'sailthru_forms_options' );
    // nonce
    $nonce = wp_create_nonce( 'add_subscriber_nonce' );

 ?>
 <div class="sailthru-signup-widget">
    <span class="sailthru-signup-widget-close"><a href="#sailthru-signup-widget">Close</a></span>
     <div class="sailthru_form">

        <?php
            // title
            if ( ! empty( $title ) ) {
                if ( ! isset( $before_title ) ) {
                    $before_title = '';
                }
                if ( !isset( $after_title ) ) {
                    $after_title = '';
                }
                echo $before_title . esc_html( trim( $title ) ) . $after_title;
            }
            if ( empty( $customfields['sailthru_customfield_success'] ) ) {
	            $success = 'Thank you for subscribing!';
            }
            else{
	            $success = $customfields['sailthru_customfield_success'];
            }
        ?>
        <div class="success" hidden="hidden"><?php echo $success;?></div>
         <form method="post" action="#" class="sailthru-add-subscriber-form">
            <div class="sailthru-add-subscriber-errors"></div>

            <div class="sailthru_form_input form-group">
                <label class="sailthru-widget-label">Email</label>
                <input type="text" name="email" value="" class="form-control sailthru_email" />
            </div>

            <?php
            $key = get_option( 'sailthru_forms_key' );
            if ( ! empty( $instance['fields'] ) ) {
            	$fields = explode( ',', $instance['fields'] );
	            foreach ( $fields as $field ) {
	            	$name_stripped = preg_replace( "/[^\da-z]/i", '_', $field );
	            	$instance['show_'.$name_stripped.'_name']     = true;
	            	$instance['show_'.$name_stripped.'_required'] = false;
	            }
            }
				for ( $i = 0; $i < $key; $i++ ) {
				$field_key = $i + 1;
				if ( ! empty( $customfields[ $field_key ] ) ) {
				$name_stripped = preg_replace( "/[^\da-z]/i", '_', $customfields[ $field_key ]['sailthru_customfield_name'] );
					if ( ! empty( $instance['show_'.$name_stripped.'_name'] ) ) {
						if( ! empty ( $customfields[ $field_key ]['sailthru_customfield_attr'] ) ) {
				                $attributes = $customfields[ $field_key ]['sailthru_customfield_attr'];
				        } else {
					                $attributes = '';
				        }
                                 echo '<div class="sailthru_form_input form-group">';
						if ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'select' ) {

				                echo '<label for="custom_' . esc_attr($name_stripped) . '">' . esc_html($customfields[ $field_key ]['sailthru_customfield_label']) . '</label>
				                <select ' . sailthru_field_class( $customfields[ $field_key ]['sailthru_customfield_class'] ) .' '. sailthru_attributes( $attributes ) . 'name="custom_' . esc_attr($name_stripped) . '" id="sailthru_' . esc_attr($name_stripped) . '_id" class="form-control">';

				                $items = explode( ',', $customfields[ $field_key ]['sailthru_customfield_value'] );
				                foreach( $items as $item ) {
				                	$vals = explode( ':', $item );
					                echo '<option value="' . esc_attr($vals[0]) . '">' . esc_attr($vals[1]) . '</option>';
				                }
				                echo '</select>';

						}
						elseif ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'radio' ) {
				                $items = explode( ',', $customfields[ $field_key ]['sailthru_customfield_value'] );
                                      echo '<div class="radio">';
				                echo '<label for="custom_' . esc_attr($name_stripped) . '">' . esc_html($customfields[ $field_key ]['sailthru_customfield_label']) . '</label>';
				                foreach ( $items as $item ) {
				                	$vals = explode( ':', $item );
					                echo '<input ';
					                if ( $instance['show_'.esc_attr($name_stripped).'_required'] == 'checked' ) {
					                	echo 'required=required ';
					                }
					                echo 'type="radio" name="custom_'. esc_attr($name_stripped) . '" value="' . esc_attr($vals[0]) . '"> ' . esc_html($vals[1]) . '';
				                }
                                      echo '</div>';
						}
                                elseif ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'checkbox' ) {
                                      $items = explode( ',', $customfields[ $field_key ]['sailthru_customfield_value'] );
                                      echo '<div class="checkbox">';
                                      echo '<label for="custom_' . esc_attr($name_stripped) . '">' . esc_html($customfields[ $field_key ]['sailthru_customfield_label']) . '</label>';
                                      foreach ( $items as $item ) {
                                        $vals = explode( ':', $item );
                                        echo '<input ';
                                        if ( $instance['show_'.esc_attr($name_stripped).'_required'] == 'checked' ) {
                                          echo 'required=required ';
                                        }
                                        echo 'type="checkbox" name="custom_'. esc_attr($name_stripped) . '[]" value="' . esc_attr($vals[0]) . '"> ' . esc_html($vals[1]) . '';
                                      }
                                       echo '</div>';
                                }
						else{
			                //check if the field is required
			                if ( $instance['show_'.$name_stripped.'_required'] == 'checked' ) {
								if ( $customfields[ $field_key ]['sailthru_customfield_type'] != 'hidden' ) {
									echo '<label for="custom_' . esc_attr($name_stripped) . '" class="sailthru-widget-label sailthru-widget-required">' . esc_html($customfields[ $field_key ]['sailthru_customfield_label']) . ' </label>';
					            }
					            echo '<input ' . sailthru_field_class( esc_attr($customfields[ $field_key ]['sailthru_customfield_class']) ) . ' type="' . esc_attr($customfields[ $field_key ]['sailthru_customfield_type']) . '" ';
					            if ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'hidden' ) {
					                echo 'value="'.esc_attr($customfields[ $field_key ]['sailthru_customfield_value']).'" ';
				                }
				                echo sailthru_attributes( $attributes ) . 'required="required" name="custom_' . esc_attr($name_stripped) . '" id="sailthru_' . esc_attr($name_stripped) . '_name" class="form-control"/>';
							}
							else{
							if ( $customfields[ $field_key ]['sailthru_customfield_type'] != 'hidden' ) {
								echo '<label for="custom_' .esc_attr($name_stripped) . '">' . esc_html($customfields[ $field_key ]['sailthru_customfield_label'] ). '</label>';
							}
								echo '<input ';
								if ( $customfields[ $field_key ]['sailthru_customfield_type'] == 'hidden' ) {
				                echo 'value="'.esc_attr($customfields[ $field_key ]['sailthru_customfield_value']).'" ';
								}
								echo sailthru_field_class( esc_attr($customfields[ $field_key ]['sailthru_customfield_class'] ) ) .' type="' .esc_attr($customfields[ $field_key ]['sailthru_customfield_type']) . '" ' . sailthru_attributes( $attributes ) . 'name="custom_' . esc_attr($name_stripped). '" id="sailthru_' .esc_attr($name_stripped). '_id"  class="form-control"/>';

							}
		            	   }
                                  echo '</div>';
					} //end if !empty name
				} // end if !empty field key
			}// end for loop
            		?>
                <input type="hidden" name="sailthru_nonce" value="<?php echo $nonce; ?>" />
                <input type="hidden" name="sailthru_email_list" value="<?php echo esc_attr( $sailthru_list ); ?>" />
                <input type="hidden" name="action" value="add_subscriber" />
                <input type="hidden" name="vars[source]" value="<?php bloginfo( 'url' ); ?>" />

                <span class="input-group-btn">
                    <button class="btn btn-reverse" type="submit">
                        Submit
                    </button>
                </span>
        </form>



    </div>
</div>
