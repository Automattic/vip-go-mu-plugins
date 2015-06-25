<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Manage eCPM Floor</span></h2>

	<form action="" method="post" accept-charset="utf-8">
		<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_ECPM_FLOOR) ?>
		<table border="0" class="form-table">
			<tr>
				<th><label for="ecpm_floor">eCPM Floor</label></th>
				<td>
					<input type="text" name="ecpm_floor" value="<?php echo esc_attr( $data['Msg'] ); ?>" id="ecpm_floor">
				</td>
			</tr>
			<tr>
				<td colspan="2"><input type="submit" value="Update"></td>
			</tr>
		</table>
	</form>
</div>