<?php
if ( !class_exists( 'OoyalaApi' ) )
	require dirname( __FILE__ ) . '/OoyalaApi.php';

class Ooyala_Options {
	public static $instance;

	public function __construct() {
		self::$instance = $this;
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
	}

	public function add_menu_page() {
		add_options_page( __( 'Ooyala Video Options', 'ooyalavideo' ), __( 'Ooyala', 'ooyalavideo' ), 'manage_options', 'ooyala-options', array( $this, 'render_options_page' ) );
	}

	public function settings_init() {
		register_setting( 'ooyala_settings', 'ooyala', array( $this, 'sanitize_settings' ) );
		add_settings_section( 'ooyala-general', '', '__return_false', 'ooyala-options' );
		add_settings_field( 'ooyala-partner-code', __( 'Partner Code', 'ooyalavideo' ), array( $this, 'partner_code' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-partner-secret', __( 'Partner Secret (v1)', 'ooyalavideo' ), array( $this, 'secret_code' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-api-key', __( 'API Key (v2)', 'ooyalavideo' ), array( $this, 'api_key' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-api-secret', __( 'API Secret (v2)', 'ooyalavideo' ), array( $this, 'api_secret' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-player-id', __( 'Player ID (v3)', 'ooyalavideo' ), array( $this, 'player_id' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-show-in-feed', __( 'Show link to blog post in feed', 'ooyalavideo' ), array( $this, 'show_in_feed' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-video-width', __( 'Video object width', 'ooyalavideo' ), array( $this, 'video_width' ), 'ooyala-options', 'ooyala-general' );
		add_settings_field( 'ooyala-video-status', __( 'Default video status', 'ooyalavideo' ), array( $this, 'video_status' ), 'ooyala-options', 'ooyala-general' );
	}

	public function partner_code() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['partner_code'] ) )
			$options['partner_code'] = '';
		?><input type="text" id="ooyala-partner-code" name="ooyala[partner_code]" value="<?php echo esc_attr( $options['partner_code'] ); ?>" class="regular-text" /><?php
	}

	public function secret_code() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['secret_code'] ) )
			$options['secret_code'] = '';
		?><input type="text" id="ooyala-partner-secret" name="ooyala[secret_code]" value="<?php echo esc_attr( $options['secret_code'] ); ?>" class="regular-text" /><?php
	}

	public function api_key() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['api_key'] ) )
			$options['api_key'] = '';
		?><input type="text" id="ooyala-api-key" name="ooyala[api_key]" value="<?php echo esc_attr( $options['api_key'] ); ?>" class="regular-text" /><?php
	}

	public function api_secret() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['api_secret'] ) )
			$options['api_secret'] = '';
		?><input type="text" id="ooyala-api-secret" name="ooyala[api_secret]" value="<?php echo esc_attr( $options['api_secret'] ); ?>" class="regular-text" /><?php
	}

	public function player_id() {
		$options = get_option( 'ooyala', array() );
		if ( isset( $options['players'] ) ) :
			if ( empty( $options['player_id'] ) ) {
				$options['player_id'] = $options['players'][0];
				update_option( 'ooyala', $options );
			}
			?>
			<select id="ooyala-player-id" name="ooyala[player_id]"> <?php
			foreach ( (array) $options['players'] as $player ) : ?>
					<option value="<?php echo esc_attr( $player ); ?>"><?php echo esc_html( $player ); ?></option>
			<?php endforeach; ?>
			</select> <?php
		else : ?>
			<input type="text" id="ooyala-player-id" name="ooyala[player_id]" value="<?php echo isset( $options['player_id'] ) ? esc_attr( $options['player_id'] ) : ''; ?>" class="regular-text" />
		<?php endif; 
	}

	public function show_in_feed() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['show_in_feed'] ) )
			$options['show_in_feed'] = 0;
		?><input type="checkbox" id="ooyala-show-in-feed" name="ooyala[show_in_feed]" value="<?php echo esc_attr( $options['show_in_feed'] ); ?>" <?php checked( $options['show_in_feed'] ); ?> />
		<span class="description"><?php echo esc_html( 'Video embedding in feeds is not yet available', 'ooyalavideo' ); ?></span><?php
	}

	public function video_width() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['video_width'] ) )
			$options['video_width'] = 250;
		?><input type="text" id="ooyala-video-width" name="ooyala[video_width]" value="<?php echo esc_attr( $options['video_width'] ); ?>" class="regular-text" /><?php		
	}

	public function video_status() {
		$options = get_option( 'ooyala', array() );
		if ( ! isset( $options['video_status'] ) )
			$options['video_status'] = 'pending';
		?><select id="ooyala-video-status" name="ooyala[video_status]">
				<option value="pending" <?php selected( $options['video_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'ooyalavideo' ); ?></option>
				<option value="live" <?php selected( $options['video_status'], 'live' ); ?>><?php esc_html_e( 'Live', 'ooyalavideo' ); ?></option>
		</select><?php
	}

	public function sanitize_settings( $options ) {
		foreach ( $options as $option_key => &$option_value ) {
			switch ( $option_key ) {
				case 'partner_code' :
				case 'secret_code' :
				case 'api_key' :
				case 'api_secret' :
				case 'video_status' :
					$option_value = esc_attr( $option_value );
					break;

				case 'show_in_feed' :
					$option_value = absint( $option_value );
					break;

				case 'video_width':
					$option_value = absint( $option_value );
					if ( $option_value > 800 )
						$option_value = 800;
					elseif ( $option_value < 250 )
						$option_value = 250;
					$options[$option_key] = $option_value;
					break;
			}
		}
		return $options;
	}

	public function render_options_page() {
		$options = get_option( 'ooyala' );

		if ( isset( $options['api_key'], $options['api_secret'] ) ) {
			try {
				$api = new OoyalaApi( $options['api_key'], $options['api_secret'] );
				$players = $api->get( "players" );
			} catch ( Exception $e ) {
				$players = array();
			}

			if ( $players && ! empty( $players->items ) ) {
				$options['players'] = array();
				foreach ( $players->items as $player )
					$options['players'][] = $player->id;
			}

			if ( $players ) {
				$options['players'] = array();
				foreach ( $players->items as $player ) {
					$options['players'][] = $player->id;
				}
				if ( empty( $options['player_id'] ) ) {
					$options['player_id'] = $options['players'][0];
				}	
			}
			
			update_option( 'ooyala', $options );
		}

		?>
		<style type="text/css" media="screen">
			#icon-ooyala {
				background: transparent url(<?php echo esc_url( plugins_url( 'img/ooyala-icon.png', __FILE__ ) ); ?>) no-repeat;
			}
		</style>

		<div class="wrap">
			<?php screen_icon( 'ooyala' ); ?>
			<h2><?php esc_html_e( 'Ooyala Settings', 'ooyalavideo' ); ?></h2>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'ooyala_settings' );
					do_settings_sections( 'ooyala-options' );
					submit_button();
				?>
			</form>
		</div><?php
		
	}
}

new Ooyala_Options;
