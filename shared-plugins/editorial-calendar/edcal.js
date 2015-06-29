/*******************************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 ******************************************************************************/

/*
  This is the WordPress editorial calendar.  It is a continuous
  calendar in both directions.  That means instead of showing only
  one month at a time it shows the months running together.  Users
  can scroll from one month to the next using the up and down
  arrow keys, the page up and page down keys, the next and previous
  month buttons, and their mouse wheel.

  The calendar shows five weeks visible at a time and maintains 11
  weeks of rendered HTML.  Only the middle weeks are visible.

                    Week 1
                    Week 2
                    Week 3
                -   Week 4   -
                |   Week 5   |
                |   Week 6   |
                |   Week 7   |
                -   Week 8   -
                    Week 9
                    Week 10
                    Week 11

  When the user scrolls down one week the new week is added at the
  end of the calendar and the first week is removed.  In this way
  the calendar will only ever have 11 weeks total and won't use up
  excessive memory.

  This calendar uses AJAX to call into the functions defined in
  edcal.php.  These functions get posts and change post dates.

  The HTML structure of the calendar is:

  <div id="cal">
      <div id="row08Nov2009">
          <div id="row08Nov2009row">
              <div class="day sunday nov" id="08Nov2009">
                  <div class="dayobj">
                      <div class="daylabel">8</div>
                      <ul class="postlist">
                      </ul>
                   </div>
               </div>
          </div>
      </div>
  </div>
 */
var edcal = {

    /*
       This value is the number of weeks the user wants to see at one time
       in the calendar.
     */
    weeksPref: 3,

    /*
       This is a preference value indicating if you see the post status
     */
    statusPref: true,

    /*
       This is a preference value indicating if you see the post author
     */
    authorPref: false,

    /*
       This is a preference value indicating if you see the post time
     */
    timePref: true,

    /*
       This is a preference value indicating if we should prompt for feeback
     */
    doFeedbackPref: true,

    /*
     * True if the calendar is in the process of moving
     */
    isMoving: false,

    /*
     * True if we are in the middle of dragging a post
     */
    inDrag: false,

    /*
       True if the calendar is in the process of queueing scrolling
       during a drag.
     */
    isDragScrolling: false,

    /*
     * This is the format we use to dates that we use as IDs in the
     * calendar.  It is independant of the visible date which is
     * formatted based on the user's locale.
     */
    internalDateFormat: 'ddMMyyyy',

    /*
       This is the position of the calendar on the screen in pixels.
       It is an array with two fields:  top and bottom.
     */
    position: null,

    /*
     * This is the first date of the current month
     */
    firstDayOfMonth: null,

    /*
     * This is the first day of the next month
     */
    firstDayOfNextMonth: null,

    /*
     * The date format used by wordpress
     */
    wp_dateFormat: 'yyyy-MM-dd',

    /*
     * The cache of dates we have already loaded posts for.
     */
    cacheDates: [],

    /*
     * The ID of the timer we use to batch new post requests
     */
    tID: null,

    /*
     * The number of steps moving for this timer.
     */
    steps: 0,

    /*
     * The constant for the concurrency error.
     */
    CONCURRENCY_ERROR: 4,

    /*
     * The constant for the user permission error
     */
    PERMISSION_ERROR: 5,

    /*
     * The constant for the nonce error
     */
    NONCE_ERROR: 6,

    /*
       The direction the calendar last moved.
       true = down = to the future
       false = up = to the past

     */
    currentDirection: true,

    /*
       This date is our index.  When the calendar moves we
       update this date to indicate the next rows we need
       to add.
     */
    _wDate: Date.today(),

    /*
     * The date since the previous move
     */
    moveDate: null,

    /*
     * This is a number from 0-6 indicating when the start
     * of the week is.  The user sets this in the Settings >
     * General page and it is a single value for the entire
     * server.  We are setting this value in edcal.php
     */
    startOfWeek: null,

    /*
       A cache of all the posts we have loaded so far.  The
       data structure is:

       posts [date - ddMMMyyyy][posts array - post object from JSON data]
     */
    posts: [],

    /*
       IE will sometimes fire the resize event twice for the same resize
       action.  We save it so we only resize the calendar once and avoid
       any flickering.
     */
    windowHeight: 0,

    /*
       This function aligns the grid in two directions.  There
       is a vertical grid with a row of each week and a horizontal
       grid for each week with a list of days.
     */
    alignGrid: function(/*string*/ gridid, /*int*/ cols, /*int*/ cellWidth, /*int*/ cellHeight, /*int*/ padding) {
        var x = 0;
        var y = 0;
        var count = 1;

        jQuery(gridid).each(function() {
            jQuery(this).css('position', 'relative');

            jQuery(this).children('div').each(function() {
                jQuery(this).css({
                    width: cellWidth + '%',
                    height: cellHeight + '%',
                    position: 'absolute',
                    left: x + '%',
                    top: y + '%'
                });

                if ((count % cols) === 0) {
                    x = 0;
                    y += cellHeight + padding;
                } else {
                    x += cellWidth + padding;
                }

                count++;
            });
        });
    },

    /*
       This is a helper function to align the calendar so we don't
       have to change the cell sizes in multiple places.
     */
    alignCal: function() {
        edcal.alignGrid('#cal', 1, 100, (100 / edcal.weeksPref) - 1, 1);
    },


    /*
       This function creates the days header at the top of the
       calendar.
     */
    createDaysHeader: function() {
        /*
         * The first day of the week in the calendar depends on
         * a wordpress setting and maybe the server locale.  This
         * means we need to determine the days of the week dynamically.
         * Luckily the Date.js library already has these strings
         * localized for us.  All we need to do is figure out the
         * first day of the week and then we can add a day from there.
         */

        var date = Date.today().next().sunday();

        /*
         * We need to call nextStartOfWeek to make sure the
         * edcal.startOfWeek variable gets initialized.
         */
        edcal.nextStartOfWeek(date);


        var html = '<div class="dayheadcont"><div class="dayhead firstday">' +
            date.add(edcal.startOfWeek).days().toString('dddd') +
        '</div>';

        html += '<div class="dayhead">' + date.add(1).days().toString('dddd') + '</div>';
        html += '<div class="dayhead">' + date.add(1).days().toString('dddd') + '</div>';
        html += '<div class="dayhead">' + date.add(1).days().toString('dddd') + '</div>';
        html += '<div class="dayhead">' + date.add(1).days().toString('dddd') + '</div>';
        html += '<div class="dayhead">' + date.add(1).days().toString('dddd') + '</div>';
        html += '<div class="dayhead lastday">' + date.add(1).days().toString('dddd') + '</div>';

        jQuery('#cal_cont').prepend(html);

        edcal.alignGrid('.dayheadcont', 7, 13.8, 100, 0.5);
    },

    /*
       We have different styles for days in previous months,
       the current month, and future months.  This function
       figures out the right class based on the date.
     */
    getDateClass: function(/*Date*/ date) {

         var monthstyle;
         var daystyle;

         if (date.compareTo(Date.today()) == -1) {
             /*
              * Date is before today
              */
             daystyle = 'beforeToday';
         } else {
             /*
              * Date is after today
              */
             daystyle = 'todayAndAfter';
         }
         if (!edcal.firstDayOfMonth) {
             /*
              * We only need to figure out the first and last day
              * of the month once
              */
             edcal.firstDayOfMonth = Date.today().moveToFirstDayOfMonth().clearTime();
             edcal.firstDayOfNextMonth = Date.today().moveToLastDayOfMonth().clearTime();
         }
         if (date.between(edcal.firstDayOfMonth, edcal.firstDayOfNextMonth)) {
             /*
              * If the date isn't before the first of the
              * month and it isn't after the last of the
              * month then it is in the current month.
              */
             monthstyle = 'month-present';
         } else if (date.compareTo(edcal.firstDayOfMonth) == 1) {
             /*
              * Then the date is after the current month
              */
             monthstyle = 'month-future';
         } else if (date.compareTo(edcal.firstDayOfNextMonth) == -1) {
             /*
              * Then the date is before the current month
              */
             monthstyle = 'month-past';
         }

         if (date.toString('dd') == '01') {
             /*
              * This this date is the first day of the month
              */
             daystyle += ' firstOfMonth';
         }


         return monthstyle + ' ' + daystyle;
    },

    /*
       Show the add post link.  This gets called when the mouse
       is over a specific day.
     */
    showAddPostLink: function(/*string*/ dayid) {
         if (edcal.inDrag) {
             return;
         }

         var createLink = jQuery('#' + dayid + ' a.daynewlink');
         createLink.css('display', 'block');
         createLink.bind('click', edcal.addPost);
    },

    /*
       Hides the add new post link it is called when the mouse moves
       outside of the calendar day.
     */
    hideAddPostLink: function(/*string*/ dayid) {
         var link = jQuery('#' + dayid + ' a.daynewlink').hide();
         link.unbind('click', edcal.addPost);
    },

    /*
       Creates a row of the calendar and adds all of the CSS classes
       and listeners for each calendar day.
     */
    createRow: function(/*jQuery*/ parent, /*bool*/ append) {
        var _date = edcal._wDate.clone();

        var newrow = '<div class="rowcont" id="' + 'row' + edcal._wDate.toString(edcal.internalDateFormat) + '">' +
                     '<div id="' + 'row' + edcal._wDate.toString(edcal.internalDateFormat) + 'row" class="row">';
        for (var i = 0; i < 7; i++) {
            /*
             * Adding all of these calls in the string is kind of messy.  We
             * could do this with the JQuery live function, but there are a lot
             * of days in the calendar and the live function gets a little slow.
             */
            newrow += '<div onmouseover="edcal.showAddPostLink(\'' + _date.toString(edcal.internalDateFormat) + '\');" ' +
                      'onmouseout="edcal.hideAddPostLink(\'' + _date.toString(edcal.internalDateFormat) + '\');" ' +
                      'id="' + _date.toString(edcal.internalDateFormat) + '" class="day ' +
                      edcal.getDateClass(_date) + ' ' +
                      _date.toString('dddd').toLowerCase() + ' month-' +
                      _date.toString('MM').toLowerCase() + '">';

            newrow += '<div class="dayobj">';

            newrow += '<a href="#" adddate="' + _date.toString('MMMM d') + '" class="daynewlink" title="' + 
                sprintf(edcal.str_newpost, _date.toString(Date.CultureInfo.formatPatterns.monthDay)) + '" ' +
                         'onclick="return false;">' + edcal.str_addPostLink + '</a>';

            if (_date.toString('dd') == '01') {
                newrow += '<div class="daylabel">' + _date.toString('MMM d');
            } else {
                newrow += '<div class="daylabel">' + _date.toString('d');
            }


            newrow += '</div>';

            newrow += '<ul class="postlist">';

            newrow += edcal.getPostItems(_date.toString(edcal.internalDateFormat));

            newrow += '</ul>';

            newrow += '</div>';
            newrow += '</div>';
            _date.add(1).days();
        }

        newrow += '</div></div>';

        if (append) {
            parent.append(newrow);

        } else {
            parent.prepend(newrow);
        }

        /*
         * This is the horizontal alignment of an individual week
         */
        edcal.alignGrid('#row' + edcal._wDate.toString(edcal.internalDateFormat) + 'row', 7, 13.9, 100, 0.5);

        edcal.draggablePost('#row' + edcal._wDate.toString(edcal.internalDateFormat) + ' li.post');

        jQuery('#row' + edcal._wDate.toString(edcal.internalDateFormat) + ' > div > div.day').droppable({
            hoverClass: 'day-active',
            accept: function(ui) {
                /*
                   We only let them drag draft posts into the past.  If
                   they try to drag and scheduled post into the past we
                   reject the drag.  Using the class here is a little
                   fragile, but it is much faster than doing date
                   arithmetic every time the mouse twitches.
                 */
                if (jQuery(this).hasClass('beforeToday')) {
                    if (ui.hasClass('draft')) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return true;
                }
            },
            greedy: true,
            tolerance: 'pointer',
            drop: function(event, ui) {
                           //output('dropped ui.draggable.attr("id"): ' + ui.draggable.attr("id"));
                           //output('dropped on jQuery(this).attr("id"): ' + jQuery(this).attr("id"));
                           //output('ui.draggable.html(): ' + ui.draggable.html());

                           var dayId = ui.draggable.parent().parent().parent().attr('id');

                           edcal.doDrop(dayId, ui.draggable.attr('id'), jQuery(this).attr('id'));
                        }
            });

        return jQuery('row' + edcal._wDate.toString(edcal.internalDateFormat));
    },

    /*
     * Handle the drop when a user drags and drops a post.
     */
    doDrop: function(/*string*/ parentId, /*string*/ postId, /*string*/ newDate, /*function*/ callback) {
         var dayId = parentId;


         // Step 0. Get the post object from the map
         var post = edcal.findPostForId(parentId, postId);

         // Step 1. Remove the post from the posts map
         edcal.removePostFromMap(parentId, postId);

         /*
            Step 2. Remove the old element from the old parent.
     
            We would like to just remove the item right away,
            but on IE with JQuery UI 1.8 that causes an error
            because it tries to access the properties of the
            object to reset the cursor and it can't since the
            object is not longer part of the DOM.  That is why
            we detach it instead of removing it.
     
            However, this causes a small memory leak since every
            drag will detach an element and never remove it.  To
            clean up we wait half a second until the drag is done
            and then remove the item.  Hacky, but it works.
          */
         var oldPost = jQuery('#' + postId);
         oldPost.detach();
         
         setTimeout(function() {
             oldPost.remove();
         }, 500);

         // Step 3. Add the item to the new DOM parent
         jQuery('#' + newDate + ' .postlist').append(edcal.createPostItem(post, newDate));

         
         if (dayId == newDate) {
             /*
              If they dropped back on to the day they started with we
              don't want to go back to the server.
              */
             edcal.draggablePost('#' + newDate + ' .post');
         } else {
             // Step6. Update the date on the server
             edcal.changeDate(newDate, post, callback);
         }
    },

    /*
     * This is a helper method to make an individual post item draggable.
     */
    draggablePost: function(/*post selector*/ post) {
         jQuery(post).each(function() {
             var postObj = edcal.findPostForId(jQuery(this).parent().parent().parent().attr('id'),
                                               jQuery(this).attr('id'));
             if (edcal.isPostMovable(postObj)) {
                 jQuery(this).draggable({
                     revert: 'invalid',
                     appendTo: 'body',
                     helper: 'clone',
                     distance: 1,
                     addClasses: false,
                     start: function() {
                       edcal.inDrag = true;
                     },
                     stop: function() {
                       edcal.inDrag = false;
                     },
                     drag: function(event, ui) {
                        edcal.handleDrag(event, ui);
                     },
                     scroll: false,
                     refreshPositions: true
                 });
                 jQuery(this).addClass('draggable');
             }
         });
    },

    /*
       When the user is dragging we scroll the calendar when they get
       close to the top or bottom of the calendar.  This function handles
       scrolling the calendar when that happens.
     */
    handleDrag: function(event, ui) {
         if (edcal.isMoving || edcal.isDragScrolling) {
             return;
         }

         edcal.isDragScrolling = true;

         if (event.pageY < (edcal.position.top + 10)) {
             /*
                This means we're close enough to the top of the calendar to
                start scrolling up.
              */
             edcal.move(1, false);
         } else if (event.pageY > (edcal.position.bottom - 10)) {
             /*
                This means we're close enough to the bottom of the calendar
                to start scrolling down.
              */
             edcal.move(1, true);
         }

         /*
            We want to start scrolling as soon as the user gets their mouse
            close to the top, but if we just scrolle with every event then
            the screen flies by way too fast.  We wait here so we scroll one
            row and wait three quarters of a second.  That way it gives a
            smooth scroll that doesn't go too fast to track.
          */
         setTimeout(function() {
             edcal.isDragScrolling = false;
         }, 300);
    },

    /*
       This is a utility method to find a post and remove it
       from the cache map.
     */
    removePostFromMap: function(/*string*/ dayobjId, /*string*/ postId) {
         if (edcal.posts[dayobjId]) {
             for (var i = 0; i < edcal.posts[dayobjId].length; i++) {
                 if (edcal.posts[dayobjId][i] &&
                     'post-' + edcal.posts[dayobjId][i].id === postId) {
                     edcal.posts[dayobjId][i] = null;
                     return true;
                 }
             }
         }

         return false;
    },

    /*
     * Adds a post to an already existing calendar day.
     */
    addPostItem: function(/*post*/ post, /*string*/ dayobjId) {
         /*
          * We are trying to select the .postlist item under this div.  It would
          * be much more adaptable to reference the class by name, but this is
          * significantly faster.  Especially on IE.
          */
         jQuery('#' + dayobjId + ' > div > ul').append(edcal.createPostItem(post, dayobjId));
    },

    /*
       Makes all the posts in the specified day draggable
       and adds the tooltip.
     */
    addPostItemDragAndToolltip: function(/*string*/ dayobjId) {
         edcal.draggablePost('#' + dayobjId + ' > div > ul > li');
    },


    /*
        Deletes the post specified. Will only be executed once the user clicks the confirm link to proceed.
    */
    deletePost: function(/*Post ID*/ postId, /*function*/ callback) {

        var url = edcal.ajax_url() + '&action=edcal_deletepost&postid=' + postId;

        jQuery.ajax({
            url: url,
            type: 'POST',
            processData: false,
            timeout: 100000,
            dataType: 'json',
            success: function(res) {
                edcal.removePostItem(res.post.date, 'post-' + res.post.id);
                if (res.error) {
                    /*
                     * If there was an error we need to remove the dropped
                     * post item.
                     */
                    if (res.error === edcal.NONCE_ERROR) {
                        edcal.showError(edcal.checksum_error);
                    }
                } else {
                    edcal.output('Finished deleting the post: "' + res.post.title + '"');
                }

                if (callback) {
                    callback(res);
                }
            },
            error: function(xhr) {
                 edcal.showError(edcal.general_error);
                 if (xhr.responseText) {
                     edcal.output('deletePost xhr.responseText: ' + xhr.responseText);
                 }
            }
        });
    },




    /*
     * Confirms if you want to delete the specified post
     */
    confirmDelete: function(/*string*/ posttitle) {
         if (confirm(edcal.str_del_msg1 + posttitle + edcal.str_del_msg2)) {
             return true;
    // [wes] might be better to call deletePost from here directly, rather than return control back to the agent... which will then follow the link and call deletePost
         } else {
             return false;
         }
    },

    /*
       This is a simple function that creates the AJAX URL with the
       nonce value generated in edcal.php.
     */
    ajax_url: function() {
         return ajaxurl + '?_wpnonce=' + edcal.wp_nonce;
    },

    /*
        NOT USED
     */
    getMediaBar: function() {
         return jQuery('#cal_mediabar').html();
    },

    /*
     * Called when the "Add a post" link is clicked.
     * Sets up a post object and displays the add form
     */
    addPost: function() {
        jQuery('#newPostScheduleButton').addClass('disabled');

        var date = jQuery(this).parent().parent().attr('id');

        var formattedtime = '10:00';
        if (edcal.timeFormat !== 'H:i') {
            formattedtime += ' AM';
        }

        var post = {
            id: 0,
            date: date,
            formatteddate: edcal.getDayFromDayId(date).toString(edcal.previewDateFormat),
            time: formattedtime
        };
        edcal.showForm(post);
        return false;
    },

    /*
     * Called when the Edit link for a post is clicked.
     * Gets post details via an AJAX call and displays the edit form
     * with the fields populated.
     */
    editPost: function(/*int*/ post_id) {
        // Un-disable the save buttons because we're editing
        jQuery('#newPostScheduleButton').removeClass('disabled');

        // Editing, so we need to make an ajax call to get body of post
        edcal.getPost(post_id, edcal.showForm);
        return false;
    },


    /*
     * When the user presses the new post link on each calendar cell they get
     * a tooltip which prompts them to add or edit a post.  Once
     * they hit save we call this function.
     *
     * post - post object containing data for the post
     * doEdit - should we edit the post immediately?  if true we send the user
     *          to the edit screen for their new post.
     */
    savePost: function(/*object*/ post, /*boolean*/ doEdit, /*boolean*/ doPublish, /*function*/ callback) {
         if (typeof(post) === 'undefined' || post === null) {
            post = edcal.serializePost();
         }

         if (!post.title || post.title === '') {
             return;
         }

         edcal.output('savePost(' + post.date + ', ' + post.title + ')');

         jQuery('#edit-slug-buttons').addClass('tiploading');

         /*
            The date.js library has a bug where it gives the wrong
            24 hour value for 12AM and 12PM.  I've filed a bug report,
            but we still need to work aorund the issue.  Hackito
            ergo sum.
          */
         if (post.time.toUpperCase() === '12:00 PM') {
             post.time = '12:00';
         } else if (post.time.toUpperCase() === '12:30 PM') {
             post.time = '12:30';
         } else if (post.time.toUpperCase() === '12:00 AM') {
             post.time = '00:00';
         } else if (post.time.toUpperCase() === '12:30 AM') {
             post.time = '00:30';
         }

         var time;
         if (post.time !== '') {
            time = Date.parse(post.time);
         } else {
            time = Date.parse('10:00:00'); // If we don't have a time set, default it to 10am
         }

         var formattedtime = time.format('H:i:s');

         var formattedDate = encodeURIComponent(edcal.getDayFromDayId(post.date).toString(edcal.wp_dateFormat) + ' ' + formattedtime);
         var url = edcal.ajax_url() + '&action=edcal_savepost';
         var postData = 'date=' + formattedDate +
                       '&title=' + encodeURIComponent(post.title) +
                       '&content=' + encodeURIComponent(post.content) +
                       '&id=' + encodeURIComponent(post.id) +
                       '&status=' + encodeURIComponent(post.status);

         if (edcal.getUrlVars().post_type) {
             postData += '&post_type=' + encodeURIComponent(edcal.getUrlVars().post_type);
         }

         if (doPublish) {
             postData += '&dopublish=' + encodeURIComponent('future');
         }

         jQuery.ajax({
            url: url,
            type: 'POST',
            processData: false,
            data: postData,
            timeout: 100000,
            dataType: 'json',
            success: function(res) {
                jQuery('#edit-slug-buttons').removeClass('tiploading');
                jQuery('#tooltip').hide();
                if (res.error) {
                    /*
                     * If there was an error we need to remove the dropped
                     * post item.
                     */
                    if (res.error === edcal.NONCE_ERROR) {
                        edcal.showError(edcal.checksum_error);
                    }
                    return;
                }

                if (!res.post) {
                    edcal.showError('There was an error creating a new post for your blog.');
                } else {
                    if (doEdit) {
                        /*
                         * If the user wanted to edit the post then we redirect
                         * them to the edit page.
                         */
                        window.location = res.post.editlink.replace('&amp;', '&');
                    } else {

                        if (res.post.id) {
                            edcal.removePostItem(res.post.date, 'post-' + res.post.id);
                        }

                        edcal.addPostItem(res.post, res.post.date);
                        edcal.addPostItemDragAndToolltip(res.post.date);
                    }
                }

                if (callback) {
                    callback(res);
                }
            },
            error: function(xhr) {
                 jQuery('#edit-slug-buttons').removeClass('tiploading');
                 jQuery('#tooltip').hide();
                 edcal.showError(edcal.general_error);
                 if (xhr.responseText) {
                     edcal.output('savePost xhr.responseText: ' + xhr.responseText);
                 }
            }
        });
        return false;
    },

    /*
     * Collects form values for the post inputted by the user into an object
     */
    serializePost: function() {
        var post = {};

        jQuery('#tooltip').find('input, textarea, select').each(function() {
            post[this.name] = this.value;
        });
        return post;
    },

    /*
     * Accepts new or existing post data and then populates text fields as necessary
     */
    showForm: function(post) {
        edcal.resetForm();

        // show tooltip
        jQuery('#tooltip').center().show();

        if (!post.id) {
            jQuery('#tooltiptitle').text(edcal.str_newpost_title + post.formatteddate);
        } else {
            jQuery('#tooltiptitle').text(sprintf(edcal.str_edit_post_title, post.typeTitle, edcal.getDayFromDayId(post.date).toString(edcal.previewDateFormat)));

            // sets the read-only author field
            //jQuery('#edcal-author-p').html(post.author);

            // add post info to form
            jQuery('#edcal-title-new-field').val(post.title);
            jQuery('#content').val(post.content);
        }

        if (post.status === 'future') {
            jQuery('#newPostScheduleButton').text(edcal.str_update);
        }

        if (post.status) {
            jQuery('#edcal-status').val(post.status);
            edcal.updatePublishButton();
        } else {
            jQuery('#edcal-status').val('draft');
            jQuery('#newPostScheduleButton').text(edcal.str_save);
        }

        /*
           If you have a status that isn't draft or future we
           just make it read only.
         */
        if (post.status && post.status !== 'draft' && post.status !== 'future' && post.status !== 'pending') {
            jQuery('#edcal-status').attr('disabled', 'true');
            jQuery('#edcal-status').append('<option class="temp" value="' + post.status + '">' + post.status + '</option>');
            jQuery('#edcal-status').val(post.status);
        }



        if (edcal.getDayFromDayId(post.date).compareTo(Date.today()) == -1) {
            /*
             * We only allow drafts in the past
             */
            jQuery('#edcal-status').attr('disabled', 'true');
        }

        var time = post.time;
        jQuery('#edcal-time').val(time);

        // set hidden fields: post.date, post.id
        jQuery('#edcal-date').val(post.date);
        jQuery('#edcal-id').val(post.id);

        /*
         * Put the focus in the post title field when the tooltip opens.
         */

        jQuery('#edcal-title-new-field').focus();
        jQuery('#edcal-title-new-field').select();

        /*
        tb_init('a.thickbox, area.thickbox, input.thickbox');

        edCanvas = document.getElementById('content');
        edInsertContent = null;

        jQuery('a.thickbox').click(function(){
            if ( typeof tinyMCE != 'undefined' && tinyMCE.activeEditor ) {
                tinyMCE.get('content').focus();
                tinyMCE.activeEditor.windowManager.bookmark = tinyMCE.activeEditor.selection.getBookmark('simple');
            }
        });
        */
    },

    /*
     * Hides the add/edit form
     */
    hideForm: function() {
        jQuery('#tooltip').hide();
        edcal.resetForm();
    },

    /*
     * Clears all the input values in the add/edit form
     */
    resetForm: function() {
        jQuery('#tooltip').find('input, textarea, select').each(function() {
            this.value = '';
        });

        jQuery('#edcal-status').removeAttr('disabled');

        jQuery('#newPostScheduleButton').text(edcal.str_publish);

        jQuery('#tooltiptitle').text('');
        //jQuery('#edcal-author-p').html('');

        jQuery('#edcal-status').removeAttr('disabled');

        jQuery('#edcal-status .temp').remove();
    },

    /*
       Creates the HTML for a post item and adds the data for
       the post to the posts cache.
     */
    createPostItem: function(/*post*/ post, /*string*/ dayobjId) {
        if (!edcal.posts[dayobjId]) {
            edcal.posts[dayobjId] = [];
        }

        edcal.posts[dayobjId][edcal.posts[dayobjId].length] = post;

        return edcal.getPostItemString(post);
    },

    /*
       Finds the post object for the specified post ID  in the
       specified day.
     */
    findPostForId: function(/*string*/ dayobjId, /*string*/ postId) {
         if (edcal.posts[dayobjId]) {
            for (var i = 0; i < edcal.posts[dayobjId].length; i++) {
                if (edcal.posts[dayobjId][i] &&
                    'post-' + edcal.posts[dayobjId][i].id === postId) {
                    return edcal.posts[dayobjId][i];
                }
            }
        }
    },

    /*
     * Removes a post from the HTML and the posts cache.
     */
    removePostItem: function(/*string*/ dayobjId, /*string*/ postId) {
         if (edcal.findPostForId(dayobjId, postId)) {
             for (var i = 0; i < edcal.posts[dayobjId].length; i++) {
                 if (edcal.posts[dayobjId][i] &&
                     'post-' + edcal.posts[dayobjId][i].id === postId) {
                     edcal.posts[dayobjId][i] = null;
                     jQuery('#' + postId).remove();
                 }
             }
         }
    },

    /*
       Gets all the post items for the specified day from
       the post cache.
     */
    getPostItems: function(/*string*/ dayobjId) {
        var postsString = '';

        if (edcal.posts[dayobjId]) {
            for (var i = 0; i < edcal.posts[dayobjId].length; i++) {
                if (edcal.posts[dayobjId][i]) {
                    postsString += edcal.getPostItemString(edcal.posts[dayobjId][i]);
                }
            }
        }

        return postsString;
    },

    /*
       This function shows the action links for the post with the
       specified ID.
     */
    showActionLinks: function(/*string*/ postid) {
         var post = edcal.findPostForId(jQuery('#' + postid).parent().parent().parent().attr('id'), postid);

         if (edcal.inDrag || !edcal.isPostEditable(post)) {
             return;
         }

         var elem = jQuery('#' + postid + ' > div.postactions');

         elem.show();


         if (elem.parent().position().top + elem.parent().height() > elem.parent().parent().height()) {
             /*
                This means the action links probably won't be visible and we need to
                scroll to make sure the users can see it.
              */
             var p = jQuery('#' + postid + ' > div.postactions').parent().parent();
             p.scrollTop(p.scrollTop() + 45);
         }
    },

    /*
       Hides the action links for the post with the specified
       post ID.
     */
    hideActionLinks: function(/*string*/ postid) {
         var elem = jQuery('#' + postid + ' > div.postactions');
         elem.hide();
    },

    /*
       Returns true if the post is movable and false otherwise.
       This is based on the post date
     */
    isPostMovable: function(/*post*/ post) {
         return post.editlink && post.status !== 'publish';
    },

    /*
       Returns true if the post is editable and false otherwise.
       This is based on user permissions
     */
    isPostEditable: function(/*post*/ post) {
         return post.editlink;
    },

    /*
       Returns readonly if the post isn't editable
     */
    getPostEditableClass: function(/*post*/ post) {
         if (post.editlink) {
             return '';
         } else {
             return 'readonly';
         }
    },

    /*
     * Gets the HTML string for a post.
     */
    getPostItemString: function(/*post*/ post) {
         var posttitle = post.title;

         if (posttitle === '') {
             posttitle = '[No Title]';
         }

         if (edcal.statusPref) {
             if (post.status === 'draft' &&
                 post.sticky === '1') {
                 /*
                  * Then this post is a sticky draft
                  */
                 posttitle += edcal.str_draft_sticky;
             } else if (post.status === 'pending' &&
                        post.sticky === '1') {
                 /*
                  * Then this post is a sticky pending post
                  */
                 posttitle += edcal.str_pending_sticky;
             } else if (post.sticky === '1') {
                 posttitle += edcal.str_sticky;
             } else if (post.status === 'pending') {
                 posttitle += edcal.str_pending;
             } else if (post.status === 'draft') {
                 posttitle += edcal.str_draft;
             } else if (post.status !== 'publish' &&
                        post.status !== 'future' &&
                        post.status !== 'pending') {
                 /*
                    There are some WordPress plugins that let you specify
                    custom post status.  In that case we just want to show
                    you the status.
                  */
                 posttitle += ' [' + post.status + ']';
             }
         }

         if (edcal.timePref) {
             posttitle = '<span class="posttime">' + post.formattedtime + '</span> ' + posttitle;
         }

         if (edcal.authorPref) {
             posttitle = sprintf(edcal.str_by, posttitle, '<span class="postauthor">' + post.author + '</span>');
         }

         var classString = '';

         if (edcal.isPostMovable(post)) {
             return '<li onmouseover="edcal.showActionLinks(\'post-' + post.id + '\');" ' +
                 'onmouseout="edcal.hideActionLinks(\'post-' + post.id + '\');" ' +
                 'id="post-' + post.id + '" class="post ' + post.status + ' ' + edcal.getPostEditableClass(post) + '"><div class="postlink ' + classString + '">' + posttitle + '</div>' +
                 '<div class="postactions">' +
                 '<a href="' + post.editlink + '">' + edcal.str_edit + '</a> | ' +
                 '<a href="#" onclick="edcal.editPost(' + post.id + '); return false;">' + edcal.str_quick_edit + '</a> | ' +
                 '<a href="' + post.dellink + '" onclick="return edcal.confirmDelete(\'' + post.title + '\');">' + edcal.str_del + '</a> | ' +
                 '<a href="' + post.permalink + '">' + edcal.str_view + '</a>' +
                 '</div></li>';
         } else {
             return '<li onmouseover="edcal.showActionLinks(\'post-' + post.id + '\');" ' +
                 'onmouseout="edcal.hideActionLinks(\'post-' + post.id + '\');" ' +
                 'id="post-' + post.id + '" class="post ' + post.status + ' ' + edcal.getPostEditableClass(post) + '"><div class="postlink ' + classString + '">' + posttitle + '</div>' +
                 '<div class="postactions">' +
                 '<a href="' + post.editlink + '">' + edcal.str_republish + '</a> | ' +
                 '<a href="' + post.permalink + '">' + edcal.str_view + '</a>' +
                 '</div></li>';
         }
    },

    /*
       Finds the calendar cell for the current day and adds the
       class "today" to that cell.
     */
    setClassforToday: function() {
        /*
           We want to set a class for the cell that represents the current day so we can
           give it a background color.
         */
        jQuery('#' + Date.today().toString(edcal.internalDateFormat)).addClass('today');
    },

    /*
       Most browsers need us to set a calendar height in pixels instead
       of percent.  This function get the correct pixel height for the
       calendar based on the window height.
     */
    getCalHeight: function() {
        var myHeight = jQuery(window).height() - jQuery('#footer').height() - jQuery('#wphead').height() - 150;

        /*
           We don't want to make the calendar too short even if the
           user's screen is super short.
         */
        return Math.max(myHeight, 500);
    },

    /*
       Moves the calendar a certain number of steps in the specified direction.
       True moves the calendar down into the future and false moves the calendar
       up into the past.
     */
    move: function(/*int*/ steps, /*boolean*/ direction, /*function*/ callback) {
         /*
          * If the add/edit post form is visible, don't go anywhere.
          */
        if (jQuery('#tooltip').is(':visible')) {
            return;
        }

        /*
           The working date is a marker for the last calendar row we created.
           If we are moving forward that will be the last row, if we are moving
           backward it will be the first row.  If we switch direction we need
           to bump up our date by 11 rows times 7 days a week or 77 days.
         */
        if (edcal.currentDirection != direction) {
            if (direction) {        // into the future
                edcal._wDate = edcal._wDate.add((edcal.weeksPref + 7) * 7).days();
            } else {                // into the past
                edcal._wDate = edcal._wDate.add(-((edcal.weeksPref + 7) * 7)).days();
            }

            edcal.steps = 0;
            edcal.moveDate = edcal._wDate;
        }

        edcal.currentDirection = direction;

        var i;


        if (direction) {
            for (i = 0; i < steps; i++) {
                jQuery('#cal > div:first').remove();
                edcal.createRow(jQuery('#cal'), true);
                edcal._wDate.add(7).days();
            }
            edcal.alignCal();
        } else {
            for (i = 0; i < steps; i++) {
                jQuery('#cal > div:last').remove();
                edcal.createRow(jQuery('#cal'), false);
                edcal._wDate.add(-7).days();
            }
            edcal.alignCal();
        }

        edcal.setClassforToday();
        edcal.setDateLabel();

        /*
         * If the user clicks quickly or uses the mouse wheel they can
         * get a lot of move events very quickly and we need to batch
         * them up together.  We set a timeout and clear it if there is
         * another move before the timeout happens.
         */
        edcal.steps += steps;
        if (edcal.tID) {
            clearTimeout(edcal.tID);
        } else {
            edcal.moveDate = edcal._wDate;
        }

        edcal.tID = setTimeout(function() {

            /*
             * Now that we are done moving the calendar we need to get the posts for the
             * new dates.  We want to load the posts between the place the calendar was
             * at when the user started moving it and the place the calendar is at now.
             */
            if (!direction) {
                edcal.getPosts(edcal._wDate.clone(),
                               edcal._wDate.clone().add(7 * (edcal.steps + 1)).days(),
                               callback);
            } else {
                edcal.getPosts(edcal._wDate.clone().add(-7 * (edcal.steps + 1)).days(),
                               edcal._wDate.clone(),
                               callback);
            }

            edcal.steps = 0;
            edcal.tID = null;
            edcal.moveDate = edcal._wDate;
        }, 1000);

        if (direction) {
            /*
               If we are going into the future then wDate is way in the
               future so we need to get the current date which is four weeks
               plus the number of visible weeks before the end of the current _wDate.
             */
            jQuery.cookie('edcal_date', edcal._wDate.clone().add(-(edcal.weeksPref + 4)).weeks().toString('yyyy-dd-MM'));
        } else {
            /*
               If we are going into the past then the current date is two
               weeks after the current _wDate
             */
            jQuery.cookie('edcal_date', edcal._wDate.clone().add(3).weeks().toString('yyyy-dd-MM'));
        }
    },

    /*
       We use the date as the ID for day elements, but the Date
       library can't parse the date without spaces and using
       spaces in IDs can cause problems.  We work around the
       issue by adding the spaces back before we parse.
     */
    getDayFromDayId: function(/*dayId*/ day) {
         return Date.parseExact(day.substring(2, 4) + '/' + day.substring(0, 2) + '/' + day.substring(4), 'MM/dd/yyyy');
    },

    /*
       This is a helper method to set the date label on the top of
       the calendar.  It looks like November 2009-December2009
     */
    setDateLabel: function(year) {
        var api = jQuery('#edcal_scrollable').scrollable();
        var items = api.getVisibleItems();

        /*
           We need to get the first day in the first week and the
           last day in the last week.  We call children twice to
           work around a small JQuery issue.
         */
        var firstDate = edcal.getDayFromDayId(items.eq(0).children('.row').children('.day:first').attr('id'));
        var lastDate = edcal.getDayFromDayId(items.eq(edcal.weeksPref - 1).children('.row').children('.day:last').attr('id'));

        jQuery('#currentRange').text(firstDate.toString('MMMM yyyy') + ' - ' + lastDate.toString('MMMM yyyy'));
    },

    /*
     * We want the calendar to start on the day of the week that matches the country
     * code in the locale.  If their full locale is en-US, that means the country
     * code is US.
     *
     * This is the full list of start of the week days from unicode.org
     * http://unicode.org/repos/cldr/trunk/common/supplemental/supplementalData.xml
     */
    nextStartOfWeek: function(/*date*/ date) {
         date = date.clone();
         if (edcal.startOfWeek === null) {
             if (edcal.locale) {
                 var local = edcal.locale.toUpperCase();

                 if (edcal.endsWith(local, 'AS') ||
                     edcal.endsWith(local, 'AZ') ||
                     edcal.endsWith(local, 'BW') ||
                     edcal.endsWith(local, 'CA') ||
                     edcal.endsWith(local, 'CN') ||
                     edcal.endsWith(local, 'FO') ||
                     edcal.endsWith(local, 'GB') ||
                     edcal.endsWith(local, 'GE') ||
                     edcal.endsWith(local, 'GL') ||
                     edcal.endsWith(local, 'GU') ||
                     edcal.endsWith(local, 'HK') ||
                     edcal.endsWith(local, 'IE') ||
                     edcal.endsWith(local, 'IL') ||
                     edcal.endsWith(local, 'IN') ||
                     edcal.endsWith(local, 'IS') ||
                     edcal.endsWith(local, 'JM') ||
                     edcal.endsWith(local, 'JP') ||
                     edcal.endsWith(local, 'KG') ||
                     edcal.endsWith(local, 'KR') ||
                     edcal.endsWith(local, 'LA') ||
                     edcal.endsWith(local, 'MH') ||
                     edcal.endsWith(local, 'MN') ||
                     edcal.endsWith(local, 'MO') ||
                     edcal.endsWith(local, 'MP') ||
                     edcal.endsWith(local, 'MT') ||
                     edcal.endsWith(local, 'NZ') ||
                     edcal.endsWith(local, 'PH') ||
                     edcal.endsWith(local, 'PK') ||
                     edcal.endsWith(local, 'SG') ||
                     edcal.endsWith(local, 'SY') ||
                     edcal.endsWith(local, 'TH') ||
                     edcal.endsWith(local, 'TT') ||
                     edcal.endsWith(local, 'TW') ||
                     edcal.endsWith(local, 'UM') ||
                     edcal.endsWith(local, 'US') ||
                     edcal.endsWith(local, 'UZ') ||
                     edcal.endsWith(local, 'VI') ||
                     edcal.endsWith(local, 'ZW')) {

                     /*
                      * Sunday
                      */
                     edcal.startOfWeek = 0;
                 } else if (edcal.endsWith(local, 'MV')) {
                     /*
                      * Friday
                      */
                     edcal.startOfWeek = 5;
                 } else if (edcal.endsWith(local, 'AF') ||
                            edcal.endsWith(local, 'BH') ||
                            edcal.endsWith(local, 'DJ') ||
                            edcal.endsWith(local, 'DZ') ||
                            edcal.endsWith(local, 'EG') ||
                            edcal.endsWith(local, 'ER') ||
                            edcal.endsWith(local, 'ET') ||
                            edcal.endsWith(local, 'IQ') ||
                            edcal.endsWith(local, 'IR') ||
                            edcal.endsWith(local, 'JO') ||
                            edcal.endsWith(local, 'KE') ||
                            edcal.endsWith(local, 'KW') ||
                            edcal.endsWith(local, 'LY') ||
                            edcal.endsWith(local, 'MA') ||
                            edcal.endsWith(local, 'OM') ||
                            edcal.endsWith(local, 'QA') ||
                            edcal.endsWith(local, 'SA') ||
                            edcal.endsWith(local, 'SD') ||
                            edcal.endsWith(local, 'SO') ||
                            edcal.endsWith(local, 'TN') ||
                            edcal.endsWith(local, 'YE')) {
                     /*
                      * Sunday
                      */
                     edcal.startOfWeek = 6;
                 } else {
                     /*
                      * Monday
                      */
                     edcal.startOfWeek = 1;
                 }
             } else {
                 /*
                  * If we have no locale set we'll assume American style and
                  * make it Sunday.
                  */
                 edcal.startOfWeek = 0;
             }
         }

         return date.next().sunday().add(edcal.startOfWeek).days();
    },

    /*
     * Just a little helper function to tell if a given string (str)
     * ends with the given expression (expr).  I could adding this
     * function to the JavaScript string object, but I don't want to
     * risk conflicts with other plugins.
     */
    endsWith: function(/*string*/ str, /*string*/ expr) {
         return (str.match(expr + '$') == expr);
    },

    /*
     * Moves the calendar to the specified date.
     */
    moveTo: function(/*Date*/ date) {
         edcal.isMoving = true;
         jQuery('#cal').empty();

         jQuery.cookie('edcal_date', date.toString('yyyy-dd-MM'));

         /*
           When we first start up our working date is 4 weeks before
           the next Sunday.
          */
         edcal._wDate = edcal.nextStartOfWeek(date).add(-21).days();

         /*
           After we remove and redo all the rows we are back to
           moving in a going down direction.
          */

         edcal.currentDirection = true;

         var count = edcal.weeksPref + 6;

         for (var i = 0; i < count; i++) {
             edcal.createRow(jQuery('#cal'), true);
             edcal._wDate.add(7).days();
         }

         edcal.alignCal();

         var api = jQuery('#edcal_scrollable').scrollable();

         api.move(2);

         edcal.setDateLabel();
         edcal.setClassforToday();
         edcal.isMoving = false;
    },

    /*
       When we handle dragging posts we need to know the size
       of the calendar so we figure it out ahead of time and
       save it.
     */
    savePosition: function() {
         var cal = jQuery('#edcal_scrollable');
         edcal.position = {
             top: cal.offset().top,
             bottom: cal.offset().top + cal.height()
         };

         /*
            When the user drags a post they get a "helper" element that clones
            the post and displays it during the drag.  This means they get all
            the same classes and styles.  However, the width of a post is based
            on the width of a day in the calendar and not anything in a style.
            That works well for the posts in the calendar, but it means we need
            to dynamically determine the width of the post when dragging.

            This value will remain the same until the calendar resizes.  That is
            why we do it here.  We need to get the width of the first visible day
            in the calendar which is why we use the complicated selector.  We also
            need to generate a style for it since the drag element doesn't exist
            yet and using the live function would really slow down the drag operation.

            We base this on the width of a way since they might not have any posts
            yet.
          */
         jQuery('#edcal_poststyle').remove();

         /*
            We need to figure out the height of each post list.  They all have the same
            height so we just look at the first visible list and set some styles on the
            page to set the post list height based on that.  We reset the value every
            time the page refreshes.
          */
         var dayHeight = jQuery('.rowcont:eq(2) .dayobj:first').height() - jQuery('.rowcont:eq(2) .daylabel:first').height() - 6;

         jQuery('head').append('<style id="edcal_poststyle" type="text/css">.ui-draggable-dragging {' +
                                    'width: ' + (jQuery('.rowcont:eq(2) .day:first').width() - 5) + 'px;' +
                               '}' +
                               '.postlist {' +
                                    'height: ' + dayHeight + 'px;' +
                               '}' +
                               '</style>');
    },

    /*
     * Adds the feedback section
     */
    addFeedbackSection: function() {
         if (edcal.visitCount > 3 && edcal.doFeedbackPref) {
             jQuery('#edcal_main_title').after(edcal.str_feedbackmsg);
         }
    },

    /*
     * Does the data collection.  This uses Mint to collect data about the way
     * the calendar is being used.
     */
    doFeedback: function() {
		return false;
    },

    /*
     * Sends no feedback and hides the section
     */
    noFeedback: function() {
         jQuery('#feedbacksection').hide('fast');
         edcal.saveFeedbackPref();
    },

    /*
     * Saves the feedback preference to the server
     */
    saveFeedbackPref: function() {
         var url = edcal.ajax_url() + '&action=edcal_saveoptions&dofeedback=' + encodeURIComponent('done');

         jQuery.ajax({
             url: url,
             type: 'POST',
             processData: false,
             timeout: 100000,
             dataType: 'text',
             success: function(res) {
                jQuery('#feedbacksection').html(edcal.str_feedbackdone);
                setTimeout(function() {
                    jQuery('#feedbacksection').hide('slow');
                }, 5000);
             },
             error: function(xhr) {
                edcal.showError(edcal.general_error);
                if (xhr.responseText) {
                    edcal.output('saveOptions xhr.responseText: ' + xhr.responseText);
                }
            }
        });

    },

    /*
     * Initializes the calendar
     */
    init: function() {
         if (jQuery('#edcal_scrollable').length === 0) {
             /*
              * This means we are on a page without the editorial
              * calendar
              */
             return;
         }

        edcal.addFeedbackSection();

        jQuery('#loading').hide();

        jQuery('#edcal_scrollable').css('height', edcal.getCalHeight() + 'px');
        edcal.windowHeight = jQuery(window).height();

        /*
         *  Add the days of the week
         */
        edcal.createDaysHeader();

        /*
         * We start by initializting the scrollable.  We use this to manage the
         * scrolling of the calendar, but don't actually call it to animate the
         * scrolling.  We specify an easing here because the default is "swing"
         * and that has a conflict with JavaScript used in the BuddyPress plugin/
         *
         * This doesn't really change anything since the animation happens offscreen.
         */
        jQuery('#edcal_scrollable').scrollable({
                                    vertical: true,
                                    size: edcal.weeksPref,
                                    keyboardSteps: 1,
                                    speed: 100,
                                    easing: 'linear'
                                    // use mousewheel plugin
                                    }).mousewheel();

        var api = jQuery('#edcal_scrollable').scrollable();

        /*
           When the user moves the calendar around we remember their
           date and save it in a cookie.  Then we read the cookie back
           when we reload so the calendar stays where the user left
           it last.
         */
        var curDate = jQuery.cookie('edcal_date');

        if (curDate) {
            curDate = Date.parseExact(curDate, 'yyyy-dd-MM');
            edcal.output('Resetting to date from the edcal_Date cookie: ' + curDate);
        } else {
            curDate = Date.today();
        }

        edcal.moveTo(curDate.clone());

        /*
         * The scrollable handles some basic binding.  This gets us
         * up arrow, down arrow and the mouse wheel.
         */
        api.onBeforeSeek(function(evt, direction) {
                         // inside callbacks the "this" variable is a reference to the API
            /*
             * Some times for reasons I haven't been able to figure out
             * the direction is an int instead of a boolean.  I don't
             * know why, but this works around it.
             */
            if (direction === 1) {
                direction = false;
            } else if (direction === 3) {
                direction = true;
            }

            if (!edcal.isMoving) {
                edcal.move(1, direction);
            }

            return false;
        });

        /*
         * We also want to listen for a few other key events
         */
        jQuery(document).bind('keydown', function(evt) {
            //if (evt.altKey || evt.ctrlKey) { return; }
            //output("evt.altKey: " + evt.altKey);
            //output("evt.keyCode: " + evt.keyCode);
            //output("evt.ctrlKey: " + evt.ctrlKey);

            if ((evt.keyCode === 34 && !(evt.altKey || evt.ctrlKey)) || //page down
                evt.keyCode === 40 && evt.ctrlKey) {                     // Ctrl+down down arrow
                edcal.move(edcal.weeksPref, true);
                return false;
            } else if ((evt.keyCode === 33 && !(evt.altKey || evt.ctrlKey)) || //page up
                evt.keyCode === 38 && evt.ctrlKey) {                            // Ctrl+up up arrow
                edcal.move(edcal.weeksPref, false);
                return false;
            } else if (evt.keyCode === 27) { //escape key
                edcal.hideForm();
                return false;
            }
        });

        edcal.getPosts(edcal.nextStartOfWeek(curDate).add(-3).weeks(),
                       edcal.nextStartOfWeek(curDate).add(edcal.weeksPref + 3).weeks());

        /*
           Now we bind the listeners for all of our links and the window
           resize.
         */
        jQuery('#moveToToday').click(function() {
            edcal.moveTo(Date.today());
            edcal.getPosts(edcal.nextStartOfWeek(Date.today()).add(-3).weeks(),
                           edcal.nextStartOfWeek(Date.today()).add(edcal.weeksPref + 3).weeks());
            return false;
        });

        jQuery('#prevmonth').click(function() {
            edcal.move(edcal.weeksPref, false);
            return false;
        });

        jQuery('#nextmonth').click(function() {
            edcal.move(edcal.weeksPref, true);
            return false;
        });

        function resizeWindow(e) {
            if (edcal.windowHeight != jQuery(window).height()) {
                jQuery('#edcal_scrollable').css('height', edcal.getCalHeight() + 'px');
                edcal.windowHeight = jQuery(window).height();
                edcal.savePosition();
            }
        }
        jQuery(window).bind('resize', resizeWindow);

        jQuery('#newPostScheduleButton').live('click', function(evt) {
            // if the button is disabled, don't do anything
            if (jQuery(this).hasClass('disabled')) {
                return false;
            }
            // Otherwise,
            // make sure we can't make duplicate posts by clicking twice quickly
            jQuery(this).addClass('disabled');
            // and save the post
            return edcal.savePost(null, false, true);
        });

        jQuery('#edcal-title-new-field').bind('keyup', function(evt) {
            if (jQuery('#edcal-title-new-field').val().length > 0 && jQuery('#edcal-time').val().length > 0) {
                jQuery('#newPostScheduleButton').removeClass('disabled');
            } else {
                jQuery('#newPostScheduleButton').addClass('disabled');
            }

            if (evt.keyCode == 13) {    // enter key
                /*
                 * If the user presses enter we want to save the draft.
                 */
                return edcal.savePost(null, true);
            }
        });

        jQuery('#edcal-status').bind('change', function(evt) {
            edcal.updatePublishButton();
        });

        jQuery('#edcal_weeks_pref').live('keyup', function(evt) {
            if (jQuery('#edcal_weeks_pref').val().length > 0) {
                jQuery('#edcal_applyoptions').removeClass('disabled');
            } else {
                jQuery('#edcal_applyoptions').addClass('disabled');
            }

            if (evt.keyCode == 13) {    // enter key
                edcal.saveOptions();
            }

        });

        edcal.savePosition();

        edcal.addOptionsSection();

        jQuery('#edcal-time').timePicker({
            show24Hours: edcal.timeFormat === 'H:i',
            separator: ':',
            step: 30
        });
    },

    /*
       This function updates the text of te publish button in the quick
       edit dialog to match the current operation.
     */
    updatePublishButton: function() {
         if (jQuery('#edcal-status').val() === 'future') {
             jQuery('#newPostScheduleButton').text(edcal.str_publish);
         } if (jQuery('#edcal-status').val() === 'pending') {
             jQuery('#newPostScheduleButton').text(edcal.str_review);
         } else {
             jQuery('#newPostScheduleButton').text(edcal.str_save);
         }
    },

    /*
       This function makes an AJAX call and changes the date of
       the specified post on the server.
     */
    changeDate: function(/*string*/ newdate, /*Post*/ post, /*function*/ callback) {
         edcal.output('Changing the date of "' + post.title + '" to ' + newdate);
         var newdateFormatted = edcal.getDayFromDayId(newdate).toString(edcal.wp_dateFormat);

         var url = edcal.ajax_url() + '&action=edcal_changedate&postid=' + post.id +
             '&postStatus=' + post.status +
             '&newdate=' + newdateFormatted + '&olddate=' + edcal.getDayFromDayId(post.date).toString(edcal.wp_dateFormat);

         jQuery('#post-' + post.id).addClass('loadingclass');

         jQuery.ajax({
            url: url,
            type: 'POST',
            processData: false,
            timeout: 100000,
            dataType: 'json',
            success: function(res) {
                if (res.error) {
                    /*
                     * If there was an error we need to remove the dropped
                     * post item.
                     */
                    edcal.removePostItem(newdate, 'post-' + res.post.id);
                    if (res.error === edcal.CONCURRENCY_ERROR) {
                        edcal.displayMessage(edcal.concurrency_error + '<br />' + res.post.title);
                    } else if (res.error === edcal.PERMISSION_ERROR) {
                        edcal.displayMessage(edcal.permission_error);
                    } else if (res.error === edcal.NONCE_ERROR) {
                        edcal.displayMessage(edcal.checksum_error);
                    }
                }

                edcal.removePostItem(res.post.date, 'post-' + res.post.id);
                edcal.addPostItem(res.post, res.post.date);
                edcal.addPostItemDragAndToolltip(res.post.date);

                if (callback) {
                    callback(res);
                }
            },
            error: function(xhr, textStatus, error) {
                 edcal.showError(edcal.general_error);

                 edcal.output('textStatus: ' + textStatus);
                 edcal.output('error: ' + error);
                 if (xhr.responseText) {
                     edcal.output('changeDate xhr.responseText: ' + xhr.responseText);
                 }
            }
        });

    },

    /*
       Makes an AJAX call to get the posts from the server within the
       specified dates.
     */
    getPosts: function(/*Date*/ from, /*Date*/ to, /*function*/ callback) {
         edcal.output('Getting posts from ' + from + ' to ' + to);

         var shouldGet = edcal.cacheDates[from];

         if (shouldGet) {
             /*
              * TODO: We don't want to make extra AJAX calls for dates
              * that we have already covered.  This is cutting down on
              * it somewhat, but we could get much better about this.
              */
             edcal.output('Using cached results for posts from ' + from.toString('dd-MMM-yyyy') + ' to ' + to.toString('dd-MMM-yyyy'));

             if (callback) {
                 callback();
             }
             return;
         }

         edcal.cacheDates[from] = true;

         var url = edcal.ajax_url() + '&action=edcal_posts&from=' + from.toString('yyyy-MM-dd') + '&to=' + to.toString('yyyy-MM-dd');

         if (edcal.getUrlVars().post_type) {
             url += '&post_type=' + encodeURIComponent(edcal.getUrlVars().post_type);
         }

         jQuery('#loading').show();

         jQuery.ajax({
             url: url,
             type: 'GET',
             processData: false,
             timeout: 100000,
             dataType: 'text',
             success: function(res) {
                jQuery('#loading').hide();
                /*
                 * These result here can get pretty large on a busy blog and
                 * the JSON parser from JSON.org works faster than the native
                 * one used by JQuery.
                 */
                var parsedRes = JSON.parseIt(res);

                if (parsedRes.error) {
                    /*
                     * If there was an error we need to remove the dropped
                     * post item.
                     */
                    if (parsedRes.error === edcal.NONCE_ERROR) {
                        edcal.showError(edcal.checksum_error);
                    }
                    return;
                }
                var postDates = [];

                /*
                   We get the posts back with the most recent post first.  That
                   is what most blogs want.  However, we want them in the other
                   order so we can show the earliest post in a given day first.
                 */
                for (var i = parsedRes.length; i >= 0; i--) {
                    var post = parsedRes[i];
                    if (post) {
                        if (post.status === 'trash') {
                            continue;
                        }

                        /*
                         * In some non-English locales the date comes back as all lower case.
                         * This is a problem since we use the date as the ID so we replace
                         * the first letter of the month name with the same letter in upper
                         * case to make sure we don't get into trouble.
                         */
                        post.date = post.date.replace(post.date.substring(2, 3), post.date.substring(2, 3).toUpperCase());

                        edcal.removePostItem(post.date, 'post-' + post.id);
                        edcal.addPostItem(post, post.date);
                        postDates[postDates.length] = post.date;
                    }
                }

                /*
                 * If the blog has a very larger number of posts then adding
                 * them all can make the UI a little slow.  Particularly IE
                 * pops up a warning giving the user a chance to abort the
                 * script.  Adding tooltips and making the items draggable is
                 * a lot of what makes things slow.  Delaying those two operations
                 * makes the UI show up much faster and the user has to wait
                 * three seconds before they can drag.  It also makes IE
                 * stop complaining.
                 */
                setTimeout(function() {
                    edcal.output('Finished adding draggable support to ' + postDates.length + ' posts.');
                    jQuery.each(postDates, function(i, postDate) {
                        edcal.addPostItemDragAndToolltip(postDate);
                    });
                }, 300);

                if (callback) {
                    callback(res);
                }

             },
             error: function(xhr) {
                edcal.showError(edcal.general_error);
                if (xhr.responseText) {
                    edcal.output('getPosts xhr.responseText: ' + xhr.responseText);
                }
            }
        });
    },

    /*
     * Retreives a single post item based on the id
     * Can optionally pass a callback function that is triggered
     * when the call successfully completes. The post object is passed
     * as a parameter for the callback.
     */
    getPost: function(/*int*/ postid, /*function*/ callback) {

        if (postid === 0) {
            return false;
        }

        // show loading
        jQuery('#loading').show();

        var url = edcal.ajax_url() + '&action=edcal_getpost&postid=' + postid;

        if (edcal.getUrlVars().post_type) {
            url += '&post_type=' + encodeURIComponent(edcal.getUrlVars().post_type);
        }

        jQuery.ajax({
            url: url,
            type: 'GET',
            processData: false,
            timeout: 100000,
            dataType: 'json',
            success: function(res) {
                // hide loading
                jQuery('#loading').hide();

                edcal.output('xhr for getPost returned: ' + res);
                if (res.error) {
                    if (res.error === edcal.NONCE_ERROR) {
                        edcal.showError(edcal.checksum_error);
                    }
                    return false;
                }
                if (typeof callback === 'function') {
                    callback(res.post);
                }
                return res.post;
            },
            error: function(xhr) {
                // hide loading
                jQuery('#loading').hide();

                 edcal.showError(edcal.general_error);
                 if (xhr.responseText) {
                     edcal.output('getPost xhr.responseText: ' + xhr.responseText);
                 }
                 return false;
            }
        });
    },

    /*
       This function adds the scren options tab to the top of the screen.  I wish
       WordPress had a hook so I could provide this in PHP, but as of version 2.9.1
       they just have an internal loop for their own screen options tabs so we're
       doing this in JavaScript.
     */
    addOptionsSection: function() {
         var html =
             '<div class="hide-if-no-js screen-meta-toggle" id="screen-options-link-wrap">' +
                '<a class="show-settings" ' +
                   'id="show-edcal-settings-link" ' +
                   'onclick="edcal.toggleOptions(); return false;" ' +
                   'href="#" ' +
                   'style="background-image: url(images/screen-options-right.gif);">' + edcal.str_screenoptions + '</a>' +
             '</div>';

         jQuery('#screen-meta-links').append(html);
    },

    /*
       Respond to clicks on the Screen Options tab by sliding it down when it
       is up and sliding it up when it is down.
     */
    toggleOptions: function() {
         if (!edcal.helpMeta) {
             /*
                Show the screen options section.  We start by saving off the old HTML
              */
             edcal.helpMeta = jQuery('#contextual-help-wrap').html();

             /*
              * Set up the visible fields option
              */
             var optionsHtml = '<div class="metabox-prefs" id="calendar-fields-prefs">' +
                                '<h5>' + edcal.str_show_opts + '</h5>' +
                                '<label for="author-hide">' +
                                    '<input type="checkbox" ' + edcal.isPrefChecked(edcal.authorPref) + 'value="true" id="author-hide" ' +
                                           'name="author-hide" class="hide-column-tog" />' + edcal.str_opt_author +
                                '</label>' +
                                '<label for="status-hide">' +
                                    '<input type="checkbox" ' + edcal.isPrefChecked(edcal.statusPref) + 'value="true" id="status-hide" ' +
                                           'name="status-hide" class="hide-column-tog" />' + edcal.str_opt_status +
                                '</label>' +
                                '<label for="time-hide">' +
                                    '<input type="checkbox" ' + edcal.isPrefChecked(edcal.timePref) + 'value="true" id="time-hide" ' +
                                           'name="time-hide" class="hide-column-tog" />' + edcal.str_opt_time +
                                '</label>' +
                            '</div>';

             /*
              * Set up the number of posts option
              */
             optionsHtml += '<div class="metabox-prefs">' +
                                '<h5>' + edcal.str_show_title + '</h5>' +
                                '<select id="edcal_weeks_pref" ' + 'class="screen-per-page" title="' + edcal.str_weekstt + '"> ';

             var weeks = parseInt(edcal.weeksPref, 10);
             for (i = 1; i < 6; i++) {
                 if (i === weeks) {
                     optionsHtml += '<option selected="true">' + i + '</option>';
                 } else {
                     optionsHtml += '<option>' + i + '</option>';
                 }
             }

             optionsHtml += '</select>' +
                    edcal.str_opt_weeks +
                 '</div>';

             /*
                I started work on adding a color picker so you could choose the color for
                drafts, published posts, and scheduled posts.  However, that makes the settings
                a lot more complicated and I'm not sure it is worth it.
              */
             //optionsHtml += '<h5>' + edcal.str_optionscolors + '</h5>';
             //optionsHtml += edcal.generateColorPicker(edcal.str_optionsdraftcolor, 'draft-color', 'lightgreen');


             optionsHtml += '<br /><button id="edcal_applyoptions" onclick="edcal.saveOptions(); return false;" class="save button">' + edcal.str_apply + '</button>';

             jQuery('#contextual-help-wrap').html(optionsHtml);

             jQuery('#contextual-help-link-wrap').css('visibility', 'hidden');

             jQuery('#contextual-help-wrap').slideDown('normal');

             jQuery('#show-edcal-settings-link').css('background-image', 'url(images/screen-options-right-up.gif)');
         } else {
             jQuery('#contextual-help-wrap').slideUp('fast');

             /*
              * restore the old HTML
              */
             jQuery('#contextual-help-wrap').html(edcal.helpMeta);

             edcal.helpMeta = null;

             jQuery('#show-edcal-settings-link').css('background-image', 'url(images/screen-options-right.gif)');
             jQuery('#contextual-help-link-wrap').css('visibility', '');
         }
    },

    generateColorPicker: function(/*String*/ title, /*string*/ id, /*string*/ value) {
         var html = '<div id="' + id + '" class="optionscolorrow">';

         html += '<span style="background-color: ' + value + ';" class="colorlabel"> ' + title + '</span> ';

         var colors = ['lightred', 'orange', 'yellow', 'lightgreen', 'lightblue', 'purple', 'lightgray'];

         edcal.output('colors.length: ' + colors.length);
         for (var i = 0; i < colors.length; i++) {
             html += '<a href="#" class="optionscolor ';

             if (colors[i] === value) {
                 html += 'colorselected';

             }

             html += '" class=' + id + colors[i] + '" style="background-color: ' + colors[i] + '; left: ' + ((i * 20) + 50) + 'px" ' +
                 'onclick="edcal.selectColor(\'' + id + '\', \'' + colors[i] + '\'); return false;"></a>';

         }

         html += '</div>';

         return html;

    },

    selectColor: function(/*string*/ id, /*string*/ value) {
         edcal.output('selectColor(' + id + ', ' + value + ')');
         jQuery('#' + id + ' .colorlabel').css('background-color', value);

         jQuery('#' + id + ' .colorselected').removeClass('colorselected');

         jQuery('#' + id + 'value').addClass('colorselected');

    },

    isPrefChecked: function(/*boolean*/ prefVal) {
         if (prefVal) {
             return ' checked="checked" ';
         } else {
             return '';
         }
    },

    /*
       Save the number of weeks options with an AJAX call.  This happens
       when you press the apply button.
     */
    saveOptions: function() {
         /*
            We start by validating the number of weeks.  We only allow
            1, 2, 3, 4, or 5 weeks at a time.
          */
         if (jQuery('#edcal_weeks_pref').val() !== '1' &&
             jQuery('#edcal_weeks_pref').val() !== '2' &&
             jQuery('#edcal_weeks_pref').val() !== '3' &&
             jQuery('#edcal_weeks_pref').val() !== '4' &&
             jQuery('#edcal_weeks_pref').val() !== '5') {
             humanMsg.displayMsg(edcal.str_weekserror);
             return;
         }

         var url = edcal.ajax_url() + '&action=edcal_saveoptions&weeks=' +
             encodeURIComponent(jQuery('#edcal_weeks_pref').val());

         jQuery('#calendar-fields-prefs').find('input, textarea, select').each(function() {
             url += '&' + encodeURIComponent(this.name) + '=' + encodeURIComponent(this.checked);
         });

         jQuery.ajax({
             url: url,
             type: 'POST',
             processData: false,
             timeout: 100000,
             dataType: 'text',
             success: function(res) {
                /*
                   Now we refresh the page because I'm too lazy to
                   make changing the weeks work inline.
                 */
                window.location.href = window.location.href;
             },
             error: function(xhr) {
                edcal.showError(edcal.general_error);
                if (xhr.responseText) {
                    edcal.output('saveOptions xhr.responseText: ' + xhr.responseText);
                }
            }
        });
    },

    /**
     * Outputs info messages to the Firebug console if it is available.
     *
     * msg    the message to write.
     */
    output: function(msg) {
        if (window.console) {
            console.info(msg);
        }
    },

    /*
     * Shows an error message and sends the message as an error to the
     * Firebug console if it is available.
     */
    showError: function(/*string*/ msg) {
        if (window.console) {
            console.error(msg);
        }

        edcal.displayMessage(msg);

    },

    /*
     * Display an error message to the user
     */
    displayMessage: function(/*string*/ msg) {
         humanMsg.displayMsg(msg);
    },

    /*
     * A helper function to get the parameters from the
     * current URL.
     */
    getUrlVars: function() {
         var vars = [], hash;
         var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
         for (var i = 0; i < hashes.length; i++) {
             hash = hashes[i].split('=');
             vars.push(hash[0]);
             vars[hash[0]] = hash[1];
         }

         return vars;
    },

    /*
     * Show an error indicating the calendar couldn't be loaded
     */
    showFatalError: function(message) {
        jQuery('#edcal_main_title').after(
            '<div class="updated below-h2" id="message"><p>' +
            edcal.str_fatal_error + message + '<br></p></div>');

        if (window.console) {
            console.error(msg);
        }
    }
};

/*
 * Helper function for jQuery to center a div
 */
jQuery.fn.center = function() {
    this.css('position', 'absolute');
    this.css('top', (jQuery(window).height() - this.outerHeight()) / 2 + jQuery(window).scrollTop() + 'px');
    this.css('left', (jQuery(window).width() - this.outerWidth()) / 2 + jQuery(window).scrollLeft() + 'px');
    return this;
};


jQuery(document).ready(function() {
    try {
        edcal.init();
    } catch (e) {
        /*
         * These kinds of errors often happen when there is a
         * conflict with a JavaScript library imported by
         * another plugin.
         */
        edcal.showFatalError(e.description);
    }

    /*
     * The calendar supports unit tests through the QUnit framework,
     * but we don't want to load the extra files when we aren't running
     * tests so we load them dynamically.  Add the qunit=true parameter
     * to run the tests.
     */
    if (edcal.getUrlVars().qunit) {
        edcal_test.runTests();
    }
});
