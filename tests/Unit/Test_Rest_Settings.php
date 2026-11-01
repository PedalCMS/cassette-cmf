<?php
/**
 * REST settings tests, including field-level show_in_rest
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Rest\Settings_Schema;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';
require_once dirname( __DIR__ ) . '/Support/rest-fixture.php';

/**
 * Class Test_Rest_Settings
 */
class Test_Rest_Settings extends CassetteCMF_UnitTestCase {

	private const NS = 'cassette-cmf/v1';

	/**
	 * Manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Set up: fresh manager, no REST server.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->reset_manager();
		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Tear down: drop the REST server again.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	/**
	 * Reset the Manager singleton and field factory.
	 *
	 * @param array<string, mixed> $boot Optional boot options.
	 */
	private function reset_manager( array $boot = [] ): void {
		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		Field_Factory::reset();

		$this->manager = empty( $boot ) ? Manager::init() : Manager::boot( $boot );
	}

	/**
	 * Register the fixture page (before the REST server is created).
	 *
	 * @param array<string, mixed> $overrides Page overrides.
	 */
	private function register_fixture( array $overrides = [] ): void {
		$this->manager->register_from_array( [ 'settings_pages' => [ Rest_Fixture::page( $overrides ) ] ] );
	}

	/**
	 * Dispatch a request.
	 *
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Route path below the namespace.
	 * @param array<string, mixed>|null $body   JSON body.
	 * @param string                    $ns     Namespace.
	 */
	private function dispatch( string $method, string $path, ?array $body = null, string $ns = self::NS ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/' . $ns . $path );

		if ( null !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Option name of a fixture field.
	 *
	 * @param string $name Field name.
	 */
	private function option( string $name ): string {
		return Rest_Fixture::PAGE_ID . '_' . $name;
	}

	/**
	 * Valid body that touches every writable type.
	 *
	 * @return array<string, mixed>
	 */
	private function full_values(): array {
		return [
			'title'         => 'Hello <b>there</b>',
			'bio'           => "Line one\nLine two",
			'contact_email' => 'me@example.com',
			'site_url'      => 'https://example.com/path',
			'launch_date'   => '2026-11-02',
			'brand_color'   => '#abc',
			'count'         => 5,
			'mode'          => 'b',
			'tags'          => [ 'a', 'c' ],
			'level'         => 'high',
			'enabled'       => true,
			'features'      => [ 'x', 'z' ],
			'notes'         => '<p>Hi</p><script>alert(1)</script>',
			'logo'          => 'https://example.com/logo.png',
			'items'         => [
				[
					'label'  => 'First',
					'qty'    => 2,
					'active' => true,
				],
				[
					'label'  => 'Second',
					'qty'    => 3,
					'active' => false,
				],
			],
			'api_key'       => 'sk-live-12345678',
			'timeout'       => 30,
			'tabbed'        => 'in a tab',
		];
	}

	/**
	 * Same input as full_values(), as a classic form would post it.
	 *
	 * @return array<string, mixed>
	 */
	private function full_post(): array {
		return [
			'title'         => 'Hello <b>there</b>',
			'bio'           => "Line one\nLine two",
			'contact_email' => 'me@example.com',
			'site_url'      => 'https://example.com/path',
			'launch_date'   => '2026-11-02',
			'brand_color'   => '#abc',
			'count'         => '5',
			'mode'          => 'b',
			'tags'          => [ 'a', 'c' ],
			'level'         => 'high',
			'enabled'       => '1',
			'features'      => [ 'x', 'z' ],
			'notes'         => '<p>Hi</p><script>alert(1)</script>',
			'logo'          => 'https://example.com/logo.png',
			'items'         => [
				[
					'label'  => 'First',
					'qty'    => '2',
					'active' => '1',
				],
				[
					'label'  => 'Second',
					'qty'    => '3',
					'active' => '0',
				],
			],
			'api_key'       => [ 'value' => 'sk-live-12345678' ],
			'timeout'       => '30',
			'tabbed'        => 'in a tab',
		];
	}

	// ---------------------------------------------------------------------
	// Opt-in and routes
	// ---------------------------------------------------------------------

	/**
	 * No page opts in: no route at all.
	 */
	public function test_no_route_when_no_page_opts_in(): void {
		$this->register_fixture( [ 'show_in_rest' => false ] );

		$this->assertArrayNotHasKey( self::NS, array_flip( rest_get_server()->get_namespaces() ) );
		$this->assertSame( 404, $this->dispatch( 'GET', '/settings/' . Rest_Fixture::PAGE_ID )->get_status() );
	}

	/**
	 * A page without the key is untouched too.
	 */
	public function test_page_without_key_has_no_route(): void {
		$page = Rest_Fixture::page();
		unset( $page['show_in_rest'] );
		$this->manager->register_from_array( [ 'settings_pages' => [ $page ] ] );

		$this->assertNotContains( self::NS, rest_get_server()->get_namespaces() );
	}

	/**
	 * A page that is not opted in gets 404 while another one is served.
	 */
	public function test_unknown_and_not_opted_in_pages_are_404(): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					Rest_Fixture::page(),
					[
						'id'         => 'private-page',
						'page_title' => 'Private',
						'menu_title' => 'Private',
						'fields'     => [
							[
								'name' => 'x',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$this->assertSame( 200, $this->dispatch( 'GET', '/settings/' . Rest_Fixture::PAGE_ID )->get_status() );
		$this->assertSame( 404, $this->dispatch( 'GET', '/settings/private-page' )->get_status() );
		$this->assertSame( 404, $this->dispatch( 'GET', '/settings/nope' )->get_status() );
	}

	/**
	 * The namespace comes from the boot option.
	 */
	public function test_namespace_comes_from_boot_option(): void {
		$this->reset_manager( [ 'rest_namespace' => 'acme/v2' ] );
		$this->register_fixture();

		$this->assertContains( 'acme/v2', rest_get_server()->get_namespaces() );
		$this->assertNotContains( self::NS, rest_get_server()->get_namespaces() );
		$this->assertSame( 200, $this->dispatch( 'GET', '/settings/' . Rest_Fixture::PAGE_ID, null, 'acme/v2' )->get_status() );
	}

	// ---------------------------------------------------------------------
	// Permissions
	// ---------------------------------------------------------------------

	/**
	 * Anonymous 401, subscriber and editor 403, administrator 200.
	 */
	public function test_permissions(): void {
		$this->register_fixture();
		$path = '/settings/' . Rest_Fixture::PAGE_ID;

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'GET', $path )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'POST', $path, [ 'values' => [] ] )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $this->dispatch( 'GET', $path )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'POST', $path, [ 'values' => [] ] )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertSame( 403, $this->dispatch( 'GET', $path )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( 200, $this->dispatch( 'GET', $path )->get_status() );
	}

	/**
	 * A page with a custom capability uses that capability.
	 */
	public function test_custom_capability(): void {
		$this->register_fixture( [ 'capability' => 'edit_posts' ] );
		$path = '/settings/' . Rest_Fixture::PAGE_ID;

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $this->dispatch( 'GET', $path )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertSame( 200, $this->dispatch( 'GET', $path )->get_status() );
		$this->assertSame( 200, $this->dispatch( 'PATCH', $path, [ 'values' => [ 'title' => 'ok' ] ] )->get_status() );
	}

	/**
	 * OPTIONS serves the schema to permitted users only.
	 */
	public function test_options_serves_schema_by_capability(): void {
		$this->register_fixture();
		$path = '/settings/' . Rest_Fixture::PAGE_ID;

		$data = $this->dispatch( 'OPTIONS', $path )->get_data();
		$this->assertArrayHasKey( 'schema', $data );
		$this->assertArrayHasKey( 'api_key', $data['schema']['properties']['values']['properties'] );

		wp_set_current_user( 0 );
		$data = $this->dispatch( 'OPTIONS', $path )->get_data();
		$this->assertArrayNotHasKey( 'properties', $data['schema'] );
	}

	// ---------------------------------------------------------------------
	// Round trip and parity with the classic save path
	// ---------------------------------------------------------------------

	/**
	 * REST write stores exactly what a classic save stores, and reads back.
	 */
	public function test_round_trip_matches_classic_save(): void {
		$this->register_fixture();
		$handler = $this->manager->get_new_settings_handler();

		// Classic save.
		$_POST = [];
		foreach ( $this->full_post() as $name => $value ) {
			$_POST[ $this->option( $name ) ] = wp_slash( $value );
		}
		foreach ( array_keys( $this->full_post() ) as $name ) {
			$handler->save_single_field( $handler->get_fields( Rest_Fixture::PAGE_ID )[ $name ], Rest_Fixture::PAGE_ID );
		}
		$_POST = [];

		$classic = [];
		foreach ( array_keys( $this->full_post() ) as $name ) {
			$classic[ $name ] = get_option( $this->option( $name ) );
			delete_option( $this->option( $name ) );
		}

		// REST save.
		$response = $this->dispatch( 'POST', '/settings/' . Rest_Fixture::PAGE_ID, [ 'values' => $this->full_values() ] );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		foreach ( $classic as $name => $expected ) {
			if ( 'api_key' === $name ) {
				// Encrypted values carry a random nonce; compare the decrypted secret.
				$this->assertSame( 'sk-live-12345678', $this->manager->get_secret( 'api_key', Rest_Fixture::PAGE_ID ) );
				$this->assertStringStartsWith( 'cassette-cmf-secret:', (string) $expected );
				continue;
			}

			$this->assertSame( $expected, get_option( $this->option( $name ) ), "Stored shape differs for {$name}" );
		}

		// REST read.
		$values = $this->dispatch( 'GET', '/settings/' . Rest_Fixture::PAGE_ID )->get_data()['values'];

		$this->assertSame( 'Hello there', $values['title'] );
		$this->assertSame( 5, $values['count'] );
		$this->assertTrue( $values['enabled'] );
		$this->assertSame( [ 'a', 'c' ], $values['tags'] );
		$this->assertSame( [ 'x', 'z' ], $values['features'] );
		$this->assertSame( '#abc', $values['brand_color'] );
		$this->assertSame( 30, $values['timeout'] );
		$this->assertSame( 'in a tab', $values['tabbed'] );
		$this->assertSame(
			[
				[
					'label'  => 'First',
					'qty'    => 2,
					'active' => true,
				],
				[
					'label'  => 'Second',
					'qty'    => 3,
					'active' => false,
				],
			],
			$values['items']
		);
		$this->assertSame( '••••5678', $values['api_key'] );
		$this->assertStringNotContainsString( 'sk-live-12345678', wp_json_encode( $values ) );
	}

	/**
	 * GET omits write-only, hidden, display-only and container fields.
	 */
	public function test_get_value_set(): void {
		$this->register_fixture();

		$values = $this->dispatch( 'GET', '/settings/' . Rest_Fixture::PAGE_ID )->get_data()['values'];

		foreach ( [ 'legacy_password', 'classic_only', 'intro', 'advanced', 'panel', 'panel_tabs' ] as $absent ) {
			$this->assertArrayNotHasKey( $absent, $values );
		}
		foreach ( [ 'title', 'locked', 'timeout', 'tabbed', 'api_key', 'items' ] as $present ) {
			$this->assertArrayHasKey( $present, $values );
		}

		// Unset values read as the type's empty form.
		$this->assertSame( '', $values['title'] );
		$this->assertNull( $values['count'] );
		$this->assertFalse( $values['enabled'] );
		$this->assertSame( [], $values['tags'] );
		$this->assertSame( [], $values['items'] );
		$this->assertSame( '#112233', $values['brand_color'] );
		$this->assertSame( '', $values['api_key'] );
	}

	/**
	 * The update is partial.
	 */
	public function test_partial_update_leaves_other_fields_alone(): void {
		$this->register_fixture();
		update_option( $this->option( 'bio' ), 'kept' );

		$response = $this->dispatch( 'PATCH', '/settings/' . Rest_Fixture::PAGE_ID, [ 'values' => [ 'title' => 'New' ] ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'New', $response->get_data()['values']['title'] );
		$this->assertSame( 'kept', get_option( $this->option( 'bio' ) ) );
	}

	/**
	 * The before-save filters apply, and null skips the field.
	 */
	public function test_before_save_filters_apply(): void {
		$this->register_fixture();

		add_filter(
			'cassette_cmf_before_save_field_title',
			static function ( $value ) {
				return strtoupper( $value );
			}
		);
		add_filter(
			'cassette_cmf_before_save_field',
			static function ( $value, $field_name ) {
				return 'bio' === $field_name ? null : $value;
			},
			10,
			2
		);

		$this->dispatch(
			'POST',
			'/settings/' . Rest_Fixture::PAGE_ID,
			[
				'values' => [
					'title' => 'abc',
					'bio'   => 'skipped',
				],
			]
		);

		$this->assertSame( 'ABC', get_option( $this->option( 'title' ) ) );
		$this->assertFalse( get_option( $this->option( 'bio' ) ) );
	}

	/**
	 * Sanitizers are idempotent, so the registered sanitize_callback running
	 * again inside update_option() cannot change a stored value.
	 */
	public function test_sanitize_is_idempotent_per_type(): void {
		$this->register_fixture();
		$fields = $this->manager->get_new_settings_handler()->get_fields( Rest_Fixture::PAGE_ID );
		$raw    = $this->full_post();

		foreach ( $raw as $name => $value ) {
			$once  = $fields[ $name ]->sanitize( $value );
			$twice = $fields[ $name ]->sanitize( $once );
			$this->assertEquals( $once, $twice, "sanitize() is not idempotent for {$name}" );
		}
	}

	/**
	 * Writing through the registered settings (admin_init fired) stores the same.
	 */
	public function test_double_sanitize_through_registered_settings_is_harmless(): void {
		$this->register_fixture();
		$this->manager->get_new_settings_handler()->register_settings();

		$response = $this->dispatch( 'POST', '/settings/' . Rest_Fixture::PAGE_ID, [ 'values' => $this->full_values() ] );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$fields = $this->manager->get_new_settings_handler()->get_fields( Rest_Fixture::PAGE_ID );
		foreach ( [ 'title', 'count', 'tags', 'items', 'notes', 'enabled', 'features' ] as $name ) {
			$stored = get_option( $this->option( $name ) );
			$this->assertEquals( $stored, $fields[ $name ]->sanitize( $stored ), "Re-sanitizing changed {$name}" );
		}
	}

	// ---------------------------------------------------------------------
	// Invalid input
	// ---------------------------------------------------------------------

	/**
	 * Invalid fields give per-field errors and nothing is written.
	 */
	public function test_invalid_input_writes_nothing(): void {
		$this->register_fixture();
		update_option( $this->option( 'title' ), 'before' );

		$response = $this->dispatch(
			'POST',
			'/settings/' . Rest_Fixture::PAGE_ID,
			[
				'values' => [
					'title'       => 'would be saved',
					'count'       => 99,
					'launch_date' => '2026-13-45',
					'mode'        => 'zzz',
					'enabled'     => 'maybe',
				],
			]
		);

		$this->assertSame( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( 'rest_invalid_param', $data['code'] );
		foreach ( [ 'count', 'launch_date', 'mode', 'enabled' ] as $name ) {
			$this->assertArrayHasKey( $name, $data['data']['params'], $name );
			$this->assertArrayHasKey( $name, $data['data']['details'], $name );
			$this->assertSame( 'rest_invalid_param', $data['data']['details'][ $name ]['code'] );
		}
		$this->assertArrayNotHasKey( 'title', $data['data']['params'] );

		$this->assertSame( 'before', get_option( $this->option( 'title' ) ) );
		$this->assertFalse( get_option( $this->option( 'count' ) ) );
	}

	/**
	 * Required fields are checked when they are submitted.
	 */
	public function test_required_field_is_enforced_when_submitted(): void {
		$this->register_fixture();

		$response = $this->dispatch( 'POST', '/settings/' . Rest_Fixture::PAGE_ID, [ 'values' => [ 'level' => '' ] ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayHasKey( 'level', $response->get_data()['data']['params'] );
	}

	/**
	 * Non-object `values` is rejected.
	 */
	public function test_values_must_be_an_object(): void {
		$this->register_fixture();

		$response = $this->dispatch( 'POST', '/settings/' . Rest_Fixture::PAGE_ID, [ 'values' => [ 'a', 'b' ] ] );
		$this->assertSame( 400, $response->get_status() );

		$response = $this->dispatch( 'POST', '/settings/' . Rest_Fixture::PAGE_ID, [] );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * A conditional field is only validated when its condition is met.
	 */
	public function test_conditional_field_validation(): void {
		$page           = Rest_Fixture::page();
		$page['fields'] = [
			[
				'name'  => 'use_proxy',
				'type'  => 'checkbox',
				'label' => 'Use proxy',
			],
			[
				'name'        => 'proxy_url',
				'type'        => 'url',
				'label'       => 'Proxy',
				'required'    => true,
				'conditional' => [
					'field'    => 'use_proxy',
					'operator' => '==',
					'value'    => '1',
				],
			],
		];
		$this->manager->register_from_array( [ 'settings_pages' => [ $page ] ] );
		$path = '/settings/' . Rest_Fixture::PAGE_ID;

		$off = $this->dispatch( 'POST', $path, [ 'values' => [ 'proxy_url' => '' ] ] );
		$this->assertSame( 200, $off->get_status() );

		$on = $this->dispatch(
			'POST',
			$path,
			[
				'values' => [
					'use_proxy' => true,
					'proxy_url' => '',
				],
			]
		);
		$this->assertSame( 400, $on->get_status() );
	}

	// ---------------------------------------------------------------------
	// Field-level show_in_rest, read-only
	// ---------------------------------------------------------------------

	/**
	 * `show_in_rest: false` fields and read-only fields are not writable over REST.
	 */
	public function test_hidden_and_readonly_fields_are_rejected_on_write(): void {
		$this->register_fixture();

		$response = $this->dispatch(
			'POST',
			'/settings/' . Rest_Fixture::PAGE_ID,
			[
				'values' => [
					'classic_only' => 'x',
					'locked'       => 'y',
					'unknown'      => 'z',
					'title'        => 'fine',
				],
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$params = $response->get_data()['data']['params'];
		$this->assertSame( [ 'classic_only', 'locked', 'unknown' ], array_keys( $params ) );
		$this->assertFalse( get_option( $this->option( 'title' ) ) );
	}

	/**
	 * A `show_in_rest: false` field still saves through the classic path.
	 */
	public function test_hidden_field_still_saves_classically(): void {
		$this->register_fixture();
		$handler = $this->manager->get_new_settings_handler();

		$_POST[ $this->option( 'classic_only' ) ] = 'classic';
		$handler->save_single_field( $handler->get_fields( Rest_Fixture::PAGE_ID )['classic_only'], Rest_Fixture::PAGE_ID );
		$_POST = [];

		$this->assertSame( 'classic', get_option( $this->option( 'classic_only' ) ) );

		$properties = Settings_Schema::build(
			Settings_Schema::collect_fields( $handler->get_top_level_fields( Rest_Fixture::PAGE_ID ) )
		)['properties']['values']['properties'];
		$this->assertArrayNotHasKey( 'classic_only', $properties );
	}

	/**
	 * Password: never returned, empty keeps the stored value, non-empty replaces it.
	 */
	public function test_password_is_write_only(): void {
		$this->register_fixture();
		$path = '/settings/' . Rest_Fixture::PAGE_ID;

		$this->dispatch( 'POST', $path, [ 'values' => [ 'legacy_password' => 'hunter2' ] ] );
		$this->assertSame( 'hunter2', get_option( $this->option( 'legacy_password' ) ) );

		$this->dispatch( 'POST', $path, [ 'values' => [ 'legacy_password' => '' ] ] );
		$this->assertSame( 'hunter2', get_option( $this->option( 'legacy_password' ) ) );

		$this->assertStringNotContainsString( 'hunter2', wp_json_encode( $this->dispatch( 'GET', $path )->get_data() ) );
	}

	// ---------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------

	/**
	 * The generated schema of the fixture page matches the snapshot.
	 *
	 * Regenerate with UPDATE_SNAPSHOT=1.
	 */
	public function test_schema_snapshot(): void {
		$this->register_fixture();

		$handler = $this->manager->get_new_settings_handler();
		$schema  = Settings_Schema::build(
			Settings_Schema::collect_fields( $handler->get_top_level_fields( Rest_Fixture::PAGE_ID ) ),
			Rest_Fixture::PAGE_ID
		);
		$actual  = json_decode( wp_json_encode( $schema ), true );
		$file    = dirname( __DIR__ ) . '/fixtures/rest-settings-schema.json';

		if ( getenv( 'UPDATE_SNAPSHOT' ) ) {
			file_put_contents( $file, wp_json_encode( $actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$this->assertFileExists( $file );
		$this->assertSame( json_decode( (string) file_get_contents( $file ), true ), $actual ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Key keywords of the schema, independent of the snapshot.
	 */
	public function test_schema_keywords(): void {
		$this->register_fixture();

		$handler    = $this->manager->get_new_settings_handler();
		$properties = Settings_Schema::build(
			Settings_Schema::collect_fields( $handler->get_top_level_fields( Rest_Fixture::PAGE_ID ) )
		)['properties']['values']['properties'];

		$this->assertSame( 'email', $properties['contact_email']['format'] );
		$this->assertSame( 'uri', $properties['site_url']['format'] );
		$this->assertSame( 'date', $properties['launch_date']['format'] );
		$this->assertSame( 'hex-color', $properties['brand_color']['format'] );
		$this->assertSame( 40, $properties['title']['maxLength'] );
		$this->assertSame( 1, $properties['count']['minimum'] );
		$this->assertSame( 10, $properties['count']['maximum'] );
		$this->assertSame( 1, $properties['count']['multipleOf'] );
		$this->assertTrue( $properties['count']['required'] );
		$this->assertSame( [ '', 'a', 'b', 'c' ], $properties['mode']['enum'] );
		$this->assertSame( [ 'low', 'high' ], $properties['level']['enum'] );
		$this->assertSame( 'array', $properties['tags']['type'] );
		$this->assertSame( [ 'a', 'b', 'c' ], $properties['tags']['items']['enum'] );
		$this->assertSame( 'boolean', $properties['enabled']['type'] );
		$this->assertSame( 'array', $properties['features']['type'] );
		$this->assertSame( 'array', $properties['items']['type'] );
		$this->assertSame( 5, $properties['items']['maxItems'] );
		$this->assertSame( 'object', $properties['items']['items']['type'] );
		$this->assertSame( 'boolean', $properties['items']['items']['properties']['active']['type'] );
		$this->assertTrue( $properties['locked']['readOnly'] );
		$this->assertTrue( $properties['legacy_password']['writeOnly'] );
		$this->assertContains( 'object', $properties['api_key']['type'] );
	}
}
