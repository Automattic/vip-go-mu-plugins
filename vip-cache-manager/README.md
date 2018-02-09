# Cache Manager #
**Contributors:** automattic, quasistar
**Tags:** cache, varnish, manager, purge, async  
**Requires PHP:** 7.0
**Stable tag:** 1.1
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Provides interfaces, including REST API, to purge resources from Varnish cache.

## Description ##

Allows purges and bans on Varnish page caches.
Can to be hooked on GitHub commits to either purge or ban all pushed assets.

Exposes methods whose use is detailed here: https://vip.wordpress.com/documentation/vip-go/controlling-vip-go-page-cache/#clearing-caches-for-post-term-or-a-specific-url .

Also provides two REST route for purges: cache-manager/v1/purge.

## Installation ##

1. Define `WP_CACHE_MANAGER_SECRET` in `wp-config.php`
2. Upload the `vip-cache-manager` directory to the `/wp-content/mu-plugins/` directory

## REST API usage ##

### URL list as JSON parameters ###

REST endpoint for purges requires passing an array of URLs to be purged as JSON data along with each POST request.
Example:

```json
{ "urls":
  [
    "http://www.example.org/someasset.css",
    "https://www.example.org/subdir/someotherasset.js"
  ]
}
```

### Authentication ###

Authentication is managed by `wpcom_vip_go_rest_api_request_allowed()`; it will check for valid authentication headers on request.

## Frequently Asked Questions ##

### Why is PHP 7 required? ###

To be able to catch fatal errors triggered by event callbacks, and define arrays in constants (such as for adding "Internal Events"), PHP 7 is necessary.
