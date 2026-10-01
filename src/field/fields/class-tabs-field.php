<?php
/**
 * TabsField for Cassette-CMF
 *
 * A container field that organizes nested fields into tabs.
 * Supports both horizontal and vertical tab layouts.
 * The tabs field itself doesn't store data - only nested fields save/load values.
 *
 * @package PedalCMS\CassetteCMF
 * @since 1.0.0
 */

namespace PedalCMS\CassetteCMF\Field\Fields;

use PedalCMS\CassetteCMF\Field\Abstract_Field;
use PedalCMS\CassetteCMF\Field\Container_Field_Interface;
use PedalCMS\CassetteCMF\Field\Field_Factory;

/**
 * Tabs_Field - Organizes fields into tabbed interface
 *
 * Configuration options:
 * - orientation: 'horizontal' or 'vertical' (default: 'horizontal')
 * - tabs: Array of tab definitions, each containing:
 *   - id: Unique tab identifier
 *   - label: Tab display label
 *   - icon: Optional dashicon class (e.g., 'dashicons-admin-generic')
 *   - description: Optional tab description
 *   - fields: Array of field configurations
 * - default_tab: ID of the tab to show by default (defaults to first tab)
 */
class Tabs_Field extends Abstract_Field implements Container_Field_Interface {

	/**
	 * Container field with no single focusable control of its own — a
	 * settings-page title has nothing to be wrapped in a label for.
	 *
	 * @var bool
	 */
	protected bool $uses_label_wrapper = false;

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
				'label'       => '', // Hide label by default for tabs field
				'orientation' => 'horizontal',
				'tabs'        => [],
				'default_tab' => '',
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
	 * Extracts all field configurations from all tabs so they can be
	 * registered individually for saving/loading.
	 *
	 * @return array<array<string, mixed>>
	 */
	public function get_nested_fields(): array {
		$nested_fields = [];
		$tabs          = $this->config['tabs'] ?? [];

		foreach ( $tabs as $tab ) {
			if ( isset( $tab['fields'] ) && is_array( $tab['fields'] ) ) {
				foreach ( $tab['fields'] as $field ) {
					$nested_fields[] = $field;
				}
			}
		}

		return $nested_fields;
	}

	/**
	 * Render the tabs field
	 *
	 * @param mixed $value Not used - container fields don't store values.
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		global $post;

		$orientation = $this->config['orientation'] ?? 'horizontal';
		$tabs        = $this->config['tabs'] ?? [];
		$default_tab = ! empty( $this->config['default_tab'] ) ? (string) $this->config['default_tab'] : ( ! empty( $tabs ) ? $this->get_tab_id( reset( $tabs ), array_key_first( $tabs ) ) : '' );
		$field_id    = $this->get_field_id();

		if ( empty( $tabs ) ) {
			return '';
		}

		// Determine context:
		// - For CPT metaboxes: use post ID from global $post
		// - For settings pages: use page_id passed as $value parameter
		// - Container fields receive their context via $value parameter from Registrar
		if ( isset( $post ) && is_object( $post ) && isset( $post->ID ) ) {
			$context = $post->ID;
		} elseif ( is_string( $value ) ) {
			// Settings page: $value is the page_id
			$context = $value;
		} else {
			$context = null;
		}

		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		// Choose layout based on orientation
		if ( 'vertical' === $orientation ) {
			$output .= $this->render_vertical_tabs( $tabs, $default_tab, $field_id, $context );
		} else {
			$output .= $this->render_horizontal_tabs( $tabs, $default_tab, $field_id, $context );
		}

		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Get a tab's ID, falling back to 'tab-{index}' when none is configured
	 *
	 * @param array<string, mixed> $tab   Tab definition.
	 * @param int|string           $index Tab's key in the tabs array.
	 * @return string
	 */
	protected function get_tab_id( array $tab, $index ): string {
		return isset( $tab['id'] ) && '' !== (string) $tab['id'] ? (string) $tab['id'] : 'tab-' . $index;
	}

	/**
	 * Render horizontal tabs layout
	 *
	 * @param array<array<string, mixed>> $tabs        Tab definitions.
	 * @param string                      $default_tab Default active tab ID.
	 * @param string                      $field_id    Field ID.
	 * @param mixed                       $context     Context (post ID or null).
	 * @return string HTML output.
	 */
	protected function render_horizontal_tabs( array $tabs, string $default_tab, string $field_id, $context = null ): string {
		return $this->render_tabs_markup( $tabs, $default_tab, $field_id, $context, 'cassette-cmf-tabs-horizontal' );
	}

	/**
	 * Render vertical tabs layout
	 *
	 * @param array<array<string, mixed>> $tabs        Tab definitions.
	 * @param string                      $default_tab Default active tab ID.
	 * @param string                      $field_id    Field ID.
	 * @param mixed                       $context     Context (post ID or null).
	 * @return string HTML output.
	 */
	protected function render_vertical_tabs( array $tabs, string $default_tab, string $field_id, $context = null ): string {
		return $this->render_tabs_markup( $tabs, $default_tab, $field_id, $context, 'cassette-cmf-tabs-vertical' );
	}

	/**
	 * Render the tabs markup shared by both orientations
	 *
	 * Implements the WAI-ARIA tabs pattern: the nav is a tablist, each
	 * button is a tab with aria-controls/aria-selected and a roving
	 * tabindex (0 on the active tab, -1 on the rest, so the tablist is a
	 * single stop in the page tab order), and each panel is a tabpanel
	 * linked back to its tab via aria-labelledby.
	 *
	 * @param array<array<string, mixed>> $tabs              Tab definitions.
	 * @param string                      $default_tab       Default active tab ID.
	 * @param string                      $field_id          Field ID.
	 * @param mixed                       $context           Context (post ID or null).
	 * @param string                      $orientation_class Layout class: cassette-cmf-tabs-horizontal or -vertical.
	 * @return string HTML output.
	 */
	protected function render_tabs_markup( array $tabs, string $default_tab, string $field_id, $context, string $orientation_class ): string {
		$output = '<div class="cassette-cmf-tabs ' . $this->esc_attr( $orientation_class ) . '" id="' . $this->esc_attr( $field_id ) . '">';

		$tablist_label = $this->get_label();
		$tablist_attrs = '';
		if ( ! empty( $tablist_label ) ) {
			$tablist_attrs = ' aria-label="' . $this->esc_attr( $tablist_label ) . '"';
		}

		// Tab navigation
		$output .= '<div class="cassette-cmf-tabs-nav" role="tablist"' . $tablist_attrs . '>';
		foreach ( $tabs as $index => $tab ) {
			$tab_id    = $this->get_tab_id( $tab, $index );
			$tab_label = $tab['label'] ?? 'Tab ' . ( $index + 1 );
			$tab_icon  = $tab['icon'] ?? '';
			$is_active = ( $tab_id === $default_tab );
			$button_id = $field_id . '-tab-' . $tab_id;
			$panel_id  = $field_id . '-panel-' . $tab_id;

			$output .= '<button type="button" class="cassette-cmf-tab-button' . ( $is_active ? ' active' : '' ) . '"';
			$output .= ' data-tab="' . $this->esc_attr( $tab_id ) . '"';
			$output .= ' role="tab"';
			$output .= ' id="' . $this->esc_attr( $button_id ) . '"';
			$output .= ' aria-controls="' . $this->esc_attr( $panel_id ) . '"';
			$output .= ' aria-selected="' . ( $is_active ? 'true' : 'false' ) . '"';
			$output .= ' tabindex="' . ( $is_active ? '0' : '-1' ) . '"';
			$output .= '>';
			if ( $tab_icon ) {
				$output .= '<span class="dashicons ' . $this->esc_attr( $tab_icon ) . '" aria-hidden="true"></span> ';
			}
			$output .= $this->esc_html( $tab_label );
			$output .= '</button>';
		}
		$output .= '</div>';

		// Tab content
		$output .= '<div class="cassette-cmf-tabs-content">';
		foreach ( $tabs as $index => $tab ) {
			$tab_id    = $this->get_tab_id( $tab, $index );
			$is_active = ( $tab_id === $default_tab );
			$button_id = $field_id . '-tab-' . $tab_id;
			$panel_id  = $field_id . '-panel-' . $tab_id;

			$output .= '<div class="cassette-cmf-tab-panel' . ( $is_active ? ' active' : '' ) . '"';
			$output .= ' data-tab="' . $this->esc_attr( $tab_id ) . '"';
			$output .= ' role="tabpanel"';
			$output .= ' id="' . $this->esc_attr( $panel_id ) . '"';
			$output .= ' aria-labelledby="' . $this->esc_attr( $button_id ) . '"';
			$output .= ' tabindex="0"';
			$output .= '>';

			if ( ! empty( $tab['description'] ) ) {
				$output .= '<p class="description">' . $this->esc_html( $tab['description'] ) . '</p>';
			}

			$output .= $this->render_tab_fields( $tab['fields'] ?? [], $context );

			$output .= '</div>';
		}
		$output .= '</div>';

		$output .= '</div>';

		$this->enqueue_tab_scripts();

		return $output;
	}

	/**
	 * Render fields within a tab
	 *
	 * Creates field instances and renders them with their current values.
	 * Each nested field loads its own value using standard WordPress functions.
	 *
	 * @param array<array<string, mixed>> $fields  Field configurations.
	 * @param mixed                       $context Context (post ID for CPT, null for settings).
	 * @return string HTML output.
	 */
	protected function render_tab_fields( array $fields, $context = null ): string {
		if ( empty( $fields ) ) {
			return '<p class="description">No fields configured for this tab.</p>';
		}

		$output = '<table class="form-table" role="presentation">';

		foreach ( $fields as $field_config ) {
			$field_name = $field_config['name'] ?? '';

			if ( class_exists( '\PedalCMS\CassetteCMF\Field\Field_Factory' ) ) {
				try {
					$field = Field_Factory::create( $field_config );

					// Load the field value using standard WordPress functions
					$field_value = $this->load_field_value( $field_name, $context );

					// Render the field
					$field_html = $field->render( $field_value );

					// Remove only the first/top-level label, not labels inside nested fields (like groups)
					// This preserves labels for checkbox/radio options and nested container fields
					$field_html = preg_replace( '/<label[^>]*class="[^"]*cassette-cmf-field-label[^"]*"[^>]*>.*?<\/label>/s', '', $field_html, 1 );

					// For settings pages (when context is a string page_id), fix the name attribute
					if ( is_string( $context ) && ! empty( $context ) ) {
						$option_name = $field->get_option_name( $context );
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
					$output .= '<tr><td colspan="2">Error rendering field: ' . $this->esc_html( $e->getMessage() ) . '</td></tr>';
				}
			}
		}

		$output .= '</table>';

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
	 * @param mixed  $context_value Context (post ID for CPT, null for settings).
	 * @return mixed Field value.
	 */
	protected function load_field_value( string $field_name, $context_value = null ) {
		// For CPT metaboxes: use post meta (context is post ID)
		if ( is_int( $context_value ) && function_exists( 'get_post_meta' ) ) {
			return get_post_meta( $context_value, $field_name, true );
		}

		// For settings pages: use options (context is page_id string)
		if ( function_exists( 'get_option' ) ) {
			// If context is a string (page_id), check field config for prefix preference
			if ( is_string( $context_value ) && ! empty( $context_value ) ) {
				// Get the field config to check use_name_prefix
				$use_prefix = true; // Default to true
				foreach ( $this->get_nested_fields() as $field_config ) {
					if ( ( $field_config['name'] ?? '' ) === $field_name ) {
						$use_prefix = $field_config['use_name_prefix'] ?? true;
						break;
					}
				}

				$option_name = $use_prefix ? $context_value . '_' . $field_name : $field_name;
				return get_option( $option_name, '' );
			}
			// Fallback: try without prefix (for backward compatibility)
			return get_option( $field_name, '' );
		}

		return '';
	}

	/**
	 * Sanitize the tabs field value
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
	 * Validate the tabs field value
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
	 * Enqueue tab switching scripts
	 *
	 * Scripts are handled by cassette-cmf.js TabsField class
	 *
	 * @return void
	 */
	protected function enqueue_tab_scripts(): void {
		// JavaScript is handled by global cassette-cmf.js
		// No inline scripts needed
	}

	/**
	 * Enqueue assets for tabs field
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
				'orientation' => [
					'type' => 'string',
					'enum' => [ 'horizontal', 'vertical' ],
				],
				'tabs'        => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'          => [ 'type' => 'string' ],
							'label'       => [ 'type' => 'string' ],
							'icon'        => [ 'type' => 'string' ],
							'description' => [ 'type' => 'string' ],
							'fields'      => [ 'type' => 'array' ],
						],
					],
				],
			],
		];
	}
}
