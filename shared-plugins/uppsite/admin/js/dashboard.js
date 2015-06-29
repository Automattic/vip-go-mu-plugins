/** Functions for UppSite dashboard */
function uppsite_change_page(data) {
    var href = '?page=uppsite-settings';
    if (typeof data != "object" && data == "refresh") {
        // Asked to refresh the page, due to expired session
        href = window.location.href;
    } else {
        // Navigate to another page
        href += (data.section != "home") ? "-" + data.section : "";
        if (typeof data.sub != 'undefined' && data.sub != "home") {
            href += '&sub=' + data.sub;
        }
    }
    window.location.href = href;
}
function uppsite_admin_get_images(page_obj){
    jQuery.get(ajaxurl,
        { // Params
            action: 'uppsite_get_info',
            uppsite_request: 'bizimages',
            page: page_obj.page
        },
        function( response ) { // Callback function
            var iframe = document.getElementById('uppsiteFrame').contentWindow;
            var repsoneObj = new Object();
            repsoneObj.data = response;
            repsoneObj.domid = page_obj.id;
            pm({
                target: iframe,
                type: "images_callback",
                data: repsoneObj
            });
        }
    );
}
pm.bind("uppsite_remote", uppsite_change_page);
pm.bind("uppsite_get_images", uppsite_admin_get_images);
pm.bind("uppsite_iframe_height", function (data) {
    jQuery('#uppsiteFrame').css('height', data);
});
pm.bind("uppsite_scroll_top", function (data) {
    jQuery("body").animate({scrollTop:0}, 400);
});