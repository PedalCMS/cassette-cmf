<?php
/**
 * Handler Tests
 *
 * Tests for the Handler classes (New_Post_Type_Handler, Existing_Post_Type_Handler,
 * New_Settings_Page_Handler, Existing_Settings_Page_Handler).
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Core\Handlers\New_Post_Type_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\Existing_Post_Type_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\Existing_Settings_Page_Handler;
use PedalCMS\CassetteCMF\CPT\Custom_Post_Type;
use PedalCMS\CassetteCMF\Settings\Settings_Page;
use PedalCMS\CassetteCMF\Field\Field_Factory;

/**
 * Class Test_Handlers
 *
 * Tests for all handler classes.
 */
class Test_Handlers extends WP_UnitTestCase {

	/**
	 * Reset between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Manager::reset();
		Field_Factory::reset();
	}

	// =========================================================================
	// New_Post_Type_Handler Tests
	// =========================================================================

	/**
	 * Test New_Post_Type_Handler can be instantiated.
	 */
	public function test_new_post_type_handler_instantiation(): void {
		$handler = new New_Post_Type_Handler();
		$this->assertInstanceOf( New_Post_Type_Handler::class, $handler );
	}

	/**
	 * Test add_post_type returns fluent interface.
	 */
	public function test_add_post_type_fluent(): void {
		$handler = new New_Post_Type_Handler();
		$result  = $handler->add_post_type( 'test_cpt', [ 'label' => 'Test' ] );

		$this->assertInstanceOf( New_Post_Type_Handler::class, $result );
		$this->assertSame( $handler, $result );
	}

	/**
	 * Test add_post_type_instance accepts Custom_Post_Type.
	 */
	public function test_add_post_type_instance(): void {
		$handler = new New_Post_Type_Handler();
		$cpt     = Custom_Post_Type::from_array( 'book', [ 'label' => 'Books' ] );

		$result = $handler->add_post_type_instance( $cpt );

		$this->assertInstanceOf( New_Post_Type_Handler::class, $result );
	}

	/**
	 * Test get_post_type returns correct object.
	 */
	public function test_get_post_type(): void {
		$handler = new New_Post_Type_Handler();
		$handler->add_post_type( 'movie', [ 'label' => 'Movies' ] );

		$cpt = $handler->get_post_type( 'movie' );

		$this->assertInstanceOf( Custom_Post_Type::class, $cpt );
	}

	/**
	 * Test get_post_type returns null for non-existent.
	 */
	public function test_get_post_type_returns_null(): void {
		$handler = new New_Post_Type_Handler();

		$this->assertNull( $handler->get_post_type( 'nonexistent' ) );
	}

	/**
	 * Test get_post_types returns all registered.
	 */
	public function test_get_post_types(): void {
		$handler = new New_Post_Type_Handler();
		$handler->add_post_type( 'type_a', [ 'label' => 'Type A' ] );
		$handler->add_post_type( 'type_b', [ 'label' => 'Type B' ] );

		$types = $handler->get_post_types();

		$this->assertCount( 2, $types );
		$this->assertArrayHasKey( 'type_a', $types );
		$this->assertArrayHasKey( 'type_b', $types );
	}

	/**
	 * Test has_post_type returns correct boolean.
	 */
	public function test_has_post_type(): void {
		$handler = new New_Post_Type_Handler();
		$handler->add_post_type( 'test_cpt', [ 'label' => 'Test' ] );

		$this->assertTrue( $handler->has_post_type( 'test_cpt' ) );
		$this->assertFalse( $handler->has_post_type( 'nonexistent' ) );
	}

	/**
	 * Test register_post_types creates the CPT.
	 */
	public function test_register_post_types(): void {
		$handler = new New_Post_Type_Handler();
		$handler->add_post_type(
			'handler_cpt',
			[
				'label'  => 'Handler CPT',
				'public' => true,
			]
		);

		$handler->register_post_types();

		$this->assertTrue( post_type_exists( 'handler_cpt' ) );

		// Cleanup
		unregister_post_type( 'handler_cpt' );
	}

	/**
	 * Test common assets are enqueued before field-specific assets.
	 *
	 * Regression test for #64: field CSS must be able to override common
	 * CSS at equal specificity, which only works if the common
	 * cassette-cmf script/style is registered before field-specific
	 * assets. Uses a repeater field, whose enqueue_assets() enqueues the
	 * distinguishable jquery-ui-sortable handle, to prove ordering.
	 */
	public function test_common_assets_enqueued_before_field_assets(): void {
		// Scripts/styles queues are global state shared across tests; reset
		// them so a handle enqueued by an earlier test can't pin an
		// unrelated position and produce a false pass or failure here.
		global $wp_scripts;
		$wp_scripts->queue = [];
		$wp_scripts->done  = [];

		set_current_screen( 'edit-post' );
		get_current_screen()->post_type = 'enqueue_order_cpt';

		$handler = new New_Post_Type_Handler();
		$handler->add_post_type( 'enqueue_order_cpt', [ 'label' => 'Test' ] );
		$handler->add_fields(
			'enqueue_order_cpt',
			[
				[
					'name'   => 'test_repeater',
					'type'   => 'repeater',
					'label'  => 'Test',
					'fields' => [
						[
							'name'  => 'sub_field',
							'type'  => 'text',
							'label' => 'Sub',
						],
					],
				],
			]
		);

		$handler->enqueue_assets();

		$queue      = $wp_scripts->queue;
		$common_pos = array_search( 'cassette-cmf', $queue, true );
		$field_pos  = array_search( 'jquery-ui-sortable', $queue, true );

		$this->assertNotFalse( $common_pos, 'Common cassette-cmf script should be enqueued.' );
		$this->assertNotFalse( $field_pos, 'Field-specific jquery-ui-sortable script should be enqueued.' );
		$this->assertLessThan(
			$field_pos,
			$common_pos,
			'Common assets must be enqueued before field-specific assets.'
		);
	}

	/**
	 * Test get_version() resolves composer.json's "version" key.
	 *
	 * Regression test: the path was computed as dirname(dirname(__DIR__)),
	 * which from src/core/Handlers only reaches src/ — one level short of
	 * the repository root where composer.json actually lives — so the
	 * file was never found and this always silently fell back to a
	 * file-mtime timestamp, regardless of composer.json's content.
	 */
	public function test_get_version_resolves_composer_json(): void {
		$composer_path = dirname( __DIR__, 2 ) . '/composer.json';
		$this->assertFileExists( $composer_path, 'Sanity check: composer.json should exist at the repository root.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$composer = json_decode( file_get_contents( $composer_path ), true );
		$this->assertArrayHasKey(
			'version',
			$composer,
			'composer.json should declare a version key; bin/set-version.sh adds one if absent.'
		);

		$handler = new New_Post_Type_Handler();
		$method  = new ReflectionMethod( $handler, 'get_version' );
		$method->setAccessible( true );

		$this->assertSame( $composer['version'], $method->invoke( $handler ) );
	}

	/**
	 * Test handler works via Manager.
	 */
	public function test_handler_via_manager(): void {
		$manager = Manager::init();
		$handler = $manager->get_new_cpt_handler();

		$this->assertInstanceOf( New_Post_Type_Handler::class, $handler );

		$handler->add_post_type(
			'managed_cpt',
			[ 'label' => 'Managed CPT' ]
		);

		$this->assertTrue( $handler->has_post_type( 'managed_cpt' ) );
	}

	/**
	 * Test adding fields to post type.
	 */
	public function test_add_fields_to_post_type(): void {
		$handler = new New_Post_Type_Handler();
		$handler->add_post_type( 'product', [ 'label' => 'Products' ] );

		// add_fields returns void
		$handler->add_fields(
			'product',
			[
				[
					'name'  => 'price',
					'type'  => 'number',
					'label' => 'Price',
				],
				[
					'name'  => 'sku',
					'type'  => 'text',
					'label' => 'SKU',
				],
			]
		);

		// Verify fields were added
		$fields = $handler->get_fields( 'product' );
		$this->assertCount( 2, $fields );
	}

	// =========================================================================
	// Existing_Post_Type_Handler Tests
	// =========================================================================

	/**
	 * Test Existing_Post_Type_Handler can be instantiated.
	 */
	public function test_existing_post_type_handler_instantiation(): void {
		$handler = new Existing_Post_Type_Handler();
		$this->assertInstanceOf( Existing_Post_Type_Handler::class, $handler );
	}

	/**
	 * Test adding fields to existing post type.
	 */
	public function test_add_fields_to_existing_post(): void {
		$handler = new Existing_Post_Type_Handler();

		// add_fields returns void
		$handler->add_fields(
			'post',
			[
				[
					'name'  => 'subtitle',
					'type'  => 'text',
					'label' => 'Subtitle',
				],
			]
		);

		// Verify fields were added
		$fields = $handler->get_fields( 'post' );
		$this->assertCount( 1, $fields );
	}

	/**
	 * Test post_type_exists for core types.
	 */
	public function test_post_type_exists(): void {
		$handler = new Existing_Post_Type_Handler();

		// Core WordPress types should exist
		$this->assertTrue( $handler->post_type_exists( 'post' ) );
		$this->assertTrue( $handler->post_type_exists( 'page' ) );

		// Random type should not exist
		$this->assertFalse( $handler->post_type_exists( 'nonexistent_type_xyz' ) );
	}

	/**
	 * Test handler works via Manager.
	 */
	public function test_existing_handler_via_manager(): void {
		$manager = Manager::init();
		$handler = $manager->get_existing_cpt_handler();

		$this->assertInstanceOf( Existing_Post_Type_Handler::class, $handler );
	}

	// =========================================================================
	// New_Settings_Page_Handler Tests
	// =========================================================================

	/**
	 * Test New_Settings_Page_Handler can be instantiated.
	 */
	public function test_new_settings_page_handler_instantiation(): void {
		$handler = new New_Settings_Page_Handler();
		$this->assertInstanceOf( New_Settings_Page_Handler::class, $handler );
	}

	/**
	 * Test add_page returns fluent interface.
	 */
	public function test_add_page_fluent(): void {
		$handler = new New_Settings_Page_Handler();
		$result  = $handler->add_page( 'my_settings', [ 'page_title' => 'My Settings' ] );

		$this->assertInstanceOf( New_Settings_Page_Handler::class, $result );
		$this->assertSame( $handler, $result );
	}

	/**
	 * Test add_page_instance accepts Settings_Page.
	 */
	public function test_add_page_instance(): void {
		$handler = new New_Settings_Page_Handler();
		$page    = Settings_Page::from_array( 'test_page', [ 'page_title' => 'Test Page' ] );

		$result = $handler->add_page_instance( $page );

		$this->assertInstanceOf( New_Settings_Page_Handler::class, $result );
	}

	/**
	 * Test get_page returns correct object.
	 */
	public function test_get_page(): void {
		$handler = new New_Settings_Page_Handler();
		$handler->add_page( 'my_settings', [ 'page_title' => 'My Settings' ] );

		$page = $handler->get_page( 'my_settings' );

		$this->assertInstanceOf( Settings_Page::class, $page );
	}

	/**
	 * Test get_page returns null for non-existent.
	 */
	public function test_get_page_returns_null(): void {
		$handler = new New_Settings_Page_Handler();

		$this->assertNull( $handler->get_page( 'nonexistent' ) );
	}

	/**
	 * Test get_pages returns all registered.
	 */
	public function test_get_pages(): void {
		$handler = new New_Settings_Page_Handler();
		$handler->add_page( 'page_a', [ 'page_title' => 'Page A' ] );
		$handler->add_page( 'page_b', [ 'page_title' => 'Page B' ] );

		$pages = $handler->get_pages();

		$this->assertCount( 2, $pages );
		$this->assertArrayHasKey( 'page_a', $pages );
		$this->assertArrayHasKey( 'page_b', $pages );
	}

	/**
	 * Test has_page returns correct boolean.
	 */
	public function test_has_page(): void {
		$handler = new New_Settings_Page_Handler();
		$handler->add_page( 'my_settings', [ 'page_title' => 'My Settings' ] );

		$this->assertTrue( $handler->has_page( 'my_settings' ) );
		$this->assertFalse( $handler->has_page( 'nonexistent' ) );
	}

	/**
	 * Test adding fields to settings page.
	 */
	public function test_add_fields_to_settings_page(): void {
		$handler = new New_Settings_Page_Handler();
		$handler->add_page( 'general_settings', [ 'page_title' => 'General Settings' ] );

		// add_fields returns void
		$handler->add_fields(
			'general_settings',
			[
				[
					'name'  => 'site_name',
					'type'  => 'text',
					'label' => 'Site Name',
				],
				[
					'name'  => 'site_email',
					'type'  => 'email',
					'label' => 'Site Email',
				],
			]
		);

		// Verify fields were added
		$fields = $handler->get_fields( 'general_settings' );
		$this->assertCount( 2, $fields );
	}

	/**
	 * Test handler works via Manager.
	 */
	public function test_new_settings_handler_via_manager(): void {
		$manager = Manager::init();
		$handler = $manager->get_new_settings_handler();

		$this->assertInstanceOf( New_Settings_Page_Handler::class, $handler );
	}

	// =========================================================================
	// Existing_Settings_Page_Handler Tests
	// =========================================================================

	/**
	 * Test Existing_Settings_Page_Handler can be instantiated.
	 */
	public function test_existing_settings_page_handler_instantiation(): void {
		$handler = new Existing_Settings_Page_Handler();
		$this->assertInstanceOf( Existing_Settings_Page_Handler::class, $handler );
	}

	/**
	 * Test is_wordpress_page for core pages.
	 */
	public function test_is_wordpress_page(): void {
		$handler = new Existing_Settings_Page_Handler();

		// Core WordPress pages
		$this->assertTrue( $handler->is_wordpress_page( 'general' ) );
		$this->assertTrue( $handler->is_wordpress_page( 'writing' ) );
		$this->assertTrue( $handler->is_wordpress_page( 'reading' ) );
		$this->assertTrue( $handler->is_wordpress_page( 'discussion' ) );
		$this->assertTrue( $handler->is_wordpress_page( 'media' ) );
		$this->assertTrue( $handler->is_wordpress_page( 'permalink' ) );
		$this->assertTrue( $handler->is_wordpress_page( 'privacy' ) );

		// Non-WordPress pages
		$this->assertFalse( $handler->is_wordpress_page( 'custom_page' ) );
	}

	/**
	 * Test get_option_group returns correct group.
	 */
	public function test_get_option_group(): void {
		$handler = new Existing_Settings_Page_Handler();

		$this->assertSame( 'general', $handler->get_option_group( 'general' ) );
		$this->assertSame( 'writing', $handler->get_option_group( 'writing' ) );
		$this->assertSame( 'reading', $handler->get_option_group( 'reading' ) );
	}

	/**
	 * Test adding fields to existing settings page.
	 */
	public function test_add_fields_to_existing_settings(): void {
		$handler = new Existing_Settings_Page_Handler();

		// add_fields returns void
		$handler->add_fields(
			'general',
			[
				[
					'name'  => 'custom_option',
					'type'  => 'text',
					'label' => 'Custom Option',
				],
			]
		);

		// Verify fields were added
		$fields = $handler->get_fields( 'general' );
		$this->assertCount( 1, $fields );
	}

	/**
	 * Test handler works via Manager.
	 */
	public function test_existing_settings_handler_via_manager(): void {
		$manager = Manager::init();
		$handler = $manager->get_existing_settings_handler();

		$this->assertInstanceOf( Existing_Settings_Page_Handler::class, $handler );
	}

	// =========================================================================
	// Handler Hook Initialization Tests
	// =========================================================================

	/**
	 * Test are_hooks_initialized returns false by default.
	 */
	public function test_hooks_not_initialized_by_default(): void {
		$handler = new New_Post_Type_Handler();

		$this->assertFalse( $handler->are_hooks_initialized() );
	}

	/**
	 * Test are_hooks_initialized returns true after init_hooks.
	 */
	public function test_hooks_initialized_after_init(): void {
		$handler = new New_Post_Type_Handler();
		$handler->init_hooks();

		$this->assertTrue( $handler->are_hooks_initialized() );
	}

	/**
	 * Test init_hooks is idempotent.
	 */
	public function test_init_hooks_idempotent(): void {
		$handler = new New_Post_Type_Handler();

		$handler->init_hooks();
		$handler->init_hooks();
		$handler->init_hooks();

		$this->assertTrue( $handler->are_hooks_initialized() );
	}

	// =========================================================================
	// Handler Chaining Tests
	// =========================================================================

	/**
	 * Test method chaining on New_Post_Type_Handler.
	 */
	public function test_new_post_type_handler_chaining(): void {
		$handler = new New_Post_Type_Handler();

		$result = $handler
			->add_post_type( 'book', [ 'label' => 'Books' ] )
			->add_post_type( 'movie', [ 'label' => 'Movies' ] );

		$this->assertInstanceOf( New_Post_Type_Handler::class, $result );
		$this->assertTrue( $handler->has_post_type( 'book' ) );
		$this->assertTrue( $handler->has_post_type( 'movie' ) );
	}

	/**
	 * Test method chaining on New_Settings_Page_Handler.
	 */
	public function test_new_settings_handler_chaining(): void {
		$handler = new New_Settings_Page_Handler();

		$result = $handler
			->add_page( 'settings_a', [ 'page_title' => 'Settings A' ] )
			->add_page( 'settings_b', [ 'page_title' => 'Settings B' ] );

		$this->assertInstanceOf( New_Settings_Page_Handler::class, $result );
		$this->assertTrue( $handler->has_page( 'settings_a' ) );
		$this->assertTrue( $handler->has_page( 'settings_b' ) );
	}
}
