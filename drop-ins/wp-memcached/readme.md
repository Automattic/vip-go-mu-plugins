# WP Memcached

CAUTION: This project is a work in progress.

This is a fork of https://github.com/Automattic/wp-memcached, but may end up merging back upstream eventually.

- Adds support for the Memcached PHP extension
- General cleanup, along with types enforcement with Psalm.
- More unit testing, for both Memcache and Memcached extensions.

## Usage

1. Install this plugin somewhere in your codebase.
2. Create a file at `wp-content/object-cache.php`, with the contents being just `require_once DIR . '/path/to/wp-cache-memcached/object-cache.php`.

This plugin aims to have full compatability with the wp-memcached plugin, and you can do those first two steps and call it a day. It will work seemlessly as a replacement. Will even keep using the same cache keys/values that are already stored.

To additionally start using the Memcached php extension instead:

1. Ensure the Memcached extension is installed, along with the libmemcached library.
2. Add `define( 'AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION', true );` to your wp-config.php file.

This will result in effectively a cache flush, after which behavior will resume per usual (though hopefully with better performance and consistency).

## Development

```
composer install

# Linting / Code standards
composer run-script phpcs
composer run-script phpcs:fix

# Type checking
composer run-script psalm

# Unit tests
./bin/test.sh
```
