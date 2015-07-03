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
 * This file contains unit tests for the editorial calendar.  It is only loaded
 * if you add the qunit=true parameter to the URL for the calendar.
 */

var edcal_test = {

    post: {},

    testContent: 'This is the content of the <b>unit test &#8211 post</b>. <!--more--> This is content after the more tag to make sure we a reading it.',
    testContent2: 'This is the content of the <b>unit test &#8211 post</b>. <!--more--> This is content after the more tag to make sure we a reading it. - CHANGED',


    runTests: function() {
        jQuery('head').append('<link>');
        css = jQuery('head').children(':last');
        css.attr({
            rel: 'stylesheet',
            type: 'text/css',
            href: '../wp-content/plugins/edcal/lib/qunit.css'
        });

        jQuery('#wpbody-content .wrap').append('<div id="edcal-qunit"></div>');

        jQuery('#edcal-qunit').append('<h1 id="qunit-header">WordPress Editorial Calendar Unit Tests</h1>' +
                                      '<h2 id="qunit-banner"></h2>' + 
                                      '<div id="qunit-testrunner-toolbar"></div>' + 
                                      '<h2 id="qunit-userAgent"></h2>' + 
                                      '<ol id="qunit-tests"></ol>' + 
                                      '<div id="qunit-fixture">test markup</div>');


        edcal_test.moveTests();
    },

    getFirstDate: function() {
         var api = jQuery('#edcal_scrollable').scrollable();
         var items = api.getVisibleItems();

         return edcal.getDayFromDayId(items.eq(0).children('.row').children('.day:first').attr('id'));
    },

    getLastDate: function() {
         var api = jQuery('#edcal_scrollable').scrollable();
         var items = api.getVisibleItems();

         return edcal.getDayFromDayId(items.eq(edcal.weeksPref - 1).children('.row').children('.day:last').attr('id'));
    },

    moveTests: function() {
         var curSunday = edcal.nextStartOfWeek(Date.today()).add(-1).weeks();

         edcal.moveTo(Date.today());

         /*
          * We'll start of with a series of tests about moving the calendar around
          */
         test('Move to today and check visible dates', function() {
             expect(2);
             ok(edcal_test.getFirstDate().equals(curSunday.clone()), 'firstDate should match ' + curSunday);

             ok(edcal_test.getLastDate().equals(curSunday.clone().add(edcal.weeksPref).weeks().add(-1).days()),
                'lastDate should match ' + curSunday);
         });

         asyncTest('Move 1 week in the future and check visible dates', function() {
             expect(2);
             edcal.move(1, true, function() {
                 ok(edcal_test.getFirstDate().equals(curSunday.clone().add(1).weeks()), 'firstDate should match ' + curSunday);

                 ok(edcal_test.getLastDate().equals(curSunday.clone().add(edcal.weeksPref).weeks().add(-1).days().add(1).weeks()),
                    'lastDate should match ' + curSunday);

                 edcal.move(1, false, function() {
                     start();
                     edcal_test.testMoveFourWeeks();
                 });
             });
         });

         return;

    },

    testMoveFourWeeks: function() {
         var curSunday = edcal.nextStartOfWeek(Date.today()).add(-1).weeks();

         /*
          * Now we'll move 4 weeks into the future
          */
         asyncTest('Move 4 week in the future and check visible dates', function() {
             expect(2);

             edcal.move(4, true, function() {
                 ok(edcal_test.getFirstDate().equals(curSunday.clone().add(4).weeks()), 'firstDate should match ' + curSunday);

                 ok(edcal_test.getLastDate().equals(curSunday.clone().add(edcal.weeksPref).weeks().add(-1).days().add(4).weeks()),
                    'lastDate should match ' + curSunday);

                 edcal.move(4, false, function() {
                     start();
                     edcal_test.testMoveEightWeeks();
                 });
             });


         });
    },

    testMoveEightWeeks: function() {
         var curSunday = edcal.nextStartOfWeek(Date.today()).add(-1).weeks();

         /*
          * Now 8 weeks into the past
          */
         asyncTest('Move 8 week in the past and check visible dates', function() {
             expect(2);

             edcal.move(8, false, function() {
                 ok(edcal_test.getFirstDate().equals(curSunday.clone().add(-8).weeks()), 'firstDate should match ' + curSunday);

                 ok(edcal_test.getLastDate().equals(curSunday.clone().add(edcal.weeksPref).weeks().add(-1).days().add(-8).weeks()),
                    'lastDate should match ' + curSunday);

                 edcal.move(8, true, function() {
                     start();
                     edcal_test.testCreatePost();
                 });
             });


         });
    },

    testCreatePost: function() {
         /*
          * Now we'll do a few tests about creating, modifying, and deleting posts.
          */

         asyncTest('Create a new post', function() {
             expect(3);

             edcal_test.post.title = 'Unit Test Post';
             edcal_test.post.content = edcal_test.testContent;
             edcal_test.post.status = 'draft';
             edcal_test.post.time = '10:00 AM';
             edcal_test.post.date = Date.today().add(7).days().toString(edcal.internalDateFormat);
             edcal_test.post.id = '0';

             edcal.savePost(edcal_test.post, false, false, function(res) {
                 if (!res.post) {
                     ok(false, 'There was an error creating the new post.');
                     start();
                     return;
                 }

                 equals(res.post.date, edcal_test.post.date, 'The resulting post should have the same date as the request');
                 equals(res.post.title, edcal_test.post.title, 'The resulting post should have the same title as the request');

                 equals(jQuery('#post-' + res.post.id).length, 1, 'The post should be added in only one place in the calendar.');

                 edcal_test.post = res.post;
                 
                 start();

                 edcal_test.testGetPost();
             });
         });

    },

    testGetPost: function() {
         /*
          * We'll start by getting data about the post we've just created
          */

         asyncTest('Get post information', function() {
             expect(3);

             edcal.getPost(edcal_test.post.id, function(post) {
                 equals(post.date, edcal_test.post.date, 'The resulting post should have the same date as the request');
                 equals(post.title, edcal_test.post.title, 'The resulting post should have the same title as the request');
                 equals(post.content, edcal_test.testContent, 'The resulting post content should be the same as the test post content');

                 edcal_test.post = post;

                 start();

                 edcal_test.testMovePost();
             });
         });

    },

    testMovePost: function() {

         asyncTest('Change the date of an existing post', function() {
             expect(2);

             // We added the post one week in the future, now we will move it
             // one day after that.
             var newDate = Date.today().add(8).days().toString(edcal.internalDateFormat);

             edcal.doDrop(edcal_test.post.date, 'post-' + edcal_test.post.id, newDate, function(res) {

                 if (!res.post) {
                     ok(false, 'There was an error creating the new post.');
                     return;
                 }

                 equals(res.post.date, newDate, 'The resulting post should have the same date as the request');

                 equals(jQuery('#post-' + res.post.id).length, 1, 'The post should be added in only one place in the calendar.');

                 edcal_test.post = res.post;

                 start();

                 edcal_test.testMovePostOneWeek();
             });
         });

    },

    testMovePostOneWeek: function() {

         asyncTest('Make a second change to the date of an existing post', function() {
             expect(2);

             // We added the post one week in the future, now we will move it
             // one day after that.
             var newDate = Date.today().add(22).days().toString(edcal.internalDateFormat);

             edcal.doDrop(edcal_test.post.date, 'post-' + edcal_test.post.id, newDate, function(res) {

                 if (!res.post) {
                     ok(false, 'There was an error creating the new post.');
                     return;
                 }

                 equals(res.post.date, newDate, 'The resulting post should have the same date as the request');

                 equals(jQuery('#post-' + res.post.id).length, 1, 'The post should be added in only one place in the calendar.');

                 edcal_test.post = res.post;

                 start();

                 edcal_test.testEditPost();
             });
         });

    },

    testEditPost: function() {

         asyncTest('Edit the content of an existing post and mark it as scheduled', function() {
             expect(2);

             edcal_test.post.title = 'Unit Test Post &#8211 Changed';
             edcal_test.post.content = edcal_test.testContent2;

             edcal.savePost(edcal_test.post, false, true, function(res)
                {
                    if (!res.post) {
                        ok(false, 'There was an error editing the post.');
                        start();
                        return;
                    }

                    equals(res.post.title, edcal_test.post.title, 'The resulting post should have the same title as the request');
                    
                    equals(jQuery('#post-' + res.post.id).length, 1, 'The post should be added in only one place in the calendar.');

                    edcal_test.post = res.post;

                    start();

                    edcal_test.testGetAfterEdit();

                });
         });

    },
    
    testGetAfterEdit: function() {
         /*
          * Now we'll test to make sure our new post data still matches what we think it should
          */

         asyncTest('Get post information after editing', function() {
             expect(3);

             edcal.getPost(edcal_test.post.id, function(post) {
                 equals(post.date, edcal_test.post.date, 'The resulting post should have the same date as the request');
                 equals(post.title, edcal_test.post.title, 'The resulting post should have the same title as the request');
                 equals(post.content, edcal_test.testContent2, 'The resulting post content should be the same as the test post content');

                 edcal_test.post = post;

                 start();

                 edcal_test.testDateConflict();
             });
         });

    },

    testDateConflict: function() {
         asyncTest('Try to change a post date and fail because of a concurrency conflict', function() {
             expect(2);

             edcal_test.post.date = Date.today().add(-1).days().toString(edcal.internalDateFormat);

             /*
              * We added the post one week in the future, now we will move it
              * one day after that.
              */
             var newDate = Date.today().add(8).days().toString(edcal.internalDateFormat);

             edcal.changeDate(newDate, edcal_test.post, function(res)
                {
                    if (!res.post) {
                        ok(false, 'There was an error with the change date conflict.');
                        return;
                    }

                    equals(res.error, edcal.CONCURRENCY_ERROR, 'This move should show an exception because it is in conflict.');

                    equals(jQuery('#post-' + res.post.id).length, 1, 'The post should be added in only one place in the calendar.');

                    edcal_test.post = res.post;

                    start();

                    edcal_test.testDeletePost();

                });
         });

    },

    testDeletePost: function() {

         /*
          * The last step is to delete the post we made so
          * the test cleans up after itself.
          */
         asyncTest('Delete the post created for testing', function() {
             expect(1);

             edcal.deletePost(edcal_test.post.id, function(res)
                {
                    if (!res.post) {
                        ok(false, 'There was an error creating the new post.');
                        start();
                        return;
                    }

                    equals(jQuery('#post-' + res.post.id).length, 0, 'The post should now be deleted from the calendar.');
                    start();

                });
         });
    }
};
