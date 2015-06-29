<?php

require_once('lib/functions.php');
require_once('api/lift-batch.php');
require_once('api/lift-http.php');
require_once('api/cloud-search-api.php');
require_once('api/cloud-search-query.php');
require_once('api/cloud-config-api.php');
require_once('lib/posts-to-sdf.php');
require_once('wp/domain-manager.php');
require_once('wp/field.php');
require_once('wp/form-controls.php');
require_once('wp/form-filters.php');
require_once('wp/lift-batch-handler.php');
require_once('wp/lift-health.php');
require_once('wp/lift-wp-search.php');
require_once('wp/lift-search-form.php');
require_once('wp/lift-update-queue.php');
require_once('wp/update-watchers/post.php');
require_once('lib/wp-asynch-events.php');

if ( !class_exists( 'Lift_Search' ) ) {

    class Lift_Search {
        /**
         * Option name for the marker of whether the user finisehd the setup process
         */

        const INITIAL_SETUP_COMPLETE_OPTION = 'lift-initial-setup-complete';
        const DB_VERSION = 5;

        /**
         * Option name for storing all user based options
         */
        const SETTINGS_OPTION = 'lift-settings';
        const DOMAIN_EVENT_WATCH_INTERVAL = 60;

        public static function error_logging_enabled() {
            return (!( defined( 'DISABLE_LIFT_ERROR_LOGGING' ) && DISABLE_LIFT_ERROR_LOGGING )) && ( class_exists( 'Voce_Error_Logging' ) || file_exists( __DIR__ . '/lib/voce-error-logging/voce-error-logging.php' ) );
        }

        public static function init() {
            if ( self::error_logging_enabled() && !class_exists( 'Voce_Error_Logging' ) && file_exists( __DIR__ . '/lib/voce-error-logging/voce-error-logging.php' ) ) {
                require_once (__DIR__ . '/lib/voce-error-logging/voce-error-logging.php');
            }

            if ( self::get_search_endpoint() && self::get_override_search() ) {
                add_action( 'init', array( 'Lift_WP_Search', 'init' ) );
            }

            if ( self::get_document_endpoint() ) {
                add_action( 'init', array( 'Lift_Batch_Handler', 'init' ), 9 );
                add_action( 'lift_post_changes_to_data', array( __CLASS__, '_default_extended_post_data' ), 10, 3 );
            }

            if ( is_admin() ) {
                require_once(__DIR__ . '/admin/admin.php');
                $admin = new Lift_Admin();
                $admin->init();
            }

            add_action( 'init', array( __CLASS__, '_upgrade_check' ) );

            //need cron hooks to be set prior to init
            add_action( Lift_Batch_Handler::BATCH_CRON_HOOK, array( 'Lift_Batch_Handler', 'send_next_batch' ) );
            add_action( Lift_Batch_Handler::QUEUE_ALL_CRON_HOOK, array( 'Lift_Batch_Handler', 'process_queue_all' ) );


            // @TODO only enqueue on search template or if someone calls the form
            add_action( 'wp_enqueue_scripts', function() {
                    wp_enqueue_script( 'lift-search-form', plugins_url( 'js/lift-search-form.js', __FILE__ ), array( 'jquery' ), '0.2', true );
                    wp_enqueue_style( 'lift-search', plugins_url( 'css/style.css', __FILE__ ) );
                } );

            //default sdf filters
            add_filter( 'lift_document_fields_result', function($fields, $post_id) {
                    $taxonomies = array( 'post_tag', 'category' );
                    foreach ( $taxonomies as $taxonomy ) {
                        if ( array_key_exists( 'taxonomy_' . $taxonomy, $fields ) ) {
                            unset( $fields['taxonomy_' . $taxonomy] );
                            $terms = get_the_terms( $post_id, $taxonomy );
                            $fields['taxonomy_' . $taxonomy . '_id'] = array( );
                            $fields['taxonomy_' . $taxonomy . '_label'] = array( );
                            foreach ( $terms as $term ) {
                                $fields['taxonomy_' . $taxonomy . '_id'][] = $term->term_id;
                                $fields['taxonomy_' . $taxonomy . '_label'][] = $term->name;
                            }
                        }
                    }

                    if ( array_key_exists( 'post_author', $fields ) ) {
                        $display_name = get_user_meta( $fields['post_author'], 'display_name', true );

                        if ( $display_name ) {
                            $fields['post_author_name'] = $display_name;
                        }
                    }

                    return $fields;
                }, 10, 2 );

            add_filter( 'cron_schedules', function( $schedules ) {
                    if ( Lift_Search::get_batch_interval() > 0 ) {
                        $interval = Lift_Search::get_batch_interval();
                    } else {
                        $interval = DAY_IN_SECONDS;
                    }

                    $schedules[Lift_Batch_Handler::CRON_INTERVAL] = array(
                        'interval' => $interval,
                        'display' => '',
                    );

                    return $schedules;
                } );
        }

        /**
         * Returns an instance of the Lift_Domain_Manager
         * @param string $access_key
         * @param string $secret_key
         * @return Lift_Domain_Manager
         */
        public static function get_domain_manager( $access_key = null, $secret_key = null ) {
            if ( is_null( $access_key ) )
                $access_key = self::get_access_key_id();
            if ( is_null( $secret_key ) )
                $secret_key = self::get_secret_access_key();

            return new Lift_Domain_Manager( $access_key, $secret_key, self::get_http_api() );
        }

        public function test_access( $id = '', $secret = '' ) {

            $credentials = array( 'access-key-id' => $id, 'secret-access-key' => $secret );
            $error = false;

            try {
                if ( Cloud_Config_API::TestConnection( $credentials ) ) {
                    $status_message = 'Success';
                    self::__set_setting( 'access-key-id', $id );
                    self::__set_setting( 'secret-access-key', $secret );
                } else {
                    $status_message = 'There was an error authenticating. Please check your Access Key ID and Secret Access Key and try again.';

                    $error = true;
                }
            } catch ( Exception $e ) {
                // @todo add exception logging?
                $status_message = 'There was an error authenticating your access keys. Please try again.';

                $error = true;
            }

            return array( 'error' => $error, 'message' => $status_message );
        }

        /**
         * Get a setting lift setting
         * @param string $setting
         * @param string $group
         * @return string | mixed
         */
        private static function __get_setting( $setting ) {
            // Note: batch-interval should be in seconds, regardless of what batch-interval-units is set to
            $default_settings = array( 'batch-interval' => 300, 'batch-interval-units' => 'm', 'override-search' => true );

            $settings = get_option( self::SETTINGS_OPTION, array( ) );

            if ( !is_array( $settings ) ) {
                $settings = $default_settings;
            } else {
                $settings = wp_parse_args( $settings, $default_settings );
            }

            return (isset( $settings[$setting] )) ? $settings[$setting] : false;
        }

        private static function __set_setting( $setting, $value ) {
            $settings = array( $setting => $value ) + get_option( self::SETTINGS_OPTION, array( ) );

            update_option( self::SETTINGS_OPTION, $settings );
        }

        /**
         * Get access key id
         * @return string
         */
        public static function get_access_key_id() {
            return ( string ) apply_filters( 'lift_access_key_id', self::__get_setting( 'access-key-id' ) );
        }

        /**
         * Sets the access key id
         * @param type $value
         */
        public static function set_access_key_id( $value ) {
            self::__set_setting( 'access-key-id', $value );
        }

        /**
         * Get secret access key
         * @return string
         */
        public static function get_secret_access_key() {
            return ( string ) apply_filters( 'lift_secret_access_key', self::__get_setting( 'secret-access-key' ) );
        }

        /**
         * Sets the secret key id
         * @param type $value
         */
        public static function set_secret_access_key( $value ) {
            self::__set_setting( 'secret-access-key', $value );
        }

        /**
         * Get search domain
         * @return string
         */
        public static function get_search_domain_name() {
            return ( string ) apply_filters( 'lift_search_domain', self::__get_setting( 'search-domain' ) );
        }

        public static function set_search_domain_name( $domain_name ) {
            $old_domain_name = self::get_search_domain_name();
            if ( $old_domain_name && $domain_name != $old_domain_name ) {
                $domain_manager = self::get_domain_manager();
                TAE_Async_Event::Unwatch( 'lift_domain_created_' . $old_domain_name );
                TAE_Async_Event::Unwatch( 'lift_needs_indexing_' . $old_domain_name );
            }
            self::__set_setting( 'search-domain', $domain_name );
        }

        /**
         * Sets the domain region
         * @param type $value
         */
        public static function set_domain_region( $value ) {
            if ( self::is_valid_region($value) ) {
                self::__set_setting( 'domain-region', $value );
            } else {
                self::__set_setting( 'domain-region', '' );
            }
        }

        public static function is_valid_region( $region ) {
            $regions = array(
                'us-east-1',
                'us-west-1',
                'us-west-2',
                'eu-west-1',
                'ap-southeast-1'
            );
            return (bool) in_array( $region, $regions );
        }

        /**
         * Get domain region
         * @return string
         */
        public static function get_domain_region() {
            return ( string ) apply_filters( 'lift_domain_region', self::__get_setting( 'domain-region' ) );
        }

        /**
         * Get search endpoint setting
         * @return string
         */
        public static function get_search_endpoint() {
            return apply_filters( 'lift_search_endpoint', self::get_domain_manager()->get_search_endpoint( self::get_search_domain_name() ) );
        }

        /**
         * Get document endpoint setting
         * @return string
         */
        public static function get_document_endpoint() {
            return apply_filters( 'lift_document_endpoint', self::get_domain_manager()->get_document_endpoint( self::get_search_domain_name() ) );
        }

        public static function set_override_search( $value ) {
            self::__set_setting( 'override-search', ( bool ) $value );
        }

        public static function get_override_search() {
            return self::__get_setting( 'override-search' );
        }

        /**
         * Get batch interval setting
         * @return int
         */
        public static function get_batch_interval() {
            return apply_filters( 'lift_batch_interval', self::__get_setting( 'batch-interval' ) );
        }

        public static function get_batch_interval_display() {
            $value = self::get_batch_interval();
            $unit = self::__get_setting( 'batch-interval-unit' );
            switch ( $unit ) {
                case 'd':
                    $value /= 24;
                case 'h':
                    $value /= 60;
                case 'm':
                    $value /= 60;
                    break;
                default:
                    $unit = 'm';
                    $value /= 60;
                    break;
            }

            return apply_filters( 'lift_batch_interval_display', compact( 'value', 'unit' ) );
        }

        /**
         * Sets the batch interval based off of user facing values
         * @param int $value The number of units
         * @param string $unit The shorthand value of the unit, options are 'm','h','d'
         */
        public static function set_batch_interval_display( $value, $unit ) {
            $old_interval = self::get_batch_interval_display();
            $has_changed = false;

            foreach ( array( 'value', 'unit' ) as $key ) {
                if ( $old_interval[$key] != $$key ) {
                    $has_changed = true;
                    break;
                }
            }

            if ( $has_changed ) {
                $interval = $value;
                switch ( $unit ) {
                    case 'd':
                        $interval *= 24;
                    case 'h':
                        $interval *= 60;
                    case 'm':
                        $interval *= 60;
                        break;
                    default:
                        $unit = 'm';
                        $interval *= 60;
                        break;
                }

                self::__set_setting( 'batch-interval-unit', $unit );
                self::__set_setting( 'batch-interval', $interval );

                if ( Lift_Batch_Handler::cron_enabled() ) {
                    $last_time = get_option( Lift_Batch_Handler::LAST_CRON_TIME_OPTION, time() );

                    Lift_Batch_Handler::enable_cron( $last_time + $interval );
                }
            }
        }

        public static function get_http_api() {
            if ( function_exists( 'wpcom_is_vip' ) && wpcom_is_vip() ) {
                $lift_http = new Lift_HTTP_WP_VIP();
            } else {
                $lift_http = new Lift_HTTP_WP();
            }
            return $lift_http;
        }

        /**
         * semi-factory method for simplify getting an api instance
         */
        public static function get_search_api() {
            $lift_http = self::get_http_api();
            return new CloudSearch_API( $lift_http, Lift_Search::get_document_endpoint(), Lift_Search::get_search_endpoint(), '2011-02-01' );
        }

        public static function get_indexed_post_types() {
            return apply_filters( 'lift_indexed_post_types', get_post_types( array( 'public' => true ) ) );
        }

        public static function get_indexed_post_fields( $post_type ) {
            return apply_filters( 'lift_indexed_post_fields', array(
                'post_title',
                'post_content',
                'post_excerpt',
                'post_date_gmt',
                'post_excerpt',
                'post_status',
                'post_type',
                'post_author'
                ), $post_type );
        }

        public static function get_indexed_taxonomies() {
            return apply_filters( 'lift_indexed_taxonomies', array(
                'category', 'post_tag'
                ) );
        }

        public static function update_schema() {
            if ( $domain = self::get_search_domain_name() ) {
                self::get_domain_manager()->apply_schema( $domain );
            }
            return true;
        }

        public static function RecentErrorsTable() {
            if ( !self::error_logging_enabled() ) {
                return '<div class="notice">Error Logging is Disabled</div>';
            }

            $args = array(
                'post_type' => Voce_Error_Logging::POST_TYPE,
                'posts_per_page' => 5,
                'post_status' => 'any',
                'orderby' => 'date',
                'order' => 'DESC',
                'tax_query' => array( array(
                        'taxonomy' => Voce_Error_Logging::TAXONOMY,
                        'field' => 'slug',
                        'terms' => array( 'error', 'lift-search' ),
                        'operator' => 'AND'
                    ) ),
            );
            $query = new WP_Query( $args );
            $html = '<table id="lift-recent-logs-table" class="wp-list-table widefat fixed posts">
                <thead>
                <tr>
                    <th class="column-date">Log ID</th>
                    <th class="column-title">Log Title</th>
                    <th class="column-categories">Time Logged</th>
                </tr>
                </thead><tbody>';
            $pages = '';
            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) : $query->the_post();
                    $html .= '<tr>';
                    $html .= '<td class="column-date">' . get_the_ID() . '</td>';
                    $html .= '<td class="column-title"><a href="' . get_admin_url() . 'post.php?post=' . get_the_ID() . '&action=edit' . '">' . get_the_title() . '</a></td>';
                    $html .= '<td class="column-categories">' . get_the_time( 'D. M d Y g:ia' ) . '</td>';
                    $html .= '</tr>';
                endwhile;
            } else {
                $html .= '<tr><td colspan="2">No Recent Errors</td></tr>';
            }
            $html .= '</tbody></table>';
            $html .= $pages;

            return $html;
        }

        public static function _default_extended_post_data( $post_data, $updated_fields, $document_id ) {

            $post_data['post_author_name'] = get_the_author_meta( 'display_name', $post_data['post_author'], $document_id );

            $taxonomies = self::get_indexed_taxonomies();

            foreach ( $taxonomies as $taxonomy ) {
                $terms = get_the_terms( $document_id, $taxonomy );
                if ( !empty( $terms ) ) {

                    $post_data["taxonomy_{$taxonomy}_label"] = array( );
                    $post_data["taxonomy_{$taxonomy}_id"] = array( );

                    foreach ( $terms as $term ) {
                        $post_data["taxonomy_{$taxonomy}_label"][] = $term->name;
                        $post_data["taxonomy_{$taxonomy}_id"][] = (string) $term->term_id;
                    }

                    $post_data["taxonomy_{$taxonomy}_label"] = implode( ', ', $post_data["taxonomy_{$taxonomy}_label"] );
                }
            }
            return $post_data;
        }

        /**
         * Log Events
         * @param type $message
         * @param type $tags
         * @return boolean
         */
        public static function event_log( $message, $error, $tags = array( ) ) {
            if ( function_exists( 'voce_error_log' ) ) {
                return voce_error_log( $message, $error, array_merge( array( 'lift-search' ), ( array ) $tags ) );
            } else {
                return false;
            }
        }

        public static function _upgrade_check() {
            global $wpdb;

            $current_db_version = get_option( 'lift_db_version', 0 );
            $queue_all = false;
            $changed_schema_fields = array( );

            if ( $current_db_version < 2 ) {
                //queue storage changes
                $post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts " .
                        "WHERE post_type = %s", Lift_Document_Update_Queue::STORAGE_POST_TYPE ) );

                $queue_id = Lift_Document_Update_Queue::get_active_queue_id();

                foreach ( $post_ids as $post_id ) {
                    if ( $update_meta = get_post_meta( $post_id, 'lift_content', true ) ) {
                        if ( is_string( $update_meta ) )
                            $update_meta = maybe_unserialize( $update_meta ); //previous versions double serialized meta

                        $meta_key = 'lift_update_' . $update_meta['document_type'] . '_' . $update_meta['document_id'];
                        $new_meta = array(
                            'document_id' => $update_meta['document_id'],
                            'document_type' => $update_meta['document_type'],
                            'action' => $update_meta['action'],
                            'fields' => $update_meta['fields'],
                            'update_date_gmt' => get_post_time( 'Y-m-d H:i:s', true, $post_id ),
                            'update_date' => get_post_time( 'Y-m-d H:i:s', false, $post_id )
                        );
                        update_post_meta( $queue_id, $meta_key, $new_meta );

                        wp_delete_post( $post_id );
                    }
                }

                update_option( 'lift_db_version', 2 );
            }

            if ( $current_db_version < 4 && self::get_search_domain_name() ) {
                //schema changes
                self::update_schema();

                update_option( 'lift_db_version', 4 );
            }

            if ( $current_db_version < 5 ) {
                wp_clear_scheduled_hook( 'lift_index_documents' );
                wp_clear_scheduled_hook( 'lift_set_endpoints' );
                update_option( 'lift_db_version', 5 );
            }
        }

    }

    add_action( 'after_setup_theme', array( 'Lift_Search', 'init' ) );
}


register_deactivation_hook( __FILE__, '_lift_deactivate' );

function _lift_deactivate() {
    $domain_manager = Lift_Search::get_domain_manager();
    if ( $domain_name = Lift_Search::get_search_domain_name() ) {
        TAE_Async_Event::Unwatch( 'lift_domain_created_' . $domain_name );
        TAE_Async_Event::Unwatch( 'lift_needs_indexing_' . $domain_name );
    }


    //clean up options
    delete_option( Lift_Search::INITIAL_SETUP_COMPLETE_OPTION );
    delete_option( Lift_Search::SETTINGS_OPTION );
    delete_option( 'lift_db_version' );
    delete_option( Lift_Document_Update_Queue::QUEUE_IDS_OPTION );

    if ( class_exists( 'Voce_Error_Logging' ) ) {
        Voce_Error_Logging::delete_logs( array( 'lift-search' ) );
    }

    Lift_Batch_Handler::_deactivation_cleanup();
    Lift_Document_Update_Queue::_deactivation_cleanup();
}

function _lift_activation() {
    //register the queue posts
    Lift_Document_Update_Queue::init();
    Lift_Document_Update_Queue::get_active_queue_id();
    Lift_Document_Update_Queue::get_closed_queue_id();
}

function lift_get_current_site_id() {
    global $wpdb;
    return ($wpdb->siteid) ? intval( $wpdb->siteid ) : 1;
}