<?php include( NC_VIEW_PATH . "/myfeeds/includes/api-form.php" ) ?>

<div class="wrap" style="overflow: hidden;">
    <a class="nc-logo" href="http://newscred.com" target="_blank">
        <img class="" src="<?php echo NC_IMAGES_URL . "/newscred-logo.png" ?>"/>
    </a>

    <div id="icon-edit-pages" class="icon32 icon32-posts-page"><br></div>
    <h2>MyFeeds <a href="javascript:" id="add-new-myfeeds"
                   class="add-new-h2" <?php if ( $this->submit_value == "Update" ) { ?>
                   style="display: none;" <?php } ?> >Add New MyFeeds</a></h2>

    <div class="clearfix"></div>

    <?php if ( !empty( $this->message ) ): ?>
    <?php foreach ( $this->message as $message ): ?>
        <div id="message" class="<?php if($message['type'] == "error" ){?> error settings-error <?php }else{ ?> updated below-h2 <?php } ?>">
            <p><?php echo esc_html( $message['msg'] ); ?></p>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>


    <?php include( NC_VIEW_PATH . "/myfeeds/includes/myfeed-form.php" ) ?>
    <?php include( NC_VIEW_PATH . "/myfeeds/includes/myfeed-list.php" ) ?>

    <br class="clear">
</div>