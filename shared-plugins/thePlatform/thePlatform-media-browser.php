<?php
/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2014  thePlatform for Media Inc.

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

/*
 * Load scripts and styles 
 */
add_action('wp_enqueue_scripts', 'theplatform_media_clear_styles_and_scripts', 100912);
function theplatform_media_clear_styles_and_scripts() {
	global $wp_styles; 
	foreach( $wp_styles->queue as $handle ) {	
		wp_dequeue_style( $handle );
	}    

	global $wp_scripts; 
    foreach( $wp_scripts->queue as $handle ) {       	
        wp_dequeue_script( $handle );
    }   

	wp_enqueue_script( 'tp_mediaview_js' );
	wp_enqueue_script( 'jquery-ui-dialog' );
	wp_enqueue_style( 'tp_bootstrap_css' );
	wp_enqueue_style( 'tp_theplatform_css' );
	wp_enqueue_style( 'wp-jquery-ui-dialog' );
	wp_enqueue_style( 'dashicons' );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
		 
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="tp:PreferredRuntimes" content="Flash, HTML5" />
		<meta name="tp:initialize" content="false" />

		<?php
		if ( !defined( 'ABSPATH' ) ) {
			exit;
		}
		$tp_viewer_cap = apply_filters( TP_VIEWER_CAP, TP_VIEWER_DEFAULT_CAP );
		if ( !current_user_can( $tp_viewer_cap ) ) {
			wp_die( '<p>You do not have sufficient permissions to browse MPX Media</p>' );
		}

		if ( !class_exists( 'ThePlatform_API' ) ) {
			require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
		}

		$tp_api = new ThePlatform_API;
		$metadata = $tp_api->get_metadata_fields();

		define( 'TP_MEDIA_BROWSER', true );

		$metadata_options = get_option( TP_METADATA_OPTIONS_KEY, array() );
		$upload_options = get_option( TP_UPLOAD_OPTIONS_KEY, TP_UPLOAD_FIELDS_DEFAULTS() );
		$preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
		$account = get_option( TP_ACCOUNT_OPTIONS_KEY );		

		if ( strcmp( $account['mpx_account_id'], "" ) == 0 ) {
			wp_die( 'MPX Account ID is not set, please configure the plugin before attempting to manage media' );
		}

		//Embed only stuff
		$players = $tp_api->get_players();		
		$IS_EMBED = isset( $_GET['embed'] ) ? true : false;

		function writePlayers( $players, $preferences ) {
			$html = '<p class="navbar-text sort-bar-text">Player:</p><form class="navbar-form navbar-left sort-bar-nav" role="sort"><select id="selectpick-player" class="form-control">';
			foreach ( $players as $player ) {
				$html .= '<option value="' . esc_attr( $player['pid'] ) . '"' . selected( $player['pid'], $preferences['default_player_pid'], false ) . '>' . esc_html( $player['title'] ) . '</option>';
			}
			$html .= '</select></form>';
			echo $html;
		}
		?>

		<script type="text/javascript">
			tpHelper = { };
			tpHelper.token = "<?php echo esc_js( $tp_api->mpx_signin() ); ?>";
			tpHelper.account = "<?php echo esc_js( $account['mpx_account_id'] ); ?>";
			tpHelper.accountPid = "<?php echo esc_js( $account['mpx_account_pid'] ); ?>";
			tpHelper.isEmbed = "<?php echo esc_js( $IS_EMBED ); ?>";
			tpHelper.mediaEmbedType = "<?php echo esc_js( $preferences['media_embed_type'] ); ?>";
		</script>
		
		<script id="category-template" type="text/x-handlebars-template">
			<a href="#" class="list-group-item cat-list-selector">{{entryTitle}}</a>
		</script>
		
		<script id="media-template" type="text/x-handlebars-template">
			<div class="media" id="{{guid}}"><img class="media-object pull-left thumb-img" data-src="{{placeHolder}}" alt="128x72" src="{{defaultThumbnailUrl}}">
				<div class="media-body">
					<div id="head">
							<strong class="media-heading">{{title}}</strong>
					</div>
					<div id="source"></div>
					<div id="desc">{{formatDescription description}}</div>
				</div>
			</div>
		</script>
		
		<script id="shortcode-template" type="text/x-handlebars-template">
			[theplatform account="{{account}}" media="{{release}}" player="{{player}}"]
		</script>			
		
		<?php wp_head(); ?>

    </head>
    <body class="tp">
		<div>
			<nav class="navbar navbar-default navbar-fixed-top" role="navigation">
				<div class="row">
					<div class="navbar-header" style="margin-left: 15px">
						<a class="navbar-brand" href="#"><img height="25px" width="25px" src="<?php echo esc_url( plugins_url( '/images/embed_button.png', __FILE__ ) ); ?>"> thePlatform</a>
					</div>            
					<form class="navbar-form navbar-left" role="search" onsubmit="return false;"><!--TODO: Add seach functionality on Enter -->
						<div class="form-group">
							<input id="input-search" type="text" class="form-control" placeholder="Keywords">
						</div>
						<button id="btn-feed-preview" type="button" class="btn btn-default">Search</button>
					</form>

					<p class="navbar-text sort-bar-text">Sort:</p>
					<form class="navbar-form navbar-left sort-bar-nav" role="sort">
						<select id="selectpick-sort" class="form-control">
							<option>Added</option>
							<option>Title</option>
							<option>Updated</option>
						</select>
					</form>

					<div id="my-content" class="navbar-left">
						<p class="navbar-text sort-bar-text">
							<!-- Render My Content checkbox -->
							<?php if ( $preferences['user_id_customfield'] !== '(None)' ) { ?>
								<input type="checkbox" id="my-content-cb" <?php checked( $preferences['filter_by_user_id'] === 'true' ); ?> />
								<label for="my-content-cb" style="font-weight: normal">My Content</label>													
							<?php } ?>
							<!-- End My Content Checkbox -->	
						</p>
					</div>
					<?php
					if ( $IS_EMBED ) {
						writePlayers( $players, $preferences );
					}
					?>    
					<img id="load-overlay" src="<?php echo esc_url( plugins_url( '/images/loading.gif', __FILE__ ) ) ?>" class="loadimg navbar-right">
				</div>           
			</nav>

			<div class="fs-main">
				<div id="filter-container">
					<div id="filter-affix" class="scrollable affix-top">
						<div id="list-categories" class="list-group">
							<a class="list-group-item active">
								Categories
							</a>
							<a href="#" class="list-group-item cat-list-selector" style="background-color: #D8E8FF;">All Videos</a>
						</div>                    
					</div>
				</div>

				<div id="content-container">
					<div id="message-panel"></div>
					<div id="media-list"></div>
				</div>
				<div id="info-container">
					<div id="info-affix" class="scrollable affix-top">
						<div id="info-player-container">
							<div id="modal-player" class="marketplacePlayer">
								<img id="modal-player-placeholder" data-src="holder.js/320x180/text:No Preview Available" src=""><!-- holder.js/128x72/text:No Thumbnail" -->
								<div class="tpPlayer" id="player"
									 tp:allowFullScreen="true"
									 tp:skinUrl="//pdk.theplatform.com/current/pdk/skins/glass/glass.json"
									 tp:layout="&lt;controls&gt;
									 &lt;region id=&quot;tpAdCountdownRegion&quot;&gt;
									 &lt;row id=&quot;tpAdCountdownContainer&quot;&gt;
									 &lt;control id=&quot;tpAdCountdown&quot;/&gt;
									 &lt;/row&gt;
									 &lt;/region&gt;
									 &lt;region id=&quot;tpBottomFloatRegion&quot; alpha=&quot;85&quot;&gt;
									 &lt;row height=&quot;10&quot;&gt;
									 &lt;group percentWidth=&quot;100&quot; direction=&quot;horizontal&quot; verticalAlign=&quot;middle&quot;&gt;
									 &lt;control id=&quot;tpScrubber&quot;/&gt;				
									 &lt;/group&gt;
									 &lt;/row&gt;
									 &lt;row&gt;
									 &lt;control id=&quot;tpPlay&quot;/&gt;								
									 &lt;spacer/&gt;
									 &lt;control id=&quot;tpCurrentTime&quot;/&gt;
									 &lt;control id=&quot;tpTimeDivider&quot;/&gt;
									 &lt;control id=&quot;tpTotalTime&quot;/&gt;
									 &lt;spacer percentWidth=&quot;100&quot;/&gt;
									 &lt;control id=&quot;tpVolumeSlider&quot;/&gt;
									 &lt;control id=&quot;tpFullScreen&quot;/&gt;			
									 &lt;/row&gt;
									 &lt;/region&gt;
									 &lt;/controls&gt;"									
									 tp:showFullTime="true"
									 tp:controlBackgroundColor="0xbbbbbb"
									 tp:backgroundColor="0xbbbbbb"
									 tp:controlFrameColor="0x666666"
									 tp:frameColor="0x666666"
									 tp:textBackgroundColor="0xcccccc"
									 tp:controlHighlightColor="0x666666"
									 tp:controlHoverColor="0x444444"
									 tp:loadProgressColor="0x111111"
									 tp:controlSelectedColor="0x48821d"
									 tp:playProgressColor="0x48821d"
									 tp:scrubberFrameColor="0x48821d"
									 tp:controlColor="0x111111"
									 tp:textColor="0x111111"
									 tp:scrubberColor="0x111111"
									 tp:scrubTrackColor="0x111111"
									 tp:pageBackgroundColor="0xcccccc"
									 tp:plugin1="type=content|url=//pdk.theplatform.com/current/pdk/swf/akamaiHD.swf|fallback=switch%3Dhttp|bufferProfile=livestable|priority=1|videoLayer=akamaihd"
									 tp:plugin2="type=content|url=//pdk.theplatform.com/current/pdk/js/plugins/akamaiHD.js|fallback=switch%3Dhttp|bufferProfile=livestable|priority=1|videoLayer=akamaihd">
									<noscript class="tpError">To view this site, you need to have JavaScript enabled in your browser, and either the Flash Plugin or an HTML5-Video enabled browser. Download <a href="http://get.adobe.com/flashplayer/" target="_black">the latest Flash player</a> and try again.</noscript>
								</div>
							</div>
							<br>
							<div id="panel-contentpane" class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title">Metadata</h3>
								</div>
								<div class="panel-body">
									<?php
									foreach ( $upload_options as $upload_field => $val ) {
										if ( $val == 'hide' ) {
											continue;
										}

										$field_title = (strstr( $upload_field, '$' ) !== false) ? substr( strstr( $upload_field, '$' ), 1 ) : $upload_field;
										$display_title = mb_convert_case( $field_title, MB_CASE_TITLE );

										//Custom names
										if ( $field_title === 'guid' ) {
											$display_title = 'Reference ID';
										}
										if ( $field_title === 'link' ) {
											$display_title = 'Related Link';
										}
										$html = '<div class="row">';
										$html .= '<strong>' . esc_html( $display_title ) . ': </strong>';
										$html .= '<span id="media-' . esc_attr( strtolower( $field_title ) ) . '"' . '" data-name="' . esc_attr( strtolower( $field_title ) ) . '"></span></div>';
										echo $html;
									}

									foreach ( $metadata_options as $custom_field => $val ) {
										if ( $val == 'hide' ) {
											continue;
										}

										$metadata_info = NULL;
										foreach ( $metadata as $entry ) {
											if ( array_search( $custom_field, $entry ) ) {
												$metadata_info = $entry;
												break;
											}
										}

										if ( is_null( $metadata_info ) ) {
											continue;
										}

										$field_title = $metadata_info['fieldName'];
										$field_prefix = $metadata_info['namespacePrefix'];
										$field_namespace = $metadata_info['namespace'];
										$field_type = $metadata_info['dataType'];
										$field_structure = $metadata_info['dataStructure'];

										$html = '<div class="row">';
										$html .= '<strong>' . esc_html( mb_convert_case( $field_title, MB_CASE_TITLE ) ) . ': </strong>';
										$html .= '<span id="media-' . esc_attr( $field_title ) . '" data-type="' . esc_attr( $field_type ) . '" data-structure="' . esc_attr( $field_structure ) . '" data-name="' . esc_attr( $field_title ) . '" data-prefix="' . esc_attr( $field_prefix ) . '" data-namespace="' . esc_attr( $field_namespace ) . '"></span></div>';
										echo $html;
									}
									?>                      
								</div>
								<div id="btn-container">
									<?php if ( $IS_EMBED ) { ?>
									<div class="btn-group">	                            
										<input type="button" id="btn-embed" class="btn btn-default btn-xs" value="Embed">
										<input type="button" id="btn-embed-close" class="btn btn-default btn-xs" value="Embed & Close">
										<input type="button" id="btn-set-image" class="btn btn-default btn-xs" value="Set Featured Image">
									</div>
									<?php } else {
										?>
										<input type="button" id="btn-edit" class="btn btn-default btn-xs" value="Edit Media">
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$tp_editor_cap = apply_filters( 'tp_editor_cap', 'upload_files' );
		if ( !$IS_EMBED && current_user_can( $tp_editor_cap ) ) {
			?>
			<div id="tp-edit-dialog" class="tp" style="display: none; padding-left:10px;">
				<div id="media-mpx-upload-form" class="tp">
					<?php require_once( dirname( __FILE__ ) . '/thePlatform-upload.php' ); ?>
				</div>
			<?php } ?>

		<?php wp_footer(); ?>
    </body>
</html>