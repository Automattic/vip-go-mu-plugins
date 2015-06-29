<?php
if (!function_exists('mysiteapp_facebook_comments_page')):
/**
 * Comment using facebook page
 */
function mysiteapp_facebook_comments_page() {

	$url = esc_url_raw( $_GET['url'] );
    if (stripos($url, home_url('/')) === false) {
        // Validate this url of our blog!
        return;
    }

	$screen = absint( $_GET['screen'] );
	$app_id = absint( $_GET['app'] );

	$encoded_url = urlencode($url.'&screen='.$screen.'&app='.$app_id.'&msa_facebook_comment_page=1');

	$screen_width = '320';

	if ( $screen ) {
		$screen_width = $screen;
	}

?><html xmlns="http://www.w3.org/1999/xhtml" xmlns:fb="http://www.facebook.com/2008/fbml">
<head>

<meta name="viewport" content="width=<?php echo esc_attr( $screen_width ); ?>,user-scalable=false" />
<meta name="viewport" content="initial-scale=1.0" />
</head>
<body>
<div id="fb-root"></div>
<script>
window.fbAsyncInit = function() {
	FB.init({appId: '<?php echo esc_js( $app_id ); ?>', status: true, cookie: true, xfbml: true});
		FB.getLoginStatus(function(response) {
			if (response.session) {
				login();
			} else { 
				FB.api('/me', function(response) {
					window.location = 'http://www.facebook.com/dialog/oauth/?scope=publish_stream&client_id=<?php echo esc_attr( $app_id ); ?>&redirect_uri=<?php echo urlencode( home_url() ); ?>&url=<?php echo $encoded_url?>&response_type=token';
				});
			}
		});     
	};
  // Load the SDK Asynchronously
  (function(d){
     var js, id = 'facebook-jssdk'; if (d.getElementById(id)) {return;}
     js = d.createElement('script'); js.id = id; js.async = true;
     js.src = "//connect.facebook.net/en_US/all.js";
     d.getElementsByTagName('head')[0].appendChild(js);
   }(document));
</script>

<div class="fb-comments" data-href="<?php echo esc_url( $url ); ?>" data-num-posts="0" data-width="<?php echo esc_attr( $screen_width ); ?>"></div>
</body>
</html>
<?php
}
endif;
// mysiteapp_facebook_comments_page