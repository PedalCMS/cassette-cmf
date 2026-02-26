<?php
/**
 * MetaboxField for Cassette-CMF
 *
 * A container field that organizes nested fields into WordPress meta boxes.
 * The metabox field itself doesn't store data - only nested fields save/load values.
 *
 * This field allows you to define multiple meta boxes per post type or settings page,
 * each with their own set of fields. If a meta box doesn't exist, it will be created.
 * If it exists, fields will be added to it.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;
use Pedalcms\CassetteCmf\Field\Field_Factory;

/**
 * Metabox_Field - Organizes fields into WordPress meta boxes
 *
 * Configuration options:
 * - metabox_id: Unique identifier for the meta box (required)
 * - metabox_title: Display title for the meta box (defaults to metabox_id)
 * - context: Where to show the meta box: 'normal', 'side', 'advanced' (default: 'normal')
 * - priority: Priority within context: 'high', 'core', 'default', 'low' (default: 'default')
 * - fields: Array of field configurations to display in the meta box
 */
class Metabox_Field extends Abstract_Field implements Container_Field_Interface {

	/**
	 * Get default configuration values
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		$defaults = parent::get_defaults();
		return array_merge(
			$defaults,
			[
				'metabox_id'    => '',
				'metabox_title' => '',
				'context'       => 'normal',
				'priority'      => 'default',
				'fields'        => [],
			]
		);
	}

	/**
	 * Check if this is a container field
	 *
	 * @return bool
	 */
	public function is_container(): bool {
		return true;
	}

	/**
	 * Get all nested field configurations
	 *
	 * Extracts all field configurations from the metabox so they can be
	 * registered individually for saving/loading.
	 *
	 * @return array<array<string, mixed>>
	 */
	public function get_nested_fields(): array {
		return $this->config['fields'] ?? [];
	}

	/**
	 * Get the metabox ID
	 *
	 * @return string
	 */
	public function get_metabox_id(): string {
		if ( ! empty( $this->config['metabox_id'] ) ) {
			return $this->config['metabox_id'];
		}
		return $this->get_name();
	}

	/**
	 * Get the metabox title
	 *
	 * @return string
	 */
	public function get_metabox_title(): string {
		if ( ! empty( $this->config['metabox_title'] ) ) {
			return $this->config['metabox_title'];
		}
		if ( ! empty( $this->config['label'] ) ) {
			return $this->config['label'];
		}
		return ucwords( str_replace( '_', ' ', $this->get_metabox_id() ) );
	}

	/**
	 * Get the metabox context
	 *
	 * @return string
	 */
	public function get_context(): string {
		return $this->config['context'] ?? 'normal';
	}

	/**
	 * Get the metabox priority
	 *
	 * @return string
	 */
	public function get_priority(): string {
		return $this->config['priority'] ?? 'default';
	}

	/**
	 * Render the metabox field
	 *
	 * Note: The actual meta box is registered via WordPress add_meta_box().
	 * This render method outputs the fields within the metabox.
	 *
	 * @param mixed $value Not used - container fields don't store values.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		global $post;

		$fields = $this->config['fields'] ?? [];

		if ( empty( $fields ) ) {
			return '<p class="description">No fields configured for this metabox.</p>';
		}

		// Determine context:
		// - For CPT metaboxes: use post ID from global $post
		// - For settings pages: use page_id passed as $value parameter
		if ( isset( $post ) && is_object( $post ) && isset( $post->ID ) ) {
			$context = $post->ID;
		} elseif ( is_string( $value ) ) {
			// Settings page: $value is the page_id
			$context = $value;
		} else {
			$context = null;
		}

		// WordPress automatically applies .metabox-location-side class to sidebar metaboxes
		// which we can target in CSS for responsive layout
		$output  = '<div class="cassette-cmf-metabox-fields">';
		$output .= $this->render_metabox_fields( $fields, $context );
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render fields within a metabox
	 *
	 * Creates field instances and renders them with their current values.
	 * Each nested field loads its own value using standard WordPress functions.
	 *
	 * @param array<array<string, mixed>> $fields  Field configurations.
	 * @param mixed                       $context Context (post ID for CPT, page_id for settings).
	 * @return string HTML output.
	 */
	protected function render_metabox_fields( array $fields, $context = null ): string {
		if ( empty( $fields ) ) {
			return '<p class="description">No fields configured for this metabox.</p>';
		}

		// Check if all fields are container fields (tabs, groups, repeaters)
		$has_container_fields = false;
		$has_regular_fields   = false;

		foreach ( $fields as $field_config ) {
			if ( class_exists( '\Pedalcms\CassetteCmf\Field\Field_Factory' ) ) {
				try {
					$field = Field_Factory::create( $field_config );
					if ( $field instanceof \Pedalcms\CassetteCmf\Field\Container_Field_Interface ) {
						$has_container_fields = true;
					} else {
						$has_regular_fields = true;
					}
				} catch ( \Exception $e ) {
					continue;
				}
			}
		}

		// If only container fields, render them directly
		// If regular fields, wrap them in a form-table for proper formatting
		$output = '';

		if ( $has_regular_fields && ! $has_container_fields ) {
			// Only regular fields - use form-table structure like tabs/groups
			$output .= '<table class="form-table" role="presentation">';

			foreach ( $fields as $field_config ) {
				$field_name = $field_config['name'] ?? '';

				if ( class_exists( '\Pedalcms\CassetteCmf\Field\Field_Factory' ) ) {
					try {
						$field = Field_Factory::create( $field_config );

						// Regular fields: load value and render
						$field_value = $this->load_field_value( $field_name, $context );
						$field_html  = $field->render( $field_value );

						// Remove only the first/top-level label, not labels inside nested fields (like groups)
						// This preserves labels for checkbox/radio options and nested container fields
						$field_html = preg_replace( '/<label[^>]*class="[^"]*cassette-cmf-field-label[^"]*"[^>]*>.*?<\/label>/s', '', $field_html, 1 );

						// For settings pages (when context is a string page_id), fix the name attribute
						if ( is_string( $context ) && ! empty( $context ) ) {
							$option_name = $context . '_' . $field_name;
							$field_html  = str_replace(
								'name="' . $field_name . '"',
								'name="' . $option_name . '"',
								$field_html
							);
							// Also handle array fields like checkboxes: name="field_name[]"
							$field_html = str_replace(
								'name="' . $field_name . '[]"',
								'name="' . $option_name . '[]"',
								$field_html
							);
						}

						$output .= '<tr>';
						$output .= '<th scope="row">' . $this->esc_html( $field->get_label() ) . '</th>';
						$output .= '<td>' . $field_html . '</td>';
						$output .= '</tr>';
					} catch ( \Exception $e ) {
						$output .= '<tr><td colspan="2"><div class="error"><p>Error rendering field: ' . $this->esc_html( $e->getMessage() ) . '</p></div></td></tr>';
					}
				}
			}

			$output .= '</table>';
		} else {
			// Mixed or only container fields - render directly
			foreach ( $fields as $field_config ) {
				$field_name = $field_config['name'] ?? '';

				if ( class_exists( '\Pedalcms\CassetteCmf\Field\Field_Factory' ) ) {
					try {
						$field = Field_Factory::create( $field_config );

						// For container fields (tabs, etc), pass context directly
						// For regular fields, load and pass the field value
						if ( $field instanceof \Pedalcms\CassetteCmf\Field\Container_Field_Interface ) {
							// Container fields need context to pass to nested fields
							$field_html = $field->render( $context );
						} else {
							// Regular fields: load value and render
							$field_value = $this->load_field_value( $field_name, $context );
							$field_html  = $field->render( $field_value );
						}

						// For settings pages (when context is a string page_id), fix the name attribute
						if ( is_string( $context ) && ! empty( $context ) ) {
							$option_name = $context . '_' . $field_name;
							$field_html  = str_replace(
								'name="' . $field_name . '"',
								'name="' . $option_name . '"',
								$field_html
							);
							// Also handle array fields like checkboxes: name="field_name[]"
							$field_html = str_replace(
								'name="' . $field_name . '[]"',
								'name="' . $option_name . '[]"',
								$field_html
							);
						}

						$output .= $field_html;
					} catch ( \Exception $e ) {
						$output .= '<div class="error"><p>Error rendering field: ' . $this->esc_html( $e->getMessage() ) . '</p></div>';
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Load a field value from WordPress
	 *
	 * Determines context and loads value accordingly:
	 * - For CPT metaboxes: use get_post_meta() with post ID
	 * - For settings pages: use get_option() with field name
	 *
	 * @param string $field_name    Field name.
	 * @param mixed  $context_value Context (post ID for CPT, page_id for settings).
	 * @return mixed Field value.
	 */
	protected function load_field_value( string $field_name, $context_value = null ) {
		// For CPT metaboxes: use post meta (context is post ID)
		if ( is_int( $context_value ) && function_exists( 'get_post_meta' ) ) {
			return get_post_meta( $context_value, $field_name, true );
		}

		// For settings pages: use options (context is page_id string)
		if ( function_exists( 'get_option' ) ) {
			// If context is a string (page_id), prefix the field name
			if ( is_string( $context_value ) && ! empty( $context_value ) ) {
				$option_name = $context_value . '_' . $field_name;
				return get_option( $option_name, '' );
			}
			// Fallback: try without prefix (for backward compatibility)
			return get_option( $field_name, '' );
		}

		return '';
	}

	/**
	 * Sanitize the metabox field value
	 *
	 * Container fields don't store values themselves, so return empty array.
	 * Nested fields handle their own sanitization.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return array Empty array.
	 */
	public function sanitize( $value ) {
		return [];
	}

	/**
	 * Validate the metabox field value
	 *
	 * Container fields don't validate themselves.
	 * Nested fields handle their own validation.
	 *
	 * @param mixed $input Input value.
	 * @return array Validation result.
	 */
	public function validate( $input ): array {
		return [
			'valid'  => true,
			'errors' => [],
		];
	}

	/**
	 * Enqueue assets for metabox field
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Styles are loaded from cassette-cmf.scss
		// No inline CSS needed
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
				'metabox_id'    => [ 'type' => 'string' ],
				'metabox_title' => [ 'type' => 'string' ],
				'context'       => [
					'type' => 'string',
					'enum' => [ 'normal', 'side', 'advanced' ],
				],
				'priority'      => [
					'type' => 'string',
					'enum' => [ 'high', 'core', 'default', 'low' ],
				],
				'fields'        => [ 'type' => 'array' ],
			],
		];
	}
}
