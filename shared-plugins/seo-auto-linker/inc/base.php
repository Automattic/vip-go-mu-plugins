<?php
abstract class SEO_Auto_Linker_Base
{
    /*
     * Our post type name
     */
    const POST_TYPE = 'seoal_container';

    /*
     * Prefix for field names and ids and postmeta keys
     */
    const PREFIX = 'seoal_';

    /*
     * Old setting.  Used for migration.
     */
    const OLD_SETTING = 'pmg_autolinker_options';

    /*
     * The current setting
     */
    const SETTING = 'seoal_options';

    /*
     * Container for post_meta
     */
    protected static $meta;

    /********** util **********/

    /*
     * Setup the post meta
     *
     * @uses get_post_custom
     * @since 0.7
     */
    protected static function setup_meta($post)
    {
        self::$meta = get_post_custom($post->ID);
    }

    /*
     * Fetch a specific meta key or return an empty string
     *
     * @since 0.7
     */
    protected static function get_meta($key, $default='')
    {
        $k = self::get_key($key);
        return isset(self::$meta[$k]) ? self::$meta[$k][0] : $default;
    }

    /*
     * Echo out an escaped meta value
     *
     * @since 0.7
     */
    protected static function meta($key, $esc = 'attr')
    {
        $val = self::get_meta($key);
        echo call_user_func("esc_{$esc}", $val);
    }

    /*
     * Get a prefixed meta key for ids and postmeta and such
     *
     * @since 0.7
     */
    protected static function get_key($key)
    {
        return sprintf('%s%s', self::PREFIX, $key);
    }

    /*
     * Echo out an escaped meta mey
     *
     * @since 0.7
     */
    protected static function key($key)
    {
        echo esc_attr(self::get_key($key));
    }

    /*
     * Fetch the available target attributes
     *
     * @since 0.7.2
     * @return array The list of target atts
     */
    protected static function get_targets()
    {
        $targets = array(
            '_blank' => __('_blank', 'seoal'),
            '_top'   => __('_top', 'seoal'),
            '_self'  => __('_self', 'seoal')
        );
        return apply_filters('seoal_targets', $targets);
    }
} // end class
