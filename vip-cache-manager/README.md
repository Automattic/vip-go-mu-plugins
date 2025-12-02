# VIP Cache Manager

The VIP Cache Manager plugin provides tools for managing the edge cache on WordPress VIP. It automatically handles cache invalidation when content changes and provides manual controls for clearing specific cache groups.

Read more about [WordPress VIP cache architecture](https://docs.wpvip.com/cache/) in our documentation.

## Dashboard Widget

The **Edge Cache Controls** widget is available on the WordPress Dashboard for users with the `manage_options` capability. It provides granular controls for clearing different cache groups:

| Action | Description |
|--------|-------------|
| **Purge entire site cache** | Clear all origin responses, static files, uploads, and private file visibility metadata. Use sparingly as it can impact site performance. |
| **Purge WordPress responses** | Clear cached WordPress responses without touching uploads or static files. |
| **Purge uploads** | Clear cached VIP File Service uploads. |
| **Purge static files** | Clear cached static assets such as theme and core CSS, JS, and images. |
| **Reset private file visibility** | Clear cached visibility information for private files. |

> ⚠️ **Warning**: Clearing the edge cache will result in a temporary increase in load on your origin servers and may impact your site's performance. Use these controls cautiously, especially on high-traffic sites.

## API Functions

The following functions are available for programmatic cache purging:

### `wpvip_purge_edge_cache_for_url( $url )`

Purge the cache for a specific URL.

**Parameters:**
- `$url` (string) – The URL to purge.

**Returns:** `bool` – True on success.

```php
wpvip_purge_edge_cache_for_url( 'https://example.com/my-page/' );
```

### `wpvip_purge_edge_cache_for_post( $post )`

Purge the cache for a post and its associated URLs (homepage, feeds, term archives, etc.).

**Parameters:**
- `$post` (int|WP_Post) – Post ID or WP_Post object.

**Returns:** `bool` – True on success.

```php
wpvip_purge_edge_cache_for_post( $post_id );
```

### `wpvip_purge_edge_cache_for_term( $term )`

Purge the cache for a term and its archive pages.

**Parameters:**
- `$term` (int|WP_Term) – Term ID or WP_Term object.

**Returns:** `bool` – True on success.

```php
wpvip_purge_edge_cache_for_term( $term_id );
```

### `wpvip_purge_edge_cache_for_site()`

Purge the entire site cache, including all origin responses, static files, uploads, and private file visibility metadata.

**Returns:** `bool` – True when the purge was queued.

```php
wpvip_purge_edge_cache_for_site();
```

### `wpvip_purge_edge_cache_for_origin_content()`

Purge cached content that originated from origin servers (WordPress responses) without affecting uploads or static files.

**Returns:** `bool` – True when the purge was queued.

```php
wpvip_purge_edge_cache_for_origin_content();
```

### `wpvip_purge_edge_cache_for_uploads()`

Purge cached uploads/media objects from the VIP File Service.

**Returns:** `bool` – True when the purge was queued.

```php
wpvip_purge_edge_cache_for_uploads();
```

### `wpvip_purge_edge_cache_for_static_files()`

Purge cached static assets (CSS, JS, images).

**Returns:** `bool` – True when the purge was queued.

```php
wpvip_purge_edge_cache_for_static_files();
```

### `wpvip_purge_edge_cache_for_private_files()`

Purge cached visibility metadata for private files.

**Returns:** `bool` – True when the purge was queued.

```php
wpvip_purge_edge_cache_for_private_files();
```

## WP-CLI Commands

Cache purge operations are also available via WP-CLI:

### Purge by scope

```bash
wp vip cache purge [--scope=<scope>] [--skip-confirm]
```

**Options:**
- `--scope=<scope>` – Limit the purge to a specific cache scope. Default: `site`
  - `site` – Purge entire site cache
  - `origin` – Purge origin content cache only
  - `uploads` – Purge uploads cache only
  - `static` – Purge static files cache only
  - `private` – Purge private file visibility cache only
- `--skip-confirm` – Skip confirmation prompt (site scope only)

**Examples:**

```bash
# Purge entire site cache (with confirmation)
wp vip cache purge

# Purge entire site cache (without confirmation)
wp vip cache purge --skip-confirm

# Purge only origin content
wp vip cache purge --scope=origin

# Purge only uploads
wp vip cache purge --scope=uploads

# Purge only static files
wp vip cache purge --scope=static

# Purge private file visibility
wp vip cache purge --scope=private
```

### Purge a specific URL

```bash
wp vip cache purge-url <url>
```

**Example:**

```bash
wp vip cache purge-url https://example.com/my-page/
```

## Admin Bar

A **Purge Page Cache** button is available in the WordPress admin bar on the front-end for users with the `manage_options` capability. This allows quick purging of the current page and its assets without navigating to the dashboard.

## Filters

### `vip_cache_manager_can_purge_cache`

Control which users can access cache purge functionality.

**Parameters:**
- `$can_purge` (bool) – Whether the user can purge cache. Default is `current_user_can( 'manage_options' )`.
- `$user` (WP_User) – The current user object.

**Returns:** `bool` – Whether the user can purge cache.

```php
add_filter( 'vip_cache_manager_can_purge_cache', function( $can_purge, $user ) {
    // Allow editors to purge cache
    if ( $user->has_cap( 'edit_others_posts' ) ) {
        return true;
    }
    return $can_purge;
}, 10, 2 );
```

### `wpcom_vip_cache_purge_urls`

Add additional URLs to be purged when a post is updated.

**Parameters:**
- `$urls` (array) – Array of URLs to purge.
- `$post_id` (int) – The post ID being purged.

**Returns:** `array` – Modified array of URLs.

```php
add_filter( 'wpcom_vip_cache_purge_urls', function( $urls, $post_id ) {
    // Add custom URL to purge list
    $urls[] = home_url( '/custom-page/' );
    return $urls;
}, 10, 2 );
```

## Deprecated Functions

The following functions are deprecated and will trigger a deprecation notice. Please update to the new function names:

| Deprecated | Replacement |
|------------|-------------|
| `wpcom_vip_purge_edge_cache_for_url()` | `wpvip_purge_edge_cache_for_url()` |
| `wpcom_vip_purge_edge_cache_for_post()` | `wpvip_purge_edge_cache_for_post()` |
| `wpcom_vip_purge_edge_cache_for_term()` | `wpvip_purge_edge_cache_for_term()` |
| `wpcom_vip_purge_edge_cache_for_site()` | `wpvip_purge_edge_cache_for_site()` |
| `wpcom_vip_purge_edge_cache_for_origin_content()` | `wpvip_purge_edge_cache_for_origin_content()` |
| `wpcom_vip_purge_edge_cache_for_uploads()` | `wpvip_purge_edge_cache_for_uploads()` |
| `wpcom_vip_purge_edge_cache_for_static_files()` | `wpvip_purge_edge_cache_for_static_files()` |
| `wpcom_vip_purge_edge_cache_for_private_files()` | `wpvip_purge_edge_cache_for_private_files()` |
