<?php
/**
 * Cassette-CMF Main Entry Point
 *
 * This is the primary facade class for Cassette-CMF. It provides a simple, unified API
 * for all Cassette-CMF functionality. Users only need to import this single class.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf;

use Pedalcms\CassetteCmf\Core\Manager;

/**
 * Class CassetteCmf
 *
 * Main entry point and facade for Cassette-CMF functionality.
 *
 * Usage:
 *   use Pedalcms\CassetteCmf\CassetteCmf;
 *
 *   // Register configuration
 *   CassetteCmf::init()->register_from_array( $config );
 *   CassetteCmf::init()->register_from_json( $json_file );
 *
 *   // Retrieve field values
 *   $value = CassetteCmf::get_field( 'field_name', $post_id );
 *   $value = CassetteCmf::get_field( 'field_name', $term_id, 'term' );
 *   $value = CassetteCmf::get_field( 'field_name', 'settings-page-id', 'settings' );
 *
 * @since 1.0.0
 */
class CassetteCmf {

	/**
	 * Get the Manager instance
	 *
	 * Returns the singleton Manager instance for registering CPTs, taxonomies,
	 * settings pages, and fields.
	 *
	 * @since 1.0.0
	 *
	 * @return Manager The Manager singleton instance.
	 */
	public static function init(): Manager {
		return Manager::init();
	}

	/**
	 * Register configuration from a PHP array
	 *
	 * Convenience method that initializes and registers in one call.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration array with 'cpts', 'taxonomies', and/or 'settings_pages' keys.
	 * @return Manager The Manager instance for chaining.
	 */
	public static function register_from_array( array $config ): Manager {
		return Manager::init()->register_from_array( $config );
	}

	/**
	 * Register configuration from a JSON file or string
	 *
	 * Convenience method that initializes and registers in one call.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json_path_or_string Path to JSON file or JSON string.
	 * @param bool   $validate            Whether to validate against schema. Default true.
	 * @return Manager The Manager instance for chaining.
	 */
	public static function register_from_json( string $json_path_or_string, bool $validate = true ): Manager {
		return Manager::init()->register_from_json( $json_path_or_string, $validate );
	}

	/**
	 * Retrieve a field value
	 *
	 * Universal method to retrieve field values from any context:
	 * - Post meta (CPT fields)
	 * - Term meta (taxonomy fields)
	 * - Settings (options)
	 *
	 * @since 1.0.0
	 *
	 * @param string     $field_name    The field name as defined in the Cassette-CMF config.
	 * @param int|string $context       The context: post ID (int), term ID (int), or settings page ID (string).
	 * @param string     $context_type  The type of context: 'post', 'term', or 'settings'. Default 'post'.
	 * @param mixed      $default_value Default value if field value is empty. Default empty string.
	 * @return mixed The field value.
	 *
	 * @example Post meta (most common):
	 *   CassetteCmf::get_field( 'author_name', $post_id );
	 *   CassetteCmf::get_field( 'price', $post_id, 'post', 0 );
	 *
	 * @example Term meta:
	 *   CassetteCmf::get_field( 'category_color', $term_id, 'term' );
	 *   CassetteCmf::get_field( 'icon_class', $term_id, 'term', 'default-icon' );
	 *
	 * @example Settings:
	 *   CassetteCmf::get_field( 'api_key', 'my-settings', 'settings' );
	 *   CassetteCmf::get_field( 'theme_color', 'theme-options', 'settings', '#ffffff' );
	 */
	public static function get_field( string $field_name, $context, string $context_type = 'post', $default_value = '' ) {
		return Manager::init()->get_field( $field_name, $context, $context_type, $default_value );
	}

	/**
	 * Get a post/CPT meta field value
	 *
	 * Convenience method for retrieving post meta values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name    The field name.
	 * @param int    $post_id       The post ID.
	 * @param mixed  $default_value Default value if empty.
	 * @return mixed The field value.
	 */
	public static function get_post_field( string $field_name, int $post_id, $default_value = '' ) {
		return Manager::init()->get_post_field( $field_name, $post_id, $default_value );
	}

	/**
	 * Get a term meta field value
	 *
	 * Convenience method for retrieving term meta values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name    The field name.
	 * @param int    $term_id       The term ID.
	 * @param mixed  $default_value Default value if empty.
	 * @return mixed The field value.
	 */
	public static function get_term_field( string $field_name, int $term_id, $default_value = '' ) {
		return Manager::init()->get_term_field( $field_name, $term_id, $default_value );
	}

	/**
	 * Get a settings field value
	 *
	 * Convenience method for retrieving settings/options values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_name      The field name.
	 * @param string $settings_page_id The settings page ID.
	 * @param mixed  $default_value   Default value if empty.
	 * @return mixed The field value.
	 */
	public static function get_settings_field( string $field_name, string $settings_page_id, $default_value = '' ) {
		return Manager::init()->get_settings_field( $field_name, $settings_page_id, $default_value );
	}

	/**
	 * Register a custom field type
	 *
	 * @since 1.0.0
	 *
	 * @param string $type       The field type identifier (e.g., 'slider', 'icon-picker').
	 * @param string $class_name The fully qualified class name implementing the field.
	 * @return Manager The Manager instance for chaining.
	 */
	public static function register_field_type( string $type, string $class_name ): Manager {
		return Manager::init()->register_field_type( $type, $class_name );
	}
}
