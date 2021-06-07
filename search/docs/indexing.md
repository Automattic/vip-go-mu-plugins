# Indexing

Indexing is the process by which data is compiled and inserted into Elasticsearch.

## What does VIP Search index? <a name='what-does-vip-search-index'></a>

VIP Search doesn't index everything. Currently VIP Search is mostly focused around posts. Other indexables/features available in the underlying ElasticPress 3.4.2  may be enabled, but they aren't yet tested or guaranteed to work.

Only WordPress events trigger the underlying ElasticPress plugin hooks that cause the index to update. Directly modifying the database or editing data through means that aren't handled by these hooks will not change the Elasticsearch index. For example, database imports will not change the Elasticsearch index.

Editing most pieces of post data will result in indexing. The exceptions are through the various allow lists in both ElasticPress and VIP Search. A very prominent example is the `vip_search_post_meta_allow_list` filter which controls which post meta are indexed. If it's empty, no post meta is indexed.

For a full list of hooks for each indexable, see the indexables SyncManager::setUp function(e.g.: [includes/classes/Indexable/Post/SyncManager.php](https://github.com/Automattic/ElasticPress/blob/master/includes/classes/Indexable/Post/SyncManager.php) for posts).

For a full list of allow lists, see [Filters](filters-actions.md) and search for combinations of `allow list`, `white list`, and `black list`.

## A reindex is NOT a sync!! <a name='reindex-is-not-sync'></a>

If you modify the database outside of normal WordPress functionality(e.g: create a post, edit a post, etc...), running `wp vip-search index` may not bring the database and Elasticsearch index back into sync. 

The only way to bring the database and index back into sync is to create a fresh index.

Currently, the only supported way to create a fresh index is to drop the current index and then index all content again. `wp vip-search index --setup` is the easiest way to make that happen.

In the very near future, index versionng will be the official way to handle these scenarios. Exact details TBD.
