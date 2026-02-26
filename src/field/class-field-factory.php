<?php
/**
 * FieldFactory for Cassette-CMF
 *
 * Factory class for creating field instances from configuration arrays.
 * Provides a registry for field types and supports custom field registration.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field;

use Pedalcms\CassetteCmf\Field\Fields\Text_Field;
use Pedalcms\CassetteCmf\Field\Fields\Textarea_Field;
use Pedalcms\CassetteCmf\Field\Fields\Select_Field;
use Pedalcms\CassetteCmf\Field\Fields\Checkbox_Field;
use Pedalcms\CassetteCmf\Field\Fields\Radio_Field;
use Pedalcms\CassetteCmf\Field\Fields\Number_Field;
use Pedalcms\CassetteCmf\Field\Fields\Email_Field;
use Pedalcms\CassetteCmf\Field\Fields\URL_Field;
use Pedalcms\CassetteCmf\Field\Fields\Date_Field;
use Pedalcms\CassetteCmf\Field\Fields\Password_Field;
use Pedalcms\CassetteCmf\Field\Fields\Color_Field;
use Pedalcms\CassetteCmf\Field\Fields\Tabs_Field;
use Pedalcms\CassetteCmf\Field\Fields\Metabox_Field;
use Pedalcms\CassetteCmf\Field\Fields\Repeater_Field;
use Pedalcms\CassetteCmf\Field\Fields\Wysiwyg_Field;
use Pedalcms\CassetteCmf\Field\Fields\Group_Field;
use Pedalcms\CassetteCmf\Field\Fields\Custom_HTML_Field;
use Pedalcms\CassetteCmf\Field\Fields\Upload_Field;

/**
 * Field_Factory class
 *
 * Creates field instances from configuration arrays and maintains
 * a registry of available field types.
 */
class Field_Factory {

	/**
	 * Registered field types
	 *
	 * @var array<string, string> Map of type name to class name
	 */
	private static array $field_types = [];

	/**
	 * Whether default field types have been registered
	 *
	 * @var bool
	 */
	private static bool $defaults_registered = false;

	/**
	 * Register a field type
	 *
	 * Allows registration of custom field types or overriding core field types.
	 *
	 * @param string $type       Field type identifier.
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 * @throws \InvalidArgumentException If class doesn't implement Field_Interface.
	 */
	public static function register_type( string $type, string $class_name ): void {
		// Validate class exists.
		if ( ! class_exists( $class_name ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException(
				sprintf( 'Class "%s" does not exist.', $class_name )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// Validate class implements Field_Interface.
		$interfaces = class_implements( $class_name );
		if ( ! in_array( Field_Interface::class, $interfaces ? $interfaces : [], true ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException(
				sprintf( 'Class "%s" must implement Field_Interface.', $class_name )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		self::$field_types[ $type ] = $class_name;
	}

	/**
	 * Register default core field types
	 *
	 * @return void
	 */
	public static function register_defaults(): void {
		if ( self::$defaults_registered ) {
			return;
		}

		// Preserve any custom types that were registered before defaults
		$custom_types = self::$field_types;

		self::$field_types = [
			'text'        => Text_Field::class,
			'textarea'    => Textarea_Field::class,
			'select'      => Select_Field::class,
			'checkbox'    => Checkbox_Field::class,
			'radio'       => Radio_Field::class,
			'number'      => Number_Field::class,
			'email'       => Email_Field::class,
			'url'         => URL_Field::class,
			'date'        => Date_Field::class,
			'password'    => Password_Field::class,
			'color'       => Color_Field::class,
			'tabs'        => Tabs_Field::class,
			'metabox'     => Metabox_Field::class,
			'repeater'    => Repeater_Field::class,
			'wysiwyg'     => Wysiwyg_Field::class,
			'group'       => Group_Field::class,
			'custom_html' => Custom_HTML_Field::class,
			'upload'      => Upload_Field::class,
		];

		// Merge back any custom types
		self::$field_types = array_merge( self::$field_types, $custom_types );

		self::$defaults_registered = true;
	}

	/**
	 * Create a field instance from configuration
	 *
	 * @param array<string, mixed> $config Field configuration array.
	 * @return Field_Interface Field instance.
	 * @throws \InvalidArgumentException If required config is missing or type is unknown.
	 */
	public static function create( array $config ): Field_Interface {
		// Ensure defaults are registered
		if ( ! self::$defaults_registered ) {
			self::register_defaults();
		}

		// Validate required config
		if ( empty( $config['name'] ) ) {
			throw new \InvalidArgumentException( 'Field config must include "name".' );
		}

		if ( empty( $config['type'] ) ) {
			throw new \InvalidArgumentException( 'Field config must include "type".' );
		}

		$type = $config['type'];
		$name = $config['name'];

		// Check if field type is registered.
		if ( ! isset( self::$field_types[ $type ] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages don't need escaping.
			throw new \InvalidArgumentException(
				sprintf( 'Unknown field type "%s". Register it with Field_Factory::register_type().', $type )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$class_name = self::$field_types[ $type ];

		// Create and return field instance
		return new $class_name( $name, $type, $config );
	}

	/**
	 * Create multiple fields from configuration array
	 *
	 * @param array<string, array<string, mixed>> $fields_config Array of field configurations.
	 * @return array<string, Field_Interface> Array of field instances keyed by field name.
	 */
	public static function create_multiple( array $fields_config ): array {
		$fields = [];

		foreach ( $fields_config as $key => $config ) {
			// If config doesn't have a name, use the array key
			if ( empty( $config['name'] ) ) {
				$config['name'] = $key;
			}

			$fields[ $config['name'] ] = self::create( $config );
		}

		return $fields;
	}

	/**
	 * Get all registered field types
	 *
	 * @return array<string, string> Map of type names to class names.
	 */
	public static function get_registered_types(): array {
		if ( ! self::$defaults_registered ) {
			self::register_defaults();
		}

		return self::$field_types;
	}

	/**
	 * Check if a field type is registered
	 *
	 * @param string $type Field type identifier.
	 * @return bool
	 */
	public static function has_type( string $type ): bool {
		if ( ! self::$defaults_registered ) {
			self::register_defaults();
		}

		return isset( self::$field_types[ $type ] );
	}

	/**
	 * Unregister a field type
	 *
	 * Useful for testing or replacing field types.
	 *
	 * @param string $type Field type identifier.
	 * @return void
	 */
	public static function unregister_type( string $type ): void {
		unset( self::$field_types[ $type ] );
	}

	/**
	 * Reset the factory (mainly for testing)
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$field_types         = [];
		self::$defaults_registered = false;
	}
}
