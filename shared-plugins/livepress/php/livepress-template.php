<?php
/**
 * LivePress template
 */

/**
 * LivePress template.
 *
 * @param bool $auto               Optional. Auto. Default false.
 * @param int  $seconds_since_last Optional. Seconds since last update. Default 0.
 * @return mixed|string
 */
function livepress_template( $auto = false, $seconds_since_last = 0 ) {
	global $post;

	$is_live    = LivePress_Updater::instance()->blogging_tools->get_post_live_status( $post->ID );
	$pin_header = LivePress_Updater::instance()->blogging_tools->is_post_header_enabled( $post->ID );

	$lp_status  = $is_live    ? 'lp-on' : 'lp-off';
	$pin_class  = $pin_header ? 'livepress-pinned-header' : '';
	// Don't show the LivePress bar on front end if the post isn't live or LivePress disabled
	if ( ! $is_live || ! LivePress_Updater::instance()->has_livepress_enabled() )
		return;

	$live            = esc_html__( 'LIVE', 'livepress' );
	$about           = wp_kses_post( __( 'Receive live updates to<br />this and other posts on<br />this site.', 'livepress' ) );
	$notifications   = esc_html__( 'Notifications', 'livepress' );
	$updates         = esc_html__( 'Live Updates', 'livepress' );
	$powered_by      = wp_kses_post ( __( 'powered by <a href="http://livepress.com">LivePress</a>', 'livepress' ) );
	$date            = new DateTime();
	$interval_string = 'P0Y0M0DT0H' . floor( $seconds_since_last / 60 ) .'M' . $seconds_since_last % 60 . 'S';

	// Generate an ISO-8601 formatted timestamp for timeago.js
	$date->sub( new DateInterval( $interval_string ) );
	$date8601 = $date->format('c');

	static $called = 0;
	if ( $called++ ) return;
	$htmlTemplate = <<<HTML
		<div id="livepress">
			<div class="lp-bar">
				<div class="lp-status $lp_status $pin_class "><span class="status-title">$live</span></div>
				<div class="lp-updated">
					<span class="lp-updated-counter" data-min="$seconds_since_last">
						<abbr class="livepress-timestamp" title="$date8601"></abbr>
					</span>
					</div>
				<div class="lp-settings-button"></div>
				<div id="lp-settings">
					<div class="lp-settings-short">
						<div class="lp-about">
							$about
						</div>
					</div>
					<ul>
						<li>
							<p>
								<input type="checkbox" id="lp-setting-sound" name="lp-setting-sound" checked="checked" />
								<label for="lp-setting-sound">$notifications</label>
							</p>
						</li>
						<li>
							<p>
								<input type="checkbox" id="lp-setting-updates" name="lp-setting-updates" checked="checked" />
								<label for="lp-setting-updates">$updates</label>
							</p>
						</li>
					</ul>
					<p class="powered-by">$powered_by</p>
				</div>
			</div>
		</div>
HTML;


	$image_url    = LP_PLUGIN_URL . "img/spin.gif";
	$htmlTemplate = str_replace("SPIN_IMAGE", $image_url, $htmlTemplate);

	$image_url    = LP_PLUGIN_URL . "img/lp-bar-logo.png";
	$htmlTemplate = str_replace("LOGO_IMAGE", $image_url, $htmlTemplate);

	$image_url    = LP_PLUGIN_URL . "img/lp-settings-close.gif";
	$htmlTemplate = str_replace("CLOSE_SETTINGS_IMAGE", $image_url, $htmlTemplate);

	$image_url    = LP_PLUGIN_URL . "img/lp-bar-cogwheel.png";
	$htmlTemplate = str_replace("BAR_COG_IMAGE", $image_url, $htmlTemplate);

	$lp_update    = LivePress_Updater::instance();
	$htmlTemplate = str_replace("<!--UPDATES_NUM-->",
			$lp_update->current_post_updates_count(), $htmlTemplate);

	if($auto)
		$htmlTemplate = str_replace('id="livepress"', 'id="livepress" class="auto"', $htmlTemplate);

	if (LivePress_Updater::instance()->is_comments_enabled()) {
		$htmlTemplate = str_replace(array( "<!--COMMENTS-->", "<!--/COMMENTS-->" ), "", $htmlTemplate );
		$htmlTemplate = str_replace("<!--COMMENTS_NUM-->",
				$lp_update->current_post_comments_count(), $htmlTemplate);
	} else {
		$htmlTemplate = preg_replace("#<!--COMMENTS-->.*?<!--/COMMENTS-->#s", "", $htmlTemplate);
	}

	if ($auto) {
		return $htmlTemplate;
	} else {
		echo wp_kses_post( $htmlTemplate );
	}
}
add_action( 'livepress_widget', 'livepress_template' );

/**
 * LivePress update box output.
 */
function livepress_update_box() {
	static $called = 0;
	if($called++) return;
	if (LivePress_Updater::instance()->has_livepress_enabled()) {
		echo '<div id="lp-update-box"></div>';
	}
}
add_action('livepress_update_box', 'livepress_update_box');

/**
 * LivePress dashboard template output.
 */
function livepress_dashboard_template() {
	echo '<div id="lp-switch-panel" class="editor-hidden">';
	echo '<a id="live-switcher" class="off preview button-secondary disconnected" style="display: none" title="' .
			esc_html__('Show or Hide the Real-Time Editor', 'livepress' ) .'">' .
			esc_html__( 'Show', 'livepress' ) .
		'</a>';
	echo '<h3>' .
			esc_html__( 'Real-Time Editor', 'livepress' ) .
		'</h3>';
	echo '<span class="warning">' .
			esc_html__( 'Click "Show" to activate the Real-Time Editor and streamline your liveblogging workflow.', 'livepress' ) .
		'</span>';
	echo '</div>';
}
