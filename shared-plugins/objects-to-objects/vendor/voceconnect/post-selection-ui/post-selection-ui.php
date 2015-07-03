<?php
/*
Plugin Name: Post Selection UI
Description: An extraction of the post selection interface from the posts-to-posts plugin
Version: 1.0.11
Author: prettyboymp, banderon, matstars
Plugin URI: http://voceconnect.com

*/
if( !class_exists('Post_Selection_UI') ){

class Post_Selection_UI {

	public static function init() {
		add_action('wp_ajax_psu_box', array(__CLASS__, 'handle_ajax_search'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));
	}

	public static function admin_enqueue_scripts() {
		wp_enqueue_style('post-selection-ui', self::local_url( 'post-selection-ui.css', __FILE__ ), array() );
		wp_enqueue_script( 'post-selection-ui', self::local_url( 'post-selection-ui.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-sortable' ), null, true );

		wp_localize_script( 'post-selection-ui', 'PostSelectionUI', array(
			'nonce' => wp_create_nonce( 'psu_search' ),
			'spinner' => admin_url( 'images/wpspin_light.gif' ),
			'clearConfirmMessage' => __( 'Are you sure you want to clear the selected items?' ),
		) );

	}

	public static function local_url($relative_path, $plugin_path) {
		$template_dir = get_template_directory();

		foreach ( array( 'template_dir', 'plugin_path' ) as $var ) {
			$$var = str_replace( '\\', '/', $$var ); // sanitize for Win32 installs
			$$var = preg_replace( '|/+|', '/', $$var );
		}
		if ( 0 === strpos( $plugin_path, $template_dir ) ) {
			$url = get_template_directory_uri();
			$folder = str_replace( $template_dir, '', dirname( $plugin_path ) );
			if ( '.' != $folder ) {
				$url .= '/' . ltrim( $folder, '/' );
			}
			if ( !empty( $relative_path ) && is_string( $relative_path ) && strpos( $relative_path, '..' ) === false ) {
				$url .= '/' . ltrim( $relative_path, '/' );
			}
			return $url;
		} else {
			return plugins_url( $relative_path, $plugin_path );
		}
	}

	public static function handle_ajax_search() {
		check_ajax_referer('psu_search');

		$args = array(
			'post_type' => array()
		);

		if (!empty($_GET['post_type']) ) {
			$unsanitized_post_types = array_map('sanitize_key', explode(',', $_GET['post_type']));
			foreach($unsanitized_post_types as $post_type) {
			 if(($post_type_obj = get_post_type_object( $post_type )) && current_user_can($post_type_obj->cap->read)) {
				 $args['post_type'][] = $post_type;
			 }
			}
		}

		if (count($args['post_type']) < 1) {
			die('-1');
		}
		if (!empty($_GET['paged'])) {
			$args['paged'] = absint($_GET['paged']);
		}
		if (!empty($_GET['s'])){
			$args['s'] = sanitize_text_field( $_GET['s'] );
		}
		if ( ! empty( $_GET['order'] ) && ( in_array( strtolower( $_GET['order'] ), array( 'asc', 'desc' ) ) ) ) {
			$args['order'] = $_GET['order'];
		}
		if (!empty($_GET['orderby'])) {
			$args['orderby'] = sanitize_text_field( $_GET['orderby'] );
		}
		if ( !empty($_GET['post_status']) && ( in_array($_GET['post_status'], array_keys(get_post_statuses())) || $_GET['post_status'] === 'inherit' || $_GET['post_status'] == 'any' ) ) {
			$args['post_status'] = $_GET['post_status'];
		}
		if ( !empty($_GET['include']) ) {
			$args['post__in'] = array_filter(array_map('intval',explode(',', $_GET['include'])));
		}

		if (!empty($_GET['exclude'])) {
			$selected = array_map('intval', explode(',', $_GET['exclude']));
		} else {
			$selected = array();
		}

		$name = 'foobar';
		if ( !empty($_GET['name']) ) {
			$_name = sanitize_text_field($_GET['name']);
			if ($_name) {
				$name = $_name;
			}
		}

		$psu_box = new Post_Selection_Box($name, array('post_type' => $args['post_type'], 'selected' => $selected));

		$response = new stdClass();
		$response->rows = $psu_box->render_results($args);
		die(json_encode($response));
	}

}
add_action('init', array('Post_Selection_UI', 'init'));

function post_selection_ui($name, $args) {
	$select_box = new Post_Selection_Box($name, $args);
	return $select_box->render();
}


class Post_Selection_Box {
	private $name;
	private $args;

	public function __construct($name, $args = array() ) {
		$defaults = array(
			'post_type' => array('post'),
			'post_status' => array('publish'),
			'limit' => 0,
			'selected' => array(),
			'id' => $name,
			'labels' => array(),
			'sortable' => true,
			'orderby' => 'date',
			'order' => 'DESC',
			'tax_query' => array(),
			'infinite_scroll' => true,
			'post__in' => array()
		);
		$args = wp_parse_args($args, $defaults);
		$args['selected'] = array_filter(array_map('intval', $args['selected']));
		$args['post__in'] = array_filter(array_map('intval', $args['post__in']));

		$args['post_type'] = (array) $args['post_type'];
		$args['post_status'] = (array) $args['post_status'];

		if (count($args['post_type']) > 1) {
			$default_labels = array(
				'name' => 'Items',
				'singular_name' => 'Item',
			);
		} else {
			$post_type = get_post_type_object($args['post_type'][0]);
			$default_labels = (array) $post_type->labels;
		}
		$args['labels'] = wp_parse_args($args['labels'], $default_labels);

		$this->args = $args;

		$this->name = $name;
	}

	private function get_addable_query($args) {
		$defaults = array(
			'post_type' => $this->args['post_type'],
			'post_status' => $this->args['post_status'],
			'posts_per_page' => 10,
			'post__not_in' => $this->args['selected'],
			'paged' => 1,
			'orderby' => $this->args['orderby'],
			'order' => $this->args['order']
		);

		if ( !empty( $this->args['tax_query'] ) ) {
			$defaults['tax_query'] = $this->args['tax_query'];
		}

		if ( !empty( $this->args['post__in'] ) ) {
			$defaults['post__in'] = $this->args['post__in'];
		}

		$query_args = wp_parse_args($args, $defaults);
		return new WP_Query($query_args);
	}

	/**
	 * Renders the add_rows for the selection box
	 * @param WP_Query $wp_query
	 * @return string
	 */
	public function render_addable_rows($wp_query) {
		$output = '';
		foreach($wp_query->posts as $post) {
			if (!get_post($post->ID)) {
				continue;
			}

			$title = esc_html(get_the_title($post->ID));

			$row_actions = '';
			$post_type_object = get_post_type_object( get_post_type($post->ID));
			$can_edit = current_user_can( $post_type_object->cap->edit_post, $post->ID );

			if ( $can_edit ) {
				$row_actions .= sprintf('<span class="edit"><a title="Edit this item" href="%s">Edit</a> | </span>', get_edit_post_link( $post->ID ));
			}

			if ( $post_type_object->publicly_queryable ) {
				if ( ($can_edit || !in_array( $post->post_status, array( 'pending', 'draft', 'future' ) ) )
					&& ( $post->post_status != 'trash') ) {
					$row_actions .= sprintf('<span class="view"><a rel="permalink" title="View %s" href="%s">View</a></span>', esc_attr(get_the_title($post->ID)), esc_url(get_permalink($post->ID)));
				}
			}

			if ($row_actions) {
				$title .= '<div class="psu-row-actions">'.$row_actions.'</div>';
			}

			$title = apply_filters('post-selection-ui-row-title', $title, $post->ID, $this->name, $this->args);
			$output .= "<tr data-post_id='{$post->ID}' data-title='". esc_attr(get_the_title($post->ID)) ."' data-permalink='".  get_permalink($post->ID) . "'>\n".
				"\t<td class='psu-col-create'><a href='#' title='Add'></a></td>".
				"\t<td class='psu-col-title'>\n";
			$output .= $title;
			$output .= "\n\t</td>\n</tr>\n";
		}
		return $output;
	}

	/**
	 * Renders the s_rows for the selection box
	 * @param array $post_ids
	 * @return string
	 *
	 * @todo look into pre-caching the posts all at once
	 */
	private function render_selected_rows($post_ids) {
		$output = '';
		foreach($post_ids as $post_id) {
			if (!$post_id || !get_post($post_id)) {
				continue;
			}

			$title = esc_html( get_the_title( $post_id ) );

			$row_actions = '';
			$can_edit = current_user_can( get_post_type_object( get_post_type($post_id) )->cap->edit_post, $post_id );
			$post_type_object = get_post_type_object( get_post_type($post_id));

			if ( $can_edit ) {
				$row_actions .= sprintf('<span class="edit"><a title="Edit this item" href="%s">Edit</a></span>', get_edit_post_link( $post_id ));
			}

			if ( $post_type_object->publicly_queryable ) {
				if ( ($can_edit || !in_array( get_post($post_id)->post_status, array( 'pending', 'draft', 'future' ) ) )
					&& ( get_post($post_id)->post_status != 'trash') ) {
					$row_actions .= sprintf('| <span class="view"><a rel="permalink" title="View %s" href="%s">View</a></span>', esc_attr(get_the_title($post_id)), esc_url(get_permalink($post_id)));
				}
			}

			if ($row_actions) {
				$title .= '<div class="psu-row-actions">'.$row_actions.'</div>';
			}

			$title = apply_filters('post-selection-ui-row-title', $title, $post_id, $this->name, $this->args);

			$output .= "<tr data-post_id='{$post_id}'>\n".
				"\t<td class='psu-col-delete'><a href='#' title='Remove'></a></td>".
				"\t<td class='psu-col-title'>\n";
			$output .= wp_kses_post( $title );
			$output .= "\n</td>\n";
			if($this->args['sortable']) {
				$output .= "\t<td class='psu-col-order'>&nbsp;</td>";
			}
			$output .= "</tr>\n";
		}
		return $output;
	}

	public function render_results($args) {
		$wp_query = $this->get_addable_query($args);
		$cpage = intval($wp_query->get('paged'));
		$max_pages = intval($wp_query->max_num_pages);

		$output = "<table class='psu-results'>\n".
			"\t<tbody>" . $this->render_addable_rows($wp_query) . "</tbody>\n".
			"</table>".
			"<div class='psu-navigation'>\n".
			"\t<div class='psu-prev button inactive' title='previous'>&lsaquo;</div>".
			"\t<div>\n".
			"\t\t<span class='psu-current' data-num='".$cpage."'>".$cpage."</span>\n".
			"\t\tof\n".
			"\t\t<span class='psu-total' data-num='".$max_pages."'>".$max_pages."</span>\n".
			"\t</div>\n".
			"\t<div class='psu-next button ' title='next'>&rsaquo;</div>\n".
			"</div>\n";
		return $output;
	}

	public function render() {
		ob_start();
		$data = array(
			'post-in'         => implode(',',$this->args['post__in']),
			'infinite-scroll' => (bool) $this->args['infinite_scroll'],
			'post_type'       => implode(',', $this->args['post_type']),
			'post_status'     => implode(',',$this->args['post_status']),
			'cardinality'     => $this->args['limit'],
			'order'           => $this->args['order'],
			'orderby'         => $this->args['orderby'],
		);
		$data_atts = array();
		foreach ( $data as $key => $value ) {
			$data_atts[] = sprintf( 'data-%s="%s"', $key, esc_attr($value) );
		}
		?>
		<div id="<?php echo esc_attr($this->args['id'] )?>" class="psu-box" <?php echo implode(' ', $data_atts); ?>>
			<input type="hidden" name="<?php echo esc_attr($this->name); ?>" value="<?php echo join(',', $this->args['selected']) ?>" />
			<table class="psu-selected" >
				<?php if($this->args['limit'] != 1): ?>
				<thead>
					<tr>
						<th class="psu-col-delete"><a href="#" title="<?php printf(__("Remove all %s"), $this->args['labels']['name']) ?>"></a></th>
						<th class="psu-col-title"><?php echo esc_html($this->args['labels']['singular_name']); ?></th>
						<?php if($this->args['sortable']) : ?>
							<th class="psu-col-order"><?php _e('Sort'); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<?php endif; ?>
				<tbody class="<?php echo $this->args['sortable'] ? 'sortable' : ''; ?>">
					<?php echo $this->render_selected_rows($this->args['selected']); ?>
				</tbody>
			</table>

			<div class="psu-add-posts" >
				<p><strong><?php printf(__('Add %s'), esc_html($this->args['labels']['singular_name'])); ?>:</strong></p>

				<ul class="wp-tab-bar clearfix">
					<?php
					$html  = '<li class="wp-tab-active" data-ref=".psu-tab-list"><a href="#">View All</a></li>';
					$html .= '<li data-ref=".psu-tab-search"><a href="#">Search</a></li>';
					$html = apply_filters('psu_tab_list', $html, $post_type = $this->args['post_type'][0]);
					echo $html;
					?>
				</ul>

				<div class="psu-tab-search tabs-panel">
					<div class="psu-search">
						<input type="text" name="p2p_search" autocomplete="off" placeholder="Search Posts" />
					</div>
				</div>

				<div class="psu-tab-list tabs-panel" <?php echo ($this->args['infinite_scroll']) ? ' style="max-height: 270px; overflow: scroll"' : '' ; ?>>
					<?php echo $this->render_results(array()); ?>

				</div>
				<?php do_action('psu_tab_div_end', $post_type = $this->args['post_type'][0], $this); ?>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}

}