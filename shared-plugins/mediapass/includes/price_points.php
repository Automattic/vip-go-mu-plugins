<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Pricing Configuration</span></h2>

	<p class="subtitle" style="padding-left:0">Set up Premium Subscription Prices.</p>
	<br/>
	<p class="subtitle" style="padding-left:0">First, choose your Premium Subscription model. Most websites will use Membership Access, to allow multiple pages or sections of content to be included in your premium subscription membership. The Single Article Access is for websites that wish to only charge for specific pieces of content. This option is similar to a pay-per-view model, and does not support ongoing premium subscriptions.</p>
	<br/>
	<form action="#" method="post" accept-charset="utf-8" id="membership-form">
	<?php MediaPass_Plugin::nonce_for(MediaPass_Plugin::NONCE_PRICING) ?>
	<div id="subscription-model">
		<label for="subscription-model">My Premium Subscription Model:</label>
		<select name="subscription_model" id="subscription-model" style="width:165px;">
			<option value="membership"<?php echo ($data['subscription_model'] == 'membership') ? ' selected="selected"' : null ?>>Membership Access</option>
			<option value="single"<?php echo ($data['subscription_model'] == 'single') ? ' selected="selected"' : null ?>>Single Article Access</option>
		</select>
	</div>
	<br/>
	<div id="membership-wrapper"<?php echo ($data['subscription_model'] == 'single') ? ' style="display:none"' : null ?>>
		
		<p class="subtitle" style="padding-left:0">Next, set up your Price Points. Your site visitors will see three options when asked to sign-up for a Premium subscription. Select the 3 subscription membership periods to choose from, and the corresponding unit price for each period. For example, if you want to charge $60 for a 6 month subscription, enter $10 for the unit price.</p>
		<br/>
		<p class="subtitle" style="padding-left:0">Price Points can be changed or updated at any time by your WordPress Administrator.</p>
		<br/>
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
									if ($data['subscription_model'] == 'membership') {
										if (!empty($data['prices'][$i]) && 
											$data['prices'][$i]['Increment'] == $value['Increment'] && 
											$data['prices'][$i]['Length'] == $value['Length']
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
								<option value="<?php echo $key ?>"<?php echo $selected ?>><?php echo esc_html( $value['Label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<label for="pricing-period">Membership at</label>
					</td>
					<td>
						<?php
						$price = null;
						if ($data['subscription_model'] == 'membership') {
							$price = $data['prices'][$i]['Price'];
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
		 <p class="subtitle" style="padding-left:0; margin-top:15px;">
          	Prices Points can only be accessed and updated by WordPress Admins.  Editors do not have access to Price Points.  Authors can not access Price Points or Reporting.  Contributors can create new content, but can never publish or enable MediaPass.
          </p>
	</div>
	
	<div id="single-wrapper"<?php echo ($data['subscription_model'] == 'membership') ? ' style="display:none"' : null ?>>
		<br/>
		<table border="0" cellspacing="0" cellpadding="0" id="price-points">
			<tr>
				<td>
					<label for="price">Single Article Price</label>
				</td>
				<td>
					$<input type="text" name="price" value="<?php echo ($data['subscription_model'] == 'single') ? esc_attr( $data['prices'][0]['Price'] ) : null ?>" id="price">
				</td>
			</tr>
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
		
	</div>
	
	</form>
	<!-- Right now, you can't.
	<p>You can also create different Price Point Sets for testing purposes. Go to Price Point Tests in the Customization section to learn more.</p>
	-->
</div>
