<?php
/**
 * Interface.
 *
 * All functions defined in this plugin should be considered
 * private meaning that they are not to be used in any other
 * WordPress extension including plugins and themes. Direct
 * use of functions defined herein constitutes unsupported use
 * and is strongly discouraged. This file contains custom filters
 * have been added which enable extension authors to interact with
 * this plugin in a responsible manner.
 *
 * @package      Taxonomy Images
 * @author       Michael Fields <michael@mfields.org>
 * @copyright    Copyright (c) 2011, Michael Fields
 * @license      http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since        0.7
 */


add_filter( 'taxonomy-images-get-terms',      'taxonomy_images_plugin_get_terms', 10, 2 );
add_filter( 'taxonomy-images-get-the-terms',  'taxonomy_images_plugin_get_the_terms', 10, 2 );
add_filter( 'taxonomy-images-list-the-terms', 'taxonomy_images_plugin_list_the_terms', 10, 2 );

add_filter( 'taxonomy-images-queried-term-image',        'taxonomy_images_plugin_get_queried_term_image', 10, 2 );
add_filter( 'taxonomy-images-queried-term-image-data',   'taxonomy_images_plugin_get_queried_term_image_data', 10, 2 );
add_filter( 'taxonomy-images-queried-term-image-id',     'taxonomy_images_plugin_get_queried_term_image_id' );
add_filter( 'taxonomy-images-queried-term-image-object', 'taxonomy_images_plugin_get_queried_term_image_object' );
add_filter( 'taxonomy-images-queried-term-image-url',    'taxonomy_images_plugin_get_queried_term_image_url', 10, 2 );


/**
 * Get Terms.
 *
 * This function adds a custom property (image_id) to each
 * object returned by WordPress core function get_terms().
 * This property will be set for all term objects. In cases
 * where a term has an associated image, "image_id" will
 * contain the value of the image object's ID property. If
 * no image has been associated, this property will contain
 * integer with the value of zero.
 *
 * Recognized Arguments:
 *
 * cache_images (bool) A non-empty value will trigger
 * this function to query for and cache all associated
 * images. An empty value disables caching. Defaults to
 * boolean true.
 *
 * having_images (bool) A non-empty value will trigger
 * this function to only return terms that have associated
 * images. If an empty value is passed all terms of the 
 * taxonomy will be returned.
 *
 * taxonomy (string) Name of a registered taxonomy to
 * return terms from. Defaults to "category".
 *
 * term_args (array) Arguments to pass as the second
 * parameter of get_terms(). Defaults to an empty array.
 *
 * @param     mixed     Default value for apply_filters() to return. Unused.
 * @param     array     Named arguments. Please see above for explantion.
 * @return    array     List of term objects.
 *
 * @access    private   Use the 'taxonomy-images-get-terms' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_get_terms( $default, $args = array() ) {
	$filter = 'taxonomy-images-get-terms';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$args = wp_parse_args( $args, array(
		'cache_images'  => true,
		'having_images' => true,
		'taxonomy'      => 'category',
		'term_args'     => array(),
		) );

	$args['taxonomy'] = explode( ',', $args['taxonomy'] );
	$args['taxonomy'] = array_map( 'trim', $args['taxonomy'] );

	foreach ( $args['taxonomy'] as $taxonomy ) {
		if ( ! taxonomy_image_plugin_check_taxonomy( $taxonomy, $filter ) ) {
			return array();
		}
	}

	$assoc = taxonomy_image_plugin_get_associations();
	if ( empty( $assoc ) ) {
		return array();
	}

	$terms = get_terms( $args['taxonomy'], $args['term_args'] );
	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$image_ids = array();
	$terms_with_images = array();
	foreach ( (array) $terms as $key => $term ) {
		$terms[$key]->image_id = 0;
		if ( array_key_exists( $term->term_taxonomy_id, $assoc ) ) {
			$terms[$key]->image_id = $assoc[$term->term_taxonomy_id];
			$image_ids[] = $assoc[$term->term_taxonomy_id];
			if ( ! empty( $args['having_images'] ) ) {
				$terms_with_images[] = $terms[$key];
			}
		}
	}
	$image_ids = array_unique( $image_ids );

	if ( ! empty( $args['cache_images'] ) ) {
		$images = array();
		if ( ! empty( $image_ids ) ) {
			$images = get_children( array( 'include' => implode( ',', $image_ids ) ) );
		}
	}

	if ( ! empty( $terms_with_images ) ) {
		return $terms_with_images;
	}
	return $terms;
}


/**
 * Get the Terms.
 *
 * This function adds a custom property (image_id) to each
 * object returned by WordPress core function get_the_terms().
 * This property will be set for all term objects. In cases
 * where a term has an associated image, "image_id" will
 * contain the value of the image object's ID property. If
 * no image has been associated, this property will contain
 * integer with the value of zero.
 *
 * Recognized Arguments:
 *
 * having_images (bool) A non-empty value will trigger
 * this function to only return terms that have associated
 * images. If an empty value is passed all terms of the
 * taxonomy will be returned. Optional.
 *
 * post_id (int) The post to retrieve terms from. Defaults
 * to the ID property of the global $post object. Optional.
 *
 * taxonomy (string) Name of a registered taxonomy to
 * return terms from. Defaults to "category". Optional.
 *
 * @param     mixed     Default value for apply_filters() to return. Unused.
 * @param     array     Named arguments. Please see above for explantion.
 * @return    array     List of term objects. Empty array if none were found.
 *
 * @access    private   Use the 'taxonomy-images-get-the-terms' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_get_the_terms( $default, $args ) {
	$filter = 'taxonomy-images-get-the-terms';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$args = wp_parse_args( $args, array(
		'having_images' => true,
		'post_id'       => 0,
		'taxonomy'      => 'category',
		) );

	if ( ! taxonomy_image_plugin_check_taxonomy( $args['taxonomy'], $filter ) ) {
		return array();
	}

	$assoc = taxonomy_image_plugin_get_associations();

	if ( empty( $args['post_id'] ) ) {
		$args['post_id'] = get_the_ID();
	}

	$terms = get_the_terms( $args['post_id'], $args['taxonomy'] );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	if ( empty( $terms ) ) {
		return array();
	}

	$terms_with_images = array();
	foreach ( (array) $terms as $key => $term ) {
		$terms[$key]->image_id = 0;
		if ( array_key_exists( $term->term_taxonomy_id, $assoc ) ) {
			$terms[$key]->image_id = $assoc[$term->term_taxonomy_id];
			if ( ! empty( $args['having_images'] ) ) {
				$terms_with_images[] = $terms[$key];
			}
		}
	}
	if ( ! empty( $terms_with_images ) ) {
		return $terms_with_images;
	}
	return $terms;
}


/**
 * List the Terms.
 *
 * Lists all terms associated with a given post that
 * have associated images. Terms without images will
 * not be included.
 *
 * Recognized Arguments:
 *
 * after (string) Text to append to the output. Optional.
 * Defaults to an empty string.
 *
 * before (string) Text to preppend to the output. Optional.
 * Defaults to an empty string.
 *
 * image_size (string) Any registered image size. Values will
 * vary from installation to installation. Image sizes defined
 * in core include: "thumbnail", "medium" and "large". "Fullsize"
 * may also be used to get the un modified image that was uploaded.
 * Optional. Defaults to "thumbnail".
 *
 * post_id (int) The post to retrieve terms from. Defaults
 * to the ID property of the global $post object. Optional.
 *
 * taxonomy (string) Name of a registered taxonomy to
 * return terms from. Defaults to "category". Optional.
 *
 * @param     mixed     Default value for apply_filters() to return. Unused.
 * @param     array     Named arguments. Please see above for explantion.
 * @return    string    HTML markup.
 *
 * @access    private   Use the 'taxonomy-images-list-the-terms' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_list_the_terms( $default, $args ) {
	$filter = 'taxonomy-images-list-the-terms';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$args = wp_parse_args( $args, array(
		'after'        => '</ul>',
		'after_image'  => '</li>',
		'before'       => '<ul class="taxonomy-images-the-terms">',
		'before_image' => '<li>',
		'image_size'   => 'thumbnail',
		'post_id'      => 0,
		'taxonomy'     => 'category',
		) );

	$args['having_images'] = true;

	if ( ! taxonomy_image_plugin_check_taxonomy( $args['taxonomy'], $filter ) ) {
		return '';
	}

	$terms = apply_filters( 'taxonomy-images-get-the-terms', '', $args );

	if ( empty( $terms ) ) {
		return '';
	}

	$output = '';
	foreach( $terms as $term ) {
		if ( ! isset( $term->image_id ) ) {
			continue;
		}
		$image = wp_get_attachment_image( $term->image_id, $args['image_size'] );
		if ( ! empty( $image ) ) {
			$output .= $args['before_image'] . '<a href="' . esc_url( get_term_link( $term, $term->taxonomy ) ) . '">' . $image .'</a>' . $args['after_image'];
		}
	}

	if ( ! empty( $output ) ) {
		return $args['before'] . $output . $args['after'];
	}
	return '';
}


/**
 * Queried Term Image.
 *
 * Prints html marking up the images associated with
 * the current queried term.
 *
 * Recognized Arguments:
 *
 * after (string) - Text to append to the image's HTML.
 *
 * before (string) - Text to prepend to the image's HTML.
 *
 * image_size (string) - May be any image size registered with
 * WordPress. If no image size is specified, 'thumbnail' will be
 * used as a default value. In the event that an unregistered size
 * is specified, this function will return an empty string.
 *
 * Designed to be used in archive templates including
 * (but not limited to) archive.php, category.php, tag.php,
 * taxonomy.php as well as derivatives of these templates.
 *
 * @param     mixed     Default value for apply_filters() to return. Unused.
 * @param     array     Named array of arguments.
 * @return    string    HTML markup for the associated image.
 *
 * @access    private   Use the 'taxonomy-images-queried-term-image' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_get_queried_term_image( $default, $args = array() ) {
	$filter = 'taxonomy-images-queried-term-image';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$args = wp_parse_args( $args, array(
		'after'      => '',
		'attr'       => array(),
		'before'     => '',
		'image_size' => 'thumbnail',
		) );

	$ID = apply_filters( 'taxonomy-images-queried-term-image-id', 0 );

	if ( empty( $ID ) ) {
		return '';
	}

	$html = wp_get_attachment_image( $ID, $args['image_size'], false, $args['attr'] );

	if ( empty( $html ) ) {
		return '';
	}

	return $args['before'] . $html . $args['after'];
}


/**
 * Queried Term Image ID.
 *
 * Designed to be used in archive templates including
 * (but not limited to) archive.php, category.php, tag.php,
 * taxonomy.php as well as derivatives of these templates.
 *
 * Returns an integer representing the image attachment's ID.
 * In the event that an image has been associated zero will
 * be returned.
 *
 * This function should never be called directly in any file
 * however it may be access in any template file via the
 * 'taxonomy-images-queried-term-image-id' filter.
 *
 * @param     mixed     Default value for apply_filters() to return. Unused.
 * @return    int       Image attachment's ID.
 *
 * @access    private   Use the 'taxonomy-images-queried-term-image-id' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_get_queried_term_image_id( $default ) {
	$filter = 'taxonomy-images-queried-term-image-id';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$obj = get_queried_object();

	/* Return early is we are not in a term archive. */
	if ( ! isset( $obj->term_taxonomy_id ) ) {
		trigger_error( sprintf( esc_html__( '%1$s is not a property of the current queried object. This usually happens when the %2$s filter is used in an unsupported template file. This filter has been designed to work in taxonomy archives which are traditionally served by one of the following template files: category.php, tag.php or taxonomy.php. Learn more about %3$s.', 'taxonomy-images' ),
			'<code>' . esc_html__( 'term_taxonomy_id', 'taxonomy-images' ) . '</code>',
			'<code>' . esc_html( $filter ) . '</code>',
			'<a href="http://codex.wordpress.org/Template_Hierarchy">' . esc_html( 'template hierarchy', 'taxonomy-images' ) . '</a>'
			) );
		return 0;
	}

	if ( ! taxonomy_image_plugin_check_taxonomy( $obj->taxonomy, $filter ) ) {
		return 0;
	}

	$associations = taxonomy_image_plugin_get_associations();
	$tt_id = absint( $obj->term_taxonomy_id );

	$ID = 0;
	if ( array_key_exists( $tt_id, $associations ) ) {
		$ID = absint( $associations[$tt_id] );
	}

	return $ID;
}


/**
 * Queried Term Image Object.
 *
 * Returns all data stored in the WordPress posts table for
 * the image associated with the term in object form. In the
 * event that no image is found an empty object will be returned.
 *
 * Designed to be used in archive templates including
 * (but not limited to) archive.php, category.php, tag.php,
 * taxonomy.php as well as derivatives of these templates.
 *
 * This function should never be called directly in any file
 * however it may be access in any template file via the
 * 'taxonomy-images-queried-term-image' filter.
 *
 * @param     mixed          Default value for apply_filters() to return. Unused.
 * @return    stdClass       WordPress Post object.
 *
 * @access    private        Use the 'taxonomy-images-queried-term-image-object' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_get_queried_term_image_object( $default ) {
	$filter = 'taxonomy-images-queried-term-image-object';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$ID = apply_filters( 'taxonomy-images-queried-term-image-id', 0 );

	$image = new stdClass;
	if ( ! empty( $ID ) ) {
		$image = get_post( $ID );
	}
	return $image;
}

/**
 * Queried Term Image URL.
 *
 * Returns a url to the image associated with the current queried
 * term. In the event that no image is found an empty string will
 * be returned.
 *
 * Designed to be used in archive templates including
 * (but not limited to) archive.php, category.php, tag.php,
 * taxonomy.php as well as derivatives of these templates.
 *
 * Recognized Arguments
 *
 * image_size (string) - May be any image size registered with
 * WordPress. If no image size is specified, 'thumbnail' will be
 * used as a default value. In the event that an unregistered size
 * is specified, this function will return an empty string.
 *
 * @param     mixed          Default value for apply_filters() to return. Unused.
 * @param     array          Named Arguments.
 * @return    string         Image URL.
 *
 * @access    private        Use the 'taxonomy-images-queried-term-image-url' filter.
 * @since     0.7
 */
function taxonomy_images_plugin_get_queried_term_image_url( $default, $args = array() ) {
	$filter = 'taxonomy-images-queried-term-image-url';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$args = wp_parse_args( $args, array(
		'image_size' => 'thumbnail',
		) );

	$data = apply_filters( 'taxonomy-images-queried-term-image-data', array(), $args );

	$url = '';
	if ( isset( $data['url'] ) ) {
		$url = $data['url'];
	}
	return $url;
}


/**
 * Queried Term Image Data.
 *
 * Returns a url to the image associated with the current queried
 * term. In the event that no image is found an empty string will
 * be returned.
 *
 * Designed to be used in archive templates including
 * (but not limited to) archive.php, category.php, tag.php,
 * taxonomy.php as well as derivatives of these templates.
 *
 * Recognized Arguments
 *
 * image_size (string) - May be any image size registered with
 * WordPress. If no image size is specified, 'thumbnail' will be
 * used as a default value. In the event that an unregistered size
 * is specified, this function will return an empty array.
 *
 * @param     mixed          Default value for apply_filters() to return. Unused.
 * @param     array          Named Arguments.
 * @return    array          Image data: url, width and height.
 *
 * @access    private        Use the 'taxonomy-images-queried-term-image-data' filter.
 * @since     0.7
 * @alter     0.7.2
 */
function taxonomy_images_plugin_get_queried_term_image_data( $default, $args = array() ) {
	$filter = 'taxonomy-images-queried-term-image-data';
	if ( $filter !== current_filter() ) {
		taxonomy_image_plugin_please_use_filter( __FUNCTION__, $filter );
	}

	$args = wp_parse_args( $args, array(
		'image_size' => 'thumbnail',
		) );

	$ID = apply_filters( 'taxonomy-images-queried-term-image-id', 0 );

	if ( empty( $ID ) ) {
		return array();
	}

	$data = array();

	if ( in_array( $args['image_size'], array( 'full', 'fullsize' ) ) ) {
		$src = wp_get_attachment_image_src( $ID, 'full' );

		if ( isset( $src[0] ) ) {
			$data['url'] = $src[0];
		}
		if ( isset( $src[1] ) ) {
			$data['width'] = $src[1];
		}
		if ( isset( $src[2] ) ) {
			$data['height'] = $src[2];
		}
	}
	else {
		$data = image_get_intermediate_size( $ID, $args['image_size'] );
	}

	if ( ! empty( $data ) ) {
		return $data;
	}

	return array();
}