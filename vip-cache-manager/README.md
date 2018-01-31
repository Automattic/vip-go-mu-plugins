# Cache Manager #
**Contributors:** automattic, quasistar
**Tags:** cache, varnish, manager, purge, async  
**Requires PHP:** 7.0
**Stable tag:** 1.1
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Provides interfaces, including REST API, to purge resources from Varnish cache.

## Description ##

Allows purges and bans on Varnish page caches. Needs to be hooked on GitHub commits.
On each commit it will then either purge or ban all pushed assets.

Exposed methods whose use is detailed here: https://vip.wordpress.com/documentation/vip-go/controlling-vip-go-page-cache/#clearing-caches-for-post-term-or-a-specific-url .

Also provides two REST routes for purges and bans: cache-manager/v1/purge and cache-manager/v1/ban, respectively.

## Installation ##

1. Define `WP_CACHE_MANAGER_SECRET` in `wp-config.php`
2. Upload the `vip-cache-manager` directory to the `/wp-content/mu-plugins/` directory

## Frequently Asked Questions ##

### Why is PHP 7 required? ###

To be able to catch fatal errors triggered by event callbacks, and define arrays in constants (such as for adding "Internal Events"), PHP 7 is necessary.
