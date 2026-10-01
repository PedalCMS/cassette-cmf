<?php
/**
 * Cassette-CMF Unit Test Case
 *
 * Base test case class for Cassette-CMF tests that handles WordPress block registry notices.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

/**
 * Class CassetteCMF_UnitTestCase
 *
 * Base test case that handles WordPress block registry notices which may occur
 * during WordPress init in certain versions.
 */
abstract class CassetteCMF_UnitTestCase extends WP_UnitTestCase {

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
		// Ignore block registry notices keyed by function name before parent assertion.
		$ignored_notices = [
			'WP_Block_Type_Registry::register'     => true,
			'WP_Block_Bindings_Registry::register' => true,
		];

		$this->caught_doing_it_wrong = array_diff_key( $this->caught_doing_it_wrong, $ignored_notices );

		parent::assert_post_conditions();
	}
}
