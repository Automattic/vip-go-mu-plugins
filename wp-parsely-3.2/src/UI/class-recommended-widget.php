<?php
/**
 * Recommended Widget file
 *
 * This provides a widget to put on a page, will have parsely recommended articles
 *
 * @category   Components
 * @package    WordPress
 * @subpackage Parse.ly
 */

declare(strict_types=1);

namespace Parsely\UI;

use WP_Widget;

use const Parsely\PARSELY_FILE;

/**
 * This is the class for the recommended widget.
 */
final class Recommended_Widget extends WP_Widget {
	/**
	 * This is the constructor function.
	 */
	public function __construct() {
		parent::__construct(
			'Parsely_Recommended_Widget',
			__( 'Parse.ly Recommended Widget', 'wp-parsely' ),
			array(
				'classname'   => 'Recommended_Widget parsely-recommended-widget-hidden',
				'description' => __( 'Display a list of post recommendations, personalized for a visitor or the current post.', 'wp-parsely' ),
			)
		);
	}

	/**
	 * Get the URL for the Recommendations API (GET /related).
	 *
	 * @see https://www.parse.ly/help/api/recommendations#get-related
	 *
	 * @internal While this is a public method now, this should be moved to a new class.
	 *
	 * @since 2.5.0
	 *
	 * @param string      $api_key          Publisher Site ID (API key).
	 * @param int|null    $published_within Publication filter start date; see https://www.parse.ly/help/api/time for
	 *                                 formatting details. No restriction by default.
	 * @param string|null $sort             What to sort the results by. There are currently 2 valid options: `score`, which
	 *                                 will sort articles by overall relevance and `pub_date` which will sort results by
	 *                                 their publication date. The default is `score`.
	 * @param string|null $boost            Available for sort=score only. Sub-sort value to re-rank relevant posts that
	 *                                 received high e.g. views; default is undefined.
	 * @param int         $return_limit     Number of records to retrieve; defaults to "10".
	 * @return string API URL.
	 */
	private function get_api_url( string $api_key, ?int $published_within, ?string $sort, ?string $boost, int $return_limit ): string {
		$related_api_endpoint = 'https://api.parsely.com/v2/related';

		$query_args = array(
			'apikey' => $api_key,
			'sort'   => $sort,
			'limit'  => $return_limit,
		);

		if ( 'score' === $sort && 'no-boost' !== $boost ) {
			$query_args['boost'] = $boost;
		}

		if ( null !== $published_within && 0 !== $published_within ) {
			$query_args['pub_date_start'] = $published_within . 'd';
		}

		return add_query_arg( $query_args, $related_api_endpoint );
	}

	/**
	 * This is the widget function
	 *
	 * @param array $args Widget Arguments.
	 * @param array $instance Values saved to the db.
	 * @return void
	 */
	public function widget( $args, $instance ): void {
		if ( ! $this->api_key_and_secret_are_populated() ) {
			return;
		}

		$removed_title_esc = remove_filter( 'widget_title', 'esc_html' );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'] );

		if ( $removed_title_esc ) {
			add_filter( 'widget_title', 'esc_html' );
		}

		$title_html = $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];
		echo wp_kses_post( $title_html );

		// Set up the variables.
		$options = get_option( 'parsely' );
		$api_url = $this->get_api_url(
			$options['apikey'],
			$instance['published_within'],
			$instance['sort'],
			$instance['boost'],
			(int) $instance['return_limit']
		);

		$recommended_widget_script_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/recommended-widget.asset.php';

		?>

		<div class="parsely-recommended-widget"
			data-parsely-widget-display-author="<?php echo esc_attr( wp_json_encode( isset( $instance['display_author'] ) && $instance['display_author'] ) ); ?>"
			data-parsely-widget-display-direction="<?php echo esc_attr( $instance['display_direction'] ?? '' ); ?>"
			data-parsely-widget-api-url="<?php echo esc_url( $api_url ); ?>"
			data-parsely-widget-img-display="<?php echo esc_attr( $instance['img_src'] ?? '' ); ?>"
			data-parsely-widget-permalink="<?php echo esc_url( get_permalink() ); ?>"
			data-parsely-widget-personalized="<?php echo esc_attr( wp_json_encode( isset( $instance['personalize_results'] ) && $instance['personalize_results'] ) ); ?>"
			data-parsely-widget-id="<?php echo esc_attr( $this->id ); ?>"
		></div>

		<?php

		wp_register_script(
			'wp-parsely-recommended-widget',
			plugin_dir_url( PARSELY_FILE ) . 'build/recommended-widget.js',
			$recommended_widget_script_asset['dependencies'],
			$recommended_widget_script_asset['version'],
			true
		);

		wp_register_style(
			'wp-parsely-recommended-widget',
			plugin_dir_url( PARSELY_FILE ) . 'build/recommended-widget.css',
			array(),
			$recommended_widget_script_asset['version']
		);

		wp_enqueue_script( 'wp-parsely-recommended-widget' );
		wp_enqueue_style( 'wp-parsely-recommended-widget' );

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * This is the form function
	 *
	 * @param array $instance Values saved to the db.
	 */
	public function form( $instance ): void {
		if ( ! $this->api_key_and_secret_are_populated() ) {
			$settings_page_url = add_query_arg( 'page', 'parsely', get_admin_url() . 'options-general.php' );

			$message = sprintf(
				/* translators: %s: Plugin settings page URL */
				__( 'The <i>Parse.ly Site ID</i> and <i>Parse.ly API Secret</i> fields need to be populated on the <a href="%s">Parse.ly settings page</a> for this widget to work.', 'wp-parsely' ),
				esc_url( $settings_page_url )
			);

			echo '<p>', wp_kses_post( $message ), '</p>';

			return;
		}

		// editable fields: title.
		$title               = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$return_limit        = ! empty( $instance['return_limit'] ) ? (int) $instance['return_limit'] : 5;
		$display_direction   = ! empty( $instance['display_direction'] ) ? $instance['display_direction'] : 'vertical';
		$published_within    = ! empty( $instance['published_within'] ) ? $instance['published_within'] : 0;
		$sort                = ! empty( $instance['sort'] ) ? $instance['sort'] : 'score';
		$boost               = ! empty( $instance['boost'] ) ? $instance['boost'] : 'views';
		$personalize_results = ! empty( $instance['personalize_results'] ) ? $instance['personalize_results'] : false;
		$img_src             = ! empty( $instance['img_src'] ) ? $instance['img_src'] : 'parsely_thumb';
		$display_author      = ! empty( $instance['display_author'] ) ? $instance['display_author'] : false;

		$instance['return_limit']        = $return_limit;
		$instance['display_direction']   = $display_direction;
		$instance['published_within']    = $published_within;
		$instance['sort']                = $sort;
		$instance['boost']               = $boost;
		$instance['personalize_results'] = $personalize_results;
		$instance['img_src']             = $img_src;
		$instance['display_author']      = $display_author;

		$boost_params = $this->get_boost_params();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-parsely' ); ?></label>
			<br>
			<input type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'published_within' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'published_within_label' ) ); ?>"><?php esc_html_e( 'Published within', 'wp-parsely' ); ?></label>
			<input type="number" id="<?php echo esc_attr( $this->get_field_id( 'published_within' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'published_within' ) ); ?>" value="<?php echo esc_attr( (string) $instance['published_within'] ); ?>" min="0" max="30"
				class="tiny-text" aria-labelledby="<?php echo esc_attr( $this->get_field_id( 'published_within_label' ) ); ?> <?php echo esc_attr( $this->get_field_id( 'published_within' ) ); ?> <?php echo esc_attr( $this->get_field_id( 'published_within_unit' ) ); ?>" />
			<span id="<?php echo esc_attr( $this->get_field_id( 'published_within_unit' ) ); ?>"> <?php esc_html_e( 'days (0 for no limit).', 'wp-parsely' ); ?></span>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'return_limit' ) ); ?>"><?php esc_html_e( 'Number of posts to show (max 20):', 'wp-parsely' ); ?></label>
			<input type="number" id="<?php echo esc_attr( $this->get_field_id( 'return_limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'return_limit' ) ); ?>" value="<?php echo esc_attr( $instance['return_limit'] ); ?>" min="1" max="20" class="tiny-text" />
		</p>
		<p>
			<fieldset>
				<legend><?php esc_html_e( 'Display entries:', 'wp-parsely' ); ?></legend>
				<p>
					<input type="radio" id="<?php echo esc_attr( $this->get_field_id( 'display_direction_horizontal' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_direction' ) ); ?>"<?php checked( $instance['display_direction'], 'horizontal' ); ?> value="horizontal" />
					<label for="<?php echo esc_attr( $this->get_field_id( 'display_direction_horizontal' ) ); ?>"><?php esc_html_e( 'Horizontally', 'wp-parsely' ); ?></label>
					<br />
					<input type="radio" id="<?php echo esc_attr( $this->get_field_id( 'display_direction_vertical' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_direction' ) ); ?>"<?php checked( $instance['display_direction'], 'vertical' ); ?> value="vertical" />
					<label for="<?php echo esc_attr( $this->get_field_id( 'display_direction_vertical' ) ); ?>"><?php esc_html_e( 'Vertically', 'wp-parsely' ); ?></label>
				</p>
			</fieldset>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'sort' ) ); ?>"><?php esc_html_e( 'Sort by:', 'wp-parsely' ); ?></label>
			<br>
			<select id="<?php echo esc_attr( $this->get_field_id( 'sort' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'sort' ) ); ?>" class="widefat">
				<option<?php selected( $instance['sort'], 'score' ); ?> value="score"><?php esc_html_e( 'Score (relevancy, boostable)', 'wp-parsely' ); ?></option>
				<option<?php selected( $instance['sort'], 'pub_date' ); ?> value="pub_date"><?php esc_html_e( 'Publish date (not boostable)', 'wp-parsely' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'boost' ) ); ?>"><?php esc_html_e( 'Boost by:', 'wp-parsely' ); ?></label>
			<br>
			<select id="<?php echo esc_attr( $this->get_field_id( 'boost' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'boost' ) ); ?>" class="widefat">
				<?php foreach ( $boost_params as $boost_param => $description ) { ?>
				<option<?php selected( $instance['boost'], $boost_param ); ?> value="<?php echo esc_attr( $boost_param ); ?>"><?php echo esc_html( $description ); ?></option>
			<?php } ?>
			</select>

		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'img_src' ) ); ?>"><?php esc_html_e( 'Image source:', 'wp-parsely' ); ?></label>
			<br>
			<select id="<?php echo esc_attr( $this->get_field_id( 'img_src' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'img_src' ) ); ?>" class="widefat">
				<option<?php selected( $instance['img_src'], 'parsely_thumb' ); ?> value="parsely_thumb"><?php esc_html_e( 'Parse.ly generated thumbnail (85x85px)', 'wp-parsely' ); ?></option>
				<option<?php selected( $instance['img_src'], 'original' ); ?> value="original"><?php esc_html_e( 'Original image', 'wp-parsely' ); ?></option>
				<option<?php selected( $instance['img_src'], 'none' ); ?> value="none"><?php esc_html_e( 'No image', 'wp-parsely' ); ?></option>
			</select>
		</p>
		<p>
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'display_author' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'display_author' ) ); ?>" value="display_author"<?php checked( $instance['display_author'], 'display_author' ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_author' ) ); ?>"><?php esc_html_e( 'Display author', 'wp-parsely' ); ?></label>
			<br />
			<input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'personalize_results' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'personalize_results' ) ); ?>" value="personalize_results"<?php checked( $instance['personalize_results'], 'personalize_results' ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'personalize_results' ) ); ?>"><?php esc_html_e( 'Personalize recommended results', 'wp-parsely' ); ?></label>
		</p>
		<?php
	}

	/**
	 * This is the update function
	 *
	 * @param array $new_instance The new values for the db.
	 * @param array $old_instance Values saved to the db.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance                        = $old_instance;
		$instance['title']               = trim( wp_kses_post( $new_instance['title'] ) );
		$instance['published_within']    = is_int( $new_instance['published_within'] ) ? $new_instance['published_within'] : (int) trim( $new_instance['published_within'] );
		$instance['return_limit']        = (int) $new_instance['return_limit'] <= 20 ? (int) $new_instance['return_limit'] : 20;
		$instance['display_direction']   = trim( $new_instance['display_direction'] );
		$instance['sort']                = trim( $new_instance['sort'] );
		$instance['boost']               = trim( $new_instance['boost'] );
		$instance['display_author']      = $new_instance['display_author'];
		$instance['personalize_results'] = $new_instance['personalize_results'];
		$instance['img_src']             = trim( $new_instance['img_src'] );
		return $instance;
	}

	/**
	 * Return the list of boost parameters, values and labels.
	 *
	 * @since 2.5.0
	 *
	 * @return array<string, string> Boost parameters values and labels.
	 */
	private function get_boost_params(): array {
		return array(
			'no-boost'              => __( 'No boost', 'wp-parsely' ),
			'views'                 => __( 'Page views', 'wp-parsely' ),
			'mobile_views'          => __( 'Page views on mobile devices', 'wp-parsely' ),
			'tablet_views'          => __( 'Page views on tablet devices', 'wp-parsely' ),
			'desktop_views'         => __( 'Page views on desktop devices', 'wp-parsely' ),
			'visitors'              => __( 'Unique page visitors, total', 'wp-parsely' ),
			'visitors_new'          => __( 'New visitors', 'wp-parsely' ),
			'visitors_returning'    => __( 'Returning visitors', 'wp-parsely' ),
			'engaged_minutes'       => __( 'Total engagement time in minutes', 'wp-parsely' ),
			'avg_engaged'           => __( 'Engaged minutes spent by total visitors', 'wp-parsely' ),
			'avg_engaged_new'       => __( 'Average engaged minutes spent by new visitors', 'wp-parsely' ),
			'avg_engaged_returning' => __( 'Average engaged minutes spent by returning visitors', 'wp-parsely' ),
			'social_interactions'   => __( 'Total for Facebook, Twitter, LinkedIn, and Pinterest', 'wp-parsely' ),
			'fb_interactions'       => __( 'Count of Facebook shares, likes, and comments', 'wp-parsely' ),
			'tw_interactions'       => __( 'Count of Twitter tweets and retweets', 'wp-parsely' ),
			'pi_interactions'       => __( 'Count of Pinterest pins', 'wp-parsely' ),
			'social_referrals'      => __( 'Page views where the referrer was any social network', 'wp-parsely' ),
			'fb_referrals'          => __( 'Page views where the referrer was facebook.com', 'wp-parsely' ),
			'tw_referrals'          => __( 'Page views where the referrer was twitter.com', 'wp-parsely' ),
			'pi_referrals'          => __( 'Page views where the referrer was pinterest.com', 'wp-parsely' ),
		);
	}

	/**
	 * Check if both the API key and API secret settings are populated with non-empty values.
	 *
	 * @since 2.5.0
	 *
	 * @return bool True if apikey and api_secret settings are not empty strings. False otherwise.
	 */
	private function api_key_and_secret_are_populated(): bool {
		$options = get_option( 'parsely' );

		// No options are saved, so API key is not available.
		if ( ! is_array( $options ) ) {
			return false;
		}

		// Parse.ly Site ID settings field is not populated.
		if ( ! array_key_exists( 'apikey', $options ) || '' === $options['apikey'] ) {
			return false;
		}

		// Parse.ly API Secret settings field is not populated.
		if ( ! array_key_exists( 'api_secret', $options ) || '' === $options['api_secret'] ) {
			return false;
		}

		return true;
	}
}
