<?php
/**
 * Request remote data from AngelList
 * Generate HTML from the result
 *
 * @since 1.0
 */
class AngelList_Company {
	/**
	 * Identify the unique company, kick off data request
	 *
	 * @since 1.0
	 * @param int $company_id AngelList company identifier
	 */
	public function __construct( $company_id ) {
		$company_id = absint( $company_id );
		if ( $company_id < 1 )
			return;
		$this->id = absint( $company_id );

		// allow a publisher to disable Schema.org markup by attaching to this filter
		$this->schema_org = (bool) apply_filters( 'angellist_schema_org', true, $this->id );

		// allow override of default browsing context
		$browsing_context = apply_filters( 'angellist_browsing_context', '_blank', $this->id );
		// limit browsing context to special keywords
		if ( ! in_array( $browsing_context, array( '', '_blank', '_self', '_parent', '_top' ), true ) )
			$browsing_context = '_blank';
		if ( $browsing_context )
			$this->anchor_extra = ' target="' . $browsing_context . '"';
		else
			$this->anchor_extra = '';
		unset( $browsing_context );

		// check for cached version of company info before we request data from AngelList
		$this->cache_key = $this->generate_cache_key();
		$html = get_transient( $this->cache_key );
		if ( empty( $html ) )
			$this->populate_data();
		else
			$this->html = $html;
	}

	/**
	 * Request company data from AngelList.
	 * Populate data in the class
	 *
	 * @since 1.0
	 */
	private function populate_data() {
		if ( ! class_exists( 'AngelList_API' ) )
			require_once( dirname( dirname( __FILE__ ) ) . '/api.php' );
		$company = AngelList_API::get_company( $this->id );
		if ( empty( $company ) )
			return;

		// are we sharing a secret?
		if ( isset( $company->hidden ) && $company->hidden === true )
			return;

		// we should at least be able to reference a startup by its name
		if ( isset( $company->name ) )
			$this->name = trim( $company->name );
		else
			return;

		// is the startup participating in AngelList and has claimed their profile?
		if ( isset( $company->community_profile ) && $company->community_profile === false )
			$this->claimed = true;
		else
			$this->claimed = false;

		if ( isset( $company->company_url ) ) {
			$url = esc_url( $company->company_url, array( 'http', 'https' ) );
			if ( $url )
				$this->url = $url;
			unset( $url );
		}

		if ( isset( $company->angellist_url ) ) {
			$url = esc_url( $company->angellist_url, array( 'http', 'https' ) );
			if ( $url )
				$this->profile_url = $url;
			unset( $url );
		}

		if ( isset( $company->thumb_url ) && $company->thumb_url !== AngelList_API::DEFAULT_IMAGE ) {
			$url = AngelList_API::filter_static_asset_url( $company->thumb_url );
			if ( $url ) {
				$image = new stdClass();
				$image->url = $url;
				$image->width = $image->height = 100;
				$this->thumbnail = $image;
				unset( $image );
			}
			unset( $url );
		}

		if ( isset( $company->logo_url ) && $company->thumb_url !== AngelList_API::DEFAULT_IMAGE ) {
			$url = AngelList_API::filter_static_asset_url( $company->logo_url );
			if ( $url )
				$this->logo_url = $url;
			unset( $url );
		}

		if ( isset( $company->high_concept ) ) {
			$concept = trim( $company->high_concept );
			if ( $concept )
				$this->concept = $concept;
			unset( $concept );
		}

		if ( isset( $company->product_desc ) ) {
			$description = trim( $company->product_desc );
			if ( $description )
				$this->description = $description;
			unset( $description );
		}

		// first location with all the data we want is considered the HQ and displayed
		if ( isset( $company->locations ) && is_array( $company->locations ) ) {
			// iterate until we find a URL + name
			foreach ( $company->locations as $location ) {
				if ( ! ( isset( $location->angellist_url ) && isset( $location->display_name ) ) )
					continue;
				$url = esc_url( $location->angellist_url, array( 'http', 'https' ) );
				if ( ! $url )
					continue;
				$hq_location = new stdClass();
				$hq_location->url = $url;
				unset( $url );
				$hq_location->name = trim( $location->display_name );
				$this->location = $hq_location;
				unset( $hq_location );
				break;
			}
		}

		// first market with the data we want is considered the main market
		if ( isset( $company->markets ) && is_array( $company->markets ) ) {
			// iterate until we find a URL + name
			foreach ( $company->markets as $tag ) {
				if ( ! ( isset( $tag->angellist_url ) && isset( $tag->display_name ) ) )
					continue;
				$url = esc_url( $tag->angellist_url, array( 'https', 'http' ) );
				if ( ! $url )
					continue;
				$main_tag = new stdClass();
				$main_tag->url = $url;
				unset( $url );
				$main_tag->name = trim( $tag->display_name );
				$this->tag = $main_tag;
				unset( $main_tag );
				break;
			}
		}
	}

	/**
	 * Request a list of people associated with the company from the AngelList API, up to limit (default 3)
	 *
	 * @since 1.1
	 * @param int $limit maximum amount of people
	 * @return string HTML list of people
	 */
	private function people( $limit = 3 ) {
		// check for garbage
		if ( ! is_int( $limit ) || $limit < 1 )
			$limit = 3;

		if ( ! class_exists( 'AngelList_API' ) )
			require_once( dirname( dirname( __FILE__ ) ) . '/api.php' );

		$people = AngelList_API::get_roles_by_company( $this->id );
		if ( ! is_array( $people ) || empty( $people ) )
			return '';

		if ( ! class_exists( 'AngelList_Person' ) )
			require_once( dirname( __FILE__ ) . '/person.php' );
		$founders = array();
		$everyone_else = array();
		foreach ( $people as $person ) {
			// only care about confirmed, active people
			if ( ! ( isset( $person->role ) && isset( $person->confirmed ) && $person->confirmed === true && ! isset( $person->ended_at ) ) )
				continue;
			if ( $person->role === 'founder' )
				$founders[] = $person;
			else if ( ! in_array( $person->role, array( 'referrer', 'attorney' ), true ) )
				$everyone_else[] = $person;
		}
		unset( $people );

		$top_people = array();
		// founders get priority treatment
		if ( ! empty( $founders ) )
			$top_people = AngelList_Person::order_by_follower_count( $founders, $limit );
		$people_count = count( $top_people );
		if ( $people_count < $limit && ! empty( $everyone_else ) )
			$top_people = array_merge( $top_people, AngelList_Person::order_by_follower_count( $everyone_else, $limit - $people_count ) );

		// this should not happen. just in case
		if ( empty( $top_people ) )
			return '';

		$people_html = '';
		foreach ( $top_people as $person_data ) {
			$person = new AngelList_Person( $person_data );
			if ( ! ( isset( $person->name ) && isset( $person->role ) ) )
				continue;
			$people_html .= $person->render( $this->schema_org, $this->anchor_extra );
		}
		if ( $people_html )
			return '<ol class="angellist-people">' . $people_html . '</ol>';
		else
			return '';
		
	}

	/**
	 * Display jobs at the company listed in AngelList
	 *
	 * @since 1.2
	 * @param int $limit show at most N jobs
	 * @return string HTML list of job listings
	 */
	private function jobs( $limit = 3 ) {
		// check for garbage
		if ( ! is_int( $limit ) || $limit < 1 )
			$limit = 3;

		if ( ! class_exists( 'AngelList_API' ) )
			require_once( dirname( dirname( __FILE__ ) ) . '/api.php' );
		$jobs = AngelList_API::get_jobs_by_company( $this->id );
		if ( ! is_array( $jobs ) || empty( $jobs ) )
			return '';
		if ( count( $jobs ) > $limit )
			$jobs = array_slice( $jobs, 0, $limit );

		if ( ! class_exists( 'AngelList_Job' ) )
			require_once( dirname( __FILE__ ) . '/job.php' );

		$jobs_html = '';
		foreach ( $jobs as $job_data ) {
			$job = new AngelList_Job( $job_data );
			if ( isset( $job->title ) )
				$jobs_html .= $job->render($this->schema_org, $this->anchor_extra);
		}
		if ( $jobs_html )
			return '<div class="angellist-jobs"><span>' . esc_html( sprintf( __( '%s is hiring:', 'angellist' ), $this->name ) ) . '</span><ol>' . $jobs_html . '</ol>';
		else
			return '';
	}

	/**
	 * Generate a cache key based on site preferences and SSL requirements
	 *
	 * @since 1.0
	 * @return string WordPress cache or transient key
	 */
	private function generate_cache_key() {
		$cache_parts = array( 'angellist-company', 'v1.2.1', $this->id );
		if ( is_ssl() )
			$cache_parts[] = 'ssl';
		if ( isset( $this->schema_org ) && ! $this->schema_org )
			$cache_parts[] = 'ns';
		if ( isset( $this->browsing_context ) && $this->browsing_context )
			$cache_parts[] = substr( $this->browsing_context, 1 ); // remove leading '_'
		return implode( '-', $cache_parts );
	}

	/**
	 * Build HTML for a company
	 *
	 * @since 1.0
	 * @return string HTML markup
	 */
	public function render() {
		if ( ! ( isset( $this->name ) && isset( $this->profile_url ) ) )
			return '';

		$profile_url_title_attr = esc_attr( sprintf( __( '%s on AngelList', 'angellist' ), $this->name ) );

		$html = '<li class="angellist-company ';
		if ( $this->claimed )
			$html .= 'angellist-claimed-profile';
		else
			$html .= 'angellist-community-profile';
		$html .= '" data-startup_id="' . $this->id . '"';
		if ( $this->schema_org ) {
			$html .= ' itemscope itemtype="http://schema.org/Corporation">';
			if ( isset( $this->url ) )
				$html .= '<meta itemprop="url" content="' . $this->url . '" />';
			else
				$html .= '<meta itemprop="url" content="' . $this->profile_url . '" />';

			if ( isset( $this->description ) )
				$html .= '<meta itemprop="description" content="' . esc_attr( str_replace( "\n\n", ' ', $this->description ) ) . '" />';
			else if ( isset( $this->concept ) )
				$html .= '<meta itemprop="description" content="' . esc_attr( $this->concept ) . '" />';

			if ( isset( $this->logo_url ) )
				$html .= '<meta itemprop="image" content="' . $this->logo_url . '" />';
			else if ( isset( $this->thumbnail ) )
				$html .= '<meta itemprop="image" content="' . $this->thumbnail->url . '" />';

			if ( isset( $this->location ) ) {
				$html .= '<meta itemprop="location" content="' . $this->location->url . '" />';
			}
		} else {
			$html .= '>';
		}

		$html .= '<div class="angellist-company-summary">';
		if ( isset( $this->thumbnail ) ) {
			$html .= '<a class="angellist-company-image" href="' . $this->profile_url . '" title="' . $profile_url_title_attr . '"' . $this->anchor_extra . '>';
			$image = '<img alt="' . esc_attr( $this->name ) . '" src="' . $this->thumbnail->url . '" width="90" height="90" />';
			$html .= '<noscript class="img" data-html="' . esc_attr( $image ) . '">' . $image . '</noscript>';
			unset( $image );
			$html .= '</a>';
		}
		$html .= '<div class="angellist-company-summary-text">';
		$html .= '<a class="angellist-company-name" href="' . $this->profile_url . '" title="' . $profile_url_title_attr . '"' . $this->anchor_extra;
		if ( $this->schema_org )
			$html .= ' itemprop="name"';
		$html .= '>' . esc_html( $this->name ) . '</a>';
		if ( isset( $this->concept ) )
			$html .= '<div class="angellist-company-concept">' . esc_html( $this->concept ) . '</div>';
		if ( isset( $this->tag ) || isset( $this->location ) ) {
			$html .= '<div class="angellist-company-metadata">';
			if ( isset( $this->tag ) ) {
				$html .= '<a class="angellist-company-tag" href="' . $this->tag->url . '"' . $this->anchor_extra . '>' . esc_html( $this->tag->name ) . '</a>';
				if ( isset( $this->location ) )
					$html .= ' &#183; ';
			}
			if ( isset( $this->location ) )
				$html .= '<a class="angellist-company-location" href="' . $this->location->url . '"' . $this->anchor_extra . '>' . esc_html( $this->location->name ) . '</a>';
			$html .= '</div>';
		}
		$html .= '</div>'; // summary-text
		$html .= '<span class="angellist-follow-button"><a href="' . $this->profile_url . '" title="' . $profile_url_title_attr . '"' . $this->anchor_extra . '>' . esc_html( __( 'Follow on AngelList', 'angellist' ) ) . '</a></span>';
		$html .= '</div>'; // summary

		if ( isset( $this->description ) ) {
			// wrap the p in a detail div for easy expansion to users, read more on the <p>, etc
			$paragraphs = explode( "\n\n", $this->description );
			array_walk( $paragraphs, 'esc_html' );
			$html .= '<div class="angellist-company-detail"><p>' . implode( '</p><p>', $paragraphs ) . '</p></div>';
			unset( $paragraphs );
		}

		// these next features only work for claimed companies
		if ( $this->claimed ) {
			$html .= $this->people();
			$html .= $this->jobs();
		}

		$html .= '</li>';

		// cache markup to save us some time on the next request
		set_transient( $this->cache_key, $html, 10800 );

		return $html;
	}
}
?>