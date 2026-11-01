<?php

/**
 * Manager class for Cassette-CMF
 *
 * Central registry and bootstrap for the Content Modeling Framework.
 * Coordinates registration of custom post types, settings pages, and fields.
 *
 * @package PedalCMS\CassetteCMF
 * @since 1.0.0
 */

namespace PedalCMS\CassetteCMF\Core;

use PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\Existing_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\New_Post_Type_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\Existing_Post_Type_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\New_Taxonomy_Handler;
use PedalCMS\CassetteCMF\Core\Handlers\Existing_Taxonomy_Handler;
use PedalCMS\CassetteCMF\Field\Abstract_Field;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Field_Interface;
use PedalCMS\CassetteCMF\Field\Fields\Secret_Field;
use PedalCMS\CassetteCMF\Json\Schema_Validator;

/**
 * Manager class - Central coordination point for Cassette-CMF
 *
 * Provides a singleton pattern for managing registration and configuration
 * of custom post types, settings pages, and field definitions.
 */
class Manager {

	/**
	 * Default script/style handle used by every handler.
	 *
	 * @var string
	 */
	public const DEFAULT_ASSET_HANDLE = 'cassette-cmf';

	/**
	 * Default name of the global JS object published by cassette-cmf.js.
	 *
	 * @var string
	 */
	public const DEFAULT_JS_GLOBAL = 'CassetteCMFFields';

	/**
	 * Default REST namespace (used by the REST controller).
	 *
	 * @var string
	 */
	public const DEFAULT_REST_NAMESPACE = 'cassette-cmf/v1';

	/**
	 * Name of the one-shot window property that hands a non-default JS
	 * global name to cassette-cmf.js. The script reads and deletes it.
	 *
	 * @var string
	 */
	public const JS_GLOBAL_BOOT_KEY = 'cassetteCmfFieldsBoot';

	/**
	 * The singleton instance
	 *
	 * @var Manager|null
	 */
	private static ?Manager $instance = null;

	/**
	 * Handler for new settings pages
	 *
	 * @var New_Settings_Page_Handler
	 */
	private New_Settings_Page_Handler $new_settings_handler;

	/**
	 * Handler for existing settings pages
	 *
	 * @var Existing_Settings_Page_Handler
	 */
	private Existing_Settings_Page_Handler $existing_settings_handler;

	/**
	 * Handler for new custom post types
	 *
	 * @var New_Post_Type_Handler
	 */
	private New_Post_Type_Handler $new_cpt_handler;

	/**
	 * Handler for existing post types
	 *
	 * @var Existing_Post_Type_Handler
	 */
	private Existing_Post_Type_Handler $existing_cpt_handler;

	/**
	 * Handler for new taxonomies
	 *
	 * @var New_Taxonomy_Handler
	 */
	private New_Taxonomy_Handler $new_taxonomy_handler;

	/**
	 * Handler for existing taxonomies
	 *
	 * @var Existing_Taxonomy_Handler
	 */
	private Existing_Taxonomy_Handler $existing_taxonomy_handler;

	/**
	 * Configuration options
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * Private constructor to prevent direct instantiation
	 *
	 * @param array<string, mixed> $options Configuration options.
	 */
	private function __construct( array $options = [] ) {
		$this->options = $options;

		// Initialize handlers
		$this->new_settings_handler      = new New_Settings_Page_Handler();
		$this->existing_settings_handler = new Existing_Settings_Page_Handler();
		$this->new_cpt_handler           = new New_Post_Type_Handler();
		$this->existing_cpt_handler      = new Existing_Post_Type_Handler();
		$this->new_taxonomy_handler      = new New_Taxonomy_Handler();
		$this->existing_taxonomy_handler = new Existing_Taxonomy_Handler();

		// Initialize hooks if WordPress is available
		if ( function_exists( 'add_action' ) ) {
			$this->new_settings_handler->init_hooks();
			$this->existing_settings_handler->init_hooks();
			$this->new_cpt_handler->init_hooks();
			$this->existing_cpt_handler->init_hooks();
			$this->new_taxonomy_handler->init_hooks();
			$this->existing_taxonomy_handler->init_hooks();
		}

		$this->load_textdomain();
	}

	/**
	 * Get the singleton instance
	 *
	 * @api
	 * @param array<string, mixed> $options Configuration options (only used on first call).
	 * @return Manager The singleton instance.
	 */
	public static function init( array $options = [] ): Manager {
		if ( null === self::$instance ) {
			self::$instance = new self( $options );
		}

		return self::$instance;
	}

	/**
	 * Boot the manager with coexistence options
	 *
	 * Additive and optional: with no call to boot(), every identifier keeps
	 * its default value. Use it when another copy of this library (plain or
	 * namespace-prefixed) may be loaded on the same site. Like init(), the
	 * options apply only on the first call; a later call with different
	 * options triggers _doing_it_wrong() and is ignored.
	 *
	 * Supported options:
	 * - `asset_handle`   (string, default `cassette-cmf`): CSS and JS handle.
	 * - `js_global`      (string, default `CassetteCMFFields`): name under which
	 *                    cassette-cmf.js publishes its API on `window`.
	 * - `rest_namespace` (string, default `cassette-cmf/v1`): REST namespace.
	 *
	 * @since 0.2.0
	 * @api
	 *
	 * @param array<string, mixed> $options Boot options.
	 * @return Manager The singleton instance.
	 */
	public static function boot( array $options = [] ): Manager {
		$resolved = self::resolve_boot_options( $options, true );

		if ( null === self::$instance ) {
			return self::init( $resolved );
		}

		if ( self::resolve_boot_options( self::$instance->options, false ) !== $resolved ) {
			self::doing_it_wrong(
				__METHOD__,
				'Cassette-CMF has already been initialized; the boot options only apply on the first call and were ignored. Call CassetteCMF::boot() before any other Cassette-CMF call.'
			);
		}

		return self::$instance;
	}

	/**
	 * Get the script/style handle used for the common CSS and JS.
	 *
	 * @since 0.2.0
	 *
	 * @api
	 * @return string
	 */
	public static function asset_handle(): string {
		return self::boot_option( 'asset_handle' );
	}

	/**
	 * Get the name of the global JS object cassette-cmf.js publishes.
	 *
	 * @since 0.2.0
	 *
	 * @api
	 * @return string
	 */
	public static function js_global(): string {
		return self::boot_option( 'js_global' );
	}

	/**
	 * Get the REST namespace.
	 *
	 * @since 0.2.0
	 *
	 * @api
	 * @return string
	 */
	public static function rest_namespace(): string {
		return self::boot_option( 'rest_namespace' );
	}

	/**
	 * Name the JS global for a non-default `js_global` boot option.
	 *
	 * Adds a one-shot inline "before" script to the already enqueued common
	 * script. With the default name nothing is added, so the output is
	 * unchanged.
	 *
	 * @since 0.2.0
	 *
	 * @param string $handle Script handle the common JS was enqueued under.
	 * @return void
	 */
	public static function add_js_global_script( string $handle ): void {
		$global = self::js_global();

		if ( self::DEFAULT_JS_GLOBAL === $global || ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			'window.' . self::JS_GLOBAL_BOOT_KEY . ' = ' . wp_json_encode( $global ) . ';',
			'before'
		);
	}

	/**
	 * Read one validated boot option, falling back to its default.
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	private static function boot_option( string $key ): string {
		$options = null === self::$instance ? [] : self::$instance->options;

		return self::resolve_boot_options( $options, false )[ $key ];
	}

	/**
	 * Resolve the supported boot options to a complete, valid set.
	 *
	 * @param array<string, mixed> $options Raw options.
	 * @param bool                 $notify  Raise _doing_it_wrong() for unknown keys and invalid values.
	 * @return array<string, string>
	 */
	private static function resolve_boot_options( array $options, bool $notify ): array {
		$rules = [
			'asset_handle'   => [ self::DEFAULT_ASSET_HANDLE, '/^[a-z0-9_-]+$/' ],
			'js_global'      => [ self::DEFAULT_JS_GLOBAL, '/^[A-Za-z_$][A-Za-z0-9_$]*$/' ],
			'rest_namespace' => [ self::DEFAULT_REST_NAMESPACE, '#^[a-z0-9-]+/v[0-9]+$#' ],
		];

		$resolved = [];
		foreach ( $rules as $key => $rule ) {
			$resolved[ $key ] = $rule[0];

			if ( ! array_key_exists( $key, $options ) ) {
				continue;
			}

			if ( is_string( $options[ $key ] ) && 1 === preg_match( $rule[1], $options[ $key ] ) ) {
				$resolved[ $key ] = $options[ $key ];
			} elseif ( $notify ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf( 'Invalid value for the "%1$s" boot option; the default "%2$s" is used.', $key, $rule[0] )
				);
			}
		}

		if ( $notify ) {
			foreach ( array_diff_key( $options, $rules ) as $key => $unused ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf( 'Unknown boot option "%s" was ignored.', (string) $key )
				);
			}
		}

		return $resolved;
	}

	/**
	 * Raise a _doing_it_wrong() notice when WordPress is loaded.
	 *
	 * @param string $function_name Function that was called wrongly.
	 * @param string $message       Explanation.
	 * @return void
	 */
	private static function doing_it_wrong( string $function_name, string $message ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong( esc_html( $function_name ), esc_html( $message ), '0.2.0' );
		}
	}

	/**
	 * Reset the singleton instance (for testing)
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Load plugin text domain for translations
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		if ( function_exists( 'load_plugin_textdomain' ) ) {
			load_plugin_textdomain(
				'cassette-cmf',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/../../languages'
			);
		}
	}

	/**
	 * Get the new settings page handler
	 *
	 * @return New_Settings_Page_Handler
	 */
	public function get_new_settings_handler(): New_Settings_Page_Handler {
		return $this->new_settings_handler;
	}

	/**
	 * Get the existing settings page handler
	 *
	 * @return Existing_Settings_Page_Handler
	 */
	public function get_existing_settings_handler(): Existing_Settings_Page_Handler {
		return $this->existing_settings_handler;
	}

	/**
	 * Get the new CPT handler
	 *
	 * @return New_Post_Type_Handler
	 */
	public function get_new_cpt_handler(): New_Post_Type_Handler {
		return $this->new_cpt_handler;
	}

	/**
	 * Get the existing CPT handler
	 *
	 * @return Existing_Post_Type_Handler
	 */
	public function get_existing_cpt_handler(): Existing_Post_Type_Handler {
		return $this->existing_cpt_handler;
	}

	/**
	 * Get the new taxonomy handler
	 *
	 * @return New_Taxonomy_Handler
	 */
	public function get_new_taxonomy_handler(): New_Taxonomy_Handler {
		return $this->new_taxonomy_handler;
	}

	/**
	 * Get the existing taxonomy handler
	 *
	 * @return Existing_Taxonomy_Handler
	 */
	public function get_existing_taxonomy_handler(): Existing_Taxonomy_Handler {
		return $this->existing_taxonomy_handler;
	}

	/**
	 * Register configuration from array
	 *
	 * Accepts a configuration array and registers custom post types,
	 * settings pages, taxonomies, and their associated fields.
	 *
	 * Filterable via 'cassette_cmf_register_config' (the whole array), and,
	 * per entity, 'cassette_cmf_{cpt,taxonomy,settings_page}_config' /
	 * '..._config_{id}' and 'cassette_cmf_fields' / 'cassette_cmf_fields_{id}'
	 * — see register_cpt_from_array(), register_taxonomy_from_array(), and
	 * register_settings_page_from_array().
	 *
	 * @api
	 * @param array<string, mixed> $config Configuration array.
	 * @return self
	 * @throws \InvalidArgumentException If configuration is invalid.
	 */
	public function register_from_array( array $config ): self {
		if ( function_exists( 'apply_filters' ) ) {
			$config = apply_filters( 'cassette_cmf_register_config', $config );
		}

		// Register custom post types
		if ( ! empty( $config['cpts'] ) && is_array( $config['cpts'] ) ) {
			foreach ( $config['cpts'] as $cpt_config ) {
				$this->register_cpt_from_array( $cpt_config );
			}
		}

		// Register taxonomies
		if ( ! empty( $config['taxonomies'] ) && is_array( $config['taxonomies'] ) ) {
			foreach ( $config['taxonomies'] as $taxonomy_config ) {
				$this->register_taxonomy_from_array( $taxonomy_config );
			}
		}

		// Register settings pages
		if ( ! empty( $config['settings_pages'] ) && is_array( $config['settings_pages'] ) ) {
			foreach ( $config['settings_pages'] as $page_config ) {
				$this->register_settings_page_from_array( $page_config );
			}
		}

		// Trigger late registration if hooks have already fired
		$this->trigger_late_registration();

		return $this;
	}

	/**
	 * Apply a broad and an id-suffixed WordPress filter to a value
	 *
	 * The id-suffixed hook lets an addon target one entity or field group
	 * without walking the whole configuration array. It omits $id from
	 * its own callback arguments, since the id is already encoded in the
	 * hook name; $extra_args (e.g. an entity type context) is passed to
	 * both hooks.
	 *
	 * Guarded by function_exists() so callers keep working when
	 * WordPress isn't loaded (e.g. the unit test suite).
	 *
	 * @param string       $tag         Base filter tag.
	 * @param mixed        $value       Value to filter.
	 * @param string       $id          Entity or field-group id, appended to $tag for the specific hook.
	 * @param array<mixed> $extra_args  Additional arguments passed after $value/$id to both hooks.
	 * @return mixed The filtered value, or $value unchanged if apply_filters() is unavailable.
	 */
	private function apply_id_filters( string $tag, $value, string $id, array $extra_args = [] ) {
		if ( ! function_exists( 'apply_filters' ) ) {
			return $value;
		}

		$value = apply_filters( $tag, $value, $id, ...$extra_args );

		return apply_filters( $tag . '_' . $id, $value, ...$extra_args );
	}

	/**
	 * Register a custom post type from array configuration
	 *
	 * Filterable via 'cassette_cmf_cpt_config' / 'cassette_cmf_cpt_config_{id}'
	 * (the whole CPT config) and 'cassette_cmf_fields' / 'cassette_cmf_fields_{id}'
	 * (just the fields array), so an addon can add, remove, or modify fields
	 * on a CPT it did not define.
	 *
	 * @param array<string, mixed> $config CPT configuration.
	 * @return void
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	private function register_cpt_from_array( array $config ): void {
		if ( empty( $config['id'] ) ) {
			throw new \InvalidArgumentException( 'CPT configuration must include "id".' );
		}

		$post_type = $config['id'];
		$config    = $this->apply_id_filters( 'cassette_cmf_cpt_config', $config, $post_type );

		$args   = $config['args'] ?? [];
		$fields = $this->apply_id_filters( 'cassette_cmf_fields', $config['fields'] ?? [], $post_type, [ 'cpt' ] );

		// Check if this is an existing post type
		$is_existing = function_exists( 'post_type_exists' ) && post_type_exists( $post_type );

		if ( $is_existing ) {
			// Add fields to existing post type
			if ( ! empty( $fields ) ) {
				$this->existing_cpt_handler->add_fields( $post_type, $fields );
			}
		} else {
			// Create new post type
			if ( ! empty( $args ) ) {
				$this->new_cpt_handler->add_post_type( $post_type, $args );
			}

			// Add fields
			if ( ! empty( $fields ) ) {
				$this->new_cpt_handler->add_fields( $post_type, $fields );
			}
		}
	}

	/**
	 * Map the schema's settings page key names onto the names the code reads
	 *
	 * `schema.json` documents `slug`, `icon` and `parent`; the code reads
	 * `menu_slug`, `icon_url` and `parent_slug`. Both spellings are accepted.
	 * When both are given the code name wins and a `_doing_it_wrong()` notice
	 * is raised. The alias key is always removed from the returned config.
	 * Only used when a new page is created: a config whose only page key is an
	 * alias (for example `parent`) keeps adding fields to an existing page.
	 *
	 * @param array<string, mixed> $config  Settings page configuration.
	 * @param string               $page_id Settings page id.
	 * @return array<string, mixed>
	 */
	private function apply_settings_page_aliases( array $config, string $page_id ): array {
		$aliases = [
			'slug'   => 'menu_slug',
			'icon'   => 'icon_url',
			'parent' => 'parent_slug',
		];

		foreach ( $aliases as $alias => $key ) {
			if ( ! array_key_exists( $alias, $config ) ) {
				continue;
			}

			if ( array_key_exists( $key, $config ) ) {
				self::doing_it_wrong(
					__METHOD__,
					sprintf(
						'Settings page "%1$s" sets both "%2$s" and "%3$s"; "%3$s" is used and "%2$s" is ignored.',
						$page_id,
						$alias,
						$key
					)
				);
			} else {
				$config[ $key ] = $config[ $alias ];
			}

			unset( $config[ $alias ] );
		}

		return $config;
	}

	/**
	 * Register a settings page from array configuration
	 *
	 * Filterable via 'cassette_cmf_settings_page_config' /
	 * 'cassette_cmf_settings_page_config_{id}' (the whole page config) and
	 * 'cassette_cmf_fields' / 'cassette_cmf_fields_{id}' (just the fields
	 * array), so an addon can add, remove, or modify fields on a settings
	 * page it did not define.
	 *
	 * @param array<string, mixed> $config Settings page configuration.
	 * @return void
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	private function register_settings_page_from_array( array $config ): void {
		if ( empty( $config['id'] ) ) {
			throw new \InvalidArgumentException( 'Settings page configuration must include "id".' );
		}

		$page_id = $config['id'];
		$config  = $this->apply_id_filters( 'cassette_cmf_settings_page_config', $config, $page_id );

		$fields = $this->apply_id_filters( 'cassette_cmf_fields', $config['fields'] ?? [], $page_id, [ 'settings_page' ] );

		// Check if this is creating a new settings page or adding to existing
		$settings_properties = [
			'page_title',
			'menu_title',
			'capability',
			'menu_slug',
			'callback',
			'icon_url',
			'position',
			'parent_slug',
		];

		$has_settings_config = false;
		foreach ( $settings_properties as $prop ) {
			if ( isset( $config[ $prop ] ) ) {
				$has_settings_config = true;
				break;
			}
		}

		if ( $has_settings_config ) {
			// Create new settings page. The schema's key names are mapped only here: on their own
			// they do not turn an "add fields to an existing page" config into a new page.
			$page_args = $this->apply_settings_page_aliases( $config, $page_id );
			unset( $page_args['fields'] );
			$this->new_settings_handler->add_page( $page_id, $page_args );

			// Add fields to new page.
			if ( ! empty( $fields ) ) {
				$this->new_settings_handler->add_fields( $page_id, $fields );
			}
		} elseif ( ! empty( $fields ) ) {
			// Add fields to existing settings page.
			$this->existing_settings_handler->add_fields( $page_id, $fields );
		}
	}

	/**
	 * Register a taxonomy from array configuration
	 *
	 * Filterable via 'cassette_cmf_taxonomy_config' /
	 * 'cassette_cmf_taxonomy_config_{id}' (the whole taxonomy config) and
	 * 'cassette_cmf_fields' / 'cassette_cmf_fields_{id}' (just the fields
	 * array), so an addon can add, remove, or modify fields on a taxonomy
	 * it did not define.
	 *
	 * @param array<string, mixed> $config Taxonomy configuration.
	 * @return void
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	private function register_taxonomy_from_array( array $config ): void {
		if ( empty( $config['id'] ) ) {
			throw new \InvalidArgumentException( 'Taxonomy configuration must include "id".' );
		}

		$taxonomy = $config['id'];
		$config   = $this->apply_id_filters( 'cassette_cmf_taxonomy_config', $config, $taxonomy );

		$args        = $config['args'] ?? [];
		$object_type = $config['object_type'] ?? [ 'post' ];
		$fields      = $this->apply_id_filters( 'cassette_cmf_fields', $config['fields'] ?? [], $taxonomy, [ 'taxonomy' ] );

		// Ensure object_type is an array
		if ( ! is_array( $object_type ) ) {
			$object_type = [ $object_type ];
		}

		// Check if this is an existing taxonomy
		$is_existing = function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy );

		if ( $is_existing ) {
			// Add fields to existing taxonomy
			if ( ! empty( $fields ) ) {
				$this->existing_taxonomy_handler->add_fields( $taxonomy, $fields );
			}
		} else {
			// Create new taxonomy
			if ( ! empty( $args ) ) {
				$this->new_taxonomy_handler->add_taxonomy( $taxonomy, $args, $object_type );
			}

			// Add fields
			if ( ! empty( $fields ) ) {
				$this->new_taxonomy_handler->add_fields( $taxonomy, $fields );
			}
		}
	}

	/**
	 * Trigger late registration if hooks have already fired
	 *
	 * @return void
	 */
	private function trigger_late_registration(): void {
		if ( ! function_exists( 'did_action' ) ) {
			return;
		}

		// Register CPTs and taxonomies if 'init' has already fired
		if ( did_action( 'init' ) ) {
			$this->new_cpt_handler->register_post_types();
			$this->new_taxonomy_handler->register_taxonomies();
		}

		// Register admin pages if 'admin_menu' has already fired
		if ( did_action( 'admin_menu' ) ) {
			$this->new_settings_handler->register_pages();
		}

		// Register settings and taxonomy fields if 'admin_init' has already fired
		if ( did_action( 'admin_init' ) ) {
			$this->new_settings_handler->register_settings();
			$this->existing_settings_handler->register_settings();
			$this->new_taxonomy_handler->register_taxonomy_fields();
			$this->existing_taxonomy_handler->register_taxonomy_fields();
		}
	}

	/**
	 * Register configuration from JSON
	 *
	 * @api
	 * @param string $path_or_json File path to JSON file or JSON string.
	 * @param bool   $validate     Whether to validate against schema (default: true).
	 * @return self
	 * @throws \InvalidArgumentException If JSON is invalid or validation fails.
	 */
	public function register_from_json( string $path_or_json, bool $validate = true ): self {
		$json_string = $this->get_json_content( $path_or_json );

		$config = json_decode( $json_string, true );

		if ( null === $config ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException( 'Invalid JSON: ' . json_last_error_msg() );
		}

		if ( ! is_array( $config ) ) {
			throw new \InvalidArgumentException( 'JSON must decode to an array/object' );
		}

		if ( $validate ) {
			$validator = new Schema_Validator();
			if ( ! $validator->validate( $config ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
				throw new \InvalidArgumentException( $validator->get_error_message() );
			}
		}

		return $this->register_from_array( $config );
	}

	/**
	 * Get JSON content from file path or string
	 *
	 * @param string $path_or_json File path or JSON string.
	 * @return string JSON content.
	 * @throws \InvalidArgumentException If file doesn't exist or is not readable.
	 */
	private function get_json_content( string $path_or_json ): string {
		if ( file_exists( $path_or_json ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $path_or_json );
			if ( false === $content ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
				throw new \InvalidArgumentException( "Unable to read file: {$path_or_json}" );
			}
			return $content;
		}

		return $path_or_json;
	}

	/**
	 * Register a custom field type
	 *
	 * @api
	 * @param string $type       Field type name.
	 * @param string $class_name Field class name.
	 * @return self
	 * @throws \InvalidArgumentException If class doesn't exist or doesn't implement Field_Interface.
	 */
	public function register_field_type( string $type, string $class_name ): self {
		Field_Factory::register_type( $type, $class_name );
		return $this;
	}

	/**
	 * Get configuration options
	 *
	 * @api
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		return $this->options;
	}

	/**
	 * Set a configuration option
	 *
	 * @api
	 * @param string $key   Option key.
	 * @param mixed  $value Option value.
	 * @return self
	 */
	public function set_option( string $key, $value ): self {
		$this->options[ $key ] = $value;
		return $this;
	}

	/**
	 * Get a configuration option
	 *
	 * @api
	 * @param string $key           Option key.
	 * @param mixed  $default_value Default value if option doesn't exist.
	 * @return mixed
	 */
	public function get_option( string $key, $default_value = null ) {
		return $this->options[ $key ] ?? $default_value;
	}

	/**
	 * Retrieve a Cassette-CMF field value
	 *
	 * This is a universal method to get field values regardless of their storage location.
	 * It automatically handles post meta, term meta, and settings options.
	 *
	 * @api
	 * @param string     $field_name    The field name as defined in the Cassette-CMF config.
	 * @param int|string $context       The context: post ID (int), term ID (int), or settings page ID (string).
	 * @param string     $context_type  The type of context: 'post', 'term', or 'settings'. Default 'post'.
	 * @param mixed      $default_value Default value if field value is empty. Default empty string.
	 * @return mixed The field value.
	 */
	public function get_field( string $field_name, $context, string $context_type = 'post', $default_value = '' ) {
		$value = null;

		switch ( $context_type ) {
			case 'post':
				$value = $this->get_post_field( $field_name, (int) $context );
				break;

			case 'term':
				$value = $this->get_term_field( $field_name, (int) $context );
				break;

			case 'settings':
				$value = $this->get_settings_field( $field_name, (string) $context );
				break;

			default:
				return $default_value;
		}

		// Return default if value is empty
		if ( null === $value || '' === $value ) {
			return $default_value;
		}

		return $value;
	}

	/**
	 * Retrieve a post meta field value
	 *
	 * @api
	 * @param string $field_name    The field name.
	 * @param int    $post_id       The post ID.
	 * @param mixed  $default_value Default value if field is empty.
	 * @return mixed The field value.
	 */
	public function get_post_field( string $field_name, int $post_id, $default_value = '' ) {
		if ( '' === $field_name || ! function_exists( 'get_post_meta' ) ) {
			return $default_value;
		}

		$value = get_post_meta( $post_id, $field_name, true );

		return ( '' === $value || null === $value ) ? $default_value : $value;
	}

	/**
	 * Retrieve a term meta field value
	 *
	 * @api
	 * @param string $field_name    The field name.
	 * @param int    $term_id       The term ID.
	 * @param mixed  $default_value Default value if field is empty.
	 * @return mixed The field value.
	 */
	public function get_term_field( string $field_name, int $term_id, $default_value = '' ) {
		if ( '' === $field_name || ! function_exists( 'get_term_meta' ) ) {
			return $default_value;
		}

		$value = get_term_meta( $term_id, $field_name, true );

		return ( '' === $value || null === $value ) ? $default_value : $value;
	}

	/**
	 * Retrieve a settings field value
	 *
	 * @api
	 * @param string $field_name    The field name.
	 * @param string $page_id       The settings page ID.
	 * @param mixed  $default_value Default value if field is empty.
	 * @return mixed The field value.
	 */
	public function get_settings_field( string $field_name, string $page_id, $default_value = '' ) {
		if ( '' === $field_name || ! function_exists( 'get_option' ) ) {
			return $default_value;
		}

		// Settings fields are stored as {page_id}_{field_name}
		$option_name = $page_id . '_' . $field_name;

		$value = get_option( $option_name, $default_value );

		// A secret field never hands out its value here, only the mask.
		$secret = $this->get_secret_field( $field_name, $page_id );
		if ( null !== $secret ) {
			return $secret->mask( get_option( $option_name, '' ) );
		}

		return $value;
	}

	/**
	 * Get the real value of a secret field
	 *
	 * The only API that returns a secret in clear text: decrypts when the
	 * field uses `encrypt`. Returns '' when the field is unset, is not a
	 * registered `secret` field, or cannot be decrypted (for example after
	 * the AUTH salt changed; an admin notice then asks to re-enter it).
	 * get_field() and the REST API return only the mask.
	 *
	 * @since 0.2.0
	 * @api
	 *
	 * @param string $field_name The secret field name.
	 * @param string $page_id    The settings page ID.
	 * @return string
	 */
	public function get_secret( string $field_name, string $page_id ): string {
		$field = $this->get_secret_field( $field_name, $page_id );

		if ( null === $field || ! function_exists( 'get_option' ) ) {
			return '';
		}

		return $field->reveal( get_option( $field->get_option_name( $page_id ), '' ) );
	}

	/**
	 * Find a registered secret field of a settings page.
	 *
	 * @param string $field_name Field name.
	 * @param string $page_id    Settings page ID.
	 * @return Secret_Field|null
	 */
	private function get_secret_field( string $field_name, string $page_id ): ?Secret_Field {
		$field = $this->new_settings_handler->get_fields( $page_id )[ $field_name ]
			?? $this->existing_settings_handler->get_fields( $page_id )[ $field_name ]
			?? null;

		return $field instanceof Secret_Field ? $field : null;
	}

	/**
	 * Get the configuration of a registered field
	 *
	 * Resolves the context to the entity the field was registered for, then
	 * looks the field up by name among the fields Cassette-CMF registered
	 * there (both the "new" and the "existing" handlers):
	 *
	 * - 'post':     $context is a post ID; its post type is used.
	 * - 'term':     $context is a term ID; its taxonomy is used.
	 * - 'settings': $context is a settings page ID.
	 * - any other context type resolves to null.
	 *
	 * Fields nested in group, tabs and metabox containers are registered under
	 * their own name and are found. Fields inside a repeater row are not
	 * registered individually and resolve to null.
	 *
	 * @since 0.2.0
	 * @api
	 *
	 * @param string     $field_name   The field name as defined in the Cassette-CMF config.
	 * @param int|string $context      Post ID, term ID, or settings page ID.
	 * @param string     $context_type 'post', 'term', or 'settings'. Default 'post'.
	 * @return array<string, mixed>|null The field config (including 'type' and 'name'), or null if not registered.
	 */
	public function get_registered_field( string $field_name, $context, string $context_type = 'post' ): ?array {
		if ( '' === $field_name ) {
			return null;
		}

		$field = null;

		switch ( $context_type ) {
			case 'post':
				$post_type = $this->resolve_post_type( $context );
				if ( null !== $post_type ) {
					$field = $this->new_cpt_handler->get_fields( $post_type )[ $field_name ]
						?? $this->existing_cpt_handler->get_fields( $post_type )[ $field_name ]
						?? null;
				}
				break;

			case 'term':
				$taxonomy = $this->resolve_taxonomy( $context );
				if ( null !== $taxonomy ) {
					$field = $this->new_taxonomy_handler->get_fields( $taxonomy )[ $field_name ]
						?? $this->existing_taxonomy_handler->get_fields( $taxonomy )[ $field_name ]
						?? null;
				}
				break;

			case 'settings':
				$page_id = is_scalar( $context ) ? (string) $context : '';
				if ( '' !== $page_id ) {
					$field = $this->new_settings_handler->get_fields( $page_id )[ $field_name ]
						?? $this->existing_settings_handler->get_fields( $page_id )[ $field_name ]
						?? null;
				}
				break;
		}

		if ( ! $field instanceof Field_Interface ) {
			return null;
		}

		$config = $field instanceof Abstract_Field ? $field->get_all_config() : [];

		$config['name'] = $field->get_name();
		$config['type'] = $field->get_type();

		return $config;
	}

	/**
	 * Whether a registered field may be exposed to companion libraries
	 *
	 * True only when get_registered_field() finds the field, its 'bindings'
	 * config key is strictly boolean true, and its type is neither 'password'
	 * nor 'secret'. Cassette-CMF itself ignores the 'bindings' key; it exists
	 * so a companion library (e.g. a block-bindings source) can expose only
	 * opted-in fields.
	 *
	 * @since 0.2.0
	 * @api
	 *
	 * @param string     $field_name   The field name as defined in the Cassette-CMF config.
	 * @param int|string $context      Post ID, term ID, or settings page ID.
	 * @param string     $context_type 'post', 'term', or 'settings'. Default 'post'.
	 * @return bool
	 */
	public function is_field_public( string $field_name, $context, string $context_type = 'post' ): bool {
		$config = $this->get_registered_field( $field_name, $context, $context_type );

		if ( null === $config ) {
			return false;
		}

		if ( ! isset( $config['bindings'] ) || true !== $config['bindings'] ) {
			return false;
		}

		return ! in_array( $config['type'], [ 'password', 'secret' ], true );
	}

	/**
	 * Resolve a post ID to its post type
	 *
	 * @param mixed $context Post ID.
	 * @return string|null Post type, or null if the post does not exist.
	 */
	private function resolve_post_type( $context ): ?string {
		if ( ! is_numeric( $context ) || ! function_exists( 'get_post_type' ) ) {
			return null;
		}

		$post_type = get_post_type( (int) $context );

		return is_string( $post_type ) && '' !== $post_type ? $post_type : null;
	}

	/**
	 * Resolve a term ID to its taxonomy
	 *
	 * @param mixed $context Term ID.
	 * @return string|null Taxonomy, or null if the term does not exist.
	 */
	private function resolve_taxonomy( $context ): ?string {
		if ( ! is_numeric( $context ) || ! function_exists( 'get_term' ) ) {
			return null;
		}

		$term = get_term( (int) $context );

		return $term instanceof \WP_Term && '' !== $term->taxonomy ? $term->taxonomy : null;
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {
		// Singleton pattern - prevent cloning
	}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
