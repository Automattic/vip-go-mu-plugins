<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Network Settings</span></h2>
	
	<p class="subtitle" style="padding-left:0">
	For WordPress users with multiple sites, MediaPass supports a Network feature.  <a href="#a-manage-sites">Manage your sites</a> and <a href="#a-pricing">configure pricing here</a>.
	</p>
	
	<br/>
	<h3 id="a-manage-sites">Current Network Sites</h3>
	<form method="POST">
	<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_NETWORK) ?>
	<input type="hidden" name="mp-network-update-active-site-action" value="1" />
	<table class="widefat" id="network-sites">
		<tr>
			<th>Active</th>
			<th>Title</th>
			<th>Domain</th>
			<th class="last">BackLink</th>
		</tr>
		<?php if (!empty($data['Msg']) && is_array($data['Msg'])): ?>
			<?php foreach ($data['Msg'] as $network): ?>
				<tr id="network-site-<?php echo esc_attr( $network['Id'] ) ?>">
					<td><input 
						type="radio" 
						name="network-selected" 
						value="<?php echo $network['Id']?>"
						<?php echo ($network['Id'] == get_option(MediaPass_Plugin::OPT_USER_NUMBER) ? 'checked="true"' : '')?> 
						/>
					</td>
					<td><?php echo esc_html( $network['Title'] ); ?></td>
					<td><?php echo esc_html( $network['Domain'] ); ?></td>
					<td class="last"><?php echo esc_html( $network['BackLink'] ); ?></td>
				</tr>
			<?php endforeach ?>
			<tr><td colspan="4" align="right">

	<input type="submit" class="button-primary" value="Save Changes" />
			</td></tr>
		<?php else: ?>
			<tr>
				<td colspan="4">No network sites found.</td>
			</tr>
		<?php endif ?>
	</table>
	<br />

	</form>
<hr/>
	<h3>Add New Site</h3>
	<form method="post" accept-charset="utf-8">
		<input type="hidden" name="mp-network-create-site-action" value="1" />
		
		<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_NETWORK) ?>
		<table border="0" class="form-table">
			<tr class="network-site">
				<td>
					<div style="float: left; margin-right: 10px;">
					<label for="title">Title</label>
					<br/>
					<input type="text" name="Title" value="" class="title">
					</div>
					<div style="float: left; margin-right: 10px;">
					<label for="domain">Domain</label>
					<br/>
					<input type="text" name="Domain" value="" class="domain">
					</div>
					<div style="float: left;">
					<label for="back_link">BackLink</label>
					<br/>
					<input type="text" name="BackLink" value="" class="back_link">
					</div>
				</td>
			</tr>
			<tr>
				<td colspan="2"><input class="button-primary" type="submit" value="Add New Network Site"></td>
			</tr>
		</table>
	</form>
<br/>
</div>

	<h3 id="a-pricing">Set Pricing</h3>
	<div id="membership-wrapper">
		<p class="subtitle" style="padding-left:0">Setting up a "Network Pass" allows site visitors to pay to access Premium content on multiple website, all managed by one WordPress administrator.  Pricing specified here will be made available to people who wish to subscribe to all sites in your Network.</p>
		<br/>
		<p class="subtitle" style="padding-left:0">Your site visitors will see three options when asked to sign-up for a Premium subscription. Select the 3 subscription membership periods to choose from, and the corresponding unit price for each period. For example, if you want to charge $60 for a 6 month subscription, enter $10 for the unit price.</p>
		<br/>
		<form method="POST">
			
		<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_NETWORK) ?>
		<input type="hidden" name="mp-network-update-active-network-pricing" value="1" />
		
		<table border="0" cellspacing="0" cellpadding="0" id="price-points">
			<tr>
				<th>Period Length</th>
				<th>Unit Price</th>
			</tr>
				
			<?php
			$i = 0;
			while ($i <= 2): ?>
				<tr>
					<td>
						<select name="prices[<?php echo $i ?>][pricing_period]" id="pricing-period-<?php echo $i ?>" style="width:90px;">
							<?php
							$options = array(
								'1mo' => array(
									'Label' => '1 Month',
									'Length' => 1,
									'Increment' => 2592000
								),
								'3mo' => array(
									'Label' => '3 Months',
									'Length' => 3,
									'Increment' => 2592000
								),
								'6mo' => array(
									'Label' => '6 Months',
									'Length' => 6,
									'Increment' => 2592000
								),
								'1yr' => array(
									'Label' => '1 Year',
									'Length' => 1,
									'Increment' => 31104000
								)
							);
							?>
							<?php foreach ($options as $key => $value): ?>
								<?php
									$selected = null;
									if (isset($data['pricing_data']['msg'])) {
										if (!empty($data['pricing_data']['msg'][$i]) && 
											$data['pricing_data']['msg'][$i]['Increment'] == $value['Increment'] && 
											$data['pricing_data']['msg'][$i]['Length'] == $value['Length']
											) {
											$selected = ' selected="selected"';
										}
									} else {
										// Defaults
										switch ($i) {
											case 0:
												if ($key == '1mo') {
													$selected = ' selected="selected"';
												}
												break;
											case 1:
												if ($key == '6mo') {
													$selected = ' selected="selected"';
												}
												break;
											case 2:
												if ($key == '1yr') {
													$selected = ' selected="selected"';
												}
												break;											
											default:
												$price = null;
												break;
										}
									}
								?>
								<option value="<?php echo $key ?>"<?php echo $selected ?>><?php echo $value['Label'] ?></option>
							<?php endforeach ?>
						</select>
						<label for="pricing-period">Membership at</label>
					</td>
					<td>
						<?php
						$price = null;
						if (isset($data['pricing_data']['msg'])) {
							$price = $data['pricing_data']['msg'][$i]['Price'];
						} else {
							// Defaults
							switch ($i) {
								case 0:
									$price = "9.95";
									break;
								case 1:
									$price = "7.95";
									break;
								case 2:
									$price = "4.95";
									break;											
								default:
									$price = null;
									break;
							}
						}
						?>
						$<input type="text" name="prices[<?php echo $i ?>][price]" value="<?php echo esc_attr( $price ); ?>" id="price-<?php echo $i ?>"> per month
					</td>
				</tr>
				
			<?php 
			$i++;
			endwhile ?>
				
			<tr>
				<td>
					<input type="submit" class="button-primary" value="Create Price Point Set">
				</td>
				<td>
					<p>
						<input type="checkbox" name="set_default" value="" id="set-default">
						<strong>Set this price point set as my default active price set.</strong></p>
					<p><strong>(Note: This will stop and overide any current price point test.)</strong></p>
				</td>
			</tr>
		</table>
	</form>

	</div>