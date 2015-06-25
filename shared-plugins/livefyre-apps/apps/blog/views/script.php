<div id="<?php echo esc_attr($livefyre_element); ?>"></div>
<script type="text/javascript">
    var networkConfigBlog = {
        <?php echo isset( $strings ) ? 'strings: ' . json_encode($strings) . ',' : ''; ?>
        network: "<?php echo esc_js($network->getName()); ?>"    
    };
    var convConfigBlog<?php echo esc_js($articleId); ?> = {
        siteId: "<?php echo esc_js($siteId); ?>",
        articleId: "<?php echo esc_js($articleId); ?>",
        el: "<?php echo esc_js($livefyre_element); ?>",
        collectionMeta: "<?php echo esc_js($collectionMetaToken); ?>",
        checksum: "<?php echo esc_js($checksum); ?>"
    };
    
    if(typeof(liveBlogConfig) !== 'undefined') {
        convConfigBlog<?php echo esc_js($articleId); ?> = lf_extend(liveBlogConfig, convConfigBlog<?php echo esc_js($articleId); ?>);
    }

    Livefyre.require(['<?php echo Livefyre_Apps::get_package_reference('fyre.conv'); ?>'], function(ConvBlog) {
        load_livefyre_auth();
        new ConvBlog(networkConfigBlog, [convConfigBlog<?php echo esc_js($articleId); ?>], function(blogWidget) {            
        }());
    });
</script>