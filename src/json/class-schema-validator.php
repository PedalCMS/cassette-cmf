<?php
/**
 * JSON Schema Validator for Cassette-CMF
 *
 * Validates JSON configuration against the Cassette-CMF schema.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Json;

/**
 * Schema_Validator class
 *
 * Validates JSON configuration data against defined schema rules.
 * This is a basic validator that checks required fields and data types.
 */
class Schema_Validator {

	/**
	 * Validation errors
	 *
	 * @var array<string>
	 */
	private array $errors = [];

	/**
	 * Validate configuration array against schema
	 *
	 * @param array<string, mixed> $config Configuration data to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate( array $config ): bool {
		$this->errors = [];

		// Validate CPTs if present
		if ( isset( $config['cpts'] ) ) {
			if ( ! is_array( $config['cpts'] ) ) {
				$this->errors[] = 'cpts must be an array';
			} else {
				foreach ( $config['cpts'] as $index => $cpt ) {
					$this->validate_cpt( $cpt, $index );
				}
			}
		}

		// Validate settings pages if present
		if ( isset( $config['settings_pages'] ) ) {
			if ( ! is_array( $config['settings_pages'] ) ) {
				$this->errors[] = 'settings_pages must be an array';
			} else {
				foreach ( $config['settings_pages'] as $index => $page ) {
					$this->validate_settings_page( $page, $index );
				}
			}
		}

		return empty( $this->errors );
	}

	/**
	 * Validate a custom post type configuration
	 *
	 * @param mixed $cpt   CPT configuration.
	 * @param int   $index Array index for error reporting.
	 * @return void
	 */
	private function validate_cpt( $cpt, int $index ): void {
		if ( ! is_array( $cpt ) ) {
			$this->errors[] = "cpts[{$index}] must be an object/array";
			return;
		}

		// Required: id
		if ( empty( $cpt['id'] ) ) {
			$this->errors[] = "cpts[{$index}] missing required field 'id'";
		} elseif ( ! is_string( $cpt['id'] ) ) {
			$this->errors[] = "cpts[{$index}].id must be a string";
		} elseif ( ! preg_match( '/^[a-z_]{1,20}$/', $cpt['id'] ) ) {
			$this->errors[] = "cpts[{$index}].id must be lowercase letters/underscores, max 20 chars";
		}

		// Optional: args
		if ( isset( $cpt['args'] ) && ! is_array( $cpt['args'] ) ) {
			$this->errors[] = "cpts[{$index}].args must be an object/array";
		}

		// Optional: fields
		if ( isset( $cpt['fields'] ) ) {
			if ( ! is_array( $cpt['fields'] ) ) {
				$this->errors[] = "cpts[{$index}].fields must be an array";
			} else {
				foreach ( $cpt['fields'] as $field_index => $field ) {
					$this->validate_field( $field, "cpts[{$index}].fields[{$field_index}]" );
				}
			}
		}
	}

	/**
	 * Validate a settings page configuration
	 *
	 * @param mixed $page  Settings page configuration.
	 * @param int   $index Array index for error reporting.
	 * @return void
	 */
	private function validate_settings_page( $page, int $index ): void {
		if ( ! is_array( $page ) ) {
			$this->errors[] = "settings_pages[{$index}] must be an object/array";
			return;
		}

		// Required: id
		if ( empty( $page['id'] ) ) {
			$this->errors[] = "settings_pages[{$index}] missing required field 'id'";
		} elseif ( ! is_string( $page['id'] ) ) {
			$this->errors[] = "settings_pages[{$index}].id must be a string";
		}

		// Optional: fields
		if ( isset( $page['fields'] ) ) {
			if ( ! is_array( $page['fields'] ) ) {
				$this->errors[] = "settings_pages[{$index}].fields must be an array";
			} else {
				foreach ( $page['fields'] as $field_index => $field ) {
					$this->validate_field( $field, "settings_pages[{$index}].fields[{$field_index}]" );
				}
			}
		}
	}

	/**
	 * Validate a field configuration
	 *
	 * @param mixed  $field Field configuration.
	 * @param string $path  Field path for error reporting.
	 * @return void
	 */
	private function validate_field( $field, string $path ): void {
		if ( ! is_array( $field ) ) {
			$this->errors[] = "{$path} must be an object/array";
			return;
		}

		// Required: name
		if ( empty( $field['name'] ) ) {
			$this->errors[] = "{$path} missing required field 'name'";
		} elseif ( ! is_string( $field['name'] ) ) {
			$this->errors[] = "{$path}.name must be a string";
		} elseif ( ! preg_match( '/^[a-z_][a-z0-9_]*$/', $field['name'] ) ) {
			$this->errors[] = "{$path}.name must start with letter/underscore, contain only lowercase letters, numbers, underscores";
		} elseif ( strlen( $field['name'] ) > 64 ) {
			$this->errors[] = "{$path}.name must be maximum 64 characters";
		}

		// Required: type
		if ( empty( $field['type'] ) ) {
			$this->errors[] = "{$path} missing required field 'type'";
		} elseif ( ! is_string( $field['type'] ) ) {
			$this->errors[] = "{$path}.type must be a string";
		} else {
			$valid_types = [
				'text',
				'textarea',
				'select',
				'checkbox',
				'radio',
				'number',
				'email',
				'url',
				'date',
				'password',
				'color',
				'wysiwyg',
				'tabs',
				'metabox',
				'group',
				'repeater',
			];
			if ( ! in_array( $field['type'], $valid_types, true ) ) {
				$this->errors[] = "{$path}.type must be one of: " . implode( ', ', $valid_types );
			}
		}

		// Type-specific validation
		$this->validate_field_type_specific( $field, $path );

		// Common optional fields
		$this->validate_common_field_properties( $field, $path );
	}

	/**
	 * Validate type-specific field requirements
	 *
	 * @param array<string, mixed> $field Field configuration.
	 * @param string               $path  Field path for error reporting.
	 * @return void
	 */
	private function validate_field_type_specific( array $field, string $path ): void {
		if ( ! isset( $field['type'] ) ) {
			return;
		}

		$type = $field['type'];

		// Fields that require options (checkbox is optional - single vs multiple)
		if ( in_array( $type, [ 'select', 'radio' ], true ) ) {
			if ( ! isset( $field['options'] ) ) {
				$this->errors[] = "{$path} field type '{$type}' requires 'options' property";
			} elseif ( ! is_array( $field['options'] ) ) {
				$this->errors[] = "{$path}.options must be an object/array";
			} elseif ( count( $field['options'] ) === 0 ) {
				$this->errors[] = "{$path}.options must contain at least one option";
			}
		}

		// Checkbox options validation (optional - can be single or multiple)
		if ( 'checkbox' === $type && isset( $field['options'] ) ) {
			if ( ! is_array( $field['options'] ) ) {
				$this->errors[] = "{$path}.options must be an object/array";
			} elseif ( count( $field['options'] ) === 0 ) {
				$this->errors[] = "{$path}.options must contain at least one option";
			}
		}

		// Number field validation
		if ( 'number' === $type ) {
			if ( isset( $field['min'] ) && ! is_numeric( $field['min'] ) ) {
				$this->errors[] = "{$path}.min must be numeric for number field";
			}
			if ( isset( $field['max'] ) && ! is_numeric( $field['max'] ) ) {
				$this->errors[] = "{$path}.max must be numeric for number field";
			}
			if ( isset( $field['step'] ) && ! is_numeric( $field['step'] ) ) {
				$this->errors[] = "{$path}.step must be numeric for number field";
			}
			if ( isset( $field['min'], $field['max'] ) && $field['min'] > $field['max'] ) {
				$this->errors[] = "{$path}.min cannot be greater than max";
			}
		}

		// Date field validation
		if ( 'date' === $type ) {
			if ( isset( $field['min'] ) && ! $this->is_valid_date_format( $field['min'] ) ) {
				$this->errors[] = "{$path}.min must be valid date (YYYY-MM-DD) for date field";
			}
			if ( isset( $field['max'] ) && ! $this->is_valid_date_format( $field['max'] ) ) {
				$this->errors[] = "{$path}.max must be valid date (YYYY-MM-DD) for date field";
			}
		}

		// Textarea specific
		if ( 'textarea' === $type ) {
			if ( isset( $field['rows'] ) ) {
				$rows = $field['rows'];
				if ( ! is_int( $rows ) && ! is_numeric( $rows ) ) {
					$this->errors[] = "{$path}.rows must be an integer";
				} elseif ( $rows < 1 || $rows > 50 ) {
					$this->errors[] = "{$path}.rows must be between 1 and 50";
				}
			}
			if ( isset( $field['cols'] ) ) {
				$cols = $field['cols'];
				if ( ! is_int( $cols ) && ! is_numeric( $cols ) ) {
					$this->errors[] = "{$path}.cols must be an integer";
				} elseif ( $cols < 10 || $cols > 200 ) {
					$this->errors[] = "{$path}.cols must be between 10 and 200";
				}
			}
		}

		// Color field validation
		if ( 'color' === $type ) {
			if ( isset( $field['default'] ) && ! $this->is_valid_hex_color( $field['default'] ) ) {
				$this->errors[] = "{$path}.default must be valid hex color (#RRGGBB) for color field";
			}
		}

		// Tabs field validation - requires tabs array with nested fields
		if ( 'tabs' === $type ) {
			if ( ! isset( $field['tabs'] ) ) {
				$this->errors[] = "{$path} field type 'tabs' requires 'tabs' property";
			} elseif ( ! is_array( $field['tabs'] ) ) {
				$this->errors[] = "{$path}.tabs must be an array";
			} elseif ( count( $field['tabs'] ) === 0 ) {
				$this->errors[] = "{$path}.tabs must contain at least one tab";
			} else {
				foreach ( $field['tabs'] as $tab_index => $tab ) {
					$this->validate_tab_definition( $tab, "{$path}.tabs[{$tab_index}]" );
				}
			}

			// Validate orientation if present
			if ( isset( $field['orientation'] ) ) {
				$valid_orientations = [ 'horizontal', 'vertical' ];
				if ( ! in_array( $field['orientation'], $valid_orientations, true ) ) {
					$this->errors[] = "{$path}.orientation must be one of: " . implode( ', ', $valid_orientations );
				}
			}
		}

		// Metabox field validation - requires fields array
		if ( 'metabox' === $type ) {
			if ( ! isset( $field['fields'] ) ) {
				$this->errors[] = "{$path} field type 'metabox' requires 'fields' property";
			} elseif ( ! is_array( $field['fields'] ) ) {
				$this->errors[] = "{$path}.fields must be an array";
			} else {
				foreach ( $field['fields'] as $nested_index => $nested_field ) {
					$this->validate_field( $nested_field, "{$path}.fields[{$nested_index}]" );
				}
			}

			// Validate context if present
			if ( isset( $field['context'] ) ) {
				$valid_contexts = [ 'normal', 'side', 'advanced' ];
				if ( ! in_array( $field['context'], $valid_contexts, true ) ) {
					$this->errors[] = "{$path}.context must be one of: " . implode( ', ', $valid_contexts );
				}
			}

			// Validate priority if present
			if ( isset( $field['priority'] ) ) {
				$valid_priorities = [ 'high', 'default', 'low' ];
				if ( ! in_array( $field['priority'], $valid_priorities, true ) ) {
					$this->errors[] = "{$path}.priority must be one of: " . implode( ', ', $valid_priorities );
				}
			}
		}

		// Group field validation - requires fields array
		if ( 'group' === $type ) {
			if ( ! isset( $field['fields'] ) ) {
				$this->errors[] = "{$path} field type 'group' requires 'fields' property";
			} elseif ( ! is_array( $field['fields'] ) ) {
				$this->errors[] = "{$path}.fields must be an array";
			} else {
				foreach ( $field['fields'] as $nested_index => $nested_field ) {
					$this->validate_field( $nested_field, "{$path}.fields[{$nested_index}]" );
				}
			}
		}

		// Repeater field validation - requires fields array
		if ( 'repeater' === $type ) {
			if ( ! isset( $field['fields'] ) ) {
				$this->errors[] = "{$path} field type 'repeater' requires 'fields' property";
			} elseif ( ! is_array( $field['fields'] ) ) {
				$this->errors[] = "{$path}.fields must be an array";
			} elseif ( count( $field['fields'] ) === 0 ) {
				$this->errors[] = "{$path}.fields must contain at least one field";
			} else {
				foreach ( $field['fields'] as $nested_index => $nested_field ) {
					$this->validate_field( $nested_field, "{$path}.fields[{$nested_index}]" );
				}
			}

			// Validate min/max if present
			if ( isset( $field['min'] ) && ( ! is_int( $field['min'] ) || $field['min'] < 0 ) ) {
				$this->errors[] = "{$path}.min must be a non-negative integer for repeater field";
			}
			if ( isset( $field['max'] ) && ( ! is_int( $field['max'] ) || $field['max'] < 1 ) ) {
				$this->errors[] = "{$path}.max must be a positive integer for repeater field";
			}
		}

		// WYSIWYG field validation
		if ( 'wysiwyg' === $type ) {
			if ( isset( $field['media_buttons'] ) && ! is_bool( $field['media_buttons'] ) ) {
				$this->errors[] = "{$path}.media_buttons must be a boolean for wysiwyg field";
			}
			if ( isset( $field['teeny'] ) && ! is_bool( $field['teeny'] ) ) {
				$this->errors[] = "{$path}.teeny must be a boolean for wysiwyg field";
			}
			if ( isset( $field['textarea_rows'] ) ) {
				$rows = $field['textarea_rows'];
				if ( ! is_int( $rows ) && ! is_numeric( $rows ) ) {
					$this->errors[] = "{$path}.textarea_rows must be an integer for wysiwyg field";
				} elseif ( $rows < 1 || $rows > 50 ) {
					$this->errors[] = "{$path}.textarea_rows must be between 1 and 50";
				}
			}
		}
	}

	/**
	 * Validate a tab definition within a tabs field
	 *
	 * @param mixed  $tab  Tab definition.
	 * @param string $path Path for error reporting.
	 * @return void
	 */
	private function validate_tab_definition( $tab, string $path ): void {
		if ( ! is_array( $tab ) ) {
			$this->errors[] = "{$path} must be an object/array";
			return;
		}

		// Required: id
		if ( empty( $tab['id'] ) ) {
			$this->errors[] = "{$path} missing required field 'id'";
		} elseif ( ! is_string( $tab['id'] ) ) {
			$this->errors[] = "{$path}.id must be a string";
		}

		// Required: label
		if ( empty( $tab['label'] ) ) {
			$this->errors[] = "{$path} missing required field 'label'";
		} elseif ( ! is_string( $tab['label'] ) ) {
			$this->errors[] = "{$path}.label must be a string";
		}

		// Required: fields
		if ( ! isset( $tab['fields'] ) ) {
			$this->errors[] = "{$path} missing required field 'fields'";
		} elseif ( ! is_array( $tab['fields'] ) ) {
			$this->errors[] = "{$path}.fields must be an array";
		} else {
			foreach ( $tab['fields'] as $field_index => $field ) {
				$this->validate_field( $field, "{$path}.fields[{$field_index}]" );
			}
		}

		// Optional: icon
		if ( isset( $tab['icon'] ) && ! is_string( $tab['icon'] ) ) {
			$this->errors[] = "{$path}.icon must be a string";
		}

		// Optional: description
		if ( isset( $tab['description'] ) && ! is_string( $tab['description'] ) ) {
			$this->errors[] = "{$path}.description must be a string";
		}
	}

	/**
	 * Validate common field properties
	 *
	 * @param array<string, mixed> $field Field configuration.
	 * @param string               $path  Field path for error reporting.
	 * @return void
	 */
	private function validate_common_field_properties( array $field, string $path ): void {
		if ( isset( $field['label'] ) ) {
			if ( ! is_string( $field['label'] ) ) {
				$this->errors[] = "{$path}.label must be a string";
			} elseif ( strlen( $field['label'] ) > 200 ) {
				$this->errors[] = "{$path}.label must be maximum 200 characters";
			}
		}

		if ( isset( $field['description'] ) ) {
			if ( ! is_string( $field['description'] ) ) {
				$this->errors[] = "{$path}.description must be a string";
			} elseif ( strlen( $field['description'] ) > 500 ) {
				$this->errors[] = "{$path}.description must be maximum 500 characters";
			}
		}

		if ( isset( $field['required'] ) && ! is_bool( $field['required'] ) ) {
			$this->errors[] = "{$path}.required must be a boolean";
		}

		if ( isset( $field['multiple'] ) && ! is_bool( $field['multiple'] ) ) {
			$this->errors[] = "{$path}.multiple must be a boolean";
		}

		if ( isset( $field['inline'] ) && ! is_bool( $field['inline'] ) ) {
			$this->errors[] = "{$path}.inline must be a boolean";
		}

		if ( isset( $field['readonly'] ) && ! is_bool( $field['readonly'] ) ) {
			$this->errors[] = "{$path}.readonly must be a boolean";
		}

		if ( isset( $field['disabled'] ) && ! is_bool( $field['disabled'] ) ) {
			$this->errors[] = "{$path}.disabled must be a boolean";
		}

		if ( isset( $field['maxlength'] ) ) {
			$maxlength = $field['maxlength'];
			if ( ! is_int( $maxlength ) && ! is_numeric( $maxlength ) ) {
				$this->errors[] = "{$path}.maxlength must be an integer";
			} elseif ( $maxlength < 1 || $maxlength > 65535 ) {
				$this->errors[] = "{$path}.maxlength must be between 1 and 65535";
			}
		}

		if ( isset( $field['placeholder'] ) ) {
			if ( ! is_string( $field['placeholder'] ) ) {
				$this->errors[] = "{$path}.placeholder must be a string";
			} elseif ( strlen( $field['placeholder'] ) > 200 ) {
				$this->errors[] = "{$path}.placeholder must be maximum 200 characters";
			}
		}

		if ( isset( $field['class'] ) ) {
			if ( ! is_string( $field['class'] ) ) {
				$this->errors[] = "{$path}.class must be a string";
			} elseif ( ! preg_match( '/^[a-zA-Z0-9_-]+(\s+[a-zA-Z0-9_-]+)*$/', $field['class'] ) ) {
				$this->errors[] = "{$path}.class must contain only valid CSS class names";
			}
		}

		if ( isset( $field['wrapper_class'] ) ) {
			if ( ! is_string( $field['wrapper_class'] ) ) {
				$this->errors[] = "{$path}.wrapper_class must be a string";
			} elseif ( ! preg_match( '/^[a-zA-Z0-9_-]+(\s+[a-zA-Z0-9_-]+)*$/', $field['wrapper_class'] ) ) {
				$this->errors[] = "{$path}.wrapper_class must contain only valid CSS class names";
			}
		}

		if ( isset( $field['context'] ) ) {
			$valid_contexts = [ 'normal', 'side', 'advanced' ];
			if ( ! in_array( $field['context'], $valid_contexts, true ) ) {
				$this->errors[] = "{$path}.context must be one of: " . implode( ', ', $valid_contexts );
			}
		}

		if ( isset( $field['priority'] ) ) {
			$valid_priorities = [ 'high', 'default', 'low' ];
			if ( ! in_array( $field['priority'], $valid_priorities, true ) ) {
				$this->errors[] = "{$path}.priority must be one of: " . implode( ', ', $valid_priorities );
			}
		}
	}

	/**
	 * Check if string is valid date format (YYYY-MM-DD)
	 *
	 * @param mixed $date Date string to validate.
	 * @return bool True if valid date format.
	 */
	private function is_valid_date_format( $date ): bool {
		if ( ! is_string( $date ) ) {
			return false;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Check if string is valid hex color
	 *
	 * @param mixed $color Color string to validate.
	 * @return bool True if valid hex color.
	 */
	private function is_valid_hex_color( $color ): bool {
		if ( ! is_string( $color ) ) {
			return false;
		}

		return (bool) preg_match( '/^#[0-9A-Fa-f]{6}$/', $color );
	}

	/**
	 * Get validation errors
	 *
	 * @return array<string> Array of error messages.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Check if there are validation errors
	 *
	 * @return bool True if there are errors.
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}

	/**
	 * Get formatted error message
	 *
	 * @return string Formatted error message.
	 */
	public function get_error_message(): string {
		if ( empty( $this->errors ) ) {
			return '';
		}

		return 'JSON validation failed:' . "\n- " . implode( "\n- ", $this->errors );
	}
}
