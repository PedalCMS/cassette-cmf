<?php
/**
 * DateField - Date input field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Date_Field class
 *
 * Renders an HTML5 date input field.
 * Supports min and max date constraints.
 */
class Date_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type' => 'date',
				'min'  => '',
				'max'  => '',
			]
		);
	}

	/**
	 * Render the date field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'type'  => 'date',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => $value ?? $this->config['default'] ?? '',
			'class' => 'regular-text',
		];

		if ( ! empty( $this->config['min'] ) ) {
			$attributes['min'] = $this->config['min'];
		}

		if ( ! empty( $this->config['max'] ) ) {
			$attributes['max'] = $this->config['max'];
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
	 * Validate date input
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	public function validate( $input ): array {
		$result = parent::validate( $input );

		// Skip if empty and not required
		if ( empty( $input ) ) {
			return $result;
		}

		// Validate date format (YYYY-MM-DD)
		$date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
		if ( ! preg_match( $date_pattern, $input ) ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				'%s must be a valid date in YYYY-MM-DD format.',
				$this->get_label()
			);
			return $result;
		}

		// Validate it's a real date
		$parts = explode( '-', $input );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf( '%s is not a valid date.', $this->get_label() );
		}

		// Validate min date
		if ( ! empty( $this->config['min'] ) && $input < $this->config['min'] ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				'%s must be on or after %s.',
				$this->get_label(),
				$this->config['min']
			);
		}

		// Validate max date
		if ( ! empty( $this->config['max'] ) && $input > $this->config['max'] ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				'%s must be on or before %s.',
				$this->get_label(),
				$this->config['max']
			);
		}

		return $result;
	}
}
