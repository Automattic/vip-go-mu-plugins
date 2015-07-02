<?php
function tix_contextual_help() {
	$screen = get_current_screen();

	if ( $screen->id == 'tix_ticket' || $screen->id == 'edit-tix_ticket' ) {

		$screen->add_help_tab( array(
			'title' => 'Overview',
			'id' => 'tix-overview',
			'content' => '
				<h3>Tickets</h3>
				<p>This screen provides access to the tickets (or ticket types) you have created. Each ticket is has various attributes like price and quantity. The total amount of available tickets determines the maximum capacity of the event. Please note that once the ticket has been published, editing things like price or questions can break data consistency, since attendees may have already bought the ticket with the old data. Also, once a ticket has been published, please keep it published. Do not revert to draft, pending or trash.</p>
				<p>Use the <strong>Screen Options</strong> panel to show and hide the columns that matter most.</p>',
		) );

		if ( $screen->id == 'tix_ticket' )
			$screen->add_help_tab( array(
				'title' => 'Excerpt',
				'id' => 'tix-excerpt',
				'content' => "
					<h3>Excerpt</h3>
					<p>The ticket excerpt contains the description of the ticket, generally things like whether it include a t-shirt, food, and so on. This will be displayed underneath the ticket title on the ticketing page.</p>",
			) );

		$screen->add_help_tab( array(
			'title' => 'Price',
			'id' => 'tix-price',
			'content' => '
				<h3>Price</h3>
				<p>The ticket price determines how much the user should pay, to obtain the ticket. The currency is set in the CampTix plugin Setup screen. Note that when changing currencies, the existing prices will not be converted, so make sure you get this right the first time.</p>
				<p>A price of <strong>0.00</strong> means that any visitor can obtain such a ticket for free. If you want to give out free tickets to certain groups, you should visit the Coupons section.</p>
				<p>As soon as at least one ticket has been purchased, the price can no longer be changed. This is made to maintain consistency throughout CampTix reports.',
		) );

		$screen->add_help_tab( array(
			'title' => 'Quantity',
			'id' => 'tix-quantity',
			'content' => '
				<h3>Quantity</h3>
				<p>The quantity of a ticket type is the maximum amount of sales. You can increase quantity over time, but decreasing it will break data consistency. The amount of remaining tickets for every type will be shown to your visitors.</p>',
		) );

		$screen->add_help_tab( array(
			'title' => 'Availability',
			'id' => 'tix-availability',
			'content' => '
				<h3>Availability</h3>
				<p>You can create early-bird tickets, or final-call tickets based on the dates. With Availability, you can set when to start ticket sales, and when to end. Leaving fields blank will set availability to Auto, meaning they can be purchased at any time.</p>',
		) );

		if ( $screen->id == 'tix_ticket' )
			$screen->add_help_tab( array(
				'title' => 'Questions',
				'id' => 'tix-questions',
				'content' => "
					<h3>Questions</h3>
					<p>Different tickets may require different information from attendees. For example, one ticket may include a t-shirt while the other can include a wristband. A third ticket may include both. You would ask for a t-shirt size in the first ticket, and a wrist size for the second ticket. You will ask both questions in the third ticket.</p>
					<p>Questions can be of different types:</p>
					<ul>
						<li><strong>Text input</strong> - a simple textbox where the attendee can enter their website URL, Twitter name, Facebook profile, phone number, etc.</li>
						<li><strong>Dropdown select</strong> - a pull-down select box where attendees can pick one of several options, like a t-shirt size. The Values column in this case, should contain the different options, separated by a comma.</li>
						<li><strong>Checkbox</strong> - can be either one or multiple checkboxes. Useful to ask users which presentations they're interested in, whether they make money from WordPress, and so on. The Values field can be empty, can contain a single value which will be used as a label for the checkbox (e.g. 'Yes') or a comma-separated list of multiple values for multiple checkboxes.
					</ul>
					<p>To delete a question, simply remove its field and save the ticket.</p>",
			) );

		/*if ( $screen->id == 'tix_ticket' )
			$screen->add_help_tab( array(
				'title' => 'Reservations',
				'id' => 'tix-reservations',
				'content' => "
					<p>Reservations is a way to make sure that a certain group of people, can always purchase their tickets, even if you sell out fast. It's like putting a few tickets aside for your friends or co-workers. Once you create a new reservation, the reservation quantity will be held privately, and accessibly only via the secret link, which you can share with the group you made the reservation for.</p>
					<p>Note, that when you create a reservation with more quantity, than the available ticket sales, we'll bump the overall ticket quantity for you. However, when you're releasing a reservation, the quantity is not changed, and the reserved tickets are visible publicly again. Reservations can be used in conjunction with coupons.</p>",
			) );*/

	} elseif ( $screen->id == 'edit-tix_attendee' || $screen->id == 'tix_attendee' ) {

		$screen->add_help_tab( array(
			'title' => 'Overview',
			'id' => 'tix-overview',
			'content' => "
				<h3>Attendees</h3>
				<p>Attendees are people who have purchased (or attempted to purchase) a ticket for the event. A <strong>published</strong> attendee is one whose payment has been confirmed. A <strong>pending</strong> attendee is one who has paid, but the payment is not yet confirmed. A <strong>draft</strong> attendee is one who has filled out the attendee info form during ticket purchase, but never completed the purchase on PayPal. Please don't change the post status manually.</p>",
		) );

		if ( $screen->id == 'edit-tix_attendee' )
			$screen->add_help_tab( array(
				'title' => 'Searching',
				'id' => 'tix-searching',
				'content' => "
					<h3>Searching</h3>
					<p>Searching through attendees is easy, on the attendees list, in the top right corner. You can search by name, e-mail, transaction id or even by an answer to the asked questions.</p>",
			) );

		if ( $screen->id == 'tix_attendee' )
			$screen->add_help_tab( array(
				'title' => 'Attendee Information',
				'id' => 'tix-attendee-info',
				'content' => "
					<h3>Attendee Information</h3>
					<p>The Attendee Information table will show you everything you need to know about the attendee, the answers to the questions asked by their ticket, their payment status, coupon code as well as the access token, which is a secret link where they can edit their information.</p>",
			) );

		$screen->add_help_tab( array(
			'title' => 'Attendees List',
			'id' => 'tix-attendees-list',
			'content' => "
				<h3>Attendees List</h3>
				<p>You can create a list of attendees on any page by using the <code>[camptix_attendees]</code> shortcode. This will create a list of avatars, names, URLs and Twitter handles if provided by the attendees. You can style the list with CSS, each item is fairly easy to target with selectors. You can even change the number of columns by adding a <code>columns</code> attribute, for example <code>[camptix_attendees columns=&quot;2&quot;]</code>.</p>",
		) );

	} elseif ( $screen->id == 'edit-tix_coupon' || $screen->id == 'tix_coupon' ) {

		$screen->add_help_tab( array(
			'title' => 'Overview',
			'id' => 'tix-overview',
			'content' => "
				<h3>Coupons</h3>
				<p>Coupons are discount codes you can give to your attendees. The available fields are quite self-explanatory:</p>
				<ul>
					<li><strong>Title</strong> - the coupon code. This is the code people will type in to get their discount. It's not case sensitive, so Coupon will work the same as COUPON or cOuPOn.</li>
					<li><strong>Discount</strong> - can either be a fixed amount or a percentage, which will be deducted from the ticket price. If a ticket price is $10 and the discount is set to $3 or 30%, people will be able to purchase the ticket for $7.</li>
					<li><strong>Quantity</strong> - the maximum number of times this coupon can be used. Note, that if the coupon quantity is more than one, an event attendee can purchase as much tickets as the coupon quantity will allow them to. This means they are not restricted to a single coupon usage per purchaser. If you'd like a coupon to be used only once, create a coupon and set the quantity to one.</li>
					<li><strong>Applies to</strong> - check the tickets that should be discounted when this coupon code is used. Note that when you create a new ticket, it will not automatically be discounted by the saved coupons. You will have to add them explicitly.</li>
					<li><strong>Availability</strong> - similar to tickets availability, defines the date period when the coupon can be used.</li>
				</ul>
				<p>You can save the coupon as a draft at any point, but it has to be Published in order to work.</p>",
		) );

	} elseif ( $screen->id == 'ticket_page_tix_tools' ) {

		$screen->add_help_tab( array(
			'title' => 'Summarize',
			'id' => 'tix-summarize',
			'content' => "
				<h3>Summarize</h3>
				<p>Summaries is a great way to group your event attendees by any of the attributes, including all the possible ticket questions. Useful to find out which t-shirt sizes you need to order, or what type of food you need to get. You can also export summaries into CSV.</p>",
		) );

		$screen->add_help_tab( array(
			'title' => 'Revenue',
			'id' => 'tix-revenue',
			'content' => "
				<h3>Revenue</h3>
				<p>The Revenue report shows the numbers and the pricing for each ticket sold, including discounts, etc. Compare the total revenue number to the one in your PayPal reports to make sure everything is in order.</p>",
		) );

		$screen->add_help_tab( array(
			'title' => 'Export',
			'id' => 'tix-export',
			'content' => "
				<h3>Export</h3>
				<p>The Export tools helps you export all your attendee data into various formats.</p>",
		) );

		$screen->add_help_tab( array(
			'title' => 'Notify',
			'id' => 'tix-export',
			'content' => "
				<h3>Notify</h3>
				<p>The Notify section lets you send e-mails targeted at specific ticket groups. Note that the e-mails will not be sent out straight away, but rather grouped into tasks, which are carried out using a cron schedule. You can monitor the status of every e-mail job in the History section.</p>",
		) );

	} elseif ( $screen->id == 'tix_ticket_page_camptix_options' ) {

		$screen->add_help_tab( array(
			'title' => 'Configuration',
			'id' => 'tix-help-configuration',
			'content' => "
				<h3>Configuration</h3>
				<p>The basic configuration is done in the General section. The event name will appear in your outgoing emails, payment reports, etc. The currency drop-down sets the currency for all tickets. Please note that changing the currency does not convert the existing ticket prices, i.e. 10 USD will become 10 EUR. Also note that not all payment methods support all currencies, so make sure you pick the currency supported by the payment methods you're planning to use.</p>",
		) );

		$screen->add_help_tab( array(
			'title' => 'Payment',
			'id' => 'tix-help-payment',
			'content' => "
				<h3>Payment Methods</h3>
				<p>You can configure the payment methods you'd like to use in the Payment section. To enable or disable a specific payment method, set its Enabled option to Yes or No respectively. Most payment methods will come with additional configuration fields. Supported currencies will be listed next to each payment method. If you'd like to change the currency, you can do so from the General tab.</p>

				<h4>PayPal Express Checkout</h4>
				<p>To use Express Checkout you'll need to obtain your API credentials from PayPal. Please note, that these are not your PayPal e-mail and password. Read the <a href='https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECAPICredentials'>Creating an API Signature</a> for more information. If you want to test your payments before going public, please refer to the <a href='https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/howto_testing_sandbox'>PayPal Sandbox</a> guide.</p>
			",
		) );

	}
}
add_action( 'in_admin_header', 'tix_contextual_help' );