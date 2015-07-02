(function($) {

	$(document).ready(function()
	{
		codepressShortcodes();
	});

	/**
	 * description
	 *
	 */
	function codepressShortcodes()
	{
		// Insert shortcode
		$('#cpsh .insert-shortcode').live('click', function(event) {

			var shortcode = $(this).attr('rel');

			window.send_to_editor(shortcode);

			// Prevent default action
			event.preventDefault();
			return false;
		});
	}

})(jQuery);