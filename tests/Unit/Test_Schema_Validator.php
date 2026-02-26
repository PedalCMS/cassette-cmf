<?php
/**
 * Schema Validator Tests
 *
 * Tests for the JSON Schema Validator class.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Json\Schema_Validator;

/**
 * Class Test_Schema_Validator
 *
 * Tests for the Schema_Validator class.
 */
class Test_Schema_Validator extends WP_UnitTestCase {

	/**
	 * Schema_Validator instance.
	 *
	 * @var Schema_Validator
	 */
	private Schema_Validator $validator;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->validator = new Schema_Validator();
	}

	// =========================================================================
	// Basic Validation Tests
	// =========================================================================

	/**
	 * Test empty config is valid.
	 */
	public function test_empty_config_is_valid(): void {
		$result = $this->validator->validate( [] );

		$this->assertTrue( $result );
		$this->assertFalse( $this->validator->has_errors() );
	}

	/**
	 * Test validator can be instantiated.
	 */
	public function test_can_instantiate(): void {
		$this->assertInstanceOf( Schema_Validator::class, $this->validator );
	}

	// =========================================================================
	// CPT Validation Tests
	// =========================================================================

	/**
	 * Test valid CPT configuration.
	 */
	public function test_valid_cpt_config(): void {
		$config = [
			'cpts' => [
				[
					'id'   => 'book',
					'args' => [
						'label'  => 'Books',
						'public' => true,
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test CPT missing required id.
	 */
	public function test_cpt_missing_id(): void {
		$config = [
			'cpts' => [
				[
					'args' => [ 'label' => 'Books' ],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertTrue( $this->validator->has_errors() );
		$this->assertStringContainsString( 'id', $this->validator->get_error_message() );
	}

	/**
	 * Test CPT id must be string.
	 */
	public function test_cpt_id_must_be_string(): void {
		$config = [
			'cpts' => [
				[
					'id' => 123,
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'string', $this->validator->get_error_message() );
	}

	/**
	 * Test CPT id pattern validation.
	 */
	public function test_cpt_id_pattern(): void {
		// Invalid: uppercase
		$config = [
			'cpts' => [
				[ 'id' => 'InvalidCPT' ],
			],
		];

		$this->assertFalse( $this->validator->validate( $config ) );

		// Invalid: too long (> 20 chars)
		$validator2 = new Schema_Validator();
		$config2    = [
			'cpts' => [
				[ 'id' => 'this_id_is_way_too_long_for_cpt' ],
			],
		];

		$this->assertFalse( $validator2->validate( $config2 ) );

		// Valid: lowercase with underscores
		$validator3 = new Schema_Validator();
		$config3    = [
			'cpts' => [
				[ 'id' => 'valid_cpt' ],
			],
		];

		$this->assertTrue( $validator3->validate( $config3 ) );
	}

	/**
	 * Test CPT with valid fields.
	 */
	public function test_cpt_with_valid_fields(): void {
		$config = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name'  => 'author',
							'type'  => 'text',
							'label' => 'Author',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test multiple CPTs validation.
	 */
	public function test_multiple_cpts(): void {
		$config = [
			'cpts' => [
				[ 'id' => 'book' ],
				[ 'id' => 'movie' ],
				[ 'id' => 'album' ],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// Settings Page Validation Tests
	// =========================================================================

	/**
	 * Test valid settings page configuration.
	 */
	public function test_valid_settings_page_config(): void {
		$config = [
			'settings_pages' => [
				[
					'id'         => 'my_settings',
					'page_title' => 'My Settings',
					'capability' => 'manage_options',
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test settings page missing id.
	 */
	public function test_settings_page_missing_id(): void {
		$config = [
			'settings_pages' => [
				[
					'page_title' => 'My Settings',
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'id', $this->validator->get_error_message() );
	}

	/**
	 * Test settings page with fields.
	 */
	public function test_settings_page_with_fields(): void {
		$config = [
			'settings_pages' => [
				[
					'id'     => 'my_settings',
					'fields' => [
						[
							'name'  => 'site_name',
							'type'  => 'text',
							'label' => 'Site Name',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertTrue( $result );
	}

	// =========================================================================
	// Field Validation Tests
	// =========================================================================

	/**
	 * Test field missing required name.
	 */
	public function test_field_missing_name(): void {
		$config = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'type'  => 'text',
							'label' => 'Missing Name',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'name', $this->validator->get_error_message() );
	}

	/**
	 * Test field missing required type.
	 */
	public function test_field_missing_type(): void {
		$config = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name'  => 'author',
							'label' => 'Author',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'type', $this->validator->get_error_message() );
	}

	/**
	 * Test field name pattern validation.
	 */
	public function test_field_name_pattern(): void {
		// Invalid: starts with number
		$config = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name' => '123field',
							'type' => 'text',
						],
					],
				],
			],
		];

		$this->assertFalse( $this->validator->validate( $config ) );

		// Invalid: contains uppercase
		$validator2 = new Schema_Validator();
		$config2    = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name' => 'InvalidName',
							'type' => 'text',
						],
					],
				],
			],
		];

		$this->assertFalse( $validator2->validate( $config2 ) );

		// Valid: underscore at start
		$validator3 = new Schema_Validator();
		$config3    = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name' => '_private_field',
							'type' => 'text',
						],
					],
				],
			],
		];

		$this->assertTrue( $validator3->validate( $config3 ) );
	}

	/**
	 * Test field name max length.
	 */
	public function test_field_name_max_length(): void {
		$long_name = str_repeat( 'a', 65 ); // 65 chars, max is 64

		$config = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name' => $long_name,
							'type' => 'text',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( '64', $this->validator->get_error_message() );
	}

	/**
	 * Test invalid field type.
	 */
	public function test_invalid_field_type(): void {
		$config = [
			'cpts' => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name' => 'test_field',
							'type' => 'invalid_type',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
	}

	/**
	 * Test all valid field types.
	 */
	public function test_all_valid_field_types(): void {
		$valid_types = [
			'text'     => [],
			'textarea' => [],
			'select'   => [ 'options' => [ 'a' => 'A' ] ], // requires options
			'checkbox' => [ 'options' => [ 'a' => 'A' ] ], // optional options for multiple
			'radio'    => [ 'options' => [ 'a' => 'A' ] ], // requires options
			'number'   => [],
			'email'    => [],
			'url'      => [],
			'date'     => [],
			'password' => [],
			'color'    => [],
			'wysiwyg'  => [],
			'tabs'     => [
				'tabs' => [
					[
						'id'     => 'tab1',
						'label'  => 'Tab 1',
						'fields' => [],
					],
				],
			], // requires tabs
			'metabox'  => [ 'fields' => [] ], // requires fields
			'group'    => [ 'fields' => [] ], // requires fields
			'repeater' => [
				'fields' => [
					[
						'name' => 'item',
						'type' => 'text',
					],
				],
			], // requires fields with at least one
		];

		foreach ( $valid_types as $type => $extra ) {
			$validator = new Schema_Validator();
			$config    = [
				'cpts' => [
					[
						'id'     => 'book',
						'fields' => [
							array_merge(
								[
									'name' => 'test_field',
									'type' => $type,
								],
								$extra
							),
						],
					],
				],
			];

			$result = $validator->validate( $config );
			$this->assertTrue( $result, "Field type '{$type}' should be valid. Errors: " . $validator->get_error_message() );
		}
	}

	// =========================================================================
	// Error Handling Tests
	// =========================================================================

	/**
	 * Test get_errors returns array.
	 */
	public function test_get_errors_returns_array(): void {
		$this->validator->validate( [] );

		$errors = $this->validator->get_errors();

		$this->assertIsArray( $errors );
	}

	/**
	 * Test get_error_message returns string.
	 */
	public function test_get_error_message_returns_string(): void {
		$this->validator->validate(
			[
				'cpts' => [
					[], // Missing id
				],
			]
		);

		$message = $this->validator->get_error_message();

		$this->assertIsString( $message );
		$this->assertNotEmpty( $message );
	}

	/**
	 * Test multiple errors are collected.
	 */
	public function test_multiple_errors_collected(): void {
		$config = [
			'cpts' => [
				[], // Missing id
				[], // Missing id
				[
					'id'     => 'book',
					'fields' => [
						[], // Missing name and type
					],
				],
			],
		];

		$this->validator->validate( $config );

		$errors = $this->validator->get_errors();

		$this->assertGreaterThan( 1, count( $errors ) );
	}

	// =========================================================================
	// Mixed Configuration Tests
	// =========================================================================

	/**
	 * Test mixed CPTs and settings pages.
	 */
	public function test_mixed_configuration(): void {
		$config = [
			'cpts'           => [
				[
					'id'     => 'book',
					'fields' => [
						[
							'name' => 'author',
							'type' => 'text',
						],
					],
				],
			],
			'settings_pages' => [
				[
					'id'     => 'my_settings',
					'fields' => [
						[
							'name' => 'site_name',
							'type' => 'text',
						],
					],
				],
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test cpts must be array.
	 */
	public function test_cpts_must_be_array(): void {
		$config = [
			'cpts' => 'not an array',
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'array', $this->validator->get_error_message() );
	}

	/**
	 * Test settings_pages must be array.
	 */
	public function test_settings_pages_must_be_array(): void {
		$config = [
			'settings_pages' => 'not an array',
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
		$this->assertStringContainsString( 'array', $this->validator->get_error_message() );
	}

	/**
	 * Test CPT item must be array.
	 */
	public function test_cpt_item_must_be_array(): void {
		$config = [
			'cpts' => [
				'not an array',
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
	}

	/**
	 * Test settings page item must be array.
	 */
	public function test_settings_page_item_must_be_array(): void {
		$config = [
			'settings_pages' => [
				'not an array',
			],
		];

		$result = $this->validator->validate( $config );

		$this->assertFalse( $result );
	}
}
