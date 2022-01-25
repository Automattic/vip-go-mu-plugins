<?php
/**
 * Recommended Widget tests.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\UI\Recommended_Widget;

/**
 * Recommended Widget tests.
 */
final class RecommendedApiTest extends TestCase {
	/**
	 * Data provider for test_recommended_api_url().
	 *
	 * @return array[]
	 */
	public function data_recommended_api_url(): array {
		return array(
			'Basic (Expected data)'                   => array(
				'my-key',
				7,
				'score',
				'views',
				5,
				'https://api.parsely.com/v2/related?apikey=my-key&sort=score&limit=5&boost=views&pub_date_start=7d',
			),
			'published_within value of 0'             => array(
				'my-key',
				0,
				'score',
				'views',
				5,
				'https://api.parsely.com/v2/related?apikey=my-key&sort=score&limit=5&boost=views',
			),
			'Sort on publish date (no boost param)'   => array(
				'my-key',
				0,
				'pub_date',
				'views',
				5,
				'https://api.parsely.com/v2/related?apikey=my-key&sort=pub_date&limit=5',
			),
			'Rank by relevance only (no boost param)' => array(
				'my-key',
				0,
				'score',
				'no-boost',
				5,
				'https://api.parsely.com/v2/related?apikey=my-key&sort=score&limit=5',
			),
		);
	}

	/**
	 * Test the basic generation of the API URL.
	 *
	 * @dataProvider data_recommended_api_url
	 * @covers \Parsely\UI\Recommended_Widget::get_api_url
	 *
	 * @param string $api_key          Publisher Site ID (API key).
	 * @param int    $published_within Publication filter start date; see https://www.parse.ly/help/api/time for
	 *                                 formatting details. No restriction by default.
	 * @param string $sort             What to sort the results by. There are currently 2 valid options: `score`, which
	 *                                 will sort articles by overall relevance and `pub_date` which will sort results by
	 *                                 their publication date. The default is `score`.
	 * @param string $boost            Available for sort=score only. Sub-sort value to re-rank relevant posts that
	 *                                 received high e.g. views; default is undefined.
	 * @param int    $return_limit     Number of records to retrieve; defaults to "10".
	 * @param string $url              Expected generated URL.
	 * @uses \Parsely\UI\Recommended_Widget::__construct
	 * @group widgets
	 */
	public function test_recommended_api_url( string $api_key, int $published_within, string $sort, string $boost, int $return_limit, string $url ): void {
		$recommended_widget = new Recommended_Widget();
		$get_api_url        = self::getMethod( 'get_api_url', Recommended_Widget::class );
		$response           = $get_api_url->invoke( $recommended_widget, $api_key, $published_within, $sort, $boost, $return_limit );

		self::assertEquals( $url, $response );
	}
}
