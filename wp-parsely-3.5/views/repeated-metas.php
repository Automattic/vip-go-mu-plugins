<?php
/**
 * Views: Parse.ly repeated metas output
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely;

foreach ( $parsely_metas as $parsely_meta_key => $parsely_meta_val ) {
	printf(
		'<meta name="%s" content="%s" />%s',
		esc_attr( 'parsely-' . $parsely_meta_key ),
		esc_attr( $parsely_meta_val ),
		"\n"
	);
}

if ( isset( $parsely_page_authors ) ) {
	foreach ( $parsely_page_authors as $parsely_author_name ) {
		printf(
			'<meta name="parsely-author" content="%s" />%s',
			esc_attr( $parsely_author_name ),
			"\n"
		);
	}
}
