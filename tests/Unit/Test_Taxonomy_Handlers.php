<?php
/**
 * Taxonomy Handler Tests
 *
 * Tests for the Taxonomy Handler classes (New_Taxonomy_Handler, Existing_Taxonomy_Handler).
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Core\Manager;
use Pedalcms\CassetteCmf\Core\Handlers\New_Taxonomy_Handler;
use Pedalcms\CassetteCmf\Core\Handlers\Existing_Taxonomy_Handler;
use Pedalcms\CassetteCmf\Field\Field_Factory;

/**
 * Class Test_Taxonomy_Handlers
 *
 * Tests for taxonomy handler classes.
 */
class Test_Taxonomy_Handlers extends WP_UnitTestCase {

	/**
	 * Reset between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Manager::reset();
		Field_Factory::reset();
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		// Unregister any test taxonomies
		foreach ( [ 'test_taxonomy', 'book_genre', 'product_category', 'event_type' ] as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}

		parent::tear_down();
	}

	// =========================================================================
	// New_Taxonomy_Handler Instantiation Tests
	// =========================================================================

	/**
	 * Test New_Taxonomy_Handler can be instantiated.
	 */
	public function test_new_taxonomy_handler_instantiation(): void {
		$handler = new New_Taxonomy_Handler();
		$this->assertInstanceOf( New_Taxonomy_Handler::class, $handler );
	}

	/**
	 * Test hooks not initialized by default.
	 */
	public function test_new_taxonomy_hooks_not_initialized_by_default(): void {
		$handler = new New_Taxonomy_Handler();
		$this->assertFalse( $handler->are_hooks_initialized() );
	}

	/**
	 * Test hooks initialized after init_hooks.
	 */
	public function test_new_taxonomy_hooks_initialized_after_init(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->init_hooks();
		$this->assertTrue( $handler->are_hooks_initialized() );
	}

	/**
	 * Test init_hooks is idempotent.
	 */
	public function test_new_taxonomy_init_hooks_idempotent(): void {
		$handler = new New_Taxonomy_Handler();

		$handler->init_hooks();
		$handler->init_hooks();
		$handler->init_hooks();

		$this->assertTrue( $handler->are_hooks_initialized() );
	}

	// =========================================================================
	// New_Taxonomy_Handler Taxonomy Registration Tests
	// =========================================================================

	/**
	 * Test add_taxonomy stores taxonomy configuration.
	 */
	public function test_add_taxonomy(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy(
			'book_genre',
			[
				'label'        => 'Genres',
				'hierarchical' => true,
			],
			[ 'book' ]
		);

		$taxonomy = $handler->get_taxonomy( 'book_genre' );

		$this->assertNotNull( $taxonomy );
		$this->assertArrayHasKey( 'args', $taxonomy );
		$this->assertArrayHasKey( 'object_type', $taxonomy );
		$this->assertEquals( 'Genres', $taxonomy['args']['label'] );
		$this->assertEquals( [ 'book' ], $taxonomy['object_type'] );
	}

	/**
	 * Test add_taxonomy with default object type.
	 */
	public function test_add_taxonomy_default_object_type(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy(
			'test_taxonomy',
			[ 'label' => 'Test' ]
		);

		$taxonomy = $handler->get_taxonomy( 'test_taxonomy' );

		$this->assertNotNull( $taxonomy );
		$this->assertEquals( [ 'post' ], $taxonomy['object_type'] );
	}

	/**
	 * Test get_taxonomy returns null for non-existent.
	 */
	public function test_get_taxonomy_returns_null(): void {
		$handler = new New_Taxonomy_Handler();
		$this->assertNull( $handler->get_taxonomy( 'nonexistent' ) );
	}

	/**
	 * Test get_taxonomies returns all registered.
	 */
	public function test_get_taxonomies(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy( 'taxonomy_a', [ 'label' => 'Taxonomy A' ] );
		$handler->add_taxonomy( 'taxonomy_b', [ 'label' => 'Taxonomy B' ] );

		$taxonomies = $handler->get_taxonomies();

		$this->assertCount( 2, $taxonomies );
		$this->assertArrayHasKey( 'taxonomy_a', $taxonomies );
		$this->assertArrayHasKey( 'taxonomy_b', $taxonomies );
	}

	/**
	 * Test register_taxonomies creates the taxonomy.
	 */
	public function test_register_taxonomies(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy(
			'test_taxonomy',
			[
				'label'  => 'Test Taxonomy',
				'public' => true,
			]
		);

		$handler->register_taxonomies();

		$this->assertTrue( taxonomy_exists( 'test_taxonomy' ) );
	}

	/**
	 * Test register_taxonomies with multiple object types.
	 */
	public function test_register_taxonomies_multiple_object_types(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy(
			'test_taxonomy',
			[
				'label'  => 'Test Taxonomy',
				'public' => true,
			],
			[ 'post', 'page' ]
		);

		$handler->register_taxonomies();

		$taxonomy = get_taxonomy( 'test_taxonomy' );

		$this->assertNotFalse( $taxonomy );
		$this->assertContains( 'post', $taxonomy->object_type );
		$this->assertContains( 'page', $taxonomy->object_type );
	}

	// =========================================================================
	// New_Taxonomy_Handler Field Tests
	// =========================================================================

	/**
	 * Test adding fields to taxonomy.
	 */
	public function test_add_fields_to_new_taxonomy(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy( 'product_category', [ 'label' => 'Categories' ] );

		$handler->add_fields(
			'product_category',
			[
				[
					'name'  => 'icon',
					'type'  => 'text',
					'label' => 'Icon',
				],
				[
					'name'  => 'color',
					'type'  => 'color',
					'label' => 'Color',
				],
			]
		);

		$fields = $handler->get_fields( 'product_category' );
		$this->assertCount( 2, $fields );
	}

	/**
	 * Test has_fields returns correct boolean.
	 */
	public function test_has_fields_new_taxonomy(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy( 'book_genre', [ 'label' => 'Genres' ] );

		$this->assertFalse( $handler->has_fields( 'book_genre' ) );

		$handler->add_fields(
			'book_genre',
			[
				[
					'name'  => 'color',
					'type'  => 'color',
					'label' => 'Color',
				],
			]
		);

		$this->assertTrue( $handler->has_fields( 'book_genre' ) );
	}

	/**
	 * Test get_fields returns empty array for non-existent taxonomy.
	 */
	public function test_get_fields_empty_for_nonexistent_taxonomy(): void {
		$handler = new New_Taxonomy_Handler();

		$fields = $handler->get_fields( 'nonexistent' );

		$this->assertIsArray( $fields );
		$this->assertEmpty( $fields );
	}

	// =========================================================================
	// New_Taxonomy_Handler via Manager Tests
	// =========================================================================

	/**
	 * Test handler works via Manager.
	 */
	public function test_new_taxonomy_handler_via_manager(): void {
		$manager = Manager::init();
		$handler = $manager->get_new_taxonomy_handler();

		$this->assertInstanceOf( New_Taxonomy_Handler::class, $handler );
	}

	/**
	 * Test Manager returns same handler instance.
	 */
	public function test_new_taxonomy_handler_singleton_via_manager(): void {
		$manager  = Manager::init();
		$handler1 = $manager->get_new_taxonomy_handler();
		$handler2 = $manager->get_new_taxonomy_handler();

		$this->assertSame( $handler1, $handler2 );
	}

	// =========================================================================
	// Existing_Taxonomy_Handler Instantiation Tests
	// =========================================================================

	/**
	 * Test Existing_Taxonomy_Handler can be instantiated.
	 */
	public function test_existing_taxonomy_handler_instantiation(): void {
		$handler = new Existing_Taxonomy_Handler();
		$this->assertInstanceOf( Existing_Taxonomy_Handler::class, $handler );
	}

	/**
	 * Test hooks not initialized by default.
	 */
	public function test_existing_taxonomy_hooks_not_initialized_by_default(): void {
		$handler = new Existing_Taxonomy_Handler();
		$this->assertFalse( $handler->are_hooks_initialized() );
	}

	/**
	 * Test hooks initialized after init_hooks.
	 */
	public function test_existing_taxonomy_hooks_initialized_after_init(): void {
		$handler = new Existing_Taxonomy_Handler();
		$handler->init_hooks();
		$this->assertTrue( $handler->are_hooks_initialized() );
	}

	/**
	 * Test init_hooks is idempotent.
	 */
	public function test_existing_taxonomy_init_hooks_idempotent(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->init_hooks();
		$handler->init_hooks();
		$handler->init_hooks();

		$this->assertTrue( $handler->are_hooks_initialized() );
	}

	// =========================================================================
	// Existing_Taxonomy_Handler Taxonomy Checks Tests
	// =========================================================================

	/**
	 * Test taxonomy_exists for core types.
	 */
	public function test_taxonomy_exists_for_core_types(): void {
		$handler = new Existing_Taxonomy_Handler();

		// Core WordPress taxonomies should exist
		$this->assertTrue( $handler->taxonomy_exists( 'category' ) );
		$this->assertTrue( $handler->taxonomy_exists( 'post_tag' ) );

		// Random taxonomy should not exist
		$this->assertFalse( $handler->taxonomy_exists( 'nonexistent_taxonomy_xyz' ) );
	}

	// =========================================================================
	// Existing_Taxonomy_Handler Field Tests
	// =========================================================================

	/**
	 * Test adding fields to existing taxonomy.
	 */
	public function test_add_fields_to_existing_taxonomy(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->add_fields(
			'category',
			[
				[
					'name'  => 'category_icon',
					'type'  => 'text',
					'label' => 'Category Icon',
				],
			]
		);

		$fields = $handler->get_fields( 'category' );
		$this->assertCount( 1, $fields );
	}

	/**
	 * Test adding multiple fields to existing taxonomy.
	 */
	public function test_add_multiple_fields_to_existing_taxonomy(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->add_fields(
			'post_tag',
			[
				[
					'name'  => 'tag_color',
					'type'  => 'color',
					'label' => 'Tag Color',
				],
				[
					'name'  => 'tag_priority',
					'type'  => 'number',
					'label' => 'Priority',
				],
				[
					'name'  => 'tag_featured',
					'type'  => 'checkbox',
					'label' => 'Featured',
				],
			]
		);

		$fields = $handler->get_fields( 'post_tag' );
		$this->assertCount( 3, $fields );
	}

	/**
	 * Test has_fields returns correct boolean for existing taxonomy.
	 */
	public function test_has_fields_existing_taxonomy(): void {
		$handler = new Existing_Taxonomy_Handler();

		$this->assertFalse( $handler->has_fields( 'category' ) );

		$handler->add_fields(
			'category',
			[
				[
					'name'  => 'color',
					'type'  => 'color',
					'label' => 'Color',
				],
			]
		);

		$this->assertTrue( $handler->has_fields( 'category' ) );
	}

	/**
	 * Test get_fields returns empty array for taxonomy without fields.
	 */
	public function test_get_fields_empty_for_taxonomy_without_fields(): void {
		$handler = new Existing_Taxonomy_Handler();

		$fields = $handler->get_fields( 'category' );

		$this->assertIsArray( $fields );
		$this->assertEmpty( $fields );
	}

	// =========================================================================
	// Existing_Taxonomy_Handler via Manager Tests
	// =========================================================================

	/**
	 * Test handler works via Manager.
	 */
	public function test_existing_taxonomy_handler_via_manager(): void {
		$manager = Manager::init();
		$handler = $manager->get_existing_taxonomy_handler();

		$this->assertInstanceOf( Existing_Taxonomy_Handler::class, $handler );
	}

	/**
	 * Test Manager returns same handler instance.
	 */
	public function test_existing_taxonomy_handler_singleton_via_manager(): void {
		$manager  = Manager::init();
		$handler1 = $manager->get_existing_taxonomy_handler();
		$handler2 = $manager->get_existing_taxonomy_handler();

		$this->assertSame( $handler1, $handler2 );
	}

	// =========================================================================
	// Term Meta Integration Tests
	// =========================================================================

	/**
	 * Test term meta can be saved and retrieved.
	 */
	public function test_term_meta_save_and_retrieve(): void {
		// Create a term
		$term = wp_insert_term( 'Test Category', 'category' );
		$this->assertNotWPError( $term );

		$term_id = $term['term_id'];

		// Save term meta
		update_term_meta( $term_id, 'test_field', 'test_value' );

		// Retrieve and verify
		$value = get_term_meta( $term_id, 'test_field', true );
		$this->assertEquals( 'test_value', $value );

		// Clean up
		wp_delete_term( $term_id, 'category' );
	}

	/**
	 * Test term meta can be deleted.
	 */
	public function test_term_meta_delete(): void {
		$term = wp_insert_term( 'Test Category 2', 'category' );
		$this->assertNotWPError( $term );

		$term_id = $term['term_id'];

		update_term_meta( $term_id, 'delete_test', 'value' );
		delete_term_meta( $term_id, 'delete_test' );

		$value = get_term_meta( $term_id, 'delete_test', true );
		$this->assertEmpty( $value );

		wp_delete_term( $term_id, 'category' );
	}

	// =========================================================================
	// Field Configuration Tests
	// =========================================================================

	/**
	 * Test field with show_in_columns configuration.
	 */
	public function test_field_with_show_in_columns(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->add_fields(
			'category',
			[
				[
					'name'            => 'category_color',
					'type'            => 'color',
					'label'           => 'Color',
					'show_in_columns' => true,
				],
			]
		);

		$fields = $handler->get_fields( 'category' );
		$field  = $fields['category_color'];

		$this->assertTrue( $field->get_config( 'show_in_columns', false ) );
	}

	/**
	 * Test field with description.
	 */
	public function test_field_with_description(): void {
		$handler = new New_Taxonomy_Handler();
		$handler->add_taxonomy( 'event_type', [ 'label' => 'Event Types' ] );

		$handler->add_fields(
			'event_type',
			[
				[
					'name'        => 'event_color',
					'type'        => 'color',
					'label'       => 'Event Color',
					'description' => 'Choose a color for this event type.',
				],
			]
		);

		$fields = $handler->get_fields( 'event_type' );
		$field  = $fields['event_color'];

		$this->assertEquals( 'Choose a color for this event type.', $field->get_config( 'description', '' ) );
	}

	/**
	 * Test field with default value.
	 */
	public function test_field_with_default_value(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->add_fields(
			'post_tag',
			[
				[
					'name'    => 'tag_priority',
					'type'    => 'number',
					'label'   => 'Priority',
					'default' => 5,
				],
			]
		);

		$fields = $handler->get_fields( 'post_tag' );
		$field  = $fields['tag_priority'];

		$this->assertEquals( 5, $field->get_config( 'default' ) );
	}

	// =========================================================================
	// Cross-Handler Tests
	// =========================================================================

	/**
	 * Test new and existing taxonomy handlers work independently.
	 */
	public function test_handlers_work_independently(): void {
		$new_handler      = new New_Taxonomy_Handler();
		$existing_handler = new Existing_Taxonomy_Handler();

		$new_handler->add_taxonomy( 'book_genre', [ 'label' => 'Genres' ] );
		$new_handler->add_fields(
			'book_genre',
			[
				[
					'name'  => 'color',
					'type'  => 'color',
					'label' => 'Color',
				],
			]
		);

		$existing_handler->add_fields(
			'category',
			[
				[
					'name'  => 'icon',
					'type'  => 'text',
					'label' => 'Icon',
				],
			]
		);

		// Verify each handler has its own fields
		$this->assertCount( 1, $new_handler->get_fields( 'book_genre' ) );
		$this->assertCount( 1, $existing_handler->get_fields( 'category' ) );

		// Verify they don't share fields
		$this->assertEmpty( $new_handler->get_fields( 'category' ) );
		$this->assertEmpty( $existing_handler->get_fields( 'book_genre' ) );
	}

	/**
	 * Test Manager provides both taxonomy handlers.
	 */
	public function test_manager_provides_both_taxonomy_handlers(): void {
		$manager = Manager::init();

		$new_handler      = $manager->get_new_taxonomy_handler();
		$existing_handler = $manager->get_existing_taxonomy_handler();

		$this->assertInstanceOf( New_Taxonomy_Handler::class, $new_handler );
		$this->assertInstanceOf( Existing_Taxonomy_Handler::class, $existing_handler );
		$this->assertNotSame( $new_handler, $existing_handler );
	}

	// =========================================================================
	// Edge Case Tests
	// =========================================================================

	/**
	 * Test adding same taxonomy twice overwrites.
	 */
	public function test_add_taxonomy_twice_overwrites(): void {
		$handler = new New_Taxonomy_Handler();

		$handler->add_taxonomy( 'test_taxonomy', [ 'label' => 'First' ] );
		$handler->add_taxonomy( 'test_taxonomy', [ 'label' => 'Second' ] );

		$taxonomy = $handler->get_taxonomy( 'test_taxonomy' );

		$this->assertEquals( 'Second', $taxonomy['args']['label'] );
	}

	/**
	 * Test adding fields to same taxonomy accumulates.
	 */
	public function test_add_fields_accumulates(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->add_fields(
			'category',
			[
				[
					'name'  => 'field_1',
					'type'  => 'text',
					'label' => 'Field 1',
				],
			]
		);

		$handler->add_fields(
			'category',
			[
				[
					'name'  => 'field_2',
					'type'  => 'text',
					'label' => 'Field 2',
				],
			]
		);

		$fields = $handler->get_fields( 'category' );

		$this->assertCount( 2, $fields );
		$this->assertArrayHasKey( 'field_1', $fields );
		$this->assertArrayHasKey( 'field_2', $fields );
	}

	/**
	 * Test empty fields array.
	 */
	public function test_add_empty_fields_array(): void {
		$handler = new Existing_Taxonomy_Handler();

		$handler->add_fields( 'category', [] );

		$this->assertFalse( $handler->has_fields( 'category' ) );
	}

	/**
	 * Test register_taxonomies does not register if taxonomy already exists.
	 */
	public function test_register_taxonomies_skips_existing(): void {
		$handler = new New_Taxonomy_Handler();

		// Try to register 'category' which already exists
		$handler->add_taxonomy( 'category', [ 'label' => 'My Category' ] );
		$handler->register_taxonomies();

		// The core category taxonomy should still exist and be unchanged
		$taxonomy = get_taxonomy( 'category' );
		$this->assertNotEquals( 'My Category', $taxonomy->label );
	}
}
