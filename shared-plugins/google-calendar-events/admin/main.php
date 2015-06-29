<div class="wrap">
	<h3><?php _e('Add a New Feed', GCE_TEXT_DOMAIN); ?></h3>

	<a href="<?php echo admin_url('options-general.php?page=' . GCE_PLUGIN_NAME . '.php&action=add'); ?>" class="button-secondary" title="<?php _e('Click here to add a new feed', GCE_TEXT_DOMAIN); ?>"><?php _e('Add Feed', GCE_TEXT_DOMAIN); ?></a>

	<br /><br />
	<h3><?php _e('Current Feeds', GCE_TEXT_DOMAIN); ?></h3>

	<?php
	//Get saved feed options
	$options = get_option(GCE_OPTIONS_NAME);
	//If there are no saved feeds
	if(empty($options)){
	?>

	<p><?php _e('You haven\'t added any Google Calendar feeds yet.', GCE_TEXT_DOMAIN); ?></p>

	<?php //If there are saved feeds, display them ?>
	<?php }else{ ?>

	<table class="widefat">
		<thead>
			<tr>
				<th scope="col"><?php _e('ID', GCE_TEXT_DOMAIN); ?></th>
				<th scope="col"><?php _e('Title', GCE_TEXT_DOMAIN); ?></th>
				<th scope="col"><?php _e('URL', GCE_TEXT_DOMAIN); ?></th>
				<th scope="col"></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th scope="col"><?php _e('ID', GCE_TEXT_DOMAIN); ?></th>
				<th scope="col"><?php _e('Title', GCE_TEXT_DOMAIN); ?></th>
				<th scope="col"><?php _e('URL', GCE_TEXT_DOMAIN); ?></th>
				<th scope="col"></th>
			</tr>
		</tfoot>

		<tbody>
			<?php 
			foreach($options as $key => $event){ ?>
			<tr>
				<td><?php echo $key; ?></td>
				<td><?php echo $event['title']; ?></td>
				<td><?php echo $event['url']; ?></td>
				<td align="right">
					<a href="<?php echo admin_url('options-general.php?page=' . GCE_PLUGIN_NAME . '.php&action=refresh&id=' . $key); ?>"><?php _e('Refresh', GCE_TEXT_DOMAIN); ?></a>&nbsp;|&nbsp;<a href="<?php echo admin_url('options-general.php?page=' . GCE_PLUGIN_NAME . '.php&action=edit&id=' . $key); ?>"><?php _e('Edit', GCE_TEXT_DOMAIN); ?></a>&nbsp;|&nbsp;<a href="<?php echo admin_url('options-general.php?page=' . GCE_PLUGIN_NAME . '.php&action=delete&id=' . $key); ?>"><?php _e('Delete', GCE_TEXT_DOMAIN); ?></a>
				</td>
			</tr>
			<?php } ?>
		</tbody>

	</table>

	<?php }
	//Get saved general options
	$options = get_option(GCE_GENERAL_OPTIONS_NAME);
	?>

	<br />
	<h3><?php _e('General Options', GCE_TEXT_DOMAIN); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><?php _e('Custom stylesheet URL', GCE_TEXT_DOMAIN); ?></th>
			<td>
				<span class="description"><?php _e('If you want to alter the default plugin styling, create a new stylesheet on your server (not in the <code>google-calendar-events</code> directory) and then enter its URL below.', GCE_TEXT_DOMAIN); ?></span>
				<br />
				<input type="text" name="gce_general[stylesheet]" value="<?php echo esc_attr( $options['stylesheet'] ); ?>" size="100" />
			</td>
		</tr><tr>
			<th scope="row"><?php _e('Add JavaScript to footer?', GCE_TEXT_DOMAIN); ?></th>
			<td>
				<span class="description"><?php _e('If you are having issues with tooltips not appearing or the AJAX functionality not working, try ticking the checkbox below.', GCE_TEXT_DOMAIN); ?></span>
				<br />
				<input type="checkbox" name="gce_general[javascript]"<?php checked($options['javascript'], true); ?> value="on" />
			</td>
		</tr><tr>
			<th scope="row"><?php _e('Loading text', GCE_TEXT_DOMAIN); ?></th>
			<td>
				<span class="description"><?php _e('Text to display while calendar data is loading (on AJAX requests).', GCE_TEXT_DOMAIN); ?></span>
				<br />
				<input type="text" name="gce_general[loading]" value="<?php echo esc_attr( $options['loading'] ); ?>" />
			</td>
		</tr><tr>
			<th scope="row"><?php _e('Error message', GCE_TEXT_DOMAIN); ?></th>
			<td>
				<span class="description"><?php _e('An error message to display to non-admin users if events cannot be displayed for any reason (admins will see a message indicating the cause of the problem).', GCE_TEXT_DOMAIN); ?></span>
				<br />
				<input type="text" name="gce_general[error]" value="<?php echo esc_attr( $options['error'] ); ?>" size="100" />
			</td>
		</tr><tr>
			<th scope="row"><?php _e('Optimise event retrieval?', GCE_TEXT_DOMAIN); ?></th>
			<td>
				<span class="description"><?php _e('If this option is enabled, the plugin will use an experimental feature of the Google Data API, which can improve performance significantly, especially with large numbers of events. Google could potentially remove / change this feature at any time.', GCE_TEXT_DOMAIN); ?></span>
				<br />
				<input type="checkbox" name="gce_general[fields]"<?php checked($options['fields'], true); ?> value="on" />
			</td>
		</tr><tr>
			<th scope="row"><?php _e('Use old styles?', GCE_TEXT_DOMAIN); ?></th>
			<td>
				<span class="description"><?php _e('Some CSS changes were made in version 0.7. If this option is enabled, the old CSS will still be added along with the main stylesheet. You should consider updating your stylesheet so that you don\'t need this enabled.', GCE_TEXT_DOMAIN); ?></span>
				<br />
				<input type="checkbox" name="gce_general[old_stylesheet]"<?php checked($options['old_stylesheet'], true); ?> value="on" />
			</td>
		</tr>
	</table>

	<br />

	<input type="submit" class="button-primary" value="<?php _e('Save', GCE_TEXT_DOMAIN); ?>" />
</div>