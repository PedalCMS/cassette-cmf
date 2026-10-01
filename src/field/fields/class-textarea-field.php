<?php
/**
 * TextareaField - Multi-line text input field
 *
 * @package PedalCMS\CassetteCMF
 * @since 1.0.0
 */

namespace PedalCMS\CassetteCMF\Field\Fields;

use PedalCMS\CassetteCMF\Field\Abstract_Field;

/**
 * Textarea_Field class
 *
 * Renders a textarea element for multi-line text input.
 * Supports rows, cols, placeholder, and maxlength attributes.
 */
class Textarea_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'        => 'textarea',
				'rows'        => 5,
				'cols'        => 50,
				'placeholder' => '',
				'maxlength'   => '',
				'allow_html'  => false,
			]
		);
	}

	/**
	 * Render the textarea field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'class' => 'large-text',
			'rows'  => $this->config['rows'],
			'cols'  => $this->config['cols'],
		];

		if ( ! empty( $this->config['placeholder'] ) ) {
			$attributes['placeholder'] = $this->config['placeholder'];
		}

		if ( ! empty( $this->config['maxlength'] ) ) {
			$attributes['maxlength'] = $this->config['maxlength'];
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

		$field_value = $value ?? $this->config['default'] ?? '';

		$output .= '<textarea' . $this->build_attributes( $attributes ) . '>';
		$output .= $this->esc_html( $field_value );
		$output .= '</textarea>';
		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Sanitize the textarea field value
	 *
	 * Unlike a single-line text field, textarea content must preserve
	 * newlines. sanitize_text_field() (the Abstract_Field default)
	 * collapses all whitespace runs, including newlines, into a single
	 * space, which silently destroys multi-line content on save.
	 *
	 * @param mixed $input Value to sanitize.
	 * @return string Sanitized value.
	 */
	public function sanitize( $input ) {
		if ( ! is_string( $input ) ) {
			return '';
		}

		if ( ! empty( $this->config['allow_html'] ) ) {
			if ( function_exists( 'wp_kses_post' ) ) {
				return wp_kses_post( $input );
			}

			$input = function_exists( 'wp_strip_all_tags' )
				? wp_strip_all_tags( $input )
				: (string) preg_replace( '/<[^>]*>/', '', $input );
		} elseif ( function_exists( 'sanitize_textarea_field' ) ) {
			return \sanitize_textarea_field( $input );
		} else {
			$input = function_exists( 'wp_strip_all_tags' )
				? wp_strip_all_tags( $input )
				: (string) preg_replace( '/<[^>]*>/', '', $input );
		}

		// Fallback normalization when WordPress sanitizers are unavailable:
		// collapse horizontal whitespace only, never newlines.
		$input = str_replace( [ "\r\n", "\r" ], "\n", $input );
		$input = (string) preg_replace( '/[^\S\n]+/', ' ', $input );

		return trim( $input );
	}
}
