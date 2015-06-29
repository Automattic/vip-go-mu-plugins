<?php

/**
 * Deprecated Shortcode.
 *
 * @return    void
 * @access    private
 */
function taxonomy_images_plugin_shortcode_deprecated( $atts = array() ) { // DEPRECATED
	$o = '';
	$defaults = array(
		'taxonomy' => 'category',
		'size'     => 'detail',
		'template' => 'list'
		);

	extract( shortcode_atts( $defaults, $atts ) );

	/* No taxonomy defined return an html comment. */
	if ( ! taxonomy_exists( $taxonomy ) ) {
		$tax = strip_tags( trim( $taxonomy ) );
		return '<!-- taxonomy_image_plugin error: Taxonomy "' . $taxonomy . '" is not defined.-->';
	}

	$terms = get_terms( $taxonomy );
	$associations = taxonomy_image_plugin_get_associations( $refresh = false );
	
	if ( ! is_wp_error( $terms ) ) {
		foreach( (array) $terms as $term ) {
			$url         = get_term_link( $term, $term->taxonomy );
			$title       = apply_filters( 'the_title', $term->name );
			$title_attr  = esc_attr( $term->name . ' (' . $term->count . ')' );
			$description = apply_filters( 'the_content', $term->description );
			
			$img = '';
			if ( array_key_exists( $term->term_taxonomy_id, $associations ) ) {
				$img = wp_get_attachment_image( $associations[$term->term_taxonomy_id], 'detail', false );
			}
			
			if( $template === 'grid' ) {
				$o.= "\n\t" . '<div class="taxonomy_image_plugin-' . $template . '">';
				$o.= "\n\t\t" . '<a style="float:left;" title="' . $title_attr . '" href="' . $url . '">' . $img . '</a>';
				$o.= "\n\t" . '</div>';
			}
			else {
				$o.= "\n\t\t" . '<a title="' . $title_attr . '" href="' . $url . '">' . $img . '</a>';;
				$o.= "\n\t\t" . '<h2 style="clear:none;margin-top:0;padding-top:0;line-height:1em;"><a href="' . $url . '">' . $title . '</a></h2>';
				$o.= $description;
				$o.= "\n\t" . '<div style="clear:both;height:1.5em"></div>';
				$o.= "\n";
			}
		}
	}
	return $o;
}
add_shortcode( 'taxonomy_image_plugin', 'taxonomy_images_plugin_shortcode_deprecated' );


/**
 * This class has been left for backward compatibility with versions
 * of this plugin 0.5 and under. Please do not use any methods or
 * properties directly in your theme.
 *
 * @access     private        This class is deprecated. Do not use!!!
 */
class taxonomy_images_plugin {
	public $settings = array();
	public function __construct() {
		$this->settings = taxonomy_image_plugin_get_associations();
		add_action( 'taxonomy_image_plugin_print_image_html', array( &$this, 'print_image_html' ), 1, 3 );
	}
	public function get_thumb( $id ) {
		return taxonomy_image_plugin_get_image_src( $id );
	}
	public function print_image_html( $size = 'medium', $term_tax_id = false, $title = true, $align = 'none' ) {
		print $this->get_image_html( $size, $term_tax_id, $title, $align );
	}
	public function get_image_html( $size = 'medium', $term_tax_id = false, $title = true, $align = 'none' ) {
		$o = '';
		if ( false === $term_tax_id ) {
			global $wp_query;
			$obj = $wp_query->get_queried_object();
			if ( isset( $obj->term_taxonomy_id ) ) {
				$term_tax_id = $obj->term_taxonomy_id;
			}
			else {
				return false;
			}
		}
		$term_tax_id = (int) $term_tax_id;
		if ( isset( $this->settings[ $term_tax_id ] ) ) {
			$attachment_id = (int) $this->settings[ $term_tax_id ];
			$alt           = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$attachment    = get_post( $attachment_id );
			/* Just in case an attachment was deleted, but there is still a record for it in this plugins settings. */
			if ( $attachment !== NULL ) {
				$o = get_image_tag( $attachment_id, $alt, '', $align, $size );
			}
		}
		return $o;
	}
}
$taxonomy_images_plugin = new taxonomy_images_plugin();