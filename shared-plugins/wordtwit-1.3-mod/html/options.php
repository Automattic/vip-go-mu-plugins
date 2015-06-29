<script language="javascript">
function showshortenersettings() {
    var $el = jQuery( '#shortcode-data-bitly' );
    if ( jQuery( '#shortcode-data-bitly:visible' ).length == 0) {
        $el.show();
    }
    else {
        $el.hide();
    }
 }
</script> 

<div class="wrap" id="wordtwit">
      <div class="plugin-section">
         <div id="top-logo-area">
            <a href="http://www.bravenewcode.com" target="_blank" ><img src="<?php echo esc_url( home_url() ); ?>/wp-content/themes/vip/plugins/wordtwit/images/logo.png"alt="WordTwit" /></a>
         </div>
         <div id="version">
            <?php global $wordtwit_version; ?>
            <?php echo esc_html( __('Version') . ' ' . $wordtwit_version ); ?>
         </div>
      </div>
      
      <div class="plugin-section bottom-spacer">
         <div class="section-info">
            <h3>News &amp; Updates</h3>
            
            BraveNewCode.com entries tagged 'WordTwit'. This list updates to provide you with the latest information about our plugin's development.
         </div>
         
         <div class="section-info">
            <div id="news-area">
               &nbsp;
            </div>       
         </div>
         
         <div class="section-info">
            <h3>Donate to WordTwit</h3>
            
            WordTwit represents many hours of hard work, and requires constant interaction with members from the community to make it a success. <br /><br /> If you'd like to support the WordTwit project, please consider <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=paypal%40bravenewcode%2ecom&amp;item_name=WordTwit%20Beer%20Fund&amp;no_shipping=1&amp;tax=0&amp;currency_code=CAD&amp;lc=CA&amp;bn=PP%2dDonationsBF&charset=UTF%2d8">donating to the WordTwit beer fund</a>. 
         </div>         
         
         <div class="wordtwit-clearer"></div>
      </div>
         
      <div class="plugin-section bottom-spacer">
         <div class="section-info">
         <h3>General Options</h3>       
            WordTwit allows you to publish a Twitter tweet whenever a new blog entry is published.  To enable it, simply authorize your Twitter account.<br /><br />
            
            You can also customize the message Twitter posts to your account by using the "message" field below.  You can use [title] to represent the title of the blog entry, and [link] to represent the permalink.
         </div>
         
         <div class="editable-area">         
            <form method="post" action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>">
               <table class="form-table" cols="2">
               	<tr>
               		<th>Authorization</th>
               		<td><label for="reauthorize"><input type="submit" name="reauthorize" value="Connect to Twitter account" /> current status: <?php echo esc_html( $twitter_status ); ?></label></td>
               	</tr>
               	<tr>
                     <th>Message</th>
                     <td><input type="text" name="message" value="<?php echo esc_attr( $message ); ?>" size="70" /></td>
                </tr>
                <tr>
                     <th>User override</th>
                               <td><label for="user_override"><input name="user_override" type="checkbox" id="user_override" value="true" <?php echo ($user_override) ? 'checked="checked"' : ''; ?>"  /> Users can override this settings</label></td>
                </tr>
                <tr>
                     <th>User preference</th>
                     <td><label for="user_preference"><input name="user_preference" type="checkbox" id="user_preference" value="true" <?php echo ($user_preference) ? 'checked="checked"' : ''; ?>"  /> Use only user data, don't fallback to general settings</label></td>
                </tr>
                <tr>
                     <th>Age threshold in hours</th>
                     <td><label for="max_age"><input name="max_age" type="text" id="max_age" value="<?php echo isset($max_age) ? esc_attr( $max_age ) : '24'; ?>"  /> For older no tweets will be send. 0 = no age limit.</label></td>
                </tr>
                
                <tr>
                	<th>Shortening Method</th>
                	<td>
	     				<select id="wordtwit_url_type" name="wordtwit_url_type" onChange="showshortenersettings()">
	     					<option value="tinyurl"<?php if ( $wordtwit_url_type == 'tinyurl' ) echo " selected"; ?>>Tinyurl - (http://tinyurl.com)</option>
	     					<option value="bitly"<?php if ( $wordtwit_url_type == 'bitly' ) echo " selected"; ?>>Bit.ly - (http://bit.ly)</option>
	     					<option value="wpme"<?php if ( $wordtwit_url_type == 'wpme' || empty( $wordtwit_url_type ) ) echo " selected"; ?>>wp.me - (http://wp.me/sf2B5-shorten)</option>
	     				</select>
					</td>
				</tr>
				
				<tr id="shortcode-data-bitly" <?php if( 'bitly' != $wordtwit_url_type ): ?> style="display:none"<?php endif; ?>>
					<th>Bit.ly Settings</th>
					<td>
						<label for="bitly_user_name"><input type="text" name="bitly_user_name" id="bitly_user_name" class="long" value="<?php if ( isset( $bitly_user_name ) ) echo esc_attr( $bitly_user_name ); ?>" /> Your bit.ly username</label><br/>
						<label for="bitly_api_key"><input type="text" name="bitly_api_key" id="bitly_api_key" class="long" value="<?php if ( isset( $bitly_api_key ) ) echo esc_attr( $bitly_api_key ); ?>" /> Your bit.ly API key</label>
					</td>
				
				</tr>
                </table>
               
               <div class="submit">
                  <input type="submit" name="info_update" value="Update Options" />
               </div>
            </form>
         </div>

         
         <div class="wordtwit-clearer"></div>
      </div>
      <div id="thanks">
         <a href="http://www.chris-wallace.com/2009/01/02/tweeties-a-free-twitter-icon-set/">Twitter Bird Photo by Chris Wallace</a>
      </div>
</div>
