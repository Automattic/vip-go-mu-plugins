<form id="posts-filter" action="" method="post">
    <?php wp_nonce_field('myfeeds_list_nonce', 'myfeeds_list_nonce_submit');  ?>
    <div class="tablenav top">

        <div class="alignleft actions">
            <select name="action">
                <option value="-1" selected="selected">Bulk Actions</option>
                <option value="delete">Delete</option>
            </select>
            <input type="submit" name="" id="doaction" class="button-secondary action" value="Apply">
        </div>
        <div class="tablenav-pages one-page">
            <span class="displaying-num"><?php echo esc_html( $this->num_rows );?> items</span>
            <?php echo  wp_kses_post( $this->app_pagin );?>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed tags" cellspacing="0">
        <thead>
        <tr>
            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
            <th scope="col">
                <span>Name</span><span class="sorting-indicator"></span>
            </th>
            <th scope="col" id="description">
                <span>Auto Published</span>
                <span class="sorting-indicator"></span>
            </th>
            <th scope="col">
                <span>Last Published Time</span>
                <span class="sorting-indicator"></span>
            </th>
            <th scope="col">
                <span>Publish Interval</span>
                <span class="sorting-indicator"></span>
            </th>
            <th scope="col">
                <span>Update MyFeeds</span>
                <span class="sorting-indicator"></span>
            </th>
        </tr>
        </thead>

        <tfoot>
        <tr>
            <th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox"></th>
            <th scope="col" style="">
                <span>Name</span><span class="sorting-indicator"></span>
            </th>
            <th scope="col" style="">
                <span>Auto Published</span><span class="sorting-indicator"></span>
            </th>
            <th scope="col">
                <span>Last Published Time</span>
                <span class="sorting-indicator"></span>
            </th>

            <th scope="col" style="">
                <span>Publish Interval</span><span class="sorting-indicator"></span>
            </th>
            <th scope="col">
                <span>Update MyFeeds</span>
                <span class="sorting-indicator"></span>
            </th>

        </tr>
        </tfoot>

        <tbody id="the-list" class="list:tag">
        <?php if ( $this->myfeed_list ): ?>
            <?php foreach ( $this->myfeed_list as $myfeed ): ?>

            <?php $edit_url =esc_url( wp_nonce_url(  NC_MYFEEDS_URL ."&action=edit&id=" . absint( $myfeed->id ), 'myfeed_edit_nonce')); ?>
            <tr id="tag-245" class="alternate">
                <th scope="row" class="check-column">
                    <input type="checkbox" name="delete_feeds[]" value="<?php echo absint( $myfeed->id );?>">
                </th>
                <td class="name column-name">
                    <strong>
                        <a class="row-title"
                           href="<?php echo $edit_url; ?>"
                           title="<?php echo esc_attr( $myfeed->name ); ?>"><?php echo esc_html( $myfeed->name ); ?></a>
                    </strong>
                    <br>

                    <div class="row-actions">
                                <span class="edit">

                                    <a href="<?php echo $edit_url; ?>">Edit</a> |
                                </span>
                                <span class="delete">
                                    <?php $delete_url = esc_url(wp_nonce_url( NC_MYFEEDS_URL . "&amp;action=delete&amp;id=" .  absint( $myfeed->id ), "myfeed_delete_nonce")); ?>
                                    <a class="delete-tag"
                                       href="<?php echo $delete_url; ?>"
                                       onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                                </span>
                    </div>

                </td>
                <td class="description column-description">
                    <?php if ( $myfeed->autopublish ): ?>
                    Yes
                    <?php else: ?>
                    No
                    <?php endif;?>
                </td>
                <td class="publish_time">
                    <?php  if ( $myfeed->autopublish && $myfeed->publish_time ): ?>
                    <?php echo esc_html( $myfeed->publish_time ); ?>
                    <?php endif;?>
                </td>
                <td class="description column-description">
                    <?php  if ( $myfeed->autopublish && $myfeed->publish_interval ): ?>
                    <?php echo esc_html( $myfeed->publish_interval ); ?> hour
                    <?php endif;?>
                </td>
                <td>
                    <?php  if ( $myfeed->autopublish ): ?>
                    <input type="button" myFeedid="<?php echo absint( $myfeed->id );?>"
                           class="button-secondary action nc-update-myfeeds-cron" value="Update">
                    <img class="nc-search-loading-myfeeds" src="<?php echo esc_url( NC_IMAGES_URL  . "/nc-loading.gif" );?>"/>
                    <?php endif;?>

                </td>
            </tr>
                <?php endforeach; ?>
            <?php else: ?>
        <tr class="no-items">
            <td class="colspanchange" colspan="5">No MyFeeds found.</td>
        </tr>

            <?php endif; ?>

        </tbody>
    </table>
    <div class="tablenav bottom">

        <div class="alignleft actions">
            <select name="action2">
                <option value="-1" selected="selected">Bulk Actions</option>
                <option value="delete">Delete</option>
            </select>
            <input type="submit" name="" id="doaction2" class="button-secondary action" value="Apply">
        </div>
        <div class="tablenav-pages one-page">
            <span class="displaying-num"><?php echo esc_html( $this->num_rows );?> items</span>
            <?php echo  wp_kses_post( $this->app_pagin );?>
        </div>
        <br class="clear">
    </div>

</form>