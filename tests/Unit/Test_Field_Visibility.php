<?php
/**
 * Field Visibility Tests
 *
 * Tests for the `bindings` field-config key and for
 * Manager::get_registered_field() / Manager::is_field_public() and their
 * CassetteCMF facade methods.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\CassetteCMF;
use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Json\Schema_Validator;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Field_Visibility
 */
class Test_Field_Visibility extends CassetteCMF_UnitTestCase {

	/**
	 * Manager instance
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		Field_Factory::reset();

		$this->manager = Manager::init();
	}

	// =========================================================================
	// POST CONTEXT
	// =========================================================================

	/**
	 * Registered vs unregistered field names (existing post type).
	 */
	public function test_registered_and_unregistered_post_field(): void {
		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name' => 'subtitle',
					'type' => 'text',
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$config = $this->manager->get_registered_field( 'subtitle', $post_id );

		$this->assertIsArray( $config );
		$this->assertSame( 'subtitle', $config['name'] );
		$this->assertSame( 'text', $config['type'] );

		$this->assertNull( $this->manager->get_registered_field( 'missing', $post_id ) );
		$this->assertNull( $this->manager->get_registered_field( '', $post_id ) );
	}

	/**
	 * Marked vs unmarked fields.
	 */
	public function test_is_field_public_requires_strict_true(): void {
		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name'     => 'marked',
					'type'     => 'text',
					'bindings' => true,
				],
				[
					'name' => 'unmarked',
					'type' => 'text',
				],
				[
					'name'     => 'marked_false',
					'type'     => 'text',
					'bindings' => false,
				],
				[
					'name'     => 'marked_string',
					'type'     => 'text',
					'bindings' => 'true',
				],
				[
					'name'     => 'marked_int',
					'type'     => 'text',
					'bindings' => 1,
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertTrue( $this->manager->is_field_public( 'marked', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'unmarked', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'marked_false', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'marked_string', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'marked_int', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'not_registered', $post_id ) );
	}

	/**
	 * Password fields are never public, even when marked.
	 */
	public function test_password_field_is_never_public(): void {
		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name'     => 'pw',
					'type'     => 'password',
					'bindings' => true,
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertIsArray( $this->manager->get_registered_field( 'pw', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'pw', $post_id ) );
	}

	/**
	 * The secret type string is excluded even before the type exists.
	 */
	public function test_secret_type_is_never_public(): void {
		Field_Factory::register_type( 'secret', \PedalCMS\CassetteCMF\Field\Fields\Password_Field::class );

		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name'     => 'token',
					'type'     => 'secret',
					'bindings' => true,
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertIsArray( $this->manager->get_registered_field( 'token', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'token', $post_id ) );
	}

	/**
	 * Post ID maps to its own post type's fields (new custom post type).
	 */
	public function test_post_id_maps_to_post_type(): void {
		$this->manager->register_from_array(
			[
				'cpts' => [
					[
						'id'     => 'vis_book',
						'args'   => [
							'public' => true,
							'labels' => [ 'name' => 'Books' ],
						],
						'fields' => [
							[
								'name'     => 'isbn',
								'type'     => 'text',
								'bindings' => true,
							],
						],
					],
				],
			]
		);

		$book_id = self::factory()->post->create( [ 'post_type' => 'vis_book' ] );
		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertTrue( $this->manager->is_field_public( 'isbn', $book_id ) );
		$this->assertSame( 'isbn', $this->manager->get_registered_field( 'isbn', $book_id )['name'] );

		// Same name, different post type: not registered there.
		$this->assertNull( $this->manager->get_registered_field( 'isbn', $post_id ) );
		$this->assertFalse( $this->manager->is_field_public( 'isbn', $post_id ) );
	}

	/**
	 * Unknown or invalid posts resolve to null.
	 */
	public function test_invalid_post_resolves_to_null(): void {
		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name'     => 'subtitle',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);

		$this->assertNull( $this->manager->get_registered_field( 'subtitle', 999999 ) );
		$this->assertNull( $this->manager->get_registered_field( 'subtitle', 0 ) );
		$this->assertNull( $this->manager->get_registered_field( 'subtitle', 'not-an-id' ) );
		$this->assertFalse( $this->manager->is_field_public( 'subtitle', 999999 ) );
	}

	// =========================================================================
	// TERM CONTEXT
	// =========================================================================

	/**
	 * Term ID maps to its taxonomy (existing taxonomy).
	 */
	public function test_term_id_maps_to_existing_taxonomy(): void {
		$this->manager->get_existing_taxonomy_handler()->add_fields(
			'category',
			[
				[
					'name'     => 'cat_color',
					'type'     => 'color',
					'bindings' => true,
				],
			]
		);

		$cat = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );
		$tag = self::factory()->term->create_and_get( [ 'taxonomy' => 'post_tag' ] );

		$this->assertTrue( $this->manager->is_field_public( 'cat_color', $cat->term_id, 'term' ) );
		$this->assertSame( 'color', $this->manager->get_registered_field( 'cat_color', $cat->term_id, 'term' )['type'] );

		$this->assertNull( $this->manager->get_registered_field( 'cat_color', $tag->term_id, 'term' ) );
		$this->assertNull( $this->manager->get_registered_field( 'cat_color', 999999, 'term' ) );
	}

	/**
	 * Term ID maps to its taxonomy (new taxonomy).
	 */
	public function test_term_id_maps_to_new_taxonomy(): void {
		$this->manager->register_from_array(
			[
				'taxonomies' => [
					[
						'id'     => 'vis_genre',
						'args'   => [
							'public' => true,
							'labels' => [ 'name' => 'Genres' ],
						],
						'fields' => [
							[
								'name' => 'genre_icon',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'vis_genre' ] );

		$this->assertIsArray( $this->manager->get_registered_field( 'genre_icon', $term->term_id, 'term' ) );
		$this->assertFalse( $this->manager->is_field_public( 'genre_icon', $term->term_id, 'term' ) );
	}

	// =========================================================================
	// SETTINGS CONTEXT
	// =========================================================================

	/**
	 * Settings page field, plus nested group fields and repeater sub-fields.
	 */
	public function test_settings_page_fields_nested_and_repeater(): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => 'vis-settings',
						'page_title' => 'Visibility',
						'menu_title' => 'Visibility',
						'capability' => 'manage_options',
						'menu_slug'  => 'vis-settings',
						'fields'     => [
							[
								'name'     => 'site_tagline',
								'type'     => 'text',
								'bindings' => true,
							],
							[
								'name'   => 'brand',
								'type'   => 'group',
								'fields' => [
									[
										'name'     => 'brand_name',
										'type'     => 'text',
										'bindings' => true,
									],
									[
										'name' => 'brand_secret_note',
										'type' => 'text',
									],
								],
							],
							[
								'name'   => 'links',
								'type'   => 'repeater',
								'fields' => [
									[
										'name'     => 'link_url',
										'type'     => 'url',
										'bindings' => true,
									],
								],
							],
						],
					],
				],
			]
		);

		$this->assertTrue( $this->manager->is_field_public( 'site_tagline', 'vis-settings', 'settings' ) );
		$this->assertSame(
			'site_tagline',
			$this->manager->get_registered_field( 'site_tagline', 'vis-settings', 'settings' )['name']
		);

		// Nested group fields have their own storage key and are registered.
		$this->assertTrue( $this->manager->is_field_public( 'brand_name', 'vis-settings', 'settings' ) );
		$this->assertFalse( $this->manager->is_field_public( 'brand_secret_note', 'vis-settings', 'settings' ) );
		$this->assertIsArray( $this->manager->get_registered_field( 'brand_secret_note', 'vis-settings', 'settings' ) );

		// The repeater itself is registered; its row sub-fields are not.
		$this->assertIsArray( $this->manager->get_registered_field( 'links', 'vis-settings', 'settings' ) );
		$this->assertNull( $this->manager->get_registered_field( 'link_url', 'vis-settings', 'settings' ) );
		$this->assertFalse( $this->manager->is_field_public( 'link_url', 'vis-settings', 'settings' ) );

		// Other page / empty page.
		$this->assertNull( $this->manager->get_registered_field( 'site_tagline', 'other-page', 'settings' ) );
		$this->assertNull( $this->manager->get_registered_field( 'site_tagline', '', 'settings' ) );
	}

	/**
	 * Fields added to an existing settings page are found too.
	 */
	public function test_existing_settings_page_field(): void {
		$this->manager->get_existing_settings_handler()->add_fields(
			'general',
			[
				[
					'name'     => 'extra_note',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);

		$this->assertTrue( $this->manager->is_field_public( 'extra_note', 'general', 'settings' ) );
	}

	// =========================================================================
	// OTHER
	// =========================================================================

	/**
	 * Unknown context types resolve to null / false.
	 */
	public function test_unknown_context_type(): void {
		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name'     => 'subtitle',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertNull( $this->manager->get_registered_field( 'subtitle', $post_id, 'user' ) );
		$this->assertNull( $this->manager->get_registered_field( 'subtitle', $post_id, '' ) );
		$this->assertFalse( $this->manager->is_field_public( 'subtitle', $post_id, 'user' ) );
	}

	/**
	 * The static facade delegates to the Manager.
	 */
	public function test_facade_methods(): void {
		$this->manager->get_existing_cpt_handler()->add_fields(
			'post',
			[
				[
					'name'     => 'subtitle',
					'type'     => 'text',
					'bindings' => true,
				],
			]
		);

		$post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		$this->assertSame( 'subtitle', CassetteCMF::get_registered_field( 'subtitle', $post_id )['name'] );
		$this->assertNull( CassetteCMF::get_registered_field( 'nope', $post_id ) );
		$this->assertTrue( CassetteCMF::is_field_public( 'subtitle', $post_id ) );
		$this->assertFalse( CassetteCMF::is_field_public( 'nope', $post_id ) );
	}

	/**
	 * The bindings key does not change field rendering or sanitizing.
	 */
	public function test_bindings_key_is_ignored_by_the_field(): void {
		$plain  = Field_Factory::create(
			[
				'name' => 'plain',
				'type' => 'text',
			]
		);
		$marked = Field_Factory::create(
			[
				'name'     => 'plain',
				'type'     => 'text',
				'bindings' => true,
			]
		);

		$this->assertSame( $plain->render( 'x' ), $marked->render( 'x' ) );
		$this->assertSame( $plain->sanitize( ' <b>x</b> ' ), $marked->sanitize( ' <b>x</b> ' ) );
		$this->assertSame( $plain->validate( 'x' ), $marked->validate( 'x' ) );
	}

	/**
	 * JSON configs with `bindings` (of any value type) pass validation.
	 *
	 * @dataProvider provide_bindings_values
	 *
	 * @param mixed $value The bindings value.
	 */
	public function test_json_config_with_bindings_validates( $value ): void {
		$validator = new Schema_Validator();

		$config = [
			'cpts'           => [
				[
					'id'     => 'vis_json_cpt',
					'fields' => [
						[
							'name'     => 'headline',
							'type'     => 'text',
							'bindings' => $value,
						],
					],
				],
			],
			'settings_pages' => [
				[
					'id'     => 'vis-json-page',
					'fields' => [
						[
							'name'     => 'tagline',
							'type'     => 'text',
							'bindings' => $value,
						],
					],
				],
			],
		];

		$this->assertTrue( $validator->validate( $config ), $validator->get_error_message() );
	}

	/**
	 * Values for the `bindings` key.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function provide_bindings_values(): array {
		return [
			'true'   => [ true ],
			'false'  => [ false ],
			'string' => [ 'yes' ],
			'int'    => [ 1 ],
		];
	}

	/**
	 * A JSON string with `bindings` registers and is reported public.
	 */
	public function test_register_from_json_with_bindings(): void {
		$json = wp_json_encode(
			[
				'settings_pages' => [
					[
						'id'         => 'vis-json-reg',
						'page_title' => 'JSON',
						'menu_title' => 'JSON',
						'capability' => 'manage_options',
						'menu_slug'  => 'vis-json-reg',
						'fields'     => [
							[
								'name'     => 'tagline',
								'type'     => 'text',
								'bindings' => true,
							],
						],
					],
				],
			]
		);

		$this->manager->register_from_json( $json );

		$this->assertTrue( $this->manager->is_field_public( 'tagline', 'vis-json-reg', 'settings' ) );
	}

	/**
	 * schema.json documents the `bindings` key on the base field.
	 */
	public function test_schema_json_documents_bindings(): void {
		$schema = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/schema.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertArrayHasKey( 'bindings', $schema['definitions']['baseField']['properties'] );
	}
}
