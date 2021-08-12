<?php

namespace Automattic\VIP\Search\UI;

/**
* Class VIP_Search_Widget
 * @package Automattic\VIP\Search\UI
 *
 * Derived from vip-search_Search_Widget
 */
class VIP_Search_Widget extends \WP_Widget {

    /**
	 * Number of aggregations (filters) to show by default.
	 *
	 * @var int
	 */
	const DEFAULT_FILTER_COUNT = 5;

	/**
	 * Default sort order for search results.
	 *
	 * @var string
	 */
	const DEFAULT_SORT = 'relevance_desc';

	public function __construct( $name = null ) {
		if ( empty( $name ) ) {
			$name = 'Enterprise Search';
		}

		parent::__construct(
			'vip-search-widget',
			$name,
			array( 'description' => 'Enterprise Search' )
		);

		if ( is_admin() ) {
			add_action( 'sidebar_admin_setup', array( $this, 'widget_admin_setup' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		}
	}

	/**
	 * Enqueues the scripts and styles needed for the customizer.
	 *
	 */
	public function widget_admin_setup() {
		wp_enqueue_style( 'widget-vip-search-filters', plugins_url( 'css/search-widget-admin-ui.css', __FILE__ ), array(), 1.0 );

		wp_register_script(
			'vip-search-widget-admin',
			plugins_url( 'js/search-widget-admin.js', __FILE__ ),
			array( 'jquery', 'jquery-ui-sortable' ),
			1.0,
		);

		wp_localize_script(
			'vip-search-widget-admin', 'vip_search_filter_admin', array(
				'defaultFilterCount' => self::DEFAULT_FILTER_COUNT,
				'i18n'               => array(
					'month'        => VIP_Search_UI_Helpers::get_date_filter_type_name( 'month', false ),
					'year'         => VIP_Search_UI_Helpers::get_date_filter_type_name( 'year', false ),
					'monthUpdated' => VIP_Search_UI_Helpers::get_date_filter_type_name( 'month', true ),
					'yearUpdated'  => VIP_Search_UI_Helpers::get_date_filter_type_name( 'year', true ),
				),
			)
		);

		wp_enqueue_script( 'vip-search-widget-admin' );
	}

	/**
	 * Enqueue scripts and styles for the frontend.
	 *
	 */
	public function enqueue_frontend_scripts() {
		if ( ! is_active_widget( false, false, $this->id_base, true ) ) {
			return;
		}

		wp_enqueue_script(
			'vip-search-widget',
			plugins_url( 'js/search-widget.js', __FILE__ ),
			array(),
			1.0,
			true
		);

		wp_enqueue_style( 'vip-search-widget', plugins_url( 'css/search-widget-frontend.css', __FILE__ ), array(), 1.0 );
	}

	/**
	 * Get the list of valid sort types/orders.
	 *
	 * @return array The sort orders.
	 *
	 */
	private function get_sort_types():array {
		return array(
			'relevance|DESC' => is_admin() ? esc_html__( 'Relevance (recommended)', 'vip-search' ) : esc_html__( 'Relevance', 'vip-search' ),
			'date|DESC'      => esc_html__( 'Newest first', 'vip-search' ),
			'date|ASC'       => esc_html__( 'Oldest first', 'vip-search' ),
		);
	}

	/**
	 * This method returns a boolean for whether the widget should show site-wide filters for the site.
	 *
	 * This is meant to provide backwards-compatibility for VIP, and other professional plan users, that manually
	 * configured filters via `Jetpack_Search::set_filters()`.
	 *
	 * @return bool Whether the widget should display site-wide filters or not.
	 */
	public function should_display_sitewide_filters() {
		$filter_widgets = get_option( 'widget_jetpack-search-filters' );

		// This shouldn't be empty, but just for sanity
		if ( empty( $filter_widgets ) ) {
			return false;
		}

		// If any widget has any filters, return false
		foreach ( $filter_widgets as $number => $widget ) {
			$widget_id = sprintf( '%s-%d', $this->id_base, $number );
			if ( ! empty( $widget['filters'] ) && is_active_widget( false, $widget_id, $this->id_base ) ) {
				return false;
			}
		}

		return true;
	}

	public function vip_search_populate_defaults( $instance ) {
		$instance = wp_parse_args(
			(array) $instance, array(
				'title'              => '',
				'search_box_enabled' => true,
				'user_sort_enabled'  => true,
				'sort'               => self::DEFAULT_SORT,
				'post_types'         => array(),
			)
		);

		return $instance;
	}

	/**
	 * Responsible for rendering the widget on the frontend.
	 *
	 * @param array $args     Widgets args supplied by the theme.
	 * @param array $instance The current widget instance.
	 *
	 */
	public function widget( $args, $instance ) {
		$instance = $this->vip_search_populate_defaults( $instance );

//		if ( is_search() ) {
//			if ( VIP_Search_UI_Helpers::should_rerun_search_in_customizer_preview() ) {
//				Jetpack_Search::instance()->update_search_results_aggregations();
//			}
//
//			$filters = Jetpack_Search::instance()->get_filters();
//
//			if ( $this->should_display_sitewide_filters() ) {
//				$filters = array_filter( $filters, array( $this, 'is_for_current_widget' ) );
//			}
//
//			if ( ! empty( $filters ) ) {
//				$display_filters = true;
//			}
//		}

		if ( empty( $instance['search_box_enabled'] ) && empty( $instance['user_sort_enabled'] ) ) {
			return;
		}

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';

		/** This filter is documented in core/src/wp-includes/default-widgets.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo $args['before_widget']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
			<div id="<?php echo esc_attr( $this->id ); ?>-wrapper" >
		<?php

		if ( ! empty( $title ) ) {
			$this->render_widget_title( $title, $args['before_title'], $args['after_title'] );
		}

		$default_sort            = $instance['sort'] ?? self::DEFAULT_SORT;
		list( $orderby, $order ) = $this->sorting_to_wp_query_param( $default_sort );
		$current_sort            = "{$orderby}|{$order}";

		// we need to dynamically inject the sort field into the search box when the search box is enabled, and display
		// it separately when it's not.
		if ( ! empty( $instance['search_box_enabled'] ) ) {
			$this->render_widget_search_form( $instance['post_types'], $orderby, $order );
		}

		if ( ! empty( $instance['search_box_enabled'] ) && ! empty( $instance['user_sort_enabled'] ) ) :
			?>
			<div class="vip-search-sort-wrapper">
				<label>
					<?php esc_html_e( 'Sort by', 'vip-search' ); ?>
					<select class="vip-search-sort">
						<?php foreach ( $this->get_sort_types() as $sort => $label ) { ?>
							<option value="<?php echo esc_attr( $sort ); ?>" <?php selected( $current_sort, $sort ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php } ?>
					</select>
				</label>
			</div>
			<?php
		endif;

//		if ( $display_filters ) {
//			/**
//			 * Responsible for rendering filters to narrow down search results.
//			 *
//			 * @module search
//			 *
//			 * @since  5.8.0
//			 *
//			 * @param array $filters    The possible filters for the current query.
//			 * @param array $post_types An array of post types to limit filtering to.
//			 */
//			do_action(
//				'jetpack_search_render_filters',
//				$filters,
//				isset( $instance['post_types'] ) ? $instance['post_types'] : null
//			);
//		}

		$this->maybe_render_sort_javascript( $instance, $order, $orderby );

		echo '</div>';
		echo $args['after_widget']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}


	/**
	 * Renders JavaScript for the sorting controls on the frontend.
	 *
	 * This JS is a bit complicated, but here's what it's trying to do:
	 * - find the search form
	 * - find the orderby/order fields and set default values
	 * - detect changes to the sort field, if it exists, and use it to set the order field values
	 *
	 * @param array  $instance The current widget instance.
	 * @param string $order    The order to initialize the select with.
	 * @param string $orderby  The orderby to initialize the select with.
	 *
	 */
	private function maybe_render_sort_javascript( array $instance, string $order, string $orderby ) {
		if ( ! empty( $instance['user_sort_enabled'] ) ) :
			?>
			<script type="text/javascript">
				var vipSearchModuleSorting = function() {
					var orderByDefault = '<?php echo 'date' === $orderby ? 'date' : 'relevance'; ?>',
						orderDefault   = '<?php echo 'ASC' === $order ? 'ASC' : 'DESC'; ?>',
						widgetId       = decodeURIComponent( '<?php echo rawurlencode( $this->id ); ?>' ),
						searchQuery    = decodeURIComponent( '<?php echo rawurlencode( get_query_var( 's', '' ) ); ?>' ),
						isSearch       = <?php echo (int) is_search(); ?>;

					var container = document.getElementById( widgetId + '-wrapper' ),
						form = container.querySelector( '.vip-search-form form' ),
						orderBy = form.querySelector( 'input[name=orderby]' ),
						order = form.querySelector( 'input[name=order]' ),
						searchInput = form.querySelector( 'input[name="s"]' ),
						sortSelectInput = container.querySelector( '.vip-search-sort' );

					orderBy.value = orderByDefault;
					order.value = orderDefault;

					// Some themes don't set the search query, which results in the query being lost
					// when doing a sort selection. So, if the query isn't set, let's set it now. This approach
					// is chosen over running a regex over HTML for every search query performed.
					if ( isSearch && ! searchInput.value ) {
						searchInput.value = searchQuery;
					}

					searchInput.classList.add( 'show-placeholder' );

					sortSelectInput.addEventListener( 'change', function( event ) {
						var values  = event.target.value.split( '|' );
						orderBy.value = values[0];
						order.value = values[1];

						form.submit();
					} );
				}

				if ( document.readyState === 'interactive' || document.readyState === 'complete' ) {
					vipSearchModuleSorting();
				} else {
					document.addEventListener( 'DOMContentLoaded', vipSearchModuleSorting );
				}
			</script>
			<?php
		endif;
	}

	/**
	 * Convert a sort string into the separate order by and order parts.
	 *
	 * @param string $sort A sort string.
	 *
	 * @return array Order by and order.
	 *
	 */
	private function sorting_to_wp_query_param( string $sort ):array {
		$parts   = explode( '|', $sort );
		$orderby = $_GET['orderby'] ?? $parts[0];

		$order = isset( $_GET['order'] )
			? strtoupper( $_GET['order'] )
			: ( ( isset( $parts[1] ) && 'ASC' === strtoupper( $parts[1] ) ) ? 'ASC' : 'DESC' );

		return array( $orderby, $order );
	}

	/**
	 * Updates a particular instance of the widget. Validates and sanitizes the options.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via VIP_Search_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 *
	 * @return array Settings to save.
	 *
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title']              = sanitize_text_field( $new_instance['title'] );
		$instance['search_box_enabled'] = empty( $new_instance['search_box_enabled'] ) ? '0' : '1';
		$instance['user_sort_enabled']  = empty( $new_instance['user_sort_enabled'] ) ? '0' : '1';
		$instance['sort']               = $new_instance['sort'];
		$instance['post_types']         = empty( $new_instance['post_types'] ) || empty( $instance['search_box_enabled'] )
			? array()
			: array_map( 'sanitize_key', $new_instance['post_types'] );

		$filters = array();
		if ( isset( $new_instance['filter_type'] ) ) {
			foreach ( (array) $new_instance['filter_type'] as $index => $type ) {
				$count = (int) $new_instance['num_filters'][ $index ];
				$count = min( 50, $count ); // Set max boundary at 50.
				$count = max( 1, $count );  // Set min boundary at 1.

				switch ( $type ) {
					case 'taxonomy':
						$filters[] = array(
							'name'     => sanitize_text_field( $new_instance['filter_name'][ $index ] ),
							'type'     => 'taxonomy',
							'taxonomy' => sanitize_key( $new_instance['taxonomy_type'][ $index ] ),
							'count'    => $count,
						);
						break;
					case 'post_type':
						$filters[] = array(
							'name'  => sanitize_text_field( $new_instance['filter_name'][ $index ] ),
							'type'  => 'post_type',
							'count' => $count,
						);
						break;
					case 'date_histogram':
						$filters[] = array(
							'name'     => sanitize_text_field( $new_instance['filter_name'][ $index ] ),
							'type'     => 'date_histogram',
							'count'    => $count,
							'field'    => sanitize_key( $new_instance['date_histogram_field'][ $index ] ),
							'interval' => sanitize_key( $new_instance['date_histogram_interval'][ $index ] ),
						);
						break;
				}
			}
		}

		if ( ! empty( $filters ) ) {
			$instance['filters'] = $filters;
		}

		return $instance;
	}

	/**
	 * Outputs the settings update form.
	 *
	 * @param array $instance Previously saved values from database.
	 *
	 */
	public function form( $instance ) {
		$instance = $this->vip_search_populate_defaults( $instance );

		$title = strip_tags( $instance['title'] );

		$classes = sprintf(
			'vip-search-filters-widget %s %s',
			$instance['search_box_enabled'] ? '' : 'hide-post-types',
			$this->id
		);
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
					<?php esc_html_e( 'Title (optional):', 'vip-search' ); ?>
				</label>
				<input
					class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					type="text"
					value="<?php echo esc_attr( $title ); ?>"
				/>
			</p>

			<p>
				<label>
					<input
						type="checkbox"
						class="vip-search-filters-widget__search-box-enabled"
						name="<?php echo esc_attr( $this->get_field_name( 'search_box_enabled' ) ); ?>"
						<?php checked( $instance['search_box_enabled'] ); ?>
					/>
					<?php esc_html_e( 'Show search box', 'vip-search' ); ?>
				</label>
			</p>

			<p>
				<label>
					<input
						type="checkbox"
						class="vip-search-filters-widget__sort-controls-enabled"
						name="<?php echo esc_attr( $this->get_field_name( 'user_sort_enabled' ) ); ?>"
						<?php checked( $instance['user_sort_enabled'] ); ?>
						<?php disabled( ! $instance['search_box_enabled'] ); ?>
					/>
					<?php esc_html_e( 'Show sort selection dropdown', 'vip-search' ); ?>
				</label>
			</p>

			<p class="vip-search-filters-widget__post-types-select">
				<label><?php esc_html_e( 'Post types to search (minimum of 1):', 'vip-search' ); ?></label>
				<?php foreach ( get_post_types( array( 'exclude_from_search' => false ), 'objects' ) as $post_type ) : ?>
					<label>
						<input
							type="checkbox"
							value="<?php echo esc_attr( $post_type->name ); ?>"
							name="<?php echo esc_attr( $this->get_field_name( 'post_types' ) ); ?>[]"
							<?php checked( empty( $instance['post_types'] ) || in_array( $post_type->name, $instance['post_types'] ) ); ?>
						/>&nbsp;
						<?php echo esc_html( $post_type->label ); ?>
					</label>
				<?php endforeach; ?>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Default sort order:', 'vip-search' ); ?>
					<select
						name="<?php echo esc_attr( $this->get_field_name( 'sort' ) ); ?>"
						class="widefat vip-search-filters-widget__sort-order">
						<?php foreach ( $this->get_sort_types() as $sort_type => $label ) { ?>
							<option value="<?php echo esc_attr( $sort_type ); ?>" <?php selected( $instance['sort'], $sort_type ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php } ?>
					</select>
				</label>
			</p>
			
            <script class="vip-search-filters-widget__filter-template" type="text/template">
                <?php echo $this->render_widget_edit_filter( array(), true ); ?>
            </script>
            <div class="vip-search-filters-widget__filters">
                <?php foreach ( (array) $instance['filters'] as $filter ) : ?>
                    <?php $this->render_widget_edit_filter( $filter ); ?>
                <?php endforeach; ?>
            </div>
            <p class="vip-search-filters-widget__add-filter-wrapper">
                <a class="button vip-search-filters-widget__add-filter" href="#">
                    <?php esc_html_e( 'Add a filter', 'vip-search' ); ?>
                </a>
            </p>
            <noscript>
                <p class="vip-search-filters-help">
                    <?php echo esc_html_e( 'Adding filters requires JavaScript!', 'vip-search' ); ?>
                </p>
            </noscript>
		</div>
		<?php
	}

	/**
	 * We need to render HTML in two formats: an Underscore template (client-side)
	 * and native PHP (server-side). This helper function allows for easy rendering
	 * of attributes in both formats.
	 *
	 * @param string $name        Attribute name.
	 * @param string $value       Attribute value.
	 * @param bool   $is_template Whether this is for an Underscore template or not.
	 */
	private function render_widget_attr( $name, $value, $is_template ) {
		echo $is_template ? "<%= $name %>" : esc_attr( $value );
	}

	/**
	 * We need to render HTML in two formats: an Underscore template (client-size)
	 * and native PHP (server-side). This helper function allows for easy rendering
	 * of the "selected" attribute in both formats.
	 *
	 * @param string $name        Attribute name.
	 * @param string $value       Attribute value.
	 * @param string $compare     Value to compare to the attribute value to decide if it should be selected.
	 * @param bool   $is_template Whether this is for an Underscore template or not.
	 */
	private function render_widget_option_selected( $name, $value, $compare, $is_template ) {
		$compare_js = rawurlencode( $compare );
		echo $is_template ? "<%= decodeURIComponent( '$compare_js' ) === $name ? 'selected=\"selected\"' : '' %>" : selected( $value, $compare );
	}

	/**
	 * Responsible for rendering the search box within our widget on the frontend.
	 *
	 * @param array  $post_types Array of post types to limit search results to.
	 * @param string $orderby    How to order the search results.
	 * @param string $order      In what direction to order the search results.
	 *
	 */
	public static function render_widget_search_form( array $post_types, string $orderby, string $order ) {
		$form = get_search_form( false );

		$fields_to_inject = array(
			'orderby' => $orderby,
			'order'   => $order,
		);

		// If the widget has specified post types to search within and IF the post types differ
		// from the default post types that would have been searched, set the selected post
		// types via hidden inputs.
		if ( self::post_types_differ_searchable( $post_types ) ) {
			$fields_to_inject['post_type'] = implode( ',', $post_types );
		}

		$form = self::inject_hidden_form_fields( $form, $fields_to_inject );

		echo '<div class="vip-search-form">';
		echo $form; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Modifies an HTML form to add some additional hidden fields.
	 *
	 * @param string $form   The form HTML to modify.
	 * @param array  $fields Array of hidden fields to add. Key is field name and value is the field value.
	 *
	 * @return string The modified form HTML.
	 *
	 */
	private static function inject_hidden_form_fields( string $form, array $fields ):string {
		$form_injection = '';

		foreach ( $fields as $field_name => $field_value ) {
			$form_injection .= sprintf(
				'<input type="hidden" name="%s" value="%s" />',
				esc_attr( $field_name ),
				esc_attr( $field_value )
			);
		}

		// This shouldn't need to be escaped since we've escaped above as we built $form_injection
		$form = str_replace(
			'</form>',
			$form_injection . '</form>',
			$form
		);

		return $form;
	}

	/**
	 * Given the widget instance, will return true when selected post types differ from searchable post types.
	 *
	 * @param array $post_types An array of post types.
	 *
	 * @return bool
	 *
	 */
	private static function post_types_differ_searchable( array $post_types ):bool {
		if ( empty( $post_types ) ) {
			return false;
		}

		$searchable_post_types = get_post_types( array( 'exclude_from_search' => false ) );
		$diff_of_searchable    = self::array_diff( $searchable_post_types, (array) $post_types );

		return ! empty( $diff_of_searchable );
	}

	/**
	 * Since PHP's built-in array_diff() works by comparing the values that are in array 1 to the other arrays,
	 * if there are less values in array 1, it's possible to get an empty diff where one might be expected.
	 *
	 * @param array $array_1
	 * @param array $array_2
	 *
	 * @return array
	 *
	 */
	private static function array_diff( array $array_1, array $array_2 ) {
		// If the array counts are the same, then the order doesn't matter. If the count of
		// $array_1 is higher than $array_2, that's also fine. If the count of $array_2 is higher,
		// we need to swap the array order though.
		if ( count( $array_1 ) !== count( $array_2 ) && count( $array_2 ) > count( $array_1 ) ) {
			$temp    = $array_1;
			$array_1 = $array_2;
			$array_2 = $temp;
		}

		// Disregard keys
		return array_values( array_diff( $array_1, $array_2 ) );
	}

	/**
	 * Outputs the search widget's title.
	 *
	 * @param string $title        The widget's title
	 * @param string $before_title The HTML tag to display before the title
	 * @param string $after_title  The HTML tag to display after the title
	 *
	 */
	private static function render_widget_title( string $title, string $before_title, string $after_title ) {
		echo $before_title . esc_html( $title ) . $after_title; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Responsible for rendering a single filter in the customizer or the widget administration screen in wp-admin.
	 *
	 * We use this method for two purposes - rendering the fields server-side, and also rendering a script template for Underscore.
	 *
	 * @param array $filter      The filter to render.
	 * @param bool  $is_template Whether this is for an Underscore template or not.
	 */
	public function render_widget_edit_filter( $filter, $is_template = false ) {
		$args = wp_parse_args(
			$filter, array(
				'name'      => '',
				'type'      => 'taxonomy',
				'taxonomy'  => '',
				'post_type' => '',
				'field'     => '',
				'interval'  => '',
				'count'     => self::DEFAULT_FILTER_COUNT,
			)
		);

		$args['name_placeholder'] = VIP_Search_UI_Helpers::generate_widget_filter_name( $args );

		?>
		<div class="vip-search-filters-widget__filter is-<?php $this->render_widget_attr( 'type', $args['type'], $is_template ); ?>">
			<p class="vip-search-filters-widget__type-select">
				<label>
					<?php esc_html_e( 'Filter Type:', 'vip-search' ); ?>
					<select name="<?php echo esc_attr( $this->get_field_name( 'filter_type' ) ); ?>[]" class="widefat filter-select">
						<option value="taxonomy" <?php $this->render_widget_option_selected( 'type', $args['type'], 'taxonomy', $is_template ); ?>>
							<?php esc_html_e( 'Taxonomy', 'vip-search' ); ?>
						</option>
						<option value="post_type" <?php $this->render_widget_option_selected( 'type', $args['type'], 'post_type', $is_template ); ?>>
							<?php esc_html_e( 'Post Type', 'vip-search' ); ?>
						</option>
						<option value="date_histogram" <?php $this->render_widget_option_selected( 'type', $args['type'], 'date_histogram', $is_template ); ?>>
							<?php esc_html_e( 'Date', 'vip-search' ); ?>
						</option>
					</select>
				</label>
			</p>

			<p class="vip-search-filters-widget__taxonomy-select">
				<label>
					<?php
						esc_html_e( 'Choose a taxonomy:', 'vip-search' );
						$seen_taxonomy_labels = array();
					?>
					<select name="<?php echo esc_attr( $this->get_field_name( 'taxonomy_type' ) ); ?>[]" class="widefat taxonomy-select">
						<?php foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) : ?>
							<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php $this->render_widget_option_selected( 'taxonomy', $args['taxonomy'], $taxonomy->name, $is_template ); ?>>
								<?php
									$label = in_array( $taxonomy->label, $seen_taxonomy_labels )
										? sprintf(
											/* translators: %1$s is the taxonomy name, %2s is the name of its type to help distinguish between several taxonomies with the same name, e.g. category and tag. */
											_x( '%1$s (%2$s)', 'A label for a taxonomy selector option', 'vip-search' ),
											$taxonomy->label,
											$taxonomy->name
										)
										: $taxonomy->label;
									echo esc_html( $label );
									$seen_taxonomy_labels[] = $taxonomy->label;
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>

			<p class="vip-search-filters-widget__date-histogram-select">
				<label>
					<?php esc_html_e( 'Choose a field:', 'vip-search' ); ?>
					<select name="<?php echo esc_attr( $this->get_field_name( 'date_histogram_field' ) ); ?>[]" class="widefat date-field-select">
						<option value="post_date" <?php $this->render_widget_option_selected( 'field', $args['field'], 'post_date', $is_template ); ?>>
							<?php esc_html_e( 'Date', 'vip-search' ); ?>
						</option>
						<option value="post_date_gmt" <?php $this->render_widget_option_selected( 'field', $args['field'], 'post_date_gmt', $is_template ); ?>>
							<?php esc_html_e( 'Date GMT', 'vip-search' ); ?>
						</option>
						<option value="post_modified" <?php $this->render_widget_option_selected( 'field', $args['field'], 'post_modified', $is_template ); ?>>
							<?php esc_html_e( 'Modified', 'vip-search' ); ?>
						</option>
						<option value="post_modified_gmt" <?php $this->render_widget_option_selected( 'field', $args['field'], 'post_modified_gmt', $is_template ); ?>>
							<?php esc_html_e( 'Modified GMT', 'vip-search' ); ?>
						</option>
					</select>
				</label>
			</p>

			<p class="vip-search-filters-widget__date-histogram-select">
				<label>
					<?php esc_html_e( 'Choose an interval:', 'vip-search' ); ?>
					<select name="<?php echo esc_attr( $this->get_field_name( 'date_histogram_interval' ) ); ?>[]" class="widefat date-interval-select">
						<option value="month" <?php $this->render_widget_option_selected( 'interval', $args['interval'], 'month', $is_template ); ?>>
							<?php esc_html_e( 'Month', 'vip-search' ); ?>
						</option>
						<option value="year" <?php $this->render_widget_option_selected( 'interval', $args['interval'], 'year', $is_template ); ?>>
							<?php esc_html_e( 'Year', 'vip-search' ); ?>
						</option>
					</select>
				</label>
			</p>

			<p class="vip-search-filters-widget__title">
				<label>
					<?php esc_html_e( 'Title:', 'vip-search' ); ?>
					<input
						class="widefat"
						type="text"
						name="<?php echo esc_attr( $this->get_field_name( 'filter_name' ) ); ?>[]"
						value="<?php $this->render_widget_attr( 'name', $args['name'], $is_template ); ?>"
						placeholder="<?php $this->render_widget_attr( 'name_placeholder', $args['name_placeholder'], $is_template ); ?>"
					/>
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Maximum number of filters (1-50):', 'vip-search' ); ?>
					<input
						class="widefat filter-count"
						name="<?php echo esc_attr( $this->get_field_name( 'num_filters' ) ); ?>[]"
						type="number"
						value="<?php $this->render_widget_attr( 'count', $args['count'], $is_template ); ?>"
						min="1"
						max="50"
						step="1"
						required
					/>
				</label>
			</p>

			<p class="vip-search-filters-widget__controls">
				<a href="#" class="delete"><?php esc_html_e( 'Remove', 'vip-search' ); ?></a>
			</p>
		</div>
	<?php
	}
}
