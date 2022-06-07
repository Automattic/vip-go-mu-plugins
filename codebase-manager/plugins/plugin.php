<?php

namespace Automattic\VIP\CodebaseManager;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Remote objects use camelCase.

class Plugin {
	private string $file;
	private string $name;
	private string $plugin_url;
	private string $update_url;
	private ?string $new_version;
	private array $vulnerabilities;

	public function __construct( string $plugin_file, array $plugin_data, object $update_info, array $vulnerabilities ) {
		$this->file = $plugin_file;
		$this->name = $plugin_data['Name'] ?? 'Missing Plugin Name';

		$this->plugin_url  = $plugin_data['PluginURI'] ?? '';
		$this->update_url  = $update_info->url ?? '';
		$this->new_version = $update_info->new_version ?? null;

		$this->vulnerabilities = $vulnerabilities;
	}

	public function display_version_update_information( int $number_of_columns ): void {
		if ( ! $this->has_available_update() ) {
			return;
		}

		printf( '<tr class="plugin-update-tr%s">', $this->is_active() ? ' active' : '' );
		printf( '<td colspan="%s" class="plugin-update colspanchange%s">', esc_attr( $number_of_columns ), $this->has_vulnerabilities() ? ' hide-box-shadow' : '' );
		print( '<div class="update-message notice inline notice-warning notice-alt"><p>' );

		// In the future, could do the fancy iframe that core does here. It also allows 3rd party plugins to hook in with `plugins_api` filter to better expose their changelogs.
		$details_url = $this->get_details_url();
		if ( ! empty( $details_url ) ) {
			/* translators: 1: Plugin name, 2: Details URL, 3: Version number. */
			$message = __( 'There is a new version of %1$s available. <a href="%2$s" target="_blank">View version %3$s details</a>.' );
			$message = sprintf( $message, esc_html( $this->name ), esc_url( $details_url ), esc_html( $this->new_version ) );
			echo wp_kses( $message, [ 'a' => [ 'href' => [], 'target' => [] ] ] ); // phpcs:ignore
		} else {
			/* translators: 1: Plugin name */
			echo esc_html( sprintf( __( 'There is a new version of %1$s available.' ), $this->name ) );
		}

		print( '</p></div></td></tr>' );
	}

	public function display_vulnerability_information( int $number_of_columns ): void {
		if ( ! $this->has_vulnerabilities() ) {
			return;
		}

		printf( '<tr class="plugin-vuln-tr%s">', $this->is_active() ? ' active' : '' );
		printf( '<td colspan="%s" class="plugin-vuln colspanchange">', esc_attr( $number_of_columns ) );
		print( '<div class="vuln-message error inline notice-error notice-alt">' );

		/* translators: 1: The number of vulnerabilities associated with the plugin. */
		$message = sprintf( _n(
			'There is %s known vulnerability for this plugin on the currently installed version:',
			'There are %s known vulnerabilities for this plugin on the currently installed version:',
			count( $this->vulnerabilities )
		), number_format_i18n( count( $this->vulnerabilities ) ) );

		echo '<p>' . esc_html( $message ) . '</p>';

		echo '<ul>';
		foreach ( $this->vulnerabilities as $vuln ) {
			// "None" could be confusing, let's convert it to "Low".
			$severity = 'NONE' === $vuln->severity ? 'Low' : ucwords( strtolower( $vuln->severity ) );

			$severity_info = sprintf( '%s severity', esc_html( $severity ) );
			if ( null !== $vuln->severityScore ) {
				$severity_info = sprintf( '%s severity: %s/10', esc_html( $severity ), esc_html( $vuln->severityScore ) );
			}

			$vuln_text = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $vuln->link ), esc_html( $severity_info ) );
			echo '<li>' . wp_kses( $vuln_text, [ 'a' => [ 'href' => [], 'target' => [] ] ] ) . '</li>'; // phpcs:ignore
		}
		echo '</ul>';

		print( '</div></td></tr>' );
	}

	private function has_vulnerabilities(): bool {
		return ! empty( $this->vulnerabilities );
	}

	private function has_available_update(): bool {
		return isset( $this->new_version );
	}

	private function is_active(): bool {
		return is_network_admin() ? is_plugin_active_for_network( $this->file ) : is_plugin_active( $this->file );
	}

	private function get_details_url(): string {
		$details_url = $this->plugin_url;

		// Grab the more specific update url if possible.
		if ( ! empty( $this->update_url ) ) {
			$is_wporg_plugin = 0 === strpos( $this->update_url, 'https://wordpress.org/plugins/' );

			// Point WPorg plugins to the changelog tab.
			$details_url = $is_wporg_plugin ? $this->update_url . '#developers' : $this->update_url;
		}

		return $details_url;
	}
}
