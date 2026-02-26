<?php
/**
 * EmailField - Email input field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Email_Field class
 *
 * Renders an HTML5 email input with automatic validation.
 * Extends TextField with email-specific validation.
 */
class Email_Field extends Abstract_Field {

	/**
	 * Constructor
	 *
	 * @param string               $name   Field name.
	 * @param string               $type   Field type.
	 * @param array<string, mixed> $config Field configuration.
	 */
	public function __construct( string $name, string $type = 'email', array $config = [] ) {
		parent::__construct( $name, $type, $config );

		// Add email validation rule by default
		$this->validation_rules['email'] = true;
	}

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'        => 'email',
				'placeholder' => '',
				'maxlength'   => '',
			]
		);
	}

	/**
	 * Render the email field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'type'  => 'email',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => $value ?? $this->config['default'] ?? '',
			'class' => 'regular-text',
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

		$output .= '<input' . $this->build_attributes( $attributes ) . ' />';
		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Sanitize email input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		if ( ! is_string( $input ) ) {
			return '';
		}

		// Use WordPress sanitize_email if available
		if ( function_exists( 'sanitize_email' ) ) {
			return \sanitize_email( $input );
		}

		// Fallback sanitization
		return strtolower( trim( $input ) );
	}
}
