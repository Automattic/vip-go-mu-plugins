<?php
/**
 * Parsely class
 *
 * @package Parsely
 * @since 2.5.0
 */

declare(strict_types=1);

namespace Parsely;

use WP_Post;
use WP_User;

/**
 * Holds most of the logic for the plugin.
 *
 * @since 1.0.0
 * @since 2.5.0 Moved from plugin root file to this file.
 */
class Parsely {
	/**
	 * Declare our constants
	 */
	public const VERSION     = PARSELY_VERSION;
	public const MENU_SLUG   = 'parsely';             // Defines the page param passed to options-general.php.
	public const OPTIONS_KEY = 'parsely';             // Defines the key used to store options in the WP database.
	public const CAPABILITY  = 'manage_options';      // The capability required for the user to administer settings.

	/**
	 * Declare some class properties
	 *
	 * @var array<string, mixed> $option_defaults The defaults we need for the class.
	 */
	private $option_defaults = array(
		'apikey'                      => '',
		'content_id_prefix'           => '',
		'api_secret'                  => '',
		'use_top_level_cats'          => false,
		'custom_taxonomy_section'     => 'category',
		'cats_as_tags'                => false,
		'track_authenticated_users'   => true,
		'lowercase_tags'              => true,
		'force_https_canonicals'      => false,
		'track_post_types'            => array( 'post' ),
		'track_page_types'            => array( 'page' ),
		'disable_javascript'          => false,
		'disable_amp'                 => false,
		'meta_type'                   => 'json_ld',
		'logo'                        => '',
		'metadata_secret'             => '',
		'parsely_wipe_metadata_cache' => false,
	);

	/**
	 * Declare post types that Parse.ly will process as "posts".
	 *
	 * @link https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	private $supported_jsonld_post_types = array(
		'NewsArticle',
		'Article',
		'TechArticle',
		'BlogPosting',
		'LiveBlogPosting',
		'Report',
		'Review',
		'CreativeWork',
	);

	/**
	 * Declare post types that Parse.ly will process as "non-posts".
	 *
	 * @link https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages
	 *
	 * @since 2.5.0
	 * @var string[]
	 */
	private $supported_jsonld_non_post_types = array(
		'WebPage',
		'Event',
		'Hotel',
		'Restaurant',
		'Movie',
	);

	/**
	 * Register action and filter hook callbacks.
	 *
	 * Also, immediately upgrade options if needed.
	 *
	 * @return void
	 */
	public function run(): void {
		// Run upgrade options if they exist for the version currently defined.
		$options = $this->get_options();
		if ( empty( $options['plugin_version'] ) || self::VERSION !== $options['plugin_version'] ) {
			$method = 'upgrade_plugin_to_version_' . str_replace( '.', '_', self::VERSION );
			if ( method_exists( $this, $method ) ) {
				call_user_func_array( array( $this, $method ), array( $options ) );
			}
			// Update our version info.
			$options['plugin_version'] = self::VERSION;
			update_option( self::OPTIONS_KEY, $options );
		}

		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_filter( 'cron_schedules', array( $this, 'wpparsely_add_cron_interval' ) );
		add_action( 'parsely_bulk_metas_update', array( $this, 'bulk_update_posts' ) );
		add_action( 'save_post', array( $this, 'update_metadata_endpoint' ) );
		add_action( 'wp_head', array( $this, 'insert_page_header_metadata' ) );
	}

	/**
	 * Adds 10 minute cron interval.
	 *
	 * @param array $schedules WP schedules array.
	 * @return array
	 */
	public function wpparsely_add_cron_interval( array $schedules ): array {
		$schedules['everytenminutes'] = array(
			'interval' => 600, // time in seconds.
			'display'  => __( 'Every 10 Minutes', 'wp-parsely' ),
		);
		return $schedules;
	}

	/**
	 * Get the full URL of the JavaScript tracker file for the site. If an API key is not set, return an empty string.
	 *
	 * @since 3.2.0
	 *
	 * @return string
	 */
	public function get_tracker_url(): string {
		if ( $this->api_key_is_set() ) {
			$tracker_url = 'https://cdn.parsely.com/keys/' . $this->get_api_key() . '/p.js';
			return esc_url( $tracker_url );
		}
		return '';
	}

	/**
	 * Insert the code for the <meta name='parsely-page'> parameter within the <head></head> tag.
	 *
	 * @since 3.2.0
	 *
	 * @param string $meta_type `json_ld` or `repeated_metas`.
	 * @return void
	 */
	public function render_metadata( string $meta_type ): void {
		/**
		 * Filter whether the Parse.ly meta tags should be inserted in the page.
		 *
		 * By default, the tags are inserted.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $insert_metadata True to insert the metadata, false otherwise.
		 */
		if ( ! apply_filters( 'wp_parsely_should_insert_metadata', true ) ) {
			return;
		}

		$parsely_options = $this->get_options();

		if (
			$this->api_key_is_missing() ||

			// Chosen not to track logged-in users.
			( ! $parsely_options['track_authenticated_users'] && $this->parsely_is_user_logged_in() ) ||

			// 404 pages are not tracked.
			is_404() ||

			// Search pages are not tracked.
			is_search()
		) {
			return;
		}

		global $post;

		// We can't construct the metadata without a valid post object.
		$parsed_post = get_post( $post );
		if ( ! $parsed_post instanceof WP_Post ) {
			return;
		}

		// Assign default values for LD+JSON
		// TODO: Mapping of an install's post types to Parse.ly post types (namely page/post).
		$parsely_page = $this->construct_parsely_metadata( $parsely_options, $parsed_post );

		// Something went wrong - abort.
		if ( 0 === count( $parsely_page ) || ! isset( $parsely_page['headline'] ) ) {
			return;
		}

		// Insert JSON-LD or repeated metas.
		if ( 'json_ld' === $meta_type ) {
			include plugin_dir_path( PARSELY_FILE ) . 'views/json-ld.php';
		} else {
			// Assume `meta_type` is `repeated_metas`.
			$parsely_post_type = $this->convert_jsonld_to_parsely_type( $parsely_page['@type'] );
			if ( isset( $parsely_page['keywords'] ) && is_array( $parsely_page['keywords'] ) ) {
				$parsely_page['keywords'] = implode( ',', $parsely_page['keywords'] );
			}

			$parsely_metas = array(
				'title'     => $parsely_page['headline'] ?? null,
				'link'      => $parsely_page['url'] ?? null,
				'type'      => $parsely_post_type,
				'image-url' => $parsely_page['thumbnailUrl'] ?? null,
				'pub-date'  => $parsely_page['datePublished'] ?? null,
				'section'   => $parsely_page['articleSection'] ?? null,
				'tags'      => $parsely_page['keywords'] ?? null,
				'author'    => isset( $parsely_page['author'] ),
			);
			$parsely_metas = array_filter( $parsely_metas, array( $this, 'filter_empty_and_not_string_from_array' ) );

			if ( isset( $parsely_page['author'] ) ) {
				$parsely_page_authors = wp_list_pluck( $parsely_page['author'], 'name' );
				$parsely_page_authors = array_filter( $parsely_page_authors, array( $this, 'filter_empty_and_not_string_from_array' ) );
			}

			include plugin_dir_path( PARSELY_FILE ) . 'views/repeated-metas.php';
		}

		// Add any custom metadata.
		if ( isset( $parsely_page['custom_metadata'] ) ) {
			include plugin_dir_path( PARSELY_FILE ) . 'views/custom-metadata.php';
		}
	}

	/**
	 * Insert the code for the <meta name='parsely-page'> parameter within the <head></head> tag.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public function insert_page_header_metadata(): void {
		$parsely_options = $this->get_options();
		$this->render_metadata( $parsely_options['meta_type'] );
	}

	/**
	 * Deprecated. Echo the metadata into the page, and return the inserted values.
	 *
	 * To just echo the metadata, use the `insert_page_header_metadata()` method.
	 * To get the metadata to be inserted, use the `construct_parsely_metadata()` method.
	 *
	 * @deprecated 3.0.0
	 * @see construct_parsely_metadata()
	 *
	 * @return array<string, mixed>
	 */
	public function insert_parsely_page(): array {
		_deprecated_function( __FUNCTION__, '3.0', 'construct_parsely_metadata()' );
		$this->insert_page_header_metadata();

		global $post;

		$parsed_post = get_post( $post );
		if ( ! $parsed_post instanceof WP_Post ) {
			return array();
		}

		return $this->construct_parsely_metadata( $this->get_options(), $parsed_post );
	}

	/**
	 * Function to be used in `array_filter` to clean up repeated metas.
	 *
	 * @since 2.6.0
	 *
	 * @param mixed $var Value to filter from the array.
	 * @return bool True if the variable is not empty, and it's a string.
	 */
	private static function filter_empty_and_not_string_from_array( $var ): bool {
		return is_string( $var ) && '' !== $var;
	}

	/**
	 * Compare the post_status key against an allowed list (by default, only 'publish'ed content includes tracking data).
	 *
	 * @since 2.5.0
	 *
	 * @param int|WP_Post $post Which post object or ID to check.
	 * @return bool Should the post status be tracked for the provided post's post_type. By default, only 'publish' is allowed.
	 */
	public static function post_has_trackable_status( $post ): bool {
		static $cache = array();
		$post_id      = is_int( $post ) ? $post : $post->ID;
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		/**
		 * Filters whether the post password check should be skipped when getting the post trackable status.
		 *
		 * @since 3.0.1
		 *
		 * @param bool $skip True if the password check should be skipped.
		 * @param int|WP_Post $post Which post object or ID is being checked.
		 *
		 * @returns bool
		 */
		$skip_password_check = apply_filters( 'wp_parsely_skip_post_password_check', false, $post );
		if ( ! $skip_password_check && post_password_required( $post ) ) {
			$cache[ $post_id ] = false;
			return false;
		}

		/**
		 * Filters the statuses that are permitted to be tracked.
		 *
		 * By default, the only status tracked is 'publish'. Use this filter if you have other published content that has a different (custom) status.
		 *
		 * @since 2.5.0
		 *
		 * @param string[]    $trackable_statuses The list of post statuses that are allowed to be tracked.
		 * @param int|WP_Post $post               Which post object or ID is being checked.
		 */
		$statuses          = apply_filters( 'wp_parsely_trackable_statuses', array( 'publish' ), $post );
		$cache[ $post_id ] = in_array( get_post_status( $post ), $statuses, true );
		return $cache[ $post_id ];
	}

	/**
	 * Creates parsely metadata object from post metadata.
	 *
	 * @param array<string, mixed> $parsely_options parsely_options array.
	 * @param WP_Post              $post object.
	 * @return array<string, mixed>
	 */
	public function construct_parsely_metadata( array $parsely_options, WP_Post $post ): array {
		$parsely_page      = array(
			'@context' => 'http://schema.org',
			'@type'    => 'WebPage',
		);
		$current_url       = $this->get_current_url();
		$queried_object_id = get_queried_object_id();

		if ( is_front_page() && ! is_paged() ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_bloginfo( 'name', 'raw' ) );
			$parsely_page['url']      = home_url();
		} elseif ( is_front_page() && is_paged() ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_bloginfo( 'name', 'raw' ) );
			$parsely_page['url']      = $current_url;
		} elseif (
			is_home() && (
				! ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) ) ||
				$queried_object_id && (int) get_option( 'page_for_posts' ) === $queried_object_id
			)
		) {
			$parsely_page['headline'] = get_the_title( get_option( 'page_for_posts', true ) );
			$parsely_page['url']      = $current_url;
		} elseif ( is_author() ) {
			// TODO: why can't we have something like a WP_User object for all the other cases? Much nicer to deal with than functions.
			$author                   = ( get_query_var( 'author_name' ) ) ? get_user_by( 'slug', get_query_var( 'author_name' ) ) : get_userdata( get_query_var( 'author' ) );
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( 'Author - ' . $author->data->display_name );
			$parsely_page['url']      = $current_url;
		} elseif ( is_category() || is_post_type_archive() || is_tax() ) {
			$category                 = get_queried_object();
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( $category->name );
			$parsely_page['url']      = $current_url;
		} elseif ( is_date() ) {
			if ( is_year() ) {
				/* translators: %s: Archive year */
				$parsely_page['headline'] = sprintf( __( 'Yearly Archive - %s', 'wp-parsely' ), get_the_time( 'Y' ) );
			} elseif ( is_month() ) {
				/* translators: %s: Archive month, formatted as F, Y */
				$parsely_page['headline'] = sprintf( __( 'Monthly Archive - %s', 'wp-parsely' ), get_the_time( 'F, Y' ) );
			} elseif ( is_day() ) {
				/* translators: %s: Archive day, formatted as F jS, Y */
				$parsely_page['headline'] = sprintf( __( 'Daily Archive - %s', 'wp-parsely' ), get_the_time( 'F jS, Y' ) );
			} elseif ( is_time() ) {
				/* translators: %s: Archive time, formatted as F jS g:i:s A */
				$parsely_page['headline'] = sprintf( __( 'Hourly, Minutely, or Secondly Archive - %s', 'wp-parsely' ), get_the_time( 'F jS g:i:s A' ) );
			}
			$parsely_page['url'] = $current_url;
		} elseif ( is_tag() ) {
			$tag = single_tag_title( '', false );
			if ( empty( $tag ) ) {
				$tag = single_term_title( '', false );
			}
			/* translators: %s: Tag name */
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( sprintf( __( 'Tagged - %s', 'wp-parsely' ), $tag ) );
			$parsely_page['url']      = $current_url;
		} elseif ( in_array( get_post_type( $post ), $parsely_options['track_post_types'], true ) && self::post_has_trackable_status( $post ) ) {
			$authors  = $this->get_author_names( $post );
			$category = $this->get_category_name( $post, $parsely_options );

			if ( has_post_thumbnail( $post ) ) {
				$image_id  = get_post_thumbnail_id( $post );
				$image_url = wp_get_attachment_image_src( $image_id );
				$image_url = $image_url[0];
			} else {
				$image_url = $this->get_first_image( $post );
			}

			$tags = $this->get_tags( $post->ID );
			if ( $parsely_options['cats_as_tags'] ) {
				$tags = array_merge( $tags, $this->get_categories( $post->ID ) );
				// add custom taxonomy values.
				$tags = array_merge( $tags, $this->get_custom_taxonomy_values( $post ) );
			}
			// the function 'mb_strtolower' is not enabled by default in php, so this check
			// falls back to the native php function 'strtolower' if necessary.
			if ( function_exists( 'mb_strtolower' ) ) {
				$lowercase_callback = 'mb_strtolower';
			} else {
				$lowercase_callback = 'strtolower';
			}
			if ( $parsely_options['lowercase_tags'] ) {
				$tags = array_map( $lowercase_callback, $tags );
			}

			/**
			 * Filters the post tags that are used as metadata keywords.
			 *
			 * @since 1.8.0
			 *
			 * @param string[] $tags Post tags.
			 * @param int      $ID   Post ID.
			 */
			$tags = apply_filters( 'wp_parsely_post_tags', $tags, $post->ID );
			$tags = array_map( array( $this, 'get_clean_parsely_page_value' ), $tags );
			$tags = array_values( array_unique( $tags ) );

			/**
			 * Filters the JSON-LD @type.
			 *
			 * @since 2.5.0
			 *
			 * @param array  $jsonld_type JSON-LD @type value, default is NewsArticle.
			 * @param int    $id          Post ID.
			 * @param string $post_type   The Post type in WordPress.
			 */
			$type            = (string) apply_filters( 'wp_parsely_post_type', 'NewsArticle', $post->ID, $post->post_type );
			$supported_types = array_merge( $this->supported_jsonld_post_types, $this->supported_jsonld_non_post_types );

			// Validate type before passing it further as an invalid type will not be recognized by Parse.ly.
			if ( ! in_array( $type, $supported_types, true ) ) {
				$error = sprintf(
					/* translators: 1: JSON @type like NewsArticle, 2: URL */
					__( '@type %1$s is not supported by Parse.ly. Please use a type mentioned in %2$s', 'wp-parsely' ),
					$type,
					'https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages'
				);
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( esc_html( $error ), E_USER_WARNING );
				$type = 'NewsArticle';
			}

			$parsely_page['@type']            = $type;
			$parsely_page['mainEntityOfPage'] = array(
				'@type' => 'WebPage',
				'@id'   => $this->get_current_url( 'post' ),
			);
			$parsely_page['headline']         = $this->get_clean_parsely_page_value( get_the_title( $post ) );
			$parsely_page['url']              = $this->get_current_url( 'post', $post->ID );
			$parsely_page['thumbnailUrl']     = $image_url;
			$parsely_page['image']            = array(
				'@type' => 'ImageObject',
				'url'   => $image_url,
			);

			$this->set_metadata_post_times( $parsely_page, $post );

			$parsely_page['articleSection'] = $category;
			$author_objects                 = array();
			foreach ( $authors as $author ) {
				$author_tag       = array(
					'@type' => 'Person',
					'name'  => $author,
				);
				$author_objects[] = $author_tag;
			}
			$parsely_page['author']    = $author_objects;
			$parsely_page['creator']   = $authors;
			$parsely_page['publisher'] = array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'logo'  => $parsely_options['logo'],
			);
			$parsely_page['keywords']  = $tags;
		} elseif ( in_array( get_post_type(), $parsely_options['track_page_types'], true ) && self::post_has_trackable_status( $post ) ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_the_title( $post ) );
			$parsely_page['url']      = $this->get_current_url( 'post' );
		} elseif ( 'page' === get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) ) {
			$parsely_page['headline'] = $this->get_clean_parsely_page_value( get_bloginfo( 'name', 'raw' ) );
			$parsely_page['url']      = home_url();
		}

		/**
		 * Filters the structured metadata.
		 *
		 * @since 2.5.0
		 *
		 * @param array   $parsely_page    Existing structured metadata for a page.
		 * @param WP_Post $post            Post object.
		 * @param array   $parsely_options The Parsely options.
		 */
		$filtered = apply_filters( 'wp_parsely_metadata', $parsely_page, $post, $parsely_options );
		if ( is_array( $filtered ) ) {
			return $filtered;
		}
		return array();
	}

	/**
	 * Sets all metadata values related to post time.
	 *
	 * @since 3.0.2
	 *
	 * @param array   $metadata Array containing all metadata. It will be potentially mutated to add keys: dateCreated, dateModified, & datePublished.
	 * @param WP_Post $post     Post object from which to extract time data.
	 * @return void
	 */
	private function set_metadata_post_times( array &$metadata, WP_Post $post ): void {
		$date_format      = 'Y-m-d\TH:i:s\Z';
		$post_created_gmt = get_post_time( $date_format, true, $post );

		if ( false === $post_created_gmt ) {
			return;
		}

		$metadata['dateCreated']   = $post_created_gmt;
		$metadata['datePublished'] = $post_created_gmt;
		$metadata['dateModified']  = $post_created_gmt;

		$post_modified_gmt = get_post_modified_time( $date_format, true, $post );

		if ( false !== $post_modified_gmt && $post_modified_gmt > $post_created_gmt ) {
			$metadata['dateModified'] = $post_modified_gmt;
		}
	}

	/**
	 * Updates the Parsely metadata endpoint with the new metadata of the post.
	 *
	 * @param int $post_id id of the post to update.
	 * @return void
	 */
	public function update_metadata_endpoint( int $post_id ): void {
		$parsely_options = $this->get_options();

		if ( $this->api_key_is_missing() || empty( $parsely_options['metadata_secret'] ) ) {
			return;
		}

		$post     = get_post( $post_id );
		$metadata = $this->construct_parsely_metadata( $parsely_options, $post );

		$endpoint_metadata = array(
			'canonical_url' => $metadata['url'],
			'page_type'     => $this->convert_jsonld_to_parsely_type( $metadata['@type'] ),
			'title'         => $metadata['headline'],
			'image_url'     => $metadata['thumbnailUrl'],
			'pub_date_tmsp' => $metadata['datePublished'],
			'section'       => $metadata['articleSection'],
			'authors'       => $metadata['creator'],
			'tags'          => $metadata['keywords'],
		);

		$parsely_api_endpoint    = 'https://api.parsely.com/v2/metadata/posts';
		$parsely_metadata_secret = $parsely_options['metadata_secret'];
		$headers                 = array(
			'Content-Type' => 'application/json',
		);
		$body                    = wp_json_encode(
			array(
				'secret'   => $parsely_metadata_secret,
				'apikey'   => $parsely_options['apikey'],
				'metadata' => $endpoint_metadata,
			)
		);
		$response                = wp_remote_post(
			$parsely_api_endpoint,
			array(
				'method'      => 'POST',
				'headers'     => $headers,
				'blocking'    => false,
				'body'        => $body,
				'data_format' => 'body',
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$current_timestamp = time();
			update_post_meta( $post_id, 'parsely_metadata_last_updated', $current_timestamp );
		}
	}

	/**
	 * Updates posts with Parsely metadata api in bulk.
	 *
	 * @return void
	 */
	public function bulk_update_posts(): void {
		global $wpdb;
		$parsely_options      = $this->get_options();
		$allowed_types        = array_merge( $parsely_options['track_post_types'], $parsely_options['track_page_types'] );
		$allowed_types_string = implode(
			', ',
			array_map(
				function( $v ) {
					return "'" . esc_sql( $v ) . "'";
				},
				$allowed_types
			)
		);
		$ids                  = wp_cache_get( 'parsely_post_ids_need_meta_updating' );
		if ( false === $ids ) {
			$ids = array();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				$wpdb->prepare( "SELECT DISTINCT(id) FROM {$wpdb->posts} WHERE post_type IN (\" . %s . \") AND id NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'parsely_metadata_last_updated');", $allowed_types_string ),
				ARRAY_N
			);
			foreach ( $results as $result ) {
				array_push( $ids, $result[0] );
			}
			wp_cache_set( 'parsely_post_ids_need_meta_updating', $ids, '', 86400 );
		}

		for ( $i = 0; $i < 100; $i++ ) {
			$post_id = array_pop( $ids );
			if ( null === $post_id ) {
				wp_clear_scheduled_hook( 'parsely_bulk_metas_update' );
				break;
			}
			$this->update_metadata_endpoint( $post_id );
		}
	}

	/**
	 * Get the cache buster value for script and styles.
	 *
	 * If WP_DEBUG is defined and truthy, and we're not running tests, then use a random number.
	 * Otherwise, use the plugin version.
	 *
	 * @since 2.5.0
	 * @deprecated 3.2.0
	 *
	 * @return string Random number string or plugin version string.
	 */
	public static function get_asset_cache_buster(): string {
		_deprecated_function( 'Parsely::get_asset_cache_buster', '3.2.0' );
		static $cache_buster;
		if ( isset( $cache_buster ) ) {
			return $cache_buster;
		}

		$cache_buster = defined( 'WP_DEBUG' ) && WP_DEBUG && empty( 'WP_TESTS_DOMAIN' ) ? wp_rand() : PARSELY_VERSION;

		/**
		 * Filters the cache buster value for linked scripts and styles.
		 *
		 * @since 2.5.0
		 *
		 * @param string $cache_buster Plugin version, unless WP_DEBUG is defined and truthy, and tests are not running.
		 */
		$cache_buster = apply_filters_deprecated( 'wp_parsely_cache_buster', array( (string) $cache_buster ), '3.2.0' );

		return $cache_buster;
	}

	/**
	 * Returns the tags associated with this page or post
	 *
	 * @param int $post_id The id of the post you're trying to get tags for.
	 * @return array The tags of the post represented by the post id.
	 */
	private function get_tags( int $post_id ): array {
		$tags      = array();
		$post_tags = wp_get_post_tags( $post_id );
		if ( ! is_wp_error( $post_tags ) ) {
			foreach ( $post_tags as $wp_tag ) {
				$tags[] = $wp_tag->name;
			}
		}
		return $tags;
	}

	/**
	 * Returns an array of all the child categories for the current post
	 *
	 * @param int    $post_id The id of the post you're trying to get categories for.
	 * @param string $delimiter What character will delimit the categories.
	 * @return array<string> All the child categories of the current post.
	 */
	private function get_categories( int $post_id, string $delimiter = '/' ): array {
		$tags = array();
		foreach ( get_the_category( $post_id ) as $category ) {
			$hierarchy = get_category_parents( $category->term_id, false, $delimiter );
			if ( ! is_wp_error( $hierarchy ) ) {
				$tags[] = rtrim( $hierarchy, '/' );
			}
		}
		// take last element in the hierarchy, a string representing the full parent->child tree,
		// and split it into individual category names.
		$last_tag = end( $tags );
		if ( false !== $last_tag ) {
			$tags = explode( '/', $last_tag );
		}

		// Remove default category name from tags if needed.
		$default_category_name = get_cat_name( get_option( 'default_category' ) );
		return array_diff( $tags, array( $default_category_name ) );
	}

	/**
	 * Safely returns options for the plugin by assigning defaults contained in optionDefaults.  As soon as actual
	 * options are saved, they override the defaults. This prevents us from having to do a lot of isset() checking
	 * on variables.
	 *
	 * @return array
	 */
	public function get_options(): array {
		$options = get_option( self::OPTIONS_KEY, $this->option_defaults );

		if ( ! is_array( $options ) ) {
			return $this->option_defaults;
		}

		return array_merge( $this->option_defaults, $options );
	}

	/**
	 * Returns a properly cleaned category/taxonomy value and will optionally use the top-level category/taxonomy value
	 * if so instructed via the `use_top_level_cats` option.
	 *
	 * @param WP_Post $post_obj The object for the post.
	 * @param array   $parsely_options The parsely options.
	 * @return string Cleaned category name for the post in question.
	 */
	private function get_category_name( WP_Post $post_obj, array $parsely_options ): string {
		$taxonomy_dropdown_choice = get_the_terms( $post_obj->ID, $parsely_options['custom_taxonomy_section'] );
		// Get top-level taxonomy name for chosen taxonomy and assign to $parent_name; it will be used
		// as the category value if 'use_top_level_cats' option is checked.
		// Assign as the default category name if no value is checked for the chosen taxonomy.
		$category_name = get_cat_name( get_option( 'default_category' ) );
		if ( ! empty( $taxonomy_dropdown_choice ) && ! is_wp_error( $taxonomy_dropdown_choice ) ) {
			if ( $parsely_options['use_top_level_cats'] ) {
				$first_term = array_shift( $taxonomy_dropdown_choice );
				$term_name  = $this->get_top_level_term( $first_term->term_id, $first_term->taxonomy );
			} else {
				$term_name = $this->get_bottom_level_term( $post_obj->ID, $parsely_options['custom_taxonomy_section'] );
			}

			if ( is_string( $term_name ) && 0 < strlen( $term_name ) ) {
				$category_name = $term_name;
			}
		}

		/**
		 * Filters the constructed category name that are used as metadata keywords.
		 *
		 * @since 1.8.0
		 *
		 * @param string  $category        Category name.
		 * @param WP_Post $post_obj        Post object.
		 * @param array   $parsely_options The Parsely options.
		 */
		$category_name = apply_filters( 'wp_parsely_post_category', $category_name, $post_obj, $parsely_options );

		return $this->get_clean_parsely_page_value( $category_name );
	}

	/**
	 * Return the top-most category/taxonomy value in a hierarcy given a taxonomy value's ID
	 * ( WordPress calls taxonomy values 'terms' ).
	 *
	 * @param int    $term_id The id of the top level term.
	 * @param string $taxonomy_name The name of the taxonomy.
	 * @return string|false $parent The top level name of the category / taxonomy.
	 */
	private function get_top_level_term( int $term_id, string $taxonomy_name ) {
		$parent = get_term_by( 'id', $term_id, $taxonomy_name );
		while ( false !== $parent && 0 !== $parent->parent ) {
			$parent = get_term_by( 'id', $parent->parent, $taxonomy_name );
		}
		return $parent ? $parent->name : false;
	}

	/**
	 * Return the bottom-most category/taxonomy value in a hierarcy given a post ID
	 * ( WordPress calls taxonomy values 'terms' ).
	 *
	 * @param int    $post_id The post id you're interested in.
	 * @param string $taxonomy_name The name of the taxonomy.
	 * @return string Name of the custom taxonomy.
	 */
	private function get_bottom_level_term( int $post_id, string $taxonomy_name ): string {
		$terms = get_the_terms( $post_id, $taxonomy_name );

		if ( ! is_array( $terms ) ) {
			return '';
		}

		$term_ids = wp_list_pluck( $terms, 'term_id' );
		$parents  = array_filter( wp_list_pluck( $terms, 'parent' ) );

		// Get array of IDs of terms which are not parents.
		$term_ids_not_parents = array_diff( $term_ids, $parents );
		// Get corresponding term objects, which are mapped to array index keys.
		$terms_not_parents = array_intersect_key( $terms, $term_ids_not_parents );
		// remove array index keys.
		$terms_not_parents_cleaned = array();
		foreach ( $terms_not_parents as $index => $value ) {
			$terms_not_parents_cleaned[] = $value;
		}

		if ( ! empty( $terms_not_parents_cleaned ) ) {
			// if you assign multiple child terms in a custom taxonomy, will only return the first.
			return $terms_not_parents_cleaned[0]->name ?? '';
		}

		return '';
	}

	/**
	 * Get all term values from custom taxonomies.
	 *
	 * @param WP_Post $post_obj The post object.
	 * @return array<string>
	 */
	private function get_custom_taxonomy_values( WP_Post $post_obj ): array {
		// filter out default WordPress taxonomies.
		$all_taxonomies = array_diff( get_taxonomies(), array( 'post_tag', 'nav_menu', 'author', 'link_category', 'post_format' ) );
		$all_values     = array();

		foreach ( $all_taxonomies as $taxonomy ) {
			$custom_taxonomy_objects = get_the_terms( $post_obj->ID, $taxonomy );
			if ( is_array( $custom_taxonomy_objects ) ) {
				foreach ( $custom_taxonomy_objects as $custom_taxonomy_object ) {
					$all_values[] = $custom_taxonomy_object->name;
				}
			}
		}

		return $all_values;
	}

	/**
	 * Returns a list of coauthors for a post assuming the Co-Authors Plus plugin is
	 * installed. Borrowed from
	 * https://github.com/Automattic/Co-Authors-Plus/blob/master/template-tags.php#L3-35
	 *
	 * @param int $post_id The id of the post.
	 * @return array<WP_User>
	 */
	private function get_coauthor_names( int $post_id ): array {
		$coauthors = array();
		if ( class_exists( 'coauthors_plus' ) ) {
			global $post, $post_ID, $coauthors_plus;

			if ( ! $post_id && $post_ID ) {
				$post_id = $post_ID;
			}

			if ( ! $post_id && $post ) {
				$post_id = $post->ID;
			}

			if ( $post_id ) {
				$coauthor_terms = get_the_terms( $post_id, $coauthors_plus->coauthor_taxonomy );

				if ( is_array( $coauthor_terms ) && ! empty( $coauthor_terms ) ) {
					foreach ( $coauthor_terms as $coauthor ) {
						$coauthor_slug = preg_replace( '#^cap-#', '', $coauthor->slug );
						$post_author   = $coauthors_plus->get_coauthor_by( 'user_nicename', $coauthor_slug );
						// In case the user has been deleted while plugin was deactivated.
						if ( ! empty( $post_author ) ) {
							$coauthors[] = new WP_User( $post_author );
						}
					}
				} elseif ( ! $coauthors_plus->force_guest_authors ) {
					if ( $post && $post_id === $post->ID ) {
						$post_author = get_userdata( $post->post_author );
					}
					if ( ! empty( $post_author ) ) {
						$coauthors[] = $post_author;
					}
				} // the empty else case is because if we force guest authors, we don't ever care what value wp_posts.post_author has.
			}
		}
		return $coauthors;
	}

	/**
	 * Determine author name from display name, falling back to firstname
	 * lastname, then nickname and finally the nicename.
	 *
	 * @param ?WP_User $author The author of the post.
	 * @return string
	 */
	private function get_author_name( ?WP_User $author ): string {
		// Gracefully handle situation where no author is available.
		if ( null === $author ) {
			return '';
		}

		if ( ! empty( $author->display_name ) ) {
			return $author->display_name;
		}

		$author_name = $author->user_firstname . ' ' . $author->user_lastname;
		if ( ' ' !== $author_name ) {
			return $author_name;
		}

		if ( ! empty( $author->nickname ) ) {
			return $author->nickname;
		}

		if ( ! empty( $author->user_nicename ) ) {
			return $author->user_nicename;
		}

		return '';
	}

	/**
	 * Retrieve all the authors for a post as an array. Can include multiple
	 * authors if coauthors plugin is in use.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string>
	 */
	private function get_author_names( WP_Post $post ): array {
		$authors = $this->get_coauthor_names( $post->ID );
		if ( 0 === count( $authors ) ) {
			$post_author = get_user_by( 'id', $post->post_author );
			if ( false !== $post_author ) {
				$authors = array( $post_author );
			}
		}

		/**
		 * Filters the list of author WP_User objects for a post.
		 *
		 * @since 1.14.0
		 *
		 * @param WP_User[] $authors One or more authors as WP_User objects.
		 * @param WP_Post   $post    Post object.
		 */
		$authors = apply_filters( 'wp_parsely_pre_authors', $authors, $post );

		// Getting the author name for each author.
		$authors = array_map( array( $this, 'get_author_name' ), $authors );

		/**
		 * Filters the list of author names for a post.
		 *
		 * @since 1.14.0
		 *
		 * @param string[] $authors One or more author names.
		 * @param WP_Post  $post    Post object.
		 */
		$authors = apply_filters( 'wp_parsely_post_authors', $authors, $post );

		return array_map( array( $this, 'get_clean_parsely_page_value' ), $authors );
	}

	/**
	 * Sanitize content
	 *
	 * @since 2.6.0
	 *
	 * @param string|null $val The content you'd like sanitized.
	 * @return string
	 */
	public function get_clean_parsely_page_value( ?string $val ): string {
		if ( null === $val ) {
			return '';
		}

		$val = str_replace( "\n", '', $val );
		$val = str_replace( "\r", '', $val );
		$val = wp_strip_all_tags( $val );
		return trim( $val );
	}

	/**
	 * Get the URL of the plugin settings page.
	 *
	 * @param int $_blog_id The Blog ID for the multisite subsite to use for context (Default null for current).
	 *
	 * @return string
	 */
	public static function get_settings_url( int $_blog_id = null ): string {
		return get_admin_url( $_blog_id, 'options-general.php?page=' . self::MENU_SLUG );
	}

	/**
	 * Get the URL of the current PHP script.
	 * A fall-back implementation to determine permalink
	 *
	 * @since 3.0.0 $parsely_type Default parameter changed to `non-post`.
	 *
	 * @param string $parsely_type Optional. Parse.ly post type you're interested in, either 'post' or 'non-post'. Default is 'non-post'.
	 * @param int    $post_id      Optional. ID of the post you want to get the URL for. Default is 0, which means the global `$post` is used.
	 * @return string
	 */
	public function get_current_url( string $parsely_type = 'non-post', int $post_id = 0 ): string {
		if ( 'post' === $parsely_type ) {
			$permalink = (string) get_permalink( $post_id );

			/**
			 * Filters the permalink for a post.
			 *
			 * @since 1.14.0
			 * @since 2.5.0  Added $post_id.
			 *
			 * @param string $permalink         The permalink URL or false if post does not exist.
			 * @param string $parsely_type      Parse.ly type ("post" or "non-post").
			 * @param int    $post_id           ID of the post you want to get the URL for. May be 0, so $permalink will be
			 *                                  for the global $post.
			 */
			$url = apply_filters( 'wp_parsely_permalink', $permalink, $parsely_type, $post_id );
		} else {
			$request_uri = isset( $_SERVER['REQUEST_URI'] )
					? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
					: '';

			$url = home_url( $request_uri );
		}

		$options = $this->get_options();
		return $options['force_https_canonicals']
				? str_replace( 'http://', 'https://', $url )
				: str_replace( 'https://', 'http://', $url );
	}

	/**
	 * Get the first image from a post
	 * https://css-tricks.com/snippets/wordpress/get-the-first-image-from-a-post/
	 *
	 * @param WP_Post $post The post object you're interested in.
	 * @return string
	 */
	public function get_first_image( WP_Post $post ): string {
		ob_start();
		ob_end_clean();
		if ( preg_match_all( '/<img.+src=[\'"]( [^\'"]+ )[\'"].*>/i', $post->post_content, $matches ) ) {
			return $matches[1][0];
		}
		return '';
	}

	/**
	 * Check to see if parsely user is logged in.
	 *
	 * @return bool
	 */
	public function parsely_is_user_logged_in(): bool {
		// can't use $blog_id here because it futzes with the global $blog_id.
		$current_blog_id = get_current_blog_id();
		$current_user_id = get_current_user_id();
		return is_user_member_of_blog( $current_user_id, $current_blog_id );
	}

	/**
	 * Convert JSON-LD type to respective Parse.ly page type.
	 *
	 * If the JSON-LD type is one of the types Parse.ly supports as a "post", then "post" will be returned.
	 * Otherwise, for "non-posts" and unknown types, "index" is returned.
	 *
	 * @since 2.5.0
	 * @since 3.2.0 Moved to private method.
	 *
	 * @see https://www.parse.ly/help/integration/metatags#field-description
	 *
	 * @param string $type JSON-LD type.
	 * @return string "post" or "index".
	 */
	private function convert_jsonld_to_parsely_type( string $type ): string {
		return in_array( $type, $this->supported_jsonld_post_types, true ) ? 'post' : 'index';
	}

	/**
	 * Determine if an API key is saved in the options.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True is API key is set, false if it is missing.
	 */
	public function api_key_is_set(): bool {
		$options = $this->get_options();

		return (
				isset( $options['apikey'] ) &&
				is_string( $options['apikey'] ) &&
				'' !== $options['apikey']
		);
	}

	/**
	 * Determine if an API key is not saved in the options.
	 *
	 * @since 2.6.0
	 *
	 * @return bool True if API key is missing, false if it is set.
	 */
	public function api_key_is_missing(): bool {
		return ! $this->api_key_is_set();
	}

	/**
	 * Get the API key if set.
	 *
	 * @since 2.6.0
	 *
	 * @return string API key if set, or empty string if not.
	 */
	public function get_api_key(): string {
		$options = $this->get_options();

		return $this->api_key_is_set() ? $options['apikey'] : '';
	}
}
