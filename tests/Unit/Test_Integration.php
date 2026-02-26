<?php
/**
 * Integration Tests
 *
 * Tests for full workflow integration of Cassette-CMF.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Core\Manager;
use Pedalcms\CassetteCmf\Field\Field_Factory;

require_once __DIR__ . '/CassetteCmf_UnitTestCase.php';

/**
 * Class Test_Integration
 *
 * Tests for end-to-end workflows.
 */
class Test_Integration extends CassetteCmf_UnitTestCase {

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

		// Reset Field_Factory.
		Field_Factory::reset();

		// Set current user as admin.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
	}

	/**
	 * Clean up registered post types.
	 */
	public function tear_down(): void {
		unregister_post_type( 'test_product' );
		parent::tear_down();
	}

	/**
	 * Test complete CPT with fields registration.
	 *
	 */
	public function test_complete_cpt_with_fields(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'     => 'test_product',
						'args'   => [
							'label'        => 'Products',
							'public'       => true,
							'show_ui'      => true,
							'supports'     => [ 'title', 'editor', 'thumbnail' ],
							'show_in_rest' => true,
						],
						'fields' => [
							[
								'name'   => 'product_details',
								'type'   => 'metabox',
								'label'  => 'Product Details',
								'fields' => [
									[
										'name'  => 'sku',
										'type'  => 'text',
										'label' => 'SKU',
									],
									[
										'name'  => 'price',
										'type'  => 'number',
										'label' => 'Price',
										'min'   => 0,
										'step'  => '0.01',
									],
									[
										'name'    => 'status',
										'type'    => 'select',
										'label'   => 'Status',
										'options' => [
											'in_stock'     => 'In Stock',
											'out_of_stock' => 'Out of Stock',
										],
									],
								],
							],
						],
					],
				],
			]
		);

		do_action( 'init' );

		// Verify CPT is registered.
		$this->assertTrue( post_type_exists( 'test_product' ) );

		// Verify fields are registered.
		$handler = $manager->get_new_cpt_handler();
		$fields  = $handler->get_fields( 'test_product' );

		$this->assertArrayHasKey( 'product_details', $fields );
	}

	/**
	 * Test complete settings page with fields.
	 */
	public function test_complete_settings_page_with_fields(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => 'test_shop_settings',
						'page_title' => 'Shop Settings',
						'menu_title' => 'Shop',
						'capability' => 'manage_options',
						'fields'     => [
							[
								'name'  => 'store_name',
								'type'  => 'text',
								'label' => 'Store Name',
							],
							[
								'name'  => 'store_email',
								'type'  => 'email',
								'label' => 'Store Email',
							],
							[
								'name'    => 'currency',
								'type'    => 'select',
								'label'   => 'Currency',
								'options' => [
									'USD' => 'US Dollar',
									'EUR' => 'Euro',
									'GBP' => 'British Pound',
								],
							],
						],
					],
				],
			]
		);

		// Verify settings page is registered.
		$handler = $manager->get_new_settings_handler();
		$this->assertTrue( $handler->has_page( 'test_shop_settings' ) );

		// Verify fields are registered.
		$fields = $handler->get_fields( 'test_shop_settings' );
		$this->assertCount( 3, $fields );
	}

	/**
	 * Test mixed CPTs and settings pages.
	 *
	 */
	public function test_mixed_cpts_and_settings(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'cpts'           => [
					[
						'id'   => 'test_product',
						'args' => [
							'label'  => 'Products',
							'public' => true,
						],
					],
				],
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

		do_action( 'init' );

		// Verify both are registered.
		$this->assertTrue( post_type_exists( 'test_product' ) );

		$handler = $manager->get_new_settings_handler();
		$this->assertTrue( $handler->has_page( 'test_settings' ) );
	}

	/**
	 * Test saving and retrieving post meta.
	 *
	 */
	public function test_save_and_retrieve_post_meta(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'     => 'test_product',
						'args'   => [
							'label'  => 'Products',
							'public' => true,
						],
						'fields' => [
							[
								'name'   => 'simple_meta',
								'type'   => 'metabox',
								'label'  => 'Simple Metabox',
								'fields' => [
									[
										'name'  => 'product_sku',
										'type'  => 'text',
										'label' => 'SKU',
									],
								],
							],
						],
					],
				],
			]
		);

		do_action( 'init' );

		// Create a product.
		$post_id = self::factory()->post->create(
			[
				'post_type'  => 'test_product',
				'post_title' => 'Test Product',
			]
		);

		// Save meta manually.
		update_post_meta( $post_id, 'product_sku', 'SKU-12345' );

		// Retrieve and verify.
		$sku = get_post_meta( $post_id, 'product_sku', true );
		$this->assertSame( 'SKU-12345', $sku );
	}

	/**
	 * Test saving and retrieving options.
	 */
	public function test_save_and_retrieve_options(): void {
		// Save options manually.
		update_option( 'test_store_name', 'My Test Store' );
		update_option( 'test_store_currency', 'USD' );

		// Retrieve and verify.
		$this->assertSame( 'My Test Store', get_option( 'test_store_name' ) );
		$this->assertSame( 'USD', get_option( 'test_store_currency' ) );

		// Clean up.
		delete_option( 'test_store_name' );
		delete_option( 'test_store_currency' );
	}

	/**
	 * Test field validation in workflow.
	 */
	public function test_field_validation_in_workflow(): void {
		$email_field = Field_Factory::create(
			[
				'name'  => 'store_email',
				'type'  => 'email',
				'label' => 'Store Email',
			]
		);

		// Valid email.
		$valid_result = $email_field->validate( 'test@example.com' );
		$this->assertTrue( $valid_result['valid'] );

		// Invalid email.
		$invalid_result = $email_field->validate( 'invalid-email' );
		$this->assertFalse( $invalid_result['valid'] );
	}

	/**
	 * Test field sanitization in workflow.
	 */
	public function test_field_sanitization_in_workflow(): void {
		$text_field = Field_Factory::create(
			[
				'name'  => 'store_name',
				'type'  => 'text',
				'label' => 'Store Name',
			]
		);

		// Test sanitization strips tags.
		$sanitized = $text_field->sanitize( '<script>alert("xss")</script>My Store' );
		$this->assertStringNotContainsString( '<script>', $sanitized );
		$this->assertStringContainsString( 'My Store', $sanitized );
	}
}
