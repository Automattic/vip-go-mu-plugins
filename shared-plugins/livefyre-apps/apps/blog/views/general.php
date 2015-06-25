<div id="lfapps-general-metabox-holder" class="metabox-holder clearfix">
    <?php
    wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
    wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            if (typeof postboxes !== 'undefined')
                postboxes.add_postbox_toggles('plugins_page_livefyre_blog');
        });
    </script>    
    <div class="postbox-container postbox-large">
        <div id="normal-sortables" class="meta-box-sortables ui-sortable">
            <div id="referrers" class="postbox ">
                <div class="handlediv" title="Click to toggle"><br></div>
                <h3 class="hndle"><span><?php esc_html_e('Live Blog Settings', 'lfapps-blog'); ?></span></h3>
                <div class='inside'>
                    <strong>Comments Configuration Options:</strong>
                    <p>There are multiple configuration options available for Live Blog and you can specify them by
                        declaring "liveBlogConfig" variable in your theme header. For example:</p>
                    <p class="code">
                        <?php echo esc_html("<script>
                                     var liveBlogConfig = { readOnly: true; }
                                     </script>"); ?>                                            
                    </p>
                    <p><a target="_blank" href="http://answers.livefyre.com/developers/app-integrations/live-blog/#convConfigObject">Click here</a> for a full explanation of Live Blog options.</p>
                    <strong>Live Blog String Customizations:</strong>
                    <p>String customizations are possible as well through applying WordPress filters. Information on how to implement this is <a target="_blank" href="http://answers.livefyre.com/developers/cms-plugins/wordpress/">found here</a>.</p>
                </div> 
            </div>
        </div>
    </div>
    <div class="postbox-container postbox-large">
        <div id="normal-sortables" class="meta-box-sortables ui-sortable">
            <div id="referrers" class="postbox ">
                <div class="handlediv" title="Click to toggle"><br></div>
                <h3 class="hndle"><span><?php esc_html_e('Live Blog Shortcode', 'lfapps-blog'); ?></span></h3>
                <div class='inside'>
                    <p>To activate Live Blog, you must add a shortcode to your content.</p>
                    <p>The shortcode usage is pretty simple. Let's say we wish to generate a Live Blog inside post content. We could enter something like this
                        inside the content editor:</p>
                    <p class='code'>[livefyre_liveblog]</p>
                    <p>Live Blog streams are separated by the "Article ID" and if not specified it will use the current post ID. You can define the "Article ID"
                        manually like this:</p>
                    <p class='code'>[livefyre_liveblog article_id="123"]</p>
                </div> 
            </div>
        </div>
    </div>     
</div>