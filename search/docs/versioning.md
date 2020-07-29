# Versioning

VIP Search supports versioned indexes, which allows new indexes to be built alongside the current index to prevent downtime during bulk indexing. 

Note - Bulk indexing by default does not drop the index, but occasionally it is necessary to remake the index (such as when certain settings are changed). This is done with `wp vip-search index --setup`

## Naming Convention <a name="naming-convention"></a>

Index numbers begins at 1, and the first index name does not have a version number appended. Index versions 2 and higher follow this format:

```
vip-<app-id>-<indexable_slug>-<subsite_id>-v<version_number>
```

Example:

```
vip-123-post-1-v2
```

## Active vs. Current <a name="active-vs-current"></a>

There are two ways to use a specific index version. 

The *active* index is the one that is serving requests for site visitors. It is the default index version in use.

The *current* index is an override that specifies which index will be used for the next request. This is useful to send certain requests to a different index version without affecting visitors, such as when indexing or testing a new index version.

Setting the *current* index is not permanent and can be done with `\Automattic\VIP\Search\Search::instance()->versioning->set_current_version_number( $indexable, $number )`.

To switch back to the current active index, just call the reset function: `\Automattic\VIP\Search\Search::instance()->versioning->resut_current_version_number( $indexable )`.

## Keeping Additional Versions in Sync <a name="in-sync"></a>

Having additional index versions is only useful if their contents do not drift from the active index.

VIP Search automatically replicates any indexing (or delete) operations to the non-active indexes to keep them in sync. For regular indexing, this is done by queueing a new job in the queue and for deletes, the documents are deleted right away (because the queue does not currently support deletes).

## Commands <a name="commands"></a>

Index versions can be managed with the following commands. For more details, see `wp help vip-search index-versions`.

```
wp vip-search index-versions list <type>
```

List the available index versions.

```
wp vip-search index-versions add <type>
```

Add a new index version. This does not actually create the ES index, but registers the new version with VIP Search so that the new ES index can be created.

```
wp vip-search index-versions get <type> <version_number>
```

Retrieve details of a specific index version.

```
wp vip-search index-versions activate <type> <version_number>
```

Make the given index version active. The active index is the one that serves site traffic, so be sure the target index is ready for production before switching.

```
wp vip-search index-versions get-active <type>
```

Get the currently active index version.

```
wp vip-search index --indexables=<type> --version=<version_number>
```

Build a new index version alongside the currently active version. The version number must have already been registered with `wp vip-search index-versions add <type>`.

Once the index is built, it can be made active with `wp vip-search index-versions activate <type> <version_number>`

## Internals <a name="internals"></a>

Internally, versions are stored in a WP option (a site option on multisites in network-mode), `vip_search_index_versions`. This option tracks known indexes and their details, such as if it is active, when it was created, and when it was activated.

Only indexes that have been registered in this option are available to use with VIP Search.

The version number is appended to the index name at runtime inside the `ep_index_name` filter.
