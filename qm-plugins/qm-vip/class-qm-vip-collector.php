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
		$version_file = WPMU_PLUGIN_DIR . '/.version';
		if ( ! file_exists( $version_file ) ) {
			$this->set_default_version_info();
			return;
		}
		$version = file_get_contents( $version_file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( ! $version ) {
			$this->set_default_version_info();
			return;
		}

		$info          = json_decode( $version );
		$branch        = $info->tag ?? '';
		$stack_version = $info->stack_version ?? [];
		$version_info  = explode( '-', $stack_version );
		$date          = isset( $version_info[0] ) ? gmdate( 'F j, Y', strtotime( $version_info[0] ) ) : null;
		$commit        = $version_info[1] ?? null;

		$this->data['mu-plugins'] = [
			'branch' => $branch,
			'commit' => $commit,
			'date'   => $date,
		];
	}

	/**
	 * Return some default information if no .version file is found or if the file is empty.
	 *
	 */
	private function set_default_version_info() {
		$this->data['mu-plugins'] = [
			'branch' => 'unbuilt',
		];
	}
}
