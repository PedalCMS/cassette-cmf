<?php
/**
 * Settings_Schema - JSON schema and value mapping for settings pages
 *
 * Builds a JSON schema from field configuration and converts values between
 * their stored (classic) shape and their REST shape. Used by the REST
 * controller; usable by any other client of the same data.
 *
 * @package PedalCMS\CassetteCMF
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMF\Rest;

use PedalCMS\CassetteCMF\Field\Abstract_Field;
use PedalCMS\CassetteCMF\Field\Container_Field_Interface;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Field_Interface;
use PedalCMS\CassetteCMF\Field\Fields\Checkbox_Field;
use PedalCMS\CassetteCMF\Field\Fields\Custom_HTML_Field;
use PedalCMS\CassetteCMF\Field\Fields\Group_Field;
use PedalCMS\CassetteCMF\Field\Fields\Metabox_Field;
use PedalCMS\CassetteCMF\Field\Fields\Number_Field;
use PedalCMS\CassetteCMF\Field\Fields\Password_Field;
use PedalCMS\CassetteCMF\Field\Fields\Repeater_Field;
use PedalCMS\CassetteCMF\Field\Fields\Secret_Field;
use PedalCMS\CassetteCMF\Field\Fields\Select_Field;
use PedalCMS\CassetteCMF\Field\Fields\Tabs_Field;
use PedalCMS\CassetteCMF\Field\Fields\Upload_Field;

/**
 * Class Settings_Schema
 *
 * All methods are static and free of side effects.
 *
 * Storage facts this class relies on (they are the classic save contract):
 * every non-container field is stored in its own option; group, tabs and
 * metabox containers store nothing and their children are stored flat, so the
 * REST `values` object is flat too.
 *
 * @api
 */
class Settings_Schema {

	/**
	 * Collect the REST-visible, storable fields of a page
	 *
	 * Walks the page's top-level fields the way the classic renderer does:
	 * group and metabox containers are descended into; a top-level container of
	 * another kind (tabs, ...) is invalid on a settings page and skipped with
	 * its children; containers nested inside a group or metabox are descended
	 * into. `custom_html` fields (no value) and fields with
	 * `show_in_rest => false` are left out, and so are the descendants of a
	 * container with `show_in_rest => false`.
	 *
	 * @param array<int|string, Field_Interface> $top_level Top-level fields of the page.
	 * @return array<string, Field_Interface> Fields keyed by name.
	 */
	public static function collect_fields( array $top_level ): array {
		$collected = [];

		foreach ( $top_level as $field ) {
			if ( ! $field instanceof Field_Interface ) {
				continue;
			}

			if ( $field instanceof Container_Field_Interface && ! $field instanceof Group_Field && ! $field instanceof Metabox_Field ) {
				continue;
			}

			self::collect_into( $field, $collected );
		}

		return $collected;
	}

	/**
	 * Collect every storable field from a flat registry (REST flags ignored)
	 *
	 * Containers and `custom_html` fields are skipped. Used for operations on
	 * stored data that must cover all fields, such as schema upgrades.
	 *
	 * @param array<int|string, Field_Interface> $all_fields Flat registry (nested fields included).
	 * @return array<string, Field_Interface> Fields keyed by name.
	 */
	public static function storage_fields( array $all_fields ): array {
		$storable = [];

		foreach ( $all_fields as $field ) {
			if ( $field instanceof Field_Interface && self::is_storable( $field ) ) {
				$storable[ $field->get_name() ] = $field;
			}
		}

		return $storable;
	}

	/**
	 * Whether a field stores a value of its own.
	 *
	 * @param Field_Interface $field Field.
	 * @return bool
	 */
	public static function is_storable( Field_Interface $field ): bool {
		return ! $field instanceof Container_Field_Interface && ! $field instanceof Custom_HTML_Field;
	}

	/**
	 * Whether a field is writable over REST.
	 *
	 * @param Field_Interface $field Field.
	 * @return bool False for `readonly` / `disabled` fields.
	 */
	public static function is_writable( Field_Interface $field ): bool {
		return empty( $field->get_config( 'readonly' ) ) && empty( $field->get_config( 'disabled' ) );
	}

	/**
	 * Build the schema of a whole settings page response
	 *
	 * The response (and the write body) is `{ "values": { field: value, ... } }`.
	 *
	 * @param array<string, Field_Interface> $fields Fields from collect_fields().
	 * @param string                         $title  Schema title (usually the page ID).
	 * @return array<string, mixed>
	 */
	public static function build( array $fields, string $title = '' ): array {
		$properties = [];

		foreach ( $fields as $name => $field ) {
			$properties[ $name ] = self::field_schema( $field );
		}

		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $title,
			'type'       => 'object',
			'properties' => [
				'values' => [
					'description'          => 'Field values keyed by field name. Writes are partial: only the submitted fields change.',
					'type'                 => 'object',
					'properties'           => $properties,
					'additionalProperties' => false,
				],
			],
		];
	}

	/**
	 * Build the JSON schema of one field
	 *
	 * A field class may provide its own by implementing `get_rest_schema(): array`.
	 * The schema describes the REST shape of the value; value rules that only the
	 * field's own `validate()` can express (cross-field conditions, custom rules)
	 * are enforced there, not by the schema.
	 *
	 * @param Field_Interface $field Field.
	 * @return array<string, mixed>
	 */
	public static function field_schema( Field_Interface $field ): array {
		if ( method_exists( $field, 'get_rest_schema' ) ) {
			$custom = $field->get_rest_schema();
			if ( is_array( $custom ) ) {
				return $custom;
			}
		}

		$schema = [];

		$label = $field->get_label();
		if ( '' !== $label ) {
			$schema['title'] = $label;
		}

		$description = (string) $field->get_config( 'description', '' );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		$schema = array_merge( $schema, self::type_schema( $field ) );

		if ( $field->get_config( 'required' ) ) {
			$schema['required'] = true;
		}

		if ( ! self::is_writable( $field ) ) {
			$schema['readOnly'] = true;
		}

		self::apply_validation_rules( $field, $schema );

		return $schema;
	}

	/**
	 * Convert a stored option value to its REST shape
	 *
	 * @param Field_Interface $field  Field.
	 * @param mixed           $stored Stored value.
	 * @return mixed
	 */
	public static function to_rest_value( Field_Interface $field, $stored ) {
		if ( $field instanceof Secret_Field ) {
			return $field->mask( $stored );
		}

		if ( $field instanceof Checkbox_Field ) {
			if ( self::has_options( $field ) ) {
				return self::to_string_list( $stored );
			}

			return is_scalar( $stored ) && ! empty( $stored ) && '0' !== (string) $stored;
		}

		if ( $field instanceof Select_Field ) {
			if ( $field->get_config( 'multiple' ) ) {
				return self::to_string_list( $stored );
			}

			return is_scalar( $stored ) ? (string) $stored : '';
		}

		if ( $field instanceof Number_Field ) {
			return is_numeric( $stored ) ? $stored + 0 : null;
		}

		if ( $field instanceof Upload_Field ) {
			if ( is_int( $stored ) || ( is_string( $stored ) && ctype_digit( $stored ) ) ) {
				return (int) $stored;
			}

			return is_string( $stored ) ? $stored : '';
		}

		if ( $field instanceof Repeater_Field ) {
			return self::repeater_to_rest( $field, $stored );
		}

		if ( ! self::is_known_type( $field ) ) {
			return $stored;
		}

		return is_scalar( $stored ) ? (string) $stored : '';
	}

	/**
	 * Convert a REST input value to the raw shape the classic form would submit
	 *
	 * The result goes through the same before-save filters, `sanitize()` and
	 * `validate()` as a classic submission.
	 *
	 * @param Field_Interface $field Field.
	 * @param mixed           $input Value from the request body.
	 * @return array{value: mixed, error: string|null, skip: bool}
	 */
	public static function from_rest_value( Field_Interface $field, $input ): array {
		$ok = static function ( $value, bool $skip = false ): array {
			return [
				'value' => $value,
				'error' => null,
				'skip'  => $skip,
			];
		};

		$fail = static function ( string $message ): array {
			return [
				'value' => null,
				'error' => $message,
				'skip'  => false,
			];
		};

		if ( $field instanceof Secret_Field ) {
			if ( null === $input || is_string( $input ) ) {
				return $ok( $input );
			}

			if ( is_array( $input ) && [ 'clear' ] === array_keys( $input ) && true === $input['clear'] ) {
				return $ok( [ 'clear' => '1' ] );
			}

			return $fail( 'must be a string, null, or {"clear": true}.' );
		}

		if ( $field instanceof Password_Field ) {
			if ( null !== $input && ! is_string( $input ) ) {
				return $fail( 'must be a string.' );
			}

			// An empty password means "keep" over REST (GET never returns it).
			return $ok( (string) $input, '' === (string) $input );
		}

		if ( $field instanceof Checkbox_Field ) {
			if ( self::has_options( $field ) ) {
				if ( ! self::is_scalar_list( $input ) ) {
					return $fail( 'must be an array of values.' );
				}

				return self::all_in_options( $field, $input ) ? $ok( $input ) : $fail( 'contains an invalid option.' );
			}

			if ( true === $input || 1 === $input || '1' === $input ) {
				return $ok( '1' );
			}

			if ( false === $input || 0 === $input || '0' === $input || '' === $input || null === $input ) {
				return $ok( '0' );
			}

			return $fail( 'must be a boolean.' );
		}

		if ( $field instanceof Select_Field && $field->get_config( 'multiple' ) ) {
			if ( ! self::is_scalar_list( $input ) ) {
				return $fail( 'must be an array of values.' );
			}

			return self::all_in_options( $field, $input ) ? $ok( $input ) : $fail( 'contains an invalid option.' );
		}

		if ( $field instanceof Number_Field ) {
			if ( null === $input || '' === $input ) {
				return $ok( '' );
			}

			if ( ( is_int( $input ) || is_float( $input ) || is_string( $input ) ) && is_numeric( $input ) ) {
				return $ok( $input );
			}

			return $fail( 'must be a number.' );
		}

		if ( $field instanceof Upload_Field ) {
			if ( null === $input ) {
				return $ok( '' );
			}

			return is_int( $input ) || is_string( $input ) ? $ok( $input ) : $fail( 'must be an attachment ID or a URL.' );
		}

		if ( $field instanceof Repeater_Field ) {
			return self::repeater_from_rest( $field, $input );
		}

		if ( ! self::is_known_type( $field ) ) {
			return $ok( $input );
		}

		if ( null === $input ) {
			return $ok( '' );
		}

		// Unlike the classic form, which silently blanks an unknown choice, REST reports it.
		if ( in_array( $field->get_type(), [ 'select', 'radio' ], true ) && ( is_string( $input ) || is_int( $input ) ) && '' !== $input && ! self::all_in_options( $field, [ $input ] ) ) {
			return $fail( 'contains an invalid option.' );
		}

		if ( is_string( $input ) || ( is_int( $input ) && in_array( $field->get_type(), [ 'select', 'radio' ], true ) ) ) {
			return $ok( $input );
		}

		return $fail( 'must be a string.' );
	}

	/**
	 * Walk a field (and its children) into the collection.
	 *
	 * @param Field_Interface                $field     Field.
	 * @param array<string, Field_Interface> $collected Collection (by reference).
	 * @return void
	 */
	private static function collect_into( Field_Interface $field, array &$collected ): void {
		if ( false === $field->get_config( 'show_in_rest' ) ) {
			return;
		}

		if ( $field instanceof Container_Field_Interface ) {
			foreach ( $field->get_nested_fields() as $config ) {
				if ( ! is_array( $config ) || empty( $config['name'] ) ) {
					continue;
				}

				try {
					$child = Field_Factory::create( $config );
				} catch ( \InvalidArgumentException $e ) {
					continue;
				}

				self::collect_into( $child, $collected );
			}

			return;
		}

		if ( self::is_storable( $field ) ) {
			$collected[ $field->get_name() ] = $field;
		}
	}

	/**
	 * Core type, format and enum keywords for a field.
	 *
	 * @param Field_Interface $field Field.
	 * @return array<string, mixed>
	 */
	private static function type_schema( Field_Interface $field ): array {
		$type = $field->get_type();

		if ( $field instanceof Secret_Field ) {
			return [
				'type'        => [ 'string', 'object', 'null' ],
				'description' => 'Write-only. Reads return a mask ("••••" and the last four characters, or "" when unset). Write a new string to replace the value; send the unchanged mask, null, or omit the key to keep it; send {"clear": true} to clear it.',
				'properties'  => [
					'clear' => [ 'type' => 'boolean' ],
				],
			];
		}

		if ( $field instanceof Password_Field ) {
			return [
				'type'        => 'string',
				'writeOnly'   => true,
				'description' => 'Write-only; never returned. An empty string keeps the stored value.',
			];
		}

		if ( $field instanceof Checkbox_Field ) {
			if ( self::has_options( $field ) ) {
				return [
					'type'        => 'array',
					'uniqueItems' => true,
					'items'       => [
						'type' => 'string',
						'enum' => self::option_keys( $field ),
					],
				];
			}

			$schema = [ 'type' => 'boolean' ];
			if ( $field->get_config( 'default' ) ) {
				$schema['default'] = true;
			}

			return $schema;
		}

		if ( $field instanceof Select_Field ) {
			if ( $field->get_config( 'multiple' ) ) {
				return [
					'type'        => 'array',
					'uniqueItems' => true,
					'items'       => [
						'type' => 'string',
						'enum' => self::option_keys( $field ),
					],
				];
			}

			return self::enum_schema( $field );
		}

		if ( 'radio' === $type ) {
			return self::enum_schema( $field );
		}

		if ( $field instanceof Number_Field ) {
			return self::number_schema( $field );
		}

		if ( $field instanceof Upload_Field ) {
			return [ 'type' => [ 'integer', 'string' ] ];
		}

		if ( $field instanceof Repeater_Field ) {
			return self::repeater_schema( $field );
		}

		$schema = [ 'type' => 'string' ];

		switch ( $type ) {
			case 'email':
				$schema['format'] = 'email';
				break;

			case 'url':
				$schema['format'] = 'uri';
				break;

			case 'date':
				$schema['format']  = 'date';
				$schema['pattern'] = '^\\d{4}-\\d{2}-\\d{2}$';
				break;

			case 'color':
				$schema['format']  = 'hex-color';
				$schema['pattern'] = '^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$';
				break;

			case 'text':
				$pattern = (string) $field->get_config( 'pattern', '' );
				if ( '' !== $pattern ) {
					$schema['pattern'] = '^(?:' . $pattern . ')$';
				}
				break;
		}

		$maxlength = $field->get_config( 'maxlength' );
		if ( is_numeric( $maxlength ) && (int) $maxlength > 0 ) {
			$schema['maxLength'] = (int) $maxlength;
		}

		$default = $field->get_config( 'default' );
		if ( is_string( $default ) && '' !== $default ) {
			$schema['default'] = $default;
		}

		return $schema;
	}

	/**
	 * Schema of a single-choice field.
	 *
	 * @param Field_Interface $field Field.
	 * @return array<string, mixed>
	 */
	private static function enum_schema( Field_Interface $field ): array {
		$keys = self::option_keys( $field );

		if ( ! $field->get_config( 'required' ) ) {
			array_unshift( $keys, '' );
		}

		return [
			'type' => 'string',
			'enum' => $keys,
		];
	}

	/**
	 * Schema of a number field.
	 *
	 * @param Field_Interface $field Field.
	 * @return array<string, mixed>
	 */
	private static function number_schema( Field_Interface $field ): array {
		$schema = [ 'type' => [ 'number', 'null' ] ];

		$min = $field->get_config( 'min' );
		if ( is_numeric( $min ) ) {
			$schema['minimum'] = $min + 0;
		}

		$max = $field->get_config( 'max' );
		if ( is_numeric( $max ) ) {
			$schema['maximum'] = $max + 0;
		}

		$step = $field->get_config( 'step' );
		if ( is_numeric( $step ) && $step > 0 ) {
			$schema['multipleOf'] = $step + 0;
		}

		$default = $field->get_config( 'default' );
		if ( is_numeric( $default ) ) {
			$schema['default'] = $default + 0;
		}

		return $schema;
	}

	/**
	 * Schema of a repeater field: an array of row objects.
	 *
	 * @param Repeater_Field $field Repeater.
	 * @return array<string, mixed>
	 */
	private static function repeater_schema( Repeater_Field $field ): array {
		$properties = [];

		foreach ( self::repeater_sub_fields( $field ) as $name => $sub_field ) {
			$properties[ $name ] = self::field_schema( $sub_field );
		}

		$schema = [
			'type'  => 'array',
			'items' => [
				'type'                 => 'object',
				'properties'           => $properties,
				'additionalProperties' => false,
			],
		];

		$min_rows = (int) $field->get_config( 'min_rows', 0 );
		$max_rows = (int) $field->get_config( 'max_rows', 0 );

		if ( $min_rows > 0 ) {
			$schema['minItems'] = $min_rows;
		}

		if ( $max_rows > 0 ) {
			$schema['maxItems'] = $max_rows;
		}

		return $schema;
	}

	/**
	 * Map the field's `validation` rules to schema keywords.
	 *
	 * @param Field_Interface      $field  Field.
	 * @param array<string, mixed> $schema Schema (by reference).
	 * @return void
	 */
	private static function apply_validation_rules( Field_Interface $field, array &$schema ): void {
		$rules = $field->get_config( 'validation' );

		if ( ! is_array( $rules ) || ! $field instanceof Abstract_Field ) {
			return;
		}

		$is_string = 'string' === ( $schema['type'] ?? '' );
		$is_number = is_array( $schema['type'] ?? null ) && in_array( 'number', $schema['type'], true );

		if ( isset( $rules['min'] ) && is_numeric( $rules['min'] ) ) {
			if ( $is_string ) {
				$schema['minLength'] = (int) $rules['min'];
			} elseif ( $is_number ) {
				$schema['minimum'] = $rules['min'] + 0;
			}
		}

		if ( isset( $rules['max'] ) && is_numeric( $rules['max'] ) ) {
			if ( $is_string ) {
				$schema['maxLength'] = (int) $rules['max'];
			} elseif ( $is_number ) {
				$schema['maximum'] = $rules['max'] + 0;
			}
		}

		if ( $is_string && ! empty( $rules['email'] ) ) {
			$schema['format'] = 'email';
		}

		if ( $is_string && ! empty( $rules['url'] ) ) {
			$schema['format'] = 'uri';
		}
	}

	/**
	 * Convert repeater rows from their stored shape.
	 *
	 * @param Repeater_Field $field  Repeater.
	 * @param mixed          $stored Stored rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function repeater_to_rest( Repeater_Field $field, $stored ): array {
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$sub_fields = self::repeater_sub_fields( $field );
		$rows       = [];

		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$out = [];
			foreach ( $sub_fields as $name => $sub_field ) {
				$out[ $name ] = self::to_rest_value( $sub_field, $row[ $name ] ?? '' );
			}

			$rows[] = $out;
		}

		return $rows;
	}

	/**
	 * Convert repeater rows from the request.
	 *
	 * @param Repeater_Field $field Repeater.
	 * @param mixed          $input Request value.
	 * @return array{value: mixed, error: string|null, skip: bool}
	 */
	private static function repeater_from_rest( Repeater_Field $field, $input ): array {
		$fail = static function ( string $message ): array {
			return [
				'value' => null,
				'error' => $message,
				'skip'  => false,
			];
		};

		if ( null === $input ) {
			$input = [];
		}

		if ( ! is_array( $input ) || ( ! empty( $input ) && array_keys( $input ) !== range( 0, count( $input ) - 1 ) ) ) {
			return $fail( 'must be an array of row objects.' );
		}

		$sub_fields = self::repeater_sub_fields( $field );
		$rows       = [];

		foreach ( $input as $index => $row ) {
			if ( ! is_array( $row ) ) {
				return $fail( sprintf( 'row %d must be an object.', $index + 1 ) );
			}

			$out = [];
			foreach ( $row as $name => $value ) {
				if ( ! isset( $sub_fields[ $name ] ) ) {
					return $fail( sprintf( 'row %1$d has an unknown property "%2$s".', $index + 1, (string) $name ) );
				}

				$converted = self::from_rest_value( $sub_fields[ $name ], $value );

				if ( null !== $converted['error'] ) {
					return $fail( sprintf( 'row %1$d, "%2$s" %3$s', $index + 1, (string) $name, $converted['error'] ) );
				}

				$out[ $name ] = $converted['value'];
			}

			$rows[] = $out;
		}

		return [
			'value' => $rows,
			'error' => null,
			'skip'  => false,
		];
	}

	/**
	 * Non-container sub-fields of a repeater, by name.
	 *
	 * @param Repeater_Field $field Repeater.
	 * @return array<string, Field_Interface>
	 */
	private static function repeater_sub_fields( Repeater_Field $field ): array {
		$sub_fields = [];

		foreach ( $field->get_sub_fields() as $config ) {
			if ( ! is_array( $config ) || empty( $config['name'] ) ) {
				continue;
			}

			try {
				$sub = Field_Factory::create( $config );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}

			if ( self::is_storable( $sub ) && ! $sub instanceof Repeater_Field ) {
				$sub_fields[ $sub->get_name() ] = $sub;
			}
		}

		return $sub_fields;
	}

	/**
	 * Option keys of a field as strings.
	 *
	 * @param Field_Interface $field Field.
	 * @return string[]
	 */
	private static function option_keys( Field_Interface $field ): array {
		$options = $field->get_config( 'options' );

		return is_array( $options ) ? array_map( 'strval', array_keys( $options ) ) : [];
	}

	/**
	 * Whether a checkbox defines options (multi) or not (single).
	 *
	 * @param Field_Interface $field Field.
	 * @return bool
	 */
	private static function has_options( Field_Interface $field ): bool {
		return ! empty( $field->get_config( 'options' ) );
	}

	/**
	 * Whether the field is one of the built-in scalar types handled as strings.
	 *
	 * Custom field types (registered by a consumer) are passed through as is.
	 *
	 * @param Field_Interface $field Field.
	 * @return bool
	 */
	private static function is_known_type( Field_Interface $field ): bool {
		return in_array(
			$field->get_type(),
			[ 'text', 'textarea', 'email', 'url', 'date', 'color', 'radio', 'select', 'wysiwyg' ],
			true
		);
	}

	/**
	 * Whether every value is one of the field's option keys.
	 *
	 * @param Field_Interface  $field  Field.
	 * @param array<int|string> $values Values.
	 * @return bool
	 */
	private static function all_in_options( Field_Interface $field, array $values ): bool {
		$keys = self::option_keys( $field );

		foreach ( $values as $value ) {
			if ( ! in_array( (string) $value, $keys, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a value is a list of scalars.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_scalar_list( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( ! is_string( $item ) && ! is_int( $item ) ) {
				return false;
			}
		}

		return empty( $value ) || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Convert a stored array to a list of strings.
	 *
	 * @param mixed $stored Stored value.
	 * @return string[]
	 */
	private static function to_string_list( $stored ): array {
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$list = [];
		foreach ( $stored as $item ) {
			if ( is_scalar( $item ) ) {
				$list[] = (string) $item;
			}
		}

		return $list;
	}
}
