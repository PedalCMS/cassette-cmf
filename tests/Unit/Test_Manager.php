<?php
/**
 * Manager Tests
 *
 * Tests for the Cassette-CMF Manager class.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;

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
			\PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler::class,
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
			\PedalCMS\CassetteCMF\Core\Handlers\Existing_Settings_Page_Handler::class,
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
			\PedalCMS\CassetteCMF\Core\Handlers\New_Post_Type_Handler::class,
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
			\PedalCMS\CassetteCMF\Core\Handlers\Existing_Post_Type_Handler::class,
			$handler
		);
	}

	// =========================================================================
	// Registration Filter Tests (#96)
	// =========================================================================

	/**
	 * Test cassette_cmf_fields_{id} lets an addon append a field to a CPT
	 * it did not define.
	 */
	public function test_cpt_fields_filter_can_add_a_field(): void {
		add_filter(
			'cassette_cmf_fields_cmf96_book',
			function ( array $fields ) {
				$fields[] = [
					'name' => 'added_by_addon',
					'type' => 'text',
				];
				return $fields;
			}
		);

		$manager = Manager::init();
		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'     => 'cmf96_book',
						'args'   => [ 'label' => 'Books' ],
						'fields' => [
							[
								'name' => 'original_field',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$fields = $manager->get_new_cpt_handler()->get_fields( 'cmf96_book' );

		remove_all_filters( 'cassette_cmf_fields_cmf96_book' );

		$this->assertArrayHasKey( 'original_field', $fields );
		$this->assertArrayHasKey( 'added_by_addon', $fields );
	}

	/**
	 * Test the broad cassette_cmf_fields filter receives the entity id
	 * and context, and applies to every entity (not just the id-suffixed
	 * target).
	 */
	public function test_broad_fields_filter_receives_id_and_context(): void {
		$seen = [];

		$callback = function ( array $fields, string $id, string $context ) use ( &$seen ) {
			$seen[] = [ $id, $context ];
			return $fields;
		};

		add_filter( 'cassette_cmf_fields', $callback, 10, 3 );

		$manager = Manager::init();
		$manager->register_from_array(
			[
				'cpts'           => [
					[
						'id'     => 'cmf96_movie',
						'args'   => [ 'label' => 'Movies' ],
						'fields' => [
							[
								'name' => 'title',
								'type' => 'text',
							],
						],
					],
				],
				'settings_pages' => [
					[
						'id'         => 'cmf96_movie_settings',
						'page_title' => 'Movie Settings',
						'fields'     => [
							[
								'name' => 'opt',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		remove_filter( 'cassette_cmf_fields', $callback, 10 );

		$this->assertContains( [ 'cmf96_movie', 'cpt' ], $seen );
		$this->assertContains( [ 'cmf96_movie_settings', 'settings_page' ], $seen );
	}

	/**
	 * Test cassette_cmf_cpt_config_{id} lets an addon modify a CPT's
	 * registration args before it is created.
	 */
	public function test_cpt_config_filter_can_modify_args(): void {
		add_filter(
			'cassette_cmf_cpt_config_cmf96_event',
			function ( array $config ) {
				$config['args']['label'] = 'Overridden Label';
				return $config;
			}
		);

		$manager = Manager::init();
		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'   => 'cmf96_event',
						'args' => [ 'label' => 'Original Label' ],
					],
				],
			]
		);

		$cpt = $manager->get_new_cpt_handler()->get_post_type( 'cmf96_event' );

		remove_all_filters( 'cassette_cmf_cpt_config_cmf96_event' );

		$this->assertSame( 'Overridden Label', $cpt->get_args()['label'] ?? null );
	}

	/**
	 * Test cassette_cmf_register_config lets an addon rewrite the whole
	 * configuration array before anything is registered.
	 */
	public function test_register_config_filter_can_add_an_entity(): void {
		add_filter(
			'cassette_cmf_register_config',
			function ( array $config ) {
				$config['cpts'][] = [
					'id'   => 'cmf96_injected_cpt',
					'args' => [ 'label' => 'Injected' ],
				];
				return $config;
			}
		);

		$manager = Manager::init();
		$manager->register_from_array( [ 'cpts' => [] ] );

		$has_injected = $manager->get_new_cpt_handler()->has_post_type( 'cmf96_injected_cpt' );

		remove_all_filters( 'cassette_cmf_register_config' );

		$this->assertTrue( $has_injected );
	}

	/**
	 * Test cassette_cmf_fields_{id} works for taxonomies too.
	 */
	public function test_taxonomy_fields_filter_can_add_a_field(): void {
		add_filter(
			'cassette_cmf_fields_cmf96_genre',
			function ( array $fields ) {
				$fields[] = [
					'name' => 'added_by_addon',
					'type' => 'text',
				];
				return $fields;
			}
		);

		$manager = Manager::init();
		$manager->register_from_array(
			[
				'taxonomies' => [
					[
						'id'     => 'cmf96_genre',
						'args'   => [ 'label' => 'Genres' ],
						'fields' => [
							[
								'name' => 'original_field',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$fields = $manager->get_new_taxonomy_handler()->get_fields( 'cmf96_genre' );

		remove_all_filters( 'cassette_cmf_fields_cmf96_genre' );

		$this->assertArrayHasKey( 'original_field', $fields );
		$this->assertArrayHasKey( 'added_by_addon', $fields );
	}

	/**
	 * Test cassette_cmf_fields_{id} works for settings pages too.
	 */
	public function test_settings_page_fields_filter_can_add_a_field(): void {
		add_filter(
			'cassette_cmf_fields_cmf96_display_settings',
			function ( array $fields ) {
				$fields[] = [
					'name' => 'added_by_addon',
					'type' => 'text',
				];
				return $fields;
			}
		);

		$manager = Manager::init();
		$manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'         => 'cmf96_display_settings',
						'page_title' => 'Display Settings',
						'fields'     => [
							[
								'name' => 'original_field',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$fields = $manager->get_new_settings_handler()->get_fields( 'cmf96_display_settings' );

		remove_all_filters( 'cassette_cmf_fields_cmf96_display_settings' );

		$this->assertArrayHasKey( 'original_field', $fields );
		$this->assertArrayHasKey( 'added_by_addon', $fields );
	}

	/**
	 * Test register_from_json() routes through the same filters as
	 * register_from_array(), since it's the JSON config path that #96
	 * also needs to cover.
	 */
	public function test_json_registration_applies_the_same_field_filter(): void {
		add_filter(
			'cassette_cmf_fields_cmf96_json_cpt',
			function ( array $fields ) {
				$fields[] = [
					'name' => 'added_by_addon',
					'type' => 'text',
				];
				return $fields;
			}
		);

		$manager = Manager::init();
		$json    = wp_json_encode(
			[
				'cpts' => [
					[
						'id'     => 'cmf96_json_cpt',
						'args'   => [ 'label' => 'JSON CPT' ],
						'fields' => [
							[
								'name' => 'original_field',
								'type' => 'text',
							],
						],
					],
				],
			]
		);
		$manager->register_from_json( $json, false );

		$fields = $manager->get_new_cpt_handler()->get_fields( 'cmf96_json_cpt' );

		remove_all_filters( 'cassette_cmf_fields_cmf96_json_cpt' );

		$this->assertArrayHasKey( 'original_field', $fields );
		$this->assertArrayHasKey( 'added_by_addon', $fields );
	}
}
