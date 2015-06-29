<?php
//Redirect to the main plugin options page if form has been submitted
if ( isset( $_GET['updated'], $_GET['action'] ) && 'add' == $_GET['action'] ) {
	wp_redirect( admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php&updated=added' ) );
	exit;
}

add_settings_section( 'gce_add', __( 'Add a Feed', GCE_TEXT_DOMAIN ), 'gce_add_main_text', 'add_feed' );
//Unique ID                                          //Title                                                                     //Function                        //Page      //Section ID
add_settings_field( 'gce_add_id_field',               __( 'Feed ID', GCE_TEXT_DOMAIN ),                                             'gce_add_id_field',               'add_feed', 'gce_add' );
add_settings_field( 'gce_add_title_field',            __( 'Feed Title', GCE_TEXT_DOMAIN ),                                          'gce_add_title_field',            'add_feed', 'gce_add' );
add_settings_field( 'gce_add_url_field',              __( 'Feed URL', GCE_TEXT_DOMAIN ),                                            'gce_add_url_field',              'add_feed', 'gce_add' );
add_settings_field( 'gce_add_retrieve_from_field',    __( 'Retrieve events from', GCE_TEXT_DOMAIN ),                                'gce_add_retrieve_from_field',    'add_feed', 'gce_add' );
add_settings_field( 'gce_add_retrieve_until_field',   __( 'Retrieve events until', GCE_TEXT_DOMAIN ),                               'gce_add_retrieve_until_field',   'add_feed', 'gce_add' );
add_settings_field( 'gce_add_max_events_field',       __( 'Maximum number of events to retrieve', GCE_TEXT_DOMAIN ),                'gce_add_max_events_field',       'add_feed', 'gce_add' );
add_settings_field( 'gce_add_date_format_field',      __( 'Date format', GCE_TEXT_DOMAIN ),                                         'gce_add_date_format_field',      'add_feed', 'gce_add' );
add_settings_field( 'gce_add_time_format_field',      __( 'Time format', GCE_TEXT_DOMAIN ),                                         'gce_add_time_format_field',      'add_feed', 'gce_add' );
add_settings_field( 'gce_add_timezone_field',         __( 'Timezone adjustment', GCE_TEXT_DOMAIN ),                                 'gce_add_timezone_field',         'add_feed', 'gce_add' );
add_settings_field( 'gce_add_cache_duration_field',   __( 'Cache duration', GCE_TEXT_DOMAIN ),                                      'gce_add_cache_duration_field',   'add_feed', 'gce_add' );
add_settings_field( 'gce_add_multiple_field',         __( 'Show multiple day events on each day?', GCE_TEXT_DOMAIN ),               'gce_add_multiple_field',         'add_feed', 'gce_add' );

add_settings_section( 'gce_add_display', __( 'Display Options', GCE_TEXT_DOMAIN ), 'gce_add_display_main_text', 'add_display' );
add_settings_field( 'gce_add_use_builder_field', __( 'Select display customization method', GCE_TEXT_DOMAIN ), 'gce_add_use_builder_field', 'add_display', 'gce_add_display' );

add_settings_section( 'gce_add_builder', __( 'Event Display Builder' ), 'gce_add_builder_main_text', 'add_builder' );
add_settings_field( 'gce_add_builder_field', __( 'Event display builder HTML and shortcodes', GCE_TEXT_DOMAIN ), 'gce_add_builder_field', 'add_builder', 'gce_add_builder' );

add_settings_section( 'gce_add_simple_display', __('Simple Display Options'), 'gce_add_simple_display_main_text', 'add_simple_display' );
add_settings_field( 'gce_add_display_start_field',     __( 'Display start time / date?', GCE_TEXT_DOMAIN ),  'gce_add_display_start_field',     'add_simple_display', 'gce_add_simple_display' );
add_settings_field( 'gce_add_display_end_field',       __( 'Display end time / date?', GCE_TEXT_DOMAIN ),    'gce_add_display_end_field',       'add_simple_display', 'gce_add_simple_display' );
add_settings_field( 'gce_add_display_separator_field', __( 'Separator text / characters', GCE_TEXT_DOMAIN ), 'gce_add_display_separator_field', 'add_simple_display', 'gce_add_simple_display' );
add_settings_field( 'gce_add_display_location_field',  __( 'Display location?', GCE_TEXT_DOMAIN ),           'gce_add_display_location_field',  'add_simple_display', 'gce_add_simple_display' );
add_settings_field( 'gce_add_display_desc_field',      __( 'Display description?', GCE_TEXT_DOMAIN ),        'gce_add_display_desc_field',      'add_simple_display', 'gce_add_simple_display' );
add_settings_field( 'gce_add_display_link_field',      __( 'Display link to event?', GCE_TEXT_DOMAIN ),      'gce_add_display_link_field',      'add_simple_display', 'gce_add_simple_display' );

//Main text
function gce_add_main_text() {
	?>
	<p><?php _e( 'Enter the feed details below, then click the Add Feed button.', GCE_TEXT_DOMAIN ); ?></p>
	<?php
}

//ID
function gce_add_id_field() {
	$options = get_option( GCE_OPTIONS_NAME );
	$id = 1;
	if ( !empty( $options ) ) { //If there are no saved feeds
		//Go to last saved feed
		end( $options );
		//Set id to last feed id + 1
		$id = key( $options ) + 1;
	}

	?>
	<input type="text" disabled="disabled" value="<?php echo esc_attr( $id ); ?>" size="3" />
	<input type="hidden" name="gce_options[id]" value="<?php echo esc_attr( $id ); ?>" />
	<?php
}

//Title
function gce_add_title_field() {
	?>
	<span class="description"><?php _e( 'Anything you like. \'Upcoming Club Events\', for example.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[title]" size="50" />
	<?php
}

//URL
function gce_add_url_field() {
	?>
	<span class="description"><?php _e( 'This will probably be something like:', GCE_TEXT_DOMAIN ); ?> <code>http://www.google.com/calendar/feeds/your-email@gmail.com/public/basic</code>.</span>
	<br />
	<span class="description"><?php _e( 'or:', GCE_TEXT_DOMAIN ); ?> <code>http://www.google.com/calendar/feeds/your-email@gmail.com/private-d65741b037h695ff274247f90746b2ty/basic</code>.</span>
	<br />
	<input type="text" name="gce_options[url]" size="100" class="required" />
	<?php
}

//Retrieve events from
function gce_add_retrieve_from_field() {
	?>
	<span class="description">
		<?php _e( 'The point in time at which to start retrieving events. Use the text-box to specify an additional offset from you chosen start point. The offset should be provided in seconds (3600 = 1 hour, 86400 = 1 day) and can be negative. If you have selected the \'Specific date / time\' option, enter a', GCE_TEXT_DOMAIN ); ?>
		<a href="http://www.timestampgenerator.com" target="_blank"><?php _e( 'UNIX timestamp', GCE_TEXT_DOMAIN ); ?></a>
		<?php _e( 'in the text-box.', GCE_TEXT_DOMAIN ); ?>
	</span>
	<br />
	<select name="gce_options[retrieve_from]">
		<option value="now"><?php _e( 'Now', GCE_TEXT_DOMAIN ); ?></option>
		<option value="today" selected="selected"><?php _e( '00:00 today', GCE_TEXT_DOMAIN ); ?></option>
		<option value="week"><?php _e( 'Start of current week', GCE_TEXT_DOMAIN ); ?></option>
		<option value="month-start"><?php _e( 'Start of current month', GCE_TEXT_DOMAIN ); ?></option>
		<option value="month-end"><?php _e( 'End of current month', GCE_TEXT_DOMAIN ); ?></option>
		<option value="any"><?php _e( 'The beginning of time', GCE_TEXT_DOMAIN ); ?></option>
		<option value="date"><?php _e( 'Specific date / time', GCE_TEXT_DOMAIN ); ?></option>
	</select>
	<input type="text" name="gce_options[retrieve_from_value]" value="0" />
	<?php
}

//Retrieve events until
function gce_add_retrieve_until_field() {
	?>
	<span class="description"><?php _e( 'The point in time at which to stop retrieving events. The instructions for the above option also apply here.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<select name="gce_options[retrieve_until]">
		<option value="now"><?php _e( 'Now', GCE_TEXT_DOMAIN ); ?></option>
		<option value="today"><?php _e( '00:00 today', GCE_TEXT_DOMAIN ); ?></option>
		<option value="week"><?php _e( 'Start of current week', GCE_TEXT_DOMAIN ); ?></option>
		<option value="month-start"><?php _e( 'Start of current month', GCE_TEXT_DOMAIN ); ?></option>
		<option value="month-end"><?php _e( 'End of current month', GCE_TEXT_DOMAIN ); ?></option>
		<option value="any" selected="selected"><?php _e( 'The end of time', GCE_TEXT_DOMAIN ); ?></option>
		<option value="date"><?php _e( 'Specific date / time', GCE_TEXT_DOMAIN ); ?></option>

	</select>
	<input type="text" name="gce_options[retrieve_until_value]" value="0" />
	<?php
}

//Max events
function gce_add_max_events_field() {
	?>
	<span class="description"><?php _e( 'Set this to a few more than you actually want to display (due to caching and timezone issues). The exact number to display can be configured per shortcode / widget.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[max_events]" value="25" size="3" />
	<?php
}

//Date format
function gce_add_date_format_field(){
	?>
	<span class="description"><?php _e( 'In <a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a>. Leave this blank if you\'d rather stick with the default format for your blog.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[date_format]" />
	<?php
}

//Time format
function gce_add_time_format_field(){
	?>
	<span class="description"><?php _e( 'In <a href="http://php.net/manual/en/function.date.php" target="_blank">PHP date format</a>. Again, leave this blank to stick with the default.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[time_format]" />
	<?php
}

//Timezone offset
function gce_add_timezone_field() {
	require_once GCE_PLUGIN_ROOT . 'admin/timezone-choices.php';
	$timezone_list = gce_get_timezone_choices();
	//Set selected="selected" for default option
	$timezone_list = str_replace( '<option value="default">Default</option>', '<option value="default" selected="selected">Default</option>', $timezone_list );
	?>
	<span class="description"><?php _e( 'If you are having problems with dates and times displaying in the wrong timezone, select a city in your required timezone here.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<?php echo $timezone_list; ?>
	<?php
}

//Cache duration
function gce_add_cache_duration_field() {
	?>
	<span class="description"><?php _e( 'The length of time, in seconds, to cache the feed (43200 = 12 hours). If this feed changes regularly, you may want to reduce the cache duration. Minimum value is 300 (five minutes).', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[cache_duration]" value="43200" />
	<?php
}

//Multiple day events
function gce_add_multiple_field() {
	?>
	<span class="description"><?php _e( 'Show events that span multiple days on each day that they span, rather than just the first day.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="checkbox" name="gce_options[multiple_day]" value="true" />
	<br /><br />
	<?php
}


//Display options
function gce_add_display_main_text() {
	?>
	<p><?php _e( 'These settings control what information will be displayed for this feed in the tooltip (for grids), or in a list.', GCE_TEXT_DOMAIN ); ?></p>
	<?php
}

function gce_add_use_builder_field() {
	?>
	<span class="description"><?php _e( 'It is recommended that you use the event display builder option, as it provides much more flexibility than the simple display options. The event display builder can do everything the simple display options can, plus lots more!', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<select name="gce_options[use_builder]">
		<option value="true" selected="selected"><?php _e( 'Event display builder', GCE_TEXT_DOMAIN ); ?></option>
		<option value="false"><?php _e( 'Simple display options', GCE_TEXT_DOMAIN ); ?></option>
	</select>
	<?php
}

//Event display builder
function gce_add_builder_main_text() {
	?>
	<p class="gce-event-builder">
		<?php _e( 'Use the event display builder to customize how event information will be displayed in the grid tooltips and in lists. Use HTML and the shortcodes (explained below) to display the information you require. A basic example display format is provided as a starting point. For more information, take a look at the', GCE_TEXT_DOMAIN ); ?>
		<a href="http://www.rhanney.co.uk/plugins/google-calendar-events/event-display-builder" target="_blank"><?php _e( 'event display builder guide', GCE_TEXT_DOMAIN ); ?></a>
	</p>
	<?php
}

function gce_add_builder_field() {
	?>
	<textarea name="gce_options[builder]" rows="10" cols="80">
&lt;div class="gce-list-event gce-tooltip-event"&gt;[event-title]&lt;/div&gt;
&lt;div&gt;&lt;span&gt;Starts:&lt;/span&gt; [start-time]&lt;/div&gt;
&lt;div&gt;&lt;span&gt;Ends:&lt;/span&gt; [end-date] - [end-time]&lt;/div&gt;
[if-location]&lt;div&gt;&lt;span&gt;Location:&lt;/span&gt; [location]&lt;/div&gt;[/if-location]
[if-description]&lt;div&gt;&lt;span&gt;Description:&lt;/span&gt; [description]&lt;/div&gt;[/if-description]
&lt;div&gt;[link newwindow="true"]More details...[/link]&lt;/div&gt;
</textarea>
	<br />
	<p style="margin-top:20px;">
		<?php _e( '(More information on all of the below shortcodes and attributes, and working examples, can be found in the', GCE_TEXT_DOMAIN ); ?>
		<a href="http://www.rhanney.co.uk/plugins/google-calendar-events/event-display-builder" target="_blank"><?php _e( 'event display builder guide', GCE_TEXT_DOMAIN ); ?></a>)
	</p>
	<h4><?php _e( 'Event information shortcodes:', GCE_TEXT_DOMAIN ); ?></h4>
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
function gce_add_simple_display_main_text() {
	?>
	<p class="gce-simple-display-options"><?php _e( 'You can use some HTML in the text fields, but ensure it is valid or things might go wonky. Text fields can be empty too.', GCE_TEXT_DOMAIN ); ?></p>
	<?php
}

function gce_add_display_start_field() {
	?>
	<span class="description"><?php _e( 'Select how to display the start date / time.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<select name="gce_options[display_start]">
		<option value="none"><?php _e( 'Don\'t display start time or date', GCE_TEXT_DOMAIN ); ?></option>
		<option value="time" selected="selected"><?php _e( 'Display start time', GCE_TEXT_DOMAIN ); ?></option>
		<option value="date"><?php _e( 'Display start date', GCE_TEXT_DOMAIN ); ?></option>
		<option value="time-date"><?php _e( 'Display start time and date (in that order)', GCE_TEXT_DOMAIN ); ?></option>
		<option value="date-time"><?php _e( 'Display start date and time (in that order)', GCE_TEXT_DOMAIN ); ?></option>
	</select>
	<br /><br />
	<span class="description"><?php _e( 'Text to display before the start time.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_start_text]" value="Starts:" />
	<?php
}

function gce_add_display_end_field() {
	?>
	<span class="description"><?php _e( 'Select how to display the end date / time.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<select name="gce_options[display_end]">
		<option value="none"><?php _e( 'Don\'t display end time or date', GCE_TEXT_DOMAIN ); ?></option>
		<option value="time"><?php _e( 'Display end time', GCE_TEXT_DOMAIN ); ?></option>
		<option value="date"><?php _e( 'Display end date', GCE_TEXT_DOMAIN ); ?></option>
		<option value="time-date" selected="selected"><?php _e( 'Display end time and date (in that order)', GCE_TEXT_DOMAIN ); ?></option>
		<option value="date-time"><?php _e( 'Display end date and time (in that order)', GCE_TEXT_DOMAIN ); ?></option>
	</select>
	<br /><br />
	<span class="description"><?php _e( 'Text to display before the end time.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_end_text]" value="Ends:" />
	<?php
}

function gce_add_display_separator_field() {
	?>
	<span class="description"><?php _e( 'If you have chosen to display both the time and date above, enter the text / characters to display between the time and date here (including any spaces).', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_separator]" value=", " />
	<?php
}

function gce_add_display_location_field() {
	?>
	<input type="checkbox" name="gce_options[display_location]" value="on" />
	<span class="description"><?php _e( 'Show the location of events?', GCE_TEXT_DOMAIN ); ?></span>
	<br /><br />
	<span class="description"><?php _e( 'Text to display before the location.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_location_text]" value="Location:" />
	<?php
}

function gce_add_display_desc_field() {
	?>
	<input type="checkbox" name="gce_options[display_desc]" value="on" />
	<span class="description"><?php _e( 'Show the description of events? (URLs in the description will be made into links).', GCE_TEXT_DOMAIN ); ?></span>
	<br /><br />
	<span class="description"><?php _e( 'Text to display before the description.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_desc_text]" value="Description:" />
	<br /><br />
	<span class="description"><?php _e( 'Maximum number of words to show from description. Leave blank for no limit.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_desc_limit]" size="3" />
	<?php
}

function gce_add_display_link_field() {
	?>
	<input type="checkbox" name="gce_options[display_link]" value="on" checked="checked" />
	<span class="description"><?php _e( 'Show a link to the Google Calendar page for an event?', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="checkbox" name="gce_options[display_link_target]" value="on" />
	<span class="description"><?php _e( 'Links open in a new window / tab?', GCE_TEXT_DOMAIN ); ?></span>
	<br /><br />
	<span class="description"><?php _e( 'The link text to be displayed.', GCE_TEXT_DOMAIN ); ?></span>
	<br />
	<input type="text" name="gce_options[display_link_text]" value="More details" />
	<?php
}
?>