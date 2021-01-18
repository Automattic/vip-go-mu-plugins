# Onboarding/Migrations

## Considerations <a name="considerations"></a>

When migrating or onboarding new sites there are a number of considerations that need to be made to make the process run smoothly:

1. What post meta are being used for queries?
    - During onboarding of certain sites, it became apparent that some sites may have enough post meta to exceed the max field count in Elasticsearch and cause issues. The solution was to institute an allow list for clients to use.
    - This functionality can also be bypassed on a site-by-site basis via `Search::DISABLE_POST_META_ALLOW_LIST`. This should be avoided or used as a stopgap measure toward getting a proper allow list in place ASAP.
    - Important since we do have a post meta allow list filter(`vip_search_post_meta_allow_list`) for indexing. **It overrides `ep_prepare_meta_allowed_protected_keys` with its value.**
    - Without adding anything to the post meta allow list, no post meta will be indexed and all Elasticsearch queries referencing that post meta will fail
    - Some ways issues with the post meta allow list may manifest are:
        - Queries that used to work prior to migration/onboarding no longer work despite the index being consistent
        - Missing parts of results while similar database queries work fine and the index is consistent
2. What taxonomy are being used for queries?
    - During onboarding of certain sites, it became apparent that some sites make use of private taxonomy in their queries. This was problematic since ElasticPress ignore private taxonomy while indexing. The solution is to use an allow list of private taxonomy to use.
    - Important since we are going to add an allow list filter(`vip_search_post_taxonomies_allow_list`) for indexing private taxonomy that may be needed for queries.
        - This filter takes the current array of taxonomy names to index and the post, and should return the new array of taxonomy names to index
    - There is no bypass for this functionality.
    - Without adding anything to the private taxonomy allow list, no private taxonomy will be indexed and all Elasticsearch queries referencing that private taxonomy will fail.
    - Some ways issues with the private taxonomy allow list will manifest are:
        - Queries that used to work prior to migration/onboarding no longer work despite the index being consistent
        - Missing parts of results while similar database queries work fine and the index is consistent
    - Some additional considerations:
        - Are these private taxonomy okay to index and use in results?
            - If so, they must be added to the filter.
            - If not, the clients code will need to be reworked by them in order for it to work without those private taxonomy.

**Note:** The value of `vip_search_post_meta_allow_list` overrides the value of `ep_prepare_meta_allowed_protected_keys`. If `vip_search_post_meta_allow_list` is empty, `ep_prepare_meta_allowed_protected_keys` is empty.

## Index Field Count <a name='index-field-count'></a>

Index field count is one of the major considerations in onboarding a site onto VIP Search. Elasticsearch limits how many unique fields an index can have. To this end, there is a limit set by the `ep_total_field_limit` filter. The default for Elasticsearch is 1000 total fields while VIP Search has it's default set at 5000 with an upper limit of 20000. 

While each object has it's own default fields it indexes, there are also meta and taxonomy considerations that must be made. Each meta and taxonomy field takes up 11 and 9 fields respectively due to type casting and other behavior in the underlying ElasticPress functionality. This means that fields can be "used up" quickly. 

Since the only taxonomy or meta that actually needs to be indexed are those that are used for querying, an allow list system was added to mitigate this issue.

Currently, we only apply allow list functionality to posts.

## Post Meta Allow List <a name='post-meta-allow-list'></a>

The post meta allow list is an allow list for what post meta may be indexed. If this is empty, no post meta will be indexed. It is incredibly important that any post meta that is used in queries be indexed or those queries won't get any hits when querying against VIP Search.

The filter for setting the post meta allow list is `vip_search_post_meta_allow_list`. Its format is either an array of strings(`array( 'meta-one', 'meta-two' )`) or an associative array(`array( 'meta-one' => true, 'meta-two' => true)`). Setting the associative array values to false makes them not be used for the post meta allow list.

## Post Taxonomy Allow List <a name='post-taxonomy-allow-list'></a>

The post taxonomy allow list is an allow list for what taxonomy may be indexed for a post. If this is empty, no taxonomy will be indexed for the post. The default values for the post taxonomy allow list is all public taxonomy from the result of `get_object_taxonomies` when called on the posts post type. This is a default inheirited from ElasticPress.

The filter for setting the post taxonomy allow list is `vip_search_post_taxonomies_allow_list`. Its format is an array of taxonomy objects.


## How to enable VIP Search? <a name='https://github.com/Automattic/vip-docs/pull/39'></a>

1. Ensure that an Elasticsearch user has been provisioned for the corresponding site. When in doubt or if no user had been provisioned yet, then please <a href="mailto:vip-support@wordpress.com">contact our VIP Support</a>.
1. Add two new constants to `wp-config.php` or other analog:
	1. `define( 'VIP_ENABLE_VIP_SEARCH', true );`
	1. `define( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION', true );`
    1. If you are indexing a multisite, also add `define( 'EP_IS_NETWORK', true );`
1. Disable and remove all other Elasticsearch-backed functionality. VIP Search uses forks of `es-wp-query` and `ElasticPress` that are loaded automatically. No other Elasticsearch backed functionality is supported while VIP Search is enabled.
1. Commit/deploy those changes if applicable.
1. Test it to make sure everything works.
1. Run a re-index to make sure everything is in sync. You may need to drop your index and then re-index if you notice any irregularities at this point. Performed by running `wp vip-search index --setup`. If you are indexing a multisite, add `--network-wide`. **This drops your index and makes it inaccessible to VIP Search while this process takes place**. If the index is currently in use and serving production data, [index versioning](versioning.md) should be used so a new index can be created and then swapped to rather than destroying the index currently in use and causing downtime.

If anything goes wrong, you can just remove the constants and re-enable/replace the plugins and settings you had in place previously.
