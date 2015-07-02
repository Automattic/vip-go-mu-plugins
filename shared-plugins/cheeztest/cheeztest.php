<?php

/**
 * Plugin Name: CheezTest
 * Description: A class to help you create new Batcache-compatible server-side A/B tests
 * Author: ICHC, Automattic
 * Version: 1.0
 * 
 * CheezTest Class - create new Batcache-compatible server-side A/B tests
 *
 * Main class from which all A/B tests are inherited. Enables fast setup
 * of A/B tests - upon initialization, the basic order of execution is:
 * set test name > check if user is qualified to participate > 
 * get segment from either server or cookie > execute 'action' callback if present 
 * > write segment cookie if neccessary
 *
 * User's qualification, segment, and group tests are done in batcache
 * so as to ensure correct cache variants are served.
 *
 * User's segment is assigned via client-side javascript. Mutliple test
 * segments are assigned at once - so if a user is qualified to participate
 * in more than one test, all segments are assigned at the same time. When
 * segments need to be set, a small javascript is injected into the <head>
 * via a call to CheezTest::write_segment_cookie(). This javascript sets a
 * cookie to retain the assigned segment.
 *
 * Test case data (name, is_qualified, & group) are stored in the $active_tests
 * static hash and made accessible via the 'is_qualified_for', 'get_group_for', and
 * 'is_in_group' static methods. This enables theme branching via:
 *
 * if ( CheezTest::is_qualified_for( 'my-example-test' ) {
 *     //test-specific stuff goes here
 * }
 *
 * - or -
 *
 * if ( CheezTest::is_in_group( 'my-example-test', 'my-example-group' ) ) {
 *    //group-specific stuff goes here
 * }
 *
 * @access	public
 * @author Matt Mirande, I Can Haz Cheezburger
 * @author Mohammad Jangda, Automattic
 * 
 * @license GPL v2
 */
class CheezTest {

	public $name = '';
	public $group = null;
	public $is_qualified = true;
	private $expires = 0;
	/**
	* @access		public
	* @staticvar	array [ $active_tests ] key / val map with name, is_qualified,
	*				and group for all child objects. Enables easier accesss
	*				across the application via "is_qualified_for", etc helpers.
	*/
	public static $active_tests = array();

	private static $is_excluded = false;

	/**
	 * __construct - main constructor routine
	 *
	 * Configures and initiates an A/B test object.
	 *
	 * @access	public
	 * @param	array [ $opts ] key / value map containing configuration
	 *			options. Options include: name, expires, groups, action,
	 *			and is_qualified settings. The group property can optionally
	 *			contain a simple array of strings representing group names or each
	 *			group can be represented as an object with a 'threshold' property.
	 *			The 'threshold' will then be used to establish the group size -
	 *			e.g. 'threshold' => 10 indicates 10% of segments (segments 0 - 9)
	 *			will be assigned to this group.
	 *
	 */
	function __construct( $opts = array() ) {
		//exclude non-user requests
		if ( ! static::is_user_request() ){
			return;
		}

		$defaults = array(
			'name' =>  'ab-test',
			'expires' => 31536000, //how long the user's segment cookie will persist
			'groups' => array( 'seg-group-a', 'seg-group-b' ),
			'is_qualified' => '', //accepts string that will be evaluated as func by batcache.
			'action' => false //accepts anon-func e.g. function( $group ){} or false to bypass
		);

		//merge opts w/ defaults to establish child's configuration
		$cfg = array_merge( $defaults, $opts );

		$this->name = $cfg[ "name" ];

		//establish basic eligibility if 'is_qualified' is empty, all visitors are qualified to receive a segment
		if ( ! empty( $cfg[ 'is_qualified' ] ) && ! $this->qualify_user( $cfg[ "is_qualified" ] ) ) {
			return;
		}

		//establish user's group
		$this->group = $this->assign_group( $cfg[ "groups" ] );

		//add object's state to active tests collection to enable theme branching
		static::$active_tests[ $this->name ] = array(
			'is_qualified' => $this->is_qualified,
			'group' => $this->group
		);

		//fire 'action' callback if present
		if ( $this->group && is_callable( $cfg[ "action" ] ) ) {
			$cfg[ "action" ]( $this->group );
		}

		//if segment needs to be recorded in cookie, enqueue JS to do so
		if ( ! $this->has_segment_cookie() ) {
			$this->expires = $cfg[ 'expires' ] ? ( gmdate( 'r', time() + $cfg[ 'expires' ] ) ) : 0;		
			add_action( 'wp_print_footer_scripts' , array( $this, 'write_segment_cookie' ), 1 );
		}
	}

	/**
	 * qualify_user - batcahe-friendly test to determine if user is qualified for test
	 *
	 * Determines if user is eligible to participate in AB test by
	 * running an arbitrary function body provided via $test argument.
	 * Result is stored within object via $this->is_qualified.
	 *
	 * @access	private
	 * @param	string [ $test ] function body used to determine user's
	 *			eligibility.
	 * @return  bool true if the user is eligible to participate
	 */
	private function qualify_user( $test ) {
		$this->is_qualified = static::run_vary_cache_func( $test );
		return $this->is_qualified;
	}

	/**
	 * has_segment_cookie - batcahe-friendly test to determine if user has a segment cookie
	 *
	 * @access	private
	 * @return  bool whether or not segment cookie exists
	 */
	private function has_segment_cookie(){
		$test = sprintf( 'return (bool) isset( $_COOKIE["%s"] );', $this->name );
		return static::run_vary_cache_func( $test );
	}

	/**
	 * assign_group - assign a segment group (e.g. A, B, etc)
	 *
	 * Determines what group the assigned segment belongs in and
	 * creates a batcache-friendly test to ensure proper variant
	 * is shown.
	 *
	 * If the user has a segment cookie, the segment in the cookie
	 * is returned. If not, the server returns a random group based
	 * on the thresholds provided in the test config.
	 *
	 * @access	private
	 * @param	array [ $groups ] key / value map which contains the
	 *			group names to use. Unless 'threshold' property is supplied
	 *			the count of the array's items determines how many possible
	 *			groups there are as well as each group's size.
	 * @return  string group assigned (as derived from $groups argument array)
	 */
	private function assign_group( $groups ){

		$segment_checks = array();
		$cookie_checks = array();
		$block_size = 100 / count( $groups );
		$block_end = $block_size;
		$block_start = 0;
		
		//loop through groups and build up test logic to determine group assignment
		foreach ( $groups as $group => $group_args ){

			//use 'threshold' to determine group sizing if available
			if ( isset( $group_args[ 'threshold' ] ) && is_array( $group_args ) ){
				$block_end = $block_start + ( int ) $group_args[ 'threshold' ];
			} else {
				$group = $groups[ $group ];
			}

			if ( $block_end > 100 ){
				$block_end = 100;
			}

			//setup batcache vary on cache conditions
			$segment_checks[] = sprintf(
				'( $seg_num >= %1$d && $seg_num < %2$d ) return "%3$s";',
				$block_start,
				$block_end,
				$group
			);

			$cookie_checks[] = sprintf(
				'( $_COOKIE["%1$s"] === "%2$s" ) return "%2$s";',
				$this->name,
				$group
			);

			//update block
			$block_start = $block_end;
			$block_end = $block_start + $block_size;
		}

		// Take array of checks and turn into string of if/then return statements
		$segment_checks_str = 'if' . implode( 'elseif', $segment_checks );
		$cookie_checks_str = 'if' . implode( 'elseif', $cookie_checks );

		$test = sprintf( 'if( isset( $_COOKIE["%1$s"] ) ){ %2$s } else { $seg_num = rand( 1,100 ); %3$s }', $this->name, $cookie_checks_str, $segment_checks_str );
		return static::run_vary_cache_func( $test );
	}

	/**
	 * run_vary_cache_func - environment-neutral interface to batcache's "vary_cache_on_function"
	 *
	 * Establishes whether or not to use a new cache variant by
	 * running an arbitrary function body provided via $test argument.
	 * $test is run both locally and in the batcache.
	 *
	 * @access	private
	 * @param	string [ $test ] function body used to determine user's
	 *			eligibility. Must be a string in order to work with
	 *			WP's batcache 'vary_cache_on_function' feature. Must
	 *			include one or more references to "$_" variables.
	 * @return  mixed (bool | string | int)
	 */
	private static function run_vary_cache_func( $test ){

		if ( preg_match('/include|require|echo|print|dump|export|open|sock|unlink|`|eval/i', $test) )
			trigger_error('Illegal word in cache variant function determiner.', E_USER_ERROR );
	
		if ( !preg_match('/\$_/', $test) )
			trigger_error('Cache variant function should refer to at least one $_ variable.', E_USER_ERROR );

		if ( function_exists( 'vary_cache_on_function' ) ) {
			vary_cache_on_function( $test );
		}

		$test_func = create_function( '', $test );
		return $test_func();
	}

	/**
	 * is_user_request - Determines if the request type is one typically made
	 * by a user
	 *
	 * Test whether or not the request being processed matches what would typically 
	 * be made by a user. Cron jobs, requests for the admin section or feeeds, and 
	 * any request made by google, ms/bing, or yahoo's slurp bot are flagged.
	 *
	 * @access	public
	 * @return	bool true if request appears to be user-generated
	 */
	private static function is_user_request(){
		//bail if we already know this request is not valid
		if ( static::$is_excluded ){
			return false;
		}

		$is_bot_test = 'return stripos( $_SERVER[ "HTTP_USER_AGENT" ], "googlebot" ) !== false || ' .
			'stripos( $_SERVER[ "HTTP_USER_AGENT" ], "bingbot" ) !== false || ' .
			'stripos( $_SERVER[ "HTTP_USER_AGENT" ], "msnbot" ) !== false || ' .
			'stripos( $_SERVER[ "HTTP_USER_AGENT" ], "slurp" ) !== false || ' .
			'stripos( $_SERVER[ "HTTP_USER_AGENT" ], "feedburner" ) !== false || ' .
			'stripos( $_SERVER[ "HTTP_USER_AGENT" ], "facebook" ) !== false || ' .
			'stripos( $_SERVER[ "HTTP_USER_AGENT" ], "technoratisnoop" ) !== false;';

		$is_bot = static::run_vary_cache_func( $is_bot_test );

		if ( ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			|| ( defined( 'WP_ADMIN' ) && WP_ADMIN )
			|| is_admin()
			|| $is_bot ) {
			static::$is_excluded = true;
			return false;
		}
		return true;
	}

	/**
	 * write_segment_cookie - serves javascript response to client to write the user's
	 * segment into a cookie so it will persist across visits
	 *
	 * @return
	 */
	function write_segment_cookie(){
		?>
			<script>
				( function(){

					// Check if cookies are enabled
					// @see http://sveinbjorn.org/cookiecheck
					var cookieEnabled = (navigator.cookieEnabled) ? true : false;

					if (typeof navigator.cookieEnabled == "undefined" && !cookieEnabled){ 
						document.cookie="testcookie";
						cookieEnabled = (document.cookie.indexOf("testcookie") != -1) ? true : false;
					}
					
					if( ! cookieEnabled )
						return;
					
					// Set cookie with test condition
					document.cookie = <?php echo json_encode( $this->name ); ?> + '=' + <?php echo json_encode( $this->group ); ?> + '; expires=' + <?php echo json_encode( $this->expires ); ?> + '; path=/';
					
				})();
			</script>
		<?php
	}

	/**
	 * is_qualified_for - Helper func to get user's eligibility for any active test
	 *
	 * Returns bool true if user is qualified to see test - usage:
	 *     if ( CheezTest::is_qualified_for( 'my-example-test' ) {
	 *         //test-specific stuff goes here
	 *     }
	 *
	 * @access	public
	 * @param	string [ $name ] test name assigned during child initialization
	 * @return  bool true if the user is eligible to participate (is qualified and has group)
	 */
	static function is_qualified_for( $name ){
		if ( array_key_exists( $name, static::$active_tests ) ) {
			return static::$active_tests[ $name ][ 'is_qualified' ] && static::$active_tests[ $name ][ 'group' ];
		}
		return false;
	}

	/**
	 * get_group_for - Helper func to get user's group assignment for any active test
	 *
	 * Returns 'group' property of static $active_tests collection
	 *
	 * @access	public
	 * @param	string [ $name ] test name assigned during child initialization
	 * @return  mixed (string | bool) group name if user is eligible, false if not
	 */
	static function get_group_for( $name ){
		if ( isset( static::$active_tests[ $name ] ) ) {
			return static::$active_tests[ $name ][ 'group' ];
		}
		return false;
	}

	/**
	 * is_in_group - Helper func to check if user is in the specified group
	 *
	 * @access	public
	 * @param	string [ $name ] test name assigned during child initialization
	 * @param	string [ $group ] group name to check against
	 * @return	bool true if user is in in test group, false if not
	 */
	static function is_in_group( $name, $group ) {
		return static::get_group_for( $name ) == $group;
	}
}