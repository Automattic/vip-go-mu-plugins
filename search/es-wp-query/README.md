<div><a href="https://travis-ci.org/alleyinteractive/es-wp-query"><img align="right" src="https://travis-ci.org/alleyinteractive/es-wp-query.svg?branch=master" /></a></div>

# Elasticsearch Wrapper for WP_Query

A drop-in replacement for WP_Query to leverage Elasticsearch for complex queries.

## Warning!

This plugin is currently in beta development, and as such, no part of it is guaranteed. It works (the unit tests prove that), but we won't be concerned about backwards compatibility until the first release. If you choose to use this, please pay close attention to the commit log to make sure we don't break anything you've implemented.


## Instructions for use

This is actually more of a library than it is a plugin. With that, it is plugin-agnostic with regards to how you're connecting to Elasticsearch. It therefore generates Elasticsearch DSL, but does not actually connect to an Elasticsearch server to execute these queries. It also does no indexing of data, it doesn't add a mapping, etc. If you need an Elasticsearch WordPress plugin, we also offer a free and open-source option called [SearchPress](https://github.com/alleyinteractive/searchpress).

Once you have your Elasticsearch plugin setup and you have your data indexed, you need to tell this library how to use it. If the implementation you're using has an included adapter, you can load it like so:

	es_wp_query_load_adapter( 'adapter-name' );


If your Elasticsearch implementation doesn't have an included adapter, you need to create a class called `ES_WP_Query` which extends `ES_WP_Query_Wrapper`. That class should, at the least, have a method `query_es()` which executes the query on the Elasticsearch server. Here's an example:

	class ES_WP_Query extends ES_WP_Query_Wrapper {
		protected function query_es( $es_args ) {
			return wp_remote_post( 'http://localhost:9200/wordpress/post/_search', array( 'body' => json_encode( $es_args ) ) );
		}
	}

See the [included adapters](https://github.com/alleyinteractive/es-wp-query/tree/master/adapters) for examples and inspiration.


Once you have an adapter setup, there are two ways you can use this library.

The first, and preferred, way to use this library is to instantiate `ES_WP_Query` instead of `WP_Query`. For instance:

	$q = new ES_WP_Query( array( 'post_type' => 'event', 'posts_per_page' => 20 ) );

This will guarantee that your query will be run using Elasticsearch (assuming that the request can and should use Elasticsearch) and you should have no conflicts with themes or plugins. The resulting object (`$q` in this example) works just like WP_Query outside of how it gets the posts.

The second way to use this library is to add `'es' => true` to your WP_Query arguments. Here's an example:

	$q = new WP_Query( array( 'post_type' => 'event', 'posts_per_page' => 20, 'es' => true ) );

In one regard, this is a safer way to use this library, because it will fall back on good 'ole `WP_Query` if the library ever goes missing. However, because it depends on the normal processing of WP_Query, it's possible for a plugin or theme to create conflicts, where that plugin or theme is trying to modify WP_Query through one of its provided filters (see below for additional details). In that regard, this can be a very unsafe way to use this library.

Regardless of which way you use the library, everything else about the object should work as per usual.

## Differences with WP_Query and Unsupported Features

### Meta Queries

* **Regexp comparisons are not supported.** The regular expression syntax is slightly different in Elasticsearch vs. PHP, so even if we tried to support them, it would result in a lot of unexpected behaviors. Furthermore, regular expressions are very resource-intensive in Elasticsearch, so you're probably better off just using WP_Query for these queries regardless.
	* If you try to use a regexp query, ES_WP_Query will throw a `_doing_it_wrong()` notice.
* **LIKE comparisons are incongruous with MySQL.** In ES_WP_Query, LIKE-comparison meta queries will run a `match` query against the analyzed meta values. This will behave similar to a keyword search and will generally be more useful than a LIKE query in MySQL. However, there are notably differences with the MySQL implementation and ES_WP_Query will very likely produce different search results, so don't expect it to be a drop-in replacement.


## A note about WP_Query filters

Since this library removes MySQL from most of the equation, the typical WP_Query filters (`posts_where`, `posts_join`, etc.) become irrelevant or -- in some extreme situations -- conflicting.

The gist of what happens whn you use `WP_Query( 'es=true' )` is that on `pre_get_posts`, the query vars are sent to a new instance of `ES_WP_Query`. The query vars are then replaced with a simple `post__in` query using the IDs which Elasticsearch found. Because the generated SQL query is far simpler than the query vars would suggest, a plugin or theme might try to manipualte the SQL and break it.

| Action/Filter              | Using `ES_WP_Query` | `ES_WP_Query` Equivalent                            | Using `WP_Query` with `'es' => true` |
| -------------------------- | ------------------- | --------------------------------------------------- | ------------------------------------ |
| `pre_get_posts`            | No issues           | `es_pre_get_posts`                                  | Potential conflicts                  |
| `posts_search`             | N/A                 | `es_posts_search`                                   | Should be N/A                        |
| `posts_search_orderby`     | N/A                 | `es_posts_search_orderby`                           | Should be N/A                        |
| `posts_where`              | N/A                 | `es_query_filter`                                   | Potential conflicts                  |
| `posts_join`               | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_join`        | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_where`       | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_groupby`     | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_orderby`     | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_limits`      | N/A                 |                                                     | Potential conflicts                  |
| `posts_where_paged`        | N/A                 | `es_posts_filter_paged`, `es_posts_query_paged`     | Potential conflicts                  |
| `posts_groupby`            | N/A                 |                                                     | Potential conflicts                  |
| `posts_join_paged`         | N/A                 |                                                     | Potential conflicts                  |
| `posts_orderby`            | N/A                 | `es_posts_sort`                                     | Potential conflicts                  |
| `posts_distinct`           | N/A                 |                                                     | Potential conflicts                  |
| `post_limits`              | N/A                 | `es_posts_size`, `es_posts_from`                    | Potential conflicts                  |
| `posts_fields`             | N/A                 | `es_posts_fields`                                   | No issues                            |
| `posts_clauses`            | N/A                 | `es_posts_clauses`                                  | Potential conflicts                  |
| `posts_selection`          | N/A                 | `es_posts_selection`                                | Potential conflicts                  |
| `posts_where_request`      | N/A                 | `es_posts_filter_request`, `es_posts_query_request` | Potential conflicts                  |
| `posts_groupby_request`    | N/A                 |                                                     | Potential conflicts                  |
| `posts_join_request`       | N/A                 |                                                     | Potential conflicts                  |
| `posts_orderby_request`    | N/A                 | `es_posts_sort_request`                             | Potential conflicts                  |
| `posts_distinct_request`   | N/A                 |                                                     | Potential conflicts                  |
| `posts_fields_request`     | N/A                 | `es_posts_fields_request`                           | No issues                            |
| `post_limits_request`      | N/A                 | `es_posts_size_request`, `es_posts_from_request`    | Potential conflicts                  |
| `posts_clauses_request`    | N/A                 | `es_posts_clauses_request`                          | Potential conflicts                  |
| `posts_request`            | N/A                 | `es_posts_request`                                  | Potential conflicts                  |
| `split_the_query`          | N/A                 |                                                     | Potential conflicts                  |
| `posts_request_ids`        | N/A                 |                                                     | Potential conflicts                  |
| `posts_results`            | N/A                 | `es_posts_results`                                  | No issues                            |
| `comment_feed_join`        | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_where`       | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_groupby`     | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_orderby`     | N/A                 |                                                     | Potential conflicts                  |
| `comment_feed_limits`      | N/A                 |                                                     | Potential conflicts                  |
| `the_preview`              | N/A                 | `es_the_preview`                                    | Potential conflicts                  |
| `the_posts`                | N/A                 | `es_the_posts`                                      | No issues                            |
| `found_posts_query`        | N/A                 |                                                     | Potential conflicts                  |
| `found_posts`              | N/A                 | `es_found_posts`                                    | Potential conflicts                  |
| `wp_search_stopwords`      | N/A                 |                                                     | N/A                                  |
| `get_meta_sql`             | N/A                 | `get_meta_dsl`                                      | N/A                                  |
| `date_query_valid_columns` | No issues           |                                                     | No issues                            |
| `get_date_sql`             | N/A                 | `get_date_dsl`                                      | N/A                                  |

Note that in the "Using `WP_Query` with `'es' => true`" column, "no issues" and "N/A" are not guaranteed. For instance, in almost every filter, the `WP_Query` object is passed by reference. If a plugin or theme modified that object, it could create a conflict. The "no issues" and "N/A" notes assume that filters are being used as intended. Lastly, everything is dependant on `pre_get_posts`. If a plugin or theme were to hook in at a priority > 1000, it could render everything a potential conflict.

## Contributing

Any help on this plugin is welcome and appreciated!

### Bugs

If you find a bug, [check the current issues](https://github.com/alleyinteractive/es-wp-query/issues) and if your bug isn't listed, [file a new one](https://github.com/alleyinteractive/es-wp-query/issues/new). If you'd like to also fix the bug you found, please indicate that in the issue before working on it (just in case we have other plans which might affect that bug, we don't want you to waste any time).

### Feature Requests

The scope of this plugin is very tight; it should cover as much of WP_Query as possible, and nothing more. If you think this is missing something within that scope, or you think some part of it can be improved, [we'd love to hear about it](https://github.com/alleyinteractive/es-wp-query/issues/new)!


## Unit Tests

Unit tests are included using phpunit. In order to run the tests, you need to add an adapter for your Elasticsearch implementation.

1. You need to create a file called `es.php` and add it to the `tests/` directory.
2. `es.php` can simply load one of the included adapters which is setup for testing. Otherwise, you'll need to do some additional setup.
3. If you're not using one of the provided adapters:
	* `es.php` needs to contain or include a function named `es_wp_query_index_test_data()`. This function gets called whenever data is added, to give you an opportunity to index it. You should force Elasticsearch to refresh after indexing, to ensure that the data is immediately searchable.
		* **NOTE: Even with refreshing, I've noticed that probably <0.1% of the time, a test may fail for no reason, and I think this is related. If a test sporadically and unexpectedly fails for you, you should re-run it to double-check.**
	* `es.php` must also contain or include a class `ES_WP_Query` which extends `ES_WP_Query_Wrapper`. At a minimum, this class should contain a `protected function query_es( $es_args )` which queries your Elasticsearch server.
	* This file can also contain anything else you need to get everything working properly, e.g. adjustments to the field map.
	* See the included adapters, especially `travis.php`, for examples.

