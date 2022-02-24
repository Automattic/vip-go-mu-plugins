<?php
/**
 * UI Tests for the Network Admin Sites List.
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration\UI;

use Parsely\Parsely;
use Parsely\Tests\Integration\TestCase;
use Parsely\UI\Network_Admin_Sites_List;
use WP_Site;

/**
 * UI Tests for the Network Admin Sites List.
 */
final class NetworkAdminSitesListTest extends TestCase {
	/**
	 * Hold an insance of Network_Admin_Sites_List
	 *
	 * @var Network_Admin_Sites_List
	 */
	private static $sites_list;

	/**
	 * Hold an instance of WP_MS_Sites_List_Table
	 *
	 * @var WP_MS_Sites_List_Table
	 */
	public $table = false;

	/**
	 * Skip all tests for non-multisite runs.
	 * Set up an instance variable to hold a `WP_MS_Sites_List_Table` object.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! is_multisite() ) {
			self::markTestSkipped();
		}

		$this->table      = _get_list_table( 'WP_MS_Sites_List_Table', array( 'screen' => 'ms-sites' ) );
		self::$sites_list = new Network_Admin_Sites_List( new Parsely() );
	}

	/**
	 * Make sure the custom column is included.
	 *
	 * @covers \Parsely\UI\Network_Admin_Sites_List::add_api_key_column
	 * @covers \Parsely\UI\Network_Admin_Sites_List::run
	 * @uses \Parsely\UI\Network_Admin_Sites_List::__construct
	 * @return void
	 */
	public function test_api_key_column_is_present(): void {
		$columns = $this->table->get_columns();
		self::assertArrayNotHasKey( 'parsely-api-key', $columns );

		self::$sites_list->run();
		$columns = $this->table->get_columns();

		self::assertArrayHasKey( 'parsely-api-key', $columns );
		self::assertSame( 'Parse.ly API Key', $columns['parsely-api-key'] );
	}

	/**
	 * Make sure the custom column is populated with default data for no option and the API key when set.
	 *
	 * @covers \Parsely\UI\Network_Admin_Sites_List::populate_api_key_column
	 * @covers \Parsely\UI\Network_Admin_Sites_List::run
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_api_key
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\UI\Network_Admin_Sites_List::__construct
	 * @return void
	 */
	public function test_api_key_column_is_correctly_printed(): void {
		$blog_id_with_api_key    = $this->factory->blog->create();
		$blog_id_without_api_key = $this->factory->blog->create();

		self::$sites_list->run();

		update_blog_option( $blog_id_with_api_key, Parsely::OPTIONS_KEY, array( 'apikey' => 'parselyrocks.example.com' ) );

		$this->table->prepare_items();

		self::assertCount( 3, $this->table->items, 'There should be the main site, the subsite with the apikey set, and a subsite without.' );

		foreach ( $this->table->items as $site ) {
			self::assertInstanceOf( WP_Site::class, $site );

			ob_start();
			$this->table->column_default( $site->to_array(), 'parsely-api-key' );
			$api_key_col_value = ob_get_clean();

			if ( $blog_id_with_api_key === (int) $site->blog_id ) {
				self::assertSame(
					'parselyrocks.example.com',
					$api_key_col_value,
					'The API key was not printed and should have been.'
				);
			} else {
				self::assertSame(
					'<em>Parse.ly API key is missing</em>',
					$api_key_col_value,
					'The default value was not printed and should have been.'
				);
			}
		}
	}
}
