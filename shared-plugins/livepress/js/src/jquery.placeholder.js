/*
 * Placeholder handler, with support for password overlay.
 * Copyright LivePress Inc.
 * Released under the GPL 3 license.
 */

jQuery.fn.placeholder = function (def_width) {
	function placeholder_is_supported () {
		return "placeholder" in document.createElement("input");
	}

	if (!placeholder_is_supported()) {
		var insertPlaceholder = function ($element) {
			var placeholder = jQuery("<input type='text'>").addClass('password-placeholder');
			placeholder.attr('value', $element.attr('placeholder'));
			var width = $element.width();
			if (!width) {
				width = def_width;
			}
			if (!width) {
				return;
			}
			var leftMargin = -width -
				parseInt($element.css('padding-left').replace("px", ""), 10) -
				parseInt($element.css('padding-right').replace("px", ""), 10) -
				parseInt($element.css('border-right-width').replace("px", ""), 10) -
				parseInt($element.css('border-left-width').replace("px", ""), 10);
			if (parseInt(jQuery.browser.version, 10) === 7 && jQuery.browser.msie) {
				width = $element.width() -
					parseInt($element.css('border-right-width').replace("px", ""), 10) -
					parseInt($element.css('border-left-width').replace("px", ""), 10) - 1;
			}
			placeholder.css({
				'width':      width,
				'margin-left':leftMargin
			});
			placeholder.bind('focus', function () {
				var $this = jQuery(this);
				var val = $this.val();
				$this.val('');
				$this.val(val);
				var $input = $this.prev();
				$this.hide();
				$input.trigger('focus');
			});
			$element.after(placeholder);
		};

		jQuery('form').bind('submit', function () {
			jQuery('input').each(function () {
				var $i = jQuery(this);
				if ($i.attr('value') === $i.attr('placeholder')) {
					$i.attr('value', "");
				}
				if ($i.hasClass('password-placeholder')) {
					$i.remove();
				}
			});
		});

		jQuery('input').each(function () {
			var $i = jQuery(this);
			if (typeof($i.attr('placeholder')) !== "undefined" && $i.attr('placeholder') !== "") {
				if (jQuery.trim($i.attr('value')) === "" || jQuery.trim($i.attr('value')) === $i.attr('placeholder')) {
					if ($i.attr('type') === "password") {
						insertPlaceholder($i);
						$i.css({visibility:'hidden'});
					}
					$i.attr('value', $i.attr('placeholder'));
					$i.css('color', '#BBB');
				}

				$i.bind('focus', function () {
					if ($i.attr('value') === $i.attr('placeholder')) {
						$i.attr('value', "");
						$i.css({visibility:'visible'});
						$i.css('color', 'black');
					}
				});

				$i.bind('blur', function () {
					if (jQuery.trim($i.attr('value')) === "") {
						$i.css('color', '#BBB');
						$i.attr('value', $i.attr('placeholder'));
						if ($i.parent().find('.password-placeholder').length === 0) {
							if ($i.attr('type') === "password") {
								insertPlaceholder($i);
							}
						}
						if ($i.next().is('.password-placeholder')) {
							$i.css({visibility:'hidden'});
							$i.parent().find('.password-placeholder').show();
						}
					} else {
						if ($i.next().is('.password-placeholder')) {
							$i.next().remove();
						}
					}
				});
			}
		});
	}
};