<?php

// Adds 'correct' w & h values to img tags in core/image blocks

function vip_fix_img_block_sizes($block_content, $block, $instance)
{
    // Don't fire in wp-admin, image blocks are fine there
    if (!is_admin()) {

        // Extract the image ID from the wp-image-* class within the img tag
        if (preg_match('/<img[^>]* class="[^"]*wp-image-(\d+)[^"]*"/', $block_content, $matches)) {
            $image_id = $matches[1];
        } else {
            return $block_content; // No image ID found, return original content
        }

        // Extract the size class from the figure tag's class attribute
        if (preg_match('/<figure[^>]* class="[^"]*size-([^\s"]+)[^"]*"/', $block_content, $size_matches)) {
            $size_name = $size_matches[1];
        } else {
            return $block_content; // No size found, return original content
        }

        // Get the image metadata
        if ($size_name == "full") {
            return $block_content; // fullsize is ok as-is, return original content
        } else {
            $metadata = wp_get_attachment_metadata($image_id);
            if (!$metadata || !isset($metadata['sizes'][$size_name])) {
                return $block_content; // No metadata found for this size, return original content
            }
        }

        // Get the width, height, and file from the metadata
        $width = $metadata['sizes'][$size_name]['width'];
        $height = $metadata['sizes'][$size_name]['height'];

        // Add the width and height attributes to the img tag
        $block_content = preg_replace(
            '/<img ([^>]+)>/',
            '<img $1 width="' . esc_attr($width) . '" height="' . esc_attr($height) . '">',
            $block_content
        );
    }
    return $block_content;
}
add_filter('render_block_core/image', 'vip_fix_img_block_sizes', 10, 3);
