<?php 
    global $livefyre, $wp_query;
    if ( $livefyre->Display->livefyre_show_comments() ) {
        // It is possible that the ID of the queried object is for a revision. Be sure to get it back to the originating post.
        if ( $parent_id = wp_is_post_revision( $wp_query->get_queried_object_id() ) ) {
            $post_id = $parent_id;
        } else {
            $post_id = $post->ID;
        }
        $transient_key = 'livefyre_bootstrap_' . $post_id;
        $cached_html = get_transient( $transient_key );
        if ( !$cached_html ) {
            $cached_html = '';
            $url = $livefyre->bootstrap_url_v3 . '/' . base64_encode($post_id) . '/bootstrap.html';
            $result = $livefyre->lf_domain_object->http->request( $url, array( 'method' => 'GET' ) );
            if ( is_array( $result ) && isset( $result['response'] ) && $result[ 'response' ][ 'code' ] == 200 && strlen( $result[ 'body' ] ) > 0 ) {
                $cached_html = $result[ 'body' ];
            }
            // v3 bootstrap currently returns a valid HTML document,
            // so we tease out the relevent pieces
            $v3_head = "<!DOCTYPE html>\n<html>\n<head>\n";
            $v3_head_replace = '';
            
            $v3_body = "</script>\n</head>\n<body>";
            $v3_body_replace = '</script>';
            
            $v3_foot = "</body>\n<html>";
            $v3_foot_replace = '';
            
            if (strpos($cached_html, $v3_head) !== false) {
                $cached_html = str_replace( $v3_head, $v3_head_replace, $cached_html );
                $cached_html = str_replace( $v3_body, $v3_body_replace, $cached_html );
                $cached_html = str_replace( $v3_foot, $v3_foot_replace, $cached_html );
            }
            
            // now check out the rest of the HTML, make sure it looks valid.
            if (strpos($cached_html, '<div') === false) {
                // if we don't see the required container,
                // something is wrong with the response
                $cached_html = '';
            }
            set_transient( $transient_key , $cached_html, 300 );
        }
        echo '<div id="comments">' . $cached_html . '</div>';
    }

    echo "<!-- Livefyre Comments Version: " . $livefyre->plugin_version."-->";
    if ( pings_open() ) {
        $num_pings = count( get_comments( array( 'post_id' => $post->ID, 'type' => 'pingback', 'status' => 'approve' ) ) ) + count( get_comments( array( 'post_id'=>$post->ID, 'type'=>'trackback', 'status'=>'approve' ) ) );
        if ( $num_pings > 0 ):
        ?>
        <div style="font-family: arial !important;" id="lf_pings">
            <h3>Trackbacks</h3>
            <ol class="commentlist">
                <?php wp_list_comments( array( 'type'=>'pings', 'reply_text' => '' ) ); ?>
            </ol>
        </div>
        <?php endif;
    }