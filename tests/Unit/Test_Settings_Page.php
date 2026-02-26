<?php
/**
 * Settings Page Registration Tests
 *
 * Tests for registering settings pages with Cassette-CMF.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Core\Manager;
use Pedalcms\CassetteCmf\Settings\Settings_Page;

/**
 * Class Test_Settings_Page
 *
 * Tests for settings page registration.
 */
class Test_Settings_Page extends WP_UnitTestCase {

	/**
	 * Reset Manager between tests.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset the Manager singleton.
		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		// Set current user as admin for capability checks.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Test creating a Settings_Page instance.
	 */
	public function test_settings_page_creation(): void {
		$page = new Settings_Page( 'test_settings' );

		$this->assertInstanceOf( Settings_Page::class, $page );
		$this->assertSame( 'test_settings', $page->get_config( 'menu_slug', 'test_settings' ) );
	}

	/**
	 * Test Settings_Page with custom title.
	 */
	public function test_settings_page_with_title(): void {
		$page = new Settings_Page( 'test_settings' );
		$page->set_page_title( 'Test Settings' );
		$page->set_menu_title( 'Test' );

		$this->assertSame( 'Test Settings', $page->get_config( 'page_title' ) );
		$this->assertSame( 'Test', $page->get_config( 'menu_title' ) );
	}

	/**
	 * Test registering settings page via Manager.
	 */
	public function test_register_settings_page_via_manager(): void {
		$manager = Manager::init();

		$page = new Settings_Page( 'test_settings' );
		$page->set_page_title( 'Test Settings' );
		$page->set_menu_title( 'Test' );
		$page->set_capability( 'manage_options' );

		$manager->get_new_settings_handler()->add_page_instance( $page );

		// Trigger admin_menu action.
		do_action( 'admin_menu' );

		// Verify the page was added.
		$handler = $manager->get_new_settings_handler();
		$this->assertTrue( $handler->has_page( 'test_settings' ) );
	}

	/**
	 * Test registering settings page from array config.
	 */
	public function test_register_settings_page_from_array(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => 'test_settings',
						'page_title' => 'Test Settings',
						'menu_title' => 'Test',
						'capability' => 'manage_options',
					],
				],
			]
		);

		$handler = $manager->get_new_settings_handler();
		$this->assertTrue( $handler->has_page( 'test_settings' ) );
	}

	/**
	 * Test settings page with fields.
	 */
	public function test_settings_page_with_fields(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => 'test_settings',
						'page_title' => 'Test Settings',
						'menu_title' => 'Test',
						'capability' => 'manage_options',
						'fields'     => [
							[
								'name'  => 'test_field',
								'type'  => 'text',
								'label' => 'Test Field',
							],
							[
								'name'  => 'test_email',
								'type'  => 'email',
								'label' => 'Test Email',
							],
						],
					],
				],
			]
		);

		$handler = $manager->get_new_settings_handler();
		$fields  = $handler->get_fields( 'test_settings' );

		$this->assertCount( 2, $fields );
	}

	/**
	 * Test adding fields to existing WordPress settings page.
	 */
	public function test_add_fields_to_existing_settings_page(): void {
		$manager = Manager::init();

		$handler = $manager->get_existing_settings_handler();

		// Add a field to the General settings page.
		$handler->add_fields(
			'options-general.php',
			[
				[
					'name'  => 'custom_site_tagline',
					'type'  => 'text',
					'label' => 'Custom Tagline',
				],
			]
		);

		$fields = $handler->get_fields( 'options-general.php' );

		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'custom_site_tagline', $fields );
	}

	/**
	 * Test is_wordpress_page for General settings.
	 */
	public function test_is_wordpress_page_general(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertTrue( $handler->is_wordpress_page( 'general' ) );
	}

	/**
	 * Test is_wordpress_page for Writing settings.
	 */
	public function test_is_wordpress_page_writing(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertTrue( $handler->is_wordpress_page( 'writing' ) );
	}

	/**
	 * Test is_wordpress_page for Reading settings.
	 */
	public function test_is_wordpress_page_reading(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertTrue( $handler->is_wordpress_page( 'reading' ) );
	}

	/**
	 * Test is_wordpress_page for Discussion settings.
	 */
	public function test_is_wordpress_page_discussion(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertTrue( $handler->is_wordpress_page( 'discussion' ) );
	}

	/**
	 * Test is_wordpress_page for Media settings.
	 */
	public function test_is_wordpress_page_media(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertTrue( $handler->is_wordpress_page( 'media' ) );
	}

	/**
	 * Test is_wordpress_page for Permalinks settings.
	 */
	public function test_is_wordpress_page_permalinks(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertTrue( $handler->is_wordpress_page( 'permalink' ) );
	}

	/**
	 * Test is_wordpress_page returns false for custom pages.
	 */
	public function test_is_wordpress_page_returns_false_for_custom(): void {
		$handler = Manager::init()->get_existing_settings_handler();

		$this->assertFalse( $handler->is_wordpress_page( 'my-custom-page' ) );
	}

	/**
	 * Test Settings_Page from_array factory method.
	 */
	public function test_settings_page_from_array(): void {
		$page = Settings_Page::from_array(
			'my_settings',
			[
				'page_title' => 'My Settings',
				'menu_title' => 'My Settings',
				'capability' => 'manage_options',
			]
		);

		$this->assertInstanceOf( Settings_Page::class, $page );
		$this->assertSame( 'My Settings', $page->get_config( 'page_title' ) );
	}

	/**
	 * Test Settings_Page set_parent for submenu.
	 */
	public function test_settings_page_submenu(): void {
		$page = new Settings_Page( 'sub_settings' );
		$page->set_page_title( 'Submenu Settings' );
		$page->set_menu_title( 'Submenu' );
		$page->set_parent( 'options-general.php' );

		// set_parent stores in parent_slug config key
		$this->assertSame( 'options-general.php', $page->get_config( 'parent_slug' ) );
	}

	/**
	 * Test Settings_Page is not submenu by default.
	 */
	public function test_settings_page_not_submenu_by_default(): void {
		$page = new Settings_Page( 'top_level' );

		// No parent_slug means not a submenu
		$this->assertNull( $page->get_config( 'parent_slug' ) );
	}

	/**
	 * Test Settings_Page fluent interface.
	 */
	public function test_settings_page_fluent_interface(): void {
		$page = ( new Settings_Page( 'fluent_test' ) )
			->set_page_title( 'Fluent Test' )
			->set_menu_title( 'Fluent' )
			->set_capability( 'manage_options' );

		$this->assertInstanceOf( Settings_Page::class, $page );
		$this->assertSame( 'Fluent Test', $page->get_config( 'page_title' ) );
		$this->assertSame( 'Fluent', $page->get_config( 'menu_title' ) );
		$this->assertSame( 'manage_options', $page->get_config( 'capability' ) );
	}

	/**
	 * Test Settings_Page get_page_id.
	 */
	public function test_settings_page_get_page_id(): void {
		$page = new Settings_Page( 'unique_id' );

		$this->assertSame( 'unique_id', $page->get_page_id() );
	}

	/**
	 * Test Settings_Page configure method.
	 */
	public function test_settings_page_configure(): void {
		$page = new Settings_Page( 'configured' );
		$page->configure(
			[
				'page_title' => 'Configured Page',
				'menu_title' => 'Configured',
				'capability' => 'edit_posts',
			]
		);

		$this->assertSame( 'Configured Page', $page->get_config( 'page_title' ) );
		$this->assertSame( 'edit_posts', $page->get_config( 'capability' ) );
	}

	/**
	 * Test Settings_Page get_all_config.
	 */
	public function test_settings_page_get_all_config(): void {
		$page = new Settings_Page( 'test_all' );
		$page->set_page_title( 'Test All' );

		$config = $page->get_all_config();

		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'page_title', $config );
		$this->assertSame( 'Test All', $config['page_title'] );
	}

	/**
	 * Test multiple settings pages registration.
	 */
	public function test_multiple_settings_pages(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => 'page_one',
						'page_title' => 'Page One',
					],
					[
						'id'         => 'page_two',
						'page_title' => 'Page Two',
					],
					[
						'id'         => 'page_three',
						'page_title' => 'Page Three',
					],
				],
			]
		);

		$handler = $manager->get_new_settings_handler();

		$this->assertTrue( $handler->has_page( 'page_one' ) );
		$this->assertTrue( $handler->has_page( 'page_two' ) );
		$this->assertTrue( $handler->has_page( 'page_three' ) );
	}
}
