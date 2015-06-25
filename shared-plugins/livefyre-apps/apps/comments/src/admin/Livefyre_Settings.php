<?php
/*
Author: Livefyre, Inc.
Version: 4.2.0
Author URI: http://livefyre.com/
*/

/*
 * Heler methods for the setting's page PHP files.
 * These are shared between multiple settings pages.
 *
 */
class Livefyre_Settings {

    /*
     * Updates posts to have allow comments turned off.
     *
     */
    function update_posts ( $id, $post_type ) {
        wp_update_post( array( 'ID' => $id, 'comment_status' => 'open' ) );
    }

    /*
     * Wrapper to grab posts by type.
     *
     */
    function select_posts ( $post_type ) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 50
        );

        add_filter( 'posts_where', array( $this, 'posts_where' ) );
        $query = new WP_Query( $args );
        remove_filter( 'posts_where', array( $this, 'posts_where' ) );
        $posts = $query->posts;
        return $posts;
    }

    function posts_where( $where_clause ) {
        global $wpdb;
        return $where_clause .= " AND comment_status = 'closed'";
    }

    /*
     * Builds a list of posts that have comments allowed turned off.
     *
     */
    function display_no_allows ( $post_type, $list ) {
        ?>
        <div id="fyreallowheader">
            <h1>Post:</h1>
        </div>
        <ul>
            <?php
            foreach ( $list as $ncpost ) {
                echo '<li>ID: <span>' .esc_html($ncpost->ID). "</span>  Title:</span> <span><a href=" .get_permalink($ncpost->ID). ">" .esc_html($ncpost->post_title). "</a></span>";
                echo '<a href="?page=livefyre&allow_comments_id=' .esc_url($ncpost->ID). '" class="fyreallowbutton">Enable</a></li>';
            }
        ?>
        <ul>
        <?php
    }

    /*
     * Gets the current status of the settings.
     *
     */
    function get_fyre_status ( $plugins_count, $disabled_posts_count, $disabled_pages_count, $import_status ) {
    
        if ( $this->get_total_errors( $plugins_count, $disabled_posts_count, $disabled_pages_count, $import_status ) == 0 ) {
            return Array('All systems go!', 'green');
        }
        if ( $plugins_count >= 1 ) {
            return Array('Error, conflicting plugins', 'red');
        }
        return Array('Warning, potential issues', 'yellow');

    }

    /*
     * Grabs the total number of errors that are occuring on the status page.
     *
     */
    function get_total_errors( $plugins_count, $disabled_posts_count, $disabled_pages_count, $import_status ) {

        return ( $plugins_count + $disabled_pages_count + $disabled_posts_count + ( $import_status != 'complete' ? 1 : 0) );

    }

    /*
     * Helper method to check which language is selected in the settings.
     *
     */
    function checkSelected( $option, $value ) {
        
        if ( get_option( $option, '' ) == $value ) {
            return 'selected="selected"';
        }
        return '';
    }

}
