<?php
/*
Plugin Name: Parse.ly
Plugin URI: http://www.parsely.com/
Description: This plugin makes it a snap to add Parse.ly tracking code to your WordPress blog.
Author: Mike Sukmanowsky (mike@parsely.com)
Version: 1.7
Requires at least: 3.0.0
Author URI: http://www.parsely.com/
License: GPL2

Copyright 2012  Parsely Incorporated

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Authors: Mike Sukmanowsky (mike@parsely.com)
*/

/* TODO List:
 * Wordpress Network support - going to hold off on any specific support here as content id prefix should work ok for now
 * Allow the user to map get_post_types() to Parse.ly post types
 * Add unit/functional tests
 * Support: is_search(), is_404()
*/

class Parsely {
    const VERSION             = '1.7';
    const MENU_SLUG           = 'parsely';             // Defines the page param passed to options-general.php
    const MENU_TITLE          = 'Parse.ly';            // Text to be used for the menu as seen in Settings sub-menu
    const MENU_PAGE_TITLE     = 'Parse.ly > Settings'; // Text shown in <title></title> when the settings screen is viewed
    const OPTIONS_KEY         = 'parsely';             // Defines the key used to store options in the WP database
    const CAPABILITY          = 'manage_options';      // The capability required for the user to administer settings
    const CATEGORY_DELIMITER  = '~-|@|!{-~';

    private $optionDefaults     = array('apikey' => '',
                                        'content_id_prefix' => '',
                                        'use_top_level_cats' => false,
                                        'child_cats_as_tags' => false,
                                        'track_authenticated_users' => true,
                                        'lowercase_tags' => true);
    private $implementationOpts = array('standard' => 'Standard',
                                        'dom_free' => 'DOM-Free');

    public function __construct() {
        // Run upgrade options if they exist for the version currently defined
        $options = $this->get_options();
        if ( empty($options['plugin_version']) || $options['plugin_version'] != Parsely::VERSION ) {
            $method = 'upgrade_plugin_to_version_' . str_replace('.', '_', Parsely::VERSION);
            if ( method_exists($this, $method) ) {
                call_user_func_array(array($this, $method), array($options));
            }
            // Update our version info
            $options['plugin_version'] = Parsely::VERSION;
            update_option(Parsely::OPTIONS_KEY, $options);
        }

        // admin_menu and a settings link
        add_action('admin_head', array($this, 'add_admin_header'));
        add_action('admin_menu', array($this, 'add_settings_sub_menu'));
        add_action('admin_init', array($this, 'initialize_settings'));
        // display warning when plugin hasn't been configured
        add_action('admin_footer', array($this, 'display_admin_warning'));

        $basename = plugin_basename(__FILE__);
        add_filter('plugin_action_links_' . $basename,
                   array($this, 'add_plugin_meta_links'));

        // inserting parsely code
        add_action('wp_head', array($this, 'insert_parsely_page'));
        add_action('wp_footer', array($this, 'insert_parsely_javascript'));
    }

    public function add_admin_header() {
        include('parsely-admin-header.php');
    }

    /* Parsely settings page in Wordpress settings menu. */
    public function add_settings_sub_menu() {
        add_options_page(Parsely::MENU_PAGE_TITLE,
                         Parsely::MENU_TITLE,
                         Parsely::CAPABILITY,
                         Parsely::MENU_SLUG,
                         array($this, 'display_settings'));
    }

    /* Parse.ly settings screen (options-general.php?page=[MENU_SLUG]) */
    public function display_settings() {
        if ( !current_user_can(Parsely::CAPABILITY) ) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        include('parsely-settings.php');
    }

    public function initialize_settings() {
        // All our options are actually stored in one single array to reduce
        // DB queries
        register_setting(Parsely::OPTIONS_KEY, Parsely::OPTIONS_KEY,
                         array($this, 'validate_options'));

        // Required Settings
        add_settings_section('required_settings', 'Required Settings',
                             array($this, 'print_required_settings'),
                             Parsely::MENU_SLUG);

        // API Key
        $h = 'You can find your Site ID on your ' .
             '<a href="http://dash.parsely.com/to/settings/api?highlight=apikey" target="_blank">' .
             'your API settings page</a>.';
        $field_args = array(
            'option_key' => 'apikey',
            'help_text' => $h
        );
        add_settings_field('apikey',
                           'Parse.ly Site ID <div class="help-icons"></div>',
                           array($this, 'print_text_tag'),
                           Parsely::MENU_SLUG, 'required_settings',
                           $field_args);


        // Optional Settings
        add_settings_section('optional_settings', 'Optional Settings',
                             array($this, 'print_optional_settings'),
                             Parsely::MENU_SLUG);
        // Content ID Prefix
        $h = 'If you use more than one content management system (e.g. ' .
             'WordPress and Drupal), you may end up with duplicate content ' .
             'IDs. Adding a Content ID Prefix will ensure the content IDs ' .
             'from WordPress will not conflict with other content management ' .
             'systems. We recommend using "WP-" for your prefix.';
        $field_args = array(
            'option_key' => 'content_id_prefix',
            'optional_args' => array(
                'placeholder' => 'WP-'),
            'help_text' => $h,
            'requires_recrawl' => true
        );
        add_settings_field('content_id_prefix',
                           'Content ID Prefix <div class="help-icons"></div>',
                           array($this, 'print_text_tag'),
                           Parsely::MENU_SLUG, 'optional_settings',
                           $field_args);

        // Use top-level cats
        $h = 'wp-parsely will use the first category assigned to a post. ' .
             'With this option selected, if you post a story to News > ' .
             'National > Florida, wp-parsely will use the "News" for the ' .
             'section name instead of "Florida".';
        add_settings_field('use_top_level_cats',
                           'Use Top-Level Categories <div class="help-icons"></div>',
                           array($this, 'print_binary_radio_tag'),
                           Parsely::MENU_SLUG, 'optional_settings',
                           array('option_key' => 'use_top_level_cats',
                                 'help_text' => $h,
                                 'requires_recrawl' => true));

        // Use child-categories as tags
        $h = 'You can use this option to ensure all assigned categories will ' .
             'be used as tags.  For example, if you had a post assigned to ' .
             'the categories: "Business/Tech", "Business/Social", your ' .
             'parsely-page tags attribute would include the tags: ' .
             '"Business/Tech", "Business/Social".';
        add_settings_field('child_cats_as_tags',
                           'Use Child Categories as Tags <div class="help-icons"></div>',
                           array($this, 'print_binary_radio_tag'),
                           Parsely::MENU_SLUG, 'optional_settings',
                           array('option_key' => 'child_cats_as_tags',
                                 'help_text' => $h,
                                 'requires_recrawl' => true));
        // Track logged-in users
        $h = 'By default, wp-parsely will track the activity of users that ' .
             'are logged into this site. You can change this setting to only ' .
             'track the activity of anonymous visitors. Note: You will no ' .
             'longer see the Parse.ly tracking code on your site if you ' .
             'browse while logged in.';
        add_settings_field('track_authenticated_users',
                           'Track Logged-in Users <div class="help-icons"></div>',
                           array($this, 'print_binary_radio_tag'),
                           Parsely::MENU_SLUG, 'optional_settings',
                           array('option_key' => 'track_authenticated_users',
                                 'help_text' => $h,
                                 'requires_recrawl' => true));

        // Lowercase all tags
        $h = 'By default, wp-parsely will use lowercase versions of your ' .
             'tags to correct for potential misspellings. You can change this '.
             'setting to ensure that tag names are used verbatim.';
        add_settings_field('lowercase_tags',
                           'Lowercase All Tags <div class="help-icons"></div>',
                           array($this, 'print_binary_radio_tag'),
                           Parsely::MENU_SLUG, 'optional_settings',
                           array('option_key' => 'lowercase_tags',
                                 'help_text' => $h,
                                 'requires_recrawl' => true));

    }

    public function validate_options($input) {
        if ( empty($input['apikey']) ) {
            add_settings_error(Parsely::OPTIONS_KEY, 'apikey',
                               'Please specify the Site ID');
        } else {
            $input['apikey'] = sanitize_text_field($input['apikey']);
            if ( strpos($input['apikey'], '.') === false ||
                strpos($input['apikey'], ' ') !== false)
                add_settings_error(Parsely::OPTIONS_KEY, 'apikey',
                                   'Your Parse.ly Site ID looks incorrect, it should look like "example.com".  You can verify your Site ID <a href="http://dash.parsely.com/to/settings/api?highlight=apikey" target="_blank">here</a>.');

        }

        // Content ID prefix
        $input['content_id_prefix'] = sanitize_text_field($input['content_id_prefix']);

        // Top-level categories
        if ( $input['use_top_level_cats'] !== 'true' && $input['use_top_level_cats'] !== 'false' ) {
            add_settings_error(Parsely::OPTIONS_KEY, 'use_top_level_cats',
                               'Value passed for use_top_level_cats must be either "true" or "false".');
        } else {
            $input['use_top_level_cats'] = $input['use_top_level_cats'] === 'true' ? true : false;
        }

        // Child categories as tags
        if ( $input['child_cats_as_tags'] !== 'true' && $input['child_cats_as_tags'] !== 'false' ) {
            add_settings_error(Parsely::OPTIONS_KEY, 'child_cats_as_tags',
                               'Value passed for child_cats_as_tags must be either "true" or "false".');
        } else {
            $input['child_cats_as_tags'] = $input['child_cats_as_tags'] === 'true' ? true : false;
        }

        // Track authenticated users
        if ( $input['track_authenticated_users'] !== 'true' && $input['track_authenticated_users'] !== 'false' ) {
            add_settings_error(Parsely::OPTIONS_KEY, 'track_authenticated_users',
                               'Value passed for track_authenticated_users must be either "true" or "false".');
        } else {
            $input['track_authenticated_users'] = $input['track_authenticated_users'] === 'true' ? true : false;
        }

        // Lowercase tags
        if ( $input['lowercase_tags'] !== 'true' && $input['lowercase_tags'] !== 'false' ) {
            add_settings_error(Parsely::OPTIONS_KEY, 'lowercase_tags',
                               'Value passed for lowercase_tags must be either "true" or "false".');
        } else {
            $input['lowercase_tags'] = $input['lowercase_tags'] === 'true' ? true : false;
        }

        return $input;
    }

    public function print_required_settings() {
        // We can optionally print some text here in the future, but we don't
        // need to now
        return;
    }

    public function print_optional_settings() {
        // We can optionally print some text here in the future, but we don't
        // need to now
        return;
    }

    /**
    * Adds a 'Settings' link to the Plugins screen in WP admin
    */
    public function add_plugin_meta_links($links) {
        array_unshift($links, '<a href="'. $this->get_settings_url() . '">' . __('Settings'). '</a>');
        return $links;
    }

    public function display_admin_warning() {
        $options = $this->get_options();
        if ( !isset($options['apikey']) || empty($options['apikey']) ) {
            ?>
            <div id='message' class='error'>
                <p>
                    <strong>Parse.ly - Dash plugin is not active.</strong>
                    You need to
                    <a href='<?php echo esc_html($this->get_settings_url()); ?>'>
                        provide your Parse.ly Dash Site ID
                    </a>
                    before things get cooking.
                </p>
            </div>
            <?php
        }
    }

    /**
    * Actually inserts the code for the <meta name='parsely-page'> parameter within the <head></head> tag.
    */
    public function insert_parsely_page() {
        $parselyOptions = $this->get_options();

        // If we don't have an API key or if we aren't supposed to show to logged in users, there's no need to proceed.
        if ( empty($parselyOptions['apikey']) || (!$parselyOptions['track_authenticated_users'] && is_user_logged_in()) ) {
            return '';
        }

        global $wp_query;
        global $post;
        // Assign default values for LD+JSON
        // TODO: Maping of an install's post types to Parse.ly post types (namely page/post)
        $parselyPage = array(
            "@context" => "http://schema.org",
            "@type" => "WebPage"
        );
        $currentURL = $this->get_current_url();
        if ( is_single() && $post->post_status == 'publish' ) {
            $authors    = $this->get_author_names($post);
            $category   = $this->get_category_name($post, $parselyOptions);
            $postId     = $parselyOptions['content_id_prefix'] . (string)get_the_ID();

            $image_url = '';
            if ( has_post_thumbnail() ) {
                $image_id = get_post_thumbnail_id();
                $image_url = wp_get_attachment_image_src($image_id);
                $image_url = $image_url[0];
            }

            $parselyPage['@type']          = 'NewsArticle';
            $parselyPage['headline']       = $this->get_clean_parsely_page_value(get_the_title());
            $parselyPage['url']            = get_permalink();
            $parselyPage['thumbnailUrl']   = $image_url;
            $parselyPage['articleId']      = $postId;
            $parselyPage['dateCreated']    = gmdate('Y-m-d\TH:i:s\Z', get_post_time('U', true));
            $parselyPage['articleSection'] = $category;
            $parselyPage['creator']        = $authors;
            $parselyPage['keywords']       = array_merge($this->get_tags_as_string($post->ID, $parselyOptions),
                                                         $this->get_categories_as_tags($post, $parselyOptions));
        } elseif ( is_page() && $post->post_status == 'publish' ) {
            $parselyPage['headline']       = $this->get_clean_parsely_page_value(get_the_title());
            $parselyPage['url']            = get_permalink();
        } elseif ( is_author() ) {
            // TODO: why can't we have something like a WP_User object for all the other cases? Much nicer to deal with than functions
            $author = (get_query_var('author_name')) ? get_user_by('slug', get_query_var('author_name')) : get_userdata(get_query_var('author'));
            $parselyPage['headline']       = $this->get_clean_parsely_page_value('Author - '.$author->data->display_name);
            $parselyPage['url']            = $currentURL;
        } elseif ( is_category() ) {
            $category = get_the_category();
            $category = $category[0];
            $parselyPage['headline']       = $this->get_clean_parsely_page_value($category->name);
            $parselyPage['url']            = $currentURL;
        } elseif ( is_date() ) {
            if ( is_year() ) {
                $parselyPage['headline']   = 'Yearly Archive - ' . get_the_time('Y');
            } elseif(is_month() ) {
                $parselyPage['headline']   = 'Monthly Archive - ' . get_the_time('F, Y');
            } elseif ( is_day() ) {
                $parselyPage['headline']   = 'Daily Archive - ' . get_the_time('F jS, Y');
            } elseif ( is_time() ) {
                $parselyPage['headline']   = 'Hourly, Minutely, or Secondly Archive - ' . get_the_time('F jS g:i:s A');
            }
            $parselyPage['url']            = $currentURL;
        } elseif ( is_tag() ) {
            $tag = single_tag_title('', FALSE);
            if ( empty($tag) ) {
                $tag = single_term_title('', FALSE);
            }
            $parselyPage['headline']       = $this->get_clean_parsely_page_value('Tagged - '.$tag);
            $parselyPage['url']            = $currentURL; // get_tag_link(get_query_var('tag_id'));
        } elseif ( is_front_page() ) {
            $parselyPage['headline']       = $this->get_clean_parsely_page_value(get_bloginfo('name', 'raw'));
            $parselyPage['url']            = home_url(); // site_url();?
        }
        include('parsely-parsely-page.php');
    }

    /**
    * Inserts the JavaScript code required to send off beacon requests
    */
    public function insert_parsely_javascript() {
        $parselyOptions = $this->get_options();
        // If we don't have an API key, there's no need to proceed.
        if ( empty($parselyOptions['apikey']) ) {
            return '';
        }

        global $post;
        $display = TRUE;

        if ( is_single() && $post->post_status != 'publish' ) {
            $display = FALSE;
        }
        if ( $display ) {
            include('parsely-javascript.php');
        }
    }

    public function print_select_tag($args) {
        $options = $this->get_options();
        $name = $args['option_key'];
        $select_options = $args['select_options'];
        $selected = isset($options[$name]) ? $options[$name] : NULL;
        $optional_args = isset($args['optional_args']) ? $args['optional_args'] : array();
        $id = esc_attr($name);
        $name = Parsely::OPTIONS_KEY."[$id]";

        $tag = '<div class="parsely-form-controls"';
        if ( isset($args['help_text']) ) {
            $tag .= ' data-has-help-text="true"';
        }
        if ( isset($args['requires_recrawl']) ) {
            $tag .= ' data-requires-recrawl="true"';
        }
        $tag .= '>';

        $tag .= "<select name='$name' id='$name'";
        foreach ( $optional_args as $key => $val ) {
            $tag .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
        }
        $tag .= '>';

        foreach ( $select_options as $key => $val ) {
            $tag .= '<option value="' . esc_attr($key) . '" ';
            $tag .= selected($selected, $key, false) . '>';
            $tag .= esc_html($val);
            $tag .= '</option>';
        }
        $tag .= '</select>';


        if ( isset($args['help_text']) ) {
            $tag .= '<div class="help-text">'.
                    '<p class="description">'.$args['help_text'].'</p>'.
                    '</div>';
        }
        $tag .= '</div>';
        echo $tag;
    }

    public function print_binary_radio_tag($args) {
        $options = $this->get_options();
        $name = $args['option_key'];
        $value = $options[$name];
        $id = esc_attr($name);
        $name = Parsely::OPTIONS_KEY."[$id]";

        $tag = '<div class="parsely-form-controls"';
        if ( isset($args['help_text']) ) {
            $tag .= ' data-has-help-text="true"';
        }
        if ( isset($args['requires_recrawl']) ) {
            $tag .= ' data-requires-recrawl="true"';
        }
        $tag .= '>';

        $tag .= "<input type='radio' name='$name' id='$id"."_true' value='true' " .
                checked($value == true, true, false) . ' />' .
                "<label for='$id"."_true'>Yes</label> " .
                "<input type='radio' name='$name' id='$id"."_false' value='false' " .
                checked($value != true, true, false) . ' />' .
                "<label for='$id"."_false'>No</label>";

        if ( isset($args['help_text']) ) {
            $tag .= '<div class="help-text">'.
                    '<p class="description">'.$args['help_text'].'</p>'.
                    '</div>';
        }
        $tag .= '</div>';

        echo $tag;
    }

    public function print_text_tag($args) {
        $options = $this->get_options();
        $name = $args['option_key'];
        $value = isset($options[$name]) ? $options[$name] : '';
        $optional_args = isset($args['optional_args']) ? $args['optional_args'] : array();
        $id = esc_attr($name);
        $name = Parsely::OPTIONS_KEY."[$id]";
        $value = esc_attr($value);

        $tag = '<div class="parsely-form-controls"';
        if ( isset($args['help_text']) ) {
            $tag .= ' data-has-help-text="true"';
        }
        if ( isset($args['requires_recrawl']) ) {
            $tag .= ' data-requires-recrawl="true"';
        }
        $tag .= '>';

        $tag .= "<input type='text' name='$name' id='$id' value='$value'";
        foreach ( $optional_args as $key => $val ) {
            $tag .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
        }
        if ( isset($args['requires_recrawl']) ) {
            $tag .= ' data-requires-recrawl="true"';
        }
        $tag .= ' />';

        if ( isset($args['help_text']) ) {
            $tag .= ' <div class="help-text" id="'.
                    esc_attr($args['option_key']).'_help_text">'.
                    '<p class="description">'.$args['help_text'].'</p>'.
                    '</div>';
        }
        echo $tag;
    }

    /**
    * Extracts a host (not TLD) from a URL
    */
    private function get_host_from_url($url) {
        if ( preg_match('/^https?:\/\/([^\/]+)\/.*$/', $url, $matches) ) {
            return $matches[1];
        } else {
            return $url;
        }
    }

    /**
    * Returns an array of strings associated with this page or post
    */
    private function get_tags_as_string($postId, $parselyOptions) {
        $wpTags = wp_get_post_tags($postId);
        $tags = array();
        foreach ( $wpTags as $wpTag ) {
            if ( $parselyOptions['lowercase_tags'] === true ) {
                $wpTag->name = strtolower($wpTag->name);
            }
            array_push($tags, $this->get_clean_parsely_page_value($wpTag->name));
        }
        return $tags;
    }

    /**
    * Safely returns options for the plugin by assigning defaults contained in optionDefaults.  As soon as actual
    * options are saved, they override the defaults.  This prevents us from having to do a lot of isset() checking
    * on variables.
    */
    private function get_options() {
        $options = get_option(Parsely::OPTIONS_KEY);
        if ( $options === false ) {
            $options = $this->optionDefaults;
        } else {
            $options = array_merge($this->optionDefaults, $options);
        }
        return $options;
    }

    /**
     * Returns an array of all the child categories for the current post delimited by a '/' if instructed
     * to do so via the `child_cats_as_tags` option.
     */
    private function get_categories_as_tags($postObj, $parselyOptions) {
        $tags = array();
        if ( !$parselyOptions['child_cats_as_tags'] ) {
            return $tags;
        }

        $categories = get_the_category($postObj->ID);
        $sectionName = $this->get_category_name($postObj, $parselyOptions);

        if ( empty($categories) ) {
            return $tags;
        }
        foreach( $categories as $category ) {
            $hierarchy = get_category_parents($category, FALSE, Parsely::CATEGORY_DELIMITER);
            $hierarchy = explode(Parsely::CATEGORY_DELIMITER, $hierarchy);
            $hierarchy = array_filter($hierarchy);
            if ( sizeof($hierarchy) == 1 && $hierarchy[0] == $sectionName ) {
                // Don't take top level categories if we're already tracking
                // using a section
                continue;
            }
            $hierarchy = join('/', $hierarchy);
            if ( $parselyOptions['lowercase_tags'] === true ) {
                $hierarchy = strtolower($hierarchy);
            }

            array_push($tags, $this->get_clean_parsely_page_value($hierarchy));
        }
        $tags = array_unique($tags);

        return $tags;
    }

    /**
    * Returns a properly cleaned category name and will optionally use the top-level category name if so instructed
    * to via the `use_top_level_cats` option.
    */
    private function get_category_name($postObj, $parselyOptions) {
        $category   = get_the_category($postObj->ID);

        // Customers with different post types may not have categories
        if ( !empty($category) ) {
            $category   = $parselyOptions['use_top_level_cats'] ? $this->get_top_level_category($category[0]->cat_ID) : $category[0]->name;
        } else {
            $category = 'Uncategorized';
        }

        return $this->get_clean_parsely_page_value($category);
    }

    /**
    * Returns the top most category in the hierarchy given a category ID.
    */
    private function get_top_level_category($categoryId) {
        $categories = get_category_parents($categoryId, FALSE, Parsely::CATEGORY_DELIMITER);
        $categories = explode(Parsely::CATEGORY_DELIMITER, $categories);
        $topLevel = $categories[0];
        return $topLevel;
    }

    /**
     * Returns a list of coauthors for a post assuming the coauthors plugin is
     * installed. Borrowed from
     * https://github.com/Automattic/Co-Authors-Plus/blob/master/template-tags.php#L3-35
     */
    private function get_coauthor_names($post_id) {
        $coauthors = array();
        if (class_exists('coauthors_plus')) {
            global $post, $post_ID, $coauthors_plus, $wpdb;

            $post_id = (int)$post_id;
            if ( !$post_id && $post_ID )
                $post_id = $post_ID;
            if ( !$post_id && $post )
                $post_id = $post->ID;

            if ( $post_id ) {
                $coauthor_terms = get_the_terms( $post_id, $coauthors_plus->coauthor_taxonomy );

                if ( is_array( $coauthor_terms ) && !empty( $coauthor_terms ) ) {
                    foreach( $coauthor_terms as $coauthor ) {
                        $coauthor_slug = preg_replace( '#^cap\-#', '', $coauthor->slug );
                        $post_author =  $coauthors_plus->get_coauthor_by( 'user_nicename', $coauthor_slug );
                        // In case the user has been deleted while plugin was deactivated
                        if ( !empty( $post_author ) )
                            $coauthors[] = $post_author;
                    }
                } else if ( !$coauthors_plus->force_guest_authors ) {
                    if ( $post && $post_id == $post->ID ) {
                        $post_author = get_userdata( $post->post_author );
                    } else {
                        $post_author = get_userdata( $wpdb->get_var( $wpdb->prepare("SELECT post_author FROM $wpdb->posts WHERE ID = %d", $post_id ) ) );
                    }
                    if ( !empty( $post_author ) )
                        $coauthors[] = $post_author;
                } // the empty else case is because if we force guest authors, we don't ever care what value wp_posts.post_author has.
            }
        }
        return $coauthors;
    }

    /**
    * Determine author name from display name, falling back to firstname +
    * lastname, then nickname and finally the nicename.
    */
    private function get_author_name($author) {
        $author_name = $author->display_name;
        if ( !empty($author_name) ) {
            return $author_name;
        }

        $author_name = $author->user_firstname . ' ' . $author->user_lastname;
        if ( $author_name != ' ' ) {
            return $author_name;
        }

        $author_name = $author->nickname;
        if ( !empty($author_name) ) {
            return $author_name;
        }

        return $author->user_nicename;
    }

    /**
     * Retrieve all the authors for a post as an array. Can include multiple
     * authors if coauthors plugin is in use.
     */
    private function get_author_names($post) {
        $authors = $this->get_coauthor_names($post->ID);
        if ( empty($authors) ) {
            $authors = array(get_user_by('id', $post->post_author));
        }
        $authors = array_map(array($this, 'get_author_name'), $authors);
        $authors = array_map(array($this, 'get_clean_parsely_page_value'), $authors);
        return $authors;
    }

    /* sanitize content
    */
    private function get_clean_parsely_page_value($val) {
        if ( is_string($val) ) {
            $val = str_replace("\n", '', $val);
            $val = str_replace("\r", '', $val);
            $val = strip_tags($val);
            $val = trim($val);
            return $val;
        } else {
            return $val;
        }
    }


    /* Get the URL of the plugin settings page */
    private function get_settings_url() {
        return admin_url('options-general.php?page='.Parsely::MENU_SLUG);
    }


    /**
    * Get the URL of the current PHP script.
    * A fall-back implementation to determine permalink
    */
    private function get_current_url() {
        $pageURL = (is_ssl() ? 'https://' : 'http://');
        $pageURL .= $_SERVER['HTTP_HOST'];
        if ( $_SERVER['SERVER_PORT'] != '80' ) {
            $pageURL .= ':'.$_SERVER['SERVER_PORT'];
        }
        $pageURL .= $_SERVER['REQUEST_URI'];
        return $pageURL;
    }

    private function upgrade_plugin_to_version_1_3($options) {
        if ( isset($options['tracker_implementation']) && $options['tracker_implementation'] == 'async' ) {
            $options['tracker_implementation'] = $this->optionDefaults['tracker_implementation'];
        }
        update_option(Parsely::OPTIONS_KEY, $options);
    }

    private function upgrade_plugin_to_version_1_4($options) {
        $this->upgrade_plugin_to_version_1_3($options);
    }

    private function upgrade_plugin_to_version_1_5($options) {
        $this->upgrade_plugin_to_version_1_4($options);
    }

    private function upgrade_plugin_to_version_1_6($options) {
        // As of version 1.6, we no longer offer tracker_implementation as a
        // setting so we'll delete it
        if ( isset($options['tracker_implementation']) ) {
            unset($options['tracker_implementation']);
        }
        update_option(Parsely::OPTIONS_KEY, $options);
    }
}

if ( class_exists('Parsely') ) {
    define('PARSELY_VERSION', Parsely::VERSION);
    $parsely = new Parsely();
}

