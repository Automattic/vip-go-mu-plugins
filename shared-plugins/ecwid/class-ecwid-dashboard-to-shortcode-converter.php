<?php

class Ecwid_Dashboard_To_Shortcode_Converter {

	public function convert( $input ) {
		while ( $result = $this->replace_product_browser( $input ) ) {
			$input = $result;
		}

		while ( $result = $this->replace_minicart( $input ) ) {
			$input = $result;
		}

		$generic_widgets = array(
			'categories'  => 'xCategories',
			'vcategories' => 'xVCategories',
			'search'      => 'xSearchPanel'
		);
		foreach ( $generic_widgets as $widget => $function ) {
			while ( $result = $this->replace_generic( $input, $function, $widget ) ) {
				$input = $result;
			}
		}

		return $input;
	}

	protected function build_short_code( $type, $id, $other_args ) {

		$args = '';
		foreach ( $other_args as $name => $value ) {
			if ( $name == 'id' ) continue;

			$args .= sprintf( '%s="%s" ', htmlentities( $name ), htmlentities( $value ) );
		}

		$result = sprintf( '[ecwid id="%s" %swidgets="%s"]', htmlentities( $id ) , $args, htmlentities( $type ) );

		return $result;
	}

	protected function replace_product_browser( $input ) {
		$matches = array();

		$match_expressions = array(
			'#<div id=\\\\"my-store-.*?</div>\s*<div>\s*<script type=\\\\"text/javascript\\\\" src=\\\\"http://app.ecwid.com/script.js\?([0-9]*)\\\\"[^>]*>\s*</script>\s*<script type=\\\\"text/javascript\\\\"[^>]*> xProductBrowser\(\\\\"categoriesPerRow=([^\\\\]*)\\\\",\\\\"views=([^\\\\]*)\\\\",\\\\"categoryView=([^\\\\]*)\\\\",\\\\"searchView=([^\\\\]*)\\\\"[^)]*\);\s*</script>\s*</div>#s',
			'#&lt;div id=\\\\"my-store-.*?&lt;/div&gt;\s*&lt;div&gt;\s*&lt;script type=\\\\"text/javascript\\\\" src=\\\\"http://app.ecwid.com/script.js\?([0-9]*)\\\\"[^&]*&gt;\s*&lt;/script&gt;\s*&lt;script type=\\\\"text/javascript\\\\"[^&]*&gt;\s*xProductBrowser\(\\\\"categoriesPerRow=([^\\\\]*)\\\\",\\\\"views=([^\\\\]*)\\\\",\\\\"categoryView=([^\\\\]*)\\\\",\\\\"searchView=([^\\\\]*)\\\\"[^)]*\);\s*&lt;/script&gt;\s*&lt;/div&gt;#s',
		);

		$expression_args = array( 'id', 'categories_per_row', 'views', 'category_view', 'search_view' );

		$found = false;
		foreach ($match_expressions as $expression) {
			if ( preg_match( $expression, $input, $matches ) ) {
				$found = true;
				break;
			}
		}

		if (!$found) {
			return $false;
		}

		$args = array();
		foreach ( $expression_args as $ind => $arg ) {
			$args[$arg] = $matches[$ind + 1]; // one for match[0] that is full string
		}

		if ( $args['views'] ) {
			$views = explode( ' ', $args['views'] );
			foreach ( $views as $view ) {
				$matches = array();
				if ( preg_match( '!grid\(([0-9]*),([0-9]*)\)!', $view, $matches ) ) {
					$args['grid'] = "$matches[1],$matches[2]";
				}
				elseif ( preg_match( '!list\(([0-9]*)\)!', $view, $matches ) ) {
					$args['list'] = $matches[1];
				}
				elseif ( preg_match( '!table\(([0-9]*)\)!', $view, $matches ) ) {
					$args['table'] = $matches[1];
				}
			}

			unset( $args['views'] );
		}

		$short_code = $this->build_short_code( "productbrowser", $args['id'], $args );

		return preg_replace( $expression, $short_code, $input, 1 );
	}

	protected function replace_minicart( $input ) {
		$expressions = array(
			'#<div>\s*<script type=\\\\"text/javascript\\\\" src=\\\\"http://app.ecwid.com/script.js\?([0-9]*)\\\\"[^>]*></script>\s*<!--[^-]*-->\s*<script type=\\\\"text/javascript\\\\"> xMinicart\(\\\\"layout=([^\\\\]*)\\\\"\);\s*</script>\s*</div>#s',
			'#&lt;div&gt;\s*&lt;script type=\\\\"text/javascript\\\\" src=\\\\"http://app.ecwid.com/script.js\?([0-9]*)\\\\"[^&]*&gt;&lt;/script&gt;\s*&lt;!--[^-]*--&gt;\s*&lt;script type=\\\\"text/javascript\\\\"&gt; xMinicart\(\\\\"layout=([^\\\\]*)\\\\"\);\s*&lt;/script&gt;\s*&lt;/div&gt;#s'
		);

		$found = false;
		$matches = array();

		foreach ($expressions as $expression) {
			if ( preg_match( $expression, $input, $matches ) ) {
				$found = $expression;
				break;
			}
		}

		if ( ! $found ) {
			return false;
		}

		$short_code = $this->build_short_code( "minicart", $matches[1], array( 'layout' => $matches[2] ) );

		return preg_replace( $found, $short_code, $input, 1 );
	}

	protected function replace_generic( $input, $function, $widget ) {
		$matches = array();

		$expressions = array(
			'#<div>\s*<script type=\\\\"text/javascript\\\\" src=\\\\"http://app.ecwid.com/script.js\?([0-9]*)\\\\"[^>]*>\s*</script>\s*<script type=\\\\"text/javascript\\\\"> ' . $function . '[^<]*</script>\s*</div>#s',
			'#&lt;div&gt;\s*&lt;script type=\\\\"text/javascript\\\\" src=\\\\"http://app.ecwid.com/script.js\?([0-9]*)\\\\"[^&]*&gt;\s*&lt;/script&gt;\s*&lt;script type=\\\\"text/javascript\\\\"&gt; ' . $function . '[^&]*&lt;/script&gt;\s*&lt;/div&gt;#s'
		);
		$found = false;

		foreach($expressions as $expression) {
			if ( preg_match( $expression, $input, $matches ) ) {
				$found = $expression;
				break;
			}
		}

		if ( ! $found ) return false;

		$short_code = $this->build_short_code( $widget, $matches[1], array() );

		return preg_replace( $found, $short_code, $input, 1 );
	}

}
