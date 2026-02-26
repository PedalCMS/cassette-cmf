<?php
/**
 * Custom_HTML_Field - Display custom HTML content
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Custom_HTML_Field class
 *
 * Renders custom HTML content as a display-only field.
 * This field does not store any value - it's purely for displaying
 * custom HTML content within settings pages or metaboxes.
 *
 * Configuration options:
 * - content: The HTML content to display (required)
 * - allowed_tags: Array of allowed HTML tags for wp_kses (optional, defaults to post allowed tags)
 * - raw_html: If true, outputs HTML without sanitization (use with caution, default: false)
 */
class Custom_HTML_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'content'      => '',
				'allowed_tags' => [],
				'raw_html'     => false,
			]
		);
	}

	/**
	 * Render the custom HTML field
	 *
	 * @param mixed $value Current field value (not used for this field type).
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$content = $this->config['content'] ?? '';

		if ( ! empty( $content ) ) {
			$output .= '<div class="cassette-cmf-custom-html-content">';

			if ( ! empty( $this->config['raw_html'] ) ) {
				// Raw HTML output - use with caution
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional raw HTML output when raw_html is enabled
				$output .= $content;
			} else {
				// Sanitized HTML output
				$allowed_tags = $this->get_allowed_tags();
				$output      .= wp_kses( $content, $allowed_tags );
			}

			$output .= '</div>';
		}

		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Get allowed HTML tags for wp_kses
	 *
	 * @return array<string, array<string, bool>>
	 */
	protected function get_allowed_tags(): array {
		// Use custom allowed tags if provided
		if ( ! empty( $this->config['allowed_tags'] ) && is_array( $this->config['allowed_tags'] ) ) {
			return $this->config['allowed_tags'];
		}

		// Default to WordPress post allowed tags with some additions
		if ( function_exists( 'wp_kses_allowed_html' ) ) {
			$allowed = wp_kses_allowed_html( 'post' );

			// Add additional commonly needed tags
			$additional_tags = [
				'style'  => [
					'type' => true,
				],
				'iframe' => [
					'src'             => true,
					'width'           => true,
					'height'          => true,
					'frameborder'     => true,
					'allowfullscreen' => true,
					'allow'           => true,
					'title'           => true,
					'loading'         => true,
				],
				'svg'    => [
					'class'           => true,
					'aria-hidden'     => true,
					'aria-labelledby' => true,
					'role'            => true,
					'xmlns'           => true,
					'width'           => true,
					'height'          => true,
					'viewbox'         => true,
					'fill'            => true,
				],
				'path'   => [
					'd'               => true,
					'fill'            => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
				],
				'circle' => [
					'cx'     => true,
					'cy'     => true,
					'r'      => true,
					'fill'   => true,
					'stroke' => true,
				],
				'rect'   => [
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
					'fill'   => true,
					'stroke' => true,
				],
			];

			return array_merge( $allowed, $additional_tags );
		}

		// Fallback if wp_kses_allowed_html is not available
		return [
			'div'    => [
				'class' => true,
				'id'    => true,
				'style' => true,
			],
			'span'   => [
				'class' => true,
				'id'    => true,
				'style' => true,
			],
			'p'      => [
				'class' => true,
				'id'    => true,
				'style' => true,
			],
			'a'      => [
				'href'   => true,
				'title'  => true,
				'target' => true,
				'class'  => true,
				'rel'    => true,
			],
			'strong' => [],
			'em'     => [],
			'br'     => [],
			'hr'     => [
				'class' => true,
			],
			'ul'     => [
				'class' => true,
			],
			'ol'     => [
				'class' => true,
			],
			'li'     => [
				'class' => true,
			],
			'h1'     => [
				'class' => true,
				'id'    => true,
			],
			'h2'     => [
				'class' => true,
				'id'    => true,
			],
			'h3'     => [
				'class' => true,
				'id'    => true,
			],
			'h4'     => [
				'class' => true,
				'id'    => true,
			],
			'h5'     => [
				'class' => true,
				'id'    => true,
			],
			'h6'     => [
				'class' => true,
				'id'    => true,
			],
			'img'    => [
				'src'    => true,
				'alt'    => true,
				'title'  => true,
				'width'  => true,
				'height' => true,
				'class'  => true,
			],
			'table'  => [
				'class' => true,
			],
			'thead'  => [],
			'tbody'  => [],
			'tr'     => [
				'class' => true,
			],
			'th'     => [
				'class'   => true,
				'colspan' => true,
				'rowspan' => true,
			],
			'td'     => [
				'class'   => true,
				'colspan' => true,
				'rowspan' => true,
			],
			'code'   => [
				'class' => true,
			],
			'pre'    => [
				'class' => true,
			],
		];
	}

	/**
	 * Sanitize the input value
	 *
	 * Custom HTML fields don't store values, so no sanitization needed.
	 *
	 * @param mixed $input Raw input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		// This field doesn't store values
		return null;
	}

	/**
	 * Validate the input value
	 *
	 * Custom HTML fields don't store values, so always valid.
	 *
	 * @param mixed $input Input value to validate.
	 * @return array
	 */
	public function validate( $input ): array {
		return [
			'valid'  => true,
			'errors' => [],
		];
	}
}
