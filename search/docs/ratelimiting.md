# Ratelimiting

## Overview <a name="overview"></a>

Ratelimiting for VIP Search was implemented to help mitigate the effect any individual site can have on the other users. These mitigations put limit on the raw amounts of operations that can be performed in a specific period of time.

## Query Ratelimiting <a name="query-ratelimiting"></a>

Query ratelimiting limits the amount of queries that can be passed through VIP Search in a specific period. Once this limit is surpassed, half of the queries from that site are passed to the database while the other half continue on to Elasticsearch. 

A query in this context can mean either a search request or an offloaded WordPress request(posts, user, taxonomy, etc...). It does not include indexing requests.

The specifics of the query ratelimiting are implemented in [Automattic/vip-go-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins). Specifically in [search/includes/classes/class-search.php](https://github.com/Automattic/vip-go-mu-plugins/blob/master/search/includes/classes/class-search.php). The limit for a period is set by `public static $max_query_count` and the period is set by `private const QUERY_COUNT_TTL`. Those values will be the same as those used in productions and should be referenced if you need to know what their value.

Those variables are also useful for testing. Setting `public static $max_query_count` to 1 allows you to easily trigger the query rate limiting for testing that functionality.

Some signs that a site currently has its queries ratelimited are:

- Looking at the ElasticPress debug bar/query monitor panel while refreshing an offloaded query operation shows intermittent activity. Some hit Elasticsearch, some don't.
- A site that depends on the query offloading for performance reasons is really slow to load or fails to load in some cases while in others it works as per normal.

## Index Ratelimiting <a name="index-ratelimiting"></a>

Index ratelimiting limits the amount of indexing operations that can occur synchronously in a specific period. Once this limit is surpassed, all indexing operations are added to a queue and are processed asynchronously for a period of time. The idea is to spread a spike out over a longer period of time so the increase in load doesn't affect other sites in a noticeable way and to reduce the amount of document merges that Elasticsearch will have to perform. The queue only allows one re-indexing of each object, so this can turn 30 indexing operations in a couple minutes into just one.

An indexing operation is any operation that triggers the creation, deletion, or modification of a document in Elasticsearch. This can be roughly assigned to creating, deleting, or modifying posts, users, taxonomy, etc.... 

The specifics of index ratelimiting are implemented in [Automattic/vip-go-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins). Specifically in [search/includes/classes/class-queue.php)](https://github.com/Automattic/vip-go-mu-plugins/blob/master/search/includes/classes/class-queue.php). The limit for a period is set by `public static $max_indexing_op_count`, the period is set by `private const INDEX_COUNT_TTL`, and the length of time all queries will be queued up for processing asynchronously is set by `private const INDEX_QUEUEING_TTL`.

Editing a taxonomy with a lot of posts can also trigger this functionality without triggering rate limiting. This is more for user experience and preventling pages from hanging. The limit for this sort of operation is set by `private const MAX_SYNC_INDEXING_COUNT`, which means editing a taxonomy with more posts than is set there will result in all the posts for the taxonomy being queued for eventual reindexing.

These variables are useful for testing. Setting `public static $max_indexing_op_count` to 0 means all posts are always queued before being reindexed. So doing that and running `wp vip-search index` is a great way to test the queueing and sweeping functionality.

Some signs that a site currently has its index operations ratelimited are:

- Changes don't apply right away. They seem to happen after a few minute delay.
