<?php
/**
 * Cassette-CMF Unit Test Case
 *
 * Base test case class for Cassette-CMF tests that handles WordPress block registry notices.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

/**
 * Class CassetteCmf_UnitTestCase
 *
 * Base test case that handles WordPress block registry notices which may occur
 * during WordPress init in certain versions.
 */
abstract class CassetteCmf_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
	}

	/**
	 * Assert post-conditions after each test.
	 *
	 * Override to ignore block registry notices that may occur in WordPress 6.5+.
	 */
	public function assert_post_conditions(): void {
		// Filter out block registry notices before parent assertion.
		$caught_doing_it_wrong = $this->caught_doing_it_wrong;

		$filtered = array_filter(
			$caught_doing_it_wrong,
			function ( $notice ) {
				// Ignore block registry notices that occur during WordPress init.
				$ignored_notices = [
					'WP_Block_Type_Registry::register',
					'WP_Block_Bindings_Registry::register',
				];
				return ! in_array( $notice, $ignored_notices, true );
			}
		);

		// Replace the caught notices with filtered ones.
		$this->caught_doing_it_wrong = $filtered;

		parent::assert_post_conditions();
	}
}
