<?php
/**
 * Backward-compatibility baseline dump.
 *
 * Boots WordPress with this repository's bundled examples and writes a
 * deterministic JSON description of everything an existing consumer could
 * observe (block types, REST routes, assets, settings pages, rendered markup,
 * public PHP API, ...). Not part of the default suite.
 *
 * @package PedalCMS\CassetteCMF\Tests\BC
 */

/**
 * Class Test_BC_Dump
 */
class Test_BC_Dump extends WP_UnitTestCase {

	/**
	 * Core REST namespaces whose routes are not described in detail.
	 *
	 * @var string[]
	 */
	private const CORE_REST_NAMESPACES = [
		'wp/v2',
		'oembed/1.0',
		'wp-site-health/v1',
		'wp-block-editor/v1',
		'batch/v1',
		'wp-abilities/v1',
		'wp-abilities/v2',
		'wp-ability/v1',
		'wp-font-library/v1',
		'wp-collaboration/v1',
		'wp-connectors/v1',
	];

	/**
	 * Core handles that core itself registers on enqueue hooks.
	 *
	 * @var string[]
	 */
	private const CORE_HOOK_HANDLES = [
		'wp-emoji-styles',
		'global-styles',
		'global-styles-css-custom-properties',
		'wp-block-styles',
		'classic-theme-styles',
		'wp-block-library-theme',
		'wp-fonts-local',
	];

	/**
	 * Ordered [search => replace] pairs applied to every string.
	 *
	 * @var array<string, string>
	 */
	private $replacements = [];

	/**
	 * Build-time asset version hashes to blank out.
	 *
	 * @var string[]
	 */
	private $versions = [];

	/**
	 * Whether this repository is the block builder.
	 *
	 * @var bool
	 */
	private $is_bb = false;

	/**
	 * Repository root (real path).
	 *
	 * @var string
	 */
	private $repo = '';

	/**
	 * Set up the string normaliser and detach the case's own notice hooks.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->is_bb = 'bb' === BC_REPO_KIND;
		$this->repo  = (string) realpath( TESTS_PLUGIN_DIR );

		foreach ( [ 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run', 'deprecated_file_included' ] as $hook ) {
			remove_action( $hook, [ $this, $hook ] );
		}

		$pairs = [];

		$pairs[ $this->repo ]                    = '{REPO}';
		$pairs[ rtrim( TESTS_PLUGIN_DIR, '/' ) ] = '{REPO}';

		$sibling = (string) realpath( TESTS_PLUGIN_DIR . '/vendor/pedalcms/cassette-cmf' );
		if ( $this->is_bb && '' !== $sibling ) {
			$pairs[ $sibling ] = '{CMF}';
		}

		if ( defined( 'WP_CORE_DIR' ) ) {
			$pairs[ rtrim( WP_CORE_DIR, '/' ) ] = '{WP}';
			$real_wp                            = realpath( WP_CORE_DIR );
			if ( $real_wp ) {
				$pairs[ rtrim( $real_wp, '/' ) ] = '{WP}';
			}
		}
		$pairs[ rtrim( ABSPATH, '/' ) ] = '{WP}';

		$pairs[ home_url() ] = '{HOME}';
		$pairs[ site_url() ] = '{HOME}';

		// Longest search string first, so nested paths win.
		uksort(
			$pairs,
			static function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		$this->replacements = array_filter(
			$pairs,
			static function ( $replace, $search ) {
				return '' !== $search;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Build the dump and write it to $BC_DUMP_OUT.
	 */
	public function test_dump(): void {
		$dump = $this->build_dump();

		$this->assertNotEmpty( $dump );
		$this->assertNotEmpty( $dump['public_api']['classes'] );

		$out = getenv( 'BC_DUMP_OUT' );
		if ( $out ) {
			$json = wp_json_encode( $this->sort_keys( $dump ), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR );
			$this->assertIsString( $json );
			file_put_contents( $out, $json . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Assemble every section.
	 *
	 * @return array<string, mixed>
	 */
	private function build_dump(): array {
		$dump = [];

		$dump['meta'] = [
			'kind' => BC_REPO_KIND,
			'wp'   => get_bloginfo( 'version' ),
		];

		$dump['block_types']            = $this->section_block_types();
		$dump['block_bindings_sources'] = $this->section_bindings();
		$dump['editor_payload']         = $this->is_bb ? $this->section_editor_payload() : [];
		$dump['rest_routes']            = $this->section_rest();

		$settings = $this->section_settings();

		$dump['settings']        = $settings['settings'];
		$dump['options']         = $settings['options'];
		$dump['scripts_styles']  = $this->section_assets( $settings['contexts'] );
		$dump['rendered_blocks'] = $this->section_rendered_blocks();
		$dump['public_api']      = $this->section_public_api();
		$dump['hooks']           = $this->section_hooks();
		$dump['notices']         = $this->section_notices();

		$dump['example_load_errors'] = $this->norm( (array) ( $GLOBALS['bc_example_errors'] ?? [] ) );

		// Spec names: scripts / styles are reported side by side per context.
		$dump['scripts'] = [];
		$dump['styles']  = [];
		foreach ( $dump['scripts_styles'] as $context => $data ) {
			$dump['scripts'][ $context ] = $data['scripts'];
			$dump['styles'][ $context ]  = $data['styles'];
		}
		unset( $dump['scripts_styles'] );

		return $dump;
	}

	// ---------------------------------------------------------------- helpers.

	/**
	 * Normalise a string: paths, URLs, nonces, versions.
	 *
	 * @param string $s Input.
	 * @return string
	 */
	private function norm_string( string $s ): string {
		foreach ( $this->replacements as $search => $replace ) {
			$s = str_replace( $search, $replace, $s );
		}

		foreach ( $this->versions as $version ) {
			$s = str_replace( $version, '{VER}', $s );
		}

		// Asset version query strings.
		$s = preg_replace( '/((?:[?&]|&amp;|&#038;)ver=)[^&"\'\s<>#]*/', '$1{VER}', $s );

		// Nonces: query args, hidden inputs (either attribute order), JSON keys.
		$s = preg_replace( '/(_wpnonce=)[0-9a-f]{8,12}/', '$1{NONCE}', $s );
		$s = preg_replace( '/(name=["\'][^"\']*nonce[^"\']*["\'][^>]*?\svalue=["\'])[0-9a-f]{8,12}/i', '$1{NONCE}', $s );
		$s = preg_replace( '/(\svalue=["\'])[0-9a-f]{8,12}(["\'][^>]*?\sname=["\'][^"\']*nonce[^"\']*["\'])/i', '$1{NONCE}$2', $s );
		$s = preg_replace( '/("[A-Za-z_\-]*nonce[A-Za-z_\-]*"\s*:\s*")[0-9a-f]{8,12}"/i', '$1{NONCE}"', $s );

		return $s;
	}

	/**
	 * Describe a callable without line numbers or object hashes.
	 *
	 * @param mixed $cb Callable-ish value.
	 * @return string|null
	 */
	private function callable_str( $cb ) {
		if ( null === $cb || '' === $cb ) {
			return null;
		}
		if ( $cb instanceof \Closure ) {
			return '{closure}';
		}
		if ( is_string( $cb ) ) {
			return $this->norm_string( $cb );
		}
		if ( is_array( $cb ) && isset( $cb[0] ) && is_object( $cb[0] ) && $cb[0] instanceof \WP_REST_Server ) {
			return 'WP_REST_Server::' . (string) ( $cb[1] ?? '' );
		}
		if ( is_array( $cb ) && 2 === count( $cb ) && isset( $cb[0], $cb[1] ) && is_string( $cb[1] ) ) {
			$class = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
			return $class . '::' . $cb[1];
		}
		if ( is_object( $cb ) ) {
			return get_class( $cb ) . '::__invoke';
		}
		return '{unknown}';
	}

	/**
	 * Recursively normalise a value for JSON output.
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private function norm( $v ) {
		if ( is_string( $v ) ) {
			return $this->norm_string( $v );
		}
		if ( $v instanceof \Closure ) {
			return '{closure}';
		}
		if ( is_object( $v ) ) {
			return '{object:' . get_class( $v ) . '}';
		}
		if ( is_array( $v ) ) {
			if ( 2 === count( $v ) && isset( $v[0], $v[1] ) && is_object( $v[0] ) && is_string( $v[1] ) ) {
				return $this->callable_str( $v );
			}
			$out = [];
			foreach ( $v as $k => $item ) {
				$out[ is_string( $k ) ? $this->norm_string( $k ) : $k ] = $this->norm( $item );
			}
			return $out;
		}
		if ( is_float( $v ) && ( is_nan( $v ) || is_infinite( $v ) ) ) {
			return (string) $v;
		}
		return $v;
	}

	/**
	 * Sort associative array keys recursively (lists keep their order).
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private function sort_keys( $v ) {
		if ( ! is_array( $v ) ) {
			return $v;
		}
		foreach ( $v as $k => $item ) {
			$v[ $k ] = $this->sort_keys( $item );
		}
		if ( [] !== $v && array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) {
			ksort( $v, SORT_STRING );
		}
		return $v;
	}

	/**
	 * Become an administrator.
	 *
	 * @return int User ID.
	 */
	private function become_admin(): int {
		$user = get_user_by( 'login', 'bc_admin' );
		if ( $user ) {
			$id = (int) $user->ID;
		} else {
			$id = wp_insert_user(
				[
					'import_id'  => 9002,
					'user_login' => 'bc_admin',
					'user_pass'  => 'bc-admin-pass',
					'user_email' => 'bc-admin@example.org',
					'role'       => 'administrator',
				]
			);
			$this->assertIsInt( $id );
		}
		wp_set_current_user( $id );
		return $id;
	}

	// --------------------------------------------------------- 1. block types.

	/**
	 * Non-core block types with their full args.
	 *
	 * @return array<string, mixed>
	 */
	private function section_block_types(): array {
		$out = [];
		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( 0 === strpos( $name, 'core/' ) ) {
				continue;
			}
			$vars = get_object_vars( $type );
			foreach ( [ 'render_callback', 'variation_callback' ] as $key ) {
				if ( array_key_exists( $key, $vars ) ) {
					$vars[ $key ] = $this->callable_str( $vars[ $key ] );
				}
			}
			$vars['variations'] = $type->variations;
			if ( is_array( $vars['uses_context'] ?? null ) ) {
				sort( $vars['uses_context'] );
			}
			$out[ $name ] = $this->norm( $vars );
		}
		ksort( $out );
		return $out;
	}

	// ------------------------------------------------------------ 5. bindings.

	/**
	 * Non-core block bindings sources.
	 *
	 * @return array<string, mixed>
	 */
	private function section_bindings(): array {
		$out = [];
		if ( ! class_exists( 'WP_Block_Bindings_Registry' ) ) {
			return $out;
		}
		foreach ( WP_Block_Bindings_Registry::get_instance()->get_all_registered() as $name => $source ) {
			if ( 0 === strpos( $name, 'core/' ) ) {
				continue;
			}
			$vars = get_object_vars( $source );
			if ( array_key_exists( 'get_value_callback', $vars ) ) {
				$vars['get_value_callback'] = $this->callable_str( $vars['get_value_callback'] );
			}
			if ( is_array( $vars['uses_context'] ?? null ) ) {
				sort( $vars['uses_context'] );
			}
			$out[ $name ] = $this->norm( $vars );
		}
		ksort( $out );
		return $out;
	}

	// ------------------------------------------------------ 2. editor payload.

	/**
	 * Editor payload as Asset_Loader builds it, plus the inline script.
	 *
	 * @return array<string, mixed>
	 */
	private function section_editor_payload(): array {
		$asset_file = TESTS_PLUGIN_DIR . '/src/assets/build/editor.asset.php';
		$version    = '';
		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$version = (string) ( $asset['version'] ?? '' );
		}
		if ( strlen( $version ) >= 6 ) {
			$this->versions[] = $version;
		}

		$manager = \PedalCMS\CassetteCMFBlocks\Core\Block_Manager::init();
		$payload = \PedalCMS\CassetteCMFBlocks\Core\Editor_Payload::build( $manager, $version );

		$this->become_admin();
		set_current_screen( 'post' );
		$this->reset_assets();
		do_action( 'enqueue_block_editor_assets' );

		$handle = \PedalCMS\CassetteCMFBlocks\Core\Asset_Loader::HANDLE;
		$before = wp_scripts()->get_data( $handle, 'before' );
		$after  = wp_scripts()->get_data( $handle, 'after' );

		wp_set_current_user( 0 );
		set_current_screen( 'front' );
		$this->reset_assets();

		return [
			'build'         => $this->norm( $payload ),
			'inline_before' => $this->norm( is_array( $before ) ? array_values( array_filter( $before ) ) : $before ),
			'inline_after'  => $this->norm( is_array( $after ) ? array_values( array_filter( $after ) ) : $after ),
		];
	}

	// ------------------------------------------------------------- 3. REST.

	/**
	 * REST namespaces and non-core route detail.
	 *
	 * @return array<string, mixed>
	 */
	private function section_rest(): array {
		$GLOBALS['wp_rest_server'] = null;
		$this->become_admin();
		$server = rest_get_server();

		$namespaces = $server->get_namespaces();
		sort( $namespaces );

		$routes = [];
		foreach ( $namespaces as $ns ) {
			if ( in_array( $ns, self::CORE_REST_NAMESPACES, true ) ) {
				continue;
			}
			foreach ( $server->get_routes( $ns ) as $route => $handlers ) {
				$entries = [];
				foreach ( $handlers as $handler ) {
					if ( ! is_array( $handler ) ) {
						continue;
					}
					$methods = isset( $handler['methods'] ) ? array_keys( array_filter( (array) $handler['methods'] ) ) : [];
					sort( $methods );
					$args = isset( $handler['args'] ) && is_array( $handler['args'] ) ? array_keys( $handler['args'] ) : [];
					sort( $args );
					$entries[] = [
						'methods'             => $methods,
						'args'                => $args,
						'callback'            => $this->callable_str( $handler['callback'] ?? null ),
						'permission_callback' => $this->callable_str( $handler['permission_callback'] ?? null ),
					];
				}
				$routes[ $this->norm_string( $route ) ] = $entries;
			}
		}
		ksort( $routes );

		wp_set_current_user( 0 );

		return [
			'namespaces' => $namespaces,
			'routes'     => $routes,
		];
	}

	// ---------------------------------------------------- 6. settings, 9. options.

	/**
	 * Settings pages (config, registered settings, rendered HTML) and option names.
	 *
	 * @return array{settings: array<string, mixed>, options: array<string, mixed>, contexts: array<int, array<string, mixed>>}
	 */
	private function section_settings(): array {
		$settings = [
			'new_pages'      => [],
			'existing_pages' => [],
		];
		$options  = [
			'settings_fields' => [],
			'object_fields'   => [],
		];
		$contexts = [];

		if ( ! class_exists( '\PedalCMS\CassetteCMF\Core\Manager' ) ) {
			return compact( 'settings', 'options', 'contexts' );
		}

		$manager = \PedalCMS\CassetteCMF\Core\Manager::init();
		$new     = $manager->get_new_settings_handler();
		$exist   = $manager->get_existing_settings_handler();

		// Make the admin menu and Settings API registration run.
		$this->become_admin();
		set_current_screen( 'dashboard' );
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		// Build the real admin menu (core items first), so submenu parents resolve
		// to the same hook suffixes as in wp-admin. menu.php fires admin_menu itself.
		global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $admin_page_hooks, $_registered_pages, $_parent_pages, $parent_file, $typenow, $pagenow;
		$menu               = [];
		$submenu            = [];
		$_wp_menu_nopriv    = [];
		$_wp_submenu_nopriv = [];
		$admin_page_hooks   = [];
		$_registered_pages  = [];
		$_parent_pages      = [];
		$pagenow            = 'index.php';
		require ABSPATH . 'wp-admin/menu.php';
		// Header-sending core callbacks cannot run under the CLI test runner.
		remove_action( 'admin_init', 'send_frame_options_header' );
		remove_action( 'admin_init', 'wp_admin_headers' );
		do_action( 'admin_init' );

		$registered = function_exists( 'get_registered_settings' ) ? get_registered_settings() : [];

		// New settings pages.
		foreach ( $new->get_pages() as $page_id => $page ) {
			$config  = $page->get_all_config();
			$hook    = $page->get_hook_suffix();
			$slug    = $page->get_menu_slug();
			$entry   = [
				'page_id'     => $page_id,
				'menu_slug'   => $slug,
				'capability'  => $config['capability'] ?? null,
				'hook_suffix' => $hook,
				'is_submenu'  => $page->is_submenu(),
				'registered'  => $page->is_registered(),
				'config'      => $this->norm( $this->callables_in_config( $config ) ),
				'settings'    => $this->settings_for_group( $registered, $slug ),
				'fields'      => $this->fields_description( $new->get_fields( $page_id ), $page_id ),
			];
			$html    = '';
			$hook_ok = is_string( $hook ) && '' !== $hook;
			if ( $hook_ok ) {
				$contexts[] = [
					'key'    => 'admin_enqueue_scripts:' . $hook,
					'screen' => $hook,
					'type'   => 'admin',
				];
				set_current_screen( $hook );
				do_action( 'load-' . $hook ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Core hook name.
				$callback = $config['callback'] ?? [ $page, 'render_default' ];
				if ( is_callable( $callback ) ) {
					ob_start();
					try {
						call_user_func( $callback );
					} catch ( \Throwable $e ) {
						echo '{error:' . get_class( $e ) . '}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captured into the dump, not rendered.
					}
					$html = (string) ob_get_clean();
				}
				set_current_screen( 'dashboard' );
			}
			$entry['rendered_html']            = $this->norm_string( $html );
			$settings['new_pages'][ $page_id ] = $entry;
			foreach ( $new->get_fields( $page_id ) as $field ) {
				$options['settings_fields'][ $page_id ][ $field->get_name() ] = $field->get_option_name( $page_id );
			}
		}

		// Existing (core) settings pages extended by CMF.
		foreach ( $exist->get_all_fields() as $page_id => $fields ) {
			if ( empty( $fields ) ) {
				continue;
			}
			$group = $exist->get_option_group( $page_id );
			ob_start();
			try {
				if ( function_exists( 'do_settings_sections' ) ) {
					do_settings_sections( $group );
				}
			} catch ( \Throwable $e ) {
				echo '{error:' . get_class( $e ) . '}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Captured into the dump, not rendered.
			}
			$html = (string) ob_get_clean();

			$settings['existing_pages'][ $page_id ] = [
				'page_id'       => $page_id,
				'option_group'  => $group,
				'is_wp_page'    => $exist->is_wordpress_page( $page_id ),
				'settings'      => $this->settings_for_group( $registered, $group ),
				'fields'        => $this->fields_description( $fields, $page_id ),
				'rendered_html' => $this->norm_string( $html ),
			];
			foreach ( $fields as $field ) {
				$options['settings_fields'][ $page_id ][ $field->get_name() ] = $field->get_option_name( $page_id );
			}
			$contexts[] = [
				'key'    => 'admin_enqueue_scripts:options-' . $page_id,
				'screen' => 'options-' . $page_id,
				'type'   => 'admin',
			];
		}

		// Post type and taxonomy field names (meta keys follow the option name).
		foreach ( $manager->get_new_cpt_handler()->get_post_types() as $post_type => $cpt ) {
			$contexts[] = [
				'key'       => 'admin_enqueue_scripts:post.php:' . $post_type,
				'screen'    => 'post',
				'type'      => 'admin',
				'post_type' => $post_type,
			];
		}
		foreach ( [ $manager->get_new_cpt_handler(), $manager->get_existing_cpt_handler() ] as $handler ) {
			foreach ( $handler->get_all_fields() as $context => $fields ) {
				foreach ( $fields as $field ) {
					$options['object_fields']['post_type'][ $context ][ $field->get_name() ] = $field->get_option_name( $context );
				}
			}
		}
		foreach ( [ $manager->get_new_taxonomy_handler(), $manager->get_existing_taxonomy_handler() ] as $handler ) {
			foreach ( $handler->get_all_fields() as $context => $fields ) {
				foreach ( $fields as $field ) {
					$options['object_fields']['taxonomy'][ $context ][ $field->get_name() ] = $field->get_option_name( $context );
				}
			}
		}
		foreach ( [ 'settings_fields', 'object_fields' ] as $key ) {
			$options[ $key ] = $this->sort_keys( $options[ $key ] );
		}

		// Registered settings in total (every group), useful as an option catalogue.
		$options['registered_settings'] = $this->norm( $this->settings_for_group( $registered, null ) );

		set_current_screen( 'front' );
		wp_set_current_user( 0 );

		ksort( $settings['new_pages'] );
		ksort( $settings['existing_pages'] );

		return compact( 'settings', 'options', 'contexts' );
	}

	/**
	 * Replace callables within a page config.
	 *
	 * @param array<string, mixed> $config Config.
	 * @return array<string, mixed>
	 */
	private function callables_in_config( array $config ): array {
		foreach ( $config as $key => $value ) {
			if ( 'callback' === $key || $value instanceof \Closure ) {
				$config[ $key ] = $this->callable_str( $value );
			}
		}
		return $config;
	}

	/**
	 * Registered settings for an option group (null = all non-core).
	 *
	 * @param array<string, array<string, mixed>> $registered get_registered_settings().
	 * @param string|null                         $group      Option group.
	 * @return array<string, mixed>
	 */
	private function settings_for_group( array $registered, $group ): array {
		$out = [];
		foreach ( $registered as $option => $args ) {
			if ( null !== $group && ( $args['group'] ?? '' ) !== $group ) {
				continue;
			}
			if ( ! $this->is_cmf_option( $option ) ) {
				continue;
			}
			if ( array_key_exists( 'sanitize_callback', $args ) ) {
				$args['sanitize_callback'] = $this->callable_str( $args['sanitize_callback'] );
			}
			$out[ $option ] = $this->norm( $args );
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Whether an option name was registered by a CMF sanitize callback.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private function is_cmf_option( string $option ): bool {
		$args = get_registered_settings()[ $option ] ?? [];
		$cb   = $args['sanitize_callback'] ?? null;
		return is_array( $cb ) && isset( $cb[0] ) && is_object( $cb[0] ) && 0 === strpos( get_class( $cb[0] ), 'PedalCMS\\' );
	}

	/**
	 * Describe fields: name, type, label, option name.
	 *
	 * @param array<string, object> $fields  Field instances.
	 * @param string                $context Option prefix.
	 * @return array<string, mixed>
	 */
	private function fields_description( array $fields, string $context ): array {
		$out = [];
		foreach ( $fields as $name => $field ) {
			$out[ (string) $name ] = [
				'class'       => get_class( $field ),
				'type'        => $field->get_type(),
				'label'       => $field->get_label(),
				'option_name' => $field->get_option_name( $context ),
			];
		}
		ksort( $out );
		return $out;
	}

	// ---------------------------------------------------- 4. scripts / styles.

	/**
	 * Reset the global script/style registries to a pristine core state.
	 */
	private function reset_assets(): void {
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		wp_scripts();
		wp_styles();
	}

	/**
	 * Core handle lists.
	 *
	 * @return array{scripts: string[], styles: string[]}
	 */
	private function core_handles(): array {
		$scripts = new WP_Scripts();
		wp_default_scripts( $scripts );
		$styles = new WP_Styles();
		wp_default_styles( $styles );
		$names = [
			'scripts' => array_keys( $scripts->registered ),
			'styles'  => array_keys( $styles->registered ),
		];

		// Admin-only core handles register when is_admin() is true.
		set_current_screen( 'dashboard' );
		$scripts = new WP_Scripts();
		wp_default_scripts( $scripts );
		$styles = new WP_Styles();
		wp_default_styles( $styles );
		set_current_screen( 'front' );

		$names['scripts'] = array_values( array_unique( array_merge( $names['scripts'], array_keys( $scripts->registered ) ) ) );
		$names['styles']  = array_values( array_unique( array_merge( $names['styles'], array_keys( $styles->registered ) ) ) );

		return $names;
	}

	/**
	 * Describe non-core handles of a registry.
	 *
	 * @param WP_Dependencies $deps     Registry.
	 * @param string[]        $core     Core handle names.
	 * @param bool            $is_style Style registry.
	 * @return array<string, mixed>
	 */
	private function describe_registry( WP_Dependencies $deps, array $core, bool $is_style ): array {
		$out = [];
		foreach ( $deps->registered as $handle => $dep ) {
			if ( in_array( $handle, $core, true ) || in_array( $handle, self::CORE_HOOK_HANDLES, true ) ) {
				continue;
			}
			$extra = [];
			foreach ( (array) $dep->extra as $key => $value ) {
				$extra[ $key ] = $value;
			}
			$entry = [
				'src'      => is_string( $dep->src ) ? $this->norm_string( $dep->src ) : $dep->src,
				'deps'     => $dep->deps,
				'ver'      => $dep->ver ? '{VER}' : $dep->ver,
				'args'     => $dep->args,
				'enqueued' => in_array( $handle, $deps->queue, true ),
				'extra'    => $this->norm( $extra ),
			];
			if ( ! $is_style && function_exists( 'wp_script_is' ) ) {
				$entry['in_footer'] = ! empty( $dep->extra['group'] );
			}
			$out[ $handle ] = $entry;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Fire each asset hook context and list non-core handles.
	 *
	 * @param array<int, array<string, mixed>> $contexts Extra admin contexts from the settings section.
	 * @return array<string, mixed>
	 */
	private function section_assets( array $contexts ): array {
		$core = $this->core_handles();
		$out  = [];

		// State as left by boot (init), before any asset hook ran.
		$out['after_init'] = [
			'scripts' => $this->describe_registry( wp_scripts(), $core['scripts'], false ),
			'styles'  => $this->describe_registry( wp_styles(), $core['styles'], true ),
		];

		$run = function ( string $key, bool $admin, array $fire, string $screen = 'post', string $post_type = '' ) use ( &$out ) {
			wp_set_current_user( 0 );
			if ( $admin ) {
				$this->become_admin();
			}
			set_current_screen( $admin ? 'dashboard' : 'front' );
			unset( $GLOBALS['typenow'] );
			if ( '' !== $post_type ) {
				$GLOBALS['typenow'] = $post_type;
			}
			$this->reset_assets();
			if ( $admin ) {
				set_current_screen( $screen );
			}
			// Whatever a pristine registry holds in this context counts as core.
			$core = [
				'scripts' => array_keys( wp_scripts()->registered ),
				'styles'  => array_keys( wp_styles()->registered ),
			];
			foreach ( $fire as $hook => $args ) {
				do_action( $hook, ...$args );
			}
			$out[ $key ] = [
				'scripts' => $this->describe_registry( wp_scripts(), $core['scripts'], false ),
				'styles'  => $this->describe_registry( wp_styles(), $core['styles'], true ),
			];
			unset( $GLOBALS['typenow'] );
		};

		$run( 'enqueue_block_editor_assets', true, [ 'enqueue_block_editor_assets' => [] ], 'post' );
		$run( 'enqueue_block_assets:editor', true, [ 'enqueue_block_assets' => [] ], 'post' );
		$run( 'enqueue_block_assets:front', false, [ 'enqueue_block_assets' => [] ] );
		$run( 'wp_enqueue_scripts:front', false, [ 'wp_enqueue_scripts' => [] ] );
		$run( 'admin_enqueue_scripts:dashboard', true, [ 'admin_enqueue_scripts' => [ 'index.php' ] ], 'dashboard' );

		foreach ( $contexts as $ctx ) {
			$hook = (string) ( $ctx['screen'] ?? '' );
			$suf  = 'post' === $hook ? 'post.php' : $hook;
			$run( (string) $ctx['key'], true, [ 'admin_enqueue_scripts' => [ $suf ] ], $hook, (string) ( $ctx['post_type'] ?? '' ) );
		}

		// Leave things pristine for the sections that follow.
		wp_set_current_user( 0 );
		set_current_screen( 'front' );
		$this->reset_assets();

		return $out;
	}

	// ------------------------------------------------------ 7. rendered blocks.

	/**
	 * Build sample attribute values from a block's schema.
	 *
	 * @param array<string, array<string, mixed>> $attributes Attribute schema.
	 * @return array<string, mixed>
	 */
	private function sample_attributes( array $attributes ): array {
		$out = [];
		foreach ( $attributes as $name => $schema ) {
			if ( in_array( $name, [ 'lock', 'metadata' ], true ) ) {
				continue;
			}
			$type = $schema['type'] ?? 'string';
			if ( is_array( $type ) ) {
				$type = reset( $type );
			}
			$default = $schema['default'] ?? null;
			if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && $schema['enum'] ) {
				$candidates   = array_values(
					array_filter(
						$schema['enum'],
						static function ( $v ) use ( $default ) {
							return $v !== $default;
						}
					)
				);
				$out[ $name ] = $candidates ? $candidates[0] : $schema['enum'][0];
				continue;
			}
			switch ( $type ) {
				case 'boolean':
					$out[ $name ] = ! (bool) $default;
					break;
				case 'number':
				case 'integer':
					$out[ $name ] = is_numeric( $default ) ? $default + 3 : 7;
					break;
				case 'array':
					$out[ $name ] = is_array( $default ) && $default ? $default : [];
					break;
				case 'object':
					$out[ $name ] = is_array( $default ) && $default ? $default : new \stdClass();
					break;
				default:
					$out[ $name ] = 'Sample ' . $name;
			}
		}
		return $out;
	}

	/**
	 * Render every non-core dynamic block as an anonymous visitor.
	 *
	 * @return array<string, mixed>
	 */
	private function section_rendered_blocks(): array {
		$author = $this->become_admin();

		$post_id = wp_insert_post(
			[
				'import_id'     => 9001,
				'post_title'    => 'BC Dump Post',
				'post_name'     => 'bc-dump-post',
				'post_content'  => 'BC dump content.',
				'post_status'   => 'publish',
				'post_author'   => $author,
				'post_date'     => '2020-01-02 03:04:05',
				'post_date_gmt' => '2020-01-02 03:04:05',
			]
		);
		$this->assertSame( 9001, $post_id );

		wp_set_current_user( 0 );
		set_current_screen( 'front' );
		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$out = [];
		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( 0 === strpos( $name, 'core/' ) || ! $type->render_callback ) {
				continue;
			}
			$entry = [];
			foreach ( [
				'defaults' => [],
				'sample'   => $this->sample_attributes( (array) $type->attributes ),
			] as $label => $attrs ) {
				$entry[ $label ] = [
					'attrs' => $this->norm( $attrs ),
					'html'  => $this->render_one( $name, $attrs ),
				];
			}
			$out[ $name ] = $entry;
		}
		ksort( $out );

		wp_reset_postdata();
		return $out;
	}

	/**
	 * Render a single block and normalise.
	 *
	 * @param string               $name  Block name.
	 * @param array<string, mixed> $attrs Attributes.
	 * @return string
	 */
	private function render_one( string $name, array $attrs ): string {
		ob_start();
		try {
			$html = render_block(
				[
					'blockName'    => $name,
					'attrs'        => $attrs,
					'innerBlocks'  => [],
					'innerHTML'    => '',
					'innerContent' => [],
				]
			);
		} catch ( \Throwable $e ) {
			$html = '{error:' . get_class( $e ) . ':' . $e->getMessage() . '}';
		}
		$echoed = (string) ob_get_clean();
		return $this->norm_string( $html . ( '' !== $echoed ? '{echoed:' . $echoed . '}' : '' ) );
	}

	// -------------------------------------------------------- 8. public API.

	/**
	 * Describe the library's public PHP surface through reflection.
	 *
	 * @return array<string, mixed>
	 */
	private function section_public_api(): array {
		$src     = $this->repo . '/src/';
		$map     = require TESTS_PLUGIN_DIR . '/vendor/composer/autoload_classmap.php';
		$classes = [];

		foreach ( $map as $class => $file ) {
			$real = realpath( $file );
			if ( ! $real || 0 !== strpos( $real, $src ) ) {
				continue;
			}
			if ( ! class_exists( $class ) && ! interface_exists( $class ) && ! trait_exists( $class ) ) {
				continue;
			}
			$classes[ $class ] = $this->describe_class( new ReflectionClass( $class ) );
		}
		ksort( $classes );

		$functions = [];
		$defined   = get_defined_functions();
		foreach ( $defined['user'] as $fn ) {
			$ref  = new ReflectionFunction( $fn );
			$file = $ref->getFileName();
			if ( $file && 0 === strpos( (string) realpath( $file ), $src ) ) {
				$functions[ $ref->getName() ] = $this->describe_function( $ref );
			}
		}
		ksort( $functions );

		return [
			'classes'   => $classes,
			'functions' => $functions,
		];
	}

	/**
	 * Describe one class-like.
	 *
	 * @param ReflectionClass $ref Reflection.
	 * @return array<string, mixed>
	 */
	private function describe_class( ReflectionClass $ref ): array {
		$kind = 'class';
		if ( $ref->isInterface() ) {
			$kind = 'interface';
		} elseif ( $ref->isTrait() ) {
			$kind = 'trait';
		} elseif ( method_exists( $ref, 'isEnum' ) && $ref->isEnum() ) {
			$kind = 'enum';
		}

		$interfaces = $ref->getInterfaceNames();
		sort( $interfaces );
		$traits = $ref->getTraitNames();
		sort( $traits );

		$constants = [];
		foreach ( $ref->getReflectionConstants() as $const ) {
			if ( $const->isPrivate() ) {
				continue;
			}
			$constants[ $const->getName() ] = [
				'visibility' => $const->isPublic() ? 'public' : 'protected',
				'value'      => $this->norm( $const->getValue() ),
			];
		}
		ksort( $constants );

		$properties = [];
		foreach ( $ref->getProperties() as $prop ) {
			if ( $prop->isPrivate() || $prop->getDeclaringClass()->getName() !== $ref->getName() ) {
				continue;
			}
			$properties[ $prop->getName() ] = [
				'visibility' => $prop->isPublic() ? 'public' : 'protected',
				'static'     => $prop->isStatic(),
				'type'       => $prop->hasType() ? (string) $prop->getType() : null,
			];
		}
		ksort( $properties );

		$methods = [];
		foreach ( $ref->getMethods() as $method ) {
			if ( $method->isPrivate() || $method->getDeclaringClass()->getName() !== $ref->getName() ) {
				continue;
			}
			$methods[ $method->getName() ] = array_merge(
				[
					'visibility' => $method->isPublic() ? 'public' : 'protected',
					'static'     => $method->isStatic(),
					'abstract'   => $method->isAbstract(),
					'final'      => $method->isFinal(),
				],
				$this->describe_function( $method )
			);
		}
		ksort( $methods );

		$parent = $ref->getParentClass();

		return [
			'kind'       => $kind,
			'abstract'   => $ref->isAbstract() && 'class' === $kind,
			'final'      => $ref->isFinal(),
			'parent'     => $parent ? $parent->getName() : null,
			'interfaces' => $interfaces,
			'traits'     => $traits,
			'constants'  => $constants,
			'properties' => $properties,
			'methods'    => $methods,
		];
	}

	/**
	 * Describe a function or method signature.
	 *
	 * @param ReflectionFunctionAbstract $ref Reflection.
	 * @return array<string, mixed>
	 */
	private function describe_function( ReflectionFunctionAbstract $ref ): array {
		$params = [];
		foreach ( $ref->getParameters() as $param ) {
			$default = null;
			if ( $param->isDefaultValueAvailable() ) {
				if ( $param->isDefaultValueConstant() ) {
					$default = '{const:' . $param->getDefaultValueConstantName() . '}';
				} else {
					$default = $this->norm( $param->getDefaultValue() );
				}
			}
			$params[] = [
				'name'        => $param->getName(),
				'type'        => $param->hasType() ? (string) $param->getType() : null,
				'by_ref'      => $param->isPassedByReference(),
				'variadic'    => $param->isVariadic(),
				'optional'    => $param->isOptional(),
				'has_default' => $param->isDefaultValueAvailable(),
				'default'     => $default,
			];
		}
		return [
			'params'      => $params,
			'return_type' => $ref->hasReturnType() ? (string) $ref->getReturnType() : null,
			'returns_ref' => $ref->returnsReference(),
		];
	}

	// ------------------------------------------------------------- 9. hooks.

	/**
	 * Hook names fired from src/ (static regex scan).
	 *
	 * @return array<string, mixed>
	 */
	private function section_hooks(): array {
		$actions = [];
		$filters = [];
		$dynamic = [];
		$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->repo . '/src', FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( 'php' !== $file->getExtension() || false !== strpos( $file->getPathname(), '/assets/build/' ) ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$rel  = substr( $file->getPathname(), strlen( $this->repo ) + 1 );

			if ( preg_match_all( '/\b(?:do_action|do_action_ref_array|apply_filters|apply_filters_ref_array)\(\s*(\$[A-Za-z_]+)/', $code, $m ) ) {
				$dynamic[ $rel ] = count( $m[0] );
			}

			// apply_id_filters( 'tag', ... ) fires both 'tag' and 'tag_{id}'.
			if ( preg_match_all( '/\bapply_id_filters\(\s*([\'"])([^\'"]+)\1/', $code, $m ) ) {
				foreach ( $m[2] as $tag ) {
					$filters[ $tag ]                = true;
					$filters[ $tag . '_{dynamic}' ] = true;
				}
			}

			if ( ! preg_match_all( '/\b(do_action|do_action_ref_array|apply_filters|apply_filters_ref_array)\(\s*([\'"])([^\'"]+)\2(\s*\.)?/', $code, $m, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $m as $hit ) {
				$name = preg_replace( '/\{?\$[A-Za-z_]+\}?/', '{dynamic}', $hit[3] ) . ( ! empty( $hit[4] ) ? '{dynamic}' : '' );
				if ( 0 === strpos( $hit[1], 'do_action' ) ) {
					$actions[ $name ] = true;
				} else {
					$filters[ $name ] = true;
				}
			}
		}
		$actions = array_keys( $actions );
		$filters = array_keys( $filters );
		sort( $actions );
		sort( $filters );
		ksort( $dynamic );
		return [
			'actions'                  => $actions,
			'filters'                  => $filters,
			'variable_name_call_sites' => $dynamic,
		];
	}

	/**
	 * _doing_it_wrong / deprecation notices raised since boot.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function section_notices(): array {
		$seen = [];
		foreach ( (array) ( $GLOBALS['bc_notices'] ?? [] ) as $notice ) {
			$row = $this->norm( $notice );
			$seen[ $row['hook'] . '|' . $row['name'] . '|' . $row['message'] ] = $row;
		}
		ksort( $seen );
		return array_values( $seen );
	}
}
