<?php
/**
 * This is a helper class for WordPress that allows format HTML tags, including inputs, textareas, etc
 *
 * @author Rinat Khaziev
 * @version 0.1
 */
class Html_Helper {

	function __construct() {

	}

	/**
	 * Render multiple choice checkboxes
	 *
	 * @param string  $name
	 * @param string  $description
	 * @param array   $data
	 */
	function checkboxes( $name = '', $description = '', $data = array(), $checked = array() ) {
		if ( $name != '' ) {
			$name = filter_var( $name, FILTER_SANITIZE_STRING );
			if ( $description );
			echo $this->element( 'p', __( $description ) );
			echo '<input type="hidden" name="' . esc_attr( $name ) .'" value="" />';
			foreach ( (array) $data as $item ) {
				$is_checked_attr =  in_array( $item, (array) $checked ) ? ' checked="true" ' : '';
				$item = filter_var( $item, FILTER_SANITIZE_STRING );
				echo '<div class="sm-input-wrapper">';
				echo '<input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $item ) . '" id="' .esc_attr( $name ) . esc_attr( $item )  . '" ' . $is_checked_attr . ' />';
				echo '<label for="' .esc_attr( $name ) . esc_attr( $item )  . '">' . esc_attr ( $item ) . '</label>';
				echo '</div>';
			}
		}
	}

	function _checkbox( $name = '', $description = '', $value = '', $atts, $checked = array() ) {
		// Generate unique id to make label clickable
		$rnd_id = uniqid( 'uniq-label-id-' );
		return '<div class="checkbox-option-wrapper"><input type="checkbox" id="' . esc_attr( $rnd_id ) . '" value="'. esc_attr( $value ) . '" name="' . esc_attr( $name ) . '" '.$this->_format_attributes( $atts ) . ' /><label for="' . esc_attr( $rnd_id ) . '">' .  esc_html ($description ) . '</label></div>';
	}

	function _radio( $name = '', $description = '', $value = '', $atts, $checked = array() ) {
		// Generate unique id to make label clickable
		$rnd_id = uniqid( 'uniq-label-id-' );
		return '<div class="checkbox-option-wrapper"><input type="radio" id="' . esc_attr( $rnd_id ) . '" value="'. esc_attr( $value ) . '" name="' . esc_attr( $name ) . '" '.$this->_format_attributes( $atts ) . ' /><label for="' . esc_attr( $rnd_id ) . '">' .  esc_html ($description ) . '</label></div>';
	}

	/**
	 * This method supports unlimited arguments,
	 * each argument represents html value
	 */
	function table_row() {
		$data = func_get_args();
		$ret = '';
		foreach ( $data as $cell )
			$ret .= $this->element( 'td', $cell, null, false );
		return "<tr>" . $ret . "</tr>\n";
	}

	/**
	 * easy wrapper method
	 *
	 * @param unknown $type (select|input)
	 * @param string  $name
	 * @param mixed   $data
	 */
	function input( $type, $name, $data = null, $attrs = array() ) {
		switch ( $type ) {
		case 'select':
			return $this->_select( $name, $data, $attrs );
			break;
		case 'text':
		case 'hidden':
		case 'submit':
		case 'file':
		case 'checkbox':
			return $this->_text( $name, $type,  $data, $attrs ) ;
			break;
		case 'radio':
			return $this->_radio( $name, $data, $attrs ) ;
		default:
			return;
		}


	}

	/**
	 * This is a private method to render inputs
	 *
	 * @access private
	 */
	function _text( $name = '', $type='text', $data = '', $attrs = array() ) {
		return '<input type="' . esc_attr( $type ) . '" value="'. esc_attr( $data ) . '" name="' . esc_attr( $name ) . '" '.$this->_format_attributes( $attrs ) . ' />';
	}

	/**
	 *
	 *
	 * @access private
	 */
	function _select( $name, $data = array(), $attrs ) {
		$ret  = '';
		foreach ( (array) $data as $key => $value ) {
			$attrs_to_pass = array( 'value' => $key );
			if ( isset( $attrs[ 'default' ] ) && $key == $attrs[ 'default' ] )
				$attrs_to_pass[ 'selected' ] = 'selected';
			$ret .= $this->element( 'option', $value, $attrs_to_pass, false );
		}
		return '<select name="' . esc_attr( $name ) . '">' . $ret . '</select>';
	}


	function table_head( $data = array(), $params = null ) {
		echo '<table><thead>';
		foreach ( $data as $th ) {
			echo '<th>' . esc_html( $th ) . '</th>';
		}
		echo '</thead><tbody>';
	}

	function table_foot() {
		echo '</tbody></table>';
	}

	function form_start( $attrs = array() ) {
		echo '<form' . $this->_format_attributes( $attrs ) .'>';
	}

	function form_end() {
		echo '</form>';
	}

	/**
	 * Renders html element
	 *
	 * @param string  $tag    one of allowed tags
	 * @param string  content innerHTML content of tag
	 * @param array   $params additional attributes
	 * @param bool    $escape escape innerHTML or not, defaults to true
	 * @return string rendered html tag
	 */
	function element( $tag, $content, $params = array(), $escape = true ) {
		$allowed = apply_filters( 'hh_allowed_html_elements' , array( 'div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'option', 'label', 'textarea', 'select', 'option' ) );
		$attr_string = $this->_format_attributes( $params );
		if ( in_array( $tag, $allowed ) )
			return "<{$tag} {$attr_string}>" . ( $escape ? esc_html ( $content ) : $content ) . "</{$tag}>";
	}

	/**
	 * Formats and returns string of allowed html attrs
	 *
	 * @param array   $attrs
	 * @return string attributes
	 */
	function _format_attributes( $attrs = array() ) {
		$attr_string = '';
		foreach ( (array) $attrs as $attr => $value ) {
			if ( in_array( $attr, $this->_allowed_html_attrs() ) )
				$attr_string .= " {$attr}='" . esc_attr ( $value ) . "'";
		}
		return $attr_string;
	}

	/**
	 * Validates and returns url as A HTML element
	 *
	 * @param string  $url    any valid url
	 * @param string  $title
	 * @param unknown $params array of html attributes
	 * @return string html link
	 */
	function a( $url, $title = '', $params = array() ) {
		$attr_string = $this->_format_attributes( $params );
		if ( filter_var( trim( $url ), FILTER_VALIDATE_URL ) )
			return '<a href="' . esc_url( trim( $url ) ) . '" ' . $attr_string . '>' . ( $title != '' ? esc_html ( $title ) : esc_url( trim( $url ) ) ) . '</a>';
	}

	/**
	 * Returns allowed HTML attributes
	 */
	function _allowed_html_attrs() {
		return apply_filters( 'hh_allowed_html_attributes', array( 'href', 'class', 'id', 'value', 'action', 'name', 'method', 'selected', 'checked', 'for', 'multiple' ) );
	}
}
