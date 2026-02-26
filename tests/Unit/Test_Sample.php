<?php
/**
 * Sample Test
 *
 * A simple test to verify the WordPress test environment is working.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

/**
 * Class Test_Sample
 *
 * Basic test to verify the testing environment.
 */
class Test_Sample extends WP_UnitTestCase {

	/**
	 * Test that true is true.
	 *
	 * This is a simple sanity check to verify PHPUnit is working.
	 */
	public function test_true_is_true(): void {
		$this->assertTrue( true );
	}

	/**
	 * Test WordPress is loaded.
	 *
	 * Verifies that the WordPress environment is properly bootstrapped.
	 */
	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( function_exists( 'add_filter' ) );
		$this->assertTrue( function_exists( 'get_option' ) );
		$this->assertTrue( function_exists( 'update_option' ) );
	}

	/**
	 * Test WordPress version is set.
	 */
	public function test_wordpress_version_is_set(): void {
		global $wp_version;

		$this->assertNotEmpty( $wp_version );
	}

	/**
	 * Test WP_UnitTestCase factory is available.
	 */
	public function test_factory_is_available(): void {
		$this->assertInstanceOf( 'WP_UnitTest_Factory', self::factory() );
	}

	/**
	 * Test can create a post.
	 */
	public function test_can_create_post(): void {
		$post_id = self::factory()->post->create(
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			]
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( 'Test Post', get_the_title( $post_id ) );
	}

	/**
	 * Test can create a user.
	 */
	public function test_can_create_user(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'testuser',
				'role'       => 'editor',
			]
		);

		$this->assertIsInt( $user_id );
		$this->assertGreaterThan( 0, $user_id );

		$user = get_user_by( 'id', $user_id );
		$this->assertSame( 'testuser', $user->user_login );
	}

	/**
	 * Test options work.
	 */
	public function test_options_work(): void {
		update_option( 'test_option', 'test_value' );

		$this->assertSame( 'test_value', get_option( 'test_option' ) );

		delete_option( 'test_option' );

		$this->assertFalse( get_option( 'test_option' ) );
	}
}
