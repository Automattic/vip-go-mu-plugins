<script type="text/javascript">

//Lightweight JSONP fetcher - www.nonobtrusive.com
var JSONP=(function(){var a=0,c,f,b,d=this;function e(j){var i=document.createElement("script"),h=false;i.src=j;i.async=true;i.onload=i.onreadystatechange=function(){if(!h&&(!this.readyState||this.readyState==="loaded"||this.readyState==="complete")){h=true;i.onload=i.onreadystatechange=null;if(i&&i.parentNode){i.parentNode.removeChild(i)}}};if(!c){c=document.getElementsByTagName("head")[0]}c.appendChild(i)}function g(h,j,k){f="?";j=j||{};for(b in j){if(j.hasOwnProperty(b)){f+=b+"="+j[b]+"&"}}var i="json"+(++a);d[i]=function(l){k(l);d[i]=null;try{delete d[i]}catch(m){}};e(h+f+"callback="+i);return i}return{get:g}}());

var secondsPassed = 0;
var stub = "Progress: ";

function checkStatusLF(){
    JSONP.get( '<?php echo esc_js(LFAPPS_Comments_Core::$quill_url); ?>/import/wordpress/<?php echo esc_js(get_option("livefyre_apps-livefyre_site_id")); ?>/status', {param1:'none'}, function(data){
        console.log('REPSONSE:', data);
        var status = data['status'],
            loc = '?page=livefyre_apps_comments';

        switch(status) {
            case 'aborted':
            case 'failed':
                // Statuses that signal a stopping point in the process.
                loc += '&status=error';
                if (data['import_failure'] && data['import_failure']['message']) {
                    loc += '&message=' + data['import_failure']['message'];
                }
                window.location.href = loc;
                break;
            
            default:
                secondsPassed++;
                if(secondsPassed <= 20) {
                    message = "Warming up the engine...";
                }
                else if(secondsPassed >= 20 && secondsPassed < 60) {
                    message = "Starting the move...";
                }
                else if(secondsPassed >= 60 && secondsPassed < 30) {
                    message = "Hang tight, work in progress...";
                }
                else if(secondsPassed >= 300 && secondsPassed < 600) {
                    message = "We're still cranking away!";
                }
                else if(secondsPassed >= 600 && secondsPassed < 1800) {
                    message = "Maybe it's time for a candy bar.";
                }
                else if(secondsPassed >= 1800 && secondsPassed < 2700) {
                    message = 'In the meantime, check out our Facebook page at <a href="http://www.facebook.com/livefyre">facebook.com/livefyre</a>';
                }
                else if(secondsPassed >= 2700 && secondsPassed < 3600) {
                    message = "Boy, you have one popular website...";
                }
                else {
                    message = "Still working here. Thanks for your patience.";
                }
                document.getElementById("livefyre-import-text").innerHTML = stub + message;
        }
        if (status === 'complete') {
            window.location.href = window.location.href.split('?')[0] + '?page=livefyre_apps_comments';
        }
    });
}

function livefyre_start_ajax(iv) {
    window.checkStatusInterval=setInterval(
        checkStatusLF, 
        iv
    );
    checkStatusLF();
}

</script>