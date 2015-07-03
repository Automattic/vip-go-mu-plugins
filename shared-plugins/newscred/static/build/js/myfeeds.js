var $nc = jQuery.noConflict();

(function($){
    var ncApp = ncApp || {};
    var NcAjax = nc_ajax;

    // add create api nonce

    $("#nc_create_apicall_nonce").val(NcAjax.nc_create_apicall_nonce);
    // show myFeeds Form content
    $( "#add-new-myfeeds" ).click( showMyFeedsForm );
    // cancel myFeeds
    $( "#myFeedsAddCancel" ).click( function () {
        $( ".nc-admin-form" ).effect( "drop" );
        $( "#add-new-myfeeds" ).show();
    } );

    if ( $( "#myfeed-autopublish" ).is( ":checked" ) )
        autoPublishClick();
    // click auto publish
    $( "#myfeed-autopublish" ).on( "click", autoPublishClick );

    // create new category
    $( "#myfeed-create-category" ).on( "click", function () {
        $( "#myfeed-category-box" ).show( "drop" );
    } )


    if ( $( "#myfeed-autopublish" ).is( ":checked" ) ) {
        $( "#myfeeds-settings" ).slideToggle();
    }


    // add new wp category for my feed insert
    $( "#add_feed_category" ).click( addWpCategory );

    // add new wp category IF HIT ENTER
    $( "#myfeed-category-box #category" ).keyup( function ( e ) {
        return false;
    } );
    $( "#myfeed-category-box #category" ).keypress( function ( e ) {
        if ( e.which == 13 ) {
            $( "#add_feed_category" ).trigger( "click" );
            return false;
        }
    } );


    // myFeeds create API modal
    $( "#nc_api_create" ).colorbox( {inline:true, width:"730px", scrolling:true} );

    $( "#cat-select-all" ).click( selectAllCategory );
    $( ".nc-api-cat-list input" ).click( unselectAllCategory );

    $( "#source_filter_name" ).select2( {
        placeholder:"Select a Source List",
        allowClear:true

    } );


    // source search list
    $( '#source_guids' ).select2( {
        minimumInputLength:2,
        multiple:true,
        ajax:{ // instead of writing the function to execute the request we use Select2's convenient helper
            url:NcAjax.ajaxurl,
            dataType:'json',
            data:function ( term ) { // page is the one-based page number tracked by Select2
                return {
                    term:term,
                    action:"ncajax-source-submit",
                    nc_get_sources_nonce : NcAjax.nc_get_sources_nonce
                };
            },
            results:function ( data ) { // parse the results into the format expected by Select2.
                // since we are using custom formatting functions we do not need to alter remote JSON data
                return {results:data};
            }
        }
    } );


    // topic filter name list
    $( "#topic_filter_name" ).select2( {
        placeholder:"Select a Topic List",
        allowClear:true

    } );

    // topic search list
    $( '#topic_guids' ).select2( {
        minimumInputLength:2,
        multiple:true,
        ajax:{ // instead of writing the function to execute the request we use Select2's convenient helper
            url:NcAjax.ajaxurl,
            dataType:'json',
            data:function ( term ) { // page is the one-based page number tracked by Select2
                return {
                    term:term,
                    action:"ncajax-topic-submit",
                    nc_get_topics_nonce : NcAjax.nc_get_topics_nonce
                };
            },
            results:function ( data ) { // parse the results into the format expected by Select2.
                // since we are using custom formatting functions we do not need to alter remote JSON data
                return {results:data};
            }
        }
    } );

    $( document ).on( 'mousedown touchstart', closeSelect2Dropdown );

    // create api call
    $( "#myfeed-api-form" ).submit( createApiCall );
    $( ".nc-update-myfeeds-cron" ).click( updateMyfeedCron );


    //myFeeds publish status change
    $( "#publish_status" ).change( function () {
        if ( $( this ).val() == 1 )
            $( "#feature_image_box" ).show( "drop" );
        else
            $( "#feature_image_box" ).hide( "drop" );

    } );

    if ( $( "#publish_status" ).val() == 1 )
        $( "#feature_image_box" ).show( "drop" );

    $("#postCommentNonce").val(NcAjax.postCommentNonce);



    // methods


    /**
     *  Update myFeeds cron
     *  It will reschedule the auto publish cron
     */
    function updateMyfeedCron() {

        $( this ).next( ".nc-search-loading-myfeeds" ).css( "visibility", "visible" );

        var query_data = {};
        query_data['id'] = $( this ).attr( "myFeedid" );
        query_data['action'] = "ncajax-update-myfeed-cron" ;
        query_data['nc_myfeeds_update_corn_nonce'] = NcAjax.nc_myfeeds_update_corn_nonce ;

        var currentThis = $( this );

        $.ajax( {
            url:NcAjax.ajaxurl,
            type:'POST',
            data:query_data,
            success:function ( response ) {
                currentThis.next( ".nc-search-loading-myfeeds" ).css( "visibility", "hidden" );
                if ( response ) {
                    currentThis.closest( ".alternate" ).find( ".publish_time" ).html( response );
                }
            },
            timeout:90000,
            error:function () {
                currentThis.next( ".nc-search-loading-myfeeds" ).css( "visibility", "hidden" );
            }
        } );

        return false;
    }


    /**
     * Show myFeeds form
     */
    function showMyFeedsForm() {
        $( "#nc-myFeeds-form" ).find( 'input[type=text], textarea' ).val( '' );
        $( "#myfeed_category" ).val( [] );
        $( "#myfeed-autopublish" ).removeAttr( "checked" );
        autoPublishClick();
        $( "#submit" ).val( "Add MyFeeds" );
        $( ".nc-admin-form" ).show( "drop" );
        $( this ).hide();
    }

    /**
     *  Add new wordpress category
     *  while adding a new myFeeds .
     */
    function addWpCategory() {

        var query_data = {};
        var cat = $( "#category" ).val();
        if(!cat) return false;

        query_data['cat'] = cat;
        query_data['action'] = "ncajax-add-myfeed-category"
        query_data['nc_add_category_nonce'] = NcAjax.nc_add_category_nonce ;


        $( ".nc-category-loading" ).css( "visibility", "visible" );

        if ( cat ) {

            $.ajax( {
                url:NcAjax.ajaxurl,
                type:'POST',
                data:query_data,
                success:function ( response ) {
                    $( ".nc-category-loading" ).css( "visibility", "hidden" );
                    if ( response ) {
                        $( '#myfeed_category' ).append( '<option value="' + response + '" selected="selected">' + cat + '</option>' );
                        $( '#myfeed_category option:last' ).focus();
                    }

                },
                timeout:90000,
                error:function () {
                    $( ".nc-category-loading" ).css( "visibility", "visible" );
                }
            } );
        }
        return false;
    }

    /**
     *
     * @param event
     */
    function closeSelect2Dropdown( event ) {
        $( "#source_filter_name" ).select2( 'close' );
        $( "#source_guids" ).select2( 'close' );
        $( "#topic_filter_name" ).select2( 'close' );
        $( "#topic_guids" ).select2( 'close' );
    }

    /***
     *  auto publish click disable/enable the
     *  fields related to the auto publish
     * @param e
     */
    function autoPublishClick( e ) {
        var mythis = $("#myfeed-autopublish");
        if ( mythis.is( ":checked" ) ) {
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-on" ).removeClass( "nc-opacity-on" ).addClass( "nc-opacity-off" );
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-off input" ).removeAttr( "disabled" );
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-off select" ).removeAttr( "disabled" );
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-off a" ).removeAttr( "disabled" );
        }
        else {
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-off" ).removeClass( "nc-opacity-off" ).addClass( "nc-opacity-on" );
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-on input" ).attr( "disabled", "" );
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-on select" ).attr( "disabled", "" );
            mythis.closest( ".feature-group" ).find( "li.nc-opacity-on a" ).attr( "disabled", "" );
        }
    }

    /**
     *  Create a new api call
     */
    function createApiCall() {

        $( ".nc-search-loading-right" ).css( "visibility", "visible" );

        $.ajax( {
            url:NcAjax.ajaxurl,
            type:'POST',
            data:$( "#myfeed-api-form" ).serialize(),
            success:function ( response ) {
                $( "#apicall" ).val( response )
                $( ".nc-search-loading-right" ).css( "visibility", "hidden" );
                $( "#cboxClose" ).trigger( "click" );
            },
            timeout:90000,
            error:function () {
                $( ".nc-search-loading-right" ).css( "visibility", "hidden" );
                $( "#cboxClose" ).trigger( "click" );

            }
        } );

        return false;
    }

    /**
     * Select all category
     */
    function selectAllCategory() {
        if ( $( this ).is( ":checked" ) )
            $( this ).closest( "li" ).find( ".nc-api-cat-list input" ).removeClass( "myfeed-category-chk" ).attr( "checked", "" ).addClass( "myfeed-category-off" );
        else
            $( this ).closest( "li" ).find( ".nc-api-cat-list input" ).removeClass( "myfeed-category-off" ).removeAttr( "checked" ).addClass( "myfeed-category-chk" );

    }

    /**
     * unselect all category
     */
    function unselectAllCategory() {
        if ( !$( this ).is( ":checked" ) ) {
            $( "#cat-select-all" ).removeAttr( "checked" );
        }
    }

})($nc);



