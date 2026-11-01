<?php
/**
 * Settings schema version + upgrade tests
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Settings\Settings_Upgrader;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Settings_Upgrade
 */
class Test_Settings_Upgrade extends CassetteCMF_UnitTestCase {

	private const PAGE = 'upg-page';

	/**
	 * Manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Calls the upgrade callback received.
	 *
	 * @var array<int, array{0: array, 1: int}>
	 */
	private array $calls = [];

	/**
	 * Previous error_log target.
	 *
	 * @var string|false
	 */
	private $previous_error_log = false;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();

		// The upgrader logs failures under WP_DEBUG; keep the test output clean.
		$this->previous_error_log = ini_set( 'error_log', '/dev/null' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		Field_Factory::reset();

		$this->manager             = Manager::init();
		$this->calls               = [];
		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		ini_set( 'error_log', (string) $this->previous_error_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		parent::tear_down();
	}

	/**
	 * Register the page.
	 *
	 * @param array<string, mixed> $page Page overrides.
	 */
	private function register( array $page = [] ): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					array_merge(
						[
							'id'         => self::PAGE,
							'page_title' => 'Upgrade',
							'menu_title' => 'Upgrade',
							'fields'     => [
								[
									'name' => 'name',
									'type' => 'text',
								],
								[
									'name' => 'old_color',
									'type' => 'text',
								],
								[
									'name' => 'color',
									'type' => 'color',
								],
							],
						],
						$page
					),
				],
			]
		);
	}

	/**
	 * A callback that renames old_color to color and records its calls.
	 */
	private function renamer(): callable {
		return function ( array $old, int $from ): array {
			$this->calls[] = [ $old, $from ];

			return [
				'color'     => $old['old_color'] ?? '',
				'old_color' => '',
			];
		};
	}

	/**
	 * Run the admin_init hook callback.
	 */
	private function admin_init(): void {
		$this->manager->get_new_settings_handler()->run_schema_upgrades();
	}

	/**
	 * Pages without the keys never write an option.
	 */
	public function test_nothing_happens_without_the_keys(): void {
		$this->register();
		update_option( self::PAGE . '_name', 'x' );

		$this->admin_init();
		$this->admin_init();

		$this->assertFalse( get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
		$this->assertFalse( get_option( Settings_Upgrader::lock_option( self::PAGE ) ) );
		$this->assertFalse( get_option( Settings_Upgrader::error_option( self::PAGE ) ) );
		$this->assertSame( 'x', get_option( self::PAGE . '_name' ) );
	}

	/**
	 * The upgrade runs on admin_init (priority 5, before the settings are registered).
	 */
	public function test_hooked_to_admin_init(): void {
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_old_color', '#123456' );

		$handler = $this->manager->get_new_settings_handler();
		$this->assertSame( 5, has_action( 'admin_init', [ $handler, 'run_schema_upgrades' ] ) );

		// Firing the real admin_init also runs core callbacks that send headers.
		$handler->run_schema_upgrades();

		$this->assertSame( '#123456', get_option( self::PAGE . '_color' ) );
		$this->assertSame( 2, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * Existing data without a stored version is version 1; the callback runs once.
	 */
	public function test_runs_once_from_implicit_version_one(): void {
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_old_color', '#abcdef' );
		update_option( self::PAGE . '_name', 'kept' );

		$this->admin_init();

		$this->assertCount( 1, $this->calls );
		$this->assertSame( 1, $this->calls[0][1] );
		$this->assertEqualsCanonicalizing(
			[
				'old_color' => '#abcdef',
				'name'      => 'kept',
			],
			$this->calls[0][0]
		);
		$this->assertCount( 2, $this->calls[0][0] );
		$this->assertSame( '#abcdef', get_option( self::PAGE . '_color' ) );
		$this->assertSame( '', get_option( self::PAGE . '_old_color' ) );
		$this->assertSame( 'kept', get_option( self::PAGE . '_name' ), 'Fields not returned are untouched.' );
		$this->assertSame( 2, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
		$this->assertFalse( get_option( Settings_Upgrader::lock_option( self::PAGE ) ), 'Lock released.' );

		// A second request (new upgrader state) does not run it again.
		$reflection = new ReflectionProperty( $this->manager->get_new_settings_handler(), 'upgrader' );
		$reflection->setAccessible( true );
		$reflection->setValue( $this->manager->get_new_settings_handler(), null );

		$this->admin_init();
		$this->assertCount( 1, $this->calls );
	}

	/**
	 * A stored version below the declared one is passed as $from.
	 */
	public function test_upgrades_from_the_stored_version(): void {
		$this->register(
			[
				'schema_version' => 4,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_name', 'x' );
		update_option( Settings_Upgrader::version_option( self::PAGE ), 3 );

		$this->admin_init();

		$this->assertSame( 3, $this->calls[0][1] );
		$this->assertSame( 4, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * A brand-new install writes the version without calling the callback.
	 */
	public function test_new_install_only_stores_the_version(): void {
		$this->register(
			[
				'schema_version' => 3,
				'upgrade'        => $this->renamer(),
			]
		);

		$this->admin_init();

		$this->assertSame( [], $this->calls );
		$this->assertSame( 3, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * Version 1 data on a page declaring version 1 just records it.
	 */
	public function test_declared_version_one_records_without_calling(): void {
		$this->register(
			[
				'schema_version' => 1,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_name', 'x' );

		$this->admin_init();

		$this->assertSame( [], $this->calls );
		$this->assertSame( 1, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * A version without a callback only records the version.
	 */
	public function test_schema_version_without_callback(): void {
		$this->register( [ 'schema_version' => 2 ] );
		update_option( self::PAGE . '_name', 'x' );

		$this->admin_init();

		$this->assertSame( 2, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
		$this->assertSame( 'x', get_option( self::PAGE . '_name' ) );
	}

	/**
	 * A held lock prevents a second run; an expired lock is taken over.
	 */
	public function test_lock_prevents_double_run_and_expires(): void {
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_old_color', '#111111' );

		// Another request holds the lock.
		add_option( Settings_Upgrader::lock_option( self::PAGE ), (string) ( time() + 60 ), '', false );

		$upgrader = $this->manager->get_new_settings_handler()->get_upgrader();
		$this->assertSame( Settings_Upgrader::STATUS_LOCKED, $upgrader->maybe_upgrade( self::PAGE ) );
		$this->assertSame( [], $this->calls );
		$this->assertFalse( get_option( Settings_Upgrader::version_option( self::PAGE ) ) );

		// The lock expires.
		update_option( Settings_Upgrader::lock_option( self::PAGE ), (string) ( time() - 5 ), false );

		$this->assertSame( Settings_Upgrader::STATUS_UPGRADED, $upgrader->maybe_upgrade( self::PAGE ) );
		$this->assertCount( 1, $this->calls );
		$this->assertFalse( get_option( Settings_Upgrader::lock_option( self::PAGE ) ) );
	}

	/**
	 * The lock cannot be taken twice.
	 */
	public function test_lock_is_exclusive(): void {
		global $wpdb;

		$this->register( [ 'schema_version' => 2 ] );
		$name = Settings_Upgrader::lock_option( self::PAGE );

		$sql = $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) ( time() + 60 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( 1, $wpdb->query( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertSame( 0, $wpdb->query( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * An exception keeps data and version, fires the action, notices, logs nothing else.
	 */
	public function test_exception_keeps_data_and_version(): void {
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => static function () {
					throw new RuntimeException( 'boom' );
				},
			]
		);
		update_option( self::PAGE . '_old_color', '#222222' );

		$seen = [];
		add_action(
			'cassette_cmf_schema_upgrade_failed',
			static function ( $page, $from, $to, $error ) use ( &$seen ) {
				$seen = [ $page, $from, $to, $error->getMessage() ];
			},
			10,
			4
		);

		$this->admin_init();

		$this->assertSame( [ self::PAGE, 1, 2, 'boom' ], $seen );
		$this->assertSame( '#222222', get_option( self::PAGE . '_old_color' ) );
		$this->assertFalse( get_option( self::PAGE . '_color' ) );
		$this->assertFalse( get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
		$this->assertFalse( get_option( Settings_Upgrader::lock_option( self::PAGE ) ), 'Lock released after a failure.' );

		ob_start();
		$this->manager->get_new_settings_handler()->get_upgrader()->print_failure_notices();
		$notice = (string) ob_get_clean();
		$this->assertStringContainsString( 'could not upgrade', $notice );
		$this->assertStringContainsString( 'boom', $notice );

		// Users without the page capability see nothing.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		ob_start();
		$this->manager->get_new_settings_handler()->get_upgrader()->print_failure_notices();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * A non-array return value is a failure and writes nothing.
	 */
	public function test_non_array_return_is_a_failure(): void {
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => static function () {
					return 'nope';
				},
			]
		);
		update_option( self::PAGE . '_old_color', '#333333' );

		$fired = 0;
		add_action(
			'cassette_cmf_schema_upgrade_failed',
			static function () use ( &$fired ) {
				++$fired;
			}
		);

		$this->admin_init();

		$this->assertSame( 1, $fired );
		$this->assertSame( '#333333', get_option( self::PAGE . '_old_color' ) );
		$this->assertFalse( get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * A later successful run clears the failure notice.
	 */
	public function test_success_clears_the_recorded_failure(): void {
		$fail = true;
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => static function ( array $old ) use ( &$fail ) {
					if ( $fail ) {
						throw new RuntimeException( 'later fixed' );
					}

					return $old;
				},
			]
		);
		update_option( self::PAGE . '_name', 'x' );

		$this->admin_init();
		$this->assertIsArray( get_option( Settings_Upgrader::error_option( self::PAGE ) ) );

		$fail       = false;
		$reflection = new ReflectionProperty( $this->manager->get_new_settings_handler(), 'upgrader' );
		$reflection->setAccessible( true );
		$reflection->setValue( $this->manager->get_new_settings_handler(), null );

		$this->admin_init();
		$this->assertFalse( get_option( Settings_Upgrader::error_option( self::PAGE ) ) );
		$this->assertSame( 2, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * Returned values go through the field sanitizer; unknown keys are ignored.
	 */
	public function test_returned_values_are_sanitized(): void {
		$this->register(
			[
				'schema_version' => 2,
				'upgrade'        => static function () {
					return [
						'name'        => '<b>Bold</b> name',
						'color'       => 'not-a-color',
						'not_a_field' => 'ignored',
					];
				},
			]
		);
		update_option( self::PAGE . '_name', 'x' );

		$this->admin_init();

		$this->assertSame( 'Bold name', get_option( self::PAGE . '_name' ) );
		$this->assertSame( '#000000', get_option( self::PAGE . '_color' ) );
		$this->assertFalse( get_option( self::PAGE . '_not_a_field' ) );
	}

	/**
	 * REST read and write trigger the upgrade first.
	 */
	public function test_rest_triggers_the_upgrade(): void {
		$this->register(
			[
				'schema_version' => 2,
				'show_in_rest'   => true,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_old_color', '#654321' );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/cassette-cmf/v1/settings/' . self::PAGE ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '#654321', $response->get_data()['values']['color'] );
		$this->assertCount( 1, $this->calls );
		$this->assertSame( 2, get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}

	/**
	 * A write also upgrades before applying, and a held lock gives 503.
	 */
	public function test_rest_write_upgrades_and_respects_the_lock(): void {
		$this->register(
			[
				'schema_version' => 2,
				'show_in_rest'   => true,
				'upgrade'        => $this->renamer(),
			]
		);
		update_option( self::PAGE . '_old_color', '#654321' );
		add_option( Settings_Upgrader::lock_option( self::PAGE ), (string) ( time() + 60 ), '', false );

		$request = new WP_REST_Request( 'POST', '/cassette-cmf/v1/settings/' . self::PAGE );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'values' => [ 'name' => 'n' ] ] ) );

		$this->assertSame( 503, rest_get_server()->dispatch( $request )->get_status() );
		$this->assertFalse( get_option( self::PAGE . '_name' ) );

		delete_option( Settings_Upgrader::lock_option( self::PAGE ) );

		// Fresh upgrader state, as in a new request.
		$reflection = new ReflectionProperty( $this->manager->get_new_settings_handler(), 'upgrader' );
		$reflection->setAccessible( true );
		$reflection->setValue( $this->manager->get_new_settings_handler(), null );

		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		$this->assertSame( 'n', get_option( self::PAGE . '_name' ) );
		$this->assertSame( '#654321', get_option( self::PAGE . '_color' ) );
	}

	/**
	 * An invalid schema_version is ignored with a notice.
	 */
	public function test_invalid_schema_version_is_ignored(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMF\Settings\Settings_Page::get_schema_version' );

		$this->register( [ 'schema_version' => 0 ] );
		update_option( self::PAGE . '_name', 'x' );
		$this->admin_init();

		$this->assertFalse( get_option( Settings_Upgrader::version_option( self::PAGE ) ) );
	}
}
