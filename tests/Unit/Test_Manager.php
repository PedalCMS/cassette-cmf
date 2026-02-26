<?php
/**
 * Manager Tests
 *
 * Tests for the Cassette-CMF Manager class.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Core\Manager;

/**
 * Class Test_Manager
 *
 * Tests for the Manager singleton and initialization.
 */
class Test_Manager extends WP_UnitTestCase {

	/**
	 * Reset Manager between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		// Reset the Manager singleton for each test.
		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Test Manager singleton pattern.
	 */
	public function test_manager_returns_singleton_instance(): void {
		$manager1 = Manager::init();
		$manager2 = Manager::init();

		$this->assertSame( $manager1, $manager2, 'Manager should return the same instance.' );
	}

	/**
	 * Test Manager is an instance of Manager class.
	 */
	public function test_manager_is_correct_instance(): void {
		$manager = Manager::init();

		$this->assertInstanceOf( Manager::class, $manager );
	}

	/**
	 * Test Manager has new settings page handler.
	 */
	public function test_manager_has_new_settings_page_handler(): void {
		$manager = Manager::init();
		$handler = $manager->get_new_settings_handler();

		$this->assertInstanceOf(
			\Pedalcms\CassetteCmf\Core\Handlers\New_Settings_Page_Handler::class,
			$handler
		);
	}

	/**
	 * Test Manager has existing settings page handler.
	 */
	public function test_manager_has_existing_settings_page_handler(): void {
		$manager = Manager::init();
		$handler = $manager->get_existing_settings_handler();

		$this->assertInstanceOf(
			\Pedalcms\CassetteCmf\Core\Handlers\Existing_Settings_Page_Handler::class,
			$handler
		);
	}

	/**
	 * Test Manager has new post type handler.
	 */
	public function test_manager_has_new_post_type_handler(): void {
		$manager = Manager::init();
		$handler = $manager->get_new_cpt_handler();

		$this->assertInstanceOf(
			\Pedalcms\CassetteCmf\Core\Handlers\New_Post_Type_Handler::class,
			$handler
		);
	}

	/**
	 * Test Manager has existing post type handler.
	 */
	public function test_manager_has_existing_post_type_handler(): void {
		$manager = Manager::init();
		$handler = $manager->get_existing_cpt_handler();

		$this->assertInstanceOf(
			\Pedalcms\CassetteCmf\Core\Handlers\Existing_Post_Type_Handler::class,
			$handler
		);
	}
}
