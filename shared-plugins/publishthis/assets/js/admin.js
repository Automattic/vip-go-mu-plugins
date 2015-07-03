(function($, pt, w) {
	'use strict';

	// Ready
	$(function() {
		$('#submitdiv h3 span').text('Actions');
		$('#submitdiv #publish').val('Save Changes');

		$('#publishthis-feed-template-field').live('change', function() {
							var $this = $(this);

							var options = '', 
								sections = pt.templateSections, 
								selected = '', 
								templateId = $this.find('option:selected').val(), 
								currentSectionId = $('#publishthis-template-section-field').data('template-section');

							for ( var i in sections[templateId]) {
								var section = sections[templateId][i];
								selected = (section.sectionId === currentSectionId) ? ' selected="selected"' : '';
								options += '<option value="'
										+ section.sectionId + '"' + selected
										+ '>' + section.displayName
										+ '</option>';
							}
							$('#publishthis-template-section-field').html(options);

							// Fields
							var $publishthisCategoryField = $('#publishthis-category-field'), 
								fields = pt.templateFields, 
								currentField = $publishthisCategoryField.data('current'), 
								fieldOptions = '<option value="0">Do Not Categorize</option>';

							for ( var j in fields[templateId]) {
								var field = fields[templateId][j];
								var selectedField = (field.shortCode === currentField) ? ' selected="selected"'	: '';
								fieldOptions += '<option value="'
										+ field.shortCode + '"' + selectedField
										+ '>' + field.displayName + '</option>';
							}

							$publishthisCategoryField.html(fieldOptions);

						});
		$('#publishthis-feed-template-field').change();

	});
})(window.jQuery, window.Publishthis, window);
