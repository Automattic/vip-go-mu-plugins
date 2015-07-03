<?php
class Blimply_Settings {

    private $settings_api;

    function __construct() {
        $this->settings_api = new WeDevs_Settings_API;

        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    function admin_init() {

        //set the settings
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );

        //initialize settings
        $this->settings_api->admin_init();
    }

    function admin_menu() {

        $manage_cap = apply_filters( 'blimpbly_manage_cap', 'manage_options' );

        add_options_page( __( 'Blimply Settings', 'blimply' ), __( 'Blimply Settings', 'blimply' ), $manage_cap, 'blimply_settings', array( $this, 'plugin_page' ) );
        add_options_page( __( 'Urban Airship Tags', 'blimply' ), __( 'Urban Airship Tags', 'blimply' ), $manage_cap, 'edit-tags.php?taxonomy=blimply_tags' );
    }

    function get_settings_sections() {
        $sections = array(
            array(
                'id' => 'urban_airship',
                'title' => __( 'Urban Airship Settings', 'blimply' )
            ),
            array(
                'id' => 'blimply_sounds',
                'title' => __( 'Push Sounds', 'blimply' )
            ),
        );
        return $sections;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {
        $settings_fields = array(
            'urban_airship' => array(
                array(
                    'name' => BLIMPLY_PREFIX . '_name',
                    'label' => __( 'Urban Airship Application Slug!', 'blimply' ),
                    'desc' => __( 'Text input description', 'blimply' ),
                    'type' => 'text',
                    'default' => 'Title'
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_app_key',
                    'label'=> __( 'Application API Key', 'blimply' ),
                    'desc'=> __( '22 character long app key( like SYk74m98TOiUhHHHHb5l_Q.', 'blimply' ),
                    'type'=> 'text',
                    'std' => __( 'my-blimply', 'blimply' ),
                    'class'=> 'nohtml'
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_app_secret',
                    'label'=> __( 'Application Master Secret', 'blimply' ),
                    'desc'=> __( '22 character long app master secret( like SYk74m98TOiUhHHHHb5l_Q. )', 'blimply' ),
                    'type'=> 'text',
                    'std' => __( 'my-blimply', 'blimply' ),
                    'class'=> 'nohtml'
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_allow_broadcast',
                    'label'=> __( 'Allow Broadcast Notifications', 'blimply' ),
                    'desc'=> __( 'You may enable broadcast pushes that all your users will get, no matter their settings in-app', 'blimply' ),
                    'type'=> 'checkbox',
                    'std' => __( 'my-blimply', 'blimply' ),
                    'class'=> 'nohtml'
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_character_limit',
                    'label'=> __( 'Limit dashboard pushes to this number of characters', 'blimply' ),
                    'desc'=> __( '', 'blimply' ),
                    'type'=> 'text',
                    'std' => 140,
                    'class'=> 'nohtml',
                    'sanitize_callback' => 'intval'
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_enable_quiet_time',
                    'label'=> __( 'Enable quiet time', 'blimply' ),
                    'desc'=> __( '', 'blimply' ),
                    'type'=> 'checkbox',
                    'std' => 140,
                    'class'=> 'nohtml',
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_quiet_time_from',
                    'label'=> __( 'Quiet time from (24h format)', 'blimply' ),
                    'desc'=> __( '', 'blimply' ),
                    'type'=> 'text',
                    'std' => "23:00",
                    'class'=> 'nohtml blimply-timepicker',
                ),
                array(
                    'name' => BLIMPLY_PREFIX . '_quiet_time_to',
                    'label'=> __( 'Quiet time to (24h format)', 'blimply' ),
                    'desc'=> __( '', 'blimply' ),
                    'type'=> 'text',
                    'std' => "7:00",
                    'class'=> 'nohtml blimply-timepicker',
                ),
            )
        );

        $tags = get_terms( 'blimply_tags', array( 'hide_empty' => 0 ) );
        foreach ( (array) $tags as $tag ) {
            $settings_fields['blimply_sounds'][] = array(
                'name' => BLIMPLY_PREFIX . '_sound_' . $tag->slug,
                'label'=> __( 'Sound for tag: ' . $tag->name , 'blimply' ),
                'desc'=> __( 'You may specify a sound that will accompany your push. Must match filename of a sound bundled in your app. Leave blank for default. Ex: my_sound.caf', 'blimply' ),
                'type'=> 'text',
                'std' => __( '', 'blimply' ),
                'class'=> 'nohtml'
            );
        }

        return $settings_fields;
    }

    function plugin_page() {
        echo '<div class="wrap">';
        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();

        echo '</div>';
    }

    /**
     * Get all the pages
     *
     * @return array page names with key value pairs
     */
    function get_pages() {
        $pages = get_pages();
        $pages_options = array();
        if ( $pages ) {
            foreach ( $pages as $page ) {
                $pages_options[$page->ID] = $page->post_title;
            }
        }

        return $pages_options;
    }

}

$settings = new Blimply_Settings;
