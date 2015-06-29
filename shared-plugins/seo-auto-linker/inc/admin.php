<?php
class SEO_Auto_Linker_Admin extends SEO_Auto_Linker_Base
{
    /*
     * Adds actions and such around the site
     */
    public static function init()
    {
        add_action(
            'admin_init',
            array(get_class(), 'settings')
        );

        add_action(
            'admin_menu',
            array(get_class(), 'menu_page')
        );
    }

    /*
     * Registers the setting and adds fields
     *
     * 0.7
     */
    public static function settings()
    {
        register_setting(
            self::SETTING,
            self::SETTING,
            array(get_class(), 'cleaner')
        );

        add_settings_section(
            'blacklist',
            __('Sitewide Blacklist', 'seoal'),
            array(get_class(), 'blacklist_section'),
            self::SETTING
        );

        add_settings_field(
            'seoal-blacklist-field',
            __('Blacklist', 'seoal'),
            array(get_class(), 'blacklist_field'),
            self::SETTING,
            'blacklist'
        );

        add_settings_section(
            'word_boundary',
            __('Word Boundaries', 'seoal'),
            array(get_class(), 'boundary_section'),
            self::SETTING
        );

        add_settings_field(
            'seoal-boundary-field',
            __('Use Alternative Word Boundaries?', 'seoal'),
            array(get_class(), 'boundary_field'),
            self::SETTING,
            'word_boundary'
        );
    }

    /*
     * Adds the menu page
     *
     * @uses add_submenu_page
     * @since 0.7
     */
    public static function menu_page()
    {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('SEO Auto Linker Options', 'seoal'),
            __('Options', 'seoal'),
            'manage_options',
            'seo-auto-linker',
            array(get_class(), 'menu_page_cb')
        );
    }

    /*
     * Settings sanitation callback
     *
     * @since 0.7
     */
    public static function cleaner($in)
    {
        $out = array();

        $blacklist = isset($in['blacklist']) && $in['blacklist'] ?
            $in['blacklist'] : '';
        $lines = preg_split('/\r\n|\r|\n/', $blacklist);
        $out['blacklist'] = array_map('esc_url', $lines);
        if ( $blacklist_max = apply_filters( 'seoal_blacklist_max', '__return_false' ) )
            $out['blacklist'] = array_slice( $out['blacklist'], 0, (int)$blacklist_max );

        $out['word_boundary'] = !empty($in['word_boundary']) ? 'on' : 'off';

        add_settings_error(
            self::SETTING,
            'seoal-success',
            __('Settings Saved', 'seoal'),
            'updated'
        );

        return $out;
    }

    /*
     * Menu page callback.  Handles outputing our options page
     */
    public static function menu_page_cb()
    {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e('SEO Auto Linker Options', 'seoal'); ?></h2>
            <?php settings_errors(self::SETTING); ?>
            <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>" style="width:80%">
                <?php
                settings_fields(self::SETTING);
                do_settings_sections(self::SETTING);
                submit_button(__('Save Settings'));
                ?>
            </form>
        </div>
        <?php
    }

    /********** Settings Section Callbacks **********/

    /*
     * Callback for the blacklist section
     *
     * @since 0.7
     */
    public static function blacklist_section()
    {
        echo '<p class="description">';
        _e('The URLs on your site where you do not want any automatic links to'.
            ' appear.  One URL per line.', 'seoal');
        echo '</p>';
    }

    /**
     * Callback for the word boundary section.
     *
     * @since   0.9
     * @access  public
     * @return  void
     */
    public static function boundary_section()
    {
        echo '<p class="description">',
            __("If you're having trouble with SEO Auto Linker matching unicode ".
               "characters and word boundaries, try checking the box below.", 'seoal'),
            '<p>';
    }

    /********** Settings Fields Callbacks **********/

    /*
     * Callback for the blacklist field
     *
     * @since 0.7
     */
    public static function blacklist_field()
    {
        $opts = get_option(self::SETTING, array());
        $blacklist = isset($opts['blacklist']) ? $opts['blacklist'] : array();
        $blacklist = implode("\n", array_map('esc_url', $blacklist));
        printf(
            '<textarea id="%1$s" name="%1$s" class="widefat" rows="15">%2$s</textarea>',
            esc_attr(self::get_key('blacklist')),
            esc_textarea($blacklist)
        );
    }

    /**
     * Callback for the word boundary field.
     *
     * @since   0.9
     * @access  public
     * @return  void
     */
    public static function boundary_field()
    {
        $opts = get_option(self::SETTING, array());
        $boundary = isset($opts['word_boundary']) && 'on' == $opts['word_boundary'] ? 'on' : 'off';
        printf(
            '<input type="checkbox" name="%1$s" id="%1$s value="on" %2$s/>',
            esc_attr(self::get_key('word_boundary')),
            checked('on', $boundary, false)
        );
    }

    /********** Util **********/

    /*
     * replace `get_key` so it wraps options in our settings name
     *
     * @since 0.7
     */
    protected static function get_key($key)
    {
        return sprintf('%s[%s]', self::SETTING, $key);
    }
} // endclass

SEO_Auto_Linker_Admin::init();
