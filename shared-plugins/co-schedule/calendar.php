<?php

$url = 'https://app.coschedule.com/calendar/#/authenticate?blogID=' . urlencode( get_option( 'tm_coschedule_id' ) );
$url .= '&build=' . $this->build;
$url .= "&userID=" . $this->current_user_id;
$render_calendar = true;

// Check permissions
if ( get_option( 'tm_coschedule_token' ) ) {
    if ( current_user_can( 'edit_posts' ) && isset( $_GET['tm_cos_user_token'] ) && ! empty( $_GET['tm_cos_user_token'] ) ) {
        $url .= '&userToken=' . urlencode( $_GET['tm_cos_user_token'] ) . '&redirect=schedule';
    } else if ( current_user_can( 'edit_posts' ) ) {
        $url .= '&redirect=schedule';
    } else {
        include( '_access-denied.php' );
        $render_calendar = false;
    }
} else {
    include( '_missing-token.php' );
    $render_calendar = false;
}

// Render calendar
if ( true == $render_calendar ) { ?>
    <iframe id="CoSiFrame" frameborder="0" border="0" src="<?php echo esc_url( $url ); ?>" width="100%"></iframe>
    <script>
        jQuery(document).ready(function($) {
            $('.update-nag').remove();
            $('#wpfooter').remove();
            $('#wpwrap #footer').remove();
            $('#wpbody-content').css('paddingBottom', 0);


            $('#CoSiFrame').css('min-height',$('#wpbody').height());
            var resize = function() {
                var p = $(window).height() - $('#wpadminbar').height();
                $('#CoSiFrame').height(p);
                $('#CoSiFrame').css('display', 'block');
                $('#CoSiFrame').css('lineHeight', 0);
            }

            resize();
            $(window).resize(function() {
                resize();
            });
        });
    </script>
<?php }