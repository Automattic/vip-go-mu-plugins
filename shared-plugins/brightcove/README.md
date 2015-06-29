=== Brightcove ===
Contributors: brightcove
Tags: video, brightcove
Requires at least: 3.3.1
Tested up to: 3.3.1
Stable tag: trunk

An easy to use plugin that inserts Brightcove Video into your Wordpress site. 

== Description ==

This plugin allows for the easy insertion of Brightcove videos and playlists into your Wordpress site or blog. This plugin seamlessly integrates with the Wordpress Media Upload feature introduced in 3.3.1 allowing for easy insertion of video. 

If you do not have a API Read Token then videos can be inserted by entering Video IDs or Playlist IDs into the plugin. Options also exist to change the type of player displaying the video as well as the height and width of the player. 

If you do have an API read token this plugin allows you to see all of your videos, search for videos by short description, long description, name and custom fields as well as see all of your playlists. Media API users are presented with thumbnail images of the video to allow for easier selection.

Both versions feature live preview of the video to make sure you have the player and video you want before inserting it into your post or page. Options also exist to set up default players, height and width throughout the site to provide a consistent look. These defaults can also be overridden at the point of inserting the video if you so desire. 

Note that in order to use this plugin you must have a Brightcove account. Please visit <a href='http://www.brightcove.com'> Brightcove </a> to sign up. 
== Installation ==

 1. Upload the bright cove file to the `/wp-content/plugins/` directory 
	a. Alternative - Upload brightcove.zip via the zip uploader for Wordpress plugins 
    b. Alternative - Click install from searching within Wordpress  plugin manager 
 2. Activate the plugin through the 'Plugins' menu in WordPress 
 3. Fill out Publisher ID, default video player ID and default playlist player ID in the Brightcove settings menu (left hand side on admin page) 
	a. Optional - Fill out media api token to access media api 
 4. That's it!!! Videos and playlist can be added from the upload media button above pages and posts


== Frequently Asked Questions ==

= Where do I find my publisher ID? =

Log in to your <a href='"https://my.brightcove.com/"'>Brightcove Video Cloud account.</a>
To retrieve your Publisher ID, go to Home > Profile. It is located under the account name.

= Where do I get a video player ID from? =

Log in to your <a href='"https://my.brightcove.com/"'>Brightcove Video Cloud account.</a>

To retrieve the ID for a player:
1. Open the Publishing tab
2. Click a player
3. Copy the Player ID under the player preview in the right hand panel.

= Where do I get a playlist player ID from? =

Log in to your <a href='"https://my.brightcove.com/"'>Brightcove Video Cloud account.</a>

To retrieve the ID for a player:
1. Open the Publishing tab
2. Click a player
3. Copy the Player ID under the player preview in the right hand panel.


= Where do I get a video ID? =

Log in to your <a href='"https://my.brightcove.com/"'>Brightcove Video Cloud account.</a>

To retrieve the ID for a video:
1. Open the Media tab
2. Under Media Library on the far left hand side click All Videos or Recently Created
3. Click on a video.
4. In the video preview section on the right hand side copy Video ID.


= Where do I get a playlist ID? =

Log in to your <a href='"https://my.brightcove.com/"'>Brightcove Video Cloud account.</a>

To retrieve the ID for a playlist:
1. Open the Media tab
2. Under Playlists on the far left hand side click All Playlists or Favorites
3. Click on a playlist.
4. Playlist ID is listed in the top bar above the videos in the playlist. 


= What's a API Read Token? Do I need one? =

For Video Cloud Professional and Video Cloud Enterprise Brightcove customers, the API Read Token is required for enhanced functionality such as searching of videos and playlists via the Brightcove Media API. To retrieve your API Read Token, go to Home > Account Settings > API Management. See this <a href=' http://support.brightcove.com/en/docs/managing-media-api-tokens'> support article </a> for detailed information on API Token management.

== Screenshots ==

1. Admin settings. From here you can set your publisher id, default players for videos and playlists, add an API Read Token (if you have one), and set default width and heights for the players. 
2. Video tab for those without an API Read Token, videos can be accessed by entering a video ID or reference number. 
3. Video tab showing a live preview of the video and player selected.
4. Playlist tab for those without an API Read Token, playlists can be entered by giving a list of space or comma seperated IDs.
5. Live preview of player in playlist tab. 
6. Shortcode for a Brightcove player in the edit window.
7. Video tab on first load for those with an API Read Token. All videos for the account are loaded in pages. Videos can be selected by clicking. 
8. Searching for video with the word testing, returns two results.
9. Preview of a video in the video search tab.
10.Playlist tab for those with an API Read Token. All playlists for the account are loaded in pages. 
11. Playlists are selected by clicking checkboxes then clicking the Add Playlists button.
12. Live preview of playlists in playlist tab. 

== Changelog ==

= 1.0 =
* Initial launch of plugin.




