<?php
/**
 * RadioField - Radio button group field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Radio_Field class
 *
 * Renders a group of radio buttons for single selection.
 * Similar to SelectField but uses radio buttons for better UX with fewer options.
 */
class Radio_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'    => 'radio',
				'options' => [],
				'inline'  => false,
			]
		);
	}

	/**
	 * Render the radio field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$field_value   = $value ?? $this->config['default'] ?? '';
		$wrapper_class = $this->config['inline'] ? 'cassette-cmf-radio-inline' : 'cassette-cmf-radio-stacked';

		$output .= '<fieldset>';
		$output .= '<div class="' . $this->esc_attr( $wrapper_class ) . '">';

		foreach ( $this->config['options'] as $opt_value => $opt_label ) {
			$radio_id   = $this->get_field_id() . '-' . $this->sanitize_key( $opt_value );
			$is_checked = (string) $opt_value === (string) $field_value;

			$attributes = [
				'type'  => 'radio',
				'id'    => $radio_id,
				'name'  => $this->name,
				'value' => $opt_value,
			];

			if ( $is_checked ) {
				$attributes['checked'] = true;
			}

			if ( ! empty( $this->config['required'] ) ) {
				$attributes['required'] = true;
			}

			if ( ! empty( $this->config['disabled'] ) ) {
				$attributes['disabled'] = true;
			}

			$output .= '<label>';
			$output .= '<input' . $this->build_attributes( $attributes ) . ' />';
			$output .= ' ' . $this->esc_html( $opt_label );
			$output .= '</label>';

			if ( ! $this->config['inline'] ) {
				$output .= '<br />';
			}
		}

		$output .= '</div>';
		$output .= '</fieldset>';
		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Sanitize radio input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		// Ensure the value is in the allowed options
		if ( ! array_key_exists( $input, $this->config['options'] ) ) {
			return '';
		}

		return parent::sanitize( $input );
	}

	/**
	 * Validate radio input
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	public function validate( $input ): array {
		$result = parent::validate( $input );

		// Ensure value is in options
		if ( ! empty( $input ) && ! array_key_exists( $input, $this->config['options'] ) ) {
			$result['valid']    = false;
			$result['errors'][] = sprintf(
				'%s contains an invalid option.',
				$this->get_label()
			);
		}

		return $result;
	}

	/**
	 * Sanitize key for use in HTML ID
	 *
	 * @param string $key Key to sanitize.
	 * @return string
	 */
	protected function sanitize_key( string $key ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return \sanitize_key( $key );
		}
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
	}
}
