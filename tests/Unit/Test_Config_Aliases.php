<?php
/**
 * Settings page key aliases and taxonomies in config arrays.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Config_Aliases
 */
class Test_Config_Aliases extends CassetteCMF_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Set up: fresh manager.
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

	/**
	 * Register one settings page and return its config.
	 *
	 * @param array<string, mixed> $page Page config.
	 * @return array<string, mixed>
	 */
	private function register_page( array $page ): array {
		$this->manager->register_from_array( [ 'settings_pages' => [ $page ] ] );

		return $this->manager->get_new_settings_handler()->get_page( $page['id'] )->get_all_config();
	}

	/**
	 * The schema's names work as aliases.
	 */
	public function test_schema_names_are_accepted_as_aliases(): void {
		$config = $this->register_page(
			[
				'id'         => 'alias_page',
				'page_title' => 'Alias',
				'slug'       => 'alias-slug',
				'icon'       => 'dashicons-admin-generic',
				'parent'     => 'options-general.php',
				'fields'     => [
					[
						'name' => 'a',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertSame( 'alias-slug', $config['menu_slug'] );
		$this->assertSame( 'dashicons-admin-generic', $config['icon_url'] );
		$this->assertSame( 'options-general.php', $config['parent_slug'] );
		$this->assertArrayNotHasKey( 'slug', $config );
		$this->assertArrayNotHasKey( 'icon', $config );
		$this->assertArrayNotHasKey( 'parent', $config );
	}

	/**
	 * A JSON config using the schema's names registers the page.
	 */
	public function test_json_config_with_schema_names_registers(): void {
		$this->manager->register_from_json(
			wp_json_encode(
				[
					'settings_pages' => [
						[
							'id'         => 'json_alias_page',
							'page_title' => 'JSON alias',
							'slug'       => 'json-alias-slug',
							'parent'     => 'tools.php',
							'fields'     => [
								[
									'name' => 'a',
									'type' => 'text',
								],
							],
						],
					],
				]
			)
		);

		$page = $this->manager->get_new_settings_handler()->get_page( 'json_alias_page' );

		$this->assertNotNull( $page );
		$this->assertSame( 'json-alias-slug', $page->get_menu_slug() );
		$this->assertTrue( $page->is_submenu() );
	}

	/**
	 * Alias keys alone do not turn an "extend an existing page" config into a new page.
	 */
	public function test_alias_only_config_still_extends_an_existing_page(): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'     => 'general',
						'parent' => 'options-general.php',
						'slug'   => 'general',
						'icon'   => 'dashicons-admin-generic',
						'fields' => [
							[
								'name' => 'extra',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$this->assertNull( $this->manager->get_new_settings_handler()->get_page( 'general' ) );
	}

	/**
	 * The code name wins and a notice is raised.
	 */
	public function test_code_name_wins_over_alias_with_a_notice(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMF\Core\Manager::apply_settings_page_aliases' );

		$config = $this->register_page(
			[
				'id'          => 'both_page',
				'page_title'  => 'Both',
				'slug'        => 'ignored-slug',
				'menu_slug'   => 'code-slug',
				'parent'      => 'ignored.php',
				'parent_slug' => 'tools.php',
				'fields'      => [
					[
						'name' => 'a',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertSame( 'code-slug', $config['menu_slug'] );
		$this->assertSame( 'tools.php', $config['parent_slug'] );
		$this->assertArrayNotHasKey( 'slug', $config );
		$this->assertArrayNotHasKey( 'parent', $config );
	}

	/**
	 * Code names alone are unchanged and raise nothing.
	 */
	public function test_code_names_are_unchanged(): void {
		$config = $this->register_page(
			[
				'id'          => 'code_page',
				'page_title'  => 'Code',
				'menu_slug'   => 'code-only',
				'icon_url'    => 'dashicons-admin-site',
				'parent_slug' => 'themes.php',
				'fields'      => [
					[
						'name' => 'a',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertSame( 'code-only', $config['menu_slug'] );
		$this->assertSame( 'dashicons-admin-site', $config['icon_url'] );
		$this->assertSame( 'themes.php', $config['parent_slug'] );
	}

	/**
	 * A config that sets none of the keys still adds fields to an existing page.
	 */
	public function test_config_without_page_keys_targets_an_existing_page(): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'     => 'general',
						'fields' => [
							[
								'name' => 'extra',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$this->assertNull( $this->manager->get_new_settings_handler()->get_page( 'general' ) );
	}

	/**
	 * A new taxonomy in a config array is registered, with its fields.
	 */
	public function test_new_taxonomy_in_config_array(): void {
		$this->manager->register_from_array(
			[
				'taxonomies' => [
					[
						'id'          => 'cfg_genre',
						'args'        => [
							'label'  => 'Genres',
							'public' => true,
						],
						'object_type' => [ 'post' ],
						'fields'      => [
							[
								'name' => 'color',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$this->assertTrue( taxonomy_exists( 'cfg_genre' ) );
		$this->assertArrayHasKey( 'color', $this->manager->get_new_taxonomy_handler()->get_fields( 'cfg_genre' ) );
	}

	/**
	 * Fields can be added to an existing taxonomy through a config array.
	 */
	public function test_existing_taxonomy_in_config_array(): void {
		$this->manager->register_from_array(
			[
				'taxonomies' => [
					[
						'id'     => 'category',
						'fields' => [
							[
								'name' => 'badge',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$this->assertArrayHasKey( 'badge', $this->manager->get_existing_taxonomy_handler()->get_fields( 'category' ) );
		$this->assertArrayNotHasKey( 'category', $this->manager->get_new_taxonomy_handler()->get_taxonomies() );
	}

	/**
	 * The taxonomy config filters and the fields filter fire.
	 */
	public function test_taxonomy_filters_fire(): void {
		$seen = [];

		add_filter(
			'cassette_cmf_taxonomy_config',
			function ( array $config, string $id ) use ( &$seen ) {
				$seen[] = 'broad:' . $id;
				return $config;
			},
			10,
			2
		);
		add_filter(
			'cassette_cmf_taxonomy_config_cfg_topic',
			function ( array $config ) use ( &$seen ) {
				$seen[]                  = 'specific';
				$config['args']['label'] = 'Renamed topics';
				return $config;
			}
		);
		add_filter(
			'cassette_cmf_fields',
			function ( array $fields, string $id, string $kind ) use ( &$seen ) {
				if ( 'cfg_topic' === $id ) {
					$seen[] = 'fields:' . $kind;
				}
				return $fields;
			},
			10,
			3
		);

		$this->manager->register_from_array(
			[
				'taxonomies' => [
					[
						'id'     => 'cfg_topic',
						'args'   => [ 'label' => 'Topics' ],
						'fields' => [
							[
								'name' => 'x',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		remove_all_filters( 'cassette_cmf_taxonomy_config' );
		remove_all_filters( 'cassette_cmf_taxonomy_config_cfg_topic' );
		remove_all_filters( 'cassette_cmf_fields' );

		$this->assertSame( [ 'broad:cfg_topic', 'specific', 'fields:taxonomy' ], $seen );
		$this->assertSame( 'Renamed topics', get_taxonomy( 'cfg_topic' )->label );
	}

	/**
	 * A config without a taxonomies key registers no taxonomy.
	 */
	public function test_config_without_taxonomies_key_registers_none(): void {
		$this->manager->register_from_array(
			[
				'cpts' => [
					[
						'id'   => 'cfg_book',
						'args' => [ 'public' => true ],
					],
				],
			]
		);

		$this->assertSame( [], $this->manager->get_new_taxonomy_handler()->get_taxonomies() );
	}
}
