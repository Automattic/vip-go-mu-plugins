<?php
/*******************************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 ******************************************************************************/

/*
Plugin Name: WordPress Editorial Calendar
Description: The Editorial Calendar makes it possible to see all your posts and drag and drop them to manage your blog.
Version: 1.3.4
Author: Colin Vernon, Justin Evans, Mary Vogt, and Zack Grossbart
Author URI: http://www.zackgrossbart.com
Plugin URI: http://stresslimitdesign.com/editorial-calendar-plugin
*/

add_action('wp_ajax_edcal_saveoptions', 'edcal_saveoptions' );
add_action('wp_ajax_edcal_changedate', 'edcal_changedate' );
//add_action('wp_ajax_edcal_newdraft', 'edcal_newdraft' );
add_action('wp_ajax_edcal_savepost', 'edcal_savepost' );
add_action('wp_ajax_edcal_changetitle', 'edcal_changetitle' );
add_action('admin_menu', 'edcal_list_add_management_page');
add_action('wp_ajax_edcal_posts', 'edcal_posts' );
add_action('wp_ajax_edcal_getpost', 'edcal_getpost' );
add_action('wp_ajax_edcal_deletepost', 'edcal_deletepost' );
//add_action("admin_print_scripts", 'edcal_scripts');
add_action("init", 'edcal_load_language');

/*
 * This error code matches CONCURRENCY_ERROR from edcal.js
 */
$EDCAL_CONCURRENCY_ERROR = "4";

/*
 * This error code matches PERMISSION_ERROR from edcal.js
 */
$EDCAL_PERMISSION_ERROR = "5";

/*
 * This error code matches NONCE_ERROR from edcal.js
 */
$EDCAL_NONCE_ERROR = "6";

/*
 * This boolean variable will be used to check whether this 
 * installation of WordPress supports custom post types.
 */
$edcal_supports_custom_types = function_exists('get_post_types') && function_exists('get_post_type_object');

function edcal_load_language() {
    //$plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain( 'editorial-calendar', false, dirname( __FILE__ ) . '/languages/' );
}

/*
 * This function adds our calendar page to the admin UI
 */
function edcal_list_add_management_page(  ) {
	global $edcal_supports_custom_types;
    if ( function_exists('add_management_page') ) {
        $page = add_posts_page( __('Calendar', 'editorial-calendar'), __('Calendar', 'editorial-calendar'), 'edit_posts', 'cal', 'edcal_list_admin' );
        add_action( "admin_print_scripts-$page", 'edcal_scripts' );
        
		if($edcal_supports_custom_types) {

	        /* 
	         * We add one calendar for Posts and then we add a separate calendar for each
	         * custom post type.  This calendar will have an URL like this:
	         * /wp-admin/edit.php?post_type=podcasts&page=cal_podcasts
	         *
	         * We can then use the post_type parameter to show the posts of just that custom
	         * type and update the labels for each post type.
	         */
	        $args = array(
	            'public'   => true,
	            '_builtin' => false
	        ); 
	        $output = 'names'; // names or objects
	        $operator = 'and'; // 'and' or 'or'
	        $post_types = get_post_types($args,$output,$operator); 
        
	        foreach ($post_types as $post_type) {
	            $page = add_submenu_page('edit.php?post_type=' . $post_type, __('Calendar', 'editorial-calendar'), __('Calendar', 'editorial-calendar'), 'edit_posts', 'cal_' . $post_type, 'edcal_list_admin');
	            add_action( "admin_print_scripts-$page", 'edcal_scripts' );
	        }

		}
    }
}

/*
 * This is the function that generates our admin page.  It adds the CSS files and 
 * generates the divs that we need for the JavaScript to work.
 */
function edcal_list_admin() {
    include_once('edcal.php');
    
    /*
     * We want to count the number of times they load the calendar
     * so we only show the feedback after they have been using it 
     * for a little while.
     */
    /*
	$edcal_count = get_option("edcal_count");
    if ($edcal_count == '') {
        $edcal_count = 0;
        add_option("edcal_count", $edcal_count, "", "yes");
    }
	*/
        
    /*
	if (get_option("edcal_do_feedback") != "done") {
        $edcal_count++;
        update_option("edcal_count", $edcal_count);
    }
	*/
    
    
    /*
     * This section of code embeds certain CSS and
     * JavaScript files into the HTML.  This has the 
     * advantage of fewer HTTP requests, but the 
     * disadvantage that the browser can't cache the
     * results.  We only do this for files that will
     * be used on this page and nowhere else.
     */
     
    echo '<!-- This is the styles from time picker.css -->';
    echo '<style type="text/css">';
    echo '@import url("' . content_url( 'themes/vip/plugins/editorial-calendar/lib/timePicker.css' ) . '");';
    echo '</style>';
	
    echo '<!-- This is the styles from humanmsg.css -->';
    echo '<style type="text/css">';
    echo '@import url("' . content_url( 'themes/vip/plugins/editorial-calendar/lib/humanmsg.css' ) . '");';
    echo '</style>';
    
    echo '<!-- This is the styles from edcal.css -->';
    echo '<style type="text/css">';
    echo '@import url("' . content_url( 'themes/vip/plugins/editorial-calendar/edcal.css' ) . '");';
    echo '</style>';
    
    ?>
    
    <!-- This is just a little script so we can pass the AJAX URL and some localized strings -->
    <script type="text/javascript">
        jQuery(document).ready(function(){
            edcal.wp_nonce = '<?php echo wp_create_nonce("edit-calendar"); ?>';
            <?php 
                if (get_option("edcal_weeks_pref") != "") {
            ?>
                edcal.weeksPref = <?php echo(get_option("edcal_weeks_pref")); ?>;
            <?php
                }
            ?>
            
            <?php 
                if (get_option("edcal_author_pref") != "") {
            ?>
                edcal.authorPref = <?php echo(get_option("edcal_author_pref")); ?>;
            <?php
                }
            ?>
            
            <?php 
                if (get_option("edcal_time_pref") != "") {
            ?>
                edcal.timePref = <?php echo(get_option("edcal_time_pref")); ?>;
            <?php
                }
            ?>
            
            <?php 
                if (get_option("edcal_status_pref") != "") {
            ?>
                edcal.statusPref = <?php echo(get_option("edcal_status_pref")); ?>;
            <?php
                }
            ?>

            edcal.startOfWeek = <?php echo(get_option("start_of_week")); ?>;
            edcal.timeFormat = "<?php echo(get_option("time_format")); ?>";
            edcal.previewDateFormat = "MMMM d";

            /*
             * We want to show the day of the first day of the week to match the user's 
             * country code.  The problem is that we can't just use the WordPress locale.
             * If the locale was fr-FR so we started the week on Monday it would still 
             * say Sunday was the first day if we didn't have a proper language bundle
             * for French.  Therefore we must depend on the language bundle writers to
             * specify the locale for the language they are adding.
             * 
             */
            edcal.locale = '<?php echo(__('en-US', 'editorial-calendar')) ?>';
            
            /*
             * These strings are all localized values.  The WordPress localization mechanism 
             * doesn't really extend to JavaScript so we localize the strings in PHP and then
             * pass the values to JavaScript.
             */
            
            edcal.str_by = <?php echo(edcal_json_encode(__('%1$s by %2$s', 'editorial-calendar'))) ?>;
            
            edcal.str_addPostLink = <?php echo(edcal_json_encode(__('New Post', 'editorial-calendar'))) ?>;
            
            edcal.str_draft = <?php echo(edcal_json_encode(__(' [DRAFT]', 'editorial-calendar'))) ?>;
            edcal.str_pending = <?php echo(edcal_json_encode(__(' [PENDING]', 'editorial-calendar'))) ?>;
            edcal.str_sticky = <?php echo(edcal_json_encode(__(' [STICKY]', 'editorial-calendar'))) ?>;
            edcal.str_draft_sticky = <?php echo(edcal_json_encode(__(' [DRAFT, STICKY]', 'editorial-calendar'))) ?>;
            edcal.str_pending_sticky = <?php echo(edcal_json_encode(__(' [PENDING, STICKY]', 'editorial-calendar'))) ?>;
            edcal.str_edit = <?php echo(edcal_json_encode(__('Edit', 'editorial-calendar'))) ?>;
            edcal.str_quick_edit = <?php echo(edcal_json_encode(__('Quick Edit', 'editorial-calendar'))) ?>;
            edcal.str_del = <?php echo(edcal_json_encode(__('Delete', 'editorial-calendar'))) ?>;
            edcal.str_view = <?php echo(edcal_json_encode(__('View', 'editorial-calendar'))) ?>;
            edcal.str_republish = <?php echo(edcal_json_encode(__('Edit', 'editorial-calendar'))) ?>;
            edcal.str_status = <?php echo(edcal_json_encode(__('Status:', 'editorial-calendar'))) ?>;
            edcal.str_cancel = <?php echo(edcal_json_encode(__('Cancel', 'editorial-calendar'))) ?>;
            edcal.str_posttitle = <?php echo(edcal_json_encode(__('Title', 'editorial-calendar'))) ?>;
            edcal.str_postcontent = <?php echo(edcal_json_encode(__('Content', 'editorial-calendar'))) ?>;
            edcal.str_newpost = <?php echo(edcal_json_encode(__('Add a new post on %s', 'editorial-calendar'))) ?>;
            edcal.str_newpost_title = <?php echo(edcal_json_encode(sprintf(__('New %s - ', 'editorial-calendar'), edcal_get_posttype_singlename()))) ?> ;
            edcal.str_update = <?php echo(edcal_json_encode(__('Update', 'editorial-calendar'))) ?>;
            edcal.str_publish = <?php echo(edcal_json_encode(__('Schedule', 'editorial-calendar'))) ?>;
            edcal.str_review = <?php echo(edcal_json_encode(__('Submit for Review', 'editorial-calendar'))) ?>;
            edcal.str_save = <?php echo(edcal_json_encode(__('Save', 'editorial-calendar'))) ?>;
            edcal.str_edit_post_title = <?php echo(edcal_json_encode(__('Edit %1$s - %2$s', 'editorial-calendar'))) ?>;
            edcal.str_scheduled = <?php echo(edcal_json_encode(__('Scheduled', 'editorial-calendar'))) ?>;
            
            edcal.str_del_msg1 = <?php echo(edcal_json_encode(__('You are about to delete the post "', 'editorial-calendar'))) ?>;
            edcal.str_del_msg2 = <?php echo(edcal_json_encode(__('". Press Cancel to stop, OK to delete.', 'editorial-calendar'))) ?>;
            
            edcal.concurrency_error = <?php echo(edcal_json_encode(__('Looks like someone else already moved this post.', 'editorial-calendar'))) ?>;
            edcal.permission_error = <?php echo(edcal_json_encode(__('You do not have permission to edit posts.', 'editorial-calendar'))) ?>;
            edcal.checksum_error = <?php echo(edcal_json_encode(__('Invalid checksum for post. This is commonly a cross-site scripting error.', 'editorial-calendar'))) ?>;
            edcal.general_error = <?php echo(edcal_json_encode(__('There was an error contacting your blog.', 'editorial-calendar'))) ?>;
            
            edcal.str_screenoptions = <?php echo(edcal_json_encode(__('Screen Options', 'editorial-calendar'))) ?>;
            edcal.str_optionscolors = <?php echo(edcal_json_encode(__('Colors', 'editorial-calendar'))) ?>;
            edcal.str_optionsdraftcolor = <?php echo(edcal_json_encode(__('Drafts: ', 'editorial-calendar'))) ?>;
            edcal.str_apply = <?php echo(edcal_json_encode(__('Apply', 'editorial-calendar'))) ?>;
            edcal.str_show_title = <?php echo(edcal_json_encode(__('Show on screen', 'editorial-calendar'))) ?>;
            edcal.str_opt_weeks = <?php echo(edcal_json_encode(__(' weeks at a time', 'editorial-calendar'))) ?>;
            edcal.str_show_opts = <?php echo(edcal_json_encode(__('Show in Calendar Cell', 'editorial-calendar'))) ?>;
            edcal.str_opt_author = <?php echo(edcal_json_encode(__('Author', 'editorial-calendar'))) ?>;
            edcal.str_opt_status = <?php echo(edcal_json_encode(__('Status', 'editorial-calendar'))) ?>;
            edcal.str_opt_time = <?php echo(edcal_json_encode(__('Time of day', 'editorial-calendar'))) ?>;
            edcal.str_fatal_error = <?php echo(edcal_json_encode(__('An error occurred while loading the calendar: ', 'editorial-calendar'))) ?>;
            
            edcal.str_weekserror = <?php echo(edcal_json_encode(__('The calendar can only show between 1 and 5 weeks at a time.', 'editorial-calendar'))) ?>;
            edcal.str_weekstt = <?php echo(edcal_json_encode(__('Select the number of weeks for the calendar to show.', 'editorial-calendar'))) ?>;

            edcal.str_feedbackmsg = <?php echo(edcal_json_encode(__('<div id="feedbacksection">' . 
             '<h2>Help us Make the Editorial Calendar Better</h2>' .
             'We are always trying to improve the Editorial Calendar and you can help. May we collect some data about your blog and browser settings to help us improve this plugin?  We\'ll only do it once and your blog will show up on our <a target="_blank" href="http://www.zackgrossbart.com/edcal/mint/">Editorial Calendar Statistics page</a>.<br /><br />' . 
             '<button class="button-secondary" onclick="edcal.doFeedback();">Collect Anonymous Data</button> ' . 
             '<a href="#" id="nofeedbacklink" onclick="edcal.noFeedback(); return false;">No thank you</a></div>', 'editorial-calendar'))) ?>;

            edcal.str_feedbackdone = <?php echo(edcal_json_encode(__('<h2>We\'re done</h2>We\'ve finished collecting data.  Thank you for helping us make the calendar better.', 'editorial-calendar'))) ?>;
        });
    </script>

    <style type="text/css">
        .loadingclass > .postlink, .loadingclass:hover > .postlink, .tiploading {
            background-image: url('images/loading.gif');
        }

        #loading {
            background-image: url('images/loading.gif');
        }

        #tipclose {
            background-image: url('<?php echo content_url( 'themes/vip/plugins/editorial-calendar/images/tip_close.png' ); ?>');
        }

        #tooltip {
            background: white url('images/gray-grad.png') repeat-x left top;
        }
        
        .month-present .daylabel, .firstOfMonth .daylabel, .dayheadcont {
            background: #6D6D6D url('images/gray-grad.png') repeat-x scroll left top;
        }

        .today .daylabel {
            background: url('images/button-grad.png') repeat-x left top;
        }

    </style>
    
    <?php
    echo '<!-- This is the code from edcal.js -->';
    echo '<script type="text/javascript" src="' . content_url( 'themes/vip/plugins/editorial-calendar/edcal.js' ) . '"></script>';
    
    ?>
    
    <div class="wrap">
        <div class="icon32" id="icon-edit"><br/></div>
        <h2 id="edcal_main_title"><?php echo(edcal_get_posttype_multiplename()); ?><?php echo(__(' Calendar', 'editorial-calendar')); ?></h2>
        
        <div id="loadingcont">
            <div id="loading"> </div>
        </div>
        
        <div id="topbar" class="tablenav">
            <div id="topleft" class="tablenav-pages">
                <h3>
                    <a href="#" title="<?php echo(__('Jump back', 'editorial-calendar')) ?>" class="prev page-numbers" id="prevmonth">&laquo;</a>
                    <span id="currentRange"></span>
                    <a href="#" title="<?php echo(__('Skip ahead', 'editorial-calendar')) ?>" class="next page-numbers" id="nextmonth">&raquo;</a>
                </h3>
            </div>
            
            <div id="topright">
                <button class="save button" title="<?php echo(__('Scroll the calendar and make the today visible', 'editorial-calendar')) ?>" id="moveToToday"><?php echo(__('Show Today', 'editorial-calendar')) ?></button>
            </div>
        </div>
        
        <div id="cal_cont">
            <div id="edcal_scrollable" class="edcal_scrollable vertical">
                <div id="cal"></div>
            </div>
        </div>
		
		<div id="tooltip" style="display:none;">
			<div id="tooltiphead">
				<div id="tooltiptitle"><?php _e('Edit Post', 'editorial-calendar') ?></div>
				<a href="#" id="tipclose" onclick="edcal.hideForm(); return false;" title="close"> </a>
			</div>

			<div class="tooltip inline-edit-row">

                <fieldset>

                <label>
					<span class="title"><?php _e('Title', 'editorial-calendar') ?></span>
					<span class="input-text-wrap"><input type="text" class="ptitle" id="edcal-title-new-field" name="title" /></span>
    			</label>

                <label>
                    <span class="title"><?php _e('Content', 'editorial-calendar') ?></span>
<?php /*
                       <div id="cal_mediabar">
    						<?php if ( current_user_can( 'upload_files' ) ) : ?>
    							<div id="media-buttons" class="hide-if-no-js">
    								<?php do_action( 'media_buttons' ); ?>
    							</div>
    						<?php endif; ?>
    				   </div>
/*/ ?>
                    <span class="input-text-wrap"><textarea cols="15" rows="7" id="content" name="content"></textarea></span>
                </label>


                <label>
                    <span class="title"><?php _e('Time', 'editorial-calendar') ?></span>
                    <span class="input-text-wrap"><input type="text" class="ptitle" id="edcal-time" name="time" value="" size="8" readonly="true" maxlength="8" autocomplete="off" /></span>
                </label>
					
                <label>
                    <span class="title"><?php _e('Status', 'editorial-calendar') ?></span>
                    <span class="input-text-wrap">
                        <select name="status" id="edcal-status">
                            <option value="draft"><?php _e('Draft', 'editorial-calendar') ?></option>
                            <option value="pending"><?php _e('Pending Review', 'editorial-calendar') ?></option>
                            <?php if ( current_user_can('publish_posts') ) {?>
                                <option id="futureoption" value="future"><?php _e('Scheduled', 'editorial-calendar') ?></option>
                            <?php } ?>
                        </select>
                    </span>
				</label>

<?php /*                <label>
                    <span class="title"><?php _e('Author', 'editorial-calendar') ?></span>
                    <span id="edcal-author-p"><!-- Placeholder for the author's name, added dynamically --></span>
                </label>
*/ ?>
                </fieldset>

				<p class="submit inline-edit-save" id="edit-slug-buttons">
                    <a class="button-primary disabled" id="newPostScheduleButton" href="#"><?php _e('Schedule', 'editorial-calendar') ?></a>
                    <a href="#" onclick="edcal.hideForm(); return false;" class="button-secondary cancel"><?php _e('Cancel', 'editorial-calendar') ?></a>
                </p>

                <input type="hidden" id="edcal-date" name="date" value="" />
                <input type="hidden" id="edcal-id" name="id" value="" />

            </div><?php // end .tooltip ?>
        </div><?php // end #tooltip ?>

    </div><?php // end .wrap ?>

    <?php
}

/*
 * We use these variables to hold the post dates for the filter when 
 * we do our post query.
 */
$edcal_startDate;
$edcal_endDate;

/*
 * When we get a set of posts to populate the calendar we don't want
 * to get all of the posts.  This filter allows us to specify the dates
 * we want.
 */
function edcal_filter_where($where = '') {
    global $edcal_startDate, $edcal_endDate, $wpdb;
    //posts in the last 30 days
    //$where .= " AND post_date > '" . date('Y-m-d', strtotime('-30 days')) . "'";
    //posts  30 to 60 days old
    //$where .= " AND post_date >= '" . date('Y-m-d', strtotime('-60 days')) . "'" . " AND post_date <= '" . date('Y-m-d', strtotime('-30 days')) . "'";
    //posts for March 1 to March 15, 2009
    $where .= $wpdb->prepare( ' AND post_date >= %s AND post_date < %s', $edcal_startDate, $edcal_endDate );
    return $where;
}

/*
 * This function adds all of the JavaScript files we need.
 *
 */
function edcal_scripts() {
    /*
     * To get proper localization for dates we need to include the correct JavaScript file for the current
     * locale.  We can do this based on the locale in the localized bundle to make sure the date locale matches
     * the locale for the other strings.
     */
    wp_enqueue_script( 'edcal-date', content_url( 'themes/vip/plugins/editorial-calendar/lib/languages/date-en-US.js' ), array( 'jquery' ) );
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'jquery-ui-draggable' );
    wp_enqueue_script( 'jquery-ui-droppable' );

    wp_enqueue_script( "edcal-lib", content_url( 'themes/vip/plugins/editorial-calendar/lib/edcallib.min.js' ), array( 'jquery' ) );

    return;
}

/*
 * This is an AJAX call that gets the posts between the from date 
 * and the to date.  
 */
function edcal_posts() {
    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    if (!edcal_checknonce() ) {
        die('edcal_posts fail');
    }
    
    global $edcal_startDate, $edcal_endDate;
    $edcal_startDate = isset($_GET['from'])?$_GET['from']:null;
    $edcal_endDate = isset($_GET['to'])?$_GET['to']:null;
    global $post;
    $args = array(
        'posts_per_page' => -1,
        'post_status' => "publish&future&draft",
        'post_parent' => null // any parent
    );
    
    /* 
     * If we're in the specific post type case we need to add
     * the post type to our query.
     */
    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
    if ($post_type) {
        $args['post_type'] = $post_type;
    }
    
    add_filter('posts_where', 'edcal_filter_where');
    $myposts = query_posts($args);
    remove_filter('posts_where', 'edcal_filter_where');
    
    ?>[
    <?php
    $size = sizeof($myposts);
    
    for($i = 0; $i < $size; $i++) {	
        $post = $myposts[$i];
        edcal_postJSON($post, $i < $size - 1);
    }
    
    ?> ]
    <?php
    
    die();
}

/*
 * This is for an AJAX call that returns a post with the specified ID
 */
function edcal_getpost() {
	
	header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
	
	// If nonce fails, return
	if (!edcal_checknonce() ) die('edcal_getpost fail');
	
	$post_id = intval($_GET['postid']);
	
	// If a proper post_id wasn't passed, return
	if(!$post_id) die('edcal_getpost invalid post ID');
    
    $args = array(
        'post__in' => array($post_id)
    );
    
    /* 
     * If we're in the specific post type case we need to add
     * the post type to our query.
     */
    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
    if ($post_type) {
        $args['post_type'] = $post_type;
    }
	
	$post = query_posts($args);
    
	// get_post and setup_postdata don't get along, so we're doing a mini-loop
	if(have_posts()) :
		while(have_posts()) : the_post();
			?>
			{
			"post" :
				<?php
				edcal_postJSON($post[0], false, true);
				?>
			}
			<?php
		endwhile;
	endif;
	die();
}

function edcal_json_encode($string) {
    /*
     * WordPress escapes apostrophe's when they show up in post titles as &#039;
     * This is the HTML ASCII code for a straight apostrophe.  This works well
     * with Firefox, but IE complains with a very unhelpful error message.  We
     * can replace them with a right curly apostrophe since that works in IE
     * and Firefox.  It is also a little nicer typographically.  
     */
    return json_encode(str_replace("&#039;", "&#146;", $string));
}

/* 
 * This helper functions gets the plural name of the post
 * type specified by the post_type parameter.
 */
function edcal_get_posttype_multiplename() {

    $post_type = isset ( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : false;
    if (!$post_type) {
        return 'Posts';
    }
		
    $postTypeObj = get_post_type_object($post_type);
    return $postTypeObj->labels->name;
}

/* 
 * This helper functions gets the singular name of the post
 * type specified by the post_type parameter.
 */

function edcal_get_posttype_singlename() {

    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
    if (!$post_type) {
        return 'Post';
    }

    $postTypeObj = get_post_type_object($post_type);
    return $postTypeObj->labels->singular_name;
}

/*
 * This function sets up the post data and prints out the values we
 * care about in a JSON data structure.  This prints out just the
 * value part. If $fullPost is set to true, post_content is also returned.
 */
function edcal_postJSON($post, $addComma = true, $fullPost = false) {
	global $edcal_supports_custom_types;
    $timeFormat = get_option("time_format");
    if ($timeFormat == "g:i a") {
        $timeFormat = "ga";
    } else if ($timeFormat == "g:i A") {
        $timeFormat = "gA";
    } else if ($timeFormat == "H:i") {
        $timeFormat = "H";
    }
    
    setup_postdata($post);
    
    if (get_post_status() == 'auto-draft') {
        /*
         * WordPress 3 added a new post status of auto-draft so
         * we want to hide them from the calendar
         */
        return;
    }
    
    /* 
     * We want to return the type of each post as part of the
     * JSON data about that post.  Right now this will always
     * match the post_type parameter for the calendar, but in
     * the future we might support a mixed post type calendar
     * and this extra data will become useful.  Right now we
     * are using this data for the title on the quick edit form.
     */
	if($edcal_supports_custom_types) {
	    $postTypeObj = get_post_type_object(get_post_type( $post ));
	    $postTypeTitle = $postTypeObj->labels->singular_name;
	} else {
	    $postTypeTitle = 'post';
	}
    ?>
        {
            "date" : "<?php the_time('d') ?><?php the_time('m') ?><?php the_time('Y') ?>", 
            "time" : "<?php the_time() ?>", 
            "formattedtime" : "<?php edcal_json_encode(the_time($timeFormat)); ?>", 
            "sticky" : "<?php echo(is_sticky($post->ID)); ?>",
            "url" : "<?php edcal_json_encode(the_permalink()); ?>", 
            "status" : "<?php echo(get_post_status()); ?>",
            "title" : <?php echo(edcal_json_encode(get_the_title())); ?>,
            "author" : <?php echo(edcal_json_encode(get_the_author())); ?>,
            "type" : "<?php echo(get_post_type( $post )); ?>",
            "typeTitle" : "<?php echo($postTypeTitle); ?>",

            <?php if ( current_user_can('edit_post', $post->ID) ) {?>
            "editlink" : "<?php echo(get_edit_post_link($post->ID)); ?>",
            <?php } ?>

            <?php if ( current_user_can('delete_post', $post->ID) ) {?>
            "dellink" : "javascript:edcal.deletePost(<?php echo $post->ID ?>)",
            <?php } ?>

            "permalink" : "<?php echo(get_permalink($post->ID)); ?>",
            "id" : "<?php the_ID(); ?>"
			
			<?php if($fullPost) : ?>
			, "content" : <?php echo edcal_json_encode($post->post_content) ?>
			
			<?php endif; ?>
        }
    <?php
    if ($addComma) {
        ?>,<?php
    }
}

/*
 * This is a helper AJAX function to delete a post. It gets called
 * when a user clicks the delete button, and allows the user to 
 * retain their position within the calendar without a page refresh.
 * It is not called unless the user has permission to delete the post
 */
function edcal_deletepost() {
	if (!edcal_checknonce()) {
		die('edcal_deletepost fail');
	}

    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    $edcal_postid = isset($_GET['postid'])?$_GET['postid']:null;
    $post = get_post($edcal_postid, ARRAY_A);
	$title = $post['post_title'];
	$date = date('dmY', strtotime($post['post_date'])); // [TODO] : is there a better way to generate the date string ... ??

	if ( ! current_user_can( 'delete_post', $edcal_postid ) )
		exit('edcal_deletepost permissions check fail');

	$force = !EMPTY_TRASH_DAYS;					// wordpress 2.9 thing. deleted post hangs around (ie in a recycle bin) after deleted for this # of days
	if ( $post->post_type == 'attachment' ) {
		$force = ( $force || !MEDIA_TRASH );
		if ( ! wp_delete_attachment($edcal_postid, $force) )
			wp_die( __('Error in deleting...') );
	} else {
		if ( !wp_delete_post($edcal_postid, $force) )
			wp_die( __('Error in deleting...') );
	}

//	return the following info so that jQuery can then remove post from edcal display :
?>
{
    "post" :
	{
        "date" : "<?php echo $date ?>", 
        "title" : "<?php echo $title ?>",
        "id" : "<?php echo $edcal_postid ?>"
	}
}
<?php

	die();	
}




/*
 * This is a helper AJAX function to change the title of a post.  It
 * gets called from the save button in the tooltip when you change a
 * post title in a calendar.
 */
function edcal_changetitle() {
    if (!edcal_checknonce()) {
        die('edcal_changetitle fail');
    }

    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    $edcal_postid = isset($_GET['postid'])?$_GET['postid']:null;
    $edcal_newTitle = isset($_GET['title'])?$_GET['title']:null;

	if ( ! current_user_can( 'edit_post', $edcal_postid ) )
		exit('edcal_changetitle permissions check fail');
    
    $post = get_post($edcal_postid, ARRAY_A);
    setup_postdata($post);
    
    $post['post_title'] = $edcal_newTitle;
    
    /*
     * Now we finally update the post into the database
     */
    wp_update_post( $post );
    
    /*
     * We finish by returning the latest data for the post in the JSON
     */
    global $post;
    $args = array(
        'posts_id' => $edcal_postid,
    );
    
    $post = get_post($edcal_postid);
    
    ?>{
        "post" :
    <?php
    
        edcal_postJSON($post);
    
    ?>
    }
    <?php
    
    
    die();
}

/*
 * This is a helper function to create a new blank draft
 * post on a specified date.
 */
function edcal_newdraft() {
    if (!edcal_checknonce()) {
        die('edcal_newdraft fail');
    }

    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    $edcal_date = isset($_POST["date"])?$_POST["date"]:null;
    
    $my_post = array();
    $my_post['post_title'] = isset($_POST["title"])?$_POST["title"]:null;
    $my_post['post_content'] = isset($_POST["content"])?$_POST["content"]:null;
    $my_post['post_status'] = 'draft';
    
    $my_post['post_date'] = $edcal_date;
    $my_post['post_date_gmt'] = get_gmt_from_date($edcal_date);
    $my_post['post_modified'] = $edcal_date;
    $my_post['post_modified_gmt'] = get_gmt_from_date($edcal_date);
    
    // Insert the post into the database
    $my_post_id = wp_insert_post( $my_post );
    
    /*
     * We finish by returning the latest data for the post in the JSON
     */
    global $post;
    $post = get_post($my_post_id);

    ?>{
        "post" :
    <?php
    
        edcal_postJSON($post, false);
    
    ?>
    }
    <?php
    
    die();
}

/*
 * This is a helper function to create a new draft post on a specified date
 * or update an existing post
 */
function edcal_savepost() {
	
	if (!edcal_checknonce()) {
        die('edcal_savepost fail');
    }

    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    $edcal_date = isset($_POST["date"])?$_POST["date"]:null;
    
    $my_post = array();
	
	// If the post id is not specified, we're creating a new post
	if($_POST['id']) {
		$my_post['ID'] = intval($_POST['id']);

		if ( ! current_user_can( 'edit_post', $my_post['ID'] ) )
			exit('edcal_savepost permissions check fail');
    } else {
        $my_post['post_status'] = 'draft'; // if new post, set the status to draft
    }
        
    $my_post['post_title'] = isset($_POST["title"])?$_POST["title"]:null;
    $my_post['post_content'] = isset($_POST["content"])?$_POST["content"]:null;
    
    $my_post['post_date'] = $edcal_date;
    $my_post['post_date_gmt'] = get_gmt_from_date($edcal_date);
    $my_post['post_modified'] = $edcal_date;
    $my_post['post_modified_gmt'] = get_gmt_from_date($edcal_date);
    
    /* 
     * When we create a new post we need to specify the post type
     * passed in from the JavaScript.
     */
    $post_type = isset($_POST["post_type"])?$_POST["post_type"]:null;
    if ($post_type) {
        $my_post['post_type'] = $post_type;
    }
    
    if($_POST['status']) {
        wp_transition_post_status($_POST['status'], $my_post['post_status'], $my_post);
        $my_post['post_status'] = $_POST['status'];
    }
    
    
    // Insert the post into the database
	if($my_post['ID']) {
		$my_post_id = wp_update_post( $my_post );
    } else {
        $my_post_id = wp_insert_post( $my_post );
    }
		
	// TODO: throw error if update/insert or getsinglepost fails
	/*
     * We finish by returning the latest data for the post in the JSON
     */
    $args = array(
        'p' => $my_post_id
    );
    
    if ($post_type) {
        $args['post_type'] = $post_type;
    }
	$post = query_posts($args);
	
	// get_post and setup_postdata don't get along, so we're doing a mini-loop
	if(have_posts()) :
		while(have_posts()) : the_post();
			?>
			{
			"post" :
				<?php
				edcal_postJSON($post[0], false);
				?>
			}
			<?php
		endwhile;
	endif;
	die();
}

/*
 * This function checks the nonce for the URL.  It returns
 * true if the nonce checks out and outputs a JSON error
 * and returns false otherwise.
 */
function edcal_checknonce() {
    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    global $EDCAL_NONCE_ERROR;
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'edit-calendar')) {
       /*
         * This is just a sanity check to make sure
         * this isn't a CSRF attack.  Most of the time this
         * will never be run because you can't see the calendar unless
         * you are at least an editor
         */
        ?>
        {
            "error": <?php echo($EDCAL_NONCE_ERROR); ?>
        }
        <?php
        return false;
    }
    return true;
}

/*
 * This function changes the date on a post.  It does optimistic 
 * concurrency checking by comparing the original post date from
 * the browser with the one from the database.  If they don't match
 * then it returns an error code and the updated post data.
 *
 * If the call is successful then it returns the updated post data.
 */
function edcal_changedate() {
    if (!edcal_checknonce()) {
        die();
    }
    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    global $edcal_startDate, $edcal_endDate;
    $edcal_postid = isset($_GET['postid'])?$_GET['postid']:null;
    $edcal_newDate = isset($_GET['newdate'])?$_GET['newdate']:null;
    $edcal_oldDate = isset($_GET['olddate'])?$_GET['olddate']:null;
    $edcal_postStatus = isset($_GET['postStatus'])?$_GET['postStatus']:null;
    
    if (!current_user_can('edit_post', $edcal_postid)) {
        global $EDCAL_PERMISSION_ERROR;
        /*
         * This is just a sanity check to make sure that the current
         * user has permission to edit posts.  Most of the time this
         * will never be run because you can't see the calendar unless
         * you are at least an editor
         */
        ?>
        {
            "error": <?php echo($EDCAL_PERMISSION_ERROR); ?>,
        <?php
        
        global $post;
        $args = array(
            'posts_id' => $edcal_postid,
        );
        
        $post = get_post($edcal_postid);
        ?>
            "post" :
        <?php
            edcal_postJSON($post, false, true);
        ?> }
        
        <?php
        die();
    }
    
    $post = get_post($edcal_postid, ARRAY_A);
    setup_postdata($post);
    
    /*
     * We are doing optimistic concurrency checking on the dates.  If
     * the user tries to move a post we want to make sure nobody else
     * has moved that post since the page was last updated.  If the 
     * old date in the database doesn't match the old date from the
     * browser then we return an error to the browser along with the
     * updated post data.
     */
     if (date('Y-m-d', strtotime($post['post_date'])) != date('Y-m-d', strtotime($edcal_oldDate))) {
        global $EDCAL_CONCURRENCY_ERROR;
        ?> {
            "error": <?php echo($EDCAL_CONCURRENCY_ERROR); ?>,
        <?php
        
        global $post;
        $args = array(
            'posts_id' => $edcal_postid,
        );
        
        $post = get_post($edcal_postid);
        ?>
            "post" :
        <?php
            edcal_postJSON($post, false, true);
        ?> }
        
        <?php
        die();
    }
    
    /*
     * Posts in WordPress have more than one date.  There is the GMT date,
     * the date in the local time zone, the modified date in GMT and the
     * modified date in the local time zone.  We update all of them.
     */
    $post['post_date_gmt'] = $post['post_date'];
    
    /*
     * When a user creates a draft and never sets a date or publishes it 
     * then the GMT date will have a timestamp of 00:00:00 to indicate 
     * that the date hasn't been set.  In that case we need to specify
     * an edit date or the wp_update_post function will strip our new
     * date out and leave the post as publish immediately.
     */
    $needsEditDate = strpos($post['post_date_gmt'], "0000-00-00 00:00:00") === 0;
    
    $updated_post = array();
    $updated_post['ID'] = $edcal_postid;
    $updated_post['post_date'] = $edcal_newDate . substr($post['post_date'], strlen($edcal_newDate));
    if ($needsEditDate != -1) {
        $updated_post['edit_date'] = $edcal_newDate . substr($post['post_date'], strlen($edcal_newDate));
    }
    
    /*
     * We need to make sure to use the GMT formatting for the date.
     */
    $updated_post['post_date_gmt'] = get_gmt_from_date($updated_post['post_date']);
    $updated_post['post_modified'] = $edcal_newDate . substr($post['post_modified'], strlen($edcal_newDate));
    $updated_post['post_modified_gmt'] = get_gmt_from_date($updated_post['post_date']);
    
    if ( $edcal_postStatus != $post['post_status'] ) {
        /*
         * We only want to update the post status if it has changed.
         * If the post status has changed that takes a few more steps
         */
        wp_transition_post_status($edcal_postStatus, $post['post_status'], $post);
        $updated_post['post_status'] = $edcal_postStatus;
        
        // Update counts for the post's terms.
        foreach ( (array) get_object_taxonomies('post') as $taxonomy ) {
            $tt_ids = wp_get_object_terms($post_id, $taxonomy, 'fields=tt_ids');
            wp_update_term_count($tt_ids, $taxonomy);
        }
        
        do_action('edit_post', $edcal_postid, $post);
        do_action('save_post', $edcal_postid, $post);
        do_action('wp_insert_post', $edcal_postid, $post);
    }
    
    /*
     * Now we finally update the post into the database
     */
    wp_update_post( $updated_post );
    
    /*
     * We finish by returning the latest data for the post in the JSON
     */
    global $post;
    $args = array(
        'posts_id' => $edcal_postid,
    );
    
    $post = get_post($edcal_postid);
    ?>{
        "post" :
        
    <?php
        edcal_postJSON($post, false, true);
    ?>}
    <?php
    
    die();
}

/*
 * This function saves the preferences
 */
function edcal_saveoptions() {
    if (!edcal_checknonce()) {
        die();
    }

    header("Content-Type: application/json");
    edcal_addNoCacheHeaders();
    
    /*
     * The number of weeks preference
     */
    $edcal_weeks = isset($_GET['weeks'])? intval( $_GET['weeks'] ):null;
    if ($edcal_weeks) {
        //add_option("edcal_weeks_pref", $edcal_weeks, "", "yes");
        update_option("edcal_weeks_pref", $edcal_weeks);
    }
    
    /*
     * The show author preference
     */
    $edcal_author = isset($_GET['author-hide'])?'true':'false';
    if ($edcal_author != null) {
        //add_option("edcal_author_pref", $edcal_author, "", "yes");
        update_option("edcal_author_pref", $edcal_author);
    }
    
    /*
     * The show status preference
     */
    $edcal_status = isset($_GET['status-hide'])?'true':'false';
    if ($edcal_status != null) {
        //add_option("edcal_status_pref", $edcal_status, "", "yes");
        update_option("edcal_status_pref", $edcal_status);
    }
    
    /*
     * The show time preference
     */
    $edcal_time = isset($_GET['time-hide'])?'true':'false';
    if ($edcal_time != null) {
        //add_option("edcal_time_pref", $edcal_time, "", "yes");
        update_option("edcal_time_pref", $edcal_time);
    }

    
    
    /*
     * We finish by returning the latest data for the post in the JSON
     */
    ?>{
        "update" : "success"
    }
    <?php
    
    die();
}

/*
 * Add the no cache headers to make sure that our responses aren't
 * cached by the browser.
 */
function edcal_addNoCacheHeaders() {
    header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
}
