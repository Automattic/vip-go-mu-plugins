<?php

// Adds 'correct' w & h values to img tags in core/image blocks
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
function vip_fix_img_block_sizes( $block_content, $block, $instance ) {

	// Don't fire in wp-admin, image blocks are fine there
	if ( is_admin() ) {
		return $block_content;
	}

	// If no HTML API, bail
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $block_content;
	}

	// Check img tag
	$img_tag_processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $img_tag_processor->next_tag( 'img' ) ) {
		// No img found, bail
		return $block_content;
	}

	// Get the image ID class from the img tag
	$img_class = $img_tag_processor->get_attribute( 'class' );
	if ( ! $img_class || ! preg_match( '/wp-image-(\d+)/', $img_class, $image_matches ) ) {
		// If we can't find wp-image-XXX, bail
		return $block_content;
	}
	$image_id = $image_matches[1];

	// Check figure tag
	$figure_tag_processor = new WP_HTML_Tag_Processor( $block_content );
	if ( ! $figure_tag_processor->next_tag( 'figure' ) ) {
		// No figure found, bail
		return $block_content;
	}

	// Get the size name class from the figure tag
	$figure_class = $figure_tag_processor->get_attribute( 'class' );
	if ( ! $figure_class || ! preg_match( '/size-([^\s"]+)/', $figure_class, $size_matches ) ) {
		// If we can't find size-xxx, bail
		return $block_content;
	}
	$size_name = $size_matches[1];

	// If the size is full, bail
	if ( 'full' === $size_name ) {
		return $block_content;
	}

	// Get meta for the attachment sizes
	$metadata = wp_get_attachment_metadata( $image_id );
	if ( ! $metadata || empty( $metadata['sizes'][ $size_name ] ) ) {
		// If no size meta is available for this size, bail
		return $block_content;
	}

	// Get values ready
	$width                = $metadata['sizes'][ $size_name ]['width'];
	$height               = $metadata['sizes'][ $size_name ]['height'];
	$update_tag_processor = new WP_HTML_Tag_Processor( $block_content );

	// Set width/height for img
	if ( $update_tag_processor->next_tag( 'img' ) ) {
		$update_tag_processor->set_attribute( 'width', esc_attr( $width ) );
		$update_tag_processor->set_attribute( 'height', esc_attr( $height ) );
	}

	// Get the result ready
	$block_content = $update_tag_processor->get_updated_html();

	// DONE
	return $block_content;
}
add_filter( 'render_block_core/image', 'vip_fix_img_block_sizes', 10, 3 );
