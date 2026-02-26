<?php
/**
 * ColorField - Color picker input field
 *
 * Example field that demonstrates custom asset enqueuing.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Color_Field class
 *
 * Renders an HTML5 color input with optional WordPress color picker.
 * Demonstrates how to enqueue custom assets for a field.
 */
class Color_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'          => 'color',
				'default'       => '#000000',
				'use_wp_picker' => true,  // Use WordPress color picker if available
			]
		);
	}

	/**
	 * Enqueue field assets
	 *
	 * Loads WordPress color picker if enabled, or falls back to HTML5 color input.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Only enqueue if WordPress color picker is requested and available
		if ( $this->config['use_wp_picker'] && function_exists( 'wp_enqueue_style' ) ) {
			// Enqueue WordPress color picker
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			// Initialization is handled by cassette-cmf.js
		}
	}

	/**
	 * Render the color field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$field_value = $value ?? $this->config['default'] ?? '#000000';

		$attributes = [
			'type'  => 'text',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => $field_value,
			'class' => $this->config['use_wp_picker'] ? 'cassette-cmf-color-picker' : 'cassette-cmf-color-input',
		];

		// Add data attribute for default color
		$attributes['data-default-color'] = $this->config['default'];

		if ( ! empty( $this->config['required'] ) ) {
			$attributes['required'] = true;
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
	 * Sanitize color input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		if ( ! is_string( $input ) ) {
			return $this->config['default'] ?? '#000000';
		}

		// Sanitize hex color
		$color = ltrim( $input, '#' );

		// Validate hex color format
		if ( preg_match( '/^[a-fA-F0-9]{6}$/', $color ) || preg_match( '/^[a-fA-F0-9]{3}$/', $color ) ) {
			return '#' . $color;
		}

		return $this->config['default'] ?? '#000000';
	}

	/**
	 * Validate color input
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	public function validate( $input ): array {
		$result = parent::validate( $input );

		// Validate hex color format
		if ( ! empty( $input ) ) {
			$color = ltrim( (string) $input, '#' );

			if ( ! preg_match( '/^[a-fA-F0-9]{6}$/', $color ) && ! preg_match( '/^[a-fA-F0-9]{3}$/', $color ) ) {
				$result['valid']    = false;
				$result['errors'][] = sprintf(
					'%s must be a valid hex color (e.g., #FF0000 or #F00).',
					$this->get_label()
				);
			}
		}

		return $result;
	}
}
