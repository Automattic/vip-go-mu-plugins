<?php
/*
 * Template file for Livefyre's div element. 
 */

global $livefyre, $wp_query, $post;
if (LFAPPS_Chat::show_chat() ) {
    echo '<div id="livefyre-chat"></div>';
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
