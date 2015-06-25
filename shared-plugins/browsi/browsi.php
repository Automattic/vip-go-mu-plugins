<?php
/*
 Plugin Name: Brow.si
 Plugin URI: http://www.getbrowsi.com
 Description: Reinventing mobile browsing!
 Author: MySiteApp Ltd.
 Version: 0.2
 Author URI: http://brow.si
 */

/** Options key **/
define( 'BROWSI_OPTIONS' , 'browsi_options' );
/** Base Brow.si url **/
define( 'BROWSI_BASE_URL' , 'js.brow.si' );

/**
 * Returns the defined site_id of Brow.si.
 * @param $notFound mixed   Not found marker
 * @return  mixed   The brow.si site id, or the $notFound marker if it isn't defined
 */
function browsi_get_site_id( $notFound = false ) {
    $options = get_option( BROWSI_OPTIONS );
    return is_array( $options ) && array_key_exists( 'site_id' , $options ) && strlen( $options['site_id'] ) > 0 ?
        $options['site_id'] : $notFound;
}

/**
 * 'wp_footer' hook - inject Brow.si's javascript code to the page's footer.
 * @note    The javascript is loaded asynchronously and only after the rest of the page is loaded, so no need to worry
 *          about delaying the page load.
 * @note    Brow.si is loaded only for supported mobile devices. The loader (br.js) returns the right javascript for the
 *          device based on the requester User-Agent, and "204 No Content" on non-supported devices (like Desktop).
 */
function browsi_footer() {
    $site_id = browsi_get_site_id();
?><script type="text/javascript">
     (function(w, d){
    <?php if ($site_id): ?>
        w['_brSiteId'] = '<?php echo esc_js($site_id) ?>';
    <?php endif; ?>
        w['_brPlatform'] = ['wordpress', '<?php echo esc_js( get_bloginfo( 'version' ) ) ?>'];
        function br() {
            var i='browsi-js'; if (d.getElementById(i)) {return;}
            var siteId = /^[a-zA-Z0-9]{1,7}$/.test(w['_brSiteId']) ? w['_brSiteId'] : null;
            var js=d.createElement('script'); js.id=i; js.async=true;
            js.src='//<?php echo BROWSI_BASE_URL ?>/' + ( siteId != null ? siteId + '/' : '' ) + 'br.js';
            (d.head || d.getElementsByTagName('head')[0]).appendChild(js);
        }
        d.readyState == 'complete' ? br() :
            ( w.addEventListener ? w.addEventListener('load', br, false) : w.attachEvent('onload', br) );
    })(window, document);
</script>
<?php
}


/********* Admin functions *********/
/**
 * Adds a text input for the Brow.si Site Id editing
 */
function browsi_admin_options_siteid() {
    $site_id = browsi_get_site_id( '' );
?><input id="browsi_site_id" name="<?php echo esc_attr( BROWSI_OPTIONS ) ?>[site_id]" size="40" type="text" value="<?php echo esc_attr( $site_id ) ?>" /><br/>
    <em>Don't have a Site ID yet? Register one <a href='http://l.brow.si/_wordpress' target='_blank'>HERE</a></em><?php
}

/**
 * Options page
 */
function browsi_admin_options_page() {
?><div class="wrap">
    <form action="options.php" method="post">
        <?php
            settings_fields( BROWSI_OPTIONS );
            do_settings_sections( __FILE__ );
            submit_button( __('Save Changes') );
        ?>
    </form>
</div><?php
}

/**
 * 'admin_notices' hook - displays a notice for the missing Brow.si Site Id
 */
function browsi_admin_notice() {
    $page = isset( $_GET['page'] ) ? $_GET['page'] : null;
    if ( $page == "browsi-settings" || !current_user_can( 'manage_options' ) ) {
        return;
    }

    if (browsi_get_site_id() === false){
    ?><div id="wrap"><div class="updated">
        <p>In order to customise <strong>Brow.si</strong> and get some cool analytics data about your mobile site usage,
                you need to <a href="<?php echo esc_url( admin_url( 'admin.php?page=browsi-settings' ) ) ?>">update</a>
                your Site ID.</p>
    </div></div><?php
    }
}

/**
 * 'admin_menu' hook - adds Brow.si under the 'Settings' panel
 */
function browsi_admin_menu() {
    add_options_page( __( 'Brow.si', 'brow.si' ) , __( 'Brow.si', 'brow.si' ) , 'manage_options' , 'browsi-settings' , 'browsi_admin_options_page' );
}

/**
 * 'admin_init' hook - adds Brow.si configuration settings (currently just the site id)
 */
function browsi_admin_init() {
    register_setting( BROWSI_OPTIONS , BROWSI_OPTIONS , 'wp_kses_post' );
    add_settings_section( 'main_section' , esc_html__( 'Brow.si Settings' , 'browsi' ) , '__return_false' , __FILE__ );
    add_settings_field( 'browsi_site_id' , esc_html__( 'Brow.si Site Id', 'browsi' ), 'browsi_admin_options_siteid' , __FILE__ , 'main_section' );
}

/** Inject Browsi javascript on the page's footer */
add_action( 'wp_footer' , 'browsi_footer' , 1000 );

/** Add Browsi to the "Settings" menu */
add_action( 'admin_menu' , 'browsi_admin_menu' );
/** Init Browsi admin options */
add_action( 'admin_init' , 'browsi_admin_init' );
/** Show notice if Brow.si Site Id isn't configured */
add_action( 'admin_notices', 'browsi_admin_notice' );