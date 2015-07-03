<style>
    <?php if(get_option('livefyre_apps-package_type') === 'community'): ?>
    .enterprise-only {display: none;}
    <?php else: ?>
    .community-only {display: none;}
    <?php endif; ?>
</style>
<div id="lfapps-general-metabox-holder" class="metabox-holder clearfix">
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
                    <h3 class="hndle"><span><?php esc_html_e('Livefyre Access Details', 'lfapps'); ?></span></h3>
                    <form name="livefyre_apps_general" id="livefyre_apps_general" action="options.php" method="POST">
                        <?php settings_fields('livefyre_apps_settings_general'); ?>
                        <div class='inside'>
                            <table cellspacing="0" class="lfapps-form-table <?php echo get_option('livefyre_apps-package_type') === 'community' ? 'lfapps-form-table-left' : ''; ?>">
                                <tbody>
                                    <tr>
                                        <th align="left" scope="row"><?php esc_html_e('Site ID', 'lfapps'); ?></th>
                                        <td align="left">
                                            <input id="livefyre_site_id" name="livefyre_apps-livefyre_site_id" type="text" size="15" value="<?php echo esc_attr(get_option('livefyre_apps-livefyre_site_id')); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th align="left" scope="row"><?php esc_html_e('Site Key', 'lfapps'); ?></th>
                                        <td align="left">
                                            <input id="livefyre_site_key" name="livefyre_apps-livefyre_site_key" type="text" value="<?php echo esc_attr(get_option('livefyre_apps-livefyre_site_key')); ?>" class='regular-text'>
                                        </td>
                                    </tr>
                                    <tr class="enterprise-only">
                                        <th align="left" scope="row"><?php esc_html_e('Network Name', 'lfapps'); ?></th>
                                        <td align="left">
                                            <input id="livefyre_domain_name" name="livefyre_apps-livefyre_domain_name" type="text" value="<?php echo esc_attr(get_option('livefyre_apps-livefyre_domain_name')); ?>" class='regular-text'>
                                        </td>
                                    </tr>
                                    <tr class="enterprise-only">
                                        <th align="left" scope="row"><?php esc_html_e('Network Key', 'lfapps'); ?></th>
                                        <td align="left">
                                            <input id="livefyre_domain_key" name="livefyre_apps-livefyre_domain_key" type="text" value="<?php echo esc_attr(get_option('livefyre_apps-livefyre_domain_key')); ?>" class='regular-text'>
                                        </td>
                                    </tr>
                                    <tr class="enterprise-only">
                                        <th align="left" scope="row"><?php esc_html_e('User Auth Type', 'lfapps'); ?></th>
                                        <td align="left" class="spacer">
                                            <input id="wp_auth_type_wordpress" name="livefyre_apps-auth_type" type="radio" value="wordpress" <?php echo get_option('livefyre_apps-auth_type') === 'wordpress' ? 'checked' : ''; ?>>
                                            <label for='wp_auth_type_wordpress'><?php esc_html_e('Native Wordpress', 'lfapps'); ?></label>
                                            <input id="wp_auth_type_custom" name="livefyre_apps-auth_type" type="radio" value="custom" <?php echo get_option('livefyre_apps-auth_type') === 'custom' ? 'checked' : ''; ?>>
                                            <label for='wp_auth_type_custom'><?php esc_html_e('Custom/LFEP', 'lfapps'); ?></label>
                                            <input id="wp_auth_type_delegate" name="livefyre_apps-auth_type" type="radio" value="auth_delegate" <?php echo get_option('livefyre_apps-auth_type') === 'auth_delegate' ? 'checked' : ''; ?>>
                                            <label for='wp_auth_type_delegate'><?php esc_html_e('Legacy Delegate', 'lfapps'); ?></label>
                                        </td>
                                    </tr>
                                    <tr class="enterprise-only authdelegate-only">
                                        <th align="left" scope="row"><?php esc_html_e('AuthDelegate Name', 'lfapps'); ?></th>
                                        <td align="left">
                                            <input id="livefyre_auth_delegate_name" name="livefyre_apps-livefyre_auth_delegate_name" type="text" value="<?php echo esc_attr(get_option('livefyre_apps-livefyre_auth_delegate_name')); ?>" class='regular-text'>
                                        </td>
                                    </tr>
                                    <tr class="enterprise-only">
                                        <th align="left" scope="row"><?php esc_html_e('Environment', 'lfapps'); ?></th>
                                        <td align="left">
                                            <input id="livefyre_environment" name="livefyre_apps-livefyre_environment" type="checkbox" value="production" <?php echo get_option('livefyre_apps-livefyre_environment') == 'production' ? 'checked' : ''; ?>>
                                            <label for="livefyre_environment"><?php esc_html_e('Check this if you are using Production Credentials', 'lfapps'); ?></label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="enterprise-only"><i>Hooking in LFEP is documented <a href="http://docs.livefyre.com/developers/identity-integration/enterprise-profiles/">here.</a></i></p>
                            <?php if(get_option('livefyre_apps-package_type') === 'community'): ?>
                            <div class="lfapps-community-signup">
                                <p><?php esc_html_e('New to Livefyre or forgotten your Site ID/Key?', 'lfapps'); ?><br/>
                                    <a href="http://livefyre.com/installation/logout/?site_url=<?php echo urlencode(home_url())?>&domain=rooms.livefyre.com&version=4&type=wordpress&lfversion=apps&postback_hook=<?php urlencode(home_url())?>&transport=http" target="_blank"><?php esc_html_e('Click here', 'lfapps'); ?></a> and we can help!</p>
                            </div>
                            <div class="clear"></div>
                            <?php endif; ?>
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
        <div class="postbox-container">
            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                <div id="referrers" class="postbox ">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3 class="hndle"><span><?php esc_html_e('Livefyre App Management', 'lfapps'); ?></span></h3>
                    <form name="livefyre_apps_management" id="livefyre_apps_management" action="options.php" method="POST">
                        <?php settings_fields('livefyre_apps_settings_apps'); ?>
                        <div class='inside'>
                            <p><?php esc_html_e('Using the options below you can enable/disable the Livefyre Apps available to you.', 'lfapps'); ?></p>
                            <div class='lfapps-appmgt-row clearfix'>
                                <div class='lfapps-appmgt-box'>
                                    <label for='lfapps_comments_enable'>
                                        <?php
                                        $icon_src = Livefyre_Apps::is_app_enabled('comments') ? 'lf-comments-icon.png' : 'lf-comments-icon-grey.png';
                                        ?>
                                        <img id="lfapps_comments_icon" src="<?php echo esc_url( LFAPPS__PLUGIN_URL . 'assets/img/' . $icon_src ); ?>"/>
                                    </label>
                                    <div class="lfapps-appmgt-controls">
                                        <input id="lfapps_comments_enable" name="livefyre_apps-apps[]" type="checkbox" value="comments" <?php echo Livefyre_Apps::is_app_enabled('comments') ? 'checked' : ''; ?>>
                                        <label for='lfapps_comments_enable'>
                                            <span><?php esc_html_e('Comments™', 'lfapps'); ?></span>
                                        </label>
                                        <p><a target="_blank" href="http://web.livefyre.com/comments/">Click here</a> for more information.</p>
                                    </div>
                                </div>
                                <div class='lfapps-appmgt-box'>
                                    <label for='lfapps_sidenotes_enable'>
                                        <?php
                                        $icon_src = Livefyre_Apps::is_app_enabled('sidenotes') ? 'lf-sidenotes-icon.png' : 'lf-sidenotes-icon-grey.png';
                                        ?>
                                        <img id="lfapps_sidenotes_icon" src="<?php echo esc_url( LFAPPS__PLUGIN_URL . 'assets/img/' . $icon_src ); ?>"/>
                                    </label>
                                    <div class="lfapps-appmgt-controls">
                                        <input id="lfapps_sidenotes_enable" name="livefyre_apps-apps[]" type="checkbox" value="sidenotes" <?php echo Livefyre_Apps::is_app_enabled('sidenotes') ? 'checked' : ''; ?>>
                                        <label for='lfapps_sidenotes_enable'>
                                            <span><?php esc_html_e('Sidenotes™', 'lfapps'); ?></span>
                                        </label>
                                        <p><a target="_blank" href="http://web.livefyre.com/streamhub/#liveSidenotes">Click here</a> for more information.</p>
                                    </div>
                                </div>
                                <div class='lfapps-appmgt-box enterprise-only'>
                                    <label for='lfapps_blog_enable'>
                                        <?php
                                        $icon_src = Livefyre_Apps::is_app_enabled('blog') ? 'lf-blog-icon.png' : 'lf-blog-icon-grey.png';
                                        ?>
                                        <img id="lfapps_blog_icon" src="<?php echo esc_url( LFAPPS__PLUGIN_URL . 'assets/img/' . $icon_src ); ?>"/>
                                    </label>
                                    <div class="lfapps-appmgt-controls">
                                        <input id="lfapps_blog_enable" name="livefyre_apps-apps[]" type="checkbox" value="blog" <?php echo Livefyre_Apps::is_app_enabled('blog') ? 'checked' : ''; ?>>
                                        <label for='lfapps_blog_enable'>
                                            <span><?php esc_html_e('Live Blog™', 'lfapps'); ?></span>
                                        </label>
                                        <p><a target="_blank" href="http://web.livefyre.com/streamhub/#liveBlog">Click here</a> for more information.</p>
                                    </div>
                                </div>
                                <div class='lfapps-appmgt-box enterprise-only'>
                                    <label for='lfapps_chat_enable'>
                                        <?php
                                        $icon_src = Livefyre_Apps::is_app_enabled('chat') ? 'lf-chat-icon.png' : 'lf-chat-icon-grey.png';
                                        ?>
                                        <img id="lfapps_chat_icon" src="<?php echo esc_url( LFAPPS__PLUGIN_URL . 'assets/img/' . $icon_src ); ?>"/>
                                    </label>
                                    <div class="lfapps-appmgt-controls">
                                        <input id="lfapps_chat_enable" name="livefyre_apps-apps[]" type="checkbox" value="chat" <?php echo Livefyre_Apps::is_app_enabled('chat') ? 'checked' : ''; ?>>
                                        <label for='lfapps_chat_enable'>
                                            <span><?php esc_html_e('Chat™', 'lfapps'); ?></span>
                                        </label>
                                        <p><a target="_blank" href="http://web.livefyre.com/streamhub/#liveChat">Click here</a> for more information.</p>
                                    </div>
                                </div>
                            </div>
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
    <div class='postbox-side'>
        <div class="postbox-container lfapps-environment-container">
            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                <div id="referrers" class="postbox ">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3 class="hndle"><span><?php esc_html_e('Environment Type', 'lfapps'); ?></span></h3>

                    <div class='inside'>
                        <p><?php esc_html_e('You are currently using:', 'lfapps'); ?></p>
                        <?php if(get_option('livefyre_apps-package_type') === 'community'): ?>
                        <span class="lfapps-community"><?php esc_html_e('Community', 'lfapps'); ?></span>
                        <?php else: ?>
                        <span class="lfapps-enterprise"><?php esc_html_e('Enterprise', 'lfapps'); ?></span>
                        <?php endif; ?>
                        (<a href="#" class="lfapps-change-env-btn"><?php esc_html_e('Change?', 'lfapps'); ?></a>)
                    </div>
                </div>
            </div>
        </div>
        <div class="postbox-container lfapps-links">
            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                <div id="referrers" class="postbox ">
                    <div class="handlediv" title="Click to toggle"><br></div>
                    <h3 class="hndle"><span><?php esc_html_e('Links', 'lfapps'); ?></span></h3>
                    <?php
                        $package_type = get_option('livefyre_apps-package_type');
                        $network = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com');
                        $network_stub = split('\.', $network);
                        $network_stub = $network_stub[0];
                    ?>
                    <div class='inside'>
                        <a href= <?php echo ($package_type === 'community' || $network === 'livefyre.com') ? "http://livefyre.com/admin" : "https://" . $network_stub . ".admin.fyre.co/v3/content" ?> target="_blank">Livefyre Admin</a>
                        <br/>
                        <a href="http://support.livefyre.com" target="_blank">Livefyre Support</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php add_thickbox(); ?>

<?php if(!get_option('livefyre_apps-initial_modal_shown', false)): ?>
<script>
    jQuery(document).ready(function(){
        tb_show("","#TB_inline?inlineId=lfapps-initial-modal&width=680&height=310");
    });
</script>
<?php endif; ?>
<div id='lfapps-initial-modal' style='display:none'>
    <?php LFAPPS_View::render_partial('initial_modal'); ?>
</div>
