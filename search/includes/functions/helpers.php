<?php
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
namespace Automattic\VIP\Search;

// source: Laravel Framework
// https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/Str.php

function str_starts_with( $haystack, $needle ) {
	return (string) $needle !== '' && strncmp( $haystack, $needle, strlen( $needle ) ) === 0;
}

function str_ends_with( $haystack, $needle ) {
	return $needle !== '' && substr( $haystack, -strlen( $needle ) ) === (string) $needle;
}

function str_contains( $haystack, $needle ) {
	return $needle !== '' && mb_strpos( $haystack, $needle ) !== false;
}
