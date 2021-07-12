<?php

namespace Automattic\VIP\Search\UI;

/**
* Class VIP_Search_Widget
 * @package Automattic\VIP\Search\UI
 *
 * Derived from Jetpack_Search_Widget
 */
class VIP_Search_Widget extends \WP_Widget {

	/**
	 * Default sort order for search results.
	 *
	 * @var string
	 */
	const DEFAULT_SORT = 'relevance_desc';

	public function __construct( $name = null ) {
		if ( empty( $name ) ) {
			$name = 'Search (VIP Enterprise)';
		}

		parent::__construct(
			'vip-search-widget',
			$name,
			array( 'description' => 'UI for VIP Enterprise Search' )
		);
	}

	/**
	 * Get the list of valid sort types/orders.
	 *
	 * @return array The sort orders.
	 *
	 */
	private function get_sort_types():array {
		return array(
			'relevance|DESC' => is_admin() ? esc_html__( 'Relevance (recommended)', 'jetpack' ) : esc_html__( 'Relevance', 'jetpack' ),
			'date|DESC'      => esc_html__( 'Newest first', 'jetpack' ),
			'date|ASC'       => esc_html__( 'Oldest first', 'jetpack' ),
		);
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
					<?php esc_html_e( 'Sort by', 'jetpack' ); ?>
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
					<?php esc_html_e( 'Title (optional):', 'jetpack' ); ?>
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
					<?php esc_html_e( 'Show search box', 'jetpack' ); ?>
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
					<?php esc_html_e( 'Show sort selection dropdown', 'jetpack' ); ?>
				</label>
			</p>

			<p class="vip-search-filters-widget__post-types-select">
				<label><?php esc_html_e( 'Post types to search (minimum of 1):', 'jetpack' ); ?></label>
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
					<?php esc_html_e( 'Default sort order:', 'jetpack' ); ?>
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
		</div>
		<?php
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

}
