<?php if($this->access_key): ?>
    <div id="nc-metabox-wrap">
        <div class="nc-copyright">
            <p>Powered by <a href="http://www.newscred.com/" class="company" title="NewsCred Inc">NewsCred Inc.</a> | <a
                href="javascript:;" title="Terms of Use">Terms of Use</a> | <a href="javascript:;" title="FAQ">FAQ</a></p>
        </div>
    </div>

    <input type="hidden" name="nc-post-author" id="nc-post-author" value=""/>
    <input type="hidden" name="nc-current-tab" id="nc-current-tab" value=""/>
    <input type="hidden" name="nc-add-post" id="nc-add-post" value=""/>

    <input type="hidden" name="nc_publish_time" id="nc_publish_time"
           value="<?php echo esc_attr( get_option('nc_article_publish_time') ); ?>"/>
    <input type="hidden" name="nc_tags" id="nc_tags"
           value="<?php echo esc_attr( get_option('nc_article_tags') ); ?>"/>
    <input type="hidden" name="nc_categories" id="nc_categories"
           value="<?php echo esc_attr( get_option('nc_article_categories') ); ?>"/>
    <?php wp_nonce_field('nc_metabox_nonce','nc_metabox_check_auth'); ?>
    <?php include( NC_BUILD_PATH . "/html/metabox.html" );?>

<?php else: ?>
    <div id="message" class="updated below-h2">
        <p>Please Add Newscred  <a href="<?php echo esc_url( NC_SETTINGS_URL ); ?>">Access Key</a></p>
    </div>
<?php endif;?>