<?php
/**
 * LivePress Post
 */

require_once( LP_PLUGIN_PATH . 'php/livepress-config.php' );

class LivePress_Post {
	/**
	 * @var string $NUM_CHARS_HASH Used to limit number of chars in
	 * post updates id
	 */
	public static $NUM_CHARS_ID = 8;
	/**
	 * @var string $ALLOWED_CHARS_ON_ID Chars allowed to be in the ID
	 */
	public static $ALLOWED_CHARS_ON_ID = array('0','1','2','3','4','5','6','7','8',
		'9','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q',
		'r','s','t','u','v','w','x','y','z','_','A','B','C','D','E','F','G','H',
		'I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
	/** Tag used to mark live updates */
	private static $chunks_tag = 'div';
	/** Class of the tag used to mark live updates */
	private static $chunks_class = 'livepress-update';
	private static $add_to_chunks_class_when_avatar = 'livepress-has-avatar';

	/** Class of the tag used to mark a stubs live update
	 *  that's used to enable the user to add update using the normal editor */
	private static $stub_chunks_class = 'livepress-update-stub';
	/** ID of the tag used to mark live updates,
	 *  text enclose in [] will be exchanged */
	private $chunks_id = 'livepress-update-[ID]';

	private static $global_tag = 'all';

	private $id = null;
	private $content = null;

	/**
	 * Constructor.
	 *
	 * @param $content
	 * @param $id
	 */
	public function  __construct( $content, $id ) {
		$this->id = $id;
		$this->content = $content;
		$this->options = get_option( LivePress_Administration::$options_name );
		global $current_user;
		$this->user_options = get_user_option( LivePress_Administration::$options_name, $current_user->ID, false );
		$this->format_class = isset( $this->options['update_format'] ) ? $this->options['update_format'] : 'default';
	}

	/**
	 * Parse the content.
	 *
	 * @access private
	 *
	 * @return DOMDocument The parsed content.
	 */
	private function parse_content() {
		$content = stripslashes( $this->content );
		// just incase we get bad HTML
		$content = balanceTags( $content, true );
		$dom = new DOMDocument();
		$xml = $this->get_valid_xml( $content );
		// Suppress XML parse warnings
		$previous_libxml_use_internal_errors_value = libxml_use_internal_errors( true );
		$parse_success = $dom->loadXML( html_entity_decode( $xml ) );
		libxml_clear_errors();
		// Restore previous error handling setting
		libxml_use_internal_errors( $previous_libxml_use_internal_errors_value );
		if ( ! $parse_success ) {

			$dom->loadHTML( $content );
		}
		return $dom;
	}

	/**
	 * Generates a valid xml string.
	 *
	 * @access private
	 *
	 * @param string $content The content of the xml.
	 * @return string The content enclose by valid xml tags.
	 */
	private function get_valid_xml($content) {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<'.self::$global_tag.'>'.$content.'</'.self::$global_tag.'>';
		return $xml;
	}


	/**
	 * Get the number of post updates in the post.
	 *
	 * @return int Updates count.
	 */
	public function get_updates_count() {
		$dom      = $this->parse_content();
		$all_node = $dom->childNodes->item( 0 );
		$count    = 0;
		if ( isset( $all_node->childNodes->length ) ){
			for ( $number = 0; $number < $all_node->childNodes->length; $number++ ) {
				$top_node = $all_node->childNodes->item( $number );
				$is_chunk_element = $top_node->nodeType == XML_ELEMENT_NODE
					&& $top_node->tagName == self::$chunks_tag
					&& in_array(self::$chunks_class,
					explode( ' ', $top_node->getAttribute( 'class' ) ));
				if ( $is_chunk_element ) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * Saves the dom as content.
	 *
	 * @access private
	 *
	 * @param DOMDocument $dom The dom that represents the content.
	 */
	private function save_dom( $dom ) {
		$all_node = $dom->childNodes->item( 0 );
		$output = $dom->SaveXML( $all_node );
		$global_tag_length = strlen( self::$global_tag );
		// + 2 is for the < and > symbols
		$this->content = substr( $output, $global_tag_length + 2, strlen( $output ) -2 * $global_tag_length - 5 );
	}

	/**
	 * Check is there child element nodes for node.
	 *
	 * @static
	 * @access private
	 *
	 * @param DOMElement $node Node to check.
	 * @return bool is $node contains child element nodes.
	 */
	private static function has_child($node) {
		if ( $node->has_childNodes() ) {
			foreach ( $node->childNodes as $c ) {
				if ( $c->nodeType == XML_ELEMENT_NODE ) {
					return true; }
			}
		}
		return false;
	}

	/**
	 * Ensure post content has each livepress chunk surrounded by self::$chunks_tag
	 */
	public function normalize_content() {
		$dom = $this->parse_content();
		$all_node = $dom->childNodes->item( 0 );
		$current_update_ids = array();
		for ( $number = 0; $number < $all_node->childNodes->length; $number++ ) {
			$top_node = $all_node->childNodes->item( $number );
			if ( $top_node->tagName == self::$chunks_tag && $top_node->getAttribute( 'id' ) != '' ) {
				$current_update_ids[] = $top_node->getAttribute( 'id' );
			}
		}

		$changed = false;
		$need_to_be_enclosed = array();
		for ( $number = 0; $number < $all_node->childNodes->length; $number++ ) {
			$top_node = $all_node->childNodes->item( $number );
			$is_chunk_element = $top_node->nodeType == XML_ELEMENT_NODE
				&& $top_node->tagName == self::$chunks_tag
				&& in_array(self::$chunks_class,
				explode( ' ', $top_node->getAttribute( 'class' ) ));
			if ( $is_chunk_element ) {
				$top_node_classes = explode( ' ', $top_node->getAttribute( 'class' ) );
				if ( count( $need_to_be_enclosed ) > 0 ) {
					$this->enclose_indexes_by_chunk_mark_tag(
						$need_to_be_enclosed,$all_node, $dom,
					$current_update_ids);
					$need_to_be_enclosed = array();
				}
				if ( in_array( self::$stub_chunks_class, $top_node_classes ) ) {
					if ( self::has_child( $top_node ) || strlen( trim( $top_node->nodeValue ) ) ) {
						$nodes = array();
						for ( $i = 0; $i < $top_node->childNodes->length; $i++ ) {
							$nodes[] = $top_node->childNodes->item( $i );
						}
						foreach ( $nodes as $node ) {
							$top_node->removeChild( $node );
						}

						$this->enclose_nodes_by_chunk_mark_tag($nodes, $all_node,
						$dom, $current_update_ids, $top_node);
					}
					$changed = true;
					$all_node->removeChild( $top_node );
				} elseif ( $top_node->getAttribute( 'id' ) === '' ) {
					// If isn't a stub update and doesn't have id, should put livepress id
					// this shouldn't happen unless in cases before hash feature was done
					$chunk_id = $this->next_post_update_id( $current_update_ids );
					$top_node->setAttribute( 'id', $chunk_id );
					$changed = true;
				}
			} else {
				$changed = true;
				$need_to_be_enclosed[] = $number;
			}
		}
		if ( count( $need_to_be_enclosed ) > 0 ) {
			$this->enclose_indexes_by_chunk_mark_tag($need_to_be_enclosed,
			$all_node, $dom, $current_update_ids);
		}

		if ( $changed ) {
			$this->save_dom( $dom );
		}
	}

	/**
	 * Check if the list of nodes to be enclose aren't just empty text nodes, if not
	 * alters the dom to enclose the specified nodes with the micro-update livepress mark tag.
	 *
	 * @access private
	 *
	 * @param array       $nodes_indexes List of nodes indexes to be enclosed.
	 * @param DOMNode     $parent_node   Parent node of the nodes to be enclosed.
	 * @param DOMDocument $dom           The DOM where the nodes belongs.
	 * @param array       $current_ids   All the current used ids on updates.
	 */
	private function enclose_indexes_by_chunk_mark_tag( $nodes_indexes, $parent_node, $dom, $current_ids ) {
		$nodes = array();
		foreach ( $nodes_indexes as $i ) {
			$nodes[] = $parent_node->childNodes->item( $i );
		}

		$just_empty_textnode = true;
		$took_off_len = 0;
		foreach ( $nodes as $node ) {
			if ( $node->nodeType == XML_TEXT_NODE && $node->isWhitespaceInElementContent() ) {
				$took_off_len++;
			} else {
				$just_empty_textnode = false;
				break;
			}
		}
		if ( $just_empty_textnode ) {
			return;
		}
		$nodes     = array_slice( $nodes, $took_off_len );
		$last_node = $nodes[count( $nodes ) - 1];
		$ref_node  = $last_node->nextSibling;

		foreach ( $nodes as $node ) {
			$parent_node->removeChild( $node );
		}
		return $this->enclose_nodes_by_chunk_mark_tag( $nodes, $parent_node, $dom, $current_ids, $ref_node );
	}

	/**
	 * Alters the dom to enclose the specified nodes with the micro-update
	 * livepress mark tag.
	 *
	 * @access private
	 *
	 * @param array       $nodes       List of nodes to be enclosed.
	 * @param DOMNode     $parent_node Parent node where the nodes will be enclosed.
	 * @param DOMDocument $dom         The DOM where the nodes belongs.
	 * @param array       $current_ids All the current used ids on updates.
	 * @param DOMNode     $ref_node    The nodes will be inserted before this one.
	 */
	private function enclose_nodes_by_chunk_mark_tag($nodes, $parent_node, $dom,
			$current_ids, $ref_node = null) {
		$chunk_mark = $dom->createElement( self::$chunks_tag );
		$chunk_id   = $this->next_post_update_id( $current_ids );
		$chunk_mark->setAttribute( 'id', $chunk_id );

		// Useful for one-line oEmbeds
		$chunk_mark->appendChild( $dom->createTextNode( "\n" ) );

		foreach ( $nodes as $node ) {
			$chunk_mark->appendChild( $node );
		}

		// Useful for one-line oEmbeds
		$chunk_mark->appendChild( $dom->createTextNode( "\n" ) );

		$output = $dom->SaveXML( $chunk_mark );
		$chunks_class = self::$chunks_class;
		if ( preg_match( '/\[livepress_metainfo.+has_avatar.*\]/s', $output ) ) {
			$chunks_class .= ' '.self::$add_to_chunks_class_when_avatar;
		}
		$chunk_mark->setAttribute( 'class', $chunks_class );

		if ( $ref_node ) {
			$parent_node->insertBefore( $chunk_mark, $ref_node );
		} else {
			$parent_node->appendChild( $chunk_mark );
		}
	}

	/**
	 * Get content.
	 */
	public function get_content() {
		return $this->content;
	}

	/**
	 * Gets the HTML of a stub post update, so user can start to use it
	 * in the normal editor.
	 *
	 * @static
	 *
	 * @return string HTML stub of a post update.
	 */
	public static function get_stub_update() {
		$stub  = '<'.self::$chunks_tag;
		$stub .= ' class="';
		$stub .= self::$chunks_class;
		$stub .= ' ';
		$stub .= self::$stub_chunks_class;
		$stub .= ' ';
		$stub .= self::format_class;
		$stub .= '">';
		$stub .= "\n\n";
		$stub .= '</'.self::$chunks_tag.'>';
		return $stub;
	}

	/**
	 * Get the hash for the next post update id.
	 *
	 * @param array $current_ids An array with the strings of the used ids, passed by reference.
	 * @return string A post update id.
	 */
	private function next_post_update_id( &$current_ids ) {
		do {
			$rnd_id = array_flip( self::$ALLOWED_CHARS_ON_ID );
			$rnd_id = array_rand( $rnd_id, self::$NUM_CHARS_ID );
			$rnd_id = implode( $rnd_id );
			$rnd_id = str_replace( '[ID]', "$this->id-$rnd_id", self::$chunks_id );
		} while (in_array( $rnd_id, $current_ids ));

		$current_ids[] = $rnd_id;
		return $rnd_id;
	}

	/**
	 * All approved comments.
	 *
	 * @static
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of comments, if found.
	 */
	public static function all_approved_comments( $post_id ) {
		return get_comments(
			array( 'post_id' => $post_id, 'status' => 'approve', 'order' => 'ASC' )
		);
	}

}
