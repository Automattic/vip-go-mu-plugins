<?php
// You can reference or reuse the classes below to add new Easy_CF_Field and Easy_CF_Validator types

/*
if ( !class_exists( "Easy_CF_Field_Toggle" ) ) {
	class Easy_CF_Field_Toggle extends Easy_CF_Field {
		public function print_form() {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' :  $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' :  $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] :  $this->_field_data['id'];
			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] :  $this->_field_data['label'];
			$value = $this->get();
			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' :  '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$admin_only = ( empty( $this->_field_data['args']['admin_only'] ) ) ? false : $this->_field_data['args']['admin_only'];
			
			$admin_only_notice = $admin_only ? '(Admin Only!)' : '';

			$label_format =
				'<div class="%s">'.
				'<p><strong>%s %s</strong>'.
				'%s'.
				'<p><label for="%s"><input type="checkbox" name="%s" %s /><strong>Yes</strong></label></p>'.
				'</div>';
			if ( ! $admin_only || ($admin_only && current_user_can('manage_options') ) )
				printf( $label_format, $class, $label, $admin_only_notice, $hint, $id, $id, checked($value, 1));
			else
				echo '<input type="hidden" name="' . $id . '" value="' . $value . '" />';
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


if ( !class_exists( "Easy_CF_Field_Array_Dropdown" ) && class_exists( "Easy_CF_Field" ) ) {
	class Easy_CF_Field_Array_Dropdown extends Easy_CF_Field {
		public function print_form( $object, $box ) {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' : $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' : $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] : $this->_field_data['id'];
			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] : $this->_field_data['label'];
			$value = $this->get();
			$this->fill_current_values( $value );

			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' : '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$select = $this->get();


			if ( !empty($select) )
				$selected_terms[] = $select;

			if ( !isset( $selected_terms ) && isset( $this->_field_data['args']['default'] ) )
				$selected_terms[] = $this->_field_data['args']['default'];

			$terms = $this->_field_data['args']['values'];
			$values = '';
			foreach( $terms as $term_id => $term ) {
				$selected = '';
				if ( in_array( $term_id, $selected_terms ) )
					$selected = 'selected="selected" ';
				$values .= '<option ' . $selected . 'value="' . $term_id . '">' . $term . '</option>' . "\n";
			}
			$label_format =
				'<div class="%s">'.
				'<p><label for="%s"><strong>%s</strong></label></p>'.
				'%s'.
				'<p><select class="%s" name="%s">'.
				'%s'.
				'</select></p>'.
				'</div>';
			printf( $label_format, $class, $id, $label, $hint, $input_class, $id, $values);
		}
	}
}

if ( !class_exists( "Easy_CF_Field_Array_Checkboxes" ) && class_exists( "Easy_CF_Field" ) ) {
	class Easy_CF_Field_Array_Checkboxes extends Easy_CF_Field {
		public function print_form( $object, $box ) {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' : $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' : $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] : $this->_field_data['id'];
			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] : $this->_field_data['label'];
			$value = $this->get();
			$this->fill_current_values( $value );

			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' : '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$select = $this->get();
			$terms = $this->_field_data['args']['values'];
			$values = '';
			foreach( $terms as $term_id => $term ) {
				$checked = '';

				if ( ( !empty($select) ) && in_array( $term_id, $select ) )
					$checked = 'checked="checked" ';

				$values .= '<li><input type="checkbox" ' . $checked . 'value="' . $term_id . '" name="' . $id . '[]" id="' . $term_id . '"><label for="' . $term_id . '">' . $term . '</label></li>' . "\n";
			}
			$label_format =
				'<div class="%s" id="%s">'.
				'%s'.
				'<p><strong>%s</strong></p>'.
				'<ul class="%s">'.
				'%s'.
				'</ul>'.
				'</div>';
			printf( $label_format, $class, $id, $label, $hint, $input_class, $values  );
		}


		public function get( $post_id = '' ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			}
			$value = get_post_meta( $post_id, $this->_field_data['id'], true );
			return maybe_unserialize( $value );
		}

		public function set( $value = '', $post_id = '' ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			}

			$value = serialize( $value );

			$result = update_post_meta( $post_id, $this->_field_data['id'], $value );
			$this->fill_current_values( $value );
			return $result;
		}

		public function validate( $value = '' ) {
			if ( empty( $value ) || is_array( $value ) )
				return true;
			else
				return false;
		}
	}
}

// Saves in post meta, loads value from Taxonomy
if ( !class_exists( "Easy_CF_Field_Meta_Taxonomy_Checkboxes" ) && class_exists( "Easy_CF_Field_Array_Checkboxes" ) ) {
	class Easy_CF_Field_Meta_Taxonomy_Checkboxes extends Easy_CF_Field_Array_Checkboxes {
		public function print_form( $object, $box ) {

			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' : $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' : $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] : $this->_field_data['id'];

			$taxonomy = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $id : $this->_field_data['args']['taxonomy_id'];

			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] : $this->_field_data['label'];

			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' : '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$terms = get_terms( $taxonomy, array( 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => 	false, 'hierarchical' => false ) );
			if ( is_wp_error( $terms ) )
				return;

			$selected = $this->get();
			$this->fill_current_values( $selected );

			$selected_terms = array();
			if ( !empty( $selected ) ) {
				if ( is_array( $selected ) ) {
					foreach( $selected as $item ) {
						$selected_terms[] = $item;
					}
				} else {
					$selected_terms[] = $selected;
				}
			}
			else {
				$no_selected = true;
			}

			$values = '';
			foreach( $terms as $term ) {

				$checked = '';
				if ( in_array( $term->term_id, $selected_terms ) )
					$checked = 'checked="checked" ';

				$values .= '<input type="checkbox" ' . $checked . 'value="' . $term->term_id . '" name="' . $id . '[]" id="' . $term->term_id . '"><label for="' . $term->term_id. '">' . $term->name . '</label><br />' . "\n";
			}
			$label_format =
				'<div class="%s" id="%s">'.
				'<p><strong>%s</strong></p>'.
				'%s'.
				'<p class="%s">'.
				'%s'.
				'</p></div>';
			printf( $label_format, $class, $id, $label, $hint, $input_class, $values  );
		}
	}
}


if ( !class_exists( "Easy_CF_Field_Taxonomy_Dropdown" ) ) {
	class Easy_CF_Field_Taxonomy_Dropdown extends Easy_CF_Field {
		public function print_form( $object, $box ) {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' : $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' : $this->_field_data['input_class'];

			$id = $this->_field_data['id'];

			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] : $this->_field_data['label'];
			$value = $this->get();
			$this->fill_current_values( $value );

			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' : '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$taxonomy = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $id : $this->_field_data['args']['taxonomy_id'];

			if ( !taxonomy_exists( $taxonomy ) )
				return;

			$terms = get_terms( $taxonomy, array( 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => false, 'hierarchical' => false ) );

			if ( is_wp_error( $terms ) )
				return;

			$selected = $this->get();
			$selected_terms = array();
			if ( !empty( $selected ) ) {
				if ( is_array( $selected ) ) {
					foreach( $selected as $item ) {
						$selected_terms[] = $item->term_id;
					}
				} else {
					$selected_terms[] = $selected->term_id;
				}
			}
			else {
				$no_selected = true;
			}

			$values = '<option  disabled="disabled"' . ( ( true === $no_selected ) ? 'selected="selected"' : '' ) . 'value="' . 0 . '">== ' . $label . ' ==</option>' . "\n";
			foreach( $terms as $term ) {
				$selected = '';
				if ( in_array( $term->term_id, $selected_terms ) )
					$selected = 'selected="selected" ';
				$values .= '<option ' . $selected . 'value="' . $term->term_id . '">' . $term->name . '</option>' . "\n";
			}
			$label_format =
				'<div class="%s">'.
				'<p><label for="%s"><strong>%s</strong></label></p>'.
				'<p><select class="%s" name="%s">'.
				'%s'.
				'</select></p>'.
				'%s'.
				'</div>';
			printf( $label_format, $class, $id, $label, $input_class, $id, $values, $hint );
		}

		public function get( $post_id = '', $raw = false ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			}

			if ( !$post_id ) {
				return false;	// the post is not saved
			}

			$taxonomy = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $this->_field_data['id'] : $this->_field_data['args']['taxonomy_id'];

			$value = $this->global_cached_wp_get_object_terms( $post_id, $taxonomy );

			if ( is_wp_error( $value ) ) {
				return false;
			}
			if ( count( $value ) == 1 )
				return $value[0];

			return $value;
		}

		public function validate($value='') {
			return true;
		}

		public function set( $value, $post_id = '' ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			}

			if ( !$post_id )
				return false;

			$taxonomy = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $this->_field_data['id'] : $this->_field_data['args']['taxonomy_id'];

			if ( (int) $value != 0 )
				$result = wp_set_object_terms( $post_id, (int) $value, 	$taxonomy );

			return $result;
		}

		public function delete( $post_id = '' ) {
			return false;
		}
	}
}


if ( !class_exists( "Easy_CF_Field_Taxonomy_Checkboxes" ) ) {
	class Easy_CF_Field_Taxonomy_Checkboxes extends Easy_CF_Field {
		public function print_form( $object, $box ) {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' : $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' : $this->_field_data['input_class'];

			$id = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $this->_field_data['id'] : $this->_field_data['args']['taxonomy_id'];

			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] : $this->_field_data['label'];
			$value = $this->get();
			$this->fill_current_values( $value );

			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' : '<p><em>' . $this->_field_data['hint'] . '</em></p>';

			$taxonomy = $id;

			if ( !taxonomy_exists( $taxonomy ) )
				return;

			$terms = get_terms( $taxonomy, array( 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => 	false, 'hierarchical' => false ) );
			if ( is_wp_error( $terms ) )
				return;

			$selected = $this->get();

			$selected_terms = array();

			if ( !empty( $selected ) ) {
				if ( is_array( $selected ) ) {
					foreach( $selected as $item ) {
						$selected_terms[] = $item->term_id;
					}
				} else {
					$selected_terms[] = $selected->term_id;
				}
			}
			else {
				$no_selected = true;
			}

			$values = '';
			foreach( $terms as $term ) {

				$checked = '';
				if ( in_array( $term->term_id, $selected_terms ) )
					$checked = 'checked="checked" ';

				$values .= '<li><input type="checkbox" ' . $checked . 'value="' . $term->term_id . '" name="' . $id . '[]" id="' . $term->term_id . '"><label for="' . $term->term_id. '">' . $term->name . '</label></li>' . "\n";
			}
			$label_format =
				'<div class="%s taxonomydiv " id="%s">'.
				'<p><strong>%s</strong></p>'.
				'%s'.
				'<div class="tabs-panel"><ul class="%s">'.
				'%s'.
				'</ul></div></div>';
			printf( $label_format, $class, $id, $label, $hint, $input_class, $values  );
		}

		public function get( $post_id = '', $raw = false ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			}

			if ( !$post_id ) {
				return false;	// the post is not saved
			}

			$taxonomy = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $this->_field_data['id'] : $this->_field_data['args']['taxonomy_id'];

			$value = $this->global_cached_wp_get_object_terms( $post_id, $taxonomy );

			if ( is_wp_error( $value ) ) {
				return false;
			}
			if ( count( $value ) == 1 )
				return $value[0];

			return $value;
		}

		public function set( $values, $post_id = '' ) {
			if ( empty( $post_id ) ) {
				global $post;
				$post_id = $post->ID;
			}

			if ( !$post_id )
				return false;


			foreach ( $values as $id => $value )
				$values[$id] = (int) $value;

			$taxonomy = ( empty( $this->_field_data['args']['taxonomy_id'] ) ) ? $this->_field_data['id'] : $this->_field_data['args']['taxonomy_id'];

			if ( count($values) != 0 )
				$result = wp_set_object_terms( $post_id, $values, $taxonomy );

			return $result;
		}

		public function delete( $post_id = '' ) {
			return false;
		}
	}
}

if ( !class_exists( "Easy_CF_Field_Image" ) ) {
	class Easy_CF_Field_Image extends Easy_CF_Field {
		public function print_form() {
			$class = ( empty( $this->_field_data['class'] ) ) ? $this->_field_data['id'] . '_class' :  $this->_field_data['class'];
			$input_class = ( empty( $this->_field_data['input_class'] ) ) ? $this->_field_data['id'] . '_input_class' :  $this->_field_data['input_class'];
			$name = ( empty( $this->_field_data['name'] ) ) ? $this->_field_data['id'] :  $this->_field_data['name'];
			$id = ( empty( $this->_field_data['id'] ) ) ? $this->_field_data['id'] :  $this->_field_data['id'];
			$label = ( empty( $this->_field_data['label'] ) ) ? $this->_field_data['id'] :  $this->_field_data['label'];
			$value = $this->get();
			$hint = ( empty( $this->_field_data['hint'] ) ) ? '' :  '<p><em>' . $this->_field_data['hint'] . '</em></p>';
			$set_title = ( empty( $this->_field_data['set_title'] ) ) ? 'Set Image' : $this->_field_data['set_title'];
			$remove_title = ( empty( $this->_field_data['remove_title'] ) ) ? 'Remove Image' : $this->_field_data['remove_title'];
			$post_id = $this->get_post_id();

			if(isset($value) && $value != ''){
				$image = wp_get_attachment_metadata( $value );
				$text = ($image['sizes']['thumbnail']['file'] != '') ? '<img src="' . wp_get_attachment_thumb_url( $value ) . '"/>' : $image['file'];
				$display = '<input type="hidden" type="text" name="%s" id="%s" value="' . $value . '"/>
							<div id="upload_image_text-%s">' . $text . '</div>';
				$remove = '&nbsp;&nbsp;&nbsp;<a class="remove-%s" href="#">%s</a>';
			}
			else{
				$display = '<input type="hidden" type="text" name="%s" id="%s" value=""/>
							<div id="upload_image_text-%s" style="display: none;"></div>';
				$remove = '&nbsp;&nbsp;&nbsp;<a class="remove-%s" href="#" style="display: none;">%s</a>';
			}

			$label_format =
				'<div class="%s">'.
				'<p><label for="%s"><strong>%s</strong></label></p>'.
				
				$display .
				'<p><a class="thickbox" id="upload_image-%s" href="'. get_admin_url() .'media-upload.php?post_id=%s&amp;type=image&amp;&amp;tab=library&amp;TB_iframe=1&amp;width=640&amp;height=534" title="%s">%s</a>' . $remove . '</p>'.
				'</div>'.
				'<script type="text/javascript">
					jQuery("#upload_image-%s").click(function() {'.
				'		obj = jQuery(this);'.
				'		window.original_send_to_editor = window.send_to_editor;'.
				'		window.send_to_editor = function(html) {'.
				'			var imgClass = jQuery("img",html).attr("class");'.
				'			var imgSrc = jQuery("img",html).attr("src");'.
				'			var thumbImg = "";'.				
				'			if (!imgClass)'.
				'				var imgClass = jQuery(html).attr("class");'.
				'			imgClass = imgClass.substring( ( imgClass.lastIndexOf( "-" ) + 1 ), imgClass.length );'.
				'			thumbImg = imgSrc.substring( 0, imgSrc.lastIndexOf( "." ) ) + "-150x150" + imgSrc.substring( imgSrc.lastIndexOf( "." ), imgSrc.length );'.
 				'			obj.parent().prev().prev().val( imgClass );'.
				'			obj.parent().prev("div").html("<img src=\'" + thumbImg + "\'/>").show();'.
				'			jQuery(".remove-%s").show();'.
				'			tb_remove();'.
				'		}'.
				'	});'.
				'	jQuery(".remove-%s").click(function(){'.
				'		jQuery(this).hide().parent().prev().html("").prev().val("");'.
				'		return false;'.
				'	});'.
				'</script>';
			printf( $label_format, $class, $id, $label, $id, $id, $id, $id, $post_id, $set_title, $set_title, $id, $remove_title, $id, $id, $id );
		}
	}
}

if ( !class_exists( "Easy_CF_Validator_Required" ) ) {
	class Easy_CF_Validator_Required extends Easy_CF_Validator {
		public function get( $value = '' ) {
			return esc_attr( $value );
		}

		public function set( $value = '' ) {
			$value = esc_attr( trim( stripslashes( $value ) ) );
			return $value;
		}

		public function validate( $value = '' ) {
			if ( empty( $value ) )
				return false;
			else
				return true;
		}
	}
}

if ( !class_exists( "Easy_CF_Validator_No_HTML" ) ) {
	class Easy_CF_Validator_No_HTML extends Easy_CF_Validator {
		public function get( $value = '' ) {
			if ( is_array( $value ) )
				$value = join( ", ", $value );
			return strip_tags( $value );
		}

		public function set( $value = '' ) {
			if ( is_array( $value ) )
				$value = join( ", ", $value );
			$value = strip_tags( trim( stripslashes( $value ) ) );
			return $value;
		}

		public function validate( $value = '' ) {
			return true;
		}
	}
}

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

if ( !class_exists( "Easy_CF_Validator_URL" ) ) {

	class Easy_CF_Validator_URL extends Easy_CF_Validator {

		public function get( $value='' ) {
			return esc_url( $value );
		}

		public function set( $value='' ) {
			$value = esc_url( $value );
			return $value;
		}

		public function validate( $value='' ) {
			if ( empty( $value ) || esc_url( $value ) )
				return true;
			else
				return false;
		}
	}
}


if ( !class_exists( "Easy_CF_Validator_Description" ) ) {
	class Easy_CF_Validator_Description extends Easy_CF_Validator_No_HTML {

		public function set( $value='' ) {
			if ( is_array( $value ) )
				$value = join( ", ", $value );

			$value = strip_tags( trim( stripslashes( $value ) ) );
			$count = 200;
			if ( str_word_count( $value ) > $count  ) {
				$words = explode(' ', $value);
				array_splice( $words, $count );
			    $value = implode(' ', $words);
			}
			return $value;
		}

	}
}

if ( !class_exists( "Easy_CF_Validator_Array" ) ) {

	class Easy_CF_Validator_Array extends Easy_CF_Validator {

		public function validate( $value='' ) {
			if ( empty( $value ) || esc_url( $value ) )
				return true;
			else
				return false;
		}
	}
}
*/
