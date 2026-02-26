<?php
/**
 * TextField - Single-line text input field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Text_Field class
 *
 * Renders a standard HTML text input field.
 * Supports placeholder, maxlength, pattern, and other HTML5 attributes.
 */
class Text_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'         => 'text',
				'placeholder'  => '',
				'maxlength'    => '',
				'pattern'      => '',
				'autocomplete' => '',
			]
		);
	}

	/**
	 * Render the text field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'type'  => 'text',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => $value ?? $this->config['default'] ?? '',
			'class' => 'regular-text',
		];

		// Add optional attributes
		if ( ! empty( $this->config['placeholder'] ) ) {
			$attributes['placeholder'] = $this->config['placeholder'];
		}

		if ( ! empty( $this->config['maxlength'] ) ) {
			$attributes['maxlength'] = $this->config['maxlength'];
		}

		if ( ! empty( $this->config['pattern'] ) ) {
			$attributes['pattern'] = $this->config['pattern'];
		}

		if ( ! empty( $this->config['autocomplete'] ) ) {
			$attributes['autocomplete'] = $this->config['autocomplete'];
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
}
