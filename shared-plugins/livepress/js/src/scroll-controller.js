/*global Livepress */
/**
 * Global object that controls scrolling not directly requested by user.
 * If focus is on an element that has the class lp-no-scroll scroll will
 * be disabled.
 */
(function () {
	var Scroller = function () {
		var temporary_disabled = false;

		this.settings_enabled = false;

		jQuery('input:text, textarea')
			.on('focusin', function (e) {
				temporary_disabled = true;
			})
			.on('focusout', function (e) {
				temporary_disabled = false;
			});

		this.shouldScroll = function () {
			return this.settings_enabled && !temporary_disabled;
		};
	};

	Livepress.Scroll = new Scroller();
}());