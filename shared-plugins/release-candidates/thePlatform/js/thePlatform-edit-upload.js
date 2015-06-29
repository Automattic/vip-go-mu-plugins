/* thePlatform Video Manager Wordpress Plugin
 Copyright (C) 2013-2015 thePlatform, LLC

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */

var theplatform_edit = (function ($) {

    var Validation = {
        /**
         * Validate Media data is valid before submitting upload/edit
         * @param  {Object} event click event
         * @return {boolean}       Did validation pass or not
         */
        validateMedia: function () {

            //TODO: Validate that file has been selected for upload but not edit
            var validationError = false;

            $('.upload_field, .custom_field').each(function () {
                var $field = $(this);
                var dataStructure = $field.data('structure');
                var dataType = $field.data('type');
                var value = $(this).val();
                var fieldError = false;
                // Detect HTML, this runs against all fields regardless of type/structure
                if (value.match(/<(\w+)((?:\s+\w+(?:\s*=\s*(?:(?:"[^"]*")|(?:'[^']*')|[^>\s]+))?)*)\s*(\/?)>/)) {
                    validationError = true;
                }
                // We're not requiring any fields at the moment,
                // so only test fields which have a value
                else if (value.length > 0) {
                    switch (dataStructure) {
                        case 'Map':
                            var values = value.indexOf(',') ? value.split(',') : [value];
                            for (var i = 0; i < values.length; i++) {
                                // Use substring to break apart to avoid issues with values that use colons
                                var index = values[i].indexOf(':');
                                var key = values[i].substr(0, index).trim();
                                var val = values[i].substr(index + 1).trim();
                                if (index === -1 || key.length === 0 || val.length === 0 || Validation.validateFormat(val, dataType)) {
                                    fieldError = true;
                                    break;
                                }
                            }
                            break;
                        case 'List':
                            var values = value.indexOf(',') ? value.split(',') : [value];
                            for (var i = 0; i < values.length; i++) {
                                if (Validation.validateFormat(values[i].trim(), dataType)) {
                                    fieldError = true;
                                    break;
                                }
                            }
                            break;
                        default:
                            if (Validation.validateFormat(value, dataType)) {
                                fieldError = true;
                            }
                            break;
                    }
                }
                if (fieldError) {
                    $field.parent().addClass('has-error');
                    validationError = fieldError;
                } else {
                    $field.parent().removeClass('has-error');
                }
            });

            var $titleField = $('#theplatform_upload_title');
            if ($titleField.val() === "") {
                validationError = true;
                $titleField.parent().addClass('has-error');
            } else {
                $titleField.parent().removeClass('has-error');
            }

            return validationError;
        },

        validateFormat: function (value, dataType) {
            var validationError = false;

            switch (dataType) {
                case 'Integer':
                    var intRegex = /^-?\d+$/;
                    validationError = !intRegex.test(value);
                    break;
                case 'Decimal':
                    var decRegex = /^-?(\d+)?(\.[\d]+)?$/;
                    validationError = !decRegex.test(value);
                    break;
                case 'Boolean':
                    var validValues = ['true', 'false', ''];
                    validationError = validValues.indexOf(value) < 0;
                    break;
                case 'URI':
                    var uriRegex = /^([a-z][a-z0-9+.-]*):(?:\/\/((?:(?=((?:[a-z0-9-._~!$&'()*+,;=:]|%[0-9A-F]{2})*))(\3)@)?(?=(\[[0-9A-F:.]{2,}\]|(?:[a-z0-9-._~!$&'()*+,;=]|%[0-9A-F]{2})*))\5(?::(?=(\d*))\6)?)(\/(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9A-F]{2})*))\8)?|(\/?(?!\/)(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/]|%[0-9A-F]{2})*))\10)?)(?:\?(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/?]|%[0-9A-F]{2})*))\11)?(?:#(?=((?:[a-z0-9-._~!$&'()*+,;=:@\/?]|%[0-9A-F]{2})*))\12)?$/i;
                    validationError = !uriRegex.test(value);
                    break;
                case 'Time':
                    var timeRegex = /^\d{1,2}:\d{2}$/;
                    validationError = !timeRegex.test(value);
                    break;
                case 'Duration':
                    var durationRegex = /^(\d+:)?([0-5]?[0-9]:)?([0-5]?[0-9])?$/;
                    validationError = !durationRegex.test(value);
                    break;
                case 'DateTime':
                    validationError = !_.isDate(new Date(value));
                    break;
                case 'Date':
                    var dateRegex = /^(\d{4})-(([0][1-9])|([1][0-2]))-(([0][1-9])|([1][0-9])|([2][0-9])|([3][0-1]))$/;
                    validationError = !dateRegex.test(value);
                    break;
                case 'Link':
                    // @todo: this could do more, right now just checks that the structure is correct
                    var linkRegex = /^(((title:)(.*),(\s+)?(href:).*)|((href:)(.*),(\s+)?(title:).*))$/;
                    validationError = !linkRegex.test(value);
                    break;
                default:
                    // nothing to do
                    break;
            }

            return validationError;
        }
    };

    var Data = {
        parseMediaParams: function () {
            var params = {};
            $('.upload_field').each(function () {
                if ($(this).val().length !== 0)
                    params[$(this).attr('name')] = $(this).val();
            });

            var categories = [];
            var categoryArray = $('.category_field').val();
            for (var i in categoryArray) {
                var name = categoryArray[i];
                if (name != '(None)') {
                    var category = {};
                    category.name = name;
                    categories.push(category);
                }
            }

            params.categories = categories;

            return params;
        },

        parseCustomParams: function () {
            var custom_params = {};

            $('.custom_field').each(function () {
                if ($(this).val().length !== 0) {
                    var $field = $(this);
                    var dataStructure = $field.data('structure');
                    var dataType = $field.data('type');
                    var value = $field.val();

                    // Convert maps to object
                    if (dataStructure == 'Map') {
                        var values = value.indexOf(',') ? value.split(',') : [value];
                        value = {};
                        for (var i = 0; i < values.length; i++) {
                            // Use substring to break apart to avoid issues with values that use colons
                            var index = values[i].indexOf(':');
                            var key = values[i].substr(0, index).trim();
                            var val = values[i].substr(index + 1).trim();
                            value[key] = Data.parseDataType(val, dataType);
                        }
                    }
                    // Convert lists to array
                    else if (dataStructure == 'List') {
                        var values = value.indexOf(',') ? value.split(',') : [value];
                        value = [];
                        for (var i = 0; i < values.length; i++) {
                            value.push(Data.parseDataType(values[i].trim(), dataType));
                        }
                    } else {
                        value = Data.parseDataType(value, dataType);
                    }

                    custom_params[$(this).attr('name')] = value;
                }

            });

            return custom_params;
        },

        parseDataType: function (value, dataType) {
            switch (dataType) {
                case 'Link':
                    var titleRegex = /title[\s+]?:[\s+]?([^,]+)/;
                    var hrefRegex = /href[\s+]?:[\s+]?([^,]+)/;
                    var title = titleRegex.exec(value)[1];
                    var href = hrefRegex.exec(value)[1];
                    value = {
                        href: href,
                        title: title
                    };
                    break;
            }
            return value;
        }
    };

    var UI = {
        onSuccess: function (response, button) {
            if (button.id === "btn-generate-thumbnail") {
                theplatform_browser.notifyUser();
            } else {
                $('.tab-pane.active .error').remove();
            }

            if (response.success && !_.has(response.data, 'isException')) {
                $(button).text('Success').val('Success').removeClass('button-success button-danger button-info').addClass('button-success');
            } else {
                $(button).text('Failed').val('Failed').removeClass('button-success button-danger button-info').addClass('button-danger');

                if (button.id === "btn-generate-thumbnail") {
                    theplatform_browser.notifyUser(response.data.description);
                } else {
                    var errorSource = $("#error-template").html();
                    var errorTemplate = _.template(errorSource);
                    var error = errorTemplate({
                        message: response.data.description
                    });

                    $('.tab-pane.active').prepend(error.trim());
                }
            }
        },

        onComplete: function (button, value, type) {
            setTimeout(function () {
                if (_.isEmpty(type)) {
                    type = "primary";
                }
                $(button).text(value).val(value).removeClass('button-success button-danger button-info').addClass('button-' + type);
            }, 1500);
        },

        updatePublishProfiles: function (mediaId) {
            API.getProfileResults(mediaId, function (data) {
                var revokeDropdown = $('#publish_status');
                revokeDropdown.empty();
                var publishDropdown = $('#edit_publishing_profile');

                for (var i = 0; i < data.length; i++) {
                    if (data[i].status == 'Processed') {
                        var option = document.createElement('option');
                        option.value = data[i].profileId;
                        option.text = publishDropdown.find('option[value="' + data[i].profileId + '"]').text();

                        // Don't show profiles that are disabled
                        if (!_.isEmpty(option.text)) {
                            revokeDropdown.append(option);
                        }

                    }
                }
                if (revokeDropdown.children().length === 0) {
                    revokeDropdown.attr('disabled', 'true');
                } else {
                    revokeDropdown.removeAttr('disabled');
                }
            });
        }
    };

    var Events = {
        onEditMetadata: function (event) {
            var me = this;
            var validationError = Validation.validateMedia(event);
            if (validationError)
                return false;
            var params = Data.parseMediaParams();
            var custom_params = Data.parseCustomParams();
            params.id = tpHelper.mediaId;

            $(this).text('Updating').removeClass('button-success button-danger button-info').addClass('button-info');

            var data = {
                _wpnonce: tp_edit_upload_local.tp_nonce.theplatform_edit,
                action: 'theplatform_edit',
                params: JSON.stringify(params),
                custom_params: JSON.stringify(custom_params)
            };

            $.ajax({
                url: tp_edit_upload_local.ajaxurl,
                data: data,
                method: 'post',
                success: function (response) {
                    UI.onSuccess(response, me);
                    if (response.success === true) {
                        $('#tp-edit-dialog').data('refresh', 'true');
                        theplatform_browser.updateMediaObject(tpHelper.mediaId);
                    }
                },
                complete: function () {
                    UI.onComplete(me, "Submit");
                }
            });
        },
        onUploadMedia: function (event) {
            var files = document.getElementById('theplatform_upload_file').files;

            var validationError = Validation.validateMedia(event);

            if (files[0] === undefined) {
                $('#file-tp-form-group').addClass('has-error');
            } else {
                $('#file-tp-form-group').removeClass('has-error');
            }

            if (validationError || files[0] === undefined)
                return false;

            var params = Data.parseMediaParams();
            var custom_params = Data.parseCustomParams();

            var profile = $('.upload_profile');
            var server = $('.server_id');

            var upload_window = window.open(tp_edit_upload_local.uploader_window_url, '_blank', 'menubar=no,location=no,resizable=no,scrollbars=no,status=no,width=700,height=180');

            var filesArray = [];

            for (var i = 0; i < files.length; i++) {
                filesArray.push(files[i]);
            }
            var uploaderData = {
                files: filesArray,
                params: JSON.stringify(params),
                custom_params: JSON.stringify(custom_params),
                profile: profile.val(),
                server: server.val(),
                source: 'theplatform_upload_data'
            };

            window.onmessage = function (e) {
                if (e.data == 'theplatform_uploader_ready') {
                    upload_window.postMessage(uploaderData, '*');
                }
            };
        },
        onAddFiles: function () {
            var files = document.getElementById('theplatform_upload_file').files;

            if (files[0] === undefined) {
                $('#file-tp-form-group').addClass('has-error');
                return false;
            } else {
                $('#file-tp-form-group').removeClass('has-error');
            }

            var profile = $('.upload_profile');
            var server = $('.server_id');

            var params = {
                id: tpHelper.mediaId
            };

            var upload_window = window.open(tp_edit_upload_local.uploader_window_url, '_blank', 'menubar=no,location=no,resizable=no,scrollbars=no,status=no,width=700,height=180');
            var filesArray = [];

            for (var i = 0; i < files.length; i++) {
                filesArray.push(files[i]);
            }
            var uploaderData = {
                files: filesArray,
                params: JSON.stringify(params),
                custom_params: JSON.stringify(''),
                profile: profile.val(),
                server: server.val(),
                source: 'theplatform_upload_data'
            };

            window.onmessage = function (e) {
                if (e.data == 'theplatform_uploader_ready') {
                    upload_window.postMessage(uploaderData, '*');
                }
            };
        },
        onPublishMedia: function () {
            var profile = $('.edit_profile').val();
            if (profile === 'tp_wp_none')
                return false;

            var me = this;
            $(this).text('Publishing').removeClass('button-primary button-success button-danger button-info').addClass('button-info');

            var params = {
                mediaId: tpHelper.mediaId,
                account: tpHelper.account,
                profile: profile,
                action: 'publish_media',
                _wpnonce: tp_edit_upload_local.tp_nonce.theplatform_publish
            };

            jQuery.ajax({
                url: tp_edit_upload_local.ajaxurl,
                data: params,
                type: "POST",
                success: function (response) {
                    UI.onSuccess(response, me);
                },
                complete: function () {
                    UI.onComplete(me, "Publish");
                }
            });
        },
        onRevokeMedia: function () {

            var profile = jQuery('.revoke_profile option:selected');

            if (profile.length === 0)
                return false;

            var me = this;
            $(this).text('Revoking').removeClass('button-primary button-success button-danger button-info').addClass('button-info');

            var params = {
                mediaId: tpHelper.mediaId,
                account: tpHelper.account,
                profile: profile.val(),
                action: 'revoke_media',
                _wpnonce: tp_edit_upload_local.tp_nonce.theplatform_revoke
            };

            $.ajax({
                url: tp_edit_upload_local.ajaxurl,
                data: params,
                type: "POST",
                success: function (response) {
                    UI.onSuccess(response, me);
                },
                complete: function () {
                    UI.onComplete(me, "Revoke");
                    setTimeout(function () {
                        UI.updatePublishProfiles(tpHelper.mediaId);
                    }, 1500);
                }
            });
        },
        onChangeFile: function () {
            var input = $(this),
                numFiles = input.get(0).files ? input.get(0).files.length : 1,
                label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
            input.trigger('fileselect', [numFiles, label]);
        },
        onFileSelect: function (event, numFiles, label) {
            var input = $(this).parents('.input-group').find(':text'),
                log = numFiles > 1 ? numFiles + ' files selected' : label;

            if (input.length) {
                input.val(log);
            }
        },
        onRevokeTabOpened: function () {
            UI.updatePublishProfiles(tpHelper.mediaId);
        },
        onTabClick: function (e) {
            e.preventDefault();

            $(".nav-tab-active").removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            var tabId = $(this).attr('href');
            $(".tab-pane.active").removeClass('active');
            $(tabId).addClass('active');
        }
    };

    var API = {
        getProfileResults: function (mediaId, callback) {
            var data = {
                _wpnonce: tp_browser_local.tp_nonce.get_profile_results,
                action: 'get_profile_results',
                mediaId: mediaId
            };

            $.post(tp_browser_local.ajaxurl, data, function (resp) {
                if (resp.success) {
                    callback(resp.data);
                } else {
                    console.log(resp);
                }

            });
        }
    };

    $(document).ready(function () {
        // Handle the custom file browser button
        $('.button-file :file').on('fileselect', Events.onFileSelect).on('change', Events.onChangeFile);
        $("#theplatform_edit_button").click(Events.onEditMetadata);
        $("#theplatform_upload_button").click(Events.onUploadMedia);
        $("#theplatform_add_file_button").click(Events.onAddFiles);
        $("#theplatform_publish_button").click(Events.onPublishMedia);
        $("#theplatform_revoke_button").click(Events.onRevokeMedia);
        $(".nav-tab #revoke").click(Events.onRevokeTabOpened);
        $(".nav-tab").click(Events.onTabClick);
    });

    return {
        updatePublishProfiles: UI.updatePublishProfiles,
        onSuccess: UI.onSuccess,
        onComplete: UI.onComplete
    };
})(jQuery);
