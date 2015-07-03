<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Plugin Settings</span></h2>
	
	<form action="" method="post" accept-charset="utf-8">
		<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_METERED) ?>
		<h3>Paywall Settings</h3>
		<table border="0" class="form-table">
			<tr>
				<th><label for="DefaultPlacementMode">Paywall Style</label></th>
				<td>
					<select name="DefaultPlacementMode" id="DefaultPlacementMode" onchange="if (this.value == 'inpage'){ document.getElementById('numparagraphs').style.display='table-row'; } else { document.getElementById('numparagraphs').style.display='none'; }">
						<option value="overlay"<?php echo (get_option(MediaPass_Plugin::OPT_DEFAULT_PLACEMENT_MODE) == 'overlay') ? ' selected="selected"' : null ?>>Page Overlay</option>
						<option value="inpage"<?php echo (get_option(MediaPass_Plugin::OPT_DEFAULT_PLACEMENT_MODE) == 'inpage')
						? ' selected="selected"' : null ?>>In-Page</option>
					</select>
				</td>
			</tr>
			<tr id="numparagraphs" style="display:<?php echo (get_option(MediaPass_Plugin::OPT_DEFAULT_PLACEMENT_MODE) == 'inpage') ? 'table-row' : 'none' ?>;">
				<th><label for="NumInPageParagraphs">Number of Paragraphs in the Teaser view before the In-Page overlay</label></th>
				<td>
					<input type="text" name="NumInPageParagraphs" value="<?php echo esc_attr( get_option(MediaPass_Plugin::OPT_NUM_INPAGE_PARAGRAPHS) ); ?>" id="NumInPageParagraphs">
				</td>
			</tr>		
		</table>
		<h3>Metered Settings</h3>
	<p class="subtitle" style=" padding-left:0;width: 800px;">
		MediaPass offers a Metered feature for Subscriptions. Metered models are used by some online publications to allow a reader to access a set number of pages before they are required to sign-up for a Premium Subscription.		
	<br/><br/>
		Enter the number of page views a user can access before they are prompted to sign up for your site's Premium Subscription.  This subscription meter is only applied to the content with MediaPass Subscriptions enabled.
	</p>
		
		<table border="0" class="form-table">
			<tr>
				<th><label for="Status">Status</label></th>
				<td>
					<select name="Status" id="Status">
						<option value="On"<?php echo ($data['Msg']['Status'] == 'On') ? ' selected="selected"' : null ?>>On</option>
						<option value="Off"<?php echo ($data['Msg']['Status'] == 'Off') ? ' selected="selected"' : null ?>>Off</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="Count">Count</label></th>
				<td>
					<input type="text" name="Count" value="<?php  if (strval($data['Msg']['Count']) != "") { echo esc_attr( $data['Msg']['Count'] ); } else { echo "0"; } ?>" id="Count">
				</td>
			</tr>
			<tr>
				<td colspan="2"><input type="submit" class="button-primary" value="Update"></td>
			</tr>
		</table>
	</form>
</div>