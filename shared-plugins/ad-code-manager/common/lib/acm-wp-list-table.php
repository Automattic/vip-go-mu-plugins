<?php
/**
 * Skeleton child class of WP_List_Table
 *
 * You need to extend it for a specific provider
 * Check /providers/doubleclick-for-publishers.php
 * to see example of implementation
 *
 * @since v0.1.3
 */
//Our class extends the WP_List_Table class, so we need to make sure that it's there

require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class ACM_WP_List_Table extends WP_List_Table {

	function __construct( $params = array() ) {
		parent::__construct( $params );
	}

	/**
	 * Define the columns that are going to be used in the table
	 *
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns( $columns = false ) {
		$default = array(
			'cb'             => '<input type="checkbox" />',
			'id'             => __( 'ID', 'ad-code-manager' ),
			'name'           => __( 'Name', 'ad-code-manager' ),
			'priority'       => __( 'Priority', 'ad-code-manager' ),
			'operator'       => __( 'Logical Operator', 'ad-code-manager' ),
			'conditionals'   => __( 'Conditionals', 'ad-code-manager' ),
		);
		$columns = apply_filters( 'acm_list_table_columns', !is_array( $columns ) || empty( $columns ) ? $default : $columns );
		// Fail-safe for misconfiguration
		$required_before = array(
			'id'             => __( 'ID', 'ad-code-manager' ),
			'cb'             => '<input type="checkbox" />',
		);
		$required_after = array(
			'priority'       => __( 'Priority', 'ad-code-manager' ),
			'operator'       => __( 'Logical Operator', 'ad-code-manager' ),
			'conditionals'   => __( 'Conditionals', 'ad-code-manager' ),
		);
		$columns = array_merge( $required_before, $columns, $required_after  );
		return $columns;
	}

	/**
	 * Define bulk actions to allow users to mass delete ad codes
	 *
	 * @since 0.2.2
	 *
	 * @return array $bulk_actions All of the bulk actions permitted on the List Table
	 */
	function get_bulk_actions() {
		$bulk_actions = array(
			'delete' => __( 'Delete', 'ad-code-manager' ),
		);
		return $bulk_actions;
	}

	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	function prepare_items() {
		global $ad_code_manager;

		$screen = get_current_screen();

		$this->items = $ad_code_manager->get_ad_codes();

		if ( empty( $this->items ) )
			return;

		/* -- Pagination parameters -- */
		//Number of elements in your table?
		$totalitems = count( $this->items ); //return the total number of affected rows

		//How many to display per page?
		$perpage = apply_filters( 'acm_list_table_per_page', 25 );

		//Which page is this?
		$paged = !empty( $_GET["paged"] ) ? intval( $_GET["paged"] ) : '';

		//Page Number
		if ( empty( $paged ) || !is_numeric( $paged ) || $paged<=0 ) { $paged=1; }
		//How many pages do we have in total?

		$totalpages = ceil( $totalitems/$perpage );

		//adjust the query to take pagination into account

		if ( ! empty( $paged ) && !empty( $perpage ) ) {
			$offset = ( $paged - 1 ) * $perpage;
		}

		/* -- Register the pagination -- */
		$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page" => $perpage,
			) );
		//The pagination links are automatically built according to those parameters

		/* -- Register the Columns -- */
		$columns = $this->get_columns();
		$hidden = array(
			'id',
		);
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() ) ;

		/**
		 * Items are set in Ad_Code_Manager class
		 * All we need to do is to prepare it for pagination
		 */
		$this->items = array_slice( $this->items, $offset, $perpage );
	}

	/**
	 * Message to be displayed if there are no ad codes found
	 *
	 * @since 0.2
	 */
	function no_items() {
		_e( 'No ad codes have been configured.', 'ad-code-manager' );
	}

	/**
	 * Prepare and echo a single ad code row
	 *
	 * @since 0.2
	 */
	function single_row( $item ) {
		static $alternate_class = '';
		$alternate_class = ( $alternate_class == '' ? ' alternate' : '' );
		$row_class = ' class="term-static' . $alternate_class . '"';

		echo '<tr id="ad-code-' . $item['post_id'] . '"' . $row_class . '>';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Fallback column callback.
	 *
	 * @since 0.2
	 *
	 * @param object  $item        Custom status as an object
	 * @param string  $column_name Name of the column as registered in $this->prepare_items()
	 * @return string $output What will be rendered
	 */
	function column_default( $item, $column_name ) {
		global $ad_code_manager;

		switch ( $column_name ) {
		case 'priority':
			return esc_html( $item['priority'] );
			break;
		case 'operator':
			return ( ! empty( $item['operator'] ) ) ? $item['operator'] : $ad_code_manager->logical_operator;
		default:
			// @todo need to make the first column (whatever it is filtered) to show row actions
			// Handle custom columns, if any
			if ( isset( $item['url_vars'][$column_name] ) )
				return esc_html( $item['url_vars'][$column_name] );
			break;
		}

	}

	/**
	 * Column with a checkbox
	 * Used for bulk actions
	 *
	 * @since 0.2.2
	 *
	 * @param object  $item Ad code as an object
	 * @return string $output What will be rendered
	 */
	function column_cb( $item ) {
		$id = $item['post_id'];
		$output = "<input type='checkbox' name='ad-codes[]' id='ad_code_{$id}' value='{$id}' />";
		return $output;
	}

	/**
	 * Display hidden information we need for inline editing
	 */
	function column_id( $item ) {
		global $ad_code_manager;
		$output = '<div id="inline_' . $item['post_id'] . '" style="display:none;">';
		$output .= '<div class="id">' . $item['post_id'] . '</div>';
		// Build the fields for the conditionals
		$output .= '<div class="acm-conditional-fields"><div class="form-new-row">';
		$output .= '<h4 class="acm-section-label">' . __( 'Conditionals', 'ad-code-manager' ) . '</h4>';
		if ( !empty( $item['conditionals'] ) ) {
			foreach ( $item['conditionals'] as $conditional ) {
				$function = $conditional['function'];
				$arguments = $conditional['arguments'];
				$output .= '<div class="conditional-single-field"><div class="conditional-function">';
				$output .= '<select name="acm-conditionals[]">';
				$output .= '<option value="">' . __( 'Select conditional', 'ad-code-manager' ) . '</option>';
				foreach ( $ad_code_manager->whitelisted_conditionals as $key ) {
					$output .= '<option value="' .  esc_attr( $key ) . '" ' . selected( $function, $key, false ) . '>';
					$output .= esc_html( ucfirst( str_replace( '_', ' ', $key ) ) );
					$output .= '</option>';
				}
				$output .= '</select>';
				$output .= '</div><div class="conditional-arguments">';
				$output .= '<input name="acm-arguments[]" type="text" value="' . esc_attr( implode( ';', $arguments ) ) .'" size="20" />';
				$output .= '<a href="#" class="acm-remove-conditional">Remove</a></div></div>';
			}
		}
		$output .= '</div><div class="form-field form-add-more"><a href="#" class="button button-secondary add-more-conditionals">' . __( 'Add more', 'ad-code-manager' ) . '</a></div>';
		$output .= '</div>';
		// Build the fields for the normal columns
		$output .= '<div class="acm-column-fields">';
		$output .= '<h4 class="acm-section-label">' . __( 'URL Variables', 'ad-code-manager' ) . '</h4>';
		foreach ( (array)$item['url_vars'] as $slug => $value ) {
			$output .= '<div class="acm-section-single-field">';
			$column_id = 'acm-column[' . $slug . ']';
			$output .= '<label for="' . esc_attr( $column_id ) . '">' . esc_html( $slug ) . '</label>';
			// Support for select dropdowns
			$ad_code_args = $ad_code_manager->current_provider->ad_code_args;
			$ad_code_arg = array_shift( wp_filter_object_list( $ad_code_args, array( 'key' => $slug ) ) );
			if ( isset( $ad_code_arg['type'] ) && 'select' == $ad_code_arg['type'] ) {
				$output .= '<select name="' . esc_attr( $column_id ) . '">';
				foreach ( $ad_code_arg['options'] as $key => $label ) {
					$output .= '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_attr( $label ) . '</option>';
				}
				$output .= '</select>';
			} else {
				$output .= '<input name="' . esc_attr( $column_id ) . '" id="' . esc_attr( $column_id ) . '" type="text" value="' . esc_attr( $value ) . '" size="20" aria-required="true">';
			}
			$output .= '</div>';
		}
		$output .= '</div>';
		// Build the field for the priority
		$output .= '<div class="acm-priority-field">';
		$output .= '<h4 class="acm-section-label">' . __( 'Priority', 'ad-code-manager' ) . '</h4>';
		$output .= '<input type="text" name="priority" value="' . esc_attr( $item['priority'] ) . '" />';
		$output .= '</div>';
		// Build the field for the logical operator
		$output .= '<div class="acm-operator-field">';
		$output .= '<h4 class="acm-section-label">' . __( 'Logical Operator', 'ad-code-manager' ) . '</h4>';
		$output .= '<select name="operator">';
		$operators = array(
			'OR'     => __( 'OR', 'ad-code-manager' ),
			'AND'    => __( 'AND', 'ad-code-manager' ),
		);
		foreach ( $operators as $key => $label ) {
			$output .= '<option ' . selected( $item['operator'], $key ) . '>' . esc_attr( $label ) . '</option>';
		}
		$output .= '</select>';
		$output .= '</div>';

		$output .= '</div>';
		return $output;
	}

	/**
	 *
	 */
	function column_name( $item ) {
		$output = isset( $item['name'] ) ? esc_html( $item['name'] ) : esc_html( $item['url_vars']['name'] );
		$output .= $this->row_actions_output( $item );
		return $output;
	}

	/**
	 * Display the conditionals for this ad code
	 *
	 * @since 0.2
	 */
	function column_conditionals( $item ) {
		if ( empty( $item['conditionals'] ) )
			return '<em>' . __( 'None', 'ad-code-manager' ) . '</em>';

		$conditionals_html = '';
		foreach ( $item['conditionals'] as $conditional ) {
			$conditionals_html .= '<strong>' . esc_html( $conditional['function'] ) . '</strong> ' . esc_html( $conditional['arguments'][0] ) . '<br />';
		}
		return $conditionals_html;
	}

	/**
	 * Produce the action links and hidden HTML for inline editing
	 *
	 * @since 0.2
	 */
	function row_actions_output( $item ) {

		$output = '';
		// $row_actions['preview-ad-code'] = '<a class="acm-ajax-preview" id="acm-preview-' . $item[ 'post_id' ] . '" href="#">' . __( 'Preview Ad Code', 'ad-code-manager' ) . '</a>';
		$row_actions['edit'] = '<a class="acm-ajax-edit" id="acm-edit-' . $item[ 'post_id' ] . '" href="#">' . __( 'Edit Ad Code', 'ad-code-manager' ) . '</a>';

		$args = array(
			'action' => 'acm_admin_action',
			'method' => 'delete',
			'id' => $item['post_id'],
			'nonce' => wp_create_nonce( 'acm-admin-action' ),
		);
		$delete_link = add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
		$row_actions['delete'] = '<a class="acm-ajax-delete" id="acm-delete-' . $item[ 'post_id' ] . '" href="' . esc_url( $delete_link ) . '">' . __( 'Delete', 'ad-code-manager' ) . '</a>';

		$output .= $this->row_actions( $row_actions );
		return $output;
	}

	/**
	 * Hidden form used for inline editing functionality
	 *
	 * @since 0.2
	 */
	function inline_edit() {
?>
	<form method="POST" action="<?php echo admin_url( 'admin-ajax.php' ); ?>"><table style="display: none"><tbody id="inlineedit">
		<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
			<fieldset><div class="inline-edit-col">
				<input type="hidden" name="id" value="" />
				<input type="hidden" name="action" value="acm_admin_action" />
				<input type="hidden" name="method" value="edit" />
				<input type="hidden" name="doing_ajax" value="true" />
				<?php wp_nonce_field( 'acm-admin-action', 'nonce' ); ?>
				<div class="acm-float-left">
				<div class="acm-column-fields"></div>
				<div class="acm-priority-field"></div>
				<div class="acm-operator-field"></div>
				</div>
				<div class="acm-conditional-fields"></div>
				<div class="clear"></div>
			</div></fieldset>
		<p class="inline-edit-save submit">
			<?php $cancel_text = __( 'Cancel', 'ad-code-manager' ); ?>
			<a href="#inline-edit" title="<?php echo esc_attr( $cancel_text ); ?>" class="cancel button-secondary alignleft"><?php echo esc_html( $cancel_text ); ?></a>
			<?php $update_text = __( 'Update', 'ad-code-manager' ); ?>
			<a href="#inline-edit" title="<?php echo esc_attr( $update_text ); ?>" class="save button-primary alignright"><?php echo esc_html( $update_text ); ?></a>
			<img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
			<span class="error" style="display:none;"></span>
			<br class="clear" />
		</p>
		</td></tr>
		</tbody></table></form>
	<?php
	}

}
