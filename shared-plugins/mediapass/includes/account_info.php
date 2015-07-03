<?php $data = $data['Msg']; ?>
<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Manage account information</span></h2>
	<p class="subtitle" style="padding-left:0">Update your MediaPass Publisher Account information here.  Accurate account and address information ensures proper payment of your Premium subscription revenue to WordPress site administrators, processed and issued by MediaPass.</p>
	<br/>
	<form action="" method="post" accept-charset="utf-8">
		<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_ACCOUNT) ?>
		<table border="0" cellspacing="0" cellpadding="0" class="form-table">
			<tbody>
				<tr>
					<th><label for="Title">Web Site Subject/Title</label></th>
					<td><input type="text" name="Title" value="<?php echo (!empty($data['Title'])) ? esc_attr( $data['Title'] ) : null ?>" id="title" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="CompanyName">Company Name</label></th>
					<td><input type="text" name="CompanyName" value="<?php echo (!empty($data['CompanyName'])) ? esc_attr( $data['CompanyName'] ) : null ?>" id="company-name" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="CompanyAddress">Company Address</label></th>
					<td><input type="text" name="CompanyAddress" value="<?php echo (!empty($data['CompanyAddress'])) ? esc_attr( $data['CompanyAddress'] ) : null ?>" id="company-address" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="City">City</label></th>
					<td><input type="text" name="City" value="<?php echo (!empty($data['City'])) ? esc_attr( $data['City'] ) : null ?>" id="city" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="State">State</label></th>
					<td><input type="text" name="State" value="<?php echo (!empty($data['State'])) ? esc_attr( $data['State'] ) : null ?>" id="state" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="Zip">ZIP or Postal Code</label></th>
					<td><input type="text" name="Zip" value="<?php echo (!empty($data['Zip'])) ? esc_attr( $data['Zip'] ) : null ?>" id="zip" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="Country">Country</label></th>
					<td><input type="text" name="Country" value="<?php echo (!empty($data['Country'])) ? esc_attr( $data['Country'] ) : null ?>" id="country" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="Telephone">Telephone</label></th>
					<td><input type="text" name="Telephone" value="<?php echo (!empty($data['Telephone'])) ? esc_attr( $data['Telephone'] ) : null ?>" id="telephone" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="CustomRedirectURL">Custom Redirect URL (http://)</label><br> (Optional): This is the URL your users will be redirected to when they decline to subscribe to your content.</th>
					<td><input type="text" name="CustomRedirectURL" value="<?php echo (!empty($data['CustomRedirectURL'])) ? esc_attr( $data['CustomRedirectURL'] ) : null ?>" id="custom-redirect-url" class="regular-text"></td>
				</tr>
				<!-- <tr>
					<th><label for="CustomSalePostURL">Custom Sale Posting URL</label></th>
					<td><input type="hidden" name="CustomSalePostURL" value="<?php echo (!empty($data['CustomSalePostURL'])) ? esc_attr( $data['CustomSalePostURL'] ) : null ?>" id="custom-sale-post-url" class="regular-text"></td>
				</tr>-->
			</tbody>
		</table>
		<p><input type="submit" class="button-primary" value="Update Account"></p>
	</form>
</div>