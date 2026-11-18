<?php
/**
 * Environment badges: Debug Mode, Sandbox and Not proxied.
 *
 * Badges show as items in the admin bar. Where no admin bar is drawn (wp-login.php,
 * logged-out visitors, themes that remove it), they show as small tabs docked to
 * the bottom-left corner instead, so they never float over page content.
 */

namespace Automattic\VIP\Helpers;

class Environment_Badges {
	const STYLE_HANDLE  = 'vip-environment-badges';
	const STYLE_VERSION = '1.0';

	/**
	 * @var array<string, array{label: string, short: string, more: string, sr_text: string, title: string, href: string, details: array<string, string>, order: int}>
	 */
	private static $badges = [];

	/**
	 * Registers a badge. Call it before `admin_bar_menu`, e.g. on `init` or an `*_enqueue_scripts` hook.
	 *
	 * @param string $id   Unique ID. Also picks the colours, via the `vip-badge--{$id}` class in the stylesheet.
	 * @param array  $args {
	 *     @type string                $label     Visible label.
	 *     @type string                $short     Optional shorter label for the admin bar on small screens.
	 *     @type string                $more      Optional extra text shown after the label. In the dock it's revealed on hover or focus,
	 *                                            and in the admin bar it's hidden on small screens.
	 *     @type string                $sr_text   Optional text for screen readers only, e.g. what clicking does.
	 *     @type string                $title     Optional tooltip.
	 *     @type string                $href      Optional link.
	 *     @type array<string, string> $details   Optional name => value pairs, shown in a panel that the badge opens.
	 *     @type int                   $order     Badges with a lower order come first. Default 10.
	 * }
	 */
	public static function add( string $id, array $args ): void {
		if ( empty( self::$badges ) ) {
			self::add_hooks();
		}

		self::$badges[ $id ] = array_merge( [
			'label'   => '',
			'short'   => '',
			'more'    => '',
			'sr_text' => '',
			'title'   => '',
			'href'    => '',
			'details' => [],
			'order'   => 10,
		], $args );

		// Registered from an enqueue hook, too late for our own enqueue callback to run.
		if ( did_action( 'wp_enqueue_scripts' ) || did_action( 'admin_enqueue_scripts' ) || did_action( 'login_enqueue_scripts' ) ) {
			static::enqueue_style();
		}
	}

	/**
	 * @return array<string, array{label: string, short: string, more: string, sr_text: string, title: string, href: string, details: array<string, string>, order: int}>
	 */
	public static function get_badges(): array {
		$badges = self::$badges;
		uasort( $badges, fn( $a, $b ) => $a['order'] <=> $b['order'] );

		return $badges;
	}

	private static function add_hooks(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_style' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_style' ] );
		add_action( 'login_enqueue_scripts', [ __CLASS__, 'enqueue_style' ] );

		add_action( 'admin_bar_menu', [ __CLASS__, 'add_admin_bar_nodes' ] );

		// Output late, after the admin bar would have been drawn. wp-admin draws it on every page except iframes,
		// which hide admin_footer output, so admin_footer only matters when a plugin unhooks the admin bar.
		add_action( 'wp_footer', [ __CLASS__, 'render_dock' ], 9999 );
		add_action( 'admin_footer', [ __CLASS__, 'render_dock' ], 9999 );
		add_action( 'login_footer', [ __CLASS__, 'render_dock' ], 9999 );
	}

	public static function enqueue_style(): void {
		wp_enqueue_style( self::STYLE_HANDLE, plugins_url( 'assets/environment-badges.css', __FILE__ ), [], self::STYLE_VERSION );
	}

	/**
	 * @param \WP_Admin_Bar $wp_admin_bar
	 */
	public static function add_admin_bar_nodes( $wp_admin_bar ): void {
		foreach ( static::get_badges() as $id => $badge ) {
			// No href or child nodes: the link or disclosure lives inside the badge's own markup.
			$wp_admin_bar->add_node( [
				'id'     => 'vip-badge-' . $id,
				'parent' => 'top-secondary',
				'title'  => self::get_badge_html( $id, $badge ),
				'meta'   => [
					'class' => 'vip-badge vip-badge--' . $id,
				],
			] );
		}
	}

	public static function render_dock(): void {
		// The badges are already in the admin bar.
		if ( did_action( 'wp_after_admin_bar_render' ) ) {
			return;
		}

		$badges = static::get_badges();
		if ( empty( $badges ) ) {
			return;
		}

		echo '<div id="vip-environment-badges">';
		foreach ( $badges as $id => $badge ) {
			echo self::get_badge_html( $id, $badge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_badge_html().
		}
		echo '</div>';
	}

	/**
	 * The same markup is used in the admin bar and the dock. The outer chip carries the badge's colours; the inner
	 * "face" is a link, a native <details> disclosure (keyboard, click and expanded state for free), or plain text.
	 */
	private static function get_badge_html( string $id, array $badge ): string {
		$title = $badge['title'] ? sprintf( ' title="%s"', esc_attr( $badge['title'] ) ) : '';
		$label = self::get_label_html( $badge );

		if ( $badge['href'] ) {
			$face = sprintf( '<a class="vip-badge-face" href="%s"%s>%s</a>', esc_url( $badge['href'] ), $title, $label );
		} elseif ( $badge['details'] ) {
			$rows = '';
			foreach ( $badge['details'] as $name => $value ) {
				$rows .= sprintf( '<dt>%s</dt><dd>%s</dd>', esc_html( $name ), esc_html( $value ) );
			}

			$face = sprintf( '<details><summary class="vip-badge-face"%s>%s</summary><dl class="vip-badge-details">%s</dl></details>', $title, $label, $rows );
		} else {
			$face = sprintf( '<span class="vip-badge-face"%s>%s</span>', $title, $label );
		}

		return sprintf( '<div class="vip-badge-chip vip-badge--%s">%s</div>', esc_attr( $id ), $face );
	}

	private static function get_label_html( array $badge ): string {
		if ( $badge['short'] ) {
			// Small screens show the abbreviation instead. Both stay in the accessible name, so speech input can use either.
			$html = sprintf( '<span class="vip-badge-label">%s</span> <abbr class="vip-badge-short">%s</abbr>', esc_html( $badge['label'] ), esc_html( $badge['short'] ) );
		} else {
			$html = esc_html( $badge['label'] );
		}

		if ( $badge['more'] ) {
			$html .= sprintf( '<span class="vip-badge-more"> %s</span>', esc_html( $badge['more'] ) );
		}

		if ( $badge['sr_text'] ) {
			$html .= sprintf( '<span class="vip-badge-sr">%s</span>', esc_html( $badge['sr_text'] ) );
		}

		return $html;
	}
}
