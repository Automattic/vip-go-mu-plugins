<?php $import_status = get_option('livefyre_apps-livefyre_import_status'); ?>
<?php if ($import_status !== 'complete'): ?>
    <?php LFAPPS_View::render_partial('import_script', array(), 'comments'); ?>
    <div class="postbox-container postbox-large">
        <div id="normal-sortables" class="meta-box-sortables ui-sortable">
            <div id="referrers" class="postbox livefyre-import-postbox">
                <div class="handlediv" title="Click to toggle"><br></div>
                <h3 class="hndle"><span><?php esc_html_e('Livefyre Import Status', 'lfapps-comments'); ?></span></h3>
                <div class='inside'>
                <?php if ($import_status === 'error'): ?>
                    <h4><?php esc_html_e('Status:', 'lfapps-comments'); ?> <span><?php esc_html_e('Failed', 'lfapps-comments'); ?> </span></h4>

                    <?php echo "<p>Message: " . esc_html(get_option('livefyre_apps-livefyre_import_message', '')) . "</p>" ?>
                    <p>Aw, man. It looks like your comment data gave our importer a hiccup and the import process was derailed. But have no fear, the Livefyre support team is here to help. 
                        If you wouldn't mind following the instructions below, our support team would be more than happy to work with you to get this problem squared away before you know it!
                        E-mail Livefyre at <a href="mailto:support@livefyre.com">support@livefyre.com</a> with the following:</p>
                    <p>1. In your WP-Admin panel, click "Tools"<br />
                        2. Click "Export"<br />
                        3. Be sure that "All Content" is selected, and then click "Download Export File"<br />
                        4. Attach and e-mail the .XML file that WordPress created to support@livefyre.com along with the URL of your blog.<br /><br />
                        <strong>Note:</strong> If you have multiple sites on your WordPress that you would like to import comments for, please make note of that
                        in the email.</p>
                    <p>Livefyre will still be active and functional on your site, but your imported comments will not be displayed in the comment stream.</p>
                    <a href="<?php echo esc_url(Livefyre_Apps_Admin::get_page_url('livefyre_apps_comments')); ?>&hide_import_message=true" class="button-primary">Got it, Thanks!</a>
                <?php elseif($import_status === 'uninitialized'): ?>
                    <?php if ( wp_count_comments()->total_comments > 100000 ): ?>                        
                        <h4>Status: <span>Pending</span></h4>
                        <p>Oh snap, it looks like you're pretty popular! You've got a really large amount of comment data that will need some extra attention from our support team to make sure that all of your comments end up properly imported. If you wouldn't mind dropping a quick e-mail to <a href="mailto:support@livefyre.com">support@livefyre.com</a> 
                        with your site's URL, we'll get the ball rolling on completing your import and making sure that you're well taken care of.</p>
                    <?php else: ?>
                        <h4>Status: <span>Uninitialized</span></h4>
                        <p>You've got some comment data that hasn't been imported into Livefyre yet, please click the 'Import Comments' button below.
                        As your comments are being imported the status will be displayed here.
                        If Livefyre is unable to import your data, you can still use the plugin, but your existing comments will not be displayed in the Livefyre comment widget. 
                        Please e-mail <a href="mailto:support@livefyre.com">support@livefyre.com</a> with any issues as we'd be more than happy to help you resolve them.</p>
                        <a href="<?php echo esc_url(Livefyre_Apps_Admin::get_page_url('livefyre_apps_comments')); ?>&livefyre_import_begin=true" class="button-primary">Import Comments</a>
                    <?php endif; ?>
                <?php else: ?>    
                    <script type="text/javascript">
                        livefyre_start_ajax(1000);
                    </script>
                    <h4>Status: <span>Running</span></h4>
                    <p>Depending on the amount of data imported, your comment data may not be immediately displayed after your import completes. If you have any questions,
                        please e-mail <a href="mailto:support@livefyre.com">support@livefyre.com.</a></p>
                    <div id="gears">
                        <img src="<?php echo LFAPPS__PLUGIN_URL . '/apps/comments/assets/img/gear1.png';?>" class="gear1" alt="" />
                        <img src="<?php echo LFAPPS__PLUGIN_URL . '/apps/comments/assets/img/gear2.png';?>" class="gear2" alt="" />
                        <img src="<?php echo LFAPPS__PLUGIN_URL . '/apps/comments/assets/img/gear3.png';?>" class="gear3" alt="" />
                    </div>
                    <p id="livefyre-import-text">Warming up the engine...</p>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>