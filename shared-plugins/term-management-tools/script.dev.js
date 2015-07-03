jQuery(function($) {
	var actions = [];

	$.each(tmtL10n, function(key, title) {
		actions.unshift({
			action: 'bulk_' + key,
			name: title,
			el: $('#tmt-input-' + key)
		});
	});

	$('.actions select')
		.each(function() {
			var $option = $(this).find('option:first');

			$.each(actions, function(i, actionObj) {
				$option.after( $('<option>', {value: actionObj.action, html: actionObj.name}) );
			});
		})
		.change(function() {
			var $select = $(this);

			$.each(actions, function(i, actionObj) {
				if ( $select.val() === actionObj.action ) {
					actionObj.el
						.insertAfter( $select )
						.css('display', 'inline')
						.find(':input').focus();
				} else {
					actionObj.el
						.css('display', 'none');
				}
			});
		});
});
