<!-- Plugin title bar -->
<script type="text/html" id="tmpl-ooyala-title-bar">
	<h1 class="ooyala-title">Ooyala</h1>

	<div class="ooyala-title-links">
		<a class="ooyala-title-link ooyala-browse-link ooyala-browsing"><?php esc_html_e( "Back to Browse", 'ooyala' ); ?></a>
		<a class="ooyala-upload-toggle ooyala-title-link"><?php esc_html_e( "Upload", 'ooyala' ); ?></a>
		<a class="ooyala-title-link ooyala-about-link"><?php esc_html_e( "About", 'ooyala' ); ?></a>
		<a class="ooyala-title-link ooyala-privacy-link" target="_ooyala" href="http://www.ooyala.com/privacy"><?php esc_html_e( "Privacy Policy", 'ooyala' ); ?></a>
	</div>
</script>

<!-- About panel -->
<script type="text/html" id="tmpl-ooyala-about-text">
	<a class="ooyala-close ooyala-close-x"></a>

<?php
	/* TODO: Localize this text. */
	include( __DIR__ . '/ooyala-about-en-us.html' );
?>

	<p style="text-align: right">
		<a class="ooyala-close" href="#"><?php esc_html_e( "Close", 'ooyala' ); ?></a>
	</p>
</script>

<!-- Main attachments browser -->
<script type="text/html" id="tmpl-ooyala-attachments-browser">
<div class="ooyala-browser-container">
	<table class="ooyala-browser-flex-container">
		<tbody>
			<tr>
				<td class="ooyala-search-toolbar"></td>
			</tr>
			<tr>
				<td class="ooyala-browser-container">
					<div class="ooyala-browser">
						<div class="ooyala-results"></div>
						<div class="ooyala-search-spinner"></div>
					</div>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="ooyala-sidebar-container">
	<div class="ooyala-sidebar">
	</div>
</div>
</script>

<!-- Single attachment -->
<script type="text/html" id="tmpl-ooyala-attachment">
<# var classes = [];
	classes.push('type-' + data.asset_type);
	#>
	<div class="attachment-preview js--select-attachment ooyala-attachment {{ classes.join(' ') }}">
		<#  // if the status is uploading and WE are actually uploading it right now (will have a percent field)
			// i.e. assets can have the status of uploading if the upload was started and abandoned (or still in progress elswhere)
			if ( data.status === 'uploading' && 'percent' in data ) { #>
			<div class="thumbnail"><div class="media-progress-bar"><div></div></div></div>
		<# } else { #>
			<div class="thumbnail">
				<div class="centered">
				<# if (data.preview_image_url) { #>
					<img src="{{ data.preview_image_url }}" draggable="false" />
				<# } #>
				</div>
			</div>
		<# } #>
			<div class="asset-details">
				<span class="asset-name">{{ data.name }}</span>
			</div>

		<# if ( data.buttons.close ) { #>
			<a class="close media-modal-icon" href="#" title="<?php esc_attr_e( 'Remove', 'ooyala' ); ?>"></a>
		<# } #>

		<# if ( data.buttons.check ) { #>
			<a class="check" href="#" title="<?php esc_attr_e( 'Deselect', 'ooyala' ); ?>"><div class="media-modal-icon"></div></a>
		<# } #>
	</div>
</script>

<!-- Main sidebar details for single attachment -->
<script type="text/html" id="tmpl-ooyala-details">
<div class="ooyala-image-details">
	<div class="thumbnail">
		<# if(data.preview_image_url) { #>
			<img src="{{ data.preview_image_url }}" class="icon" draggable="false" />
			<div class="ooyala-thumbnail-action">
			<# if(typeof data.attachment_id != 'undefined') { #>
				<# if(data.attachment_id && data.attachment_id === wp.media.view.settings.post.featuredImageId) { #>
					<span class="ooyala-status-text ooyala-status-featured"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Featured Image Set to Thumbnail', 'ooyala' ); ?></span>
				<# } else { #>
				<button class="ooyala-set-featured button-secondary" {{ data.attachment_id && data.attachment_id === wp.media.view.settings.post.featuredImageId ? disabled="disabled" : '' }}><?php esc_html_e( 'Set Thumbnail as Featured Image', 'ooyala' ); ?></button>
				<# } #>
			<# } else { #>
				<span class="ooyala-status-text ooyala-status-checking loading"><?php esc_html_e( 'Checking image', 'ooyala' ); ?></span>
			<# } #>
		<# } #>
		</div>
	</div>
</div>
<dl class="ooyala-image-details-list">

	<dt class="ooyala-title"><?php esc_html_e( 'Title:', 'ooyala' ); ?></dt>
	<dd class="ooyala-title">{{ data.name }}</dd>

	<# if (data.duration) { #>
	<dt class="ooyala-duration"><?php esc_html_e( 'Duration:', 'ooyala' ); ?></dt>
	<dd class="ooyala-duration">{{ data.duration_string }}</dd>
	<# } #>

	<dt class="ooyala-status"><?php esc_html_e( 'Status:', 'ooyala' ); ?></dt>
	<dd class="ooyala-status ooyala-status-{{ data.status }} {{ data.status == 'processing' ? 'loading' : '' }}">{{ data.status }}
	<# if (data.status=='uploading' && data.percent !== undefined) { #>
		<em class="progress">(<span>{{ data.percent }}</span>%)</em>
	<# } #>
	</dd>

	<# if ( data.description ) { #>
	<dt class="ooyala-description"><?php esc_html_e( 'Description:', 'ooyala' ); ?></dt>
	<#  if ( data.description.length > ( data.descriptionMaxLen + data.maxLenThreshold ) ) {
			var trunc = data.description.lastIndexOf(" ", data.descriptionMaxLen);
			if (trunc==-1) trunc = data.descriptionMaxLen;
			#>
	<dd class="ooyala-description">{{ data.description.slice(0,trunc) }}<span class="more">{{ data.description.slice(trunc) }}</span> <a href="#" class="show-more">(show&nbsp;more)</a></dd>
		<# } else { #>
	<dd class="ooyala-description">{{ data.description }}</dd>
		<# }
	 } #>

	<# if(data.labels && data.labels.length > 0) {
	#>
	<dt class="ooyala-labels"><?php esc_html_e( 'Labels:', 'ooyala' ); ?></dt>
	<dd class="ooyala-labels">
		<ul>
		<# for(var i = 0; i < data.labels.length; i++) { #>
			<li class="ooyala-label"><a href="#label-{{ data.labels[i].id }}" title="Click to refine your search by this label">{{ data.labels[i].name }}</a></li>
		<# } #>
		</ul>
	</dd>
	<# }
#>
</dl>
</script>

<!-- Player display options -->
<script type="text/html" id="tmpl-ooyala-display-settings">
<h3><?php esc_html_e( 'Player Display Settings', 'ooyala' ); ?></h3>

<div class="ooyala-display-settings-wrapper {{ (data.model.forceEmbed || data.model.attachment.canEmbed()) ? '' : 'embed-warning' }}">
<div class="message"><?php esc_html_e( 'This asset may not display correctly due to its current status. Do you wish to embed it anyway?', 'ooyala' ); ?><a href="#">Show Player Settings</a></div>
<label class="setting">
	<span><?php esc_html_e( 'Player', 'ooyala' ); ?></span>
	<# if ( data.players.isFetching ) { #>
		<em class="loading"><?php esc_html_e( 'Retrieving players', 'ooyala' ); ?></em>
	<# } else { #>
		<select data-setting="player_id">
			<option value=""><?php esc_html_e( 'Default', 'ooyala' ); ?></option>
		<# data.players.each( function(item) { #>
			<option value="{{ item.get('id') }}">{{ item.get('name') }}</option>
		<# }); #>
		</select>
	<# } #>
</label>

<label class="setting">
	<span><?php esc_html_e( 'Platform', 'ooyala' ); ?></span>
	<select data-setting="platform">
		<option value=""><?php esc_html_e( 'Default', 'ooyala' ); ?></option>
		<# _.each(['flash','flash-only','html5-fallback','html5-priority'], function(value) { #>
			<option value="{{ value }}">{{ value }}</option>
		<# }); #>
	</select>
</label>

<div class="setting resolution">
	<span><?php esc_html_e( 'Size', 'ooyala' ); ?></span>
	<# if (data.model.attachment.get('downloadingResolutions')) { #>
		<em class="loading"><?php esc_html_e( 'Retrieving video resolutions', 'ooyala' ); ?></em>
	<# } else { #>
		<select data-setting="resolution">
			<option value="auto"><?php esc_html_e( 'Auto', 'ooyala' ); ?></option>
		<# var resolutions = data.model.attachment.get('resolutions');
		if (resolutions && resolutions.length > 0) {
			for (var i = 0; i < resolutions.length; i++) {
				var res = resolutions[i].join(' x ') #>
				<option value="{{ res }}">{{ res }}</option>
			<# }
		} #>
			<option value="custom"><?php esc_html_e( 'Custom', 'ooyala' ); ?></option>
		</select>
		<div class="custom-resolution">
			<input type="text" data-setting="width"/>
			X
			<input type="text" data-setting="height"/>
			<label><input type="checkbox" data-setting="lockAspectRatio"> <?php esc_html_e( 'Maintain aspect ratio', 'ooyala' ); ?></label>
		</div>
	<# } #>
</div>

<label class="setting">
	<span><?php esc_html_e( 'Enable Channels', 'ooyala' ); ?></span>
	<input type="checkbox" data-setting="enable_channels"/>
</label>

<label class="setting initial-time">
	<span><?php esc_html_e( 'Initial Time', 'ooyala' ); ?></span>
	<input type="text" data-setting="initial_time" min="0" max="{{ data.model.attachment.get('duration') / 1000 }}"> <?php esc_html_e( 'sec', 'ooyala' ); ?>
</label>

<label class="setting">
	<span><?php esc_html_e( 'Autoplay', 'ooyala' ); ?></span>
	<input type="checkbox" data-setting="autoplay"/>
</label>

<label class="setting">
	<span><?php esc_html_e( 'Chromeless', 'ooyala' ); ?></span>
	<input type="checkbox" data-setting="chromeless"/>
</label>

<label class="setting">
	<span><?php esc_html_e( 'Locale', 'ooyala' ); ?></span>
	<select data-setting="locale">
		<option value=''><?php esc_html_e( 'User Default', 'ooyala' ); ?></option>
	<?php
	$locales = array(
		'zh_CN' => 'Chinese (Simplified)', /* need to verify these */
		'zh_TW' => 'Chinese (Traditional)',
		'en' => 'English',
		'fr' => 'French',
		'de' => 'German',
		'it' => 'Italian',
		'ja' => 'Japanese',
		'pl' => 'Polish',
		'pt' => 'Portuguese',
		'ru' => 'Russian',
		'es' => 'Spanish',
	);
	foreach ( $locales as $code => $label ) { ?>
		<option value="<?php esc_attr_e( $code ); ?>"><?php esc_html_e( $label, 'ooyala' ); ?></option>
<?php } ?>
	</select>
</label>

<label class="setting additional-parameters">
	<span><?php esc_html_e( 'Additional Player Parameters', 'ooyala' ); ?></span>
	<em class="error-message"><?php esc_html_e( 'There is an error in your syntax:', 'ooyala' ); ?></em>
	<textarea data-setting="additional_params_raw" placeholder="<?php esc_attr_e( 'Key/value pairs in JSON or JavaScript object literal notation', 'ooyala' ); ?>">{{ data.model.additional_params }}</textarea>
</label>
</div>
</script>

<!-- The square "More" button -->
<script type="text/html" id="tmpl-ooyala-more">
	<div class="attachment-preview">
		<div class="ooyala-more-spinner">
		</div>
		<div class="ooyala-more-text-container">
			<!--// <span class="ooyala-number-remaining"></span> //-->
			<span class="ooyala-more-text"><?php esc_html_e( 'More', 'ooyala' ); ?></span>
		</div>
	</div>
</script>

<!-- Unsupported browser message -->
<script type="text/html" id="tmpl-ooyala-unsupported-browser">
	<h1><?php esc_html_e( "Sorry, this browser is unsupported!", 'ooyala' ); ?></h1>

	<p><?php esc_html_e( "The Ooyala plugin requires at least Internet Explorer 10 to function. This plugin also supports other modern browsers with proper CORS support such as Firefox, Chrome, Safari, and Opera.", 'ooyala' ); ?></p>
</script>

<!-- Asset upload panel -->
<script type="text/html" id="tmpl-ooyala-upload-panel">
	<a class="ooyala-close ooyala-close-x"></a>
	<# if ( data.controller.uploader.files.length ) {
		var file = data.controller.uploader.files[0];
		var isUploading = data.controller.uploader.state === ooyala.plupload.STARTED;
		#>
		<div class="file-name"><?php esc_html_e( 'File:', 'ooyala' ); ?> {{ file.name }} <em class="file-size">({{ new Number( file.size ).bytesToString() }})</em>
		<# if( !isUploading ) { #>
			<a class="button ooyala-upload-browser" tabindex="10"><?php esc_html_e( 'Change', 'ooyala' ); ?></a>
		<# } #>
		</div>
		<label class="setting"><?php esc_html_e( 'Title', 'ooyala' ); ?><input type="text" value="{{ file.model.get('name') }}" data-setting="name" tabindex="20"></label>
		<label class="setting"><?php esc_html_e( 'Description', 'ooyala' ); ?><textarea data-setting="description" tabindex="30">{{ file.model.get('description') }}</textarea></label>
		<label class="setting"><?php esc_html_e( 'Post-processing Status', 'ooyala' ); ?>
		<select data-setting="futureStatus" tabindex="40">
		<# var status = ['live','paused'];
			for( var i = 0; i < status.length; i++) { #>
				<option value="{{ status[i] }}" {{{ status[i] == file.model.get('futureStatus') ? ' selected="selected"' : '' }}}>{{ status[i] }}</option>
		<# } #>
		</select></label>
		<div class="ooyala-upload-controls {{ isUploading ? 'uploading' : '' }}">
			<div class="progress"><span>{{ ( file.model.asset && file.model.asset.get('percent') ) || 0 }}</span>%</div>
			<a class="button ooyala-stop-upload" tabindex="60"><?php esc_html_e( 'Cancel Upload', 'ooyala' ); ?></a>
			<a class="button ooyala-start-upload" tabindex="50"><?php esc_html_e( 'Start Upload', 'ooyala' ); ?></a>
		</div>
	<# } else { #>
		<div class="ooyala-upload-browser-container">
			<h4><?php esc_html_e( 'Upload an asset to your account.', 'ooyala' ); ?></h4>
		<a class="button button-hero ooyala-upload-browser"><?php esc_html_e( 'Select File', 'ooyala' ); ?></a>
		</div>
	<# } #>
</script>

<!-- Current label refinement for search secondary toolbar -->
<script type="text/html" id="tmpl-ooyala-label-search">
	<?php esc_html_e( 'Refining by Label:', 'ooyala' ); ?>
	<span class="ooyala-selected-label"></span>
	<a href="#" title="<?php esc_attr_e( 'Clear Label', 'ooyala' ); ?>" class="ooyala-clear-label dashicons dashicons-dismiss"></a>
</script>
