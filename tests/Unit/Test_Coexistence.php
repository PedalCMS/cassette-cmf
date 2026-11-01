<?php
/**
 * Coexistence tests (boot options and two loaded copies)
 *
 * Loads a Strauss-style namespace-prefixed copy of src/ next to the plain
 * library and checks that the two never collide.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\CassetteCMF;
use PedalCMS\CassetteCMF\Core\Manager;

require_once dirname( __DIR__ ) . '/Support/class-prefixed-copy.php';

/**
 * Class Test_Coexistence
 */
class Test_Coexistence extends WP_UnitTestCase {

	private const COPY_NS = 'CopyB\\Vendor\\PedalCMS\\CassetteCMF';

	/**
	 * Absolute path of the generated copy of src/.
	 *
	 * @var string
	 */
	private static $copy_dir = '';

	/**
	 * Fully qualified Manager class of the prefixed copy.
	 *
	 * @var string
	 */
	private $copy_manager = self::COPY_NS . '\\Core\\Manager';

	/**
	 * Generate the prefixed copy once and register its autoloader.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$copy_dir = rtrim( sys_get_temp_dir(), '/' ) . '/cassette-cmf-copy-b-' . getmypid();

		Prefixed_Copy::generate(
			dirname( __DIR__, 2 ) . '/src',
			self::$copy_dir . '/src',
			[ 'PedalCMS\\CassetteCMF' => self::COPY_NS ]
		);
		Prefixed_Copy::autoload( self::$copy_dir . '/src' );
	}

	/**
	 * Remove the generated copy.
	 */
	public static function tear_down_after_class(): void {
		Prefixed_Copy::remove( self::$copy_dir );
		parent::tear_down_after_class();
	}

	/**
	 * Reset both singletons around each test.
	 */
	public function set_up(): void {
		parent::set_up();

		Manager::reset();
		( $this->copy_manager )::reset();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Reset both singletons and clean the queues.
	 */
	public function tear_down(): void {
		Manager::reset();
		( $this->copy_manager )::reset();

		parent::tear_down();
	}

	/**
	 * Without boot() every identifier keeps its default.
	 */
	public function test_defaults_without_boot(): void {
		Manager::init();

		$this->assertSame( 'cassette-cmf', Manager::asset_handle() );
		$this->assertSame( 'CassetteCMFFields', Manager::js_global() );
		$this->assertSame( 'cassette-cmf/v1', Manager::rest_namespace() );
	}

	/**
	 * Boot stores valid options.
	 */
	public function test_boot_stores_options(): void {
		$manager = CassetteCMF::boot(
			[
				'asset_handle'   => 'acme-cmf',
				'js_global'      => 'AcmeFields',
				'rest_namespace' => 'acme-cmf/v2',
			]
		);

		$this->assertSame( Manager::init(), $manager );
		$this->assertSame( 'acme-cmf', Manager::asset_handle() );
		$this->assertSame( 'AcmeFields', Manager::js_global() );
		$this->assertSame( 'acme-cmf/v2', Manager::rest_namespace() );
	}

	/**
	 * Invalid values raise _doing_it_wrong() and fall back to the defaults.
	 */
	public function test_invalid_values_fall_back_to_defaults(): void {
		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMF\\Core\\Manager::resolve_boot_options' );

		CassetteCMF::boot(
			[
				'asset_handle'   => 'Bad Handle!',
				'js_global'      => '1bad',
				'rest_namespace' => 'no-version',
				'surprise'       => true,
			]
		);

		$this->assertSame( 'cassette-cmf', Manager::asset_handle() );
		$this->assertSame( 'CassetteCMFFields', Manager::js_global() );
		$this->assertSame( 'cassette-cmf/v1', Manager::rest_namespace() );
	}

	/**
	 * Options apply on the first call only; a conflicting second call is flagged.
	 */
	public function test_second_boot_with_different_options_is_flagged(): void {
		CassetteCMF::boot( [ 'asset_handle' => 'first-cmf' ] );

		// An identical call is silent.
		CassetteCMF::boot( [ 'asset_handle' => 'first-cmf' ] );

		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMF\\Core\\Manager::boot' );
		CassetteCMF::boot( [ 'asset_handle' => 'second-cmf' ] );

		$this->assertSame( 'first-cmf', Manager::asset_handle() );
	}

	/**
	 * Booting after init() with non-default options is flagged.
	 */
	public function test_boot_after_init_with_options_is_flagged(): void {
		Manager::init();

		$this->setExpectedIncorrectUsage( 'PedalCMS\\CassetteCMF\\Core\\Manager::boot' );
		CassetteCMF::boot( [ 'asset_handle' => 'late-cmf' ] );

		$this->assertSame( 'cassette-cmf', Manager::asset_handle() );
	}

	/**
	 * A non-default js_global adds a one-shot inline script, the default adds none.
	 */
	public function test_js_global_inline_script_only_when_non_default(): void {
		CassetteCMF::boot();
		wp_register_script( 'cmf-default-probe', 'https://example.test/a.js', [], '1', true );
		Manager::add_js_global_script( 'cmf-default-probe' );
		$this->assertEmpty( wp_scripts()->get_data( 'cmf-default-probe', 'before' ) );

		Manager::reset();
		CassetteCMF::boot( [ 'js_global' => 'AcmeFields' ] );
		wp_register_script( 'cmf-custom-probe', 'https://example.test/b.js', [], '1', true );
		Manager::add_js_global_script( 'cmf-custom-probe' );

		$before = implode( "\n", (array) wp_scripts()->get_data( 'cmf-custom-probe', 'before' ) );
		$this->assertStringContainsString( 'window.cassetteCmfFieldsBoot = "AcmeFields";', $before );
	}

	/**
	 * The shipped JS reads and deletes the hand-over property and falls back to the default name.
	 */
	public function test_js_file_publishes_under_the_handed_over_name(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/assets/js/cassette-cmf.js' );

		$this->assertStringContainsString( "let globalName = 'CassetteCMFFields';", $js );
		$this->assertStringContainsString( 'delete window.cassetteCmfFieldsBoot', $js );
		$this->assertStringContainsString( 'window[globalName] = CassetteCMFFields;', $js );
	}

	/**
	 * Two copies, one prefixed, side by side.
	 */
	public function test_two_copies_do_not_collide(): void {
		$copy_class   = self::COPY_NS . '\\CassetteCMF';
		$copy_manager = $this->copy_manager;

		// Copy B: boot with its own identifiers, then register a settings page.
		$copy_b = $copy_class::boot(
			[
				'asset_handle' => 'copyb-cmf',
				'js_global'    => 'CopyBFields',
			]
		);
		$this->assertInstanceOf( $copy_manager, $copy_b );
		$this->assertSame( 'copyb-cmf', $copy_manager::asset_handle() );

		// Copy A: the plain library on defaults.
		$copy_a = CassetteCMF::init();
		$this->assertInstanceOf( Manager::class, $copy_a );
		$this->assertNotSame( get_class( $copy_a ), get_class( $copy_b ) );
		$this->assertSame( 'cassette-cmf', Manager::asset_handle() );

		$copy_a->register_from_array( $this->page_config( 'copy_a_page' ) );
		$copy_b->register_from_array( $this->page_config( 'copy_b_page' ) );

		$this->assertTrue( $copy_a->get_new_settings_handler()->has_page( 'copy_a_page' ) );
		$this->assertFalse( $copy_a->get_new_settings_handler()->has_page( 'copy_b_page' ) );
		$this->assertTrue( $copy_b->get_new_settings_handler()->has_page( 'copy_b_page' ) );
		$this->assertFalse( $copy_b->get_new_settings_handler()->has_page( 'copy_a_page' ) );

		// Fire the admin_menu hook and run each copy's admin_init callback
		// (firing admin_init itself would also run core callbacks that send
		// headers, which the test runner has already sent). Neither may fatal.
		do_action( 'admin_menu' );
		$copy_a->get_new_settings_handler()->register_settings();
		$copy_b->get_new_settings_handler()->register_settings();
		$this->assertTrue( has_action( 'admin_init', [ $copy_b->get_new_settings_handler(), 'register_settings' ] ) > 0 );
		$this->assertTrue( has_action( 'admin_init', [ $copy_a->get_new_settings_handler(), 'register_settings' ] ) > 0 );

		$suffix_a = $copy_a->get_new_settings_handler()->get_page( 'copy_a_page' )->get_hook_suffix();
		$suffix_b = $copy_b->get_new_settings_handler()->get_page( 'copy_b_page' )->get_hook_suffix();
		$this->assertNotEmpty( $suffix_a );
		$this->assertNotEmpty( $suffix_b );

		// Page A: only the default handle is enqueued.
		$this->reset_queues();
		set_current_screen( $suffix_a );
		do_action( 'admin_enqueue_scripts' );

		$this->assertContains( 'cassette-cmf', wp_scripts()->queue );
		$this->assertNotContains( 'copyb-cmf', wp_scripts()->queue );
		$this->assertContains( 'cassette-cmf', wp_styles()->queue );
		$this->assertNotContains( 'copyb-cmf', wp_styles()->queue );

		$src_a = wp_scripts()->registered['cassette-cmf']->src;
		$this->assertStringNotContainsString( 'cassette-cmf-copy-b', $src_a );
		$this->assertStringEndsWith( 'assets/js/cassette-cmf.js', $src_a );
		$this->assertEmpty( wp_scripts()->get_data( 'cassette-cmf', 'before' ), 'The default copy emits no extra inline script.' );

		// Page B: only copy B's handle, from copy B's own directory, naming its global.
		$this->reset_queues();
		set_current_screen( $suffix_b );
		do_action( 'admin_enqueue_scripts' );

		$this->assertContains( 'copyb-cmf', wp_scripts()->queue );
		$this->assertNotContains( 'cassette-cmf', wp_scripts()->queue );
		$this->assertContains( 'copyb-cmf', wp_styles()->queue );

		$src_b = wp_scripts()->registered['copyb-cmf']->src;
		$this->assertStringContainsString( 'cassette-cmf-copy-b', $src_b );
		$this->assertStringEndsWith( 'assets/js/cassette-cmf.js', $src_b );
		$this->assertNotSame( $src_a, $src_b );

		$before = implode( "\n", (array) wp_scripts()->get_data( 'copyb-cmf', 'before' ) );
		$this->assertStringContainsString( 'window.cassetteCmfFieldsBoot = "CopyBFields";', $before );
	}

	/**
	 * A minimal settings-page config with one text field.
	 *
	 * @param string $id Page id.
	 * @return array<string, mixed>
	 */
	private function page_config( string $id ): array {
		return [
			'settings_pages' => [
				[
					'id'         => $id,
					'page_title' => 'Page ' . $id,
					'menu_title' => 'Page ' . $id,
					'capability' => 'manage_options',
					'fields'     => [
						[
							'name'  => 'title_' . $id,
							'type'  => 'text',
							'label' => 'Title',
						],
					],
				],
			],
		];
	}

	/**
	 * Empty the script and style queues.
	 *
	 * @return void
	 */
	private function reset_queues(): void {
		wp_scripts()->queue = [];
		wp_scripts()->done  = [];
		wp_styles()->queue  = [];
		wp_styles()->done   = [];
	}
}
