
<p><a href="<?php echo esc_attr( preg_replace('/&wpatt-page=[^&]*/', '', $_SERVER['REQUEST_URI'] ) ); ?>">&laquo; Back to options page</a></p>
<h3>Goal</h3>
<p>The Attached Image is a simple plugin that packs quite a punch. It shows the first image attached to the current post. It was inspired by a plugin wrote by Kaf Oseo, but when support was stopped &amp; a recent upgrade of WordPress meant it didn't work exactly like it used to I decided to take on the challenge of remaking it using the new WordPress functions available.</p>
<h3>Features</h3>
<ul>
  <li>Can show the full, medium or thumbnail sized image attached to the current post.</li>
  <li>Can make a hyperlink around the image that points to the post the image is attached to, the full image, the attachment page or a custom URL using custom fields on a post by post basis.</li>
  <li>If more than one image is attached to a post then the image to be shown can be changed using the WordPress gallery page. Just pull the image you wish to show right to the top of the list and press save.</li>
  <li>Can be returned instead of echoed so the output can be stored in a variable for developers to use as they wish.</li>
  <li>Can show a default image if no image is available. Also changeable on a post by post basis via custom fields.</li>
  <li>and more&hellip;</li>
</ul>
<h3>Installation</h3>
<p>All you need to do to install is the following:</p>
<ol>
  <li>Unzip &amp; place the folder into the <code>wp-content/plugins</code> folder. (I'm going to assume you've done this)</li>
  <li>Go to the plugins page of WP &amp; activate the plugin. (Also assumed as done)</li>
  <li>Go into the template editor &amp; find where you would like the image to show. It must be within the loop which looks something like this:
    <pre><code>
    &lt;?php if (have_posts()) : ?&gt;
		&lt;?php while (have_posts()) : the_post(); ?&gt;
        	&lt;!-- Some HTML will be here --&gt;
        &lt;?php endwhile; ?&gt;
    &lt;?php endif; ?&gt;
    </code></pre>
  </li>
  <li>At the point you have found, place <code>&lt;?php the_attached_image(); ?&gt;</code></li>
  <li>Go to the 'The Attached Image' options page under the WordPress Appearence menu.</li>
</ol>
<h3>Options</h3>
<h4>General Options</h4>
<p>The Attached Image now comes with an options page so you don't have to get into the nitty gritty of all the complicated code to make it work. Here is a description of what each option does. First the general options:</p>
<dl>
  <dt><strong><em>Image Size</em></strong></dt>
  <dd>This is the size of image you would like to use. As of version 2.2 it supports WordPress' generated thumbnails, medium size, large size images, and of course the original full size image. You can now specify a size using a function call to override the options page. This is great if you want to call the plugin twice on two template pages &amp; want to use different size image. Use <code>img_size=</code> and then either full, large, medium or thumbnail. Use ampersands (&amp;) to seperate parameters.</dd>
  <dt><strong><em>CSS Class</em></strong></dt>
  <dd>This is the class that you would like placed in the image tag. The default is <code>attached-image</code> and can be styled as normal through a CSS stylesheet. This option is so anyone who already has a class can use that if they wish. You can also call a different CSS class using function call parameters again options are seperated by ampersands (&amp;). CSS class' parameter is <code>css_class</code> <strong>Don't</strong> use spaces in CSS class names when using this method. An example of both image size &amp; css class together would be this. <code>the_attached_image('img_size=thumbnail&amp;css_class=custom-class');</code></dd>
  <dt><strong><em>Custom Image Size</em></strong></dt>
  <dd>Here you can input a custom image size. Beware, this uses the inbuilt width &amp; height attributes of the image tag &amp; as such can degrade picture quality if used too aggressively. Please use with caution.</dd>
  <dt><strong><em>Default Image Path</em></strong></dt>
  <dd>A simple one. This is the path to a default image if you wish to use one. Empty or leave the box empty to disable it. <strong>Very Important</strong> the image path must be from the WordPress root &amp; not your hosts root, it must also start with a forward slash (/). So if your blog is in <code>http://example.com/blog/</code> and you kept the image <code>default.jpg</code> in the <code>wp-content</code> folder the path would still just be <code>/wp-content/default.jpg</code> and <strong>NOT</strong> <code>/blog/wp-content/default.jpg</code></dd>
  <dt><strong><em>Image Link Location</em></strong></dt>
  <dd>Do you want a link to be placed on the image that is produced &amp; if so where do you want it to point. The possible options are no link, post, image &amp; attachment page. I think it's pretty self explanitory what they do. You can also provide a custom link on a post by post basis, more on that in the custom fields section further down the page.</dd>
  <dt><strong><em>Image Alternate Text:</em></strong></dt>
  <dd>Allows you to choose what the default alternate text for the image should be. You can choose either image filename, image description, post title or post slug. The description is taken from the description field that you can fill in when uploading an image via WordPress' uploader. If one isn't provided it falls back to the images filename. A custom value may be input via custom fields, see Custom Field Info below.</dd>
  <dt><strong><em>Link Title Text:</em></strong></dt>
  <dd>This is the text placed in the title attribute of the hyperlink placed around the image. This will only have an effect if you do <strong>NOT</strong> have Image Link Location set to No Link. The options are the same as the alternated text &amp; a custom value can be input via custom fields, see Custom Field Info below.</dd>
</dl>
<h4>Advanced Options</h4>
<p>The following are advanced options. If you aren't comfortable messing around with them then just leave them. You can actually stop the plugin from working correctly by selecting the wrong option here so please be careful. If you are a seasoned coder or know what you are doing then ignore this &amp; happy hunting.</p>
<dl>
  <dt><strong><em>Generate An Image Tag:</em></strong></dt>
  <dd>Fairly obvious... Whether to make an image tag or just place the full URL to the selected size image onto the page. If a link location is selected then it will also create the selected hyperlink around the URL. This can be useful to some people so feel free to be inventive.</dd>
  <dt><strong><em>Echo or Return:</em></strong></dt>
  <dd>Also fairly obvious, if you are a coder. Tells the plugin whether to echo out the output or return the output ready for processing by PHP. Can also be used to do some inventive stuff with the output.</dd>
  <dt><strong><em>Hyperlink Rel Attribute:</em></strong></dt>
  <dd>This should allow the plugin to work with most, if not all lightbox scripts. Refer to the documention of the lightbox script for what to place in the rel attribute.</dd>
  <dt><strong><em>Image Order:</em></strong></dt>
  <dd>By default the plugin will use the image in the first position of the WordPress gallery page. The image to show can be changed by reordering the images on the WP gallery screen, however you can use this to change which image it will pick. If you change this to 3 it will always try to pick the 3rd image in the WP gallery order. If there isn't 3 images it will pick the nearest it can get to the 3rd image.</dd>
</dl>
<h3>Custom Field Info</h3>
<p>Some of the options can be changed on a post by post basis through the use of custom fields. These are the available keys, what they do &amp; the values they expect. All of the keys prepended with <em>att</em> so that they are easily recogniseable as for use with The Attached Image &amp; to stop conflicts with other plugins that may use custom fields.</p>
<dl>
  <dt><strong>Key:</strong> <em>att_custom_img</em></dt>
  <dd>This field is used to show any image from the WordPress attachment database, even if it isn't attached to the current post. It requires the ID of the image you wish to show. It can generally be found out in the media section of WordPress.</dd>
  <dt><strong>Key:</strong> <em>att_default_pic</em></dt>
  <dd>Allows you to override the default picture that is to be shown if no picture is available. Path rules are exactly the same as the previously mentioned option.</dd>
  <dt><strong>Key:</strong> <em>att_width &amp; att_height</em></dt>
  <dd>Pretty obvious, but it allows you to change the width &amp; height of the image. It again uses the in-built browser method of resizing, so again be careful. Also please remember these are two seperate keys, I have listed them together but you must use two custom fields one for width & one for height.</dd>
  <dt><strong>Key:</strong> <em>att_custom_link</em></dt>
  <dd>Allows you to chose a custom URL for the hyperlink to go to. It will override the setting chosen in the options page for that single post. If you have selected no link in the options using this will override it and create a hyperlink for that single post.</dd>
  <dt><strong>Key:</strong> <em>att_custom_alt</em></dt>
  <dd>Allows you to chose a custom alt attibute to be placed in the image tag.</dd>
  <dt><strong>Key:</strong> <em>att_custom_link_title</em></dt>
  <dd>Allows you to chose a custom title attribute to be placed in the hyperlink. Only has an effect if Link Image Location is <strong>NOT</strong> set to No Link.</dd>
</dl>
<h3>More Questions?</h3>
<p>Then drop by <a href="http://return-true.com/2008/12/wordpress-plugin-the-attached-image/" title="Home of The Attached Image Plugin">Return True</a> and leave a comment and I will get back to you with an answer ASAP.</p>
<h3>Feature Requests & Bug Reporting</h3>
<p>I'm always looking for ways to improve The Attached Image whether it be by adding new features or fixing a bug that has been found. If you have a bug or a feature request the go to <a href="http://return-true.com/2008/12/wordpress-plugin-the-attached-image/" title="Home of The Attached Image Plugin">Return True</a> and leave a comment or send an email to pablorobinson[at]gmail[dot]com.</p>
<h3>Donating</h3>
<p>If you like The Attached Image &amp; are feeling kind then you can buy me a coffee or two by donating via Paypal using the button below:</p>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
  <input type="hidden" name="cmd" value="_s-xclick">
  <input type="hidden" name="hosted_button_id" value="3161138">
  <input type="image" src="https://www.paypal.com/en_GB/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="">
  <img alt="" border="0" src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1">
</form>
<p>You can also donate staight to the hosting fund for Return True if you prefer. This will help keep the site running which also allows me to continue working on The Attached Image, you can do that using the button below which also uses Paypal to pay securely. Thank you.</p>
<p><a href="http://www.dreamhost.com/donate.cgi?id=10292"><img src="https://secure.newdream.net/donate4.gif" alt="Donate towards my web hosting bill!" border="0"></a></p>
<h2>Legacy Overrides</h2>
<p>These are a list of legacy parameters that allow you to override the values set in the options page. This is useful if you want to have more than one call the <code>the_attached_image()</code> on different pages in you template, but you want them to show different size images, or remove the link and other things. Basically it allows you to call the plugin twice, but have it do two different things. The parameters are entered in Query String format an example is <code>the_attached_image('img_size=medium&amp;link=image&amp;css_class=featured-image');</code>.</p>
<dl>
<dt><code>img_size</code></dt>
<dd>Changes image size to be pulled back by WordPress. Options are thumbnail, medium, large &amp; full. Default is thumbnail.</dd>
<dt><code>css_class</code></dt>
<dd>The CSS class to place inside the image tag.</dd>
<dt><code>img_tag</code></dt>
<dd>Whether or not to echo the URL in an image tag. Options are true or false. Default is true.</dd>
<dt><code>echo</code></dt>
<dd>whether to echo or return the output. True will echo, false will return. Default is true.</dd>
<dt><code>link</code></dt>
<dd>Where you want the link to go. Will only work if href is set to true. Options are none, post, image and attachment. It is also possible to use a custom URL via the custom fields, please refer to the custom fields section above for more. Default is post.</dd>
<dt><code>default</code></dt>
<dd>The path to a default image if one is wanted. The path must start with a forward slash and be based from the wordpress directory, not your hosts directory. Options are false &amp; a path to the image. Default is false. Can also be adjusted via custom fields, again check above for more.</dd>
<dt><code>width &amp; height</code></dt>
<dd>Two seperate parameters that do the obvious thing. The set a custom width &amp; height for all images. This only resizes using the image tag width &amp; height attribute, as such quality will suffer greatly when resizing too much either way. Also adjustable using custom fields, check above for more.</dd>
<dt><code>image_order</code></dt>
<dd>Allows you to change which image the plugin uses from the WP gallery page. Normally, if there is more than one image, the plugin will pick the image marked in 1st. This allows you to change that. If there isn’t a picture at the position then it will take the pictures as near to that number as possible. Default is 1</dd>
<dt><code>rel</code></dt>
<dd>The rel attribute is generally used to add lightbox scripts. Just put the word shown by your lightbox instructions here and all should work as long as href is set to true &amp; link is set to image, since there has to be a link and the URL to the full image for the lightbox script to work.</dd>
<dt><code>alt</code></dt>
<dd>Allows you to choose what the default alternate text for the image should be. You can choose either image filename, image description, post title or post slug. The description is taken from the description field that you can fill in when uploading an image via WordPress’ uploader. If one isn’t provided it falls back to the images filename. A custom value may be input via custom fields, see Custom Field Info above.</dd>
<dt><code>title_link</code></dt>
<dd>This is the text placed in the title attribute of the hyperlink placed around the image. This will only have an effect if you do <strong>NOT</strong> have Image Link Location set to No Link. The options are the same as the alternated text &amp; a custom value can be input via custom fields, see Custom Field Info above.</dd>
</dl>
