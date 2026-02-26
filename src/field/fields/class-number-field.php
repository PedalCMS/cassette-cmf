<?php
/**
 * NumberField - Numeric input field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Number_Field class
 *
 * Renders an HTML5 number input with min, max, and step support.
 */
class Number_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'        => 'number',
				'min'         => '',
				'max'         => '',
				'step'        => '',
				'placeholder' => '',
			]
		);
	}

	/**
	 * Render the number field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'type'  => 'number',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => $value ?? $this->config['default'] ?? '',
			'class' => 'regular-text',
		];

		if ( ! empty( $this->config['min'] ) || 0 === $this->config['min'] || '0' === $this->config['min'] ) {
			$attributes['min'] = $this->config['min'];
		}

		if ( ! empty( $this->config['max'] ) || 0 === $this->config['max'] || '0' === $this->config['max'] ) {
			$attributes['max'] = $this->config['max'];
		}

		if ( ! empty( $this->config['step'] ) ) {
			$attributes['step'] = $this->config['step'];
		}

		if ( ! empty( $this->config['placeholder'] ) ) {
			$attributes['placeholder'] = $this->config['placeholder'];
		}

		if ( ! empty( $this->config['required'] ) ) {
			$attributes['required'] = true;
		}

		if ( ! empty( $this->config['readonly'] ) ) {
			$attributes['readonly'] = true;
		}

		if ( ! empty( $this->config['disabled'] ) ) {
			$attributes['disabled'] = true;
		}

		$output .= '<input' . $this->build_attributes( $attributes ) . ' />';
		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Sanitize number input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		if ( empty( $input ) && 0 !== $input && '0' !== $input ) {
			return '';
		}

		// Convert to appropriate numeric type
		if ( strpos( (string) $input, '.' ) !== false ) {
			return (float) $input;
		}

		return (int) $input;
	}

	/**
	 * Validate number input
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	public function validate( $input ): array {
		$result = parent::validate( $input );

		// Skip validation if empty and not required.
		if ( empty( $input ) && 0 !== $input && '0' !== $input ) {
			return $result;
		}

		// Validate it's numeric
		if ( ! is_numeric( $input ) ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf( '%s must be a number.', $this->get_label() );
			return $result;
		}

		// Validate min
		if ( isset( $this->config['min'] ) && '' !== $this->config['min'] && $input < $this->config['min'] ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				'%s must be at least %s.',
				$this->get_label(),
				$this->config['min']
			);
		}

		// Validate max
		if ( isset( $this->config['max'] ) && '' !== $this->config['max'] && $input > $this->config['max'] ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				'%s must be at most %s.',
				$this->get_label(),
				$this->config['max']
			);
		}

		return $result;
	}
}
