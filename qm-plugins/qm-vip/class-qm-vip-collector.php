<?php

class QM_VIP_Collector extends QM_Collector {

	/**
	 * @var string
	 */
	public $id = 'qm-vip';

	/**
	 * @return string
	 */
	public function name() {
		return esc_html__( 'VIP', 'query-monitor' );
	}

	/**
	 * @return void
	 */
	public function process() {
		$this->process_version_file();
	}

	private function process_version_file() {
		$version_file = WP_CONTENT_DIR . '/mu-plugins/.version';
		if ( ! file_exists( $version_file ) ) {
			return null;
		}
		$version = file_get_contents( $version_file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( ! $version ) {
			return null;
		}

		$info          = json_decode( $version );
		$tag           = $info->tag ?? null;
		$stack_version = $info->stack_version ?? null;
		$version_info  = explode( '-', $stack_version );
		$date          = isset( $version_info[0] ) ? gmdate( 'F j, Y', strtotime( $version_info[0] ) ) : null;
		$commit        = $version_info[1] ?? null;

		$this->data['mu-plugins-stack']  = $tag;
		$this->data['version']['date']   = $date;
		$this->data['version']['commit'] = $commit;
	}
}
