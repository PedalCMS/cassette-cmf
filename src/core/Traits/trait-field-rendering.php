<?php
/**
 * Field Rendering Trait
 *
 * Provides common field rendering functionality shared across handlers.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core\Traits;

use Pedalcms\CassetteCmf\Field\Field_Interface;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;

/**
 * Trait Field_Rendering_Trait
 *
 * Common field rendering logic for CPT and Settings handlers.
 */
trait Field_Rendering_Trait {

	/**
	 * Render a field for settings page
	 *
	 * @param Field_Interface $field       Field instance.
	 * @param string          $option_name Option name for the field.
	 * @param string          $page_id     Settings page ID.
	 * @return string Rendered HTML.
	 */
	protected function render_settings_field_html( Field_Interface $field, string $option_name, string $page_id ): string {
		// Container fields pass context, regular fields pass value
		if ( $field instanceof Container_Field_Interface ) {
			return $field->render( $page_id );
		}

		// Get current value
		$value      = function_exists( 'get_option' ) ? get_option( $option_name, '' ) : '';
		$field_html = $field->render( $value );

		// Remove only the first/top-level label, not labels inside nested fields (like groups)
		// This preserves labels for checkbox/radio options and nested container fields
		$field_html = preg_replace( '/<label[^>]*class="[^"]*cassette-cmf-field-label[^"]*"[^>]*>.*?<\/label>/s', '', $field_html, 1 );

		// Replace field name with option name
		$field_html = $this->replace_field_name( $field_html, $field->get_name(), $option_name );

		return $field_html;
	}

	/**
	 * Render a field for CPT metabox
	 *
	 * @param Field_Interface $field   Field instance.
	 * @param int             $post_id Post ID.
	 * @return string Rendered HTML.
	 */
	protected function render_cpt_field_html( Field_Interface $field, int $post_id ): string {
		// Container fields don't have values
		if ( $field instanceof Container_Field_Interface ) {
			return $field->render( null );
		}

		// Get value from post meta
		$value = function_exists( 'get_post_meta' )
			? get_post_meta( $post_id, $field->get_name(), true )
			: '';

		return $field->render( $value );
	}

	/**
	 * Replace field name in HTML with option name
	 *
	 * @param string $html        HTML content.
	 * @param string $field_name  Original field name.
	 * @param string $option_name New option name.
	 * @return string Modified HTML.
	 */
	protected function replace_field_name( string $html, string $field_name, string $option_name ): string {
		// Regular name attribute
		$html = str_replace(
			'name="' . $field_name . '"',
			'name="' . $option_name . '"',
			$html
		);

		// Array names (checkboxes, multi-select)
		$html = str_replace(
			'name="' . $field_name . '[]"',
			'name="' . $option_name . '[]"',
			$html
		);

		// Nested names (repeaters)
		$html = str_replace(
			'name="' . $field_name . '[',
			'name="' . $option_name . '[',
			$html
		);

		return $html;
	}

	/**
	 * Output a nonce field
	 *
	 * @param string $action Nonce action.
	 * @param string $name   Nonce field name.
	 * @return void
	 */
	protected function render_nonce_field( string $action, string $name ): void {
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $action, $name );
		}
	}

	/**
	 * Verify a nonce
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Nonce action.
	 * @return bool
	 */
	protected function verify_nonce( string $nonce, string $action ): bool {
		if ( ! function_exists( 'wp_verify_nonce' ) ) {
			return true; // Allow in test environment
		}

		return (bool) wp_verify_nonce( $nonce, $action );
	}
}
