<?php

/**
 * Manager class for Cassette-CMF
 *
 * Central registry and bootstrap for the Content Modeling Framework.
 * Coordinates registration of custom post types, settings pages, and fields.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core;

use Pedalcms\CassetteCmf\Core\Handlers\New_Settings_Page_Handler;
use Pedalcms\CassetteCmf\Core\Handlers\Existing_Settings_Page_Handler;
use Pedalcms\CassetteCmf\Core\Handlers\New_Post_Type_Handler;
use Pedalcms\CassetteCmf\Core\Handlers\Existing_Post_Type_Handler;
use Pedalcms\CassetteCmf\Core\Handlers\New_Taxonomy_Handler;
use Pedalcms\CassetteCmf\Core\Handlers\Existing_Taxonomy_Handler;
use Pedalcms\CassetteCmf\Field\Field_Factory;
use Pedalcms\CassetteCmf\Json\Schema_Validator;

/**
 * Manager class - Central coordination point for Cassette-CMF
 *
 * Provides a singleton pattern for managing registration and configuration
 * of custom post types, settings pages, and field definitions.
 */
class Manager {


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
	 * @param array<string, mixed> $config Configuration array.
	 * @return self
	 * @throws \InvalidArgumentException If configuration is invalid.
	 */
	public function register_from_array( array $config ): self {
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
	 * Register a custom post type from array configuration
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
		$args      = $config['args'] ?? [];
		$fields    = $config['fields'] ?? [];

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
	 * Register a settings page from array configuration
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
		$fields  = $config['fields'] ?? [];

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
			// Create new settings page
			$page_args = $config;
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
	 * @param array<string, mixed> $config Taxonomy configuration.
	 * @return void
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	private function register_taxonomy_from_array( array $config ): void {
		if ( empty( $config['id'] ) ) {
			throw new \InvalidArgumentException( 'Taxonomy configuration must include "id".' );
		}

		$taxonomy    = $config['id'];
		$args        = $config['args'] ?? [];
		$object_type = $config['object_type'] ?? [ 'post' ];
		$fields      = $config['fields'] ?? [];

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
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		return $this->options;
	}

	/**
	 * Set a configuration option
	 *
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

		return get_option( $option_name, $default_value );
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
