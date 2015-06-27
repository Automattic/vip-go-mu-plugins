<?php

/**********************************************
	Filter inline blocks of raw HTML
***********************************************/
global $ptwsh_raw_parts;
$ptwsh_raw_parts = array ();

/**
 * Extract content surrounded by [raw] or other supported tags
 * and replace it with placeholder text.
 *
 * @global array $ptwsh_raw_parts Used to store the extracted content blocks.
 *
 * @param string  $text      The input content to filter.
 * @param bool    $keep_tags Store both the tagged content and the tags themselves. Defaults to false - storing only the content.
 * @return string Filtered content.
 */
function ptwsh_extract_exclusions( $text, $keep_tags = false ) {
	global $ptwsh_raw_parts, $wp_current_filter;
	// Note to self: The regexp version was much shorter, but it had problems
	// with big posts.

	$tags = array( array( '[ptraw]', '[/ptraw]' ) );

	foreach ( $tags as $tag_pair ) {
		list ( $start_tag, $end_tag ) = $tag_pair;

		// Find the start tag
		$start = stripos( $text, $start_tag, 0 );
		while ( $start !== false ) {
			$content_start = $start + strlen( $start_tag );

			// find the end tag
			$fin = stripos( $text, $end_tag, $content_start );

			// break if there's no end tag
			if ( $fin == false )
				break;

			// extract the content between the tags
			$content = substr( $text, $content_start, $fin - $content_start );

			if ( ( array_search( 'get_the_excerpt', $wp_current_filter ) !== false ) || ( array_search( 'the_excerpt', $wp_current_filter ) !== false ) ) {
				// Strip out the raw blocks when displaying an excerpt
				$replacement = '';
			} else {
				// Store the content and replace it with a marker
				if ( $keep_tags ) {
					$ptwsh_raw_parts[] = $start_tag . $content . $end_tag;
				} else {
					$ptwsh_raw_parts[] = $content;
				}
				$replacement = "!PTRAWBLOCK" . ( count( $ptwsh_raw_parts ) - 1 ) . "!";
			}
			$text = substr_replace( $text, $replacement, $start, $fin + strlen( $end_tag ) - $start );

			// Have we reached the end of the string yet?
			if ( $start + strlen( $replacement ) > strlen( $text ) )
				break;

			// Find the next start tag
			$start = stripos( $text, $start_tag, $start + strlen( $replacement ) );
		}
	}
	return $text;
}

/**
 * Replace the placeholders created by ptwsh_extract_exclusions() with the original content.
 *
 * @global array $ptwsh_raw_parts Used to check if there is anything to insert.
 *
 * @param unknown $text                 string The input content to filter.
 * @param unknown $placeholder_callback callable|string Optional. The callback that will be used to process each placeholder.
 * @return string Filtered content.
 */
function ptwsh_insert_exclusions( $text, $placeholder_callback = 'ptwsh_insertion_callback' ) {
	global $ptwsh_raw_parts;
	if ( ! isset( $ptwsh_raw_parts ) )
		return $text;
	return preg_replace_callback( "/(<p>)?!PTRAWBLOCK(?P<index>\d+?)!(\s*?<\/p>)?/", $placeholder_callback, $text );
}

/**
 * Regex callback for ptwsh_insert_exclusions.
 * Returns the extracted content
 * corresponding to a matched placeholder.
 *
 * @global $ptwsh_raw_parts
 *
 * @param unknown $matches array Regex matches.
 * @return string Replacement string for this match.
 */
function ptwsh_insertion_callback( $matches ) {
	global $ptwsh_raw_parts;

	$openingParagraph = isset( $matches [1] ) ? $matches[1] : '';
	$closingParagraph = isset( $matches [3] ) ? $matches[3] : '';
	$code = $ptwsh_raw_parts [intval( $matches ['index'] )];

	// If the [raw] block is wrapped in its own paragraph, strip the <p>...</p>
	// tags. If there's
	// only one of <p>|</p> tag present, keep it - it's probably part of a
	// larger paragraph.
	if ( empty( $openingParagraph ) || empty( $closingParagraph ) ) {
		$code = $openingParagraph . $code . $closingParagraph;
	}
	return htmlspecialchars_decode( $code );
}

function ptwsh_setup_content_filters() {
	// Extract the tagged content before WP can get to it, then re-insert it
	// later.
	add_filter( 'the_content', 'ptwsh_extract_exclusions', 2 );

	// A workaround for WP-Syntax. If we run our insertion callback at the
	// normal, extra-late
	// priority, WP-Syntax will see the wrong content when it runs its own
	// content substitution hook.
	// We adapt to that by running our callback slightly earlier than
	// WP-Syntax's.
	$wp_syntax_priority = has_filter( 'the_content', 'wp_syntax_after_filter' );
	if ( $wp_syntax_priority !== false ) {
		$rawhtml_priority = $wp_syntax_priority - 1;
	} else {
		$rawhtml_priority = 1001;
	}
	add_filter( 'the_content', 'ptwsh_insert_exclusions', $rawhtml_priority );
}
add_action( 'after_setup_theme', 'ptwsh_setup_content_filters' );

/*
 * WordPress can also mangle code when initializing the post/page editor. To
 * prevent this, we override the the_editor_content filter in almost the same
 * way that we did the_content.
 */

function ptwsh_extract_exclusions_for_editor( $text ) {
	return ptwsh_extract_exclusions( $text, true );
}

function ptwsh_insert_exclusions_for_editor( $text ) {
	return ptwsh_insert_exclusions( $text, 'ptwsh_insertion_callback_for_editor' );
}

function ptwsh_insertion_callback_for_editor( $matches ) {
	global $ptwsh_raw_parts;
	return htmlspecialchars( $ptwsh_raw_parts [intval( $matches ['index'] )], ENT_NOQUOTES );
}

add_filter( 'the_editor_content', 'ptwsh_extract_exclusions_for_editor', 2 );
add_filter( 'the_editor_content', 'ptwsh_insert_exclusions_for_editor', 1001 );
