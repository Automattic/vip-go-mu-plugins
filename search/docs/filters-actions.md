# Filters and Actions

Filters and actions are generally the same as ElasticPress as of version 3.4.2. There have been some additions made to ElasticPress in Automattic/ElasticPress to facilitate different functionality that VIP Search offers.

In addition, there are some VIP Search specific filters. Only these will be expanded upon, all the others will be listed and looking them up will be an exercise left to the reader.

All relevant code can be found in [Automattic/vip-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins) and [Automattic/ElasticPress](https://github.com/Automattic/ElasticPress)

## Filters <a name="filters"></a>

Filters are functions that WordPress uses to pass data through. They operate like a queue ordered by priority where the higher the priority the later it runs at the points where `apply_filters` is called for a particular hook. Effectively, the last applied filter gets to decide the value of that `apply_filters` call.

### VIP Search <a name="vip-search-filters"></a>

These a filters exclusively in VIP Search. They are all prefixed by `vip_search`.

#### vip_search_healthchecks_enabled_environments

|                   |                                                                                                                   |
|-------------------|-------------------------------------------------------------------------------------------------------------------|
| `$environments`   | An array containing the list of environments to enabled VIP Search health checks for. Defaults to `production`    |
| `Returns`         | An array containing the list of environments to enabled VIP Search health checks for.                             |
 ---------------------------------------------------------------------------------------------------------------------------------------

The purpose of this filter is to control which environments VIP Search health checks should be running on.

#### vip_search_post_taxonomies_allow_list
|                   |                                                                                                   |
|-------------------|---------------------------------------------------------------------------------------------------|
| `$taxonomy_names` | An array of taxonomy names. Defaults to value from `ep_sync_taxonomies` ElasticPress filter       |
| `$post`           | A `WP_POST` object                                                                                |
| `Returns`         | An array of `WP_Term` objects.                                                                    |
 -----------------------------------------------------------------------------------------------------------------------

The purpose of this filter is to allow the controlling of which terms are indexed for a post. Adding or removing taxonomy names from this filter changes the array of `WP_Term` objects that are passed to the `ep_sync_taxonomies` filter.

#### vip_search_post_meta_allow_list

|                           |                                                                                                           |
|---------------------------|-----------------------------------------------------------------------------------------------------------|
| `$post_meta_allow_list`   | An array of post meta keys or an associative array where the key is the meta key and the value is true    |
| `$post`                   | A `WP_POST` object                                                                                        |
| `Returns`                 | An array or associative array of post meta                                                                |
 ---------------------------------------------------------------------------------------------------------------------------------------

The purpose of this filter is to only allow indexing of meta that will actually be used for querying. This is to keep each indexes field count in check and to prevent reindexing of posts when a meta that isn't involved in querying is changed.

### Automattic/ElasticPress <a name="a-ep-filters"></a>

Here is a list of filters available in our fork of ElasticPress. Some are unique to the fork or are used to produce unique VIP Search functionality. Please see [Automattic/vip-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins) for VIP Search implementation details.

*  ep_{indexable slug}_index_kill
*  ep_admin_notices
*  ep_admin_show_host
*  ep_admin_show_index_prefix
*  ep_admin_supported_post_types
*  ep_allow_post_content_filtered_index
*  ep_allowed_documents_ingest_mime_types
*  ep_autosuggest_ngram_fields
*  ep_autosuggest_options
*  ep_autosuggest_query_placeholder
*  ep_bulk_index_action_args
*  ep_bulk_index_request_path
*  ep_bulk_index_request_path
*  ep_bulk_items_per_page
*  ep_config_mapping
*  ep_config_mapping_request
*  ep_create_pipeline_args
*  ep_dashboard_index_args
*  ep_dashboard_indexable_labels
*  ep_default_language
*  ep_do_intercept_request
*  ep_elasticpress_enabled
*  ep_elasticsearch_plugins
*  ep_elasticsearch_version
*  ep_es_query_results
*  ep_facet_query_string
*  ep_facet_search_threshold
*  ep_facet_search_widget
*  ep_facet_taxonomies_size
*  ep_feature_active
*  ep_feature_requirements_status
*  ep_find_related_args
*  ep_format_request_headers
*  ep_formatted_args
*  ep_formatted_args_query
*  ep_fuzziness_arg
*  ep_get_hits_from_query
*  ep_get_pipeline_args
*  ep_global_alias
*  ep_host
*  ep_ignore_invalid_dates
*  ep_index_default_per_page
*  ep_index_health_stats_indices
*  ep_index_meta
*  ep_index_name
*  ep_index_posts_args
*  ep_index_prefix
*  ep_indexable_post_status
*  ep_indexable_post_types
*  ep_indexable_sites
*  ep_indexable_sites_args
*  ep_indexable_taxonomies
*  ep_install_status
*  ep_intercept_remote_request
*  ep_is_indexing
*  ep_is_indexing_wpcli
*  ep_item_sync_kill
*  ep_keep_index
*  ep_last_sync
*  ep_match_boost
*  ep_match_phrase_boost
*  ep_max_remote_request_tries
*  ep_pc_supported_post_types
*  ep_post_formatted_args
*  ep_post_mapping
*  ep_post_mapping_file
*  ep_post_query_db_args
*  ep_related_posts_fields
*  ep_post_sync_args
*  ep_post_sync_args_post_prepare_meta
*  ep_pre_index_{indexable slug}
*  ep_pre_request_host
*  ep_pre_request_url
*  ep_prepare_meta_allowed_protected_keys
*  ep_prepare_meta_data
*  ep_prepare_meta_excluded_public_keys
*  ep_prepare_meta_whitelist_key
*  ep_prepare_term_meta_allowed_protected_keys
*  ep_prepare_term_meta_excluded_public_keys
*  ep_prepare_term_meta_whitelist_key
*  ep_prepare_user_meta_allowed_protected_keys
*  ep_prepare_user_meta_excluded_public_keys
*  ep_prepare_user_meta_whitelist_key
*  ep_query_post_type
*  ep_query_request_path
*  ep_related_posts_max_query_terms
*  ep_related_posts_min_doc_freq
*  ep_related_posts_min_term_freq
*  ep_remote_request_is_valid_res
*  ep_retrieve_the_{index type}
*  ep_sanitize_feature_settings
*  ep_search_fields
*  ep_search_post_return_args
*  ep_search_request_path
*  ep_search_scope
*  ep_search_term_return_args
*  ep_search_user_return_args
*  ep_searchable_post_types
*  ep_set_default_sort
*  ep_skip_action_edited_term
*  ep_skip_index_reset
*  ep_skip_post_meta_sync
*  ep_sync_taxonomies
*  ep_sync_terms_allow_hierarchy
*  ep_term_all_query_db_args
*  ep_term_formatted_args
*  ep_term_formatted_args_query
*  ep_term_fuzziness_arg
*  ep_term_mapping
*  ep_term_mapping_file
*  ep_term_match_boost
*  ep_term_match_phrase_boost
*  ep_term_max_shingle_diff
*  ep_term_query_db_args
*  ep_term_search_fields
*  ep_term_search_scope
*  ep_term_suggest_post_status
*  ep_term_suggest_post_type
*  ep_term_sync_args
*  ep_user_formatted_args
*  ep_user_formatted_args_query
*  ep_user_fuzziness_arg
*  ep_user_mapping
*  ep_user_mapping_file
*  ep_user_match_boost
*  ep_user_match_phrase_boost
*  ep_user_query_db_args
*  ep_user_search_fields
*  ep_user_search_remove_wildcards
*  ep_user_sync_args
*  ep_user_sync_kill
*  ep_weighted_query_for_post_type
*  ep_weighting_configuration
*  ep_weighting_configuration_for_autosuggest
*  ep_weighting_default_post_type_weights
*  ep_weighting_fields_for_post_type
*  ep_weighting_ignore_fields_in_consideration
*  ep_woocommerce_supported_taxonomies
*  ep_wp_query_cached_posts
*  ep_wp_query_cached_terms
*  ep_wp_query_search_cached_users
*  epwr_boost_mode
*  epwr_decay
*  epwr_decay_function
*  epwr_offset
*  epwr_scale
*  epwr_score_mode
*  pre_ep_index_sync_queue

## Actions <a name="actions"></a>

Actions are functions that WordPress executes at certain predefined points. The operate like a queue ordered by priority where the higher the priority the later it runs at the ponts where `do_action` is called for a particular hook.

### VIP Search <a name="vip-search-actions"></a>

There are no VIP Search actions at this time.

### Automattic/ElasticPress <a name="a-ep-actions"></a>

Here is a list of actions available in our fork of ElasticPress. Some are unique to the fork or are used to produce unique VIP Search functionality. Please see [Automattic/vip-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins) for VIP Search implementation details. 

*  ep_add_query_log
*  ep_after_add_to_queue
*  ep_after_bulk_index
*  ep_after_index
*  ep_after_index_post
*  ep_after_remove_from_queue
*  ep_cli_{indexable slug}_bulk_index
*  ep_cli_object_index
*  ep_dashboard_put_mapping
*  ep_dashboard_start_index
*  ep_delete_post
*  ep_feature_box_long
*  ep_feature_box_summary
*  ep_feature_create
*  ep_feature_post_activation
*  ep_after_index_{indexable slug}
*  ep_index_post_retrieve_raw_response
*  ep_index_retrieve_raw_response
*  ep_invalid_response
*  ep_pre_dashboard_index
*  ep_remote_request
*  ep_retrieve_aggregations
*  ep_retrieve_raw_response
*  ep_settings_custom
*  ep_setup_features
*  ep_sync_on_edited_term
*  ep_sync_on_meta_update
*  ep_sync_on_set_object_terms
*  ep_sync_on_transition
*  ep_sync_user_on_transition
*  ep_valid_response
*  ep_weighting_added
*  ep_wp_cli_pre_index
*  ep_wp_query
*  ep_wp_query_non_cached_search
*  ep_wp_query_search