<?php
/**
 * RepeaterField for Cassette-CMF
 *
 * A container field that creates repeatable sets of nested fields.
 * Unlike other container fields, the repeater stores its own data as a serialized array
 * containing all the repeated field values.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;
use Pedalcms\CassetteCmf\Field\Field_Factory;

/**
 * Repeater_Field - Creates repeatable sets of fields
 *
 * Configuration options:
 * - fields: Array of field configurations for each repeatable row
 * - min_rows: Minimum number of rows (default: 0)
 * - max_rows: Maximum number of rows (default: unlimited)
 * - button_label: Label for the "Add Row" button (default: 'Add Row')
 * - row_label: Label template for each row (supports {{index}} placeholder)
 * - collapsible: Whether rows can be collapsed (default: true)
 * - collapsed: Whether rows start collapsed (default: false)
 * - sortable: Whether rows can be reordered (default: true)
 *
 * Data is stored as a serialized array with structure:
 * [
 *   [ 'field1' => 'value1', 'field2' => 'value2' ],
 *   [ 'field1' => 'value3', 'field2' => 'value4' ],
 *   ...
 * ]
 */
class Repeater_Field extends Abstract_Field {

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
				'fields'       => [],
				'min_rows'     => 0,
				'max_rows'     => 0, // 0 = unlimited
				'button_label' => 'Add Row',
				'row_label'    => 'Row {{index}}',
				'collapsible'  => true,
				'collapsed'    => false,
				'sortable'     => true,
			]
		);
	}

	/**
	 * Get the field configurations for each row
	 *
	 * @return array<array<string, mixed>>
	 */
	public function get_sub_fields(): array {
		return $this->config['fields'] ?? [];
	}

	/**
	 * Render the repeater field
	 *
	 * @param mixed $value Current value (array of rows).
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$field_name   = $this->get_name();
		$field_id     = $this->get_field_id();
		$sub_fields   = $this->get_sub_fields();
		$min_rows     = (int) ( $this->config['min_rows'] ?? 0 );
		$max_rows     = (int) ( $this->config['max_rows'] ?? 0 );
		$button_label = $this->config['button_label'] ?? 'Add Row';
		$row_label    = $this->config['row_label'] ?? 'Row {{index}}';
		$collapsible  = $this->config['collapsible'] ?? true;
		$collapsed    = $this->config['collapsed'] ?? false;
		$sortable     = $this->config['sortable'] ?? true;

		// Ensure value is an array
		$rows = is_array( $value ) ? $value : [];

		// Add minimum rows if needed.
		$row_count = count( $rows );
		while ( $row_count < $min_rows ) {
			$rows[] = [];
			++$row_count;
		}

		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		// Main repeater container
		$output .= '<div class="cassette-cmf-repeater" ';
		$output .= 'id="' . $this->esc_attr( $field_id ) . '" ';
		$output .= 'data-field-name="' . $this->esc_attr( $field_name ) . '" ';
		$output .= 'data-min-rows="' . $this->esc_attr( (string) $min_rows ) . '" ';
		$output .= 'data-max-rows="' . $this->esc_attr( (string) $max_rows ) . '" ';
		$output .= 'data-sortable="' . ( $sortable ? 'true' : 'false' ) . '" ';
		$output .= 'data-collapsible="' . ( $collapsible ? 'true' : 'false' ) . '">';

		// Rows container
		$output .= '<div class="cassette-cmf-repeater-rows">';

		// Render existing rows
		foreach ( $rows as $row_index => $row_data ) {
			$output .= $this->render_row( $row_index, $row_data, $sub_fields, $field_name, $row_label, $collapsible, $collapsed );
		}

		$output .= '</div>'; // .cassette-cmf-repeater-rows

		// Add row button
		$can_add = ( 0 === $max_rows || count( $rows ) < $max_rows );
		$output .= '<div class="cassette-cmf-repeater-actions">';
		$output .= '<button type="button" class="button cassette-cmf-repeater-add" ' . ( ! $can_add ? 'disabled' : '' ) . '>';
		$output .= '<span class="dashicons dashicons-plus-alt2"></span> ';
		$output .= $this->esc_html( $button_label );
		$output .= '</button>';
		$output .= '</div>';

		// Hidden template for new rows (used by JavaScript)
		$output .= '<script type="text/template" class="cassette-cmf-repeater-template">';
		$output .= $this->render_row( '{{INDEX}}', [], $sub_fields, $field_name, $row_label, $collapsible, false );
		$output .= '</script>';

		$output .= '</div>'; // .cassette-cmf-repeater

		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		$this->enqueue_repeater_scripts();
		$this->enqueue_assets();

		return $output;
	}

	/**
	 * Render a single repeater row
	 *
	 * @param int|string                  $row_index   Row index.
	 * @param array<string, mixed>        $row_data    Row data.
	 * @param array<array<string, mixed>> $sub_fields  Sub-field configurations.
	 * @param string                      $field_name  Parent field name.
	 * @param string                      $row_label   Row label template.
	 * @param bool                        $collapsible Whether row is collapsible.
	 * @param bool                        $collapsed   Whether row starts collapsed.
	 * @return string HTML output.
	 */
	protected function render_row( $row_index, array $row_data, array $sub_fields, string $field_name, string $row_label, bool $collapsible, bool $collapsed ): string {
		$label       = str_replace( '{{index}}', (string) ( is_int( $row_index ) ? $row_index + 1 : $row_index ), $row_label );
		$row_classes = 'cassette-cmf-repeater-row';

		if ( $collapsed && $collapsible ) {
			$row_classes .= ' collapsed';
		}

		$output = '<div class="' . $row_classes . '" data-row-index="' . $this->esc_attr( (string) $row_index ) . '">';

		// Row header
		$output .= '<div class="cassette-cmf-repeater-row-header">';

		// Drag handle (if sortable)
		$output .= '<span class="cassette-cmf-repeater-drag-handle dashicons dashicons-move" title="Drag to reorder"></span>';

		// Row label
		$output .= '<span class="cassette-cmf-repeater-row-label">' . $this->esc_html( $label ) . '</span>';

		// Row actions
		$output .= '<div class="cassette-cmf-repeater-row-actions">';

		if ( $collapsible ) {
			$output .= '<button type="button" class="cassette-cmf-repeater-toggle" title="Toggle">';
			$output .= '<span class="dashicons dashicons-arrow-down"></span>';
			$output .= '</button>';
		}

		$output .= '<button type="button" class="cassette-cmf-repeater-remove" title="Remove">';
		$output .= '<span class="dashicons dashicons-trash"></span>';
		$output .= '</button>';

		$output .= '</div>'; // .cassette-cmf-repeater-row-actions
		$output .= '</div>'; // .cassette-cmf-repeater-row-header

		// Row content (fields)
		$content_style = ( $collapsed && $collapsible ) ? ' style="display: none;"' : '';
		$output       .= '<div class="cassette-cmf-repeater-row-content"' . $content_style . '>';
		$output       .= '<table class="form-table cassette-cmf-repeater-fields" role="presentation">';

		foreach ( $sub_fields as $sub_field_config ) {
			$sub_field_name = $sub_field_config['name'] ?? '';

			if ( empty( $sub_field_name ) ) {
				continue;
			}

			try {
				$sub_field = Field_Factory::create( $sub_field_config );

				// Get value for this sub-field from row data
				$sub_value = $row_data[ $sub_field_name ] ?? '';

				// Render the sub-field
				$sub_html = $sub_field->render( $sub_value );

				// Remove only the first/top-level label, not labels inside nested fields (like groups)
				// This preserves labels for checkbox/radio options and nested container fields
				$sub_html = preg_replace( '/<label[^>]*class="[^"]*cassette-cmf-field-label[^"]*"[^>]*>.*?<\/label>/s', '', $sub_html, 1 );
				// To: name="repeater_name[row_index][sub_field_name]"
				$original_name = $sub_field_name;
				$new_name      = $field_name . '[' . $row_index . '][' . $original_name . ']';

				$sub_html = str_replace(
					'name="' . $original_name . '"',
					'name="' . $new_name . '"',
					$sub_html
				);

				// Also handle array fields like checkboxes
				$sub_html = str_replace(
					'name="' . $original_name . '[]"',
					'name="' . $new_name . '[]"',
					$sub_html
				);

				// Update ID to be unique per row
				$original_id = 'field-' . $original_name;
				$new_id      = 'field-' . $field_name . '-' . $row_index . '-' . $original_name;
				$sub_html    = str_replace(
					'id="' . $original_id . '"',
					'id="' . $new_id . '"',
					$sub_html
				);

				$output .= '<tr>';
				$output .= '<th scope="row">' . $this->esc_html( $sub_field->get_label() ) . '</th>';
				$output .= '<td>' . $sub_html . '</td>';
				$output .= '</tr>';
			} catch ( \Exception $e ) {
				$output .= '<tr><td colspan="2">Error: ' . $this->esc_html( $e->getMessage() ) . '</td></tr>';
			}
		}

		$output .= '</table>';
		$output .= '</div>'; // .cassette-cmf-repeater-row-content
		$output .= '</div>'; // .cassette-cmf-repeater-row

		return $output;
	}

	/**
	 * Sanitize the repeater value
	 *
	 * Sanitizes each row's fields using their respective sanitize methods.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return array Sanitized array of rows.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sub_fields     = $this->get_sub_fields();
		$sanitized_rows = [];

		foreach ( $value as $row_index => $row_data ) {
			if ( ! is_array( $row_data ) ) {
				continue;
			}

			$sanitized_row = [];

			foreach ( $sub_fields as $sub_field_config ) {
				$sub_field_name = $sub_field_config['name'] ?? '';

				if ( empty( $sub_field_name ) ) {
					continue;
				}

				try {
					$sub_field   = Field_Factory::create( $sub_field_config );
					$field_value = $row_data[ $sub_field_name ] ?? '';

					$sanitized_row[ $sub_field_name ] = $sub_field->sanitize( $field_value );
				} catch ( \Exception $e ) {
					// If field creation fails, skip this field
					continue;
				}
			}

			if ( ! empty( $sanitized_row ) ) {
				$sanitized_rows[] = $sanitized_row;
			}
		}

		return $sanitized_rows;
	}

	/**
	 * Validate the repeater value
	 *
	 * Validates each row's fields using their respective validate methods.
	 *
	 * @param mixed $input Value to validate.
	 * @return array Validation result.
	 */
	public function validate( $input ): array {
		$errors     = [];
		$sub_fields = $this->get_sub_fields();
		$min_rows   = (int) ( $this->config['min_rows'] ?? 0 );
		$max_rows   = (int) ( $this->config['max_rows'] ?? 0 );

		if ( ! is_array( $input ) ) {
			$input = [];
		}

		$row_count = count( $input );

		// Check minimum rows
		if ( $min_rows > 0 && $row_count < $min_rows ) {
			$errors[] = sprintf( 'At least %d row(s) required.', $min_rows );
		}

		// Check maximum rows
		if ( $max_rows > 0 && $row_count > $max_rows ) {
			$errors[] = sprintf( 'Maximum %d row(s) allowed.', $max_rows );
		}

		// Validate each row
		foreach ( $input as $row_index => $row_data ) {
			if ( ! is_array( $row_data ) ) {
				continue;
			}

			foreach ( $sub_fields as $sub_field_config ) {
				$sub_field_name = $sub_field_config['name'] ?? '';

				if ( empty( $sub_field_name ) ) {
					continue;
				}

				try {
					$sub_field   = Field_Factory::create( $sub_field_config );
					$field_value = $row_data[ $sub_field_name ] ?? '';

					$result = $sub_field->validate( $field_value );

					if ( ! $result['valid'] && ! empty( $result['errors'] ) ) {
						foreach ( $result['errors'] as $error ) {
							$errors[] = sprintf( 'Row %d - %s: %s', $row_index + 1, $sub_field->get_label(), $error );
						}
					}
				} catch ( \Exception $e ) {
					continue;
				}
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Enqueue repeater JavaScript
	 *
	 * Scripts are handled by cassette-cmf.js RepeaterField class
	 *
	 * @return void
	 */
	protected function enqueue_repeater_scripts(): void {
		// JavaScript is handled by global cassette-cmf.js
		// No inline scripts needed
	}

	/**
	 * Enqueue assets for repeater field
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Make sure jQuery UI Sortable is available
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		// Styles are loaded from cassette-cmf.scss
		// No inline CSS needed
	}

	/**
	 * Get field schema for JSON validation
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema(): array {
		$base_schema = parent::get_schema();

		return array_merge(
			$base_schema,
			[
				'fields'       => $this->get_sub_fields(),
				'min_rows'     => $this->config['min_rows'] ?? 0,
				'max_rows'     => $this->config['max_rows'] ?? 0,
				'button_label' => $this->config['button_label'] ?? 'Add Row',
				'row_label'    => $this->config['row_label'] ?? 'Row {{index}}',
				'collapsible'  => $this->config['collapsible'] ?? true,
				'collapsed'    => $this->config['collapsed'] ?? false,
				'sortable'     => $this->config['sortable'] ?? true,
			]
		);
	}
}
