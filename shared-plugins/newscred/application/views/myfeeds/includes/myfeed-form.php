<!-- myfeeds-settings add form -->
<form method="post" action="" class="nc-admin-form"
      id="nc-myFeeds-form" <?php if ( $this->submit_value == "Update" ) { ?>style="display: block;" <?php } ?>>
    <div id="tabs-4" class="myfeeds-settings TabsContent">
        <fieldset class="right">
            <ol class="feature-group">
                <li>
                    <label for="name" class="nc-label">Name</label>
                    <input name="name" id="name" class="regular-text" type="text"
                           value="<?php if ( isset( $this->data[ 'name' ] ) )
                               echo esc_attr($this->data[ 'name' ]); ?>" size="40" aria-required="true">

                    <p class="description">Please enter the MyFeeds Name</p>
                </li>
                <li class="relative">
                    <label for="api_call" class="nc-label">API Call</label>
                    <textarea name="apicall" id="apicall" rows="5"
                              cols="30"><?php if ( isset( $this->data[ 'apicall' ] ) )
                        echo esc_textarea( $this->data[ 'apicall' ] ) ; ?></textarea>
                    <a id="nc_api_create" href="#inline_content" class="button">Create New</a>

                    <p class="description">Please enter your NewsCred API</p>
                </li>
            </ol>
        </fieldset>
        <fieldset class="left">
            <ol class="feature-group">
                <li>
                    <label for="myfeed-autopublish" class="nc-label">Auto Publish</label>
                    <input name="autopublish" id="myfeed-autopublish"
                           type="checkbox" <?php if ( isset( $this->data[ 'autopublish' ] ) && $this->data[ 'autopublish' ] == 1 ) { ?>
                           checked="" <?php } ?> value="">

                </li>
                <li>
                    <hr>
                </li>
                <li class="nc-opacity-on">
                    <label for="publish_status" class="nc-label">Publish Status</label>
                    <select name="publish_status" id="publish_status" disabled>
                        <option value="1" <?php if ( isset( $this->data[ 'publish_status' ] ) ) {
                            if ( $this->data[ 'publish_status' ] == 1 )
                                echo 'selected=""';
                        }
                        else { ?> selected="" <?php } ?>   >Publish
                        </option>
                        <option value="0" <?php if ( isset( $this->data[ 'publish_status' ] ) ) {
                            if ( $this->data[ 'publish_status' ] == 0 )
                                echo 'selected=""';
                        }
                        else { ?> selected="" <?php } ?>>Save as Draft
                        </option>
                    </select>
                </li>
                <li class="nc-opacity-on">
                    <label for="publish_interval" class="nc-label">Publish Interval</label>
                    <select name="publish_interval" id="publish_interval">
                        <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                        <?php
                        $selected = "";
                        if ( isset( $this->data[ 'publish_interval' ] ) && $this->data[ 'publish_interval' ] == $i )
                            $selected = 'selected=""';
                        ?>
                        <option <?php echo $selected; ?>  value="<?php echo $i; ?>"><?php echo $i; ?> Hour</option>
                        <?php endfor; ?>
                    </select>

                </li>
                <li class="relative nc-opacity-on">
                    <label for="myfeed_category" class="nc-label">Category</label>
                    <select data-placeholder="Select Categories" id="myfeed_category" name="myfeed_category[]" id=""
                            multiple>
                        <?php
                        $category_list = "";
                        if ( isset( $this->data[ 'myfeed_category' ] ) && $this->data[ 'myfeed_category' ] != "" )
                            $category_list = array_flip( unserialize( $this->data[ 'myfeed_category' ] ) );
                        ?>
                        <?php foreach ( $this->categories as $category )  : ?>
                        <?php
                        $selected = "";
                        if ( $category_list && array_key_exists( $category->term_id, $category_list ) )
                            $selected = 'selected=""';
                        ?>
                        <option <?php echo $selected; ?>
                            value="<?php echo $category->term_id; ?>"><?php  echo esc_html($category->cat_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="javascript:;" class="button" id="myfeed-create-category">Create New</a>
                </li>

                <li class="nc-opacity-on" id="myfeed-category-box">
                    <label for="category" class="nc-label">New Category Name</label>
                    <input name="category" type="text" id="category" class="medium-text"/>
                    <a href="javascript:;" id="add_feed_category" class="button" disabled>Add</a>
                    <img class="nc-category-loading" src="<?php echo esc_url(NC_IMAGES_URL . "/nc-loading.gif"); ?>"/>
                </li>

                <li class="nc-opacity-on" id="feature_image_box">
                    <label for="feature_image" class="nc-label">Feature Image</label>
                    <input id="feature_image" class="keeptags"
                           type="checkbox" <?php if ( isset( $this->data[ 'feature_image' ] ) ) {
                        if ( $this->data[ 'feature_image' ] == 1 )
                            echo 'checked=""';
                    }
                    else { ?> checked="" <?php } ?> name="feature_image" disabled/>

                </li>

                <li class="nc-opacity-on last">
                    <label for="feed_tag" class="nc-label">Keep Tags</label>
                    <input id="feed_tag" class="keeptags"
                           type="checkbox" <?php if ( isset( $this->data[ 'feed_tag' ] ) ) {
                        if ( $this->data[ 'feed_tag' ] == 1 )
                            echo 'checked=""';
                    }
                    else { ?> checked="" <?php } ?> name="feed_tag" disabled/>

                </li>
            </ol>
        </fieldset>
        <div class="clearfix"></div>
        <p class="submit">
            <?php if( $this->submit_value == "Add") : ?>
                <?php wp_nonce_field('myfeeds_nonce_add', 'myfeeds_nonce_add_submit');  ?>
            <?php else: ?>
                <?php wp_nonce_field('myfeeds_nonce_update', 'myfeeds_nonce_update_submit');  ?>
            <?php endif; ?>

            <input type="hidden" name="publish_time" value="<?php if ( isset( $this->data[ 'publish_time' ] ) )
                echo esc_attr( $this->data[ 'publish_time' ] ); ?>"/>
            <input type="hidden" name="id" value="<?php if ( isset( $this->data[ 'id' ] ) )
                echo esc_attr($this->data[ 'id' ]); ?>"/>

            <a class="button" href="<?php echo esc_url(NC_MYFEEDS_URL); ?>"> Cancel</a>
            <input type="submit" name="submit" id="submit" class="button button-primary"
                   value="<?php echo esc_attr( $this->submit_value ); ?> MyFeeds"/>
        </p>
    </div>
</form>
<!-- end myfeeds-settings ad form -->
