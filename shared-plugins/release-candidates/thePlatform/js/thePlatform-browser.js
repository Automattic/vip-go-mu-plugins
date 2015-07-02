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

var theplatform_browser = (function ($) {
    /**
     * UI Methods
     * @type {Object}
     */
    var UI = {
        /**
         * Refresh the infinite scrolling media list based on the selected category and search options
         * @return {void}
         */
        refreshView: function () {
            UI.notifyUser(); //clear alert box.
            UI.updateContentPane({
                title: ''
            });

            //TODO: If sorting clear search?
            var queryObject = {
                search: $('#input-search').val(),
                category: $('#selectpick-categories').val(),
                sort: $('#selectpick-sort').val(),
                order: $('#selectpick-order').val(),
                myContent: $('#my-content-cb').prop('checked')
            };

            tpHelper.queryParams = queryObject;
            tpHelper.queryString = API.buildMediaQuery(queryObject);
            tpHelper.currentPage = 1;
            Events.onGetMedia(tpHelper.currentPage);
        },

        notifyUser: function (message) {
            jQuery('div.error').remove();

            if (message === undefined) {
                return;
            }

            var errorSource = $("#error-template").html();
            var errorTemplate = _.template(errorSource);
            var error = errorTemplate({
                message: message
            });
            jQuery('#poststuff').prepend(error);
        },

        updateContentPane: function (mediaItem) {
            if (mediaItem.title === '') {
                $('#info-container').css('visibility', 'hidden');
                $('.tpPlayer').css('visibility', 'hidden');
            } else {
                $('#info-container').css('visibility', 'visible');
            }

            var $fields = $('.tp-field');

            $fields.each(function (index, field) {
                var $field = $(field);
                var prefix = $field.data('prefix');
                var dataType = $field.data('type');
                var dataStructure = $field.data('structure');

                var name = $field.data('name');
                var fullName = name;
                if (prefix !== undefined)
                    fullName = prefix + '$' + name;
                var value = mediaItem[fullName];

                if (name == 'id' && value !== undefined) {
                    value = value.substring(value.lastIndexOf('/') + 1);
                }

                // Update the right content pane
                if (name === 'categories') {
                    var catArray = mediaItem.categories || [];
                    var catList = '';
                    for (i = 0; i < catArray.length; i++) {
                        if (catList.length > 0)
                            catList += ', ';
                        catList += catArray[i].name;
                    }
                    value = catList;
                } else if (!_.isEmpty(value)) {
                    if (dataStructure == 'List' || dataStructure == 'Map') {
                        var valString = '';
                        // Lists
                        if (dataStructure == 'List') {
                            for (var i = 0; i < value.length; i++) {
                                valString += Formatting.formatValue(value[i], dataType) + ', ';
                            }
                        }
                        // Maps
                        else {
                            for (var propName in value) {
                                if (value.hasOwnProperty(propName))
                                    valString += propName + ': ' + Formatting.formatValue(value[propName], dataType) + ', ';
                            }
                        }
                        // Remove the last comma
                        if (valString.length)
                            value = valString.substr(0, valString.length - 2);
                        else
                            value = '';
                    } else {
                        value = Formatting.formatValue(value, dataType);
                    }
                }

                $('#media-' + name).html(value || '');

                // Update content on the hidden Edit dialog
                var upload_field = $('#theplatform_upload_' + fullName.replace('$', "\\$"));
                if (!upload_field.hasClass('userid')) {
                    upload_field.val(value || '');
                }
            });
        },

        addMediaObject: function (media) {
            var placeHolder = "";
            if (media.defaultThumbnailUrl === "")
                placeHolder = "holder.js/128x72/text:No Thumbnail";

            var mediaSource = $("#media-template").html();
            var mediaTemplate = _.template(mediaSource);

            var newMedia = mediaTemplate({
                id: media.id,
                guid: media.guid,
                pid: media.pid,
                placeHolder: placeHolder,
                defaultThumbnailUrl: media.defaultThumbnailUrl,
                title: media.title,
                description: media.description
            });

            newMedia = $(newMedia);
            newMedia.data('guid', media.guid);
            newMedia.data('pid', media.pid);
            newMedia.data('media', media);
            newMedia.data('id', media.id);
            var previewUrl = Formatting.extractVideoUrlfromMedia(media);

            // For the Embed dialog, we don't media without a release in the list
            // TODO: Consider changing this because of OneURL
            if (previewUrl.length === 0 && tpHelper.isEmbed == "1") {
                return;
            }

            newMedia.data('release', previewUrl.pop());

            // Update or add new media to the UI
            var existingMedia = document.getElementById(media.id);
            if (existingMedia !== null) {
                $(existingMedia).replaceWith(newMedia);
            } else {
                $('#media-list').append(newMedia);
            }

            newMedia.on('click', Events.onClickMedia);

            //Select the first one on the page.
            if ($('#media-list').children().length < 2)
                $('.tp-media', '#media-list').click();
        },
        updateMediaObject: function (mediaId) {
            API.getVideoById(mediaId, function (media) {
                if (_.has(media, 'id'))
                    UI.addMediaObject(media);
                UI.updateContentPane(media);
                Holder.run();
            });
        }
    };

    /**
     * Event Handlers
     * @type {Object}
     */
    var Events = {
        onClickMedia: function () {
            UI.updateContentPane($(this).data('media'));
            $('.tp-media.selected').removeClass('selected');
            $(this).addClass('selected');

            if (tpHelper.mediaEmbedType == 'pid') {
                tpHelper.currentRelease = 'media/' + $(this).data('pid');
            } else if (tpHelper.mediaEmbedType == 'guid') {
                var accountId = tpHelper.account.substring(tpHelper.account.lastIndexOf('/') + 1);
                tpHelper.currentRelease = 'media/guid/' + accountId + '/' + encodeURIComponent($(this).data('guid'));
            } else {
                tpHelper.currentRelease = $(this).data('release');
            }

            tpHelper.mediaId = $(this).data('id');
            tpHelper.selectedThumb = $(this).data('media').defaultThumbnailUrl;
            $pdk.controller.resetPlayer();
            if ($(this).data('release') !== undefined) {
                $('#modal-player-placeholder').css('visibility', 'hideen');
                $('.tpPlayer').css('visibility', 'visible');
                $pdk.controller.loadReleaseURL("//link.theplatform.com/s/" + tpHelper.accountPid + "/" + tpHelper.currentRelease, true);
            } else {
                $('.tpPlayer').css('visibility', 'hidden');
                $('#modal-player-placeholder').css('visibility', 'visible');
            }
        },
        onEmbed: function () {
            var player = $('#selectpick-player').val();

            var shortcodeSource = $("#shortcode-template").html();
            var shortcodeTemplate = _.template(shortcodeSource);

            //'[theplatform account="' + tpHelper.accountPid + '" media="' + tpHelper.currentRelease + '" player="' + player + '"]';
            var shortcode = shortcodeTemplate({
                account: tpHelper.accountPid,
                release: tpHelper.currentRelease,
                player: player
            });

            var win = window.dialogArguments || opener || parent || top;

            win.wp.media.editor.insert(shortcode.trim());

        },
        onEmbedAndClose: function () {
            Events.onEmbed();
        },
        onSetImage: function () {
            var post_id = window.parent.jQuery('#post_ID').val();
            if (!tpHelper.selectedThumb || !post_id)
                return;
            var data = {
                action: 'set_thumbnail',
                img: tpHelper.selectedThumb,
                id: post_id,
                _wpnonce: tp_browser_local.tp_nonce.set_thumbnail
            };

            $.post(tp_browser_local.ajaxurl, data, function (response) {
                if (response.success)
                    window.parent.jQuery('#postimagediv .inside').html(response.data);
            });
        },
        onEditMetadata: function () {
            $("#tp-edit-dialog").dialog({
                modal: true,
                title: 'Edit Media',
                resizable: true,
                minWidth: 800,
                width: 1024,
                open: function () {
                    $('#tp-edit-dialog').data('refresh', 'false');
                    $('.ui-dialog-titlebar-close').addClass('ui-button');
                    $('.tab-pane .error').remove();
                    theplatform_edit.updatePublishProfiles(tpHelper.mediaId);
                },
                close: function () {

                }
            }).css("overflow", "hidden");
            return false;
        },
        onGenerateThumbnail: function () {
            if ($(this).val() == 'Generating') {
                return;
            }

            $(this).val('Generating').removeClass('button-primary button-success button-danger button-info').addClass('button-info');
            var data = {
                action: 'generate_thumbnail',
                mediaId: tpHelper.mediaId,
                _wpnonce: tp_browser_local.tp_nonce.generate_thumbnail
            };

            if (!_.isUndefined(tpHelper.currentMediaTime)) {
                data.time = tpHelper.currentMediaTime;
            }
            var me = this;
            $.ajax({
                url: tp_browser_local.ajaxurl,
                method: 'post',
                data: data,
                success: function (response) {
                    theplatform_edit.onSuccess(response, me);
                },
                complete: function () {
                    theplatform_edit.onComplete(me, "Generate Thumbnail", "secondary");
                }

            });
        },
        onMediaPlaying: function (media) {
            tpHelper.currentMediaTime = media.data.currentTimeAggregate;
        },
        OnLoadReleaseUrl: function () {
            tpHelper.currentMediaTime = undefined;
        },
        onGetMedia: function (page, performCount) {
            var me = this;
            if (me.viewLoading === true) {
                return;
            }
            var MAX_RESULTS = 20;
            $('.spinner').show(); // show loading before we call getVideos
            var theRange = ((page - 1) * MAX_RESULTS + 1) + '-' + (page * MAX_RESULTS);

            me.viewLoading = true;

            var videosPromise = $.when(API.getVideos(theRange), API.getVideoCount(performCount));

            videosPromise.done(function(videos, count) {
                tpHelper.feedResultCount = videos.entryCount;

                if (videos.entryCount === 0) {
                    UI.notifyUser('No Results');
                }

                $('#media-list').empty();
                var entries = videos.entries;

                for (var i = 0; i < entries.length; i++) {
                    UI.addMediaObject(entries[i]);
                }

                Events.onGetMediaCount(count, MAX_RESULTS);

                $('.spinner').hide();
                Holder.run();
                me.viewLoading = false;
            });
        },
        onGetMediaCount: function (totalResults, MAX_RESULTS) {
            tpHelper.totalResults = totalResults;
            var page = tpHelper.currentPage;
            var postfix = totalResults == 1 ? ' item' : ' items';
            $('.displaying-num').text(totalResults + postfix);
            if (totalResults !== 0) {
                var pages = Math.ceil(totalResults / MAX_RESULTS);
                $('.total-pages').text(pages);
                $('.current-page').each(function () {
                    $(this).attr('max', pages).val(page);
                });

                if (pages <= 1) {
                    $('.pagination-links a').each(function () {
                        $(this).addClass('disabled');
                    });
                }

                if (pages > 1) {
                    $('.first-page,.prev-page,.last-page,.next-page').each(function () {
                        $(this).removeClass('disabled');
                    });
                }

                if (page == pages) {
                    $('.last-page,.next-page').each(function () {
                        $(this).addClass('disabled');
                    });
                }

                if (page == 1) {
                    $('.first-page,.prev-page').each(function () {
                        $(this).addClass('disabled');
                    });
                }
            } else {
                $('.total-pages').text(1);
                $('.current-page').each(function () {
                    $(this).attr('max', 1).val(1);
                });
                $('.pagination-links a').each(function () {
                    $(this).addClass('disabled');
                });
            }
        },
        onPageNavigation: function (e) {
            e.preventDefault();

            if ($(this).hasClass('disabled')) {
                return;
            }
            var buttonClass = $(this).attr('class');

            switch (buttonClass) {
                case 'next-page':
                    tpHelper.currentPage++;
                    break;
                case 'prev-page':
                    tpHelper.currentPage--;
                    break;
                case 'last-page':
                    tpHelper.currentPage = parseInt($('.total-pages')[0].innerText);
                    break;
                case 'first-page':
                    tpHelper.currentPage = 1;
                    break;
            }
            Events.onGetMedia(tpHelper.currentPage, false);
        }
    };

    /**
     * Data Formatting Methods
     * @type {Object}
     */
    var Formatting = {
        formatValue: function (value, dataType) {
            switch (dataType) {
                case 'DateTime':
                    value = new Date(value);
                    break;
                case 'Duration':
                    value = Formatting.secondsToDuration(value);
                    break;
                case 'Link':
                    var a = document.createElement('a');
                    a.href = value.href;
                    a.target = '_blank';
                    a.text = value.title;
                    value = a;
                    break;
            }
            return value;
        },

        secondsToDuration: function (secs) {
            var t = new Date(1970, 0, 1);
            t.setSeconds(secs);
            var s = t.toTimeString().substr(0, 8);
            if (secs > 86399)
                s = Math.floor((t - Date.parse("1/1/70")) / 3600000) + s.substr(2);
            return s;
        },

        extractVideoUrlfromMedia: function (media) {
            var urls = [];
            if (!_.has(media, 'content'))
                return urls;

            for (var i = 0; i < media.content.length; i++) {
                var content = media.content[i];
                if ((content.contentType == "video" || content.contentType == "audio") && content.releases) {
                    for (var releaseIndex in content.releases) {
                        if (content.releases[releaseIndex].delivery == "streaming")
                            urls.push(content.releases[releaseIndex].pid);
                    }
                }
            }
            return urls;
        }
    };

    /**
     * mpx API calls
     * @type {Object}
     */
    var API = {
        getVideos: function (range) {
            var deferred = $.Deferred();

            var data = {
                _wpnonce: tp_browser_local.tp_nonce.get_videos,
                action: 'get_videos',
                range: range,
                query: tpHelper.queryString,
                isEmbed: tpHelper.isEmbed,
                myContent: $('#my-content-cb').prop('checked')
            };

            $.post(tp_browser_local.ajaxurl, data, function (resp) {
                if (!resp.success) {
                    UI.notifyUser(resp.data);
                    $('.spinner').hide();

                    deferred.reject(resp.data);
                } else {
                    deferred.resolve(resp.data);
                }
            });

            return deferred.promise();
        },
        getVideoCount: function (performCount) {
            var deferred = $.Deferred();

            if (performCount === false) {
                deferred.resolve(tpHelper.totalResults);
                return deferred.promise();
            }

            var data = {
                _wpnonce: tp_browser_local.tp_nonce.get_video_count,
                action: 'get_video_count',
                myContent: $('#my-content-cb').prop('checked'),
                isEmbed: tpHelper.isEmbed,
                query: tpHelper.queryString,
            };

            $.post(tp_browser_local.ajaxurl, data, function (resp) {
                deferred.resolve(resp.data);
            });

            return deferred.promise();
        },
        buildMediaQuery: function (data) {
            var queryParams = '';
            if (data.category)
                queryParams = queryParams.appendParams({
                    byCategories: data.category
                });

            if (data.search) {
                queryParams = queryParams.appendParams({
                    q: encodeURIComponent(data.search)
                });
            }

            if (data.sort) {
                var sortValue = data.sort + data.order;
                queryParams = queryParams.appendParams({
                    sort: sortValue
                });
            }

            return queryParams;
        },
        getCategoryList: function () {
            var data = {
                _wpnonce: tp_browser_local.tp_nonce.get_categories,
                action: 'get_categories',
                sort: 'order',
                fields: 'title'
            };

            $.post(tp_browser_local.ajaxurl, data,
                function (entries) {
                    var categoryPicker = $('#selectpick-categories');
                    // Add each category
                    for (var idx in entries.data) {
                        var entryTitle = entries.data[idx].fullTitle;
                        var option = document.createElement('option');
                        option.value = entryTitle;
                        option.text = entryTitle;
                        categoryPicker.append(option);
                    }
                });
        },
        getVideoById: function (mediaId, callback) {
            var data = {
                _wpnonce: tp_browser_local.tp_nonce.get_video_by_id,
                action: 'get_video_by_id',
                mediaId: mediaId
            };

            $.post(tp_browser_local.ajaxurl, data, function (resp) {
                callback(resp.data);
            });
        }
    };

    //Make my life easier by prototyping this into the string.
    String.prototype.appendParams = function (params) {
        var updatedString = this;
        for (var key in params) {
            if (updatedString.indexOf(key + '=') > -1)
                continue;
            updatedString += '&' + key + '=' + encodeURIComponent(params[key]);
        }
        return updatedString;
    };

    // Set up our template helper method
    _.template.formatDescription = function (description) {
        if (description && description.length > 300) {
            return description.substring(0, 297) + '...';
        }
        return description;
    };

    $(document).ready(function () {
        if (!_.isUndefined(window.$pdk)) {
            $pdk.initialize();
            $pdk.controller.addEventListener('OnMediaPlaying', Events.onMediaPlaying);
            $pdk.controller.addEventListener('OnLoadReleaseUrl', Events.onLoadReleaseUrl);
        }

        $('#btn-embed').click(Events.onEmbed);
        $('#btn-embed-close').click(Events.onEmbedAndClose);
        $('#btn-set-image').click(Events.onSetImage);
        $('#btn-edit').click(Events.onEditMetadata);
        $('#btn-generate-thumbnail').click(Events.onGenerateThumbnail);

        /**
         * Search form event handlers
         */
        $('#btn-search').click(UI.refreshView);
        $('#input-search').keyup(function (event) {
            if (event.keyCode == 13)
                UI.refreshView();
        });

        $('#current-page-selector').keyup(function (event) {
            if (event.keyCode == 13)
                Events.onGetMedia($(this).val(), false);
        });

        $('.pagination-links a').click(Events.onPageNavigation);

        // Load Categories from mpx
        API.getCategoryList();

        /**
         * Set up the infinite scrolling media list and load the first sets of media
         */
        Events.onGetMedia(tpHelper.currentPage);
    });

    // Expose the updateMediaObject method outside this module
    return {
        updateMediaObject: UI.updateMediaObject,
        notifyUser: UI.notifyUser
    };

})(jQuery);
