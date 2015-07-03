<?php
/*
Plugin Name: Image Metadata Cruncher
Description: Gives you ultimate controll over which image metadata (EXIF or IPTC) WordPress extracts from an uploaded image and where and in what form it then goes. You can even specify unlimited custom post meta tags as the target of the extracted image metadata. 
Version: 1.8
Author: Peter Hudec
Author URI: http://peterhudec.com
Plugin URI: http://peterhudec.com/programming/2012/11/13/image-metadata-cruncher-wp-plugin/
License: GPL2
*/


/**
 * Main plugin class
 */
class Image_Metadata_Cruncher {
	
	// stores metadata between wp_handle_upload_prefilter and add_attachment hooks
	private $metadata;
	
	private $keyword;
	private $keywords;
	private $pattern;
	public $plugin_name = 'Image Metadata Cruncher';
	private $version = 1.5;
	private $after_update = FALSE;
	private $settings_slug = 'image_metadata_cruncher-options';
	private $donate_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RJYHYJJD2VKAN';
	
	/**
	 * Constructor
	 */
	function __construct() {
		$options = get_option( $this->plugin_name );
		$this->after_update = intval( $options['version'] ) < intval( $this->version );
		
		// the EXIF and IPTC mapping arrays are quite long, so they deserve to be in separate files
		require_once 'includes/exif-mapping.php';
		require_once 'includes/iptc-mapping.php';
		
		// create regex patterns
		$this->patterns();
		
		/////////////////////////////////////////////////////////////////////////////////////
		// WordPress Hooks
		/////////////////////////////////////////////////////////////////////////////////////
		
		// plugin settings hooks
		
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta',  array( $this, 'plugin_row_meta' ), 10, 2 );
		register_activation_hook( __FILE__, array( $this, 'defaults' ) );
		add_action('admin_init', array( $this, 'init' ) );
		add_action('admin_menu', array( $this, 'options' ) );
		
		// plugin functionality hooks
		add_action( 'wp_handle_upload_prefilter', array( $this, 'wp_handle_upload_prefilter' ) );
		add_action( 'add_attachment', array( $this, 'add_attachment' ) );
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Functionality
	/////////////////////////////////////////////////////////////////////////////////////
	
	private function insert_next_to_key( &$array, $key, $value, $insert_before = FALSE ) {
		
		// get position of the index in the array
		$offset = array_search( $key, array_keys( $array ) );
		
		if ( ! $insert_before ) {
			$offset++;
		}
		
		$left = array_slice( $array, 0, $offset );
		$right = array_slice( $array, $offset );
		
		$array = array_merge( $left, $value, $right );
	}
	
	/**
	 * The wp_handle_upload_prefilter hook gets triggered before
	 * wordpress erases all the image metadata
	 * 
	 * @return untouched file
	 */
	public function wp_handle_upload_prefilter( $file ) {
			
		// get meta
		$this->metadata = $this->get_meta_by_path( $file['name'], $file['tmp_name'] );
		
		// return untouched file
		return $file;
	}
	
	/**
	 * The add_attachment hook gets triggered when the attachment post is created.
	 * In Wordpress media uploads are handled as posts.
	 */
	public function add_attachment( $post_ID, $template = array() ) {
		
		// get plugin options
		$options = get_option( $this->prefix );
		
		// uploaded image is handled as post by WordPress
		$post = get_post( $post_ID );
		
		// Try to get template from $template array then from $options.
		$title = isset( $template['title'] ) ? $template['title'] : $options['title'];
		$caption = isset( $template['caption'] ) ? $template['caption'] : $options['caption'];
		$description = isset( $template['description'] ) ? $template['description'] : $options['description'];
		$alt = isset( $template['alt'] ) ? $template['alt'] : $options['alt'];
		
		if ( isset( $template['custom_meta'] ) ) {
			$meta = $template['custom_meta'];
		} elseif ( isset( $options['custom_meta'] ) ) {
			$meta = $options['custom_meta'];
		} else {
			$meta = array();
		}
		
		// Apply new values to post properties:
		
		// title
		$post->post_title = $this->render_template( $title );
		// caption
		$post->post_excerpt = $this->render_template( $caption );
		// description
		$post->post_content = $this->render_template( $description );
		// alt is meta attribute
		update_post_meta( $post_ID, '_wp_attachment_image_alt', $this->render_template( $alt ) );
		
		// add custom post meta if any
		foreach ( $meta as $key => $value ) {
			// get value
			$value = $this->render_template( $value );
			
			// update or create the post meta
		    add_post_meta( $post_ID, $key, $value, true ) or update_post_meta( $post_ID, $key, $value );
		}
		
		// finally sanitize and update post
		// sanitize_post( $post );
		wp_update_post( $post );
	}
	
	
	/**
	 * Extracts image metadata from the image specified by its path.
	 * 
	 * @return structured array with all available metadata
	 */
	public function get_meta_by_path( $name, $tmp_name = NULL ) {
		
		if ( !$tmp_name ) {
			$tmp_name = $name;
		}
		
		$this->metadata = array();
				
		// extract metadata from file
		//  the $meta variable will be populated with it
		$size = getimagesize( $tmp_name, $meta );
		
		// extract pathinfo and merge with size
		$this->metadata['Image'] = array_merge( $size, pathinfo( $name ) );
		
		// remove index 'dirname'
		unset($this->metadata['Image']['dirname']);
		
		// parse iptc
		//  IPTC is stored in the APP13 key of the extracted metadata
		$iptc = iptcparse( $meta['APP13'] );
		
		if ( $iptc ) {
			// symplify array structure
			foreach ( $iptc as &$i ) {
				// if the array has only one item
				if ( count( $i ) <= 1 ) {
					$i = $i[0];
				}
			}
			
			// add named copies to all found IPTC items
			foreach ( $iptc as $key => $value ) {
				if ( isset( $this->IPTC_MAPPING[ $key ] ) ) {
					$name = $this->IPTC_MAPPING[ $key ];
					
					// add "Caption" alias to "Caption-Caption-Abstract"
					if ( $key == '2#120' ) {
						$this->insert_next_to_key( $iptc, $key, array( 'Caption' => $value ) );
					}
					
					$this->insert_next_to_key( $iptc, $key, array( $name => $value ) );
				}
			}
		}
		
		if ( $iptc ) {
			$this->metadata['IPTC'] = $iptc;
		}
		
		// parse exif		
		$exif = NULL;
		
		// the exif_read_data() function throws a warning if it is passed an unsupported file format.
		// This warning is impossible to catch so we have to check the file mime type manually
		$safe_file_formats = array(
			'image/jpg',
			'image/jpeg',
			'image/tif',
			'image/tiff',
		);
		
		
		if ( in_array( $size['mime'], $safe_file_formats ) ) {
		
			$exif = exif_read_data( $tmp_name );
			
			if ( is_array( $exif ) ) {
				// add named copies of UndefinedTag:0x0000 items to $exif array
				foreach ( $exif as $key => $value ) {
					// check case insensitively if key begins with "UndefinedTag:"
					if ( strtolower( substr( $key, 0, 13 ) ) == 'undefinedtag:' ) {
						// get EXIF tag name by ID and convert it to base 16 integer
						$id = intval( substr( $key, 13 ), 16 );
						
						if ( isset( $this->EXIF_MAPPING[ $id ] ) ) {
							// create copy with EXIF tag name as key
							$name = $this->EXIF_MAPPING[ $id ];
							//$exif[ $name ] = $value;
							$this->insert_next_to_key( $exif, $key, array( $name => $value ) );
						}
					}
				}
			}
			
		}
		
		if ( $exif ) {
			$this->metadata['EXIF'] = $exif;
		}
		
		// no need for return but good for testing
		return $this->metadata;
	}
	
	
	/**
	 * Extracts image metadata from the image specified by the attachment post ID.
	 * 
	 * @return structured array with all available metadata
	 */
	public function get_meta_by_id( $ID ) {
		$post = get_post( $ID );
		return $this->get_meta_by_path( $post->guid );
	}
	
	/**
	 * Extracts metadata from the image file belonging to the attachment post
	 * specified by the $ID and updates the post according to supplied $template array.
	 * The $template array should have following structure:
	 * 
	 * $template = array(
	 * 		'title' => 'Title template',
	 * 		'caption' => 'Caption template',
	 * 		'description' => 'Description template',
	 * 		'alt' => 'Alt template',
	 * 		'custom_meta' => array(
	 * 			'meta-name' => 'Meta template',
	 * 			'another-meta-name' => 'Another meta template',
	 * 		),
	 * )
	 * 
	 * Settings templates will be used for missing indexes in the array.
	 */
	public function crunch( $ID, $template = array() ) {
		# Get the attachment post.
		$post = get_post( $ID );
		
		# Extract metadata.
		$this->get_meta_by_id( $ID );
		
		# Update attachment.
		$this->add_attachment( $ID, $template );
	}
	
	
	/**
	 * Replaces template tags in template string.
	 * 
	 * @return Sanitized template string with processed template tags.
	 */
	private function render_template( $template ){
		
		// restore escaped characters
		$template = str_replace( 
			array(
				'&lt;',
				'&gt;',
				'&#039;',
				'&quot;'
			),
			array(
				'<',
				'>',
				"'",
				'"'
			),
			$template
		);
		
		// replace each found tag with parse_tag method return value
		$result = preg_replace_callback( $this->pattern, array( $this, 'parse_tag' ), $template );
		
		if ( $result === NULL ) {
			$result = $template;
		}
	        
		// handle escaped curly brackets
		$result = str_replace(array('\{', '\}'), array('{', '}'), $result);
		
		return sanitize_text_field( $result );
	}
	
	/**
	 * Converts array keys recursively
	 * 
	 * @return recursive copy of an array with lowercase keys
	 */
	private function array_keys_to_lower_recursive( $array ) {
		$array = array_change_key_case( $array, CASE_LOWER );
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				// if value is array call this function recursively with the value as argument
				$array[ $key ] = $this->array_keys_to_lower_recursive( $value );
			}
		}
		return $array;
	}
	
	/**
	 * Searches for metadata case insensitively by category and value
	 * 
	 * @return found value
	 */
	private function get_metadata( $metadata, $category, $key ) {
		// convert to lowercase to allow for case insensitive search
		$category = strtolower( $category );
		$key = strtolower( $key );
		
		if ( isset( $metadata[ $category ][ $key ] ) ) {
			return $metadata[ $category ][ $key ];
		}
	}
	
	/**
	 * Analyses template keyword and searches for its value in $this->metadata
	 * 
	 * @return found value
	 */
	private function get_meta_by_key( $key, $delimiter = NULL ){
		
		// convert metadata keys to lowercase to allow for case insensitive keys
		$metadata = $this->array_keys_to_lower_recursive( $this->metadata );
		
		if ( ! $delimiter ) {
	    	// if no delimiter specified in the tag, coma and space will be used as default
	    	$delimiter = ', ';
	    }
				
		// separate key prefix and suffix on the first occurence of a colon
		$pieces = explode( ':', $key, 2 );
		
		// get case insensitive prefix
		$category = strtolower( $pieces[0] );
		
		if ( count( $pieces ) > 1 ) {
			// parse path pieces separated by ">" greater than character
			$path = explode( '>', $pieces[1] );
		} else {
			// tag is not valid without anything after colon e.g. "EXIF:"
			return; // exit and return nothing			
		}
		
		// start search
		$value = $key = NULL;
		
		if ( $category == 'all' ) {
			
			// get nested level specified by path
			$value = $this->explore_path( $this->metadata, $path );
			
			switch ( strtolower( $path[0] )  ) {
				case 'php':
					// return found value as human readable PHP array
					return print_r( $value, TRUE );
					break;
					
				case 'json':
					// return found value as JSON
					return json_encode( $value );
					break;
					
				case 'jsonpp':
					// return found value as pretty printed JSON
					
					// JSON_PRETTY_PRINT constant is available since PHP 5.4.0
					$JSON_PRETTY_PRINT = defined( 'JSON_PRETTY_PRINT' ) ? JSON_PRETTY_PRINT : NULL ;
					
					return json_encode( $value, $JSON_PRETTY_PRINT );
					break;
					
				case 'xml':
					// not implemented yet
					break;
				
				default:
					break;
			}
		} elseif ( $category == 'exif' ) {
			
			// key is the first part of the path
			$key = $path[0];
			
			// try to find value directly in the keys returned by exif_read_data() function
			// e.g. {EXIF:Model}
			$value = $this->get_metadata( $metadata, $category, $key );
			
			if ( ! $value ) {
				// some EXIF tags are returned by the exif_read_data() functions like "UndefinedTag:0x####"
				// so if nothing found try looking up for "UndefinedTag:0x####"
				
				// since we need an uppercase hex number e.g. 0xA432 but with lowercase 0x part
				//  we convert the key to base 16 integer and then back to uppercase string
				$key = strtoupper( dechex( intval( $key, 16 ) ) );
				
				// construct the "UndefinedTag:0x####" key and search for it in the extracted metadata
				$key = "UndefinedTag:0x$key";
				$value = $this->get_metadata( $metadata, $category, $key );
			}
			
		} else {
			// try to find anything that is provided (handles IPTC too)
			$key = $path[0];
			$value = $this->get_metadata( $metadata, $category, $key );
		}
		
		// get the level of the value specified in the path
		$value = $this->explore_path( $value, $path );
		
		if ( is_array( $value ) ) {
			// if value is array convert it to string
			$value = implode( $delimiter, $value );
		}
		
		// some IPTC metadata contain the  End Of Transmission character, which strips everythig after it
		$value = str_replace("", '', $value);
		
		return $value;
	}
	
	/**
	 * Traverses value according to path
	 * 
	 * @return value found at the level specified in the path
	 */
	private function explore_path( $value, $path, $index = 0 ) {
		// if value is array
		if ( is_array( $value ) ) {
			$index++;
			if ( isset( $path[ $index ] ) ) {
				// if index set in the path, get its value
				
				// temporarily convert value and path to lowercase to allow for key insensitive lookup 
				$value_lower = array_change_key_case( $value, CASE_LOWER );
				$path_lower = strtolower( $path[ $index ] );
				$value = $value_lower[ $path_lower ];
				
				// before returning check if there is not another part of the path
				return $this->explore_path( $value, $path, $index );
			} else {
				return $value;
			}
		} else {
			// if value is not an aray return it
			return $value;
		}
	}
	
	/**
	 * Processes the match of a regular expression which matches the template tag and
	 * captures keywords group and success, default and delimiter options
	 * 
	 * @return tag replacement or empty string
	 */
	private function parse_tag( $match ) {
		
		$keywords = isset( $match['keywords'] ) ? explode( '|', $match['keywords'] ) : array();
		$success = isset( $match['success'] ) ? $match['success'] : FALSE;
		$default = isset( $match['default'] ) ? $match['default'] : FALSE;
		$delimiter = isset( $match['delimiter'] ) ? $match['delimiter'] : FALSE;
		
		if ( $keywords ) {
			foreach ( $keywords as $keyword ) {
		    	// search for key in metadata extracted from the image
		    	
		    	//TODO: Sanitize?
		        $meta = $this->get_meta_by_key( trim( $keyword ), $delimiter );
		        
		        if ( $meta ) {
		        	// return first found meta
		        	if ( $success ) {
		        		// if success option specified
		        		//   return success string with $ dolar sign replaced by found meta
		        		//   and handle escaped characters
		        		return str_replace(
		        			array(
		        				'\$', // replace escaped dolar sign with some unusual unicode character
		        				'$', // replace dolar signs for meta value
		        				'\"', // replace escaped doublequote for doublequote
		        				'\u2328' // replace \u2328 with dolar sign
		        			),
		        			array(
		        				'\u2328',
		        				$meta,
		        				'"',
		        				'$'
							),
		        			$success
						);
		        	} else {
		        		return $meta;
		        	}
		        }
		    }
		}
	    
		
		// if flow gets here nothing was found so...
		if ( $default ){
			// ...return default if specified or...
			return $default;
		} else {
			// ...empty string
			return '';
		}
		
	}
	
	/**
	 * Declares all regex patterns used inside the class
	 */
	private function patterns() {
		
		// matches key in form of: abc:def(>ijk)*
		$this->keyword = '
			[\w]+ # category prefix
			: # colon
			[\w.:#-]+ # keyword first part
			(?: # zero or more keyword parts
				> # part delimiter
				[\w.:#-]+ # part
			)*
		';
		
		// matches keys in form of: key( | key)*
		$this->keywords = '
			'.$this->keyword.' # at least one key
			(?: # zero or more additional keys
				\s* # space
				\| # colon delimiter
				\s* # space
				'.$this->keyword.' # key
			)*
		';
		
		// matches tag in form of: { keys @ "success" % "default" # "identifier" }
		$this->pattern = '/
			{
			\s*
			(?P<keywords>'.$this->keywords.')
			\s*
			(?: # success
				@ # identifier
				\s* # space
				" # opening quote
				(?P<success> # capture value
					(?: # must contain
						\\\\" # either escaped doublequote \"
						| # or
						[^"] # any non doublequote character
					)* # zero or more times
				)
				" # closing quote
			)?
			\s*
			(?: # default
				% # identifier
				\s* # space
				" # opening quote
				(?P<default> # capture value
					(?: # must contain
						\\\\" # either escaped doublequote \"
						| # or
						[^"] # any non doublequote character
					)* # zero or more times
				)
				" # closing quote
			)?
			\s*
			(?: # delimiter
				\# # identifier
				\s* # space
				" # opening quote
				(?P<delimiter> # capture value
					(?: # must contain
						\\\\" # either escaped doublequote \"
						| # or
						[^"] # any non doublequote character
					)* # zero or more times
				)
				" # closing quote
			)?
			\s*
			}
		/x';
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////
	// Settings
	/////////////////////////////////////////////////////////////////////////////////////
	
	public $prefix = 'image_metadata_cruncher';
	
	/**
	 * Adds action links to the plugin
	 * 
	 * @return updated plugin links
	 */
	public function plugin_action_links( $links, $file ) {
		
	    static $this_plugin;
	    if ( ! $this_plugin ) {
	        $this_plugin = plugin_basename( __FILE__ );
	    }
		
	    if ( $file == $this_plugin ) {
	    	$url = esc_url( admin_url( "admin.php?page={$this->settings_slug}" ) );
	        $settings_link = "<a href=\"$url\">Settings</a>";
	        array_unshift( $links, $settings_link );
	    }
		
	    return $links;
	}
	
	/**
	 * Adds action links to the plugin row
	 * 
	 * @return updated plugin links
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			$url = esc_url( admin_url( "admin.php?page={$this->settings_slug}" ) );
	        $links[] = "<a href=\"$url\">Settings</a>";
			$links[] = "<a href=\"$this->donate_url\">Donate</a>";
		}
		return $links;
	}
	
	/////////////////////////////////////////////////////////////////////////////////////
	// JavaScript and CSS
	/////////////////////////////////////////////////////////////////////////////////////
	
	function js_rangy_core() { wp_enqueue_script( "{$this->prefix}_rangy_core" ); }
	function js_rangy_selectionsaverestore() { wp_enqueue_script( "{$this->prefix}_rangy_selectionsaverestore" ); }
	function js() { wp_enqueue_script( "{$this->prefix}_script" ); }
	function css() { wp_enqueue_style( "{$this->prefix}_style" ); }
	
	/**
	 * Default plugin options
	 */
	public function defaults() {
		add_option( $this->prefix, array(
			'version' => $this->version,
			'title' => '{ IPTC:Headline }',
			'alt' => '',
			'caption' => '',
			'enable_highlighting' => 'enable',
			'description' => '{ IPTC:Caption | EXIF:ImageDescription }',
			'custom_meta' => array()
		) );
	}
	
	/**
	 * Adds a section to the plugin admin page
	 */
	private function section( $id, $title ) {
		add_settings_section(
			"{$this->prefix}_section_{$id}", // section id
			$title, // title
			array( $this, "section_{$id}" ), // callback
			"{$this->prefix}-section-{$id}" // page
		);
	}
	
	/**
	 * Plugin initialization
	 */
	public function init() {
	    
	    // register stylesheets and scripts for admin
	    wp_register_script( "{$this->prefix}_rangy_core", plugins_url( 'js/ext/rangy-core.js', __FILE__ ) );
	    wp_register_script( "{$this->prefix}_rangy_selectionsaverestore", plugins_url( 'js/ext/rangy-selectionsaverestore.js', __FILE__ ) );
	    wp_register_script( "{$this->prefix}_script", plugins_url( 'js/script.js', __FILE__ ) );
	    wp_register_style( "{$this->prefix}_style", plugins_url( 'style.css', __FILE__ ) );
	    
	    ///////////////////////////////////
	    // Sections
	    ///////////////////////////////////
	    $this->section( 0, 'Tag Syntax Highlighting:' );
	    $this->section( 1, 'Media form fields:' );
	    $this->section( 2, 'Custom image meta tags:' );
	    $this->section( 3, 'Available metadata keywords:' );
	    $this->section( 4, 'How to Use Template Tags' );
	    $this->section( 5, 'About Image Metadata Cruncher:' );
	    
	    ///////////////////////////////////
	    // Options
	    ///////////////////////////////////
	    
	    // Title
	    // register a new setting...
	    register_setting(
	        "{$this->prefix}_title",          // option group
	        $this->prefix,                    // option name
	        array( $this, 'sanitizer' )       // sanitizer
	    );     
		         
	    // ...and add it to a section
	    add_settings_field(
	        "{$this->prefix}_title",          // field id
	        'Title:',                         // title
	        array( $this, 'title_cb' ),       // callback
	        "{$this->prefix}-section-1",      // section page
	        "{$this->prefix}_section_1"       // section id
		);
	    
	    // Alternate text
	    register_setting(
	        "{$this->prefix}_alt",            // option group
	        $this->prefix,                    // option name
	        array( $this, 'sanitizer' )       // sanitizer
	    );
		
	    add_settings_field(
	        "{$this->prefix}_alt",            // field id
	        'Alternate text:',                // title
	        array( $this, 'alt_cb' ),         // callback
	        "{$this->prefix}-section-1",      // section page
	        "{$this->prefix}_section_1"       // section id
		);
	    
	    // Caption
	    register_setting(
	        "{$this->prefix}_caption",        // option group
	        $this->prefix,                    // option name
	        array( $this, 'sanitizer')        // sanitizer
	    );
		
	    add_settings_field(
	        "{$this->prefix}_caption",        // field id
	        'Caption:',                       // title
	        array( $this, 'caption_cb' ),     // callback
	        "{$this->prefix}-section-1",      // section page
	        "{$this->prefix}_section_1"       // section id
		);
	    
	    // Description
	    register_setting(
	        "{$this->prefix}_description",    // option group
	        $this->prefix,                    // option name
	        array( $this, 'sanitizer' )       // sanitizer
	    );
		
	    add_settings_field(
	        "{$this->prefix}_description",    // field id
	        'Description:',                   // title
	        array( $this, 'description_cb' ), // callback
	        "{$this->prefix}-section-1",      // section page
	        "{$this->prefix}_section_1"       // section id
		);
	}
	
	/**
	 * Plugin options callback
	 */
	public function options() {
		$page = add_plugins_page(
			'Image Metadata Cruncher',
			'Image Metadata Cruncher',
			'manage_options',
			"{$this->prefix}-options",
			array( $this, 'options_cb' )
		);
		
		add_action( 'admin_print_scripts-' . $page, array( $this, 'js_rangy_core' ) );
		add_action( 'admin_print_scripts-' . $page, array( $this, 'js_rangy_selectionsaverestore' ) );
	    add_action( 'admin_print_scripts-' . $page, array( $this, 'js' ) );
	    add_action( 'admin_print_styles-' . $page, array( $this, 'css' ) );
	}
	
	/**
	 * Options page callback
	 */
	public function options_cb() { ?>
		<div id="metadata-cruncher" class="wrap metadata-cruncher">
			<h2>Image Metadata Cruncher Options</h2>
			<?php settings_errors(); ?>
			<h2 class="nav-tab-wrapper">
				<?php
					if ( isset( $_GET['tab'] ) ) {
						$active_tab = $_GET['tab'];
					} else {
						$active_tab = 'settings';
					}
					
					function active_tab( $value, $at  ) {
						if ( $at == $value ) {
							echo 'nav-tab-active';
						}
					}
				?>
				<a href="?page=image_metadata_cruncher-options&tab=settings" class="nav-tab <?php active_tab( 'settings', $active_tab ); ?>">Settings</a>
				<a href="?page=image_metadata_cruncher-options&tab=metadata" class="nav-tab <?php active_tab( 'metadata', $active_tab ); ?>">Available Metadata</a>
				<a href="?page=image_metadata_cruncher-options&tab=usage" class="nav-tab <?php active_tab( 'usage', $active_tab ); ?>"><?php _e( 'How to Use Template Tags' ) ?></a>
				<a href="?page=image_metadata_cruncher-options&tab=about" class="nav-tab <?php active_tab( 'about', $active_tab ); ?>">About</a>
			</h2>
			
			<?php if ( $active_tab == 'settings' ): ?>
				<form action="options.php" method="post">
					<?php
						settings_fields( "{$this->prefix}_title" ); // renders hidden input fields
						settings_fields( "{$this->prefix}_alt" ); // renders hidden input fields
						do_settings_sections( "{$this->prefix}-section-0" );
						do_settings_sections( "{$this->prefix}-section-1" );
						do_settings_sections( "{$this->prefix}-section-2" );
						submit_button();
					?>
				</form>
			<?php elseif ( $active_tab == 'metadata' ): ?>
				<?php do_settings_sections( "{$this->prefix}-section-3" ); ?>
			<?php elseif ( $active_tab == 'usage' ): ?>
				<?php do_settings_sections( "{$this->prefix}-section-4" ); ?>
			<?php elseif ( $active_tab == 'about' ): ?>
				<?php do_settings_sections( "{$this->prefix}-section-5" ); ?>
			<?php endif ?>
		</div>
	<?php }
	
	///////////////////////////////////
    // Section callbacks
    ///////////////////////////////////
    
    public function section_0() {
    	$options = get_option( $this->prefix );
    	
		if ( isset( $options['version'] ) && intval( $options['version'] ) > 1.0 ) {
			//TODO: Move to defaults
			// if the plugin has been updated from version 1.0 enable highlighting by default
    		$options['enable_highlighting'] = 'enable';
			$options['version'] = $this->version;
			update_option( $this->prefix, $options );
		}
    	?>
        <p>
        	The fancy syntax highlighting of template tags may in some cases cause strange caret/cursor behaviour.
        	If you encounter any of such problems, you can disable this feature here.
        </p>
        <input type="checkbox" value="enable" <?php checked( 'enable', $options['enable_highlighting'] ); ?> name="<?php echo $this->prefix; ?>[enable_highlighting]" id="enable-highlighting" />
        <label for="highlighting">Enable highlighting</label>
    <?php }
    
    // media form fields
    public function section_1() { ?>
    	<p>
		    Specify text templates with which should the media upload form be prepopulated with.
		    Use template tags like this <code>{ IPTC:Headline }</code> to place found metadata into the templates.
			Template tags can be as simple as <code>{ EXIF:Model }</code> or more complex like
			<code>{ EXIF:LensInfo>2 | EXIF:LensModel @ "Lens info is $" % "Lens info not found" # "; " }</code>.
		</p>
		<p>
			Tags with invalid syntax will be ignored by the plugin and will apear
			unchanged in the <em>Upload Media Form</em> fields.
			For your better orientation valid template tags get highlighted as you type.
		</p>
		<p>
			To find out more about the template tag syntax read the
			<a href="?page=image_metadata_cruncher-options&tab=usage">How to Use Template Tags</a> section.
		</p>
	<?php }
	
	// custom post metadata
	public function section_2() { ?>
	    <?php $options = get_option( $this->prefix ); ?>
		<p>Here you can specify your own meta fields that will be saved to the database with the uploaded image.</p>
		<table id="custom-meta-list" class="widefat">
			<colgroup>
				<col class="col-name" />
				<col class="col-template" />
				<col class="col-delete" />
			</colgroup>
			<thead>
				<th>Name</th>
				<th>Template</th>
				<th>Delete</th>
			</thead>
			<?php if ( isset( $options['custom_meta'] ) ) : ?>
				<?php if ( is_array( $options['custom_meta'] ) ): ?>
					<?php foreach ( $options['custom_meta'] as $key => $value ): ?>
						<?php
						$key = sanitize_text_field($key);
						$value = sanitize_text_field($value);
						?>
						<tr>
			                <td><input type="text" class="name" value="<?php echo $key ?>" /></td>
			                <td>
			                	<div class="highlighted ce" contenteditable="true"><?php echo $value ?></div>
			                	<?php // used textarea because hidden input caused bugs when whitespace got converted to &nbsp; ?>
			                	<textarea class="hidden-input template" name="<?php echo $this->prefix; ?>[custom_meta][<?php echo $key ?>]" ><?php echo $value ?></textarea>
			                </td>
			                <td><button class="button">Remove</button></td>
						</tr>
					<?php endforeach; ?>
				<?php endif ?>
			<?php endif ?>
		</table>
		<div>
			<button id="add-custom-meta" class="button">Add New Field</button>
		</div>	
	<?php }
	
	// list of available metadata tags
	public function section_3() { ?>
		<p>
			The <strong>Image Metadata Cruncher</strong> template tags are <strong>case insensitive</strong> and so are the metadata keywords.
			Thus <code>EXIF:ImageHeight</code> is the same as <code>exif:imageheight</code> and <code>EXIF:IMAGEHEIGHT</code>.
		</p>
		
		<h2>SPECIAL:</h2>
		<p>
			The <code>ALL:php</code>, <code>ALL:json</code> and <code>ALL:jsonpp</code> keywords
			return all the available information structured as nested arrays,
			formatted according to the suffix after the colon <code>:</code>.
			You can access the nested values using the <code>&gt;</code> greater than notation.
			For example
			<code>{ALL:php>iptc}</code>,
			<code>{ALL:json>exif}</code>,
			<code>{ALL:jsonpp>iptc>caption-abstract}</code>,
			<code>{ALL:php>exif>computed}</code>,
			<code>{ALL:json>exif>computed>ApertureFNumber}</code>,
			<code>{ALL:jsonpp>exif>0xA432>3}</code> and so forth.
		</p>
		<div>
			<table>
				<tr>
					<td class="tag-list special">
						<span class="tag">
							<span class="first">
								<span class="prefix">ALL</span><span class="colon">:</span><span class="part">php</span>						
							</span>
						</span>
					</td>
					<td>
						Prints out all found metadata formated as PHP array.
					</td>
				</tr>
				<tr>
					<td class="tag-list special">
						<span class="tag">
							<span class="first">
								<span class="prefix">ALL</span><span class="colon">:</span><span class="part">json</span>						
							</span>
						</span>
					</td>
					<td>
						Prints out all found metadata formated as JSON.
					</td>
				</tr>
				<tr>
					<td class="tag-list special">
						<span class="tag">
							<span class="first">
								<span class="prefix">ALL</span><span class="colon">:</span><span class="part">jsonpp</span>						
							</span>
						</span>
					</td>
					<td>
						Prints out all found metadata formated as pretty printed JSON (works only in PHP 5.4.0, in older versions behaves like ALL:json).
					</td>
				</tr>
			</table>			
		</div>
		<br />
		
		<h2>Image:</h2>
		<p>
			Basic information related to the image file.
		</p>
		
		<div class="tag-list iptc">
			<?php 
				$image_keys = array('bits', 'channels', 'mime', 'basename', 'filename', 'extension');
			?>
			<?php foreach ( $image_keys as $value ): ?>
				<span class="tag">
					<span class="first">
						<span class="prefix">Image</span><span class="colon">:</span><span class="part"><?php echo $value; ?></span>						
					</span>
				</span>
			<?php endforeach; ?>
		</div>
		<br />
		
		<h2>IPTC:</h2>
		<p>
			The plugin gets the image <strong>IPTC</strong> metadata using the <strong>PHP</strong> <code>iptcparse()</code> function. 
			You can access the IPTC metadata either by name <code>{IPTC:City}</code>, or
			by <strong>ID</strong> <code>{IPTC:2#090}</code>.
			
			Use the <code>{ALL:PHP}</code> template tag to get a list of all the metadata the plugin can read from the image,
			or <code>{ALL:PHP>IPTC}</code> to get only the <strong>IPTC</strong> metadata.
			Here is a link to the <a target="_blank" href="http://www.iptc.org/std/IIM/4.1/specification/IIMV4.1.pdf">official IPTC metadata specification PDF</a>.
		</p>
		<p>
			This list of <strong>IPTC</strong> tags was automatically generated from the
			<a target="_blank" href="http://owl.phy.queensu.ca/~phil/exiftool/TagNames/IPTC.html">Phil Harvey's ExifTool IPTC Tag List</a>.
		</p>
		<div class="tag-list iptc">
			<?php // Generate the IPTC list automatically from $this->IPTC_MAPPING ?>
			<?php foreach ( $this->IPTC_MAPPING as $key => $value ): ?>
				<span class="tag">
					<span class="first">
						<span class="prefix">IPTC</span><span class="colon">:</span><span class="part"><?php echo $value; ?></span>						
					</span>
					or
					<span class="second">
						<span class="prefix">IPTC</span><span class="colon">:</span><span class="part"><?php echo $key; ?></span>
					</span>
				</span>
			<?php endforeach; ?>
		</div>
		<br />
		
		<h2 >EXIF:</h2>
		<p>
			The plugin gets the image <strong>EXIF</strong> metadata using the <strong>PHP</strong> <code>exif_read_data()</code> function. 
			You can access the same <strong>EXIF</strong> metadata either by name
			<code>{EXIF:Model}</code> or by ID, which is a hexadecimal number <code>{EXIF:0x0110}</code>.
			If you think that the uploaded image has any <strong>EXIF</strong> metadata not listed here
			you can still try to get it by name <code>{EXIF:FooBar}</code> or ID <code>{EXIF:0x123456}</code>
			and if the <strong>PHP</strong> <code>exif_read_data()</code> function finds it, the template tag will return it.
			Use the <code>{ALL:PHP}</code> template tag to get a list of all the metadata the plugin can read from the image,
			or <code>{ALL:PHP>EXIF}</code> to get only the <stron>EXIF</strong> metadata.
			Here is a link to the <a target="_blank" href="http://www.cipa.jp/english/hyoujunka/kikaku/pdf/DC-008-2010_E.pdf">official EXIF metadata specification PDF</a>.
		</p>
		<p>
			This list of <strong>EXIF</strong> tags was automatically generated from the
			<a target="_blank" href="http://owl.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html">Phil Harvey's ExifTool EXIF Tag List</a>.
		</p>
		<div class="tag-list exif">
			<?php // Generate the EXIF list automatically from $this->EXIF_MAPPING ?>
			<?php foreach ($this->EXIF_MAPPING as $key => $value): ?>
				<span class="tag">
					<span class="first">
						<span class="prefix">EXIF</span><span class="colon">:</span><span class="part"><?php echo $value; ?></span>						
					</span>
					or
					<span class="second">
						<span class="prefix">EXIF</span><span class="colon">:</span><span class="part"><?php echo sprintf( "0x%04x", $key ); ?></span>
					</span>
				</span>
			<?php endforeach; ?>
		</div>
		
	<?php }
	
	// how to use template tags
	public function section_4()	{ ?>
		<p>
			The Image Metadata Cruncher plugin uses template tags enclosed in curly brackets
			<code>{}</code>
			to place metadata into your predefined templates.
			You can use multiple template tags inside one template.
			The tags are case insensitive so
			<code>{ EXIF:ImageDescription }</code> is the same as
			<code>{ exif:imagedescription }</code> or
			<code>{ EXIF:IMAGEDESCRIPTION }</code>.
			Only tags with valid syntax will be processed by the plugin.
			Faulty tags will be ignored and printed out in the <em>Upload New Media</em> form as they are.
			Valid template tags get highlighted as you type, so you can immediately see whether they are valid or not.
		</p>
		<br />
		
		<h2>Simplest template tag</h2>
		<p>
			The simplest tag consist of a metadata keyword inside curly brackets.
			The keyword itself consist of prefix defining the metadata category e.g.
			<code>IPTC</code>, <code>EXIF</code> a colon
			<code>:</code>
			and the actual metadata identifier
			<code>Make</code>:
		</p>
		<code>
			{ EXIF:Make }
		</code>
		<p>
			If for instance you only need to retrieve the information about the camera make
			from the image EXIF metadata you can use it like this:
		</p>
		<div class="ce highlighted">
			{ EXIF:Make }
		</div>	
		<p>
			If the image contains the requested metadata the tag will be replaced with the found value and
			if the image was taken with a Canon camera, the text in the
			<em>Upload New Media</em> form would be:
		</p>
		<div class="example ok">
			Canon
		</div>
		<p>
			If the image doesn't contain such information the tag will return an empty string:
		</p>
		<div class="example err"></div>
		<br />
		
		<h2>Template tag with fallback keywords</h2>
		<p>
			It can happen that the image doesn't contain the metadata specified in the template tag
			but contains another, similar one in some other metadata field.
			In such cases you can specify a fallback keyword inside the tag after the first one
			delimited by the <code>|</code> pipe sign.
		</p>
		<code>
			{ IPTC:Caption | EXIF:ImageDescription }
		</code>
		<p>
			If for instance you want to retrieve the image's IPTC caption and in case it is not available
			try to get the EXIF image description you can use a tag like this:
		</p>
		<div class="ce highlighted">
			{ IPTC:Caption | EXIF:ImageDescription }
		</div>
		<p>
			If the image contains the IPTC caption it will return it:
		</p>
		<div class="example ok">
			Sample IPTC caption.
		</div>
		<p>
			If not, it tries to retrieve the value specified in the next keyword after the pipe, which is
			<code>EXIF:ImageDescription</code>. If the image contains it, the tag will be replaced with it.
		</p>
		<div class="example ok">
			Sample EXIF image description.
		</div>
		<p>
			Again, if the image doesn't contain any of the requested metadata, it will return an empty string:
		</p>
		<div class="example err"></div>
		<p>
			You can chain as many fallback keywords as you want.
			The tag will be replaced with the first found value in specified order.
		</p>
		<code>
			{ IPTC:Headline | IPTC:ObjectName | EXIF:ImageDescription | EXIF:ModifyDate }
		</code>
		<br />
		<br />
		
		<h2>Available Metadata</h2>
		<p>
			The first part of a keyword (before the colon) specifies the metadata group,
			the second part (after the colon) specifies a particular metadata within the group.
			There are three main groups of metadata:
			<code>EXIF</code>,
			<code>IPTC</code> and
			<code>ALL</code>.
		</p>
		<p>
			The <code>EXIF</code> and <code>IPTC</code> groups are self explanatory.
			Check the
			<a href="?page=image_metadata_cruncher-options&tab=metadata">Available Metadata</a> 
			section to get the list of available suffixes.
		</p>
		<p>
			The <code>ALL</code> group is special. Its main purpose is debugging.
			It returns all metadata found in the uploaded image formatted according to the keyword suffix,
			which can be <code>php</code>, <code>json</code> and <code>jsonpp</code>.
		</p>
		<p>
			Here is a truncated example of
			<code>{ ALL:php }</code>
			tag in action.
		</p>
		<div class="ce highlighted" >
			{ ALL:php }
		</div>
		<p>
			It returns all metadata found in the image formatted as nested PHP arrays.
		</p>
		<div class="example ok"><pre style="overflow: hidden">Array
(
    [Image] => Array
        (
            [0] => 589
            [1] => 632
            [2] => 2
            [3] => width="589" height="632"
            [bits] => 8
            [channels] => 3
            [mime] => image/jpeg
        )

    [IPTC] => Array
        (
            [1#005] => Destination
            [Destination] => Destination
            [1#000] => �
            [EnvelopeRecordVersion] => �
            [1#050] => ProductID
            [ProductID] => ProductID
            (...rest truncated for brevity...)
        )

    [EXIF] => Array
        (
            [FileName] => phpbNUtQp
            [FileDateTime] => 1352388440
            [FileSize] => 145110
            [FileType] => 2
            [MimeType] => image/jpeg
            [SectionsFound] => ANY_TAG, IFD0, THUMBNAIL, EXIF
            [COMPUTED] => Array
                (
                    [html] => width="589" height="632"
                    [Height] => 632
                    [Width] => 589
                    [IsColor] => 1
                    [ByteOrderMotorola] => 0
                    [ApertureFNumber] => f/2.8
                    [FocusDistance] => 4294967296.00m
                    [Thumbnail.FileType] => 2
                    [Thumbnail.MimeType] => image/jpeg
                )
            (...rest truncated for brevity...)
        )
)</pre></div>
		
		
		<br />
		
		<h2>Accessing Nested Metadata</h2>
		<p>
			As you can see on the example above some of the metadata like
			<code>EXIF:COMPUTED</code> or
			<code>EXIF:0xA432</code>
			are in the form of an array.
			You can request such metadata directly:
		</p>
		<div class="ce highlighted" >
			{ EXIF:COMPUTED }
		</div>
		<p>
			If found the value will be returned as a comma separated list.
		</p>
		<div class="example ok">
			width="589" height="632", 632, 589, 1, 0, f/2.8, 4294967296.00m, 2, image/jpeg
		</div>
		<p>
			Or you can acces the items of the array by specifiing the desired index
			after a <code>&gt;</code> greater than sign:
		</p>
		<div class="ce highlighted" >
			{ EXIF:COMPUTED>FocusDistance }
		</div>
		<p>
			If found the value will be returned directly.
		</p>
		<div class="example ok">
			4294967296.00m
		</div>
		<p>
			The path can be nested arbitrarily deep. You can even use it with the
			<code>ALL</code> category like this:
		</p>
		<div class="ce highlighted" >
			{ ALL:json>EXIF>COMPUTED }
		</div>
		<p>
			If found the value will be returned as a JSON array.
		</p>
		<div class="example ok">
			{"html":"width="589" height="632"","height":632,"width":589,"iscolor":1,"byteordermotorola":0,"aperturefnumber":"f/2.8","focusdistance":"4294967296.00m","thumbnail.filetype":2,"thumbnail.mimetype":"image/jpeg"}
		</div>
		<br />
		
		<h2>Other Tag Options</h2>
		<p>
			Tags are replaced by empty string if no metadata found.
			This can sometimes lead to undesired results:
		</p>
		<div class="ce highlighted" >
			The picture was taken with { EXIF:Make } camera.
		</div>
		<p>
			If found, there's no problem.
		</p>
		<div class="example ok">
			The picture was taken with Canon camera.
		</div>
		<p>
			If not found, the result is a pointless sentence with double whitespace between
			the words <em>with</em> and <em>camera</em>.
		</p>
		<div class="example err">
			The picture was taken with&nbsp;&nbsp;camera.
		</div>
		<br />
		
		<h3>The Default Option</h3>
		<p>
			We can avoid that by using the
			<strong>default</strong> option in the form <code>% "not found text"</code>.
			The previous example would then look like this:
		</p>
		<div class="ce highlighted" >
			The picture was taken with { EXIF:Make % "unknown" } camera.
		</div>
		<p>
			If found, the tag would be replaced by the found value.
		</p>
		<div class="example ok">
			The picture was taken with Canon camera.
		</div>
		<p>
			If not found, the default text will be used.
		</p>
		<div class="example err">
			The picture was taken with unknown camera.
		</div>
		<br />
		
		<h3>The Success Option</h3>
		<p>
			The <strong>success</strong> option in the form <code>@ "success $ text"</code>
			together with the default option allow you to have greater controll over the resulting text.
			The <code>$</code> dollar sign has a special meaning and will be replaced by the found value.
			Here is an example:
		</p>
		<div class="ce highlighted" >
			{ EXIF:Model @ "The picture was taken with $." % "Camera info is not available!" }
		</div>
		<p>
			If found, the tag would be replaced by the string specified in the success option,
			with the <code>$</code> dollar sign replaced by the found value.
		</p>
		<div class="example ok">
			The picture was taken with Canon EOS 7D.
		</div>
		<p>
			If not found, the default text will be used.
		</p>
		<div class="example err">
			Camera info is not available!
		</div>
		<br />
		
		<h3>The Delimiter Option</h3>
		<p>
			The <strong>delimiter</strong> option in the form <code># "delimiter text"</code>
			allows you to replace the default delimiter
			<code>, </code> which separates the values returned by array metadata.
		</p>
		<div class="ce highlighted" >
			{ EXIF:LensInfo # " >>> " }
		</div>
		<p>
			If the found metadata is an array, the delimiter string will be used to separate its values.
		</p>
		<div class="example ok">
			70/1 >>> 200/1 >>> 0/0 >>> 0/0
		</div>
		<br />
		
		<h3>Special Characters in Options</h3>
		<p>
			Except for the <strong>success</strong> option there is only one
			special character <code>&quot;</code> the doublequote which must be escaped
			by a backslash <code>\&quot;</code> if you want to use it inside the string.
			In the <strong>success</strong> option also the <code>$</code>
			dollar sign must be escaped <code>\$</code> if you don't want it to be replaced
			by the found value.
		</p>
		<br />
		
		<h3>Printing Out Valid Tags</h3>
		<p>
			If for some strange reason you want to print out a valid tag instead of being processed
			use escaped curly brackets <code>\{\}</code>
		</p>
		<div class="ce highlighted" >
			{ EXIF:Make }, \{ EXIF:Make \}
		</div>
		<p>
			The first tag will be processed, the second ignored and printed out.
		</p>
		<div class="example ok">
			Canon, { EXIF:Make }
		</div>
		<br />
		
		<h2>Using it All Together</h2>
		<p>
			You can use all the tag options together, but they must appear in the tag
			in a particular order.
			You can skip any of the option but you need to preserve the order.
		</p>
		<ol>
			<li>
				Metadata keywords <code>IPTC:Headline | IPTC:ObjectName | EXIF:ImageDescription</code>
			</li>
			<li>
				Success option <code>@ "success text"</code>
			</li>
			<li>
				Default option <code>@ "default text"</code>
			</li>
			<li>
				Delimiter option <code>@ "delimiter text"</code>
			</li>
		</ol>
		<p>
			 The whitespace inside tags has no special meaning and you can completely skip it.
		</p>
		<p>
			 Here are some examples of valid tags:
		</p>
		<div class="ce highlighted" >
			{ EXIF:LensInfo>2 } array index 2
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensInfo | EXIF:LensModel | EXIF:LensMake } fallback keywords
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:SceneCaptureType | EXIF:ExposureMode | IPTC:TimeSent @ "Info: $" % "No info found!" # " \ " } all together
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensInfo | EXIF:LensModel % "No lens info found!" # " >>> " } default and delimiter
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensInfo @ "Lens info: $" # " >>> " } success and delimiter
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:Make @ "Camera is \"$\"" } success with escaped quotes
		</div>
		<br />
		<div class="ce highlighted" >
			{ IPTC:ObjectName } is the same as { IPTC:2#005 }
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:ExposureIndex } is the same as { EXIF:0xa215 }
		</div>
		<br />
		<div class="ce highlighted" >
			{ foo:bar % "I bet this text gets printed!" } insane keywords are still valid
		</div>
		<br />
		<p>
			 And here some invalid ones:
		</p>
		<div class="ce highlighted" >
			{ EXIF:LensInfo> } trailing > 
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensInfo | EXIF:LensModel | EXIF:LensMake | } trailing |
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:Make @ "Camera is "$"" } unescaped quotes in option string
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensInfo  # " >>> " % "No lens info found!" } options in bad order
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensInfo # " >>> " @ "Lens info: $" } options in bad order
		</div>
		<br />
		<div class="ce highlighted" >
			{ EXIF:LensMake % "No lens info found!" @ "Lens info: $" } options in bad order
		</div>
		<br />
		<br />
		<br />
		<br />
		<br />
		<br />
		
	<?php }
	
	// about
	public function section_5()	{ ?>
		<p>
			Created just for fun by me <strong>Peter Hudec</strong>.
			You cand find out more about me at <a href="http://peterhudec.com" target="_blank">peterhudec.com</a>.
		</p>
		<p>
			This plugin is and allways will be free but if you can't help yourself and want to pay for it anyway, you can do so by clicking the button below <strong>:-)</strong><br />
		</p>
		<form action="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RJYHYJJD2VKAN" method="post">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="RJYHYJJD2VKAN">
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
		</form>
	<?php }
		
	///////////////////////////////////
    // Options callbacks
    ///////////////////////////////////
    
    /**
	 * General callback for media form fields
	 */
	private function cb( $key ) {
		$options = get_option( $this->prefix );
		$value = sanitize_text_field($options[$key]);
		$key = sanitize_text_field($key);
		?>
		
		<div class="highlighted ce" contenteditable="true"><?php echo $value; ?></div>
		<?php // used textarea because hidden input caused bugs when whitespace got converted to &nbsp; ?>
		<textarea class="hidden-input" id="<?php echo $this->prefix; ?>[<?php echo $key; ?>]" name="<?php echo $this->prefix; ?>[<?php echo $key; ?>]"><?php echo $value; ?></textarea>
	<?php }
	
	public function title_cb() { $this->cb( 'title' ); }
	
	public function alt_cb() { $this->cb( 'alt' ); }
	
	public function caption_cb() { $this->cb( 'caption' ); }
	
	public function description_cb() { $this->cb( 'description' ); }
	
	/**
	 * Escapes dangerous characters from settings form fields
	 */
	public function sanitizer( $input ) {
				
		$output = array();
		
		foreach ( $input as $key => $value ) {
			
			if ( is_array( $value ) ) {
				// if is array iterate over it...
				
				$output[ $key ] = array();
				foreach ( $value as $k => $v ) {
					// ...and sanitize both key and value
					$output[ $key ][ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
				}
				
			} else {
				// sanitize value
				$output[ $key ] = sanitize_text_field( $value );
			}
		}
		return $output;
	}
}

// instantiate the plugin
$image_metadata_cruncher = new Image_Metadata_Cruncher();

?>