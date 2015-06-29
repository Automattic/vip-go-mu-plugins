<?php
/**
 * An AngelList person displayed in a sompany profile
 *
 * @since 1.1
 */
class AngelList_Person {

	/**
	 * Build a Person object based on data from startup_roles
	 *
	 * @since 1.1
	 * @param stdClass $person_data data of a confirmed person associated with a company as returned by startup_roles
	 */
	public function __construct( $person_data ) {
		if ( isset( $person_data->role ) )
			$this->role = strtolower( trim( str_replace( '_', ' ', $person_data->role ) ) );

		if ( isset( $person_data->tagged ) ) {
			if ( isset( $person_data->tagged->name ) )
				$this->name = trim( $person_data->tagged->name );
			if ( isset( $person_data->tagged->angellist_url ) ) {
				$url = esc_url( $person_data->tagged->angellist_url, array( 'http', 'https' ) );
				if ( $url )
					$this->url = $url;
				unset( $url );
			}
			if ( isset( $person_data->tagged->image ) ) {
				if ( ! class_exists( 'AngelList_API' ) )
					require_once( dirname( dirname( __FILE__ ) ) . '/api.php' );
				$url = AngelList_API::filter_static_asset_url( $person_data->tagged->image );
				if ( $url && $url !== AngelList_API::DEFAULT_IMAGE ) {
					$image = new stdClass();
					$image->url = $url;
					$image->width = $image->height = 140;
					$this->image = $image;
					unset( $image );
				}
				unset( $url );
			}
		}
	}

	/**
	 * Choose n people up to limit sorted by their total number of AngelList followers
	 *
	 * @since 1.1
	 * @param array $people an individual startup role with a user subobject
	 * @param int $limit limits the number of objects returned
	 * @return array the passed in people array reordered by follower count
	 */
	public static function order_by_follower_count( array $people, $limit = 3 ) {
		if ( count( $people ) === 1 || $limit < 1 )
			return $people;
	
		$top_people = array();
		$people_count = 0;
		$people_by_followers = array();
		foreach ( $people as $person ) {
			if ( ! isset( $person->tagged ) )
				continue;
			else if ( isset( $person->tagged->follower_count ) && $person->tagged->follower_count > 0 )
				$people_by_followers[ (string) $person->tagged->follower_count ][] = $person;
			else
				$people_by_followers['0'][] = $person;
		}
		if ( ! empty( $people_by_followers ) && krsort( $people_by_followers ) ) {
			foreach ( $people_by_followers as $followers => $followers_person ) {
				if ( $people_count === $limit )
					break;
	
				// do two or more people share the same follower count?
				if ( is_array( $followers_person ) ) {
					// attempt to reorder by start date to break the tie
					$followers_person = AngelList_Person::order_by_start_date( $followers_person, $limit - $people_count );
					foreach ( $followers_person as $followers_person_person ) {
						if ( $people_count === $limit )
							break;
						$top_people[] = $followers_person_person;
						$people_count++;
					}
				} else {
					$top_people[] = $followers_person;
					$people_count++;
				}
			}
		}
		return $top_people;
	}

	/**
	 * Order a list of AngelList startup roles by company relationship start date
	 *
	 * @since 1.1
	 * @param array $people an individual startup role
	 * @param int $limit limits the number of objects returned
	 * @return array the passed in people array reordered by start date. people without a start date are placed at the end of the array
	 */
	public static function order_by_start_date( $people, $limit = 3 ) {
		if ( count( $people ) === 1 || $limit < 1 )
			return $people;
	
		$top_people = array();
		$people_count = 0;
		$people_by_date = array();
		$dateless = array();
		foreach( $people as $person ) {
			if ( isset( $person->started_at ) && is_string( $person->started_at ) ) {
				// remove separators from date string for pure numeric string comparison
				$people_by_date[ str_replace( '-', '', $person->started_at ) ] = $person;
			} else {
				$dateless[] = $person;
			}
		}
		if ( ! empty( $people_by_date ) && ksort( $people_by_date ) ) {
			foreach ( $people_by_date as $date => $date_person ) {
				if ( $people_count === $limit )
					break;
	
				// do two or more people share the same start date?
				if ( is_array( $date_person ) ) {
					foreach ( $date_person as $date_person_person ) {
						if ( $people_count === $limit )
							break;
						$top_people[] = $date_person_person;
						$people_count++;
					}
				} else {
					$top_people[] = $date_person;
					$people_count++;
				}
			}
		} else {
			foreach ( $dateless as $dateless_person ) {
				if ( $people_count === $limit )
					break;
				$top_people[] = $dateless_person;
				$people_count++;
			}
		}
		return $top_people;
	}

	/**
	 * HTML markup for a single person
	 *
	 * @since 1.1
	 * @param bool $schema_org output Schema.org markup
	 * @param string $anchor_extra extra attributes such as browser context (target) to be applied to each anchor element
	 * @return string HTML markup for a single list item or empty string if minimum requirements (name, role) not met
	 */
	public function render( $schema_org = true, $anchor_extra = '' ) {
		if ( ! ( isset( $this->name ) && isset( $this->role ) ) )
			return '';

		$html = '<li class="angellist-person angellist-company-' . str_replace( ' ', '', $this->role ) . '"';
		// Schema.org corporation supports founder and employee objects only
		if ( $schema_org && in_array( $this->role, array( 'founder', 'employee' ), true ) ) {
			$html .= ' itemprop="' . $this->role . '" itemscope itemtype="http://schema.org/Person">';
			if ( isset( $this->url ) )
				$html .= '<meta itemprop="url" content="' . $this->url . '" />';
			if ( isset( $this->image ) )
				$html .= '<meta itemprop="image" content="' . $this->image->url . '" />';
		} else {
			$schema_org = false;
			$html .= '>';
		}

		if ( isset( $this->image ) ) {
			$html .= '<div class="angellist-person-photo">';
			if ( isset( $this->url ) )
				$html .= '<a href="' . $this->url . '"' . $anchor_extra . '>';
			$image = '<img alt="' . esc_attr( $this->name ) . '" src="' . $this->image->url . '" width="38" height="38" />';
			$html .= '<noscript class="img" data-html="' . esc_attr( $image ) . '">' . $image . '</noscript>';
			unset( $image );
			if ( isset( $this->url ) )
				$html .= '</a>';
			$html .= '</div>';
		}

		$html .= '<div class="angellist-person-summary">';
		if ( isset( $this->url ) )
			$html .= '<a href="' . $this->url . '"' . $anchor_extra . '>';
		$html .= '<span class="angellist-person-name"';
		if ( $schema_org )
			$html .= ' itemprop="name"';
		$html .= '>' . esc_html( $this->name ) . '</span>';
		if ( isset( $this->url ) )
			$html .= '</a>';
		$html .= '<div class="angellist-person-title"';
		if ( $schema_org && $this->role !== 'employee' )
			$html .= ' itemprop="jobTitle"';
		$html .= '>' . esc_html( ucwords( $this->role ) ) . '</div>';

		$html .= '</div></li>';

		return $html;
	}
}
?>