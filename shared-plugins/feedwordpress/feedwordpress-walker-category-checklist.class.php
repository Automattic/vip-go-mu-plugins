<?php
/**
 * FeedWordPress_Walker_Category_Checklist
 *
 * @version 2010.0531
 *
 * This is the fucking stupidest thing ever.
 */

// require_once(ABSPATH.'/wp-admin/includes/template.php');
// Fucking fuck.
// Unfuck by extending base Walker instead of Walker_Category_Checklist class by rinatkhaziev
class FeedWordPress_Walker_Category_Checklist extends Walker {
	var $tree_type = 'category';
	var $prefix = '';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function start_lvl(&$output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl(&$output, $depth, $args) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function end_el(&$output, $category, $depth, $args) {
		$output .= "</li>\n";
	}

	function set_prefix ($prefix) {
		$this->prefix = $prefix;
	}

	function start_el (&$output, $category, $depth, $args) {
		extract($args);
                if ( empty($taxonomy) ) :
                	$taxonomy = 'category';
                endif; 
                
                if ( $taxonomy == 'category' ) :
                	$name = 'post_category';
		else :
                	$name = 'tax_input['.$taxonomy.']';
                endif;
                
                $unit = array();
                if (strlen($this->prefix) > 0) :
                	$unit[] = $this->prefix;
		endif;
		$unit[] = $taxonomy;
		$unit[] = $category->term_id;
		$unitId = implode("-", $unit);

		$class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category category-checkbox"' : ' class="category-checkbox"';
		$output .= "\n<li id='{$unitId}'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="'.$name.'[]" id="in-'.$unitId. '"' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
	} /* FeedWordPress_Walker_Category_Checklist::start_el() */
} /* FeedWordPress_Walker_Category_Checklist */
