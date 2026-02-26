<?php
/**
 * PasswordField - Password input field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Password_Field class
 *
 * Renders a password input field (masked text).
 * Does not pre-fill value for security reasons.
 */
class Password_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'         => 'password',
				'placeholder'  => '',
				'maxlength'    => '',
				'autocomplete' => 'off',
			]
		);
	}

	/**
	 * Render the password field
	 *
	 * @param mixed $value Current field value (ignored for security).
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		// Never pre-fill password fields for security
		$attributes = [
			'type'  => 'password',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => '',
			'class' => 'regular-text',
		];

		if ( ! empty( $this->config['placeholder'] ) ) {
			$attributes['placeholder'] = $this->config['placeholder'];
		}

		if ( ! empty( $this->config['maxlength'] ) ) {
			$attributes['maxlength'] = $this->config['maxlength'];
		}

		if ( ! empty( $this->config['autocomplete'] ) ) {
			$attributes['autocomplete'] = $this->config['autocomplete'];
		}

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
	 * Sanitize password input
	 *
	 * Passwords should typically not be sanitized in the traditional sense.
	 * Just ensure it's a string and trim whitespace.
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		if ( ! is_string( $input ) ) {
			return '';
		}

		// Don't use strip_tags or other sanitization that might weaken passwords
		return trim( $input );
	}
}
