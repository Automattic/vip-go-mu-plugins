<?php
/**
 * Plugin Name: Table of Contents
 * Description: Adds a table of contents to your pages based on h3 and h4 tags. Useful for documention-centric sites.
 * Author: Automattic
 *
 * License: GPL v2
 */

if ( ! class_exists( 'Table_Of_Contents' ) ):

class Table_Of_Contents {
	function init() {
		add_action( 'template_redirect', array( __CLASS__, 'load_filters' ) );
	}

	function load_filters() {
		if ( is_singular() ) {
			add_filter( 'the_content', array( __CLASS__, 'add_overview_h3' ) );
			add_filter( 'the_content', array( __CLASS__, 'add_toc' ) );
		}
	}

	function add_toc( $content ) {
		// only affect the main post and not post in custom queries
		if ( get_the_ID() != get_queried_object_id() || ! in_array( get_post_type(), apply_filters( 'toc_post_types', array( 'page' ) ) ) )
			return $content;

		$toc = '';
		$tags_and_subtags = self::get_tags_and_subtags( 'h3', 'h4', $content );

		$content = self::add_ids_and_jumpto_links( 'h3', $content );
		$content = self::add_ids_and_jumpto_links( 'h4', $content );

		if ( ! empty( $tags_and_subtags ) ) {
			$toc .= '<div class="vip-lobby-toc">';
			$toc .= '<h3>Contents</h3><ul class="items">';
			foreach ( $tags_and_subtags as $tag => $subtags ) {
				$toc .= '<li><a href="#' . sanitize_title_with_dashes( $tag )  . '">' . $tag  . '</a>';
				if ( ! empty( $subtags ) ) {
					$toc .= '<ul>';
					foreach ( $subtags as $subtag ) {
						$toc .= '<li><a href="#' . sanitize_title_with_dashes( $subtag )  . '">' . $subtag  . '</a>'; 
					}
					$toc .= '</ul>';
				}
				$toc .= '</li>';

			}
			$toc .= '</ul>';
			$toc .= '</div>';
		}

		return $toc . $content;
	}

	function add_overview_h3( $content ) {
		// only affect the main post and not post in custom queries
		if ( get_the_ID() != get_queried_object_id() || ! in_array( get_post_type(), apply_filters( 'toc_post_types', array( 'page' ) ) ) )
			return $content;

		$h3s = self::get_tags_matches( 'h3', $content );
		if ( ! empty( $h3s ) )
			$content = "<h3>Overview</h3>\n" . $content;
		return $content;
	}

	function add_ids_and_jumpto_links( $tag, $content ) {
		$items = self::get_tags_matches( $tag, $content );
		$first = true;
		$matches = $replacements = array();

		foreach ($items as $item) {
			$replacement = '';
			$matches[] = $item[0];
			$id = sanitize_title_with_dashes($item[2]);

			if ( ! $first ) {
				$replacement .= '<p class="toc-jump"><a href="#content">&uarr; Top &uarr;</a></p>';
			} else {
				$first = false;
			}

			$replacement .= sprintf( '<%1$s id="%2$s">%3$s <a href="#%2$s" class="anchor">#</a></%1$s>', $tag, $id, $item[2] );
			$replacements[] = $replacement;
		}

		$content = str_replace( $matches, $replacements, $content );

		return $content;
	}

	private function _get_tag_pattern( $tag ) {
		return "/(<{$tag}>)(.*)(<\/{$tag}>)/";
	}

	function get_tags_matches( $tag, $content = '' ) {
		if ( empty( $content ) )
			$content = get_the_content();
		preg_match_all( self::_get_tag_pattern( $tag ), $content, $matches, PREG_SET_ORDER );
		return $matches;
	}

	function get_tags( $tag, $content ) {
		$tags = array();
		$matches = self::get_tags_matches( $tag, $content );
		foreach ( $matches as $match ) {
			$tags[] = $match[2];
		}
		return $tags;
	}

	function get_tags_and_subtags( $tag, $subtag, $content ) {
		$tags_and_subtags = $headers = array();

		if ( empty( $content ) )
			$content = get_the_content();

		$pattern = self::_get_tag_pattern( $tag );

		$has_headers = preg_match_all( $pattern, $content, $header_matches, PREG_SET_ORDER );
		if ( ! $has_headers )
			return;

		foreach ( $header_matches as $header_match ) {
			$headers[] = $header_match[2];
		}

		$section_index = 0;
		$sections = preg_split( $pattern, $content );
		array_shift( $sections );

		foreach ( $sections as $section ) {

			$header = $headers[ $section_index ];
			$subheaders = self::get_tags( $subtag, $section );

			if ( ! empty( $header ) )
				$tags_and_subtags[ $header ] = $subheaders;

			$section_index++;
		}

		return $tags_and_subtags;
	}
}

Table_Of_Contents::init();

endif;
