/*global lp_strings, Livepress, console, LivepressConfig */
/**
 * Object containing methods pertaining to instance message integration.
 *
 * @namespace
 * @type {Object}
 */
var ImIntegration = {
	CHECK_TIMEOUT_SECONDS: 5,
	CHECK_TIMES:           5
};

/**
 * Check the status of the specified protocol
 *
 * @memberOf ImIntegration
 * @param {String} protocol Protocol to check.
 * @see ImIntegration.__check_status
 */
ImIntegration.check_status = function (protocol) {
	ImIntegration.__check_status(protocol, ImIntegration.CHECK_TIMES);
};

/**
 * Create HTML markup for a loading spinnter.
 *
 * @memberOf ImIntegration
 * @return {Object} jQuery object containing the spinner.
 * @private
 */
ImIntegration.__spin_loading = function () {
	var image_path = LivepressConfig.lp_plugin_url + '/img/spin.gif',
		html_image = jQuery("<img />").attr('src', image_path);

	console.log("created spin: " + html_image.html());
	console.log("image_path  : " + image_path);
	return html_image;
};

/**
 * Check the status of a specific protocol several times.
 *
 * @memberOf ImIntegration
 * @param {String} protocol Protocol to check.
 * @param {number} tries Number of times to test the protocol.
 * @private
 */
ImIntegration.__check_status = function (protocol, tries) {
	var params = {},
		$check_button = jQuery("#check_" + protocol),
		$check_message = jQuery("#check_message_" + protocol),
		admin_ajax_url = LivepressConfig.site_url + '/wp-admin/admin-ajax.php';

	params.action = "lp_im_integration";
	params._ajax_nonce = LivepressConfig.ajax_lp_im_integration;
	params.im_integration_check_status = true;
	params.im_service = protocol;

	console.log("Start status check to: protocol=" + protocol);

	$check_button.hide();

	$check_message.css({ 'color': 'black' }).html(ImIntegration.__spin_loading());

	jQuery.post(admin_ajax_url, params, function (response, code) {
		var json_response = JSON.parse(response),
			show_button = false,
			error_msg = "",
			reason;

		if ((json_response.status === 'not_found' ||
			json_response.status === 'offline' ||
			json_response.status === 'failed') && tries > 0) {
			//checked_str = ((LivePress_IM_Integration.CHECK_TIMES + 1) - tries) + "/" + LivePress_IM_Integration.CHECK_TIMES;
			setTimeout(function () {
				ImIntegration.__check_status(params.im_service, tries - 1);
			}, ImIntegration.CHECK_TIMEOUT_SECONDS * 1000);
		} else if (json_response.status === 'not_found') {
			show_button = true;
			$check_message.html( lp_strings.account_not_found ).css({'color':'red'});
		} else if (json_response.status === 'connecting') {
			setTimeout(function () {
				ImIntegration.__check_status(params.im_service, 5);
			}, ImIntegration.CHECK_TIMEOUT_SECONDS * 1000);
			$check_message.html( lp_strings.connecting ).css({'color':'lightgreen'});
		} else if (json_response.status === 'offline') {
			$check_message.html( lp_strings.offline );
		} else if (json_response.status === 'online') {
			$check_message.html( lp_strings.connected ).css({'color':'green'});
		} else if (json_response.status === 'failed') {
			show_button = true;
			reason = json_response.reason;

			if (reason === 'authentication_error') {
				error_msg = lp_strings.user_pass_invalid;
			} else if (reason === "wrong_jid") {
				error_msg = lp_strings.wrong_account_name;
			} else {
				console.log("Im check failure reason: ", reason);
				error_msg = lp_strings.internal_error;
			}

			$check_message.html(error_msg).css({'color':'red'});
		} else {
			show_button = true;
			$check_message.html( lp_strings.unknown_error ).css({'color':'red'});
		}

		if (show_button) {
			$check_button.show();
		}
	});

};

/**
 * Current status of the test message.
 *
 * @memberOf ImIntegration
 * @type {Boolean}
 */
ImIntegration.test_message_sending = false;

/**
 * Send a test message from a given user via a specified protocol.
 *
 * @memberOf ImIntegration
 * @param {String} source Source of the message.
 * @param {String} protocol Protocol to use while sending the message.
 * @see ImIntegration.test_message_sending
 */
ImIntegration.send_test_message = function (source, protocol) {
	var $input = jQuery("#" + source),
		buddy = $input.attr('value'),
		$button,
		$feedback_msg,
		params,
		feedback_msg = "",
		self = this;

	if (buddy.length === 0) {
		return;
	}
	if (this.test_message_sending) {
		return;
	}
	this.test_message_sending = true;
	$input.attr('readOnly', true);

	$button = jQuery("#" + source + "_test_button");
	$button.attr('value', lp_strings.sending + "...");
	$button.attr("disabled", true);

	$feedback_msg = jQuery("#" + protocol + "_message");
	$feedback_msg.html("");

	params = {};
	params.action = 'im_integration';
	params.im_integration_test_message = true;
	params.im_service = protocol;
	params.buddy = buddy;

	console.log("Sending test message to: " + buddy + " using " + protocol + " protocol");

	jQuery.ajax({
		url:      LivepressConfig.ajax_url,
		type:     'post',
		dataType: 'json',
		data:     params,

		error: function (request) {
			feedback_msg = lp_strings.problem_connecting;
		},

		success: function (data) {
			console.log("return from test message: %d", data);
			if (data === 200) {
				feedback_msg = lp_strings.test_msg_sent;
			} else {
				feedback_msg = lp_strings.test_msg_failure;
			}
		},

		complete: function (XMLHttpRequest, textStatus) {
			console.log("feed: %s", feedback_msg);
			$feedback_msg.html(feedback_msg);

			self.test_message_sending = false;
			$input.attr('readOnly', false);

			$button.attr('value', lp_strings.send_again );
			$button.attr("disabled", false);
		}
	});
};