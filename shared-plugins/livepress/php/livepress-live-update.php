<?php
require_once ( LP_PLUGIN_PATH . 'php/livepress-config.php' );

/**
 * A piece of post content
 *
 * @author fgiusti
 */
class LivePress_Live_Update {
	/** Tag used to mark the metainfo inside a live update */
	private static $metainfo_tag = 'div';
	/** Class of the tag used  to mark the metainfo inside a live update */
	private static $metainfo_class = 'livepress-meta';

	/**
	 * Instance.
	 *
	 * @static
	 * @access private
	 * @var null
	 */
	private static $instance = NULL;

	/**
	 * Instance.
	 *
	 * @static
	 * @return LivePress_Live_Update
	 */
	public static function instance() {
		if (self::$instance == NULL) {
			self::$instance = new LivePress_Live_Update();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		$this->options = get_option( LivePress_Administration::$options_name );
		global $current_user;
		$this->user_options = get_user_option( LivePress_Administration::$options_name, $current_user->ID, false );

		add_shortcode( 'livepress_metainfo', array( &$this, 'shortcode' ) );
		if ( is_admin() ) {
			// setup WYSIWYG editor
			$this->add_editor_button();
		}
	}

	/**
	 * Add Editor button.
	 *
	 * @access private
	 */
	private function add_editor_button() {
		// Don't bother doing this stuff if the current user lacks permissions
		if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
			return;

		// Add only in Rich Editor mode
		if ( get_user_option('rich_editing') == 'true' ) {
			add_filter( 'teeny_mce_buttons', array( &$this, 'register_tinymce_button' ) );
			add_filter( 'mce_buttons', array( &$this, 'register_tinymce_button' ) );
		}
	}

	/**
	 * Register TinyMCE buttons.
	 *
	 * @param array $buttons Buttons array.
	 * @return mixed
	 */
	public function register_tinymce_button( $buttons ) {
		array_push( $buttons, 'separator', 'livepress' );
		return $buttons;
	}

	/**
	 * LivePress metainfo shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode contents.
	 */
	public function shortcode( $atts ) {
		// Extract the attributes
		extract( shortcode_atts( array(
			"author"        => "",
			"time"          => "",
			"avatar_url"    => NULL,
			"has_avatar"    => FALSE,
			"timestamp"     => "",
			"update_header" => "",
		), $atts ) );

		$options  = $this->options;
		$metainfo = "";
		if ($has_avatar || $avatar_url) {
			if ($avatar_url == null) {
				$metainfo .= self::get_avatar_img_tag($this->user_options['avatar_display']);
			} else {
				$metainfo .= self::avatar_img_tag($avatar_url);
			}
		}

		$settings      = get_option( 'livepress' );

		$update_format = '';
		if( isset( $settings['update_format'] ) ){
			$update_format = $settings['update_format'];
		}

		if ( 'default' === $update_format ) {
			if ($author) {
				$metainfo .= $this->format_author($author)." ";
			}

			if ($time) {
				if ($author) {
					$metainfo .= " <strong>|</strong> ";
				}
			}
		}

		if ($time) {
			$timestring = date( apply_filters( 'livepress_timestamp_time_format', 'g:i A' ), strtotime( $atts['timestamp'] ) + ( get_option('gmt_offset') * 3600 ) );

			if ( isset( $options['timestamp_format'] ) && 'timeof' === $options['timestamp_format'] ) {
					$metainfo .= '<span class="livepress-update-header-timestamp">';
					$metainfo .= str_replace( '###TIME###', $timestring, self::timestamp_html_template())." ";
					$metainfo = str_replace( '###TIMESTAMP###', $timestamp, $metainfo );
					$metainfo .= '</span>';
			} else {
					$metainfo .= '<span class="livepress-update-header-timestamp">';
					$metainfo .= str_replace('###TIME###', $time, self::timestamp_html_template())." ";
					$metainfo = str_replace( '###TIMESTAMP###', $timestamp, $metainfo );
					$metainfo .= '</span>';
			}
		}

		if ($update_header) {
			$metainfo .= '<span class="livepress-update-header">' . wptexturize( urldecode( $update_header ) ) . "</span> ";
		}

		if ($metainfo) {
			$metainfo = '<'. self::$metainfo_tag .' class="'. self::$metainfo_class .'">'
				. $metainfo
				. '</'. self::$metainfo_tag .'>';
		}

		return $metainfo;
	}

	/**
	 * Add user options to shortcode.
	 *
	 * @param string $content Post content.
	 */
	public function fill_livepress_shortcodes( $content ) {
	
		$options       = $this->options;
		$new_shortcode = "[livepress_metainfo";

		preg_match('/\[livepress_metainfo show_timestmp="(.*)"\]/s', $content, $show_timestmp );
		if ( ! empty( $show_timestmp[1] ) ) {
			$current_time_attr = ' time="'. $this->format_timestamp( current_time('timestamp') ) .'" ';
			if ($options['timestamp']) {
				if (isset($this->custom_timestamp)) {
					$custom_timestamp = strtotime($this->custom_timestamp);
					$new_shortcode   .= ' time="'. $this->format_timestamp($custom_timestamp) .'"';
				} else {
					$new_shortcode .= $current_time_attr;
				}
			}
			$new_shortcode   .= ' timestamp="'. date( 'c', current_time('timestamp', 1) ) .'"';
		}
		if (isset($this->custom_author_name)) {
			$authorname = $this->custom_author_name;
		} else {
			$authorname = self::get_author_display_name($options);
		}

		if ($authorname) {
			$new_shortcode .= ' author="'.$authorname.'"';
		}




		if ($options["include_avatar"]) {
			$new_shortcode .= ' has_avatar="1"';
			if (isset($this->custom_avatar_url)) {
				$new_shortcode .= ' avatar_url="'.$this->custom_avatar_url.'"';
			}
		}

		// Pass the update header thru to processed shortcode
		preg_match('/.*update_header="(.*)"\]/s', $content, $update_header);

		if ( isset( $update_header[1] ) && 'undefined' !== $update_header[1] ) {
			$new_shortcode .= ' update_header="' . $update_header[1] . '"';
		}

		$new_shortcode .= "]";

		// Replace empty livepress_metainfo with calculated one
		$content = preg_replace('/\[livepress_metainfo[^\]]*]/s', $new_shortcode, $content);

		// Replace POSTTIME inside livepress_metainfo with current time
		if ( ! empty( $show_timestmp[1] ) ) {
			return preg_replace('/(\[livepress_metainfo[^\]]*)POSTTIME([^\]]*\])/s', "$1".$current_time_attr."$2", $content);
		} else {
			return $content;
		}
	}

	/**
	 * Set a custom author name to be used instead of the current author name.
	 *
	 * @param string $name The custom author name.
	 */
	public function use_custom_author_name( $name ) {
		$this->custom_author_name = $name;
	}

	/**
	 * Set a custom timestamp to be used instead of the current time.
	 *
	 * @param string $time Timestamp.
	 */
	public function use_custom_timestamp( $time ) {
		$this->custom_timestamp = $time;
	}

	/**
	 * Set a custom avatar url to be used instead of selected one.
	 *
	 * @param string $avatar_url Avatar URL.
	 */
	public function use_custom_avatar_url( $avatar_url ) {
		$this->custom_avatar_url = $avatar_url;
	}

	/**
	 * Return the formatted HTML for the author of the livepress update.
	 *
	 * @access private
	 *
	 * @param string $author The author display name.
	 * @return string HTML formatted author.
	 */
	private function format_author( $author ) {
		$config = LivePress_Config::get_instance();
		return str_replace( '###AUTHOR###', $author, $config->get_option( 'author_template' ) );
	}

	/**
	 * The HTML image tag for the avatar from WP or Twitter based on user configuration.
	 *
	 * @static
	 *
	 * @param string $from The source of the avatar, can be "twitter" or "native".
	 * @return string HTML image tag.
	 */
	public static function get_avatar_img_tag( $from ) {
		$avatar_img_tag = get_avatar($user->ID, 30);
		if ( $from === 'twitter' && LivePress_Administration::twitter_avatar_url() ) {
			$avatar_img_tag = self::avatar_img_tag( LivePress_Administration::twitter_avatar_url() );
		}
		return $avatar_img_tag;
	}

	/**
	 * Avatar <img> tag.
	 *
	 * @static
	 *
	 * @param string $url Image source URL.
	 * @return string HTML img tag.
	 */
	public static function avatar_img_tag( $url ) {
		return "<img src='" . esc_url( $url ) ."' class='avatar avatar-30 photo avatar-default' height='30' width='30' />";
	}

	/**
	 * The author name choosen by the user to be displayed
	 *
	 * @static
	 *
	 * @param array $options author_display should be "custom" or "native". If "custom",
	 *                       author_display_custom_name should contain the name.
	 * @return string The name to be displayed or FALSE if something goes wrong.
	 */
	public static function get_author_display_name( $options ) {
		$author = FALSE;
		if ($options['author_display'] == 'custom') {
			$author = $options['author_display_custom_name'];
		} else {
			// TODO: decouple
			$user = wp_get_current_user();
			if ( $user->ID ) {
				if ( empty( $user->display_name ) ) {
					$author = $user->user_login;
				} else {
					$author = $user->display_name;
				}
			}
		}
		return $author;
	}

	/**
	 * Return the HTML for the timestamp.
	 *
	 * @access private
	 *
	 * @param int $timestamp Unix timestamp that defaults to current local time, if not given.
	 *
	 * @return string HTML formatted timestamp.
	 */
	private function format_timestamp( $timestamp = NULL ) {
		$config = LivePress_Config::get_instance();
		return date( $config->get_option( 'timestamp_template' ), $timestamp );
	}

	/**
	 * The user defined or default HTML template for the post timestamp.
	 *
	 * @static
	 *
	 * @return string HTML for the timestamp with ###TIME### where should go the formatted time.
	 */
	public static function timestamp_html_template() {
		$config = LivePress_Config::get_instance();
		return $config->get_option('timestamp_html_template');
	}

	/**
	 * The timestamp template.
	 *
	 * @static
	 *
	 * @return string Timestamp to be formatted as PHP date() function.
	 */
	public static function timestamp_template() {
		$config = LivePress_Config::get_instance();
		return $config->get_option('timestamp_template');
	}

	/**
	 * The author template.
	 *
	 * @static
	 *
	 * @return string Author template.
	 */
	public static function author_template() {
		$config = LivePress_Config::get_instance();
		return $config->get_option('author_template');
	}
}
