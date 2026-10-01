<?php
/**
 * Additional Field Type Tests
 *
 * Tests for field types that were not fully covered in Test_Field_Types.php
 * Includes: WysiwygField, RepeaterField, and additional rendering tests.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Field\Field_Factory;

/**
 * Class Test_Additional_Field_Types
 *
 * Additional tests for field types.
 */
class Test_Additional_Field_Types extends WP_UnitTestCase {

	/**
	 * Reset Field_Factory between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Field_Factory::reset();
	}

	// =========================================================================
	// WysiwygField Tests
	// =========================================================================

	/**
	 * Test WysiwygField renders correctly.
	 */
	public function test_wysiwyg_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_wysiwyg',
				'type'  => 'wysiwyg',
				'label' => 'Content Editor',
			]
		);

		$html = $field->render( '<p>Test content</p>' );

		// Should contain textarea or editor element
		$this->assertNotEmpty( $html );
		$this->assertStringContainsString( 'test_wysiwyg', $html );
	}

	/**
	 * Test WysiwygField with custom rows.
	 */
	public function test_wysiwyg_field_custom_rows(): void {
		$field = Field_Factory::create(
			[
				'name'          => 'test_wysiwyg',
				'type'          => 'wysiwyg',
				'label'         => 'Content',
				'textarea_rows' => 20,
			]
		);

		$html = $field->render( '' );

		// Output should exist
		$this->assertNotEmpty( $html );
	}

	/**
	 * Test WysiwygField without media buttons.
	 */
	public function test_wysiwyg_field_no_media_buttons(): void {
		$field = Field_Factory::create(
			[
				'name'          => 'test_wysiwyg',
				'type'          => 'wysiwyg',
				'media_buttons' => false,
			]
		);

		$html = $field->render( '' );

		$this->assertNotEmpty( $html );
	}

	/**
	 * Test WysiwygField teeny mode.
	 */
	public function test_wysiwyg_field_teeny_mode(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_wysiwyg',
				'type'  => 'wysiwyg',
				'teeny' => true,
			]
		);

		$html = $field->render( '' );

		$this->assertNotEmpty( $html );
	}

	/**
	 * Test WysiwygField get_type returns correct type.
	 */
	public function test_wysiwyg_field_type(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_wysiwyg',
				'type' => 'wysiwyg',
			]
		);

		$this->assertSame( 'wysiwyg', $field->get_type() );
	}

	// =========================================================================
	// RepeaterField Tests
	// =========================================================================

	/**
	 * Test RepeaterField renders correctly.
	 */
	public function test_repeater_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'label'  => 'Repeater Field',
				'fields' => [
					[
						'name'  => 'sub_text',
						'type'  => 'text',
						'label' => 'Sub Text',
					],
				],
			]
		);

		$html = $field->render( [] );

		$this->assertNotEmpty( $html );
		$this->assertStringContainsString( 'cassette-cmf-repeater', $html );
	}

	/**
	 * Test RepeaterField with existing data.
	 */
	public function test_repeater_field_with_data(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'name',
						'type' => 'text',
					],
				],
			]
		);

		$data = [
			[ 'name' => 'Row 1' ],
			[ 'name' => 'Row 2' ],
		];

		$html = $field->render( $data );

		$this->assertNotEmpty( $html );
	}

	/**
	 * Test RepeaterField row_label_field uses the sub-field's value as
	 * the row label instead of the index-based row_label template.
	 *
	 * Regression test for #71.
	 */
	public function test_repeater_row_label_field(): void {
		$field = Field_Factory::create(
			[
				'name'            => 'test_repeater',
				'type'            => 'repeater',
				'row_label_field' => 'name',
				'fields'          => [
					[
						'name' => 'name',
						'type' => 'text',
					],
				],
			]
		);

		$data = [
			[ 'name' => 'Alice' ],
			[ 'name' => 'Bob' ],
		];

		$html = $field->render( $data );

		$this->assertStringContainsString( '<span class="cassette-cmf-repeater-row-label">Alice</span>', $html );
		$this->assertStringContainsString( '<span class="cassette-cmf-repeater-row-label">Bob</span>', $html );
		$this->assertStringNotContainsString( 'Row 1', $html );
		$this->assertStringNotContainsString( 'Row 2', $html );
	}

	/**
	 * Test RepeaterField falls back to the row_label template when the
	 * configured row_label_field sub-field is empty for a row.
	 *
	 * Regression test for #71.
	 */
	public function test_repeater_row_label_field_falls_back_when_empty(): void {
		$field = Field_Factory::create(
			[
				'name'            => 'test_repeater',
				'type'            => 'repeater',
				'row_label_field' => 'name',
				'fields'          => [
					[
						'name' => 'name',
						'type' => 'text',
					],
				],
			]
		);

		$data = [
			[ 'name' => '' ],
		];

		$html = $field->render( $data );

		$this->assertStringContainsString( '<span class="cassette-cmf-repeater-row-label">Row 1</span>', $html );
	}

	/**
	 * Test RepeaterField without row_label_field keeps existing
	 * index-based labeling (default behavior unchanged).
	 */
	public function test_repeater_without_row_label_field_uses_index(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'name',
						'type' => 'text',
					],
				],
			]
		);

		$data = [
			[ 'name' => 'Alice' ],
		];

		$html = $field->render( $data );

		$this->assertStringContainsString( '<span class="cassette-cmf-repeater-row-label">Row 1</span>', $html );
	}

	/**
	 * Test RepeaterField get_sub_fields returns config.
	 */
	public function test_repeater_get_sub_fields(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'field_a',
						'type' => 'text',
					],
					[
						'name' => 'field_b',
						'type' => 'number',
					],
				],
			]
		);

		$sub_fields = $field->get_sub_fields();

		$this->assertIsArray( $sub_fields );
		$this->assertCount( 2, $sub_fields );
	}

	/**
	 * Test RepeaterField with min_rows.
	 */
	public function test_repeater_min_rows(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_repeater',
				'type'     => 'repeater',
				'min_rows' => 2,
				'fields'   => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render( [] );

		// Should have at least 2 rows rendered
		$this->assertNotEmpty( $html );
		$this->assertStringContainsString( 'data-min-rows="2"', $html );
	}

	/**
	 * Test RepeaterField with max_rows.
	 */
	public function test_repeater_max_rows(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_repeater',
				'type'     => 'repeater',
				'max_rows' => 5,
				'fields'   => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render( [] );

		$this->assertStringContainsString( 'data-max-rows="5"', $html );
	}

	/**
	 * Test RepeaterField custom button label.
	 */
	public function test_repeater_button_label(): void {
		$field = Field_Factory::create(
			[
				'name'         => 'test_repeater',
				'type'         => 'repeater',
				'button_label' => 'Add New Item',
				'fields'       => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render( [] );

		$this->assertStringContainsString( 'Add New Item', $html );
	}

	/**
	 * Test RepeaterField collapsible option.
	 */
	public function test_repeater_collapsible(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_repeater',
				'type'        => 'repeater',
				'collapsible' => true,
				'fields'      => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render( [ [ 'text' => 'Test' ] ] );

		$this->assertStringContainsString( 'data-collapsible="true"', $html );
	}

	/**
	 * Test RepeaterField sortable option.
	 */
	public function test_repeater_sortable(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_repeater',
				'type'     => 'repeater',
				'sortable' => true,
				'fields'   => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render( [] );

		$this->assertStringContainsString( 'data-sortable="true"', $html );
	}

	/**
	 * Test RepeaterField renders keyboard-accessible move up/down buttons,
	 * with the first row's "move up" and the last row's "move down"
	 * disabled.
	 *
	 * Regression test for #72.
	 */
	public function test_repeater_move_buttons_disabled_at_ends(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render(
			[
				[ 'text' => 'Row A' ],
				[ 'text' => 'Row B' ],
				[ 'text' => 'Row C' ],
			]
		);

		// Three move-up and three move-down buttons for the rendered rows,
		// plus one more of each in the hidden template row used by JS.
		$this->assertSame( 4, substr_count( $html, 'cassette-cmf-repeater-move-up' ) );
		$this->assertSame( 4, substr_count( $html, 'cassette-cmf-repeater-move-down' ) );

		// aria-labels present for screen readers.
		$this->assertStringContainsString( 'aria-label="Move row up"', $html );
		$this->assertStringContainsString( 'aria-label="Move row down"', $html );

		// First row's move-up and last row's move-down are disabled; find
		// each row's block and check its own buttons individually.
		$rows = explode( 'data-row-index="0"', $html )[1];
		$rows = explode( 'data-row-index="1"', $rows )[0];
		$this->assertMatchesRegularExpression( '/cassette-cmf-repeater-move-up"[^>]*disabled/', $rows );
		$this->assertDoesNotMatchRegularExpression( '/cassette-cmf-repeater-move-down"[^>]*disabled/', $rows );

		$last_row = explode( 'data-row-index="2"', $html )[1];
		$this->assertDoesNotMatchRegularExpression( '/cassette-cmf-repeater-move-up"[^>]*disabled/', $last_row );
		$this->assertMatchesRegularExpression( '/cassette-cmf-repeater-move-down"[^>]*disabled/', $last_row );
	}

	/**
	 * Test RepeaterField renders a polite live region for keyboard-move
	 * announcements.
	 *
	 * Regression test for #72.
	 */
	public function test_repeater_renders_live_region(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'text',
						'type' => 'text',
					],
				],
			]
		);

		$html = $field->render( [] );

		$this->assertStringContainsString( 'cassette-cmf-repeater-announcer', $html );
		$this->assertStringContainsString( 'aria-live="polite"', $html );
	}

	/**
	 * Test RepeaterField type.
	 */
	public function test_repeater_field_type(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'fields' => [],
			]
		);

		$this->assertSame( 'repeater', $field->get_type() );
	}

	// =========================================================================
	// URLField Additional Tests
	// =========================================================================

	/**
	 * Test URLField renders with placeholder.
	 */
	public function test_url_field_placeholder(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_url',
				'type'        => 'url',
				'placeholder' => 'https://example.com',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'placeholder="https://example.com"', $html );
	}

	/**
	 * Test URLField renders with required.
	 */
	public function test_url_field_required(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_url',
				'type'     => 'url',
				'required' => true,
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'required', $html );
	}

	/**
	 * Test URLField renders with readonly.
	 */
	public function test_url_field_readonly(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_url',
				'type'     => 'url',
				'readonly' => true,
			]
		);

		$html = $field->render( 'https://example.com' );

		$this->assertStringContainsString( 'readonly', $html );
	}

	/**
	 * Test URLField renders with disabled.
	 */
	public function test_url_field_disabled(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_url',
				'type'     => 'url',
				'disabled' => true,
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'disabled', $html );
	}

	// =========================================================================
	// Password Field Additional Tests
	// =========================================================================

	/**
	 * Test PasswordField with autocomplete off.
	 */
	public function test_password_field_autocomplete(): void {
		$field = Field_Factory::create(
			[
				'name'         => 'test_password',
				'type'         => 'password',
				'autocomplete' => 'new-password',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'type="password"', $html );
	}

	/**
	 * Test PasswordField with placeholder.
	 */
	public function test_password_field_placeholder(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_password',
				'type'        => 'password',
				'placeholder' => 'Enter password',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'placeholder="Enter password"', $html );
	}

	// =========================================================================
	// Color Field Additional Tests
	// =========================================================================

	/**
	 * Test ColorField with default value.
	 */
	public function test_color_field_default(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_color',
				'type'    => 'color',
				'default' => '#ff0000',
			]
		);

		$html = $field->render( null );

		$this->assertStringContainsString( 'value="#ff0000"', $html );
	}

	// =========================================================================
	// Date Field Additional Tests
	// =========================================================================

	/**
	 * Test DateField with min date.
	 */
	public function test_date_field_min(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_date',
				'type' => 'date',
				'min'  => '2025-01-01',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'min="2025-01-01"', $html );
	}

	/**
	 * Test DateField with max date.
	 */
	public function test_date_field_max(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_date',
				'type' => 'date',
				'max'  => '2025-12-31',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'max="2025-12-31"', $html );
	}

	// =========================================================================
	// Number Field Additional Tests
	// =========================================================================

	/**
	 * Test NumberField with step.
	 */
	public function test_number_field_step(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_number',
				'type' => 'number',
				'step' => 0.01,
			]
		);

		$html = $field->render( 0 );

		$this->assertStringContainsString( 'step="0.01"', $html );
	}

	/**
	 * Test NumberField with placeholder.
	 */
	public function test_number_field_placeholder(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_number',
				'type'        => 'number',
				'placeholder' => 'Enter number',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'placeholder="Enter number"', $html );
	}

	// =========================================================================
	// Textarea Field Additional Tests
	// =========================================================================

	/**
	 * Test TextareaField with cols.
	 */
	public function test_textarea_field_cols(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_textarea',
				'type' => 'textarea',
				'cols' => 50,
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'cols="50"', $html );
	}

	/**
	 * Test TextareaField with maxlength.
	 */
	public function test_textarea_field_maxlength(): void {
		$field = Field_Factory::create(
			[
				'name'      => 'test_textarea',
				'type'      => 'textarea',
				'maxlength' => 500,
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'maxlength="500"', $html );
	}

	// =========================================================================
	// Field Description Tests
	// =========================================================================

	/**
	 * Test field renders description.
	 */
	public function test_field_renders_description(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test',
				'type'        => 'text',
				'description' => 'This is a helpful description',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'This is a helpful description', $html );
	}

	/**
	 * Test field renders label.
	 */
	public function test_field_renders_label(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test',
				'type'  => 'text',
				'label' => 'My Field Label',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'My Field Label', $html );
	}

	// =========================================================================
	// Custom_HTML_Field Tests
	// =========================================================================

	/**
	 * Test Custom_HTML_Field renders basic HTML content.
	 */
	public function test_custom_html_field_renders_content(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'label'   => 'Custom Content',
				'content' => '<p>This is custom HTML content.</p>',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'cassette-cmf-custom-html-content', $html );
		$this->assertStringContainsString( 'This is custom HTML content', $html );
		$this->assertStringContainsString( 'Custom Content', $html );
	}

	/**
	 * Test Custom_HTML_Field with complex HTML.
	 */
	public function test_custom_html_field_complex_html(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '<div class="notice"><strong>Important:</strong> Please review the settings.</div>',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'Important', $html );
		$this->assertStringContainsString( 'Please review the settings', $html );
	}

	/**
	 * Test Custom_HTML_Field with raw_html enabled.
	 */
	public function test_custom_html_field_raw_html(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_custom_html',
				'type'     => 'custom_html',
				'content'  => '<script>alert("test")</script>',
				'raw_html' => true,
			]
		);

		$html = $field->render();

		// With raw_html enabled, script tags should be present
		$this->assertStringContainsString( '<script>', $html );
	}

	/**
	 * Test Custom_HTML_Field sanitization removes dangerous content by default.
	 */
	public function test_custom_html_field_sanitizes_by_default(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '<p>Safe content</p><script>alert("xss")</script>',
			]
		);

		$html = $field->render();

		// Script should be stripped when raw_html is not enabled
		$this->assertStringContainsString( 'Safe content', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Test Custom_HTML_Field with description.
	 */
	public function test_custom_html_field_with_description(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_custom_html',
				'type'        => 'custom_html',
				'content'     => '<p>Info block</p>',
				'description' => 'This displays custom HTML.',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'This displays custom HTML', $html );
	}

	/**
	 * Test Custom_HTML_Field with empty content.
	 */
	public function test_custom_html_field_empty_content(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '',
			]
		);

		$html = $field->render();

		// Should still render wrapper but no content div
		$this->assertStringContainsString( 'cassette-cmf-field', $html );
		$this->assertStringNotContainsString( 'cassette-cmf-custom-html-content', $html );
	}

	/**
	 * Test Custom_HTML_Field sanitize returns null (doesn't store values).
	 */
	public function test_custom_html_field_sanitize_returns_null(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '<p>Content</p>',
			]
		);

		$result = $field->sanitize( 'any value' );

		$this->assertNull( $result );
	}

	/**
	 * Test Custom_HTML_Field validate always returns valid.
	 */
	public function test_custom_html_field_validate_always_valid(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '<p>Content</p>',
			]
		);

		$result = $field->validate( 'any value' );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test Custom_HTML_Field wrapper has correct data attributes.
	 */
	public function test_custom_html_field_wrapper_attributes(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '<p>Content</p>',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'data-field-name="test_custom_html"', $html );
		$this->assertStringContainsString( 'data-field-type="custom_html"', $html );
		$this->assertStringContainsString( 'cassette-cmf-field-custom_html', $html );
	}

	// =========================================================================
	// Upload_Field Tests
	// =========================================================================

	/**
	 * Test Upload_Field renders basic structure.
	 */
	public function test_upload_field_renders_basic_structure(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_upload',
				'type'  => 'upload',
				'label' => 'Featured Image',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'cassette-cmf-upload-container', $html );
		$this->assertStringContainsString( 'cassette-cmf-upload-value', $html );
		$this->assertStringContainsString( 'cassette-cmf-upload-button', $html );
		$this->assertStringContainsString( 'Featured Image', $html );
	}

	/**
	 * Test Upload_Field with custom button text.
	 */
	public function test_upload_field_custom_button_text(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_upload',
				'type'        => 'upload',
				'button_text' => 'Choose Image',
				'remove_text' => 'Clear',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'Choose Image', $html );
		$this->assertStringContainsString( 'Clear', $html );
	}

	/**
	 * Test Upload_Field with library type filter.
	 */
	public function test_upload_field_library_type(): void {
		$field = Field_Factory::create(
			[
				'name'         => 'test_upload',
				'type'         => 'upload',
				'library_type' => 'image',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'data-library-type="image"', $html );
	}

	/**
	 * Test Upload_Field with multiple selection.
	 */
	public function test_upload_field_multiple(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_upload',
				'type'     => 'upload',
				'multiple' => true,
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'data-multiple="true"', $html );
	}

	/**
	 * Test Upload_Field renders with existing value.
	 */
	public function test_upload_field_with_value(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$html = $field->render( 123 );

		$this->assertStringContainsString( 'value="123"', $html );
	}

	/**
	 * Test Upload_Field sanitize with numeric ID.
	 */
	public function test_upload_field_sanitize_numeric(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$result = $field->sanitize( '456' );

		$this->assertSame( 456, $result );
	}

	/**
	 * Test Upload_Field sanitize with URL.
	 */
	public function test_upload_field_sanitize_url(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$result = $field->sanitize( 'https://example.com/image.jpg' );

		$this->assertSame( 'https://example.com/image.jpg', $result );
	}

	/**
	 * Test Upload_Field sanitize with empty value.
	 */
	public function test_upload_field_sanitize_empty(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$result = $field->sanitize( '' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test Upload_Field sanitize with invalid value.
	 */
	public function test_upload_field_sanitize_invalid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$result = $field->sanitize( 'not-a-url-or-id' );

		$this->assertSame( '', $result );
	}

	/**
	 * Test Upload_Field validate passes for valid input.
	 */
	public function test_upload_field_validate_valid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$result = $field->validate( 123 );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test Upload_Field with description.
	 */
	public function test_upload_field_with_description(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_upload',
				'type'        => 'upload',
				'description' => 'Upload a featured image for this post.',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'Upload a featured image for this post.', $html );
	}

	/**
	 * Test Upload_Field wrapper has correct data attributes.
	 */
	public function test_upload_field_wrapper_attributes(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload',
				'type' => 'upload',
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'data-field-name="test_upload"', $html );
		$this->assertStringContainsString( 'data-field-type="upload"', $html );
		$this->assertStringContainsString( 'cassette-cmf-field-upload', $html );
	}

	/**
	 * Test Upload_Field includes necessary attributes for JS functionality.
	 *
	 * Note: The inline script is only rendered once per page (static variable),
	 * so we test that the HTML has proper structure for the JS to work.
	 */
	public function test_upload_field_includes_script(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_upload_script',
				'type' => 'upload',
			]
		);

		$html = $field->render();

		// Verify the field has proper structure for JS functionality.
		$this->assertStringContainsString( 'cassette-cmf-upload-button', $html );
		$this->assertStringContainsString( 'data-field-id="cassette-cmf-field-test_upload_script"', $html );
		$this->assertStringContainsString( 'cassette-cmf-upload-remove', $html );
		$this->assertStringContainsString( 'cassette-cmf-upload-value', $html );
		$this->assertStringContainsString( 'cassette-cmf-upload-preview', $html );
		$this->assertStringContainsString( 'cassette-cmf-upload-container', $html );
	}

	/**
	 * Test Custom_HTML_Field does not use a label wrapper.
	 *
	 * Regression test for #50: this field type has no input control at
	 * all, so a settings-page title has nothing for label_for to point at.
	 */
	public function test_custom_html_field_does_not_use_label_wrapper(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_custom_html',
				'type'    => 'custom_html',
				'content' => '<p>Some content</p>',
			]
		);

		$this->assertFalse( $field->uses_label_wrapper() );
	}
}
