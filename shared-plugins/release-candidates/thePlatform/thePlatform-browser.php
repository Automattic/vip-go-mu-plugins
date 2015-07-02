<?php
/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2015 thePlatform, LLC

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$account = get_option( TP_ACCOUNT_OPTIONS_KEY );
if ( $account == false || empty( $account['mpx_account_id'] ) ) {
	wp_die( '<div class="error"><p>mpx Account ID is not set, please configure the plugin before attempting to manage media</p></div>' );
}

define( 'TP_MEDIA_BROWSER', true );

$tp_editor_cap = apply_filters( TP_EDITOR_CAP, TP_EDITOR_DEFAULT_CAP );

if ( ! current_user_can( $tp_editor_cap ) ) {
	wp_die( '<div class="error"><p>You do not have sufficient permissions to browse mpx Media</p></div>' );
}

require_once( dirname( __FILE__ ) . '/thePlatform-HTML.php' );
$tp_html = new ThePlatform_HTML();

$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
$account     = get_option( TP_ACCOUNT_OPTIONS_KEY );

global $page_hook;
$IS_EMBED = $page_hook != 'toplevel_page_theplatform';

?>

<div class="wrap">
	<?php if ( ! $IS_EMBED ) {
		echo '<h2>mpx Video Manager</h2>';
	} ?>

	<!-- Write out the search bar -->
	<?php $tp_html->media_search_bar(); ?>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2 tp-post-body">
			<!-- main content -->
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<div class="postbox">
						<div class="inside">
							<?php $tp_html->pagination( 'top' ) ?>
							<div id="media-list"></div>
							<?php $tp_html->pagination( 'bottom' ) ?>
						</div>
						<!-- .inside -->
					</div>
					<!-- .postbox -->
				</div>
				<!-- .meta-box-sortables .ui-sortable -->
			</div>
			<!-- post-body-content -->

			<!-- sidebar -->
			<div id="postbox-container-1" class="postbox-container tp-postbox-container-1">
				<div class="meta-box-sortables">
					<div class="postbox">
						<div class="inside">
							<div id="info-player-container" class="scrollable">
								<?php $tp_html->preview_player() ?>
								<?php $tp_html->content_pane() ?>
							</div>
						</div>
						<!-- .inside -->
					</div>
					<!-- .postbox -->
					<?php if ( ! $IS_EMBED ) {
						$tp_html->content_pane_buttons();
					} ?>
				</div>
				<!-- .meta-box-sortables -->
			</div>
			<!-- #postbox-container-1 .postbox-container -->
		</div>
		<!-- #post-body .metabox-holder .columns-2 -->
		<br class="clear">
		<!-- #poststuff -->
		<?php if ( $IS_EMBED ) {
			$tp_html->add_media_toolbar();
		} ?>
	</div>
</div><!-- .wrap -->

<?php
if ( ! $IS_EMBED && current_user_can( $tp_editor_cap )) {
?>
<div id="tp-edit-dialog">
	<?php require_once( dirname( __FILE__ ) . '/thePlatform-edit-upload.php' ); ?>
	<?php } ?>

	<script type="text/javascript">
		tpHelper = {};
		tpHelper.account = <?php echo json_encode( $account['mpx_account_id'] ); ?>;
		tpHelper.accountPid = <?php echo json_encode( $account['mpx_account_pid'] ); ?>;
		tpHelper.isEmbed = <?php echo json_encode( $IS_EMBED ); ?>;
		tpHelper.mediaEmbedType = <?php echo json_encode( $preferences['media_embed_type'] ); ?>;
		tpHelper.selectedCategory = '';
		tpHelper.queryString = '';
		tpHelper.currentPage = 1;
	</script>


	<script id="media-template" type="text/template">
		<div class="tp-media" id="<%= id %>">
			<div class="tp-media-left">
				<img class="tp-media-object tp-thumb-img" data-src="<%= placeHolder %>" alt="128x72"
				     src="<%= defaultThumbnailUrl %>">
			</div>
			<div class="tp-media-body">
				<strong class="tp-media-heading"><%= title %></strong>

				<div id="desc"><%= _.template.formatDescription( description ) %></div>
			</div>
		</div>
	</script>

	<script id="shortcode-template" type="text/template">
		[theplatform account="<%= account %>" media="<%= release %>" player="<%= player %>"]
	</script>

	<script id="error-template" type="text/template">
		<div class="error below-h2"><p><%= message %></p></div>
	</script>