<?php
/*
 * Security check
 * Exit if file accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Playbuzz Admin
 * Displays playbuzz menus in WordPress dashboard.
 *
 * @since 0.1.0
 */
class PlaybuzzAdmin {

	protected static $option_name = 'playbuzz';

	protected static $data = array(

		// General
		'key'               => 'default',
		'pb_user'           => '',

		// items
		'info'              => '1',
		'shares'            => '1',
		'comments'          => '1',
		'recommend'         => '1',
		'margin-top'        => '0',
		'embeddedon'        => 'content',

		// Recommendations
		'active'            => 'false',
		'show'              => 'footer',
		'view'              => 'large_images',
		'items'             => '3',
		'links'             => 'https://www.playbuzz.com',
		'section-page'		=>	'',

		// Tags
		'tags-mix'          => '1',
		'tags-fun'          => '',
		'tags-pop'          => '',
		'tags-geek'         => '',
		'tags-sports'       => '',
		'tags-editors-pick' => '',
		'more-tags'         => '',

	);

	/*
	 * Constructor
	 */
	public function __construct() {

		// Admin sub-menu
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		}

	}

	/*
	 * Get option name
	 */
	public function get_option_name() {

		return self::$option_name;

	}

	/*
	 * Get data
	 */
	public function get_data() {

		return self::$data;

	}

	/*
	 * Register setting page using the Settings API.
	 */
	public function admin_init() {

		register_setting(
			'playbuzz',
			$this->get_option_name()
		);

	}

	/*
	 * Add entry in the settings menu.
	 */
	public function add_settings_page() {

		add_options_page(
			__( 'Playbuzz', 'playbuzz' ),
			__( 'Playbuzz', 'playbuzz' ),
			'manage_options',
			'playbuzz',
			array( $this, 'playbuzz_settings_page' )
		);

	}

	/*
	 * Print the menu page itself.
	 */
	public function playbuzz_settings_page() {

		// Load settings
		$options = get_option( $this->get_option_name() );

		// Set default tab
		$playbuzz_active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'start';
		
		// Display the page
		?>
		<a name="top"></a>
		<div class="wrap" id="playbuzz-admin">
			<h1><?php esc_html_e( 'Playbuzz Plugin', 'playbuzz' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=<?php echo urlencode( $this->get_option_name() ); ?>&tab=start"      class="nav-tab <?php echo $playbuzz_active_tab == 'start'      ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Getting Started', 'playbuzz' ); ?></a>
				<a href="?page=<?php echo urlencode( $this->get_option_name() ); ?>&tab=embed"      class="nav-tab <?php echo $playbuzz_active_tab == 'embed'      ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Site Settings',   'playbuzz' ); ?></a>
				<a href="?page=<?php echo urlencode( $this->get_option_name() ); ?>&tab=shortcodes" class="nav-tab <?php echo $playbuzz_active_tab == 'shortcodes' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Shortcodes',      'playbuzz' ); ?></a>
				<a href="?page=<?php echo urlencode( $this->get_option_name() ); ?>&tab=feedback"   class="nav-tab <?php echo $playbuzz_active_tab == 'feedback'   ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Feedback',        'playbuzz' ); ?></a>
			</h2>

			<?php if( $playbuzz_active_tab == 'start' ) { ?>

				<div class="playbuzz_start">

					<img src="<?php echo esc_url( plugins_url( 'img/admin-embed-items.png', __FILE__ ) ); ?>" class="location_img">

					<h3><?php esc_html_e( 'Embed Items from Playbuzz', 'playbuzz' ); ?></h3>

					<ol class="circles-list">
						<li>
							<p><?php esc_html_e( 'Create a new post/page and click the blue Playbuzz button in the visual editor', 'playbuzz' ); ?></p>
						</li>
						<li>
							<p><?php esc_html_e( 'Search for any item, browse our featured items or select one of your own creations', 'playbuzz' ); ?></p>
						</li>
						<li>
							<p><?php esc_html_e( 'To embed the item to your post click on the “Embed” button', 'playbuzz' ); ?></p>
						</li>
					</ol>

				</div>

				<div class="playbuzz_start">

					<img src="<?php echo esc_url( plugins_url( 'img/admin-embed-customization.png', __FILE__ ) ); ?>" class="location_img">

					<h3><?php esc_html_e( 'Item Customization', 'playbuzz' ); ?></h3>

					<ol class="circles-list">
						<li>
							<p><?php esc_html_e( 'After embedding the item in your post, click on the settings icon to open the item settings panel', 'playbuzz' ); ?></p>
						</li>
						<li>
							<p><?php esc_html_e( 'Apply default site settings', 'playbuzz' ); ?></p>
						</li>
						<li>
							<p><?php esc_html_e( 'Or select the "custom" option to apply customized settings', 'playbuzz' ); ?></p>
						</li>
					</ol>

				</div>

				<div class="playbuzz_start">

					<h3><?php esc_html_e( 'Default Site Settings', 'playbuzz' ); ?></h3>

					<ol class="circles-list">
						<li>
							<p><?php printf( __( 'You don’t have to manually set the options for each of your embeds! You can now use the <a href="%s">default site settings</a> panel', 'playbuzz' ), esc_url('?page='.$this->get_option_name().'&tab=embed') ); ?></p>
						</li>
						<li>
							<p><?php printf( __( 'This panel  is always available for you <a href="%s">here</a> and at the following path: Settings > Playbuzz > Customization', 'playbuzz' ), esc_url('?page='.$this->get_option_name().'&tab=embed') ); ?></p>
						</li>
						<li>
							<p><?php esc_html_e( 'Your site default settings won\'t override any of your manual item-specific settings!', 'playbuzz' ); ?></p>
						</li>
					</ol>

				</div>

			<?php } elseif( $playbuzz_active_tab == 'embed' ) { ?>

				<form method="post" action="options.php">

					<?php settings_fields( 'playbuzz' ); ?>

					<div class="playbuzz_embed">

						<h3><?php esc_html_e( 'My Items', 'playbuzz' ); ?></h3>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[pb_user]">
							<?php esc_html_e( 'Playbuzz Username', 'playbuzz' ); ?>
							<input type="text" class="regular-text" id="<?php echo esc_attr( $this->get_option_name() ); ?>[pb_user]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[pb_user]" value="<?php echo esc_attr( isset( $options['pb_user'] ) ? str_replace( ' ', '', $options['pb_user'] ) : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. sarahpark10', 'playbuzz' ); ?>">
						</label>
						<p class="description description-width"><?php printf( esc_html__( 'You can find your username by going to your profile page on %s, and copying the username from the page URL. (Everything after the last "/")', 'playbuzz' ), '<a href="https://www.playbuzz.com/" target="_blank">Playbuzz.com</a>' ); ?></p>
						<p class="description"><strong><?php esc_html_e( 'Example:', 'playbuzz' ); ?></strong> www.playbuzz.com/<strong class="red">username10</strong></p>

					</div>

					<div class="playbuzz_embed">

						<h3><?php esc_html_e( 'Default Site Settings', 'playbuzz' ); ?></h3>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[info]">
							<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[info]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[info]" value="1" <?php if ( isset( $options['info'] ) && ( '1' == $options['info'] ) ) echo 'checked="checked"'; ?>>
							<?php esc_html_e( 'Display item information', 'playbuzz' ); ?>
						</label>
						<p class="description indent"><?php esc_html_e( 'Show item thumbnail, name, description, creator.', 'playbuzz' ); ?></p>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[shares]">
							<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[shares]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[shares]" value="1" <?php if ( isset( $options['shares'] ) && ( '1' == $options['shares'] ) ) echo 'checked="checked"'; ?>>
							<?php esc_html_e( 'Display share buttons', 'playbuzz' ); ?>
						</label>
						<p class="description indent"><?php esc_html_e( 'Show share buttons with links to YOUR site.', 'playbuzz' ); ?></p>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[comments]">
							<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[comments]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[comments]" value="1" <?php if ( isset( $options['comments'] ) && ( '1' == $options['comments'] ) ) echo 'checked="checked"'; ?>>
							<?php esc_html_e( 'Display Facebook comments', 'playbuzz' ); ?>
						</label>
						<p class="description indent"><?php esc_html_e( 'Show Facebook comments in your items.', 'playbuzz' ); ?></p>

					</div>

					<div class="playbuzz_embed">

						<h3><?php esc_html_e( 'Sticky Header Preferences', 'playbuzz' ); ?></h3>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[margin-top]">
							<?php esc_html_e( 'Height', 'playbuzz' ); ?>
							<input type="text" id="<?php echo esc_attr( $this->get_option_name() ); ?>[margin-top]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[margin-top]" value="<?php echo esc_attr( isset( $options['margin-top'] ) ? $options['margin-top'] : 0 ); ?>" class="small-text">
							<?php esc_html_e( 'px', 'playbuzz' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Use this if your website has top header that\'s always visible, even while scrolling down.', 'playbuzz' ); ?></p>

					</div>

					<div class="playbuzz_embed">

						<h3><?php esc_html_e( 'Appearance Preferences', 'playbuzz' ); ?></h3>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[embeddedon]">
							<?php esc_html_e( 'Display embedded items on', 'playbuzz' ); ?>
							<select id="<?php echo esc_attr( $this->get_option_name() ); ?>[embeddedon]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[embeddedon]">
								<option value="content" <?php if ( isset( $options['embeddedon'] ) && ( 'content' == $options['embeddedon'] ) ) echo 'selected'; ?>><?php esc_html_e( 'Posts & Pages Only',                  'playbuzz' ); ?></option>
								<option value="all"     <?php if ( isset( $options['embeddedon'] ) && ( 'all'     == $options['embeddedon'] ) ) echo 'selected'; ?>><?php esc_html_e( 'All pages (singular, archive, ect.)', 'playbuzz' ); ?></option>
							</select>
						</label>
						<p class="description"><?php printf( __( 'Whether to show the embedded content only in <a href="%s" target="_blank">singular pages</a>, or <a href="%s" target="_blank">archive page</a> too.', 'playbuzz' ), 'https://codex.wordpress.org/Function_Reference/is_singular', 'https://codex.wordpress.org/Template_Hierarchy' ); ?></p>

					</div>

					<div class="playbuzz_embed">

						<h3><?php esc_html_e( 'Item Recommendations', 'playbuzz' ); ?></h3>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[recommend]">
							<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[recommend]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[recommend]" class="tags_toggle_triger" value="1" <?php if ( isset( $options['recommend'] ) && ( '1' == $options['recommend'] ) ) echo 'checked="checked"'; ?>>
							<?php esc_html_e( 'Display more recommendations', 'playbuzz' ); ?>
						</label>
						<p class="description indent"><?php esc_html_e( 'Show recommendations for more items.', 'playbuzz' ); ?></p>

						<div class="tags_toggle">

							<hr class="indent">

							<img src="<?php echo esc_url( plugins_url( 'img/admin-recommendations.png', __FILE__ ) ); ?>" class="location_img">

							<label class="indent"><?php esc_html_e( 'Tags', 'playbuzz' ); ?></label>
							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-mix]" class="indent">
								<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-mix]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-mix]" value="1" <?php if ( isset( $options['tags-mix'] ) && ( '1' == $options['tags-mix'] ) ) echo 'checked="checked"'; ?>>
								<?php esc_html_e( 'All', 'playbuzz' ); ?>
							</label>
							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-fun]" class="indent">
								<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-fun]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-fun]" value="1" <?php if ( isset( $options['tags-fun'] ) && ( '1' == $options['tags-fun'] ) ) echo 'checked="checked"'; ?>>
								<?php esc_html_e( 'Fun', 'playbuzz' ); ?>
							</label>
							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-pop]" class="indent">
								<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-pop]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-pop]" value="1" <?php if ( isset( $options['tags-pop'] ) && ( '1' == $options['tags-pop'] ) ) echo 'checked="checked"'; ?>>
								<?php esc_html_e( 'Pop', 'playbuzz' ); ?>
							</label>
							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-geek]" class="indent">
								<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-geek]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-geek]" value="1" <?php if ( isset( $options['tags-geek'] ) && ( '1' == $options['tags-geek'] ) ) echo 'checked="checked"'; ?>>
								<?php esc_html_e( 'Geek', 'playbuzz' ); ?>
							</label>
							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-sports]" class="indent">
								<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-sports]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-sports]" value="1" <?php if ( isset( $options['tags-sports'] ) && ( '1' == $options['tags-sports'] ) ) echo 'checked="checked"'; ?>>
								<?php esc_html_e( 'Sports', 'playbuzz' ); ?>
							</label>
							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-editors-pick]" class="indent">
								<input type="checkbox" id="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-editors-pick]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-editors-pick]" value="1" <?php if ( isset( $options['tags-editors-pick'] ) && ( '1' == $options['tags-editors-pick'] ) ) echo 'checked="checked"'; ?>>
								<?php esc_html_e( 'Editor\'s Pick', 'playbuzz' ); ?>
							</label>

							<hr class="indent">

							<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[more-tags]" class="indent"><?php esc_html_e( 'Custom Tags', 'playbuzz' ); ?></label>
							<input type="text" class="regular-text indent" id="<?php echo esc_attr( $this->get_option_name() ); ?>[more-tags]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[more-tags]" value="<?php echo esc_attr( isset( $options['more-tags'] ) ? $options['more-tags'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Comma separated tags, e.g. food, rap, weather', 'playbuzz' ); ?>">

						</div>

					</div>

					<div class="playbuzz_embed" style="display:none;">

						<h3><?php esc_html_e( 'Authentication', 'playbuzz' ); ?></h3>

						<label for="<?php echo esc_attr( $this->get_option_name() ); ?>[key]" class="indent"><?php esc_html_e( 'API Key', 'playbuzz' ); ?></label>
						<input type="text" class="regular-text indent" id="<?php echo esc_attr( $this->get_option_name() ); ?>[key]" name="<?php echo esc_attr( $this->get_option_name() ); ?>[key]" value="<?php if ( $options['key'] ) echo esc_attr( $options['key'] ); else echo esc_attr( str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ) ); ?>">

					</div>

					<?php submit_button(); ?> 

					<div class="playbuzz_embed" style="display:none;"> <?php /* Hidden from the user for the next year for backwards compatibility reasons, and then remove the "Related Content Embed" feature from the plugin. */ ?>

						<h3><?php esc_html_e( 'Related Content Customization', 'playbuzz' ); ?></h3>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Number of Items', 'playbuzz' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( $this->get_option_name() ); ?>[items]">
										<option value="2"  <?php if ( '2'  == $options['items'] ) echo 'selected'; ?>>2</option>
										<option value="3"  <?php if ( '3'  == $options['items'] ) echo 'selected'; ?>>3</option>
										<option value="4"  <?php if ( '4'  == $options['items'] ) echo 'selected'; ?>>4</option>
										<option value="5"  <?php if ( '5'  == $options['items'] ) echo 'selected'; ?>>5</option>
										<option value="6"  <?php if ( '6'  == $options['items'] ) echo 'selected'; ?>>6</option>
										<option value="7"  <?php if ( '7'  == $options['items'] ) echo 'selected'; ?>>7</option>
										<option value="8"  <?php if ( '8'  == $options['items'] ) echo 'selected'; ?>>8</option>
										<option value="9"  <?php if ( '9'  == $options['items'] ) echo 'selected'; ?>>9</option>
										<option value="10" <?php if ( '10' == $options['items'] ) echo 'selected'; ?>>10</option>
										<option value="11" <?php if ( '11' == $options['items'] ) echo 'selected'; ?>>11</option>
										<option value="12" <?php if ( '12' == $options['items'] ) echo 'selected'; ?>>12</option>
										<option value="13" <?php if ( '13' == $options['items'] ) echo 'selected'; ?>>13</option>
										<option value="14" <?php if ( '14' == $options['items'] ) echo 'selected'; ?>>14</option>
										<option value="15" <?php if ( '15' == $options['items'] ) echo 'selected'; ?>>15</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Items layout', 'playbuzz' ); ?></th>
								<td valign="top">
									<input type="radio" name="<?php echo esc_attr( $this->get_option_name() ); ?>[view]" value="large_images"      <?php if ( 'large_images'      == $options['view'] ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Large Images',      'playbuzz' ); ?><br><img src="<?php echo esc_url( plugins_url( 'img/recommendation-tumbs.jpg',      __FILE__) ); ?>" title="<?php esc_attr_e( 'Large Images',      'playbuzz' ); ?>"><br><br>
									<input type="radio" name="<?php echo esc_attr( $this->get_option_name() ); ?>[view]" value="horizontal_images" <?php if ( 'horizontal_images' == $options['view'] ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Horizontal Images', 'playbuzz' ); ?><br><img src="<?php echo esc_url( plugins_url( 'img/recommendation-image-list.jpg', __FILE__) ); ?>" title="<?php esc_attr_e( 'Horizontal Images', 'playbuzz' ); ?>"><br><br>
									<input type="radio" name="<?php echo esc_attr( $this->get_option_name() ); ?>[view]" value="no_images"         <?php if ( 'no_images'         == $options['view'] ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'No Images',         'playbuzz' ); ?><br><img src="<?php echo esc_url( plugins_url( 'img/recommendation-list.jpg',       __FILE__) ); ?>" title="<?php esc_attr_e( 'No Images',         'playbuzz' ); ?>">
								</td>
							</tr>
							<?php /*
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Tags', 'playbuzz' ); ?></th>
								<td>
									<input type="checkbox" class="checkbox"        name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-mix]"          value="1" <?php if ( '1' == $options['tags-mix']          ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'All',            'playbuzz' ); ?><br/>
									<input type="checkbox" class="checkbox_indent" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-fun]"          value="1" <?php if ( '1' == $options['tags-fun']          ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Fun',            'playbuzz' ); ?><br/>
									<input type="checkbox" class="checkbox_indent" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-pop]"          value="1" <?php if ( '1' == $options['tags-pop']          ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Pop',            'playbuzz' ); ?><br/>
									<input type="checkbox" class="checkbox_indent" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-geek]"         value="1" <?php if ( '1' == $options['tags-geek']         ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Geek',           'playbuzz' ); ?><br/>
									<input type="checkbox" class="checkbox_indent" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-sports]"       value="1" <?php if ( '1' == $options['tags-sports']       ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Sports',         'playbuzz' ); ?><br/>
									<input type="checkbox" class="checkbox_indent" name="<?php echo esc_attr( $this->get_option_name() ); ?>[tags-editors-pick]" value="1" <?php if ( '1' == $options['tags-editors-pick'] ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Editor\'s Pick', 'playbuzz' ); ?><br/>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php esc_html_e( 'Custom Tags', 'playbuzz' ); ?></th>
								<td>
									<input type="text" name="<?php echo esc_attr( $this->get_option_name() ); ?>[more-tags]" value="<?php echo esc_attr( $options['more-tags'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Comma separated tags', 'playbuzz' ); ?>">
								</td>
							</tr>
							*/ ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Open Items at', 'playbuzz' ); ?></th>
								<td>
									<p><?php printf( __( '<a href="%s" target="_blank">Create</a> a new page containing the <code>[playbuzz-section]</code> shortcode. Then select it below as the destination page where items will open:', 'playbuzz' ), 'post-new.php?post_type=page' ); ?></p>
									<?php
									if ( isset( $options['section-page'] ) ) {
										$link_page_id = $options['section-page'];
									} else {
										$link_page_id = 0;
									}
									wp_dropdown_pages( array( 'selected' => $link_page_id, 'post_type' => 'page', 'hierarchical' => 1, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0' ) );
									?>
									<input type="hidden" name="<?php echo esc_attr( $this->get_option_name() ); ?>[links]" value="<?php echo esc_attr( $options['links'] ); ?>" class="regular-text" placeholder="https://www.playbuzz.com/">
								</td>
							</tr>
							<tr class="separator">
								<td colspan="2">
									<hr>
								</td>
							</tr>
							<tr>
								<th colspan="2"><?php esc_html_e( 'Automatically Add Recommendations', 'playbuzz' ); ?></th>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Active', 'playbuzz' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( $this->get_option_name() ); ?>[active]">
										<option value="false" <?php if ( 'false' == $options['active'] ) echo 'selected'; ?>><?php esc_html_e( 'Disable', 'playbuzz' ); ?></option>
										<option value="true"  <?php if ( 'true'  == $options['active'] ) echo 'selected'; ?>><?php esc_html_e( 'Enable',  'playbuzz' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Location', 'playbuzz' ); ?></th>
								<td>
									<select name="<?php echo esc_attr( $this->get_option_name() ); ?>[show]">
										<option value="header" <?php if ( 'header' == $options['show'] ) echo 'selected'; ?>><?php esc_html_e( 'Above the content',  'playbuzz' ); ?></option>
										<option value="footer" <?php if ( 'footer' == $options['show'] ) echo 'selected'; ?>><?php esc_html_e( 'Bellow the content', 'playbuzz' ); ?></option>
									</select>
								</td>
							</tr>
						</table>

						<?php submit_button(); ?> 

					</div>

				</form>

			<?php } elseif( $playbuzz_active_tab == 'shortcodes' ) { ?>

				<div class="playbuzz_shortcodes">

					<h3><?php esc_html_e( 'Item Shortcode', 'playbuzz' ); ?></h3>
					<p><?php printf( __( 'Choose any Playful Content item from %s and easily embed it in a post.', 'playbuzz' ), '<a href="https://www.playbuzz.com/" target="_blank">playbuzz.com</a>' ); ?></p>
					<p><?php esc_html_e( 'For basic use, paste the item URL into your text editor and go to the visual editor to make sure it loads.', 'playbuzz' ); ?></p>
					<p><?php esc_html_e( 'For more advance usage, use the following shortcode if you want to adjust the item appearance:', 'playbuzz' ); ?></p>
					<p><code>[playbuzz-item url="https://www.playbuzz.com/llamap10/how-weird-are-you" comments="false"]</code></p>
					<p><?php printf( __( 'You can set default appearance settings in the <a href="%s">Site Settings</a> tab.', 'playbuzz' ), esc_url('?page='.$this->get_option_name().'&tab=embed') ); ?></p>
					<p><?php esc_html_e( 'Or you can override the default appearance and customize each item with the following shortcode attributes:', 'playbuzz' ); ?></p>
					<dl>
						<dt>url</dt>
						<dd>
							<p><?php esc_html_e( 'The URL of the item that will be displayed.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: URL', 'playbuzz' ); ?></p>
						</dd>
						<dt>info</dt>
						<dd>
							<p><?php esc_html_e( 'Show item info (thumbnail, name, description, editor, etc).', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>shares</dt>
						<dd>
							<p><?php esc_html_e( 'Show sharing buttons.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>comments</dt>
						<dd>
							<p><?php esc_html_e( 'Show comments control from the item page.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>recommend</dt>
						<dd>
							<p><?php esc_html_e( 'Show recommendations for more items.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>links</dt>
						<dd>
							<p><?php esc_html_e( 'Destination page, containing the [playbuzz-section] shortcode, where new items will be displayed.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: URL ; Default: https://www.playbuzz.com/', 'playbuzz' ); ?></p>
						</dd>
						<dt>width</dt>
						<dd>
							<p><?php esc_html_e( 'Define custom width in pixels.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: auto', 'playbuzz' ); ?></p>
						</dd>
						<dt>height</dt>
						<dd>
							<p><?php esc_html_e( 'Define custom height in pixels.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: auto', 'playbuzz' ); ?></p>
						</dd>
						<dt>margin-top</dt>
						<dd>
							<p><?php esc_html_e( 'Define custom margin-top in pixels.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: 0px', 'playbuzz' ); ?></p>
						</dd>
					</dl>

				</div>

				<div class="playbuzz_shortcodes">

					<h3><?php esc_html_e( 'Section Shortcode', 'playbuzz' ); ?></h3>
					<p><?php printf( __( 'Choose any list of Playful Items in a specific vertical from %s and easily embed it in a post. This is best used as a "Playful Section" displaying items in the selected tags (topics).', 'playbuzz' ), '<a href="https://playbuzz.com/" target="_blank">playbuzz.com</a>' ); ?></p>
					<p><?php esc_html_e( 'For basic use, paste the section URL into your text editor and go to the visual editor to make sure it loads.', 'playbuzz' ); ?></p>
					<p><?php esc_html_e( 'Use the following shortcode if you want to adjust the settings of your embedded section:', 'playbuzz' ); ?></p>
					<p><code>[playbuzz-section tags="All" width="600"]</code></p>
					<p><?php esc_html_e( 'You can tweak the general settings for the section with the following shortcode attributes:', 'playbuzz' ); ?></p>
					<dl>
						<dt>tags</dt>
						<dd>
							<p><?php esc_html_e( 'Filters the content shown by comma separated tags.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: All', 'playbuzz' ); ?></p>
						</dd>
						<dt>shares</dt>
						<dd>
							<p><?php esc_html_e( 'Show sharing buttons.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>comments</dt>
						<dd>
							<p><?php esc_html_e( 'Show comments control from the item page.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>recommend</dt>
						<dd>
							<p><?php esc_html_e( 'Show recommendations for more items.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: Boolean (true/false) ; Default: true', 'playbuzz' ); ?></p>
						</dd>
						<dt>links</dt>
						<dd>
							<p><?php esc_html_e( 'Destination page, containing the [playbuzz-section] shortcode, where new items will be displayed.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: URL ; Default: https://www.playbuzz.com/', 'playbuzz' ); ?></p>
						</dd>
						<dt>width</dt>
						<dd>
							<p><?php esc_html_e( 'Define custom width in pixels.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: auto', 'playbuzz' ); ?></p>
						</dd>
						<dt>height</dt>
						<dd>
							<p><?php esc_html_e( 'Define custom height in pixels.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: auto', 'playbuzz' ); ?></p>
						</dd>
						<dt>margin-top</dt>
						<dd>
							<p><?php esc_html_e( 'Define custom margin-top in pixels.', 'playbuzz' ); ?></p>
							<p><?php esc_html_e( 'Type: String ; Default: 0px', 'playbuzz' ); ?></p>
						</dd>
					</dl>

				</div>

			<?php } elseif( $playbuzz_active_tab == 'feedback' ) { ?>

				<div class="playbuzz_feedback">

					<h3><?php esc_html_e( 'We Are Listening', 'playbuzz' ); ?></h3>

					<p><?php esc_html_e( 'We’d love to know about your experiences with our WordPress plugin and beyond. Drop us a line using the form below', 'playbuzz' ); ?></p>

					<div class="playbuzz_feedback_message">
						<p><br></p>
					</div>

					<form id="playbuzz_feedback_form" method="post">
						<p>
							<label for="fullName"><?php esc_html_e( 'Your Name', 'playbuzz' ); ?></label>
							<input type="text" name="fullName" class="regular-text">
						</p>
						<p>
							<label for="email"><?php esc_html_e( 'Email (so we can write you back)', 'playbuzz' ); ?></label>
							<input type="text" name="email" class="regular-text" value="<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>">
						</p>
						<p>
							<label for="message"><?php esc_html_e( 'Message', 'playbuzz' ); ?></label>
							<textarea name="message" rows="5" class="widefat" placeholder="<?php esc_attr_e( 'What\'s on your mind?', 'playbuzz' ); ?>"></textarea>
						</p>
						<input type="hidden" name="subject" value="<?php esc_attr_e( 'WordPress plugin feedback', 'playbuzz' ); ?>">
						<input type="button" name="button" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit', 'playbuzz' ); ?>">
					</form>

				</div>

				<div class="playbuzz_feedback">

					<h3><?php esc_html_e( 'Enjoying the Playbuzz WordPress Plugin?', 'playbuzz' ); ?></h3>
					<p><?php printf( __( '<a href="%s" target="_blank">Rate us</a> on the WordPress Plugin Directory to help others to discover the engagement value of Playbuzz embeds!', 'playbuzz' ), 'https://wordpress.org/support/view/plugin-reviews/playbuzz#postform' ); ?></p>

				</div>

				<div class="playbuzz_feedback">

					<h3><?php esc_html_e( 'Become a Premium Playbuzz Publisher', 'playbuzz' ); ?></h3>
					<p><?php esc_html_e( 'Want to learn how Playbuzz can take your publication’s engagement to new heights?', 'playbuzz' ); ?> <a href="https://publishers.playbuzz.com/" target="_blank"><?php esc_html_e( 'Lets Talk!', 'playbuzz' ); ?></a></p>

				</div>

				<div class="playbuzz_feedback">

					<h3><?php esc_html_e( 'Join the Playbuzz Publishers Community', 'playbuzz' ); ?></h3>
					<p>
						<a href="https://www.facebook.com/playbuzzpublishers" target="_blank" class="playbuzz_facebook"></a>
						<a href="https://twitter.com/PlayBuzzPublish" target="_blank" class="playbuzz_twitter"></a>
						<a href="https://plus.google.com/101288499306597838075/posts" target="_blank" class="playbuzz_googleplus"></a>
						<a href="https://www.linkedin.com/company/playbuzz" target="_blank" class="playbuzz_linkedin"></a>
						<a href="https://www.pinterest.com/playbuzz/" target="_blank" class="playbuzz_pinterest"></a>
						<a href="http://play-buzz.tumblr.com/" target="_blank" class="playbuzz_tumblr"></a>
						<a href="https://instagram.com/play_buzz" target="_blank" class="playbuzz_instagram"></a>
					</p>

				</div>

			<?php } ?>

		</div>
		<?php

	}

}
new PlaybuzzAdmin();
