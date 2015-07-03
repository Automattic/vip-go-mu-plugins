<?php
$ooyala = get_option( 'ooyala' );
if ( ! class_exists( 'OoyalaBacklotAPI' ) )
	require_once( dirname(__FILE__) . '/class-ooyala-backlot-api.php' );
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<title>Ooyala Video</title>
<?php wp_print_scripts( array( 'jquery', 'ooyala', 'ooyala-uploader', 'set-post-thumbnail', 'jquery-ui-progressbar' ) ); ?>
<?php wp_print_styles( array( 'global', 'media', 'wp-admin', 'colors', 'jquery-ui-progressbar' ) ); ?>
<script type="text/javascript">
	var ajaxurl = <?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>;
	var postId = <?php echo absint( $_GET['post_id'] ); ?>;
	var ajax_nonce_ooyala = <?php echo wp_json_encode( wp_create_nonce( 'ooyala' ) ); ?>;
</script>

<script>
	jQuery(document).ready(function () {
		OV.Popup.init();
		OV.Popup.resizePop();

		//Make initial reqest to load last few videos
		OV.Popup.ooyalaRequest( 'last_few' );
	});
	jQuery(window).resize(function() {
	  	setTimeout(function ()	{ OV.Popup.resizePop(); }, 50);
	});
</script>
<style>
	body { min-width:300px !important; }
	.tablenav-pages a { font-weight: normal;}
	.ooyala-item {float:left; height:146px; width:146px; padding:4px; border:1px solid #DFDFDF; margin:4px; box-shadow: 2px 2px 2px #DFDFDF;}
	.ooyala-item .item-title {height: 32px;}
	.ooyala-item .photo { margin:4px; }
	.ooyala-item .photo img { width: 128px; height:72px}
	.ooyala-item .item-title {text-align:center;}
	#latest-link {font-size: 0.6em; padding-left:10px;}
	#ov-content-upload label {display:block}
	.ui-progressbar .ui-progressbar-value { background-image: url(<?php echo esc_url( plugins_url( 'css/ooyala-uploader/images/pbar-ani.gif' ) ); ?>); }
	#progressbar { margin: 5px; width: 80%; }

</style>
</head>
<body id="media-upload">
	<div id="media-upload-header">
		<ul id="sidemenu" class="ov-tabs">
			<li id="ov-tab-ooyala"><a class="current" href=""><?php esc_html_e('Ooyala video','ooyalavideo'); ?></a></li>
			<li id="ov-tab-upload"><a href=""><?php esc_html_e('Upload to Ooyala','ooyalavideo'); ?></a></li>
		</ul>
	</div>
	<div class="ov-contents">
		<div id="ov-content-ooyala" class="ov-content">
		 	<form name="ooyala-requests-form" action="#">
				<p id="media-search">
					<img src="<?php echo esc_url( $this->plugin_url ); ?>img/ooyala100.png" style="vertical-align: middle; margin-right: 10px;"/>
					<select name="ooyalasearchfield" id="ov-search-field">
						<option value="description" selected="selected">Description</option>
						<option value="name">Name</option>
						<option value="labels">Label</option>
					</select>
					<label class="screen-reader-text" for="media-search-input"><?php esc_html_e('Search Keyword', 'ooyala_video');?></label>
					<input type="text" id="ov-search-term" name="ooyalasearch" value="">
					<input type="submit" name=""  id="ov-search-button" class="button" value="Search">
				</p>
				<div id="response-div">
					<h3 class="media-title"><?php esc_html_e('Loading...', 'ooyala_video');?></h3>
		      	</div>
		        <table border="0" cellpadding="4" cellspacing="0">

		           <tr>
		            <td nowrap="nowrap" style="text-align:right;"><?php echo esc_html_e('Insert Video ID:','ooyalavideo'); ?></td>
		            <td>
		              <table border="0" cellspacing="0" cellpadding="0">
		                <tr>
		                  <td><input name="vid" type="text" id="ooyala_vid" value="" style="width: 200px" /></td>
		                </tr>
		              </table></td>
		          </tr>
		           <tr>
		            <td nowrap="nowrap" style="text-align:right;">Player ID:</td>
		            <td>

			<select id="ooyala_player_id" name="ooyala_player_id">
<?php			foreach ( (array) $ooyala['players'] as $player ) : ?>
					<option value="<?php echo esc_attr( $player ); ?>"><?php echo esc_html( $player ); ?></option>
			<?php endforeach; ?>
			</select>
					</td>
		          </tr>
		          <tr>
		            <td>
			    <input type="submit" id="ooyala-insert" name="insert" value="<?php echo esc_attr_e('Insert','ooyalavideo'); ?>" />
		            </td>
		            <td align="right"><a href="#close" id="ooyala-close"><?php esc_html_e('Cancel', 'ooyala_video');?></td>
		          </tr>
		        </table>
		      <input type="hidden" name="tab" value="portal" />
			</form>
		</div>
		<div id="ov-content-upload" class="ov-content"  style="display:none;margin:1em">
			<h3 class="media-title"><?php esc_html_e('Upload to Ooyala', 'ooyalavideo' ); ?></h3>
      <label for="assetName">Name: </label>
      <input type="text" id="ooyala-file-name"/>
      <label for="assetDescription">Description: </label>
      <textarea id="ooyala-file-description" rows="5" style="width: 100%"></textarea><br/>
      <input type="file" id="ooyala-file" />
    <div>
		<button id="uploadButton" disabled onclick="return startUpload();">Upload!</button>
		<div id="status"></div>
    </div>
    <div id="progressbar" style="display:none;"></div>
    <script type="text/javascript">

		jQuery('#ooyala-file').change( function(e) {
			jQuery('#uploadButton').removeAttr("disabled");
		});
		function startUpload() {
			jQuery('#progressbar').show();
			jQuery('#progressbar').progressbar({value: 0});
			jQuery('#uploadButton').attr("disabled");
			var myUploader = new OoyalaUploader({
				embedCodeReady : function( asset_id ) {
					// The asset is created on Ooyala and uploading may commence
					// @todo: Tell the user the upload has started?
				},
				uploadProgress : function( asset_id, percent ) {
					jQuery( "#progressbar" ).progressbar({value: percent});
				},
				uploadComplete : function( asset_id ) {
					// The upload is complete
				},
				uploadError : function( asset_id, type, fileName, statusCode, message ) {
					console.log( message );
				}
			});
			myUploader.uploadFile( document.getElementById('ooyala-file').files[0], {
				assetCreationUrl : ajaxurl+'?action=ooyala_uploader&request=asset-create',
				assetUploadingUrl : ajaxurl+'?action=ooyala_uploader&request=asset-upload',
				assetStatusUpdateUrl : ajaxurl+'?action=ooyala_uploader&request=asset-status&asset_id=assetID',
				labelCreationUrl : ajaxurl+'?action=ooyala_uploader&request=labels-create',
				labelAssignmentUrl : ajaxurl+'?action=ooyala_uploader&request=labels-assign',
				name : jQuery('#ooyala-file-name').val(),
				description : jQuery('#ooyala-file-description').val(),
				postProcessingStatus: <?php echo wp_json_encode( $ooyala['video_status'] ); ?>
			} );
		}
    </script>
        </div>
		</div>
	</div>
</div>
</body>
</html>
