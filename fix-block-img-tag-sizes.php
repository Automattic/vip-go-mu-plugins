<?php

// Adds 'correct' w & h values to img tags in core/image blocks

function fix_img_block_sizes($block_content, $block, $instance)
{

    // Use resize args for images instead of w|h|crop (optional)
    $resizearg = false;

    // Don't fire in wp-admin
    if (!is_admin()) {

        if ('core/image' !== $block['blockName']) {
            return $block_content; // Only modify core/image blocks
        }

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
        $metadata = wp_get_attachment_metadata($image_id);
        if (!$metadata || !isset($metadata['sizes'][$size_name])) {
            return $block_content; // No metadata found for this size, return original content
        }

        // Get the width, height, and file from the metadata
        $width = $metadata['sizes'][$size_name]['width'];
        $height = $metadata['sizes'][$size_name]['height'];
        $new_file = $metadata['sizes'][$size_name]['file'];

        if ($resizearg) {
            // Get the original image URL and replace the file name with the new file
            if (preg_match('/<img [^>]*src="([^"]+)"[^>]*>/', $block_content, $url_matches)) {
                $original_url = $url_matches[1];
                $parsed_url = wp_parse_url($original_url);
                $base_url = trailingslashit($parsed_url['scheme'] . '://' . $parsed_url['host'] . dirname($parsed_url['path']));

                // Construct new URL with the new file
                $new_url = $base_url . $new_file;

                // Replace the original URL with the new URL in the img tag
                $block_content = str_replace($original_url, $new_url, $block_content);
            }
        }

        // Add the width and height attributes to the img tag
        $block_content = preg_replace(
            '/<img ([^>]+)>/',
            '<img $1 width="' . esc_attr($width) . '" height="' . esc_attr($height) . '">',
            $block_content
        );
    }
    return $block_content;
}
add_filter('render_block', 'fix_img_block_sizes', 10, 3);
