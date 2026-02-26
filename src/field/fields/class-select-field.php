<?php
/**
 * SelectField - Dropdown select field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Select_Field class
 *
 * Renders a select dropdown with options.
 * Options can be a simple array or key-value pairs.
 * Supports multiple selection with 'multiple' config.
 */
class Select_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'     => 'select',
				'options'  => [],
				'multiple' => false,
				'size'     => 1,
			]
		);
	}

	/**
	 * Render the select field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$field_value = $value ?? $this->config['default'] ?? '';

		// Handle multiple values
		if ( $this->config['multiple'] && ! is_array( $field_value ) ) {
			$field_value = $field_value ? [ $field_value ] : [];
		}

		$attributes = [
			'id'    => $this->get_field_id(),
			'name'  => $this->name . ( $this->config['multiple'] ? '[]' : '' ),
			'class' => 'regular-text',
		];

		if ( $this->config['multiple'] ) {
			$attributes['multiple'] = true;
			$attributes['size']     = $this->config['size'] > 1 ? $this->config['size'] : 5;
		}

		if ( ! empty( $this->config['required'] ) ) {
			$attributes['required'] = true;
		}

		if ( ! empty( $this->config['disabled'] ) ) {
			$attributes['disabled'] = true;
		}

		$output .= '<select' . $this->build_attributes( $attributes ) . '>';

		// Add options
		foreach ( $this->config['options'] as $opt_value => $opt_label ) {
			$selected = $this->config['multiple']
				? in_array( $opt_value, (array) $field_value, true )
				: ( (string) $opt_value === (string) $field_value );

			$output .= sprintf(
				'<option value="%s"%s>%s</option>',
				$this->esc_attr( $opt_value ),
				$selected ? ' selected' : '',
				$this->esc_html( $opt_label )
			);
		}

		$output .= '</select>';
		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Sanitize select input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		// Handle multiple selection
		if ( $this->config['multiple'] && is_array( $input ) ) {
			return array_map( [ $this, 'sanitize_single_value' ], $input );
		}

		return $this->sanitize_single_value( $input );
	}

	/**
	 * Sanitize a single value
	 *
	 * @param mixed $value Value to sanitize.
	 * @return mixed
	 */
	protected function sanitize_single_value( $value ) {
		// Ensure the value is in the allowed options
		if ( ! array_key_exists( $value, $this->config['options'] ) ) {
			return '';
		}

		return parent::sanitize( $value );
	}

	/**
	 * Validate select input
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	public function validate( $input ): array {
		$result = parent::validate( $input );

		// Additional validation: ensure value(s) are in options
		$values = $this->config['multiple'] ? (array) $input : [ $input ];

		foreach ( $values as $val ) {
			if ( ! empty( $val ) && ! array_key_exists( $val, $this->config['options'] ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf(
					'%s contains an invalid option.',
					$this->get_label()
				);
				break;
			}
		}

		return $result;
	}
}
