<?php
/**
 * An individual company job listing
 *
 * @since 1.2
 */
class AngelList_Job {

	/**
	 * Build a job object based on AngelList job API response data
	 *
	 * @since 1.2
	 * @param stdClass single response from AngelList job listing API
	 */
	public function __construct( $job_data ) {
		if ( isset( $job_data->title ) )
			$this->title = trim( $job_data->title );
		if ( isset( $job_data->angellist_url ) ) {
			$url = esc_url( $job_data->angellist_url, array( 'http', 'https' ) );
			if ( $url )
				$this->url = $url;
			unset( $url );
		}
	}

	/**
	 * HTML markup for a single job mention
	 *
	 * @since 1.2
	 * @param bool $schema_org output Schema.org markup
	 * @param string $anchor_extra extra attributes such as browser context (target) to be applied to each anchor element
	 * @return string HTML markup for a single list item or empty string if minimum requirements (name, role) not met
	 */
	public function render( $schema_org = true, $anchor_extra = '' ) {
		if ( ! isset( $this->title ) )
			return '';

		$html = '<li class="angellist-job"';
		if ( $schema_org ) {
			$html .= ' itemscope itemtype="http://schema.org/JobPosting"><meta itemprop="name" content="' . esc_attr( $this->title ) . '" />';
			if ( isset( $this->url ) )
				$html .= '<meta itemprop="url" content="' . $this->url . '" />';
		}
		else
			$html .= '>';
		if ( isset( $this->url ) )
			$html .= '<a rel="nofollow" href="' . $this->url . '"' .  $anchor_extra . '>' . esc_html( $this->title ) . '</a>';
		else
			$html .= esc_html( $this->title );
		$html .= '</li>';

		return $html;
	}
}
?>