<div id="lfapps-plugin-conflict-metabox-holder" class="metabox-holder clearfix">
    <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
    wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            if (typeof postboxes !== 'undefined')
                postboxes.add_postbox_toggles('plugins_page_livefyre_apps');
        });
    </script>    
    
    <div class='postbox-large'>
        <div class="postbox-container">
            <div id="normal-sortables" class="meta-box-sortables ui-sortable">                
                <div id="referrers" class="postbox">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3 class="hndle"><span><?php esc_html_e('Livefyre Plugin Conflict', 'lfapps'); ?></span></h3>
                    <div class="inside">
                        <p>The following plugins cannot run at the same time as Livefyre Apps. Please de-activate these plugins before you can use Livefyre Apps.</p>
                        <ul>
                            <?php foreach(Livefyre_Apps::get_conflict_plugins() as $plugin): ?>
                            <li><?php echo esc_html('- ' . $plugin); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p>Check your plugin settings to make sure that Livefyre Comments and Livefyre Sidenotes are deactivated.</p>
                    </div>
                </div>
            </div>
        </div>        
    </div>
    <div class='postbox-side'>        
        <div class="postbox-container lfapps-links">
            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                <div id="referrers" class="postbox ">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3 class="hndle"><span><?php esc_html_e('Links', 'lfapps'); ?></span></h3>
                    <div class='inside'>
                        <a href="http://livefyre.com/admin" target="_blank">Livefyre Admin</a>
                        <br/>
                        <a href="http://support.livefyre.com" target="_blank">Livefyre Support</a>
                    </div>
                </div>
            </div>
        </div>        
    </div>        
</div>