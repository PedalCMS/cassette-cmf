<?php
/**
 * URLField - URL input field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * URL_Field class
 *
 * Renders an HTML5 URL input with automatic validation.
 */
class URL_Field extends Abstract_Field {

	/**
	 * Constructor
	 *
	 * @param string               $name   Field name.
	 * @param string               $type   Field type.
	 * @param array<string, mixed> $config Field configuration.
	 */
	public function __construct( string $name, string $type = 'url', array $config = [] ) {
		parent::__construct( $name, $type, $config );

		// Add URL validation rule by default
		$this->validation_rules['url'] = true;
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
				'type'        => 'url',
				'placeholder' => '',
			]
		);
	}

	/**
	 * Render the URL field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'type'  => 'url',
			'id'    => $this->get_field_id(),
			'name'  => $this->name,
			'value' => $value ?? $this->config['default'] ?? '',
			'class' => 'regular-text code',
		];

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
	 * Sanitize URL input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		if ( ! is_string( $input ) ) {
			return '';
		}

		// Use WordPress esc_url_raw if available
		if ( function_exists( 'esc_url_raw' ) ) {
			return \esc_url_raw( $input );
		}

		// Fallback sanitization
		return filter_var( $input, FILTER_SANITIZE_URL );
	}
}
