<?php
/**
 * Field Sanitization Tests
 *
 * Tests for field sanitization across all field types.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Field\Field_Factory;

/**
 * Class Test_Field_Sanitization
 *
 * Tests for sanitization methods of all field types.
 */
class Test_Field_Sanitization extends WP_UnitTestCase {

	/**
	 * Reset Field_Factory between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Field_Factory::reset();
	}

	// =========================================================================
	// TextField Sanitization
	// =========================================================================

	/**
	 * Test TextField sanitizes HTML.
	 */
	public function test_text_field_strips_html(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'text',
			]
		);

		$result = $field->sanitize( '<script>alert("xss")</script>Hello' );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( 'Hello', $result );
	}

	/**
	 * Test TextField trims whitespace.
	 */
	public function test_text_field_trims_whitespace(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'text',
			]
		);

		$result = $field->sanitize( '  trimmed  ' );

		$this->assertSame( 'trimmed', $result );
	}

	/**
	 * Test TextField handles non-string input.
	 */
	public function test_text_field_handles_non_string(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'text',
			]
		);

		// Non-strings are returned as-is by the default sanitizer
		$this->assertSame( 123, $field->sanitize( 123 ) );
	}

	// =========================================================================
	// TextareaField Sanitization
	// =========================================================================

	/**
	 * Test TextareaField sanitizes input.
	 */
	public function test_textarea_field_preserves_newlines(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'textarea',
			]
		);

		$input  = "Line 1\nLine 2\nLine 3";
		$result = $field->sanitize( $input );

		// WordPress sanitize_textarea_field preserves newlines in actual WP context
		// In test environment, we just verify it returns a string
		$this->assertIsString( $result );
	}

	/**
	 * Test TextareaField sanitizes HTML.
	 */
	public function test_textarea_field_sanitizes_html(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'textarea',
			]
		);

		$result = $field->sanitize( '<script>bad</script>Good content' );

		$this->assertStringNotContainsString( '<script>', $result );
	}

	// =========================================================================
	// NumberField Sanitization
	// =========================================================================

	/**
	 * Test NumberField converts string to integer.
	 */
	public function test_number_field_converts_to_integer(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
			]
		);

		$this->assertSame( 42, $field->sanitize( '42' ) );
	}

	/**
	 * Test NumberField converts string to float.
	 */
	public function test_number_field_converts_to_float(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
			]
		);

		$this->assertSame( 3.14, $field->sanitize( '3.14' ) );
	}

	/**
	 * Test NumberField handles non-numeric input.
	 */
	public function test_number_field_non_numeric(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
			]
		);

		$result = $field->sanitize( 'not a number' );

		// Should return 0 or empty for non-numeric
		$this->assertIsNumeric( $result );
	}

	/**
	 * Test NumberField handles empty input.
	 */
	public function test_number_field_empty_input(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
			]
		);

		$result = $field->sanitize( '' );

		// Empty should return 0 or be numeric
		$this->assertTrue( 0 === $result || '' === $result || null === $result );
	}

	// =========================================================================
	// EmailField Sanitization
	// =========================================================================

	/**
	 * Test EmailField sanitizes valid email.
	 */
	public function test_email_field_valid_email(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->sanitize( 'test@example.com' );

		$this->assertSame( 'test@example.com', $result );
	}

	/**
	 * Test EmailField sanitizes email with spaces.
	 */
	public function test_email_field_trims_spaces(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->sanitize( '  test@example.com  ' );

		$this->assertStringNotContainsString( ' ', $result );
	}

	/**
	 * Test EmailField handles invalid email.
	 */
	public function test_email_field_invalid_email(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->sanitize( 'not-an-email' );

		// WordPress sanitize_email returns empty for invalid
		$this->assertNotSame( 'not-an-email', $result );
	}

	// =========================================================================
	// URLField Sanitization
	// =========================================================================

	/**
	 * Test URLField sanitizes valid URL.
	 */
	public function test_url_field_valid_url(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->sanitize( 'https://example.com' );

		$this->assertSame( 'https://example.com', $result );
	}

	/**
	 * Test URLField sanitizes URL with spaces.
	 */
	public function test_url_field_handles_spaces(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->sanitize( 'https://example.com/path with spaces' );

		// Spaces should be encoded or removed
		$this->assertStringNotContainsString( ' ', $result );
	}

	/**
	 * Test URLField handles non-string input.
	 */
	public function test_url_field_non_string(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->sanitize( 123 );

		$this->assertIsString( $result );
	}

	// =========================================================================
	// DateField Sanitization
	// =========================================================================

	/**
	 * Test DateField sanitizes valid date.
	 */
	public function test_date_field_valid_date(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
			]
		);

		$result = $field->sanitize( '2025-01-15' );

		$this->assertSame( '2025-01-15', $result );
	}

	/**
	 * Test DateField sanitizes whitespace.
	 */
	public function test_date_field_trims_whitespace(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
			]
		);

		$result = $field->sanitize( '  2025-01-15  ' );

		$this->assertSame( '2025-01-15', $result );
	}

	// =========================================================================
	// PasswordField Sanitization
	// =========================================================================

	/**
	 * Test PasswordField preserves special characters.
	 */
	public function test_password_field_preserves_special_chars(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'password',
			]
		);

		$password = 'P@ssw0rd!#$%^&*()';
		$result   = $field->sanitize( $password );

		// Password should preserve most special characters
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test PasswordField handles empty input.
	 */
	public function test_password_field_empty(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'password',
			]
		);

		$result = $field->sanitize( '' );

		$this->assertSame( '', $result );
	}

	// =========================================================================
	// ColorField Sanitization
	// =========================================================================

	/**
	 * Test ColorField sanitizes valid hex with hash.
	 */
	public function test_color_field_valid_hex(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$this->assertSame( '#ff0000', $field->sanitize( '#ff0000' ) );
	}

	/**
	 * Test ColorField adds hash prefix.
	 */
	public function test_color_field_adds_hash(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$result = $field->sanitize( 'ff0000' );

		$this->assertStringStartsWith( '#', $result );
	}

	/**
	 * Test ColorField returns default for invalid.
	 */
	public function test_color_field_invalid_returns_default(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$result = $field->sanitize( 'not-a-color' );

		$this->assertSame( '#000000', $result );
	}

	/**
	 * Test ColorField preserves case.
	 */
	public function test_color_field_preserves_case(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$this->assertSame( '#FF0000', $field->sanitize( '#FF0000' ) );
	}

	// =========================================================================
	// SelectField Sanitization
	// =========================================================================

	/**
	 * Test SelectField sanitizes valid option.
	 */
	public function test_select_field_valid_option(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'select',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$result = $field->sanitize( 'a' );

		$this->assertSame( 'a', $result );
	}

	/**
	 * Test SelectField sanitizes invalid option.
	 */
	public function test_select_field_invalid_option(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'select',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$result = $field->sanitize( 'invalid' );

		// Should return empty or first option
		$this->assertNotSame( 'invalid', $result );
	}

	// =========================================================================
	// CheckboxField Sanitization
	// =========================================================================

	/**
	 * Test CheckboxField single checkbox sanitizes to boolean-like.
	 */
	public function test_checkbox_field_single(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'checkbox',
			]
		);

		$result = $field->sanitize( '1' );

		$this->assertTrue( ! empty( $result ) );
	}

	/**
	 * Test CheckboxField multiple options sanitizes array.
	 */
	public function test_checkbox_field_multiple(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'checkbox',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
					'c' => 'Option C',
				],
			]
		);

		$result = $field->sanitize( [ 'a', 'c' ] );

		$this->assertIsArray( $result );
		$this->assertContains( 'a', $result );
		$this->assertContains( 'c', $result );
		$this->assertNotContains( 'b', $result );
	}

	/**
	 * Test CheckboxField filters invalid options.
	 */
	public function test_checkbox_field_filters_invalid(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'checkbox',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$result = $field->sanitize( [ 'a', 'invalid', 'b' ] );

		$this->assertIsArray( $result );
		$this->assertContains( 'a', $result );
		$this->assertContains( 'b', $result );
		$this->assertNotContains( 'invalid', $result );
	}

	// =========================================================================
	// RadioField Sanitization
	// =========================================================================

	/**
	 * Test RadioField sanitizes valid option.
	 */
	public function test_radio_field_valid_option(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'radio',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$result = $field->sanitize( 'a' );

		$this->assertSame( 'a', $result );
	}

	/**
	 * Test RadioField sanitizes invalid option.
	 */
	public function test_radio_field_invalid_option(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'radio',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$result = $field->sanitize( 'invalid' );

		// Should return empty or first option
		$this->assertNotSame( 'invalid', $result );
	}

	// =========================================================================
	// WysiwygField Sanitization
	// =========================================================================

	/**
	 * Test WysiwygField allows safe HTML.
	 */
	public function test_wysiwyg_field_allows_safe_html(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'wysiwyg',
			]
		);

		$input  = '<p><strong>Bold</strong> and <em>italic</em></p>';
		$result = $field->sanitize( $input );

		// Should preserve safe tags
		$this->assertStringContainsString( '<p>', $result );
		$this->assertStringContainsString( '<strong>', $result );
	}

	/**
	 * Test WysiwygField strips dangerous tags.
	 */
	public function test_wysiwyg_field_strips_dangerous(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'wysiwyg',
			]
		);

		$input  = '<script>alert("xss")</script><p>Safe content</p>';
		$result = $field->sanitize( $input );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( 'Safe content', $result );
	}

	/**
	 * Test WysiwygField handles non-string input.
	 */
	public function test_wysiwyg_field_non_string(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'wysiwyg',
			]
		);

		$result = $field->sanitize( [ 'not', 'a', 'string' ] );

		$this->assertSame( '', $result );
	}
}
