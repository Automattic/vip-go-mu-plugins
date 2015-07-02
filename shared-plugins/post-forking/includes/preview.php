<?php
/**
 * Select the proper template for previewing a fork
 *
 * This class is responsible for looking up the fork's parent and loading the
 * appropriate template.
 *
 * @package fork
 */

class Fork_Preview {

	/**
	 * Hook into WP "template_redirect" hook.
	 *
	 */
	function __construct( &$parent ) {
		$this->parent = &$parent;

		add_action( 'template_redirect', array( &$this, 'choose_template' ) );
	}

	/**
	 * Get the fork's parent post, set up a query, and load correct template.
	 *
	 * Duplicates the functionality of /wp-includes/template-loader.php and includes
	 * a lot of copypasta, but that's only to ensure that it follows the same logic.
	 *
	 */
	function choose_template() {

		$p = get_queried_object_id();
		if ( get_post_type( $p ) !== 'fork' ) return;

		$pp = get_post( $p )->post_parent;
		$parent = get_post( $pp );

		if ( $parent->post_type == 'page' )
			$query = array( 'page_id' => $pp );
		else
			$query = array( 'p' => $pp );

		$t = new WP_Query( $query );

		$template = false;
		if     ( $t->is_404()            && $template = get_404_template()            ) :
		elseif ( $t->is_search()         && $template = get_search_template()         ) :
		elseif ( $t->is_tax()            && $template = get_taxonomy_template()       ) :
		elseif ( $t->is_front_page()     && $template = get_front_page_template()     ) :
		elseif ( $t->is_home()           && $template = get_home_template()           ) :
		elseif ( $t->is_attachment()     && $template = get_attachment_template()     ) :
			remove_filter('the_content', 'prepend_attachment');
		elseif ( $t->is_single()         && $template = get_single_template()         ) :
		elseif ( $t->is_page			 && $template = get_page_template()           ) :
		elseif ( $t->is_category()       && $template = get_category_template()       ) :
		elseif ( $t->is_tag()            && $template = get_tag_template()            ) :
		elseif ( $t->is_author()         && $template = get_author_template()         ) :
		elseif ( $t->is_date()           && $template = get_date_template()           ) :
		elseif ( $t->is_archive()        && $template = get_archive_template()        ) :
		elseif ( $t->is_comments_popup() && $template = get_comments_popup_template() ) :
		elseif ( $t->is_paged()          && $template = get_paged_template()          ) :
		else :
			$template = get_index_template();
		endif;

		if ( $template = apply_filters( 'template_include', $template ) )
			include( $template );
		return;
	}

}
