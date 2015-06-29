<?php
//Redirect to the main plugin options page if form has been submitted
if ( isset( $_GET['updated'], $_GET['action'] ) && 'edit' == $_GET['action'] ) {
	wp_redirect( admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php&updated=edited' ) );
	exit;
}

add_settings_section('gce_edit', __('Edit Feed', GCE_TEXT_DOMAIN), 'gce_edit_main_text', 'edit_feed');
//Unique ID                                           //Title                                                                     //Function                         //Page       //Section ID
add_settings_field('gce_edit_id_field',               __('Feed ID', GCE_TEXT_DOMAIN),                                             'gce_edit_id_field',               'edit_feed', 'gce_edit');
add_settings_field('gce_edit_title_field',            __('Feed Title', GCE_TEXT_DOMAIN),                                          'gce_edit_title_field',            'edit_feed', 'gce_edit');
add_settings_field('gce_edit_url_field',              __('Feed URL', GCE_TEXT_DOMAIN),                                            'gce_edit_url_field',              'edit_feed', 'gce_edit');
add_settings_field('gce_edit_retrieve_from_field',    __('Retrieve events from', GCE_TEXT_DOMAIN),                                'gce_edit_retrieve_from_field',    'edit_feed', 'gce_edit');
add_settings_field('gce_edit_retrieve_until_field',   __('Retrieve events until', GCE_TEXT_DOMAIN),                               'gce_edit_retrieve_until_field',   'edit_feed', 'gce_edit');
add_settings_field('gce_edit_max_events_field',       __('Maximum number of events to retrieve', GCE_TEXT_DOMAIN),                'gce_edit_max_events_field',       'edit_feed', 'gce_edit');
add_settings_field('gce_edit_date_format_field',      __('Date format', GCE_TEXT_DOMAIN),                                         'gce_edit_date_format_field',      'edit_feed', 'gce_edit');
add_settings_field('gce_edit_time_format_field',      __('Time format', GCE_TEXT_DOMAIN),                                         'gce_edit_time_format_field',      'edit_feed', 'gce_edit');
add_settings_field('gce_edit_timezone_field',         __('Timezone adjustment', GCE_TEXT_DOMAIN),                                 'gce_edit_timezone_field',         'edit_feed', 'gce_edit');
add_settings_field('gce_edit_cache_duration_field',   __('Cache duration', GCE_TEXT_DOMAIN),                                      'gce_edit_cache_duration_field',   'edit_feed', 'gce_edit');
add_settings_field('gce_edit_multiple_field',         __('Show multiple day events on each day?', GCE_TEXT_DOMAIN),               'gce_edit_multiple_field',         'edit_feed', 'gce_edit');

add_settings_section('gce_edit_display', __('Display Options', GCE_TEXT_DOMAIN), 'gce_edit_display_main_text', 'edit_display');
add_settings_field('gce_edit_use_builder_field', __('Select display customization method', GCE_TEXT_DOMAIN), 'gce_edit_use_builder_field', 'edit_display', 'gce_edit_display');

add_settings_section('gce_edit_builder', __('Event Display Builder'), 'gce_edit_builder_main_text', 'edit_builder');
add_settings_field('gce_edit_builder_field', __('Event display builder HTML and shortcodes', GCE_TEXT_DOMAIN), 'gce_edit_builder_field', 'edit_builder', 'gce_edit_builder');

add_settings_section('gce_edit_simple_display', __('Simple Display Options'), 'gce_edit_simple_display_main_text', 'edit_simple_display');
add_settings_field('gce_edit_display_start_field',     __('Display start time / date?', GCE_TEXT_DOMAIN),  'gce_edit_display_start_field',     'edit_simple_display', 'gce_edit_simple_display');
add_settings_field('gce_edit_display_end_field',       __('Display end time / date?', GCE_TEXT_DOMAIN),    'gce_edit_display_end_field',       'edit_simple_display', 'gce_edit_simple_display');
add_settings_field('gce_edit_display_separator_field', __('Separator text / characters', GCE_TEXT_DOMAIN), 'gce_edit_display_separator_field', 'edit_simple_display', 'gce_edit_simple_display');
add_settings_field('gce_edit_display_location_field',  __('Display location?', GCE_TEXT_DOMAIN),           'gce_edit_display_location_field',  'edit_simple_display', 'gce_edit_simple_display');
add_settings_field('gce_edit_display_desc_field',      __('Display description?', GCE_TEXT_DOMAIN),        'gce_edit_display_desc_field',      'edit_simple_display', 'gce_edit_simple_display');
add_settings_field('gce_edit_display_link_field',      __('Display link to event?', GCE_TEXT_DOMAIN),      'gce_edit_display_link_field',      'edit_simple_display', 'gce_edit_simple_display');

//Main text
function gce_edit_main_text(){
	?>
	<p><?php _e('Make any changes you require to the feed details below, then click the Save Changes button.', GCE_TEXT_DOMAIN); ?></p>
	<?php
}

//ID
function gce_edit_id_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<input type="text" disabled="disabled" value="<?php echo esc_attr( $options['id'] ); ?>" size="3" />
	<input type="hidden" name="gce_options[id]" value="<?php echo esc_attr( $options['id'] ); ?>" />
	<?php
}

//Title
function gce_edit_title_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('Anything you like. \'Upcoming Club Events\', for example.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[title]" value="<?php echo esc_attr( $options['title'] ); ?>" size="50" />
	<?php
}

//URL
function gce_edit_url_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('This will probably be something like: <code>http://www.google.com/calendar/feeds/your-email@gmail.com/public/full</code>.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<span class="description"><?php _e('or: <code>http://www.google.com/calendar/feeds/your-email@gmail.com/private-d65741b037h695ff274247f90746b2ty/basic</code>.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[url]" value="<?php echo esc_attr( $options['url'] ); ?>" size="100" />
	<?php
}

//Retrieve events from
function gce_edit_retrieve_from_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('The point in time at which to start retrieving events. Use the text-box to specify an additional offset from you chosen start point. The offset should be provided in seconds (3600 = 1 hour, 86400 = 1 day) and can be negative. If you have selected the \'Specific date / time\' option, enter a <a href="http://www.timestampgenerator.com" target="_blank">UNIX timestamp</a> in the text-box.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<select name="gce_options[retrieve_from]">
		<option value="now"<?php selected($options['retrieve_from'], 'now'); ?>>Now</option>
		<option value="today"<?php selected($options['retrieve_from'], 'today'); ?>>00:00 today</option>
		<option value="week"<?php selected($options['retrieve_from'], 'week'); ?>>Start of current week</option>
		<option value="month-start"<?php selected($options['retrieve_from'], 'month-start'); ?>>Start of current month</option>
		<option value="month-end"<?php selected($options['retrieve_from'], 'month-end'); ?>>End of current month</option>
		<option value="any"<?php selected($options['retrieve_from'], 'any'); ?>>The beginning of time</option>
		<option value="date"<?php selected($options['retrieve_from'], 'date'); ?>>Specific date / time</option>
	</select>
	<input type="text" name="gce_options[retrieve_from_value]" value="<?php echo esc_attr( $options['retrieve_from_value'] ); ?>" />
	<?php
}

//Retrieve events until
function gce_edit_retrieve_until_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('The point in time at which to stop retrieving events. The instructions for the above option also apply here.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<select name="gce_options[retrieve_until]">
		<option value="now"<?php selected($options['retrieve_until'], 'now'); ?>>Now</option>
		<option value="today"<?php selected($options['retrieve_until'], 'today'); ?>>00:00 today</option>
		<option value="week"<?php selected($options['retrieve_until'], 'week'); ?>>Start of current week</option>
		<option value="month-start"<?php selected($options['retrieve_until'], 'month-start'); ?>>Start of current month</option>
		<option value="month-end"<?php selected($options['retrieve_until'], 'month-end'); ?>>End of current month</option>
		<option value="any"<?php selected($options['retrieve_until'], 'any'); ?>>The end of time</option>
		<option value="date"<?php selected($options['retrieve_until'], 'date'); ?>>Specific date / time</option>

	</select>
	<input type="text" name="gce_options[retrieve_until_value]" value="<?php echo esc_attr( $options['retrieve_until_value'] ); ?>" />
	<?php
}

//Max events
function gce_edit_max_events_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('Set this to a few more than you actually want to display (due to caching and timezone issues). The exact number to display can be configured per shortcode / widget.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[max_events]" value="<?php echo esc_attr( $options['max_events'] ); ?>" size="3" />
	<?php
}

//Date format
function gce_edit_date_format_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('In <a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a>. Leave this blank if you\'d rather stick with the default format for your blog.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[date_format]" value="<?php echo esc_attr( $options['date_format'] ); ?>" />
	<?php
}

//Time format
function gce_edit_time_format_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('In <a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a>. Again, leave this blank to stick with the default.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[time_format]" value="<?php echo esc_attr( $options['time_format'] ); ?>" />
	<?php
}

//Timezone offset
function gce_edit_timezone_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	require_once GCE_PLUGIN_ROOT . 'admin/timezone-choices.php';
	$timezone_list = gce_get_timezone_choices();
	//Set selected="selected" for selected timezone
	$timezone_list = str_replace(('<option value="' . $options['timezone'] . '"'), ('<option value="' . $options['timezone'] . '" selected="selected"'), $timezone_list);
	?>
	<span class="description"><?php _e('If you are having problems with dates and times displaying in the wrong timezone, select a city in your required timezone here.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<?php echo $timezone_list; ?>
	<?php
}

//Cache duration
function gce_edit_cache_duration_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('The length of time, in seconds, to cache the feed (43200 = 12 hours). If this feed changes regularly, you may want to reduce the cache duration. Minimum value is 300 (five minutes).', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[cache_duration]" value="<?php echo esc_attr( $options['cache_duration'] ); ?>" />
	<?php
}

//Multiple day events
function gce_edit_multiple_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('Show events that span multiple days on each day that they span, rather than just the first day.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="checkbox" name="gce_options[multiple_day]" value="true"<?php checked($options['multiple_day'], 'true'); ?> />
	<br /><br />
	<?php
}


//Display options
function gce_edit_display_main_text(){
	?>
	<p><?php _e('These settings control what information will be displayed for this feed in the tooltip (for grids), or in a list.', GCE_TEXT_DOMAIN); ?></p>
	<?php
}

function gce_edit_use_builder_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('It is recommended that you use the event display builder option, as it provides much more flexibility than the simple display options. The event display builder can do everything the simple display options can, plus lots more!', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<select name="gce_options[use_builder]">
		<option value="true"<?php selected($options['use_builder'], 'true'); ?>><?php _e('Event display builder', GCE_TEXT_DOMAIN); ?></option>
		<option value="false"<?php selected($options['use_builder'], 'false'); ?>><?php _e('Simple display options', GCE_TEXT_DOMAIN); ?></option>
	</select>
	<?php
}

//Event display builder
function gce_edit_builder_main_text(){
	?>
	<p class="gce-event-builder"><?php _e('Use the event display builder to customize how event information will be displayed in the grid tooltips and in lists. Use HTML and the shortcodes (explained below) to display the information you require. A basic example display format is provided as a starting point. For more information, take a look at the <a href="http://www.rhanney.co.uk/plugins/google-calendar-events/event-display-builder" target="_blank">event display builder guide</a>.', GCE_TEXT_DOMAIN); ?></p>
	<?php
}

function gce_edit_builder_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<textarea name="gce_options[builder]" rows="10" cols="80"><?php echo esc_textarea( $options['builder'] ); ?></textarea>
	<br />
	<p style="margin-top:20px;"><?php _e('(More information on all of the below shortcodes and attributes, and working examples, can be found in the <a href="http://www.rhanney.co.uk/plugins/google-calendar-events/event-display-builder" target="_blank">event display builder guide</a>)', GCE_TEXT_DOMAIN); ?></p>
	<h4><?php _e('Event information shortcodes:', GCE_TEXT_DOMAIN); ?></h4>
	<ul>
		<li><code>[event-title]</code><span class="description"> - <?php _e( 'The event title (possible attributes: <code>html</code>, <code>markdown</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[start-time]</code><span class="description"> - <?php _e( 'The event start time. Will use the time format specified in the above settings', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[start-date]</code><span class="description"> - <?php _e( 'The event start date. Will use the date format specified in the above settings', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[start-custom]</code><span class="description"> - <?php _e( 'The event start date / time. Will use the format specified in the <code>format</code> attribute (possible attributes: <code>format</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[start-human]</code><span class="description"> - <?php _e( 'The difference between the start time of the event and the time now, in human-readable format, such as \'1 hour\', \'4 days\', \'15 mins\' (possible attributes: <code>precision</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[end-time]</code><span class="description"> - <?php _e( 'The event end time. Will use the time format specified in the above settings', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[end-date]</code><span class="description"> - <?php _e( 'The event end date. Will use the date format specified in the above settings', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[end-custom]</code><span class="description"> - <?php _e( 'The event end date / time. Will use the format specified in the <code>format</code> attribute (possible attributes: <code>format</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[end-human]</code><span class="description"> - <?php _e( 'The difference between the end time of the event and the time now, in human-readable format (possible attributes: <code>precision</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[location]</code><span class="description"> - <?php _e( 'The event location (possible attributes: <code>html</code>, <code>markdown</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[maps-link]&hellip;[/maps-link]</code><span class="description"> - <?php _e( 'Anything between the opening and closing shortcode tags (inlcuding further shortcodes) will be linked to Google Maps, using the event location as a search parameter (possible attributes: <code>newwindow</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[description]</code><span class="description"> - <?php _e( 'The event description (possible attributes: <code>html</code>, <code>markdown</code>, <code>limit</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[link]&hellip;[/link]</code><span class="description"> - <?php _e( 'Anything between the opening and closing shortcode tags (inlcuding further shortcodes) will be linked to the Google Calendar page for the event (possible attributes: <code>newwindow</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[url]</code><span class="description"> - <?php _e( 'The raw URL to the Google Calendar page for the event', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[length]</code><span class="description"> - <?php _e( 'The length of the event, in human-readable format (possible attributes: <code>precision</code>)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[event-num]</code><span class="description"> - <?php _e( 'The position of the event in the current list, or the position of the event in the current month (for grids)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[event-id]</code><span class="description"> - <?php _e( 'The event UID (a unique identifier assigned to the event by Google)', GCE_TEXT_DOMAIN ); ?></span></li>
	</ul>
	<h4><?php _e( 'Feed information shortcodes:', GCE_TEXT_DOMAIN ); ?></h4>
	<ul>
		<li><code>[feed-title]</code><span class="description"> - <?php _e( 'The title of the feed from which the event comes', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[feed-id]</code><span class="description"> - <?php _e( 'The ID of the feed from which the event comes', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[cal-id]</code><span class="description"> - <?php _e( 'The calendar ID (a unique identifier assigned to the calendar by Google)', GCE_TEXT_DOMAIN ); ?></span></li>
	</ul>
	<h4><?php _e( 'Conditional shortcodes:', GCE_TEXT_DOMAIN ); ?></h4>
	<p class="description" style="margin-bottom:18px;"><?php _e( 'Anything entered between the opening and closing tags of each of the following shortcodes will only be displayed if its condition (below) is met.', GCE_TEXT_DOMAIN ); ?></p>
	<ul>
		<li><code>[if-all-day]&hellip;[/if-all-day]</code><span class="description"> - <?php _e( 'The event is an all-day event', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-not-all-day]&hellip;[/if-not-all-day]</code><span class="description"> - <?php _e( 'The event is not an all-day event', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-title]&hellip;[/if-title]</code><span class="description"> - <?php _e( 'The event has a title', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-description]&hellip;[/if-description]</code><span class="description"> - <?php _e( 'The event has a description', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-location]&hellip;[/if-location]</code><span class="description"> - <?php _e( 'The event has a location', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-tooltip]&hellip;[/if-tooltip]</code><span class="description"> - <?php _e( 'The event is to be displayed in a tooltip (not a list)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-list]&hellip;[/if-list]</code><span class="description"> - <?php _e( 'The event is to be displayed in a list (not a tooltip)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-now]&hellip;[/if-now]</code><span class="description"> - <?php _e( 'The event is taking place now (after the start time, but before the end time)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-not-now]&hellip;[/if-not-now]</code><span class="description"> - <?php _e( 'The event is not taking place now (may have ended or not yet started)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-started]&hellip;[/if-started]</code><span class="description"> - <?php _e( 'The event has started (even if it has also ended)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-not-started]&hellip;[/if-not-started]</code><span class="description"> - <?php _e( 'The event has not started', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-ended]&hellip;[/if-ended]</code><span class="description"> - <?php _e( 'The event has ended', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-not-ended]&hellip;[/if-not-ended]</code><span class="description"> - <?php _e( 'The event has not ended (even if it hasn\'t started)', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-first]&hellip;[/if-first]</code><span class="description"> - <?php _e( 'The event is the first of the day', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-not-first]&hellip;[/if-not-first]</code><span class="description"> - <?php _e( 'The event is not the first of the day', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-multi-day]&hellip;[/if-multi-day]</code><span class="description"> - <?php _e( 'The event spans multiple days', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>[if-single-day]&hellip;[/if-single-day]</code><span class="description"> - <?php _e( 'The event does not span multiple days', GCE_TEXT_DOMAIN ); ?></span></li>
	</ul>
	<h4><?php _e( 'Attributes:', GCE_TEXT_DOMAIN ); ?></h4>
	<p class="description" style="margin-bottom:18px;"><?php _e( 'The possible attributes mentioned above are explained here:', GCE_TEXT_DOMAIN ); ?></p>
	<ul>
		<li><code>html</code><span class="description"> - <?php _e( 'Whether or not to parse HTML that has been entered in the relevant field. Can be <code>true</code> or <code>false</code>', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>markdown</code><span class="description"> - <?php _e( 'Whether or not to parse <a href="http://daringfireball.net/projects/markdown" target="_blank">Markdown</a> that has been entered in the relevant field. <a href="http://michelf.com/projects/php-markdown" target="_blank">PHP Markdown</a> must be installed for this to work. Can be <code>true</code> or <code>false</code>', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>limit</code><span class="description"> - <?php _e( 'The word limit for the field. Should be specified as a positive integer', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>format</code><span class="description"> - <?php _e( 'The date / time format to use. Should specified as a <a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a> string', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>newwindow</code><span class="description"> - <?php _e( 'Whether or not the link should open in a new window / tab. Can be <code>true</code> or <code>false</code>', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>precision</code><span class="description"> - <?php _e( 'How precise to be when displaying a time difference in human-readable format. Should be specified as a positive integer', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>offset</code><span class="description"> - <?php _e( 'An offset (in seconds) to apply to start / end times before display. Should be specified as a (positive or negative) integer', GCE_TEXT_DOMAIN ); ?></span></li>
		<li><code>autolink</code><span class="description"> - <?php _e( 'Whether or not to automatically convert URLs in the description to links. Can be <code>true</code> or <code>false</code>', GCE_TEXT_DOMAIN ); ?></span></li>
	</ul>
	<?php
}

//Simple display options
function gce_edit_simple_display_main_text(){
	?>
	<p class="gce-simple-display-options"><?php _e('You can use some HTML in the text fields, but ensure it is valid or things might go wonky. Text fields can be empty too.', GCE_TEXT_DOMAIN); ?></p>
	<?php
}

function gce_edit_display_start_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('Select how to display the start date / time.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<select name="gce_options[display_start]">
		<option value="none"<?php selected($options['display_start'], 'none'); ?>><?php _e('Don\'t display start time or date', GCE_TEXT_DOMAIN); ?></option>
		<option value="time"<?php selected($options['display_start'], 'time'); ?>><?php _e('Display start time', GCE_TEXT_DOMAIN); ?></option>
		<option value="date"<?php selected($options['display_start'], 'date'); ?>><?php _e('Display start date', GCE_TEXT_DOMAIN); ?></option>
		<option value="time-date"<?php selected($options['display_start'], 'time-date'); ?>><?php _e('Display start time and date (in that order)', GCE_TEXT_DOMAIN); ?></option>
		<option value="date-time"<?php selected($options['display_start'], 'date-time'); ?>><?php _e('Display start date and time (in that order)', GCE_TEXT_DOMAIN); ?></option>
	</select>
	<br /><br />
	<span class="description"><?php _e('Text to display before the start time.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_start_text]" value="<?php echo esc_attr( $options['display_start_text'] ); ?>" />
	<?php
}

function gce_edit_display_end_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('Select how to display the end date / time.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<select name="gce_options[display_end]">
		<option value="none"<?php selected($options['display_end'], 'none'); ?>><?php _e('Don\'t display end time or date', GCE_TEXT_DOMAIN); ?></option>
		<option value="time"<?php selected($options['display_end'], 'time'); ?>><?php _e('Display end time', GCE_TEXT_DOMAIN); ?></option>
		<option value="date"<?php selected($options['display_end'], 'date'); ?>><?php _e('Display end date', GCE_TEXT_DOMAIN); ?></option>
		<option value="time-date"<?php selected($options['display_end'], 'time-date'); ?>><?php _e('Display end time and date (in that order)', GCE_TEXT_DOMAIN); ?></option>
		<option value="date-time"<?php selected($options['display_end'], 'date-time'); ?>><?php _e('Display end date and time (in that order)', GCE_TEXT_DOMAIN); ?></option>
	</select>
	<br /><br />
	<span class="description"><?php _e('Text to display before the end time.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_end_text]" value="<?php echo esc_attr( $options['display_end_text'] ); ?>" />
	<?php
}

function gce_edit_display_separator_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<span class="description"><?php _e('If you have chosen to display both the time and date above, enter the text / characters to display between the time and date here (including any spaces).', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_separator]" value="<?php echo esc_attr( $options['display_separator'] ); ?>" />
	<?php
}

function gce_edit_display_location_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<input type="checkbox" name="gce_options[display_location]"<?php checked($options['display_location'], 'on'); ?> value="on" />
	<span class="description"><?php _e('Show the location of events?', GCE_TEXT_DOMAIN); ?></span>
	<br /><br />
	<span class="description"><?php _e('Text to display before the location.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_location_text]" value="<?php echo esc_attr( $options['display_location_text'] ); ?>" />
	<?php
}

function gce_edit_display_desc_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<input type="checkbox" name="gce_options[display_desc]"<?php checked($options['display_desc'], 'on'); ?> value="on" />
	<span class="description"><?php _e('Show the description of events?  (URLs in the description will be made into links).', GCE_TEXT_DOMAIN); ?></span>
	<br /><br />
	<span class="description"><?php _e('Text to display before the description.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_desc_text]" value="<?php echo esc_attr( $options['display_desc_text'] ); ?>" />
	<br /><br />
	<span class="description"><?php _e('Maximum number of words to show from description. Leave blank for no limit.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_desc_limit]" value="<?php echo esc_attr( $options['display_desc_limit'] ); ?>" size="3" />
	<?php
}

function gce_edit_display_link_field(){
	$options = get_option(GCE_OPTIONS_NAME);
	$options = $options[$_GET['id']];
	?>
	<input type="checkbox" name="gce_options[display_link]"<?php checked($options['display_link'], 'on'); ?> value="on" />
	<span class="description"><?php _e('Show a link to the Google Calendar page for an event?', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="checkbox" name="gce_options[display_link_target]"<?php checked($options['display_link_target'], 'on'); ?> value="on" />
	<span class="description"><?php _e('Links open in a new window / tab?', GCE_TEXT_DOMAIN); ?></span>
	<br /><br />
	<span class="description"><?php _e('The link text to be displayed.', GCE_TEXT_DOMAIN); ?></span>
	<br />
	<input type="text" name="gce_options[display_link_text]" value="<?php echo esc_attr( $options['display_link_text'] ); ?>" />
	<?php
}
?>