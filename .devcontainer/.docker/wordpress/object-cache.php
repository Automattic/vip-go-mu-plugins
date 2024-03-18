<?php

if ( file_exists( __DIR__ . '/mu-plugins/drop-ins/object-cache.php' ) ) {
	require_once __DIR__ . '/mu-plugins/drop-ins/object-cache.php';
}

// We are not loading `../wp-includes/cache.php` as a fallback because `wp_start_object_cache()` does that for us.
// If we load it here, `wp_using_ext_object_cache()` will return `true`; this may cause unwated side effects.
// It is, however, still possible that `wp_using_ext_object_cache()` returns `true`; that happens if `advanced-cache.php`
// loads `object-cache.php` before `wp_start_object_cache()` is called. However, this must not happen in a VIP environment.
