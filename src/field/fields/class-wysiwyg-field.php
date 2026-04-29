<?php
/**
 * WysiwygField - WordPress WYSIWYG editor field
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Wysiwyg_Field class
 *
 * Renders a WordPress WYSIWYG editor using wp_editor().
 * Supports media buttons, teeny mode, and custom editor settings.
 */
class Wysiwyg_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'media_buttons' => true,
				'teeny'         => false,
				'textarea_rows' => 10,
				'editor_class'  => '',
				'wpautop'       => true,
				'quicktags'     => true,
			]
		);
	}

	/**
	 * Render the WYSIWYG field
	 *
	 * @param mixed $value Current field value.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$editor_id = $this->get_field_id();
		$settings  = $this->get_editor_settings();

		// Get the content value
		$content = $value ?? $this->config['default'] ?? '';

		$this->enqueue_assets();

		// Buffer the editor output.
		ob_start();

		if ( ! empty( $this->config['deferred_init'] ) || ! empty( $this->config['defer_editor_init'] ) ) {
			$settings_json = '{}';

			if ( function_exists( 'wp_json_encode' ) ) {
				$settings_json = wp_json_encode( $this->get_js_editor_settings() );
			}

			if ( false === $settings_json ) {
				$settings_json = '{}';
			}

			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped via esc_attr/esc_html methods.
			echo '<textarea id="' . $this->esc_attr( $editor_id ) . '" '
				. 'name="' . $this->esc_attr( $this->name ) . '" '
				. 'rows="' . $this->esc_attr( (string) $settings['textarea_rows'] ) . '" '
				. 'class="large-text wp-editor-area cassette-cmf-wysiwyg-editor" '
				. 'data-cassette-cmf-wysiwyg-settings="' . $this->esc_attr( $settings_json ) . '">'
				. $this->esc_html( $content )
				. '</textarea>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( function_exists( 'wp_editor' ) ) {
			wp_editor( $content, $editor_id, $settings );
		} else {
			// Fallback for non-WordPress environments.
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped via esc_attr/esc_html methods.
			echo '<textarea id="' . $this->esc_attr( $editor_id ) . '" '
				. 'name="' . $this->esc_attr( $this->name ) . '" '
				. 'rows="' . $this->esc_attr( (string) $settings['textarea_rows'] ) . '" '
				. 'class="large-text">'
				. $this->esc_html( $content )
				. '</textarea>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$output .= ob_get_clean();
		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Get wp_editor settings.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_editor_settings(): array {
		return [
			'media_buttons' => $this->config['media_buttons'] ?? true,
			'teeny'         => $this->config['teeny'] ?? false,
			'textarea_rows' => $this->config['textarea_rows'] ?? 10,
			'textarea_name' => $this->name,
			'editor_class'  => $this->config['editor_class'] ?? '',
			'wpautop'       => $this->config['wpautop'] ?? true,
			'quicktags'     => $this->config['quicktags'] ?? true,
		];
	}

	/**
	 * Get JavaScript editor settings for deferred initialization.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_js_editor_settings(): array {
		return [
			'mediaButtons' => $this->config['media_buttons'] ?? true,
			'teeny'        => $this->config['teeny'] ?? false,
			'textareaRows' => $this->config['textarea_rows'] ?? 10,
			'textareaName' => $this->name,
			'editorClass'  => $this->config['editor_class'] ?? '',
			'wpautop'      => $this->config['wpautop'] ?? true,
			'quicktags'    => $this->config['quicktags'] ?? true,
		];
	}

	/**
	 * Enqueue WordPress editor assets when available.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}
	}

	/**
	 * Sanitize the WYSIWYG field value
	 *
	 * Uses wp_kses_post to allow safe HTML.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string Sanitized value.
	 */
	public function sanitize( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		// Use wp_kses_post if available to allow safe HTML
		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $value );
		}

		// Fallback: strip all tags.
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $value );
		}

		return (string) preg_replace( '/<[^>]*>/', '', $value );
	}

	/**
	 * Validate the WYSIWYG field value
	 *
	 * @param mixed $input Input value.
	 * @return array Validation result.
	 */
	public function validate( $input ): array {
		$errors = [];

		// Check required
		if ( ! empty( $this->config['required'] ) && empty( $input ) ) {
			$errors[] = $this->translate( 'This field is required.', 'cassette-cmf' );
		}

		// Check minimum length
		if ( ! empty( $this->config['min'] ) && strlen( (string) $input ) < $this->config['min'] ) {
			$errors[] = sprintf(
				$this->translate( 'Content must be at least %d characters.', 'cassette-cmf' ),
				$this->config['min']
			);
		}

		// Check maximum length
		if ( ! empty( $this->config['max'] ) && strlen( (string) $input ) > $this->config['max'] ) {
			$errors[] = sprintf(
				$this->translate( 'Content must not exceed %d characters.', 'cassette-cmf' ),
				$this->config['max']
			);
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Get field schema for JSON validation
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'media_buttons' => [ 'type' => 'boolean' ],
				'teeny'         => [ 'type' => 'boolean' ],
				'textarea_rows' => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 50,
				],
				'editor_class'  => [ 'type' => 'string' ],
				'wpautop'       => [ 'type' => 'boolean' ],
				'quicktags'     => [ 'type' => 'boolean' ],
				'min'           => [
					'type'    => 'integer',
					'minimum' => 0,
				],
				'max'           => [
					'type'    => 'integer',
					'minimum' => 1,
				],
			],
		];
	}
}
