<?php
/**
 * React settings renderer tests
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Json\Schema_Validator;
use PedalCMS\CassetteCMF\Settings\React_Renderer;
use PedalCMS\CassetteCMF\Settings\Settings_Page;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';
require_once dirname( __DIR__ ) . '/Support/rest-fixture.php';

/**
 * Class Test_React_Renderer
 */
class Test_React_Renderer extends CassetteCMF_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Handler.
	 *
	 * @var New_Settings_Page_Handler
	 */
	private New_Settings_Page_Handler $handler;

	/**
	 * Set up: fresh manager, an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		Field_Factory::reset();

		$this->manager             = Manager::init();
		$this->handler             = $this->manager->get_new_settings_handler();
		$GLOBALS['wp_rest_server'] = null;
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Fresh script and style registries per test.
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Register a React page and a classic page, then the admin pages.
	 *
	 * @param array<string, mixed> $react_overrides Overrides for the React page.
	 */
	private function boot_pages( array $react_overrides = [] ): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					Rest_Fixture::page( array_merge( [ 'render_mode' => 'react' ], $react_overrides ) ),
					[
						'id'         => 'classic-page',
						'page_title' => 'Classic',
						'menu_title' => 'Classic',
						'fields'     => [
							[
								'name'  => 'plain',
								'type'  => 'text',
								'label' => 'Plain',
							],
						],
					],
				],
			]
		);

		$this->handler->register_pages();
	}

	/**
	 * Hook suffix of a page.
	 *
	 * @param string $page_id Page ID.
	 */
	private function hook_of( string $page_id ): string {
		return (string) $this->handler->get_page( $page_id )->get_hook_suffix();
	}

	/**
	 * Run the handler's enqueue step on a screen.
	 *
	 * @param string $screen Screen ID.
	 */
	private function enqueue_on( string $screen ): void {
		wp_scripts()->queue = [];
		wp_styles()->queue  = [];
		set_current_screen( $screen );
		$this->handler->enqueue_assets();
	}

	/**
	 * The app's script is enqueued on its own page only, with the asset file's deps.
	 */
	public function test_enqueues_only_on_its_page(): void {
		$this->boot_pages();
		$handle = React_Renderer::handle();

		$this->assertSame( 'cassette-cmf-settings-app', $handle );

		$this->enqueue_on( $this->hook_of( 'rest-fixture' ) );
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
		$this->assertTrue( wp_style_is( $handle, 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'cassette-cmf', 'enqueued' ), 'No classic script on a React page.' );

		$asset = require React_Renderer::build_path() . '/settings-app.asset.php';
		$this->assertSame( $asset['version'], wp_scripts()->registered[ $handle ]->ver );
		$this->assertSame( $asset['dependencies'], wp_scripts()->registered[ $handle ]->deps );
		$this->assertSame( 'replace', wp_styles()->get_data( $handle, 'rtl' ) );
		$this->assertContains( 'wp-components', wp_styles()->registered[ $handle ]->deps );

		$this->enqueue_on( $this->hook_of( 'classic-page' ) );
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'cassette-cmf', 'enqueued' ) );

		$this->enqueue_on( 'dashboard' );
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
	}

	/**
	 * The page config reaches the script through an inline object.
	 */
	public function test_inline_config_object(): void {
		update_option( 'rest-fixture_title', 'Stored title' );
		$this->boot_pages();
		$this->enqueue_on( $this->hook_of( 'rest-fixture' ) );

		$before = (array) wp_scripts()->get_data( React_Renderer::handle(), 'before' );
		$inline = implode( "\n", $before );

		$this->assertStringContainsString( 'window.CassetteCMFFieldsSettingsApp = ', $inline );

		$json   = substr( $inline, strpos( $inline, '= ' ) + 2 );
		$config = json_decode( rtrim( trim( $json ), ';' ), true );

		$this->assertIsArray( $config, json_last_error_msg() . substr( $json, -200 ) );

		$this->assertSame( 'rest-fixture', $config['pageId'] );
		$this->assertSame( '/cassette-cmf/v1/settings/rest-fixture', $config['restPath'] );
		$this->assertSame( 'Stored title', $config['values']['title'] );
		$this->assertNotEmpty( $config['fields'] );
	}

	/**
	 * A non-default boot option names the global differently.
	 */
	public function test_js_global_follows_the_boot_option(): void {
		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		Field_Factory::reset();
		$this->manager = Manager::boot( [ 'js_global' => 'AcmeFields' ] );
		$this->handler = $this->manager->get_new_settings_handler();

		$this->assertSame( 'AcmeFieldsSettingsApp', React_Renderer::js_global() );
	}

	/**
	 * The page callback prints the root element and a noscript notice.
	 */
	public function test_page_callback_outputs_root_and_noscript(): void {
		$this->boot_pages();
		$page = $this->handler->get_page( 'rest-fixture' );

		ob_start();
		call_user_func( $page->get_config( 'callback' ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="cassette-cmf-settings-rest-fixture"', $html );
		$this->assertStringContainsString( 'class="cassette-cmf-settings-app-root"', $html );
		$this->assertStringContainsString( 'data-config-global="CassetteCMFFieldsSettingsApp"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	/**
	 * Classic pages keep the default callback and the classic output.
	 */
	public function test_classic_pages_are_unchanged(): void {
		$this->boot_pages();
		$page = $this->handler->get_page( 'classic-page' );

		$this->assertSame( 'classic', $page->get_render_mode() );
		$this->assertSame( [ $page, 'render_default' ], $page->get_config( 'callback' ) );
		$this->assertArrayNotHasKey( 'render_mode', $page->get_all_config() );

		ob_start();
		$page->render_default();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'cassette-cmf-settings-app-root', $html );
	}

	/**
	 * React mode implies the REST route.
	 */
	public function test_react_mode_registers_the_rest_route(): void {
		$this->boot_pages();

		$this->assertTrue( $this->handler->get_page( 'rest-fixture' )->is_rest_enabled() );
		$this->assertFalse( $this->handler->get_page( 'classic-page' )->is_rest_enabled() );

		$this->assertSame( 200, rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/cassette-cmf/v1/settings/rest-fixture' ) )->get_status() );
		$this->assertSame( 404, rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/cassette-cmf/v1/settings/classic-page' ) )->get_status() );
	}

	/**
	 * Missing build: the classic page is used and the admin says why.
	 */
	public function test_missing_build_falls_back_to_classic(): void {
		add_filter(
			'cassette_cmf_settings_app_build_path',
			static function () {
				return '/nonexistent/build';
			}
		);

		$this->assertStringContainsString( 'missing', (string) React_Renderer::unavailable_reason() );

		$this->boot_pages();
		$page = $this->handler->get_page( 'rest-fixture' );

		$this->assertSame( [ $page, 'render_default' ], $page->get_config( 'callback' ) );

		$this->enqueue_on( $this->hook_of( 'rest-fixture' ) );
		$this->assertFalse( wp_script_is( React_Renderer::handle(), 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'cassette-cmf', 'enqueued' ), 'The classic assets load instead.' );

		set_current_screen( $this->hook_of( 'rest-fixture' ) );
		ob_start();
		$this->handler->show_react_fallback_notice();
		$notice = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $notice );
		$this->assertStringContainsString( 'classic mode', $notice );
		$this->assertStringContainsString( 'missing', $notice );

		set_current_screen( $this->hook_of( 'classic-page' ) );
		ob_start();
		$this->handler->show_react_fallback_notice();
		$this->assertSame( '', (string) ob_get_clean(), 'No notice on other pages.' );
	}

	/**
	 * WordPress older than 6.7 falls back too.
	 */
	public function test_old_wordpress_falls_back_to_classic(): void {
		global $wp_version;
		$original = $wp_version;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulating an old WordPress.
		$wp_version = '6.6.2';
		$reason     = React_Renderer::unavailable_reason();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore.
		$wp_version = $original;

		$this->assertStringContainsString( '6.7', (string) $reason );
		$this->assertNull( React_Renderer::unavailable_reason() );
	}

	/**
	 * A page with its own callback keeps it.
	 */
	public function test_custom_callback_wins(): void {
		$this->boot_pages( [ 'callback' => '__return_empty_string' ] );

		$this->assertSame( '__return_empty_string', $this->handler->get_page( 'rest-fixture' )->get_config( 'callback' ) );
		$this->enqueue_on( $this->hook_of( 'rest-fixture' ) );
		$this->assertFalse( wp_script_is( React_Renderer::handle(), 'enqueued' ) );
	}

	/**
	 * `render_mode` is additive: unknown values mean classic plus _doing_it_wrong.
	 */
	public function test_render_mode_validation_is_additive(): void {
		$validator = new Schema_Validator();

		foreach ( [ 'classic', 'react', 'something-else' ] as $mode ) {
			$this->assertTrue(
				$validator->validate(
					[
						'settings_pages' => [
							[
								'id'          => 'p',
								'render_mode' => $mode,
							],
						],
					]
				),
				"render_mode \"$mode\" must not reject a config."
			);
		}

		$page = new Settings_Page( 'x', [ 'render_mode' => 'fancy' ] );

		$this->setExpectedIncorrectUsage( 'PedalCMS\CassetteCMF\Settings\Settings_Page::get_render_mode' );
		$this->assertSame( 'classic', $page->get_render_mode() );
		$this->assertSame( 'classic', $page->get_render_mode(), 'Warned once, still classic.' );

		$this->assertSame( 'classic', ( new Settings_Page( 'y' ) )->get_render_mode() );
		$this->assertSame( 'react', ( new Settings_Page( 'z', [ 'render_mode' => 'react' ] ) )->get_render_mode() );
	}

	/**
	 * The field tree: containers, options, conditionals, secret, classic-only.
	 */
	public function test_app_config_describes_the_page(): void {
		$this->boot_pages();
		$renderer = new React_Renderer( $this->handler, $this->handler->get_page( 'rest-fixture' ) );
		$config   = $renderer->get_app_config();
		$values   = (array) $config['values'];

		$flat = [];
		$walk = static function ( array $nodes ) use ( &$walk, &$flat ): void {
			foreach ( $nodes as $node ) {
				$flat[ $node['name'] ] = $node;
				foreach ( [ 'children', 'subFields' ] as $key ) {
					if ( isset( $node[ $key ] ) ) {
						$walk( $node[ $key ] );
					}
				}
				foreach ( $node['tabs'] ?? [] as $tab ) {
					$walk( $tab['fields'] );
				}
			}
		};
		$walk( $config['fields'] );

		$types = array_unique( array_column( $flat, 'type' ) );
		foreach ( [ 'text', 'textarea', 'email', 'url', 'date', 'color', 'number', 'select', 'radio', 'checkbox', 'wysiwyg', 'upload', 'repeater', 'password', 'secret', 'custom_html', 'group', 'metabox', 'tabs' ] as $type ) {
			$this->assertContains( $type, $types, "Type $type is described." );
		}

		$this->assertSame( 'metabox', $config['fields'][ array_search( 'panel', array_column( $config['fields'], 'name' ), true ) ]['type'] );
		$this->assertSame(
			[
				'value' => 'a',
				'label' => 'Alpha',
			],
			$flat['mode']['options'][0]
		);
		$this->assertTrue( $flat['tags']['multiple'] );
		$this->assertArrayHasKey( 'subFields', $flat['items'] );
		$this->assertSame( 'qty', $flat['items']['subFields'][1]['name'] );
		$this->assertTrue( $flat['locked']['readonly'] || $flat['locked']['disabled'] );
		$this->assertNotEmpty( $flat['intro']['html'] );
		$this->assertArrayNotHasKey( 'classic_only', $flat, 'show_in_rest:false fields are not drawn.' );
		$this->assertContains( 'Classic only', $config['classicOnly'] );
		$this->assertSame( '', $values['api_key'], 'A secret is only ever a mask.' );
		$this->assertArrayNotHasKey( 'legacy_password', $values );
		$this->assertSame( 'REST fixture', $config['title'] );
	}

	/**
	 * Conditional rules reach the app in normalized form.
	 */
	public function test_conditionals_are_passed_normalized(): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'          => 'cond',
						'page_title'  => 'Conditions',
						'render_mode' => 'react',
						'fields'      => [
							[
								'name' => 'mode',
								'type' => 'text',
							],
							[
								'name'        => 'extra',
								'type'        => 'text',
								'conditional' => [
									'field'   => 'mode',
									'compare' => 'eq',
									'values'  => [ 'x' ],
								],
							],
						],
					],
				],
			]
		);

		$config = ( new React_Renderer( $this->handler, $this->handler->get_page( 'cond' ) ) )->get_app_config();

		$this->assertNull( $config['fields'][0]['conditional'] );
		$this->assertSame(
			[
				'relation' => 'AND',
				'rules'    => [
					[
						'field'    => 'mode',
						'operator' => '==',
						'value'    => [ 'x' ],
					],
				],
			],
			$config['fields'][1]['conditional']
		);
	}

	/**
	 * Custom HTML is sanitized exactly like the classic renderer does.
	 */
	public function test_custom_html_matches_classic_output(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'note',
				'type'    => 'custom_html',
				'content' => '<p onclick="x()">Hi <script>alert(1)</script><b>there</b></p>',
			]
		);

		$html = $field->get_content_html();

		$this->assertStringNotContainsString( 'script', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringContainsString( '<b>there</b>', $html );
		$this->assertStringContainsString( '<div class="cassette-cmf-custom-html-content">' . $html . '</div>', $field->render() );
	}
}
