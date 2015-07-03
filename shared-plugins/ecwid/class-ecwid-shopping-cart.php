<?php

class Ecwid_Shopping_Cart {

	const DEMO_STORE_ID = '1003';
	const ECWID_URL     = 'app.ecwid.com';

	const MAX_VIEW_ITEMS = 100;

	const MAX_CATEGORIES_PER_ROW     = '25';
	const DEFAULT_CATEGORIES_PER_ROW = '3';

	const DEFAULT_GRID_SIZE  = '3,3';
	const DEFAULT_LIST_SIZE  = '10';
	const DEFAULT_TABLE_SIZE = '20';

	const DEFAULT_SEARCH_VIEW   = 'list';
	const DEFAULT_CATEGORY_VIEW = 'grid';

	protected $scriptjs_rendered = false;
	protected $rendered_widgets = array();

	public function __construct()
	{
		require_once ECWID_PLUGIN_DIR . "/class-ecwid-settings-page.php";
		$this->settingsPage = new Ecwid_Settings_Page();
		$this->add_hooks();
	}

	protected function add_hooks() {
		if ( ! is_admin() ) {
			add_shortcode( 'ecwid', array( $this, 'ecwid_shortcode' ) );
			add_shortcode( 'ecwid_product', array( $this, 'ecwid_product_shortcode' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_frontend_css' ) );
		} else {
			add_filter( 'content_save_pre', array( $this, 'process_ecwid_script_tags' ) );
		}
	}

	public function process_ecwid_script_tags( $content ) {
		require_once ECWID_PLUGIN_DIR . "/class-ecwid-dashboard-to-shortcode-converter.php";

		if ( strpos( $content, 'app.ecwid.com/script.js' ) ) {
			$reverse = new Ecwid_Dashboard_To_Shortcode_Converter();
			$content = $reverse->convert( $content );
		}

		return $content;
	}

	/**
	 * The ecwid shopping cart shortcode.
	 *
	 * Produces ecwid widgets for listed in "widgets" attributes.
	 *
	 * The supported attributes are:
	 * - 'widgets', 'id' are common attributes
	 * - 'categories_per_row', 'search_view', 'category_view', 'responsive', 'default_category_id', 'grid', 'list', 'table' are for product browser widget.
	 * - 'layout' is for minicart widget
	 *
	 * More information about widgets attributes of certain widgets can be found here: http://kb.ecwid.com/w/page/15853259/Ecwid%20widgets
	 *
	 * @param $attributes
	 *
	 * @return string
	 */
	public function ecwid_shortcode( $attributes ) {
		$args = shortcode_atts(
			array(
				'id'                  => self::DEMO_STORE_ID,
				'widgets'             => 'productbrowser',
				'categories_per_row'  => self::DEFAULT_CATEGORIES_PER_ROW,
				'search_view'         => self::DEFAULT_SEARCH_VIEW,
				'category_view'       => self::DEFAULT_CATEGORY_VIEW,
				'responsive'          => 'yes',
				'default_category_id' => 0,
				'layout'			  => '',
				// grid, list and table are not reset to defaults because if one does not specify them, then the products view does not include that type of display
				'grid'                => null,
				'table'               => null,
				'list'                => null,
			),
			$attributes,
			'ecwid'
		);

		$result = '<div>';

		if ( ! $this->scriptjs_rendered ) {
			$result .= $this->render_scriptjs( $args['id'] );
		}

		$widgets = explode( ' ', $args['widgets'] );
		foreach ( $widgets as $widget ) {
			$widget = trim( $widget );

			switch ( $widget ) {
				case 'productbrowser':
					if ( !in_array( $widget, $this->rendered_widgets ) ) {
						$result .= $this->get_widget_productbrowser( $args );
					} else {
						$result .= $this->get_productbrowser_placeholder();
					}

					break;

				case 'categories':
					$result .= $this->get_widget_categories( $args );
					break;

				case 'vcategories':
					$result .= $this->get_widget_vcategories( $args );
					break;

				case 'search':
					$result .= $this->get_widget_search( $args );
					break;

				case 'minicart':
					$result .= $this->get_widget_minicart( $args );
					break;
			}

			$this->rendered_widgets[] = $widget;
		}

		$result .= '</div>';

		return $result;
	}

	public function ecwid_product_shortcode( $shortcode_attributes ) {

		$attributes = shortcode_atts(
			array(
				'store_id' => null,
				'product_id' => null,
				'display' => 'picture title price options addtobag',
				'link' => null
			),
			$shortcode_attributes
		);

		$store_id = $attributes['store_id'];

		$product_id = $attributes['product_id'];

		if ( is_null( $store_id ) || !is_numeric( $store_id ) || $store_id <= 0 ) return;
		if ( is_null( $product_id ) || !is_numeric( $product_id ) || $product_id <= 0 ) return;

		$display_items = array(
			'picture'  => '<div itemprop="picture"></div>',
			'title'    => '<div class="ecwid-title" itemprop="title"></div>',
			'price'    => '<div itemtype="http://schema.org/Offer" itemscope itemprop="offers">'
				. '<div class="ecwid-productBrowser-price ecwid-price" itemprop="price"></div>'
				. '</div>',
			'options'  => '<div itemprop="options"></div>',
			'qty'      => '<div itemprop="qty"></div>',
			'addtobag' => '<div itemprop="addtobag"></div>'
		);

		$result = $this->render_scriptjs( $store_id );
		$result .= sprintf(
			'<div class="ecwid ecwid-SingleProduct ecwid-Product ecwid-Product-%d" '
			. 'itemscope itemtype="http://schema.org/Product" '
			. 'data-single-product-id="%d">',
			$store_id, $product_id
		);

		$items = preg_split( '![^0-9^a-z^A-Z^\-^_]!', $attributes['display'] );

		if ( is_array( $items ) && count( $items ) > 0 ) {
			foreach ( $items as $item ) {
				if ( array_key_exists( $item, $display_items ) ) {
					if ( isset( $attributes['link'] ) && in_array( $item, array( 'title', 'picture' ) ) ) {
						$product_link = $attributes['link'] . '#!/~/product/id=' . $product_id;
						$result .= '<a href="' . esc_url( $product_link ) . '">' . $display_items[$item] . '</a>';
					} else {
						$result .= $display_items[$item];
					}
				}
			}
		}

		$result .= '</div>';

		$result .= '<script type="text/javascript">xSingleProduct();</script>';

		return $result;
	}

	public function add_frontend_css()
	{
		wp_enqueue_style( 'ecwid-frontend', plugin_dir_url( __FILE__ ) . 'css/frontend.css' );
	}

	protected function get_productbrowser_placeholder()
	{
		$header = __( 'Unable to show storefront', 'ecwid-shortcode' );
		$message = __(
			'There can be only one Ecwid Product Browser or Ecwid Search widget'
			. ' on a single page. Please, choose which one should stay on the page'
			. ' and remove other widgets.',
			'ecwid-shortcode'
		);

		$content = '<div class="ecwid-warning">'
			. '<div class="header">' . esc_html( $header ) . '</div>'
			. '<div class="message">' . esc_html( $message ) . '</div>'
			. '</div>';

		return $content;
	}

	protected function render_scriptjs( $store_id ) {

		$store_id = intval( $store_id );
		if ( ! $store_id ) {
			$store_id = self::DEMO_STORE_ID;
		}

		$url = '//' . self::ECWID_URL . '/script.js?' . $store_id . '&data_platform=wpcom';
		$result = '<script type="text/javascript" src="' . esc_url( $url ) . '"></script>';
		$this->scriptjs_rendered = true;

		return $result;
	}

	protected function get_widget_productbrowser( $args ) {

		// Categories per row
		$cats_per_row = $this->sanitize_int(
			$args['categories_per_row'],
			self::DEFAULT_CATEGORIES_PER_ROW,
			self::MAX_CATEGORIES_PER_ROW
		);

		// Views
		// if only some of 'grid', 'list' and 'table' are specified, then others are not available for customer
		// otherwise it produces empty value meaning that all three are available with default sizes
		$views = array();

		$grid = $args['grid'];
		if ( ! is_null( $grid ) ) {
			$value = $this->sanitize_grid(
				$grid,
				self::DEFAULT_GRID_SIZE,
				self::MAX_VIEW_ITEMS
			);

			$views[] = "grid($value)";
		}

		$list = $args['list'];
		if ( ! is_null( $list ) ) {
			$list = $this->sanitize_int(
				$list,
				self::DEFAULT_LIST_SIZE,
				self::MAX_VIEW_ITEMS
			);

			$views[] = "list($list)";
		}

		$table = $args['table'];
		if ( ! is_null( $table ) ) {
			$table = $this->sanitize_int(
				$table,
				self::DEFAULT_TABLE_SIZE,
				self::MAX_VIEW_ITEMS
			);

			$views[] = "table($table)";
		}

		if ( ! empty( $views ) ) {
			$views = implode( " ", $views );
		}
		else {
			$views = '';
		}


		// Search view
		$search_view = $this->sanitize_enum(
			$args['search_view'],
			self::DEFAULT_SEARCH_VIEW,
			array( 'list', 'grid', 'table' )
		);


		// Category view
		$cat_view = $this->sanitize_enum(
			$args['category_view'],
			self::DEFAULT_CATEGORY_VIEW,
			array( 'list', 'grid', 'table' )
		);


		// Responsive
		$responsive_code = $args['responsive'] != 'no' ? ",'responsive=yes'" : '';


		// Default category id
		$default_cat = intval( $args['default_category_id'] );
		$default_category_code = $default_cat ? ",'defaultCategoryId=" . esc_js($default_cat) . "'" : '';


		$result = sprintf(
			'<script type="text/javascript"> xProductBrowser('
			. "'categoriesPerRow=%s',"
			. "'views=%s',"
			. "'categoryView=%s',"
			. "'searchView=%s',"
			. "'style='"
			. $responsive_code
			. $default_category_code
			. "); </script>",
			esc_js($cats_per_row), esc_js($views), esc_js($cat_view), esc_js($search_view)
		);

		return $result;
	}

	protected function get_widget_minicart( $args ) {

		$layout      = strtolower( isset($args['layout']) ? $args['layout'] : '' );
		$layout_code = '';

		$valid_layouts = array( 'attachToCategories', 'floating', 'Mini', 'MiniAttachToProductBrowser' );

		foreach ( $valid_layouts as $valid ) {
			if ( strtolower( $valid ) == $layout ) {
				$layout_code = ",'layout=" . esc_js( $valid ) . "'";
			}
		}

		$result = '<script type="text/javascript"> xMinicart("style="' . $layout_code. ');</script>';

		return $result;
	}

	protected function get_widget_categories( $args ) {
		return '<script type="text/javascript"> xCategories(\'style=\');</script>';
	}

	protected function get_widget_vcategories( $args ) {
		return '<script type="text/javascript"> xVCategories(\'style=\');</script>';
	}

	protected function get_widget_search( $args ) {
		return '<script type="text/javascript"> xSearchPanel(\'style=\');</script>';
	}

	/**
	 * Returns $value if it is a positive int less than $max; $default otherwise.
	 *
	 * @param $value
	 * @param $default
	 * @param $max
	 *
	 * @return int
	 */
	protected function sanitize_int( $value, $default, $max ) {

		$result = $default;

		$value = intval( $value );
		if ( 0 < $value && $max >= $value ) {
			$result = $value;
		}

		return $result;
	}

	/**
	 * Returns $value if it represents one of the $values array items; $default otherwise.
	 *
	 * @param $value
	 * @param $default
	 * @param $values
	 *
	 * @return mixed
	 */
	protected function sanitize_enum( $value, $default, $values ) {

		$result = $default;

		if ( in_array( $value, $values ) ) {
			$result = $value;
		}

		return $result;
	}

	/**
	 * Returns a $default value if $value is not in form "int,int" or its elements count is zero or exceeds $max_total
	 *
	 * @param $value
	 * @param $default
	 * @param $max_total
	 *
	 * @return string
	 */
	protected function sanitize_grid( $value, $default, $max_total ) {
		$result = $default;

		$sizes = explode( ",", $value );
		if ( 2 == count( $sizes ) ) {
			$rows = intval( $sizes	[0] );
			$cols = intval( $sizes[1] );

			if (
				$max_total >= $rows
				&& 1 <= $rows
				&& $max_total >= $cols
				&& 1 <= $cols
				&& $max_total >= $rows * $cols
			) {
				$result = "$rows,$cols";
			}
		}

		return $result;
	}
}
