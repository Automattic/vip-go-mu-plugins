<div id="lfapps-general-metabox-holder" class="metabox-holder clearfix">
    <?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
    wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            if (typeof postboxes !== 'undefined')
                postboxes.add_postbox_toggles('plugins_page_livefyre_sidenotes');
        });
    </script>    
    <div class="postbox-container postbox-large">
        <div id="normal-sortables" class="meta-box-sortables ui-sortable">
            <div id="referrers" class="postbox ">
                <div class="handlediv" title="Click to toggle"><br></div>
                <h3 class="hndle"><span><?php esc_html_e('Livefyre Sidenotes Settings', 'lfapps-sidenotes'); ?></span></h3>
                <form name="livefyre_sidenotes_general" id="livefyre_sidenotes_general" action="options.php" method="POST">
                    <?php settings_fields('livefyre_apps_settings_sidenotes'); ?>
                    <div class='inside'>
                        <table cellspacing="0" class="lfapps-form-table">
                            <tr>
                                <th align="left" scope="row">
                                    <?php esc_html_e('Enable Sidenotes on', 'lfapps-sidenotes'); ?><br/>
                                    <span class="info"><?php esc_html_e('(Select the types of posts on which you wish to enable Livefyre Sidenotes)', 'lfapps-sidenotes'); ?></span>
                                </th>
                                <td align="left" valign="top">
                                    <?php
                                    $excludes = array( '_builtin' => false );
                                    $post_types = get_post_types( $args = $excludes );
                                    $post_types = array_merge(array('post'=>'post', 'page'=>'page'), $post_types);
                                    foreach ($post_types as $post_type ) {
                                        $post_type_name = 'livefyre_sidenotes_display_' .$post_type;
                                        $checked = '';
                                        if(get_option('livefyre_apps-'.$post_type_name)) {
                                            $checked = 'checked';
                                        } 
                                        ?>
                                        <input type="checkbox" id="<?php echo esc_attr('livefyre_apps-'.$post_type_name); ?>" name="<?php echo esc_attr('livefyre_apps-'.$post_type_name); ?>" value="true" <?php echo $checked; ?>/>
                                        <label for="<?php echo esc_attr('livefyre_apps-'.$post_type_name); ?>"><?php echo esc_html_e($post_type, 'lfapps-sidenotes'); ?></label><br/>
                                        <?php
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php if(get_option('livefyre_apps-package_type') === 'enterprise'): ?>
                            <tr>
                                <th align="left" scope="row">
                                    <?php esc_html_e('Selectors', 'lfapps-sidenotes'); ?><br/>
                                    <span class="info"><?php esc_html_e('(The selectors option is used to specify which content can be Sidenoted. More information can be found ', 'lfapps-sidenotes'); ?> <a href="http://answers.livefyre.com/developers/app-integrations/sidenotes/#Selectors" target="_blank">here</a>)</span>
                                </th>
                            </tr>
                            <tr>
                                <td align="left" valign="top">
                                    <textarea id='livefyre_apps-livefyre_sidenotes_selectors' name='livefyre_apps-livefyre_sidenotes_selectors' cols='60' rows='6'><?php echo esc_html(get_option('livefyre_apps-livefyre_sidenotes_selectors')); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td colspan='2'>
                                    <strong>Sidenotes Configuration Options:</strong>
                                    <p>There are multiple other configuration options available for Livefyre Sidenotes and you can specify them by
                                    declaring "livefyreSidenotesConfig" variable in your theme header. For example:</p>
                                    <blockquote class="code">
                                    <?php echo esc_html("<script>
                                         var livefyreSidenotesConfig = { iconVisibility: \"hover\"; }
                                         </script>"); ?>                                            
                                    </blockquote>
                                    <p><a target="_blank" href="http://answers.livefyre.com/developers/app-integrations/sidenotes/">Click here</a> for a full explanation of Livefyre Sidenotes options.</p>
                                    <strong>Sidenotes String Customizations:</strong>
                                    <p>String customizations are possible as well through applying WordPress filters. Information on how to implement this is <a target="_blank" href="http://answers.livefyre.com/developers/cms-plugins/wordpress/">found here</a>.</p>
                                </td>
                            </tr>
                            <?php endif; ?>                            
                        </table>
                    </div>
                    <div id="major-publishing-actions">									
                        <div id="publishing-action">
                            <?php submit_button(); ?>
                        </div>
                        <div class="clear"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>     
</div>