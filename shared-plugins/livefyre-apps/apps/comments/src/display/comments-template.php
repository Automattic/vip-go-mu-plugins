<?php
/*
 * Template file for Livefyre's div element. Decides if cached Livefyre HTML
 * exists and uses it.
 *
 */

global $livefyre, $wp_query, $post;
if ( LFAPPS_Comments_Display::livefyre_show_comments() ) {
    if ( $parent_id = wp_is_post_revision( $wp_query->post->ID ) ) {
        $post_id = $parent_id;
    } else {
        $post_id = $post->ID;
    }
    $url = LFAPPS_Comments_Core::$bootstrap_url_v3 . '/' . base64_encode($post_id) . '/bootstrap.html';
    $lfHttp = new LFAPPS_Http_Extension();
    $result = $lfHttp->request( $url );
    $cached_html = '';
    if ( $result['response']['code'] == 200 ){
        $cached_html = $result['body'];
        $cached_html = preg_replace( '(<script>[\w\W]*<\/script>)', '', $cached_html );
    }
    echo '<div id="livefyre-comments">' . wp_kses_post( $cached_html ) . '</div>';
}

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

?>
