<?php
class Livepress_Configuration_Item {
	// Types
	public static $STRING   = 1;
	public static $LITERAL  = 2;
	public static $BOOLEAN  = 4;
	public static $BLOCK    = 8;
	public static $ENDBLOCK = 16;
	public static $ARRAY    = 32;

	/**
	 * Constructor.
	 *
	 * @param $name
	 * @param $value
	 * @param $type
	 */
	function __construct( $name, $value, $type ) {
		$this->name  = $name;
		$this->value = $value;
		$this->type  = $type;
		$this->all_values = array();
	}

	/**
	 * Render a configuration item.
	 *
	 * @param $options
	 * @return string
	 */
	function render($options) {
		$value     = $this->value;
		$separator = ",";

		if (array_key_exists('disable_comma', $options)) {
			$separator = "";
		}

		switch ($this->type) {
			case self::$STRING:
				$value   = esc_js($value);
				$element = "{$this->name}: '$value'";
				break;

			case self::$LITERAL:
				$element = "{$this->name}: $value";
				break;

			case self::$BOOLEAN:
				if ($value) {
					$element = "{$this->name}: true";
				} else {
					$element = "{$this->name}: false";
				}
				break;

			case self::$BLOCK:
				$element = "{$this->name}: {";
				break;

			case self::$ENDBLOCK:
				$element = "}";
				break;

			case self::$ARRAY:
				$element = $this->name.': [';
				$first   = TRUE;
				foreach($value as $v) {
					$element .= $first ? '' : ',';
					$element .= "'".esc_js($v)."'";
					$first    = FALSE;
				}
				$element .= ']';
				break;

			default:
				$element = '';
		}

		if ($this->type != self::$BLOCK) {
			$element .= $separator;
		}

		return $element;
	}

}

class LivePress_JavaScript_Config {
	/**
	 * Constructor.
	 */
	function __construct() {
		$this->values = array();
		$this->buffer = array();
		$this->all_values = array();
	}

	/**
	 * Add new configuration value.
	 *
	 * @param      $name
	 * @param      $value
	 * @param null $type
	 */
	function new_value($name, $value, $type = NULL ) {
		if ($type == NULL) {
			$type = Livepress_Configuration_Item::$STRING;
		}
		$this->values[] = new Livepress_Configuration_Item($name, $value, $type);
		$this->all_values[ $name ] = $value;
	}

	/**
	 * Flush.
	 */
	function flush() {
	}

	function get_values() {
		return $this->all_values;
	}
}
