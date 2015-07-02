<?php

include_once( ABSPATH . 'wp-includes/class-simplepie.php' );
include_once( dirname(__FILE__) . '/interface-syndication-client.php' );

class Syndication_WP_RSS_Client extends SimplePie implements Syndication_Client {

    private $default_post_type;
    private $default_post_status;
    private $default_comment_status;
    private $default_ping_status;
    private $default_cat_status;

    function __construct( $site_ID ) {

        switch( SIMPLEPIE_VERSION ) {
            case '1.2.1':
                parent::SimplePie();
                break;
            case '1.3':
                parent::__construct();
                break;
            default:
                parent::__construct();
                break;
        }

        parent::__construct();

        $this->set_feed_url( get_post_meta( $site_ID, 'syn_feed_url', true ) );

        $this->default_post_type        = get_post_meta( $site_ID, 'syn_default_post_type', true );
        $this->default_post_status      = get_post_meta( $site_ID, 'syn_default_post_status', true );
        $this->default_comment_status   = get_post_meta( $site_ID, 'syn_default_comment_status', true );
        $this->default_ping_status      = get_post_meta( $site_ID, 'syn_default_ping_status', true );
        $this->default_cat_status       = get_post_meta( $site_ID, 'syn_default_cat_status', true );

        add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_meta' ), 10, 5 );
        add_action( 'syn_post_pull_new_post', array( __CLASS__, 'save_tax' ), 10, 5 );
        add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_meta' ), 10, 5 );
        add_action( 'syn_post_pull_edit_post', array( __CLASS__, 'update_tax' ), 10, 5 );
    }

    public static function get_client_data() {
        return array( 'id' => 'WP_RSS', 'modes' => array( 'pull' ), 'name' => 'RSS' );
    }

    public function new_post($post_ID) {
        // Not supported
        return false;
    }

    public function edit_post($post_ID, $ext_ID) {
        // Not supported
        return false;
    }

    public function delete_post($ext_ID) {
        // Not supported
        return false;
    }

    public function test_connection() {
        // TODO: Implement test_connection() method.
        return true;
    }

    public function is_post_exists($ext_ID) {
        // Not supported
        return false;
    }

    public static function display_settings($site) {

        $feed_url                   = get_post_meta( $site->ID, 'syn_feed_url', true );
        $default_post_type          = get_post_meta( $site->ID, 'syn_default_post_type', true );
        $default_post_status        = get_post_meta( $site->ID, 'syn_default_post_status', true );
        $default_comment_status     = get_post_meta( $site->ID, 'syn_default_comment_status', true );
        $default_ping_status        = get_post_meta( $site->ID, 'syn_default_ping_status', true );
        $default_cat_status         = get_post_meta( $site->ID, 'syn_default_cat_status', true );

        ?>

        <p>
            <label for="feed_url"><?php echo esc_html__( 'Enter feed URL', 'push-syndication' ); ?></label>
        </p>
        <p>
            <input type="text" class="widefat" name="feed_url" id="feed_url" size="100" value="<?php echo esc_attr( $feed_url ); ?>" />
        </p>
        <p>
            <label for="default_post_type"><?php echo esc_html__( 'Select post type', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_post_type" id="default_post_type" />

            <?php

            $post_types = get_post_types();

            foreach( $post_types as $post_type ) {
                echo '<option value="' . esc_attr( $post_type ) . '"' . selected( $post_type, $default_post_type ) . '>' . esc_html( $post_type )  . '</option>';
            }

            ?>

            </select>
        </p>
        <p>
            <label for="default_post_status"><?php echo esc_html__( 'Select post status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_post_status" id="default_post_status" />

            <?php

            $post_statuses  = get_post_statuses();

            foreach( $post_statuses as $key => $value ) {
                echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $default_post_status ) . '>' . esc_html( $key )  . '</option>';
            }

            ?>

            </select>
        </p>
        <p>
            <label for="default_comment_status"><?php echo esc_html__( 'Select comment status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_comment_status" id="default_comment_status" />
                <option value="open" <?php selected( 'open', $default_comment_status )  ?> >open</option>
                <option value="closed" <?php selected( 'closed', $default_comment_status )  ?> >closed</option>
            </select>
        </p>
        <p>
            <label for="default_ping_status"><?php echo esc_html__( 'Select ping status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_ping_status" id="default_ping_status" />
            <option value="open" <?php selected( 'open', $default_ping_status )  ?> >open</option>
            <option value="closed" <?php selected( 'closed', $default_ping_status )  ?> >closed</option>
            </select>
        </p>
        <p>
            <label for="default_cat_status"><?php echo esc_html__( 'Select category status', 'push-syndication' ); ?></label>
        </p>
        <p>
            <select name="default_cat_status" id="default_cat_status" />
            <option value="yes" <?php selected( 'yes', $default_cat_status )  ?> ><?php echo esc_html__( 'import categories', 'push-syndication' ); ?></option>
            <option value="no" <?php selected( 'no', $default_cat_status )  ?> ><?php echo esc_html__( 'ignore categories', 'push-syndication' ); ?></option>
            </select>
        </p>

        <?php

        do_action( 'syn_after_site_form', $site ); 
    }

    public static function save_settings( $site_ID ) {

        update_post_meta( $site_ID, 'syn_feed_url', esc_url_raw( $_POST['feed_url'] ) );
        update_post_meta( $site_ID, 'syn_default_post_type', $_POST['default_post_type'] );
        update_post_meta( $site_ID, 'syn_default_post_status', $_POST['default_post_status'] );
        update_post_meta( $site_ID, 'syn_default_comment_status', $_POST['default_comment_status'] );
        update_post_meta( $site_ID, 'syn_default_ping_status', $_POST['default_ping_status'] );
        update_post_meta( $site_ID, 'syn_default_cat_status', $_POST['default_cat_status'] );
        return true;

    }

    public function get_post( $ext_ID ) {
        // TODO: Implement get_post() method.
    }

    public function get_posts( $args = array() ) {

        $this->init();
        $this->handle_content_type();

        // hold all the posts
        $posts = array();
        $taxonomy = array( 'cats' => array(), 'tags' => array() );

        foreach( $this->get_items() as $item ) {
            if ( 'yes' == $this->default_cat_status ) {
                $taxonomy = $this->set_taxonomy( $item );
            }

            $post = array(
                'post_title'        => $item->get_title(),
                'post_content'      => $item->get_content(),
                'post_excerpt'      => $item->get_description(),
                'post_type'         => $this->default_post_type,
                'post_status'       => $this->default_post_status,
                'post_date'         => date( 'Y-m-d H:i:s', strtotime( $item->get_date() ) ),
                'comment_status'    => $this->default_comment_status,
                'ping_status'       => $this->default_ping_status,
                'post_guid'         => $item->get_id(),
                'post_category'     => $taxonomy['cats'],
                'tags_input'        => $taxonomy['tags']
            );
            // This filter can be used to exclude or alter posts during a pull import
            $post = apply_filters( 'syn_rss_pull_filter_post', $post, $args, $item );
            if ( false === $post )
                continue;
            $posts[] = $post;
        }

        return $posts;

    }
    
    public function set_taxonomy( $item ) {
        $cats = $item->get_categories();
        $ids = array(
            'cats'    => array(),
            'tags'            => array()
        );

        foreach ( $cats as $cat ) {
            // checks if term exists
            if ( $result = get_term_by( 'name', $cat->term, 'category' ) ) {
                if ( isset( $result->term_id ) ) {
                    $ids['cats'][] = $result->term_id;
                }
            } elseif ( $result = get_term_by( 'name', $cat->term, 'post_tag' ) ) {
                if ( isset( $result->term_id ) ) {
                    $ids['tags'][] = $result->term_id;
                }                    
            } else {
                // creates if not
                $result = wp_insert_term( $cat->term, 'category' );
                if ( isset( $result->term_id ) ) {
                    $ids['cats'][] = $result->term_id;
                }
            }
        }

        // returns array ready for post creation
        return $ids;
    }
    
    public static function save_meta( $result, $post, $site, $transport_type, $client ) {
        if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
            return false;
        }
        $categories = $post['post_category'];
        wp_set_post_terms($result, $categories, 'category', true);
        $metas = $post['postmeta'];
            
        //handle enclosures separately first
        $enc_field = isset( $metas['enc_field'] ) ? $metas['enc_field'] : null;
        $enclosures = isset( $metas['enclosures'] ) ? $metas['enclosures'] : null;
        if ( isset( $enclosures ) && isset ( $enc_field ) ) {
            // first remove all enclosures for the post (for updates) if any
            delete_post_meta( $result, $enc_field);
            foreach( $enclosures as $enclosure ) {
                if (defined('ENCLOSURES_AS_STRINGS') && constant('ENCLOSURES_AS_STRINGS')) {
                    $enclosure = implode("\n", $enclosure);
                }
                add_post_meta($result, $enc_field, $enclosure, false);
            }
    
            // now remove them from the rest of the metadata before saving the rest
            unset($metas['enclosures']);
        }
            
        foreach ($metas as $meta_key => $meta_value) {
            add_post_meta($result, $meta_key, $meta_value, true);
        }
    }
    
    public static function update_meta( $result, $post, $site, $transport_type, $client ) {
        if ( ! $result || is_wp_error( $result ) || ! isset( $post['postmeta'] ) ) {
            return false;
        }
        $categories = $post['post_category'];
        wp_set_post_terms($result, $categories, 'category', true);
        $metas = $post['postmeta'];
            
        // handle enclosures separately first
        $enc_field = isset( $metas['enc_field'] ) ? $metas['enc_field'] : null;
        $enclosures = isset( $metas['enclosures'] ) ? $metas['enclosures'] : null;
        if ( isset( $enclosures ) && isset( $enc_field ) ) {
            // first remove all enclosures for the post (for updates)
            delete_post_meta( $result, $enc_field);
            foreach( $enclosures as $enclosure ) {
                if (defined('ENCLOSURES_AS_STRINGS') && constant('ENCLOSURES_AS_STRINGS')) {
                    $enclosure = implode("\n", $enclosure);
                }
                add_post_meta($result, $enc_field, $enclosure, false);
            }
    
            // now remove them from the rest of the metadata before saving the rest
            unset($metas['enclosures']);
        }
            
        foreach ($metas as $meta_key => $meta_value) {
            update_post_meta($result, $meta_key, $meta_value);
        }
    }
    
    public static function save_tax( $result, $post, $site, $transport_type, $client ) { 
        if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
            return false;
        }
        $taxonomies = $post['tax'];
        foreach ( $taxonomies as $tax_name => $tax_value ) {
            // post cannot be used to create new taxonomy
            if ( ! taxonomy_exists( $tax_name ) ) {
                continue;
            }
            wp_set_object_terms($result, (string)$tax_value, $tax_name, true);
        }
    }
    
    public static function update_tax( $result, $post, $site, $transport_type, $client ) {
        if ( ! $result || is_wp_error( $result ) || ! isset( $post['tax'] ) ) {
            return false;
        }
        $taxonomies = $post['tax'];
        $replace_tax_list = array();
        foreach ( $taxonomies as $tax_name => $tax_value ) {
            //post cannot be used to create new taxonomy
            if ( ! taxonomy_exists( $tax_name ) ) {
                continue;
            }
            if ( !in_array($tax_name, $replace_tax_list ) ) {
                //if we haven't processed this taxonomy before, replace any terms on the post with the first new one
                wp_set_object_terms($result, (string)$tax_value, $tax_name );
                $replace_tax_list[] = $tax_name; 
            } else {
                //if we've already added one term for this taxonomy, append any others
                wp_set_object_terms($result, (string)$tax_value, $tax_name, true);
            }
        }
    }
}
