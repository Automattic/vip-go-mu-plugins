<?php

function render_vip_dashboard_widget_contact() {
	$ajaxurl      = add_query_arg( array( '_wpnonce' => wp_create_nonce( 'vip-dashboard' ) ), untrailingslashit( admin_url( 'admin-ajax.php' ) ) );
	$current_user = wp_get_current_user();

	?>

	<div class="widget">
		<h2 class="widget__title">Contact WordPress VIP Support</h2>

		<div id="vip_dashboard_message_container"></div>

		<form id="vip_dashboard_contact_form" action="<?php echo esc_url( $ajaxurl ); ?>" class="widget__contact-form"
			method="get">
			<div class="contact-form__row">
				<div class="contact-form__label">
					<label for="contact-form__name">Name</label>
				</div>
				<div class="contact-form__input">
					<input type="text" value="<?php echo esc_attr( $current_user->display_name ); ?>"
						id="contact-form__name" placeholder="First and last name"/>
				</div>
			</div>
			<div class="contact-form__row">
				<div class="contact-form__label">
					<label for="contact-form__email">Email</label>
				</div>
				<div class="contact-form__input">
					<input type="text" value="<?php echo esc_attr( $current_user->user_email ); ?>"
						id="contact-form__email" placeholder="Email address"/>
				</div>
			</div>
			<div class="contact-form__row">
				<div class="contact-form__label">
					<label for="contact-form__subject">Subject</label>
				</div>
				<div class="contact-form__input">
					<input type="text" id="contact-form__subject" placeholder="Ticket name"/>
				</div>
			</div>
			<div class="contact-form__row">
				<div class="contact-form__label">
					<label for="contact-form__details">Details</label>
				</div>
				<div class="contact-form__input">
					<textarea name="details" rows="4" id="contact-form__details"
							placeholder="Please be descriptive"></textarea>
				</div>
			</div>
			<div class="contact-form__row">
				<div class="contact-form__label">
					<label for="contact-form__priority">Priority</label>
				</div>
				<div class="contact-form__input">
					<select id="contact-form__priority">
						<optgroup label="Normal Priority">
							<option value="Low">Low</option>
							<option value="Medium">Normal</option>
							<option value="High">High</option>
						</optgroup>
						<optgroup label="Urgent Priority">
							<option value="Emergency">Emergency (Outage, Security, Revert, etc...)</option>
						</optgroup>
					</select>
				</div>
			</div>
			<div class="contact-form__row">
				<div class="contact-form__label">
					<label for="contact-form__cc">CC:</label>
				</div>
				<div class="contact-form__input">
					<input type="text" id="contact-form__cc" placeholder="Comma separated email addresses"/>
				</div>
			</div>
			<div class="contact-form__row submit-button">
				<div class="contact-form__label">
					<label></label>
				</div>
				<div class="contact-form__input">
					<input id="vip_contact_form_submit" type="submit" value="Send Request">
				</div>
			</div>
		</form>
	</div>

	<?php
}
