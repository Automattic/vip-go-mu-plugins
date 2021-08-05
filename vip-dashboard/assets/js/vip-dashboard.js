jQuery(document).ready(function ($) {

    var message_container = $('#vip_dashboard_message_container');
    var contact_form = $('#vip_dashboard_contact_form');
    var contact_form_submit = $('#vip_contact_form_submit');

    function renderVIPContactFormMessage(status, message) {
        message_container.empty();
        var content = "<div class='contact-form__" + status + "'>" + message + "</div>";
        message_container.html(content);
    }

    contact_form.submit(function (e) {
        e.preventDefault();

        contact_form_submit.prop('disabled', true);

        var data = {
            name: $('#contact-form__name').val(),
            email: $('#contact-form__email').val(),
            subject: $('#contact-form__subject').val(),
            type: $('#contact-form__type').val(),
            body: $('#contact-form__details').val(),
            priority: $('#contact-form__priority').val(),
            cc: $('#contact-form__cc').val(),
            action: 'vip_contact'
        };

        jQuery.ajax({
            type: 'POST',
            url: contact_form.prop('action'),
            data: data,
            success: function (data, textStatus) {
                if (textStatus === 'success') {
                    var result = jQuery.parseJSON(data);
                    renderVIPContactFormMessage(result.status, result.message);

                    if ( result.status === 'success' ) {
                        contact_form.reset();
                    }
                } else {
                    renderVIPContactFormMessage('error', 'Your message could not be sent, please try again.');
                }
                contact_form_submit.prop('disabled', false);
            }.bind(this)
        });
    });
});