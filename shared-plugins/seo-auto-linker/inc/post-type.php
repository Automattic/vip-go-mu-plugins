<?php
class SEO_Auto_Linker_Post_Type extends SEO_Auto_Linker_Base
{
    /*
     * Nonce action
     */
    const NONCE = 'seoal_post_nonce';

    /*
     * Sets up all the actions and filters
     *
     * @uses add_action
     * @uses add_filter
     * @since 0.7
     */
    public static function init()
    {
        // register post type
        add_action(
            'init',
            array(get_class(), 'register')
        );

        // add our meta boxes
        add_action(
            'add_meta_boxes_' . self::POST_TYPE,
            array(get_class(), 'meta_boxes'),
            30
        );

        add_action(
            'save_post',
            array(get_class(), 'save'),
            10,
            2
        );

        add_action(
            'manage_' . self::POST_TYPE . '_posts_custom_column',
            array(get_class(), 'column_cb'),
            10,
            2
        );

        add_action(
            'load-edit.php',
            array(get_class(), 'load_edit')
        );

        add_action(
            'load-post-new.php',
            array(get_class(), 'no_autosave')
        );

        add_action(
            'load-post.php',
            array(get_class(), 'no_autosave')
        );

        add_filter(
            'post_updated_messages',
            array(get_class(), 'update_messages')
        );

        add_filter(
            'manage_edit-' . self::POST_TYPE . '_columns',
            array(get_class(), 'columns')
        );

        add_filter(
            'post_row_actions',
            array(get_class(), 'actions'),
            10,
            2
        );
    }

    /*
     * Hooked into `init`.  Registers the post type
     *
     * @uses register_post_type
     * @since 0.7
     */
    public static function register()
    {
        $labels = array(
            'name'              => __('Automatic Links', 'seoal'),
            'singular_name'     => __('Automatic Link', 'seoal'),
            'add_new'           => __('Add New Link', 'seoal'),
            'all_items'         => __('All Links', 'seoal'),
            'add_new_item'      => __('Add New Link', 'seoal'),
            'edit_item'         => __('Edit Link','seoal'),
            'new_item'          => __('New Link', 'seoal'),
            'search_items'      => __('Search Links', 'seoal'),
            'not_found'         => __('No Links Found', 'seoal'),
            'not_found_in_trash' => __('No Links in the Trash', 'seoal'),
            'menu_name'         => __('SEO Auto Linker', 'seoal')
        );

        $args = array(
            'label'         => __('Automatic Links', 'seoal'),
            'labels'        => $labels,
            'description'   => __('A container for SEO Auto Linker', 'seoal'),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'menu_position' => 110,
            'supports'      => array('title')
        );

        register_post_type(
            self::POST_TYPE,
            $args
        );
    }

    /*
     * Adds the SEO Auto Linker meta boxes.  If WordPress seo is installed,
     * this will remove that metabox from the SEO Auto Linker post type
     *
     * @uses add_meta_box
     * @uses remove_meta_box
     * @since 0.7
     */
    public static function meta_boxes($post)
    {
        // remove wpseo
        if(defined('WPSEO_BASENAME'))
        {
            remove_meta_box(
                'wpseo_meta',
                self::POST_TYPE,
                'normal'
            );
        }

        // remove the submit div, we'll roll our own
        remove_meta_box(
            'submitdiv',
            self::POST_TYPE,
            'side'
        );

        // add the submit div
        add_meta_box(
            'seoal-submitdiv',
            __('Save', 'seoal'),
            array(get_class(), 'submit_cb'),
            self::POST_TYPE,
            'side',
            'high'
        );

        // keywords & url box
        add_meta_box(
            'seoal-keywords',
            __('Keywords & URL', 'seoal'),
            array(get_class(), 'keyword_cb'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        // blacklist box
        add_meta_box(
            'seoal-blacklist',
            __('Blacklist', 'seoal'),
            array(get_class(), 'blacklist_cb'),
            self::POST_TYPE,
            'normal',
            'low'
        );

        // post type box
        add_meta_box(
            'seoal-types',
            __('Allowed Post Types', 'seoal'),
            array(get_class(), 'type_cb'),
            self::POST_TYPE,
            'side',
            'low'
        );

        add_action(
            'dbx_post_sidebar',
            array(get_class(), 'nonce_field')
        );

        self::setup_meta($post);
    }

    /*
     * hooked into `load-edit.php`.  
     *
     * @todo contextual help
     * @since 0.7
     */
    public static function load_edit()
    {
        $pt = isset($_GET['post_type']) ? $_GET['post_type'] : false;
        if($pt && $pt == self::POST_TYPE)
        {
            $screen = get_current_screen();
            add_filter(
                'display_post_states',
                array(get_class(), 'change_states')
            );

            add_filter(
                "bulk_actions-{$screen->id}",
                array(get_class(), 'bulk_actions')
            );

            add_filter(
                "views_{$screen->id}",
                array(get_class(), 'filter_views')
            );
        }
    }

    /*
     * A hack to get rid of the autosave script on the seoal_container post type
     * edit screen. This script was causing errors and saying that the user was
     * leaving the page without saving (even when hitting the submit button).
     *
     * @uses wp_deregister_script
     * @since 0.7.1
     */
    public static function no_autosave()
    {
        global $typenow; // sigh globals
        if(self::POST_TYPE == $typenow)
        {
            // why doesn't `wp_dequeue_script` work?
            wp_deregister_script('autosave');
        }
    }

    /*
     * Save ALL the DATA
     *
     * @uses update_post_meta
     * @uses delete_post_meta
     * @uses wp_verify_nonce
     * @uses current_user_can
     * @since 0.7
     */
    public static function save($post_id, $post)
    {
        if($post->post_type != self::POST_TYPE) return;
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if(!current_user_can('manage_options')) return;
        if(!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) 
            return;

        $map = array(
            'keywords'   => array('strip_tags', 'esc_attr'),
            'url'        => array('esc_url'),
            'times'      => array('absint'),
            'target'     => array('esc_attr'),
            'self_links' => 'checkbox',
            'nofollow'   => 'checkbox',
        );
        foreach($map as $key => $escapers)
        {
            $key = self::get_key($key);
            if(isset($_POST[$key]) && $_POST[$key])
            {
                if('checkbox' == $escapers)
                {
                    $val = 'on';
                }
                else
                {
                    $val = $_POST[$key];
                    foreach($escapers as $e)
                        $val = call_user_func($e, $val);
                }
                update_post_meta($post_id, $key, $val);
            }
            else
            {
                delete_post_meta($post_id, $key);
            }
        }

        $bl = self::get_key('blacklist');
        if(isset($_POST[$bl]) && $_POST[$bl])
        {
            $blacklist = preg_split('/\r\n|\r|\n/', $_POST[$bl]);
            $blacklist = array_map('esc_url', $blacklist);
            update_post_meta($post_id, $bl, $blacklist);
        }
        else
        {
            delete_post_meta($post_id, $bl);
        }

        foreach(get_post_types() as $pt)
        {
            $key = self::get_key("type_{$pt}");
            $val = isset($_POST[$key]) && $_POST[$key] ? 'on' : 'off';
            update_post_meta($post_id, $key, $val);
        }
    }

    /*
     * Spits out a nonce field for use with our meta boxes
     *
     * @uses wp_nonce_field
     * @since 0.7
     */
    public static function nonce_field()
    {
        wp_nonce_field(
            self::NONCE,
            self::NONCE,
            false
        );
    }

    /********** Meta Box Callbacks **********/

    /*
     * Callback for the submitdiv
     *
     * @since 0.7
     */
    public static function submit_cb($post)
    {
        $typeobj = get_post_type_object($post->post_type);
        $can_edit = current_user_can('manage_options');
        if(!$can_edit)
        {
            echo '<p>' . __('You must be an admin to edit links', 'seoal') . '</p>';
            return;
        }
        ?>
        <div id="post-status-select">
            <input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr(('auto-draft' == $post->post_status) ? 'draft' : $post->post_status); ?>" />
            <label for="post_status"><?php _e('Status: ', 'seoal'); ?></label>
            <select name='post_status' id='post_status' tabindex='4'>
                <option<?php selected($post->post_status, 'publish'); ?> value='publish'><?php _e('Enabled', 'seoal') ?></option>
                <option<?php selected($post->post_status, 'draft'); ?> value='draft'><?php _e('Disabled', 'seoal') ?></option>
            </select>
        </div>
        <p id="major-publishing-action">
            <a class="button-secondary submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php _e('Delete', 'seoal'); ?></a>
            <input type="submit" name="save" class="button-primary" value="<?php esc_attr_e('Save', 'seoal'); ?>" />
        </p>
        <?php
    }

    /*
     * Callback for the keywords, link url, and allowed links meta box
     *
     * @since 0.7
     */
    public static function keyword_cb($post)
    {
        $target = self::get_meta('target');
        $self_links = self::get_meta('self_links', 'off');
        $nofollow = self::get_meta('nofollow', 'off');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="<?php self::key('keywords'); ?>">
                        <?php _e('Keywords', 'seoal'); ?>
                    </label>
                </th>
                <td>
                    <textarea class="widefat" name="<?php self::key('keywords'); ?>" id="<?php self::key('keywords'); ?>"><?php self::meta('keywords', 'textarea'); ?></textarea>
                    <p class="description">
                        <?php _e('Comma separated. These are the terms you want to link.', 'seoal'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php self::key('url'); ?>">
                        <?php _e('URL', 'seoal'); ?>
                    <label>
                </th>
                <td>
                    <input type="text" class="widefat" name="<?php self::key('url'); ?>" id="<?php self::key('url'); ?>" value="<?php self::meta('url'); ?>" />
                    <p class="description">
                        <?php _e('The url to which you want to link.', 'seoal'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php self::key('times'); ?>">
                        <?php _e('Links per Page', 'seoal'); ?>
                    <label>
                </th>
                <td>
                    <input type="text" class="widefat" name="<?php self::key('times'); ?>" id="<?php self::key('times'); ?>" value="<?php self::meta('times'); ?>" />
                    <p class="description">
                        <?php 
                        _e('The number of times per page (or post type) you want' .
                        'the above keyowrds to link to the url', 'seoal');
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php self::key('target'); ?>">
                        <?php _e('Target', 'seoal'); ?>
                    </label>
                </td>
                <td>
                    <select name="<?php self::key('target'); ?>" id="<?php self::key('target'); ?>">
                        <?php foreach(self::get_targets() as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($target, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php self::key('self_links'); ?>">
                        <?php _e('Allow Self Links?', 'seoal'); ?>
                    </label>
                </td>
                <td>
                    <input type="checkbox"
                           name="<?php self::key('self_links'); ?>"
                           id="<?php self::key('target'); ?>"
                           value="on"
                           <?php checked($self_links, 'on'); ?> />
                </td>
            </tr>
            <tr>
                <td>
                    <label for="<?php self::key('nofollow'); ?>">
                        <?php _e('Nofollow This Link', 'seoal'); ?>
                    </label>
                </td>
                <td>
                    <input type="checkbox"
                           name="<?php self::key('nofollow'); ?>"
                           id="<?php self::key('nofollow'); ?>"
                           value="on"
                           <?php checked($nofollow, 'on'); ?> />
                </td>
            </tr>
        </table>
        <?php
    }

    /*
     * Callback for the blacklist meta box
     *
     * @since 0.7
     */
    public static function blacklist_cb($post)
    {
        $blacklist = self::get_meta('blacklist', array());
        $blacklist = maybe_unserialize($blacklist);
        if($blacklist) $blacklist = array_map('esc_url', $blacklist);
        // I have a hunch this is bad for windows machines?
        $blacklist = implode("\n", $blacklist);
        ?>
        <textarea class="widefat" id="<?php self::key('blacklist'); ?>" name="<?php self::key('blacklist'); ?>" rows="15"><?php echo $blacklist; ?></textarea>
        <p class="description">
            <?php _e("URLs on which you don't want to have this link.", 'seoal'); ?>
        </p>
        <?php
    }

    /*
     * Callback for the post type meta box
     *
     * @since 0.7
     */
    public static function type_cb($post)
    {
        foreach(get_post_types(array('public' => true)) as $post_type): 
        $typeobj = get_post_type_object($post_type);
        ?>
        <label for="<?php self::key("type_{$post_type}"); ?>">
            <input type="checkbox" name="<?php self::key("type_{$post_type}"); ?>" id="<?php self::key("type_{$post_type}"); ?>" <?php checked(self::get_meta("type_{$post_type}"), 'on'); ?> />
            <?php echo esc_attr($typeobj->label); ?>
        </label><br />
        <?php
        endforeach;
    }

    /********** Misc. Display funcitonality **********/

    /*
     * Filters post updated messages to include things for our post type
     *
     * @since 0.7
     */
    public static function update_messages($msg)
    {
        $msg[self::POST_TYPE] = array(
            0  => '', // unused
            1  => __('Link Updated', 'seoal'),
            2  => '', // custom field updated?  wtf is that?
            3  => '', // same
            4  => __('Link Updated', 'seoal'),
            5  => '', // we don't use post revisions
            6  => __('Link Updated', 'seoal'),
            7  => __('Link Updated', 'seoal'),
            8  => '', // will never be used
            9  => '', // we dont' schedule links
            10 => __('Link Updated', 'seoal')
        );
        return $msg;
    }

    /*
     * Filter columns for the post list type
     *
     * @since 0.7
     */
    public static function columns($columns)
    {
        $columns = array(
            'cb'       => '<input type="checkbox" />',
            'title'    => __('Title', 'seoal'),
            'keywords' => __('Keywords', 'seoal'),
            'url'      => __('URL', 'seoal')
        );
        return $columns;
    }

    /*
     * Spits out the values for the list table columns
     *
     * @since 0.7
     */
    public static function column_cb($column, $post_id)
    {
        switch($column)
        {
            case 'keywords':
                if($kw = get_post_meta($post_id, self::get_key('keywords'), true))
                {
                    echo esc_html($kw);
                }
                else
                {
                    _e('No Keywords.', 'seoal');
                }
                break;
            case 'url':
                if($url = get_post_meta($post_id, self::get_key('url'), true))
                {
                    echo esc_url($url);
                }
                else
                {
                    _e('No URL.', 'seoal');
                }
                break;
            default:
                break;
        }
    }

    /*
     * Removes quick edit form post row options
     *
     * @since 0.7
     */
    public static function actions($actions, $post)
    {
        if($post->post_type != self::POST_TYPE) return $actions;
        if(isset($actions['inline hide-if-no-js']))
            unset($actions['inline hide-if-no-js']);
        return $actions;
    }

    /*
     * Change 'draft' to `disabled`
     *
     * @since 0.7
     */
    public static function change_states($states)
    {
        if(isset($states['draft']))
            $states['draft'] = __('Disabled', 'seoal');
        return $states;
    }

    /*
     * Remove the bulk edit action
     *
     * @since 0.7
     */
    public static function bulk_actions($actions)
    {
        if(isset($actions['edit']))
            unset($actions['edit']);
        return $actions;
    }

    /*
     * Remove drafts & published from the views
     *
     * @since .07
     */
    public static function filter_views($views)
    {
        if(isset($views['draft']))
            unset($views['draft']);
        if(isset($views['publish']))
            unset($views['publish']);
        // if it's just the "all" label, forget it
        if(count($views) == 1) return array();
        return $views;
    }
} // end class

SEO_Auto_Linker_Post_Type::init();
