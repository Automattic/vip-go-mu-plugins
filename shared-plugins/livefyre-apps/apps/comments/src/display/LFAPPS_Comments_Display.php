<?php
use Livefyre\Livefyre;

class LFAPPS_Comments_Display {

    /*
     * Designates what Livefyre's widget is binding to.
     *
     */
    function __construct( $lf_core ) {
        if (LFAPPS_Comments::comments_active()) {
            add_action( 'wp_footer', array( &$this, 'lf_init_script' ) );
            
            // Set comments_template filter to maximum value to always override the default commenting widget
            
            add_filter( 'comments_template', array( &$this, 'livefyre_comments_template' ), $this->lf_widget_priority() );
            add_filter( 'comments_number', array( &$this, 'livefyre_comments_number' ), 10, 2 );
            
            add_shortcode('livefyre_livecomments', array('LFAPPS_Comments_Display', 'init_shortcode'));
        }
    
    }

    /*
     * Helper function to test if comments shouldn't be displayed.
     *
     */
    function livefyre_comments_off() {
    
        return ( get_option('livefyre_apps-livefyre_site_id', '' ) == '' || get_option('livefyre_apps-livefyre_site_key', '') == '');

    }

    /*
     * Gets the Livefyre priority.
     *
     */
    function lf_widget_priority() {

        return intval( get_option( 'livefyre_apps-livefyre_widget_priority', 99 ) );

    }
        
    /*
     * Builds the Livefyre JS code that will build the conversation and load it onto the page. The
     * bread and butter of the whole plugin.
     *
     */
    function lf_init_script() {
    /*  Reset the query data because theme code might have moved the $post gloabl to point 
        at different post rather than the current one, which causes our JS not to load properly. 
        We do this in the footer because the wp_footer() should be the last thing called on the page.
        We don't do it earlier, because it might interfere with what the theme code is trying to accomplish.  */
        wp_reset_query();
        
        global $post, $current_user, $wp_query;
        if ( comments_open() && self::livefyre_show_comments() ) {   // is this a post page?
            Livefyre_Apps::init_auth();
            
            $network = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com' );
            $network = ( $network == '' ? 'livefyre.com' : $network );
        
            $siteId = get_option('livefyre_apps-livefyre_site_id' );
            $siteKey = get_option('livefyre_apps-livefyre_site_key' );
            $network_key = get_option('livefyre_apps-livefyre_domain_key', '');
            $post = get_post();
            $articleId = apply_filters('livefyre_article_id', get_the_ID());
            $title = apply_filters('livefyre_collection_title', get_the_title(get_the_ID()));
            $url = apply_filters('livefyre_collection_url', get_permalink(get_the_ID()));
            $tags = array();
            $posttags = get_the_tags( $wp_query->post->ID );
            if ( $posttags ) {
                foreach( $posttags as $tag ) {
                    array_push( $tags, $tag->name );
                }
            }
            
            $network = Livefyre::getNetwork($network, strlen($network_key) > 0 ? $network_key : null);            
            $site = $network->getSite($siteId, $siteKey);
            
            $collectionMetaToken = $site->buildCollectionMetaToken($title, $articleId, $url, array("tags"=>$tags, "type"=>"livecomments"));
            $checksum = $site->buildChecksum($title, $url, $tags, 'livecomments');
            $strings = apply_filters( 'livefyre_custom_comments_strings', null );
            
            $livefyre_element = 'livefyre-comments';
            $display_template = false;
            LFAPPS_View::render_partial('script', 
                    compact('siteId', 'siteKey', 'network', 'articleId', 'collectionMetaToken', 'checksum', 'strings', 'livefyre_element', 'display_template'), 
                    'comments');   
            
            $ccjs = LFAPPS__PROTOCOL . '://cdn.livefyre.com/libs/commentcount/v1.0/commentcount.js';
            echo '<script type="text/javascript" data-lf-domain="' . esc_attr( $network->getName() ) . '" id="ncomments_js" src="' . esc_attr( $ccjs ) . '"></script>';
            
        }
    }

    /*
     * Debug script that will point customers to what could be potential issues.
     *
     */
    function lf_debug() {
        return false;
        global $post;
        $post_type = get_post_type( $post );
        $article_id = $post->ID;
        $site_id = get_option('livefyre_apps-livefyre_site_id', '' );
        $display_posts = get_option('livefyre_apps-livefyre_display_posts', 'true' );
        $display_pages = get_option('livefyre_apps-livefyre_display_pages', 'true' );
        echo "\n";
        ?>
            <!-- LF DEBUG
            site-id: <?php echo esc_html($site_id) . "\n"; ?>
            article-id: <?php echo esc_html($article_id) . "\n"; ?>
            post-type: <?php echo esc_html($post_type) . "\n"; ?>
            comments-open: <?php echo esc_html(comments_open() ? "true\n" : "false\n"); ?>
            is-single: <?php echo is_single() ? "true\n" : "false\n"; ?>
            display-posts: <?php echo esc_html($display_posts) . "\n"; ?>
            display-pages: <?php echo esc_html($display_pages) . "\n"; ?>
            -->
        <?php
        
    }

    /*
     * The template for the Livefyre div element.
     *
     */
    public static function livefyre_comments_template( ) {
        if(class_exists('LFAPPS_Chat') && !self::livefyre_show_comments() && LFAPPS_Chat::show_chat()) {
            return LFAPPS_Chat::comments_template();
        }
        return dirname( __FILE__ ) . '/comments-template.php';        
    }

    /*
     * Handles the toggles on the settings page that decide which post types should be shown.
     * Also prevents comments from appearing on non single items and previews.
     *
     */
    public static function livefyre_show_comments() {
        
        global $post;
        /* Is this a post and is the settings checkbox on? */
        $display_posts = ( is_single() && get_option('livefyre_apps-livefyre_display_post'));
        /* Is this a page and is the settings checkbox on? */
        $display_pages = ( is_page() && get_option('livefyre_apps-livefyre_display_page'));
        /* Are comments open on this post/page? */
        $comments_open = ( $post->comment_status == 'open' );

        $display = $display_posts || $display_pages;
        $post_type = get_post_type();
        if ( $post_type != 'post' && $post_type != 'page' ) {
            
            $post_type_name = 'livefyre_display_' .$post_type;
            $display = ( get_option('livefyre_apps-'. $post_type_name, 'true' ) == 'true' );
        }

        return $display
            && Livefyre_Apps::is_app_enabled('comments')
            && !is_preview()
            && $comments_open;

    }

    /*
     * Build the Livefyre comment count variable.
     *
     */
    function livefyre_comments_number( $count ) {

        global $post;
        return '<span data-lf-article-id="' . esc_attr($post->ID) . '" data-lf-site-id="' . esc_attr(get_option('livefyre_apps-livefyre_site_id', '' )) . '" class="livefyre-commentcount">'.esc_html($count).'</span>';

    }
    
    /**
     * Run shortcode [livecomments]
     * @param array $atts array of attributes passed to shortcode
     */
    public static function init_shortcode($atts=array()) {
        if(isset($atts['article_id'])) {
            $articleId = $atts['article_id'];
            $title = isset($pagename) ? $pagename : 'Comments (ID: ' . $atts['article_id'];
            global $wp;
            $url = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );
            $tags = array();
        } else {
            global $post;
            if(get_the_ID() !== false) {
                $articleId = apply_filters('livefyre_article_id', get_the_ID());
                $title = apply_filters('livefyre_collection_title', get_the_title(get_the_ID()));
                $url = apply_filters('livefyre_collection_url', get_permalink(get_the_ID()));
                $tags = array();
                $posttags = get_the_tags( $post->ID );
                if ( $posttags ) {
                    foreach( $posttags as $tag ) {
                        array_push( $tags, $tag->name );
                    }
                }
            } else {
                return;
            }
        }
        Livefyre_Apps::init_auth();
        $network = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com' );
        $network = ( $network == '' ? 'livefyre.com' : $network );

        $siteId = get_option('livefyre_apps-livefyre_site_id' );
        $siteKey = get_option('livefyre_apps-livefyre_site_key' );
        $network_key = get_option('livefyre_apps-livefyre_domain_key', '');
        
        $network = Livefyre::getNetwork($network, strlen($network_key) > 0 ? $network_key : null);            
        $site = $network->getSite($siteId, $siteKey);

        $collectionMetaToken = $site->buildCollectionMetaToken($title, $articleId, $url, array("tags"=>$tags, "type"=>"livecomments"));
        $checksum = $site->buildChecksum($title, $url, $tags, 'livecomments');
        $strings = apply_filters( 'livefyre_custom_comments_strings', null );

        $livefyre_element = 'livefyre-comments-'.$articleId;
        $display_template = true;
        return LFAPPS_View::render_partial('script', 
                compact('siteId', 'siteKey', 'network', 'articleId', 'collectionMetaToken', 'checksum', 'strings', 'livefyre_element', 'display_template'), 
                'comments', true);   
    }    
}
