jQuery(document).ready(function($) {
    if($("#lfapps-env-cancel-btn").length) {
        $("#lfapps-env-cancel-btn").click(function(e) {
            e.preventDefault();
            tb_remove();
        });
    }
    if($("#lfapps-env-submit-btn").length) {
        $("#lfapps-env-submit-btn").click(function(e) {
            e.preventDefault();
            location.href = $("#lfapps-env-url").val() + '&type=' + $("input[name='lfapps-env']:checked").val();
        });
    }
    if($(".lfapps-change-env-btn").length) {
        $(".lfapps-change-env-btn").click(function(e) {
            e.preventDefault();
            tb_show("","#TB_inline?inlineId=lfapps-initial-modal&width=680&height=310");
        });
    }
    if($("#lfapps_comments_enable").length) {
        $("#lfapps_comments_enable").change(function() {
            var cur_src = $("#lfapps_comments_icon").attr('src');
            var new_src = cur_src;
            if($(this).is(':checked')) {                
                new_src = cur_src.replace("lf-comments-icon-grey.png", "lf-comments-icon.png");                
            } else {
                new_src = cur_src.replace("lf-comments-icon.png", "lf-comments-icon-grey.png");   
            }
            $("#lfapps_comments_icon").attr('src', new_src);
        });
    }
    if($("#lfapps_sidenotes_enable").length) {
        $("#lfapps_sidenotes_enable").change(function() {
            var cur_src = $("#lfapps_sidenotes_icon").attr('src');
            var new_src = cur_src;
            if($(this).is(':checked')) {                
                new_src = cur_src.replace("lf-sidenotes-icon-grey.png", "lf-sidenotes-icon.png");                
            } else {
                new_src = cur_src.replace("lf-sidenotes-icon.png", "lf-sidenotes-icon-grey.png");   
            }
            $("#lfapps_sidenotes_icon").attr('src', new_src);
        });
    }
    if($("#lfapps_blog_enable").length) {
        $("#lfapps_blog_enable").change(function() {
            var cur_src = $("#lfapps_blog_icon").attr('src');
            var new_src = cur_src;
            if($(this).is(':checked')) {                
                new_src = cur_src.replace("lf-blog-icon-grey.png", "lf-blog-icon.png");                
            } else {
                new_src = cur_src.replace("lf-blog-icon.png", "lf-blog-icon-grey.png");   
            }
            $("#lfapps_blog_icon").attr('src', new_src);
        });
    }
    if($("#lfapps_chat_enable").length) {
        $("#lfapps_chat_enable").change(function() {
            var cur_src = $("#lfapps_chat_icon").attr('src');
            var new_src = cur_src;
            if($(this).is(':checked')) {                
                new_src = cur_src.replace("lf-chat-icon-grey.png", "lf-chat-icon.png");                
            } else {
                new_src = cur_src.replace("lf-chat-icon.png", "lf-chat-icon-grey.png");   
            }
            $("#lfapps_chat_icon").attr('src', new_src);
        });
    }
    if($('#lfapps .lfapps-access-container').length) {
        $('#lfapps .lfapps-access-container a.nav-tab').click(function() {
            if($(this).hasClass('lfapps-tab-community')) {
                if(!$(".lfapps-tab-community").hasClass('nav-tab-active')) {
                    $(".lfapps-tab-community").addClass('nav-tab-active');
                    $(".lfapps-tab-enterprise").removeClass('nav-tab-active');
                }                
                $(".community-only").show();
                $(".enterprise-only").hide();
                $("#package_type").val('community');
            } else {
                if(!$(".lfapps-tab-enterprise").hasClass('nav-tab-active')) {
                    $(".lfapps-tab-community").removeClass('nav-tab-active');
                    $(".lfapps-tab-enterprise").addClass('nav-tab-active');
                }
                $(".community-only").hide();
                $(".enterprise-only").show();
                $("#package_type").val('enterprise');
            }
        });
    }
    
    if($('#lfapps input[name="livefyre_apps-auth_type"]').length) {
        if($('#lfapps input[name="livefyre_apps-auth_type"]:checked').val() === 'auth_delegate') {
            if($(".enterprise-only").is(':visible')) {
                $(".authdelegate-only").show();
            }            
        } else {
            $(".authdelegate-only").hide();
        }
        $('#lfapps input[name="livefyre_apps-auth_type"]').click(function() {
            if($(this).val() === 'auth_delegate') {
                $(".authdelegate-only").show();
            } else {
                $(".authdelegate-only").hide();                
            }
        });
    }
});
