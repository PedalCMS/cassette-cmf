<?php
/**
 * Field Validation Tests
 *
 * Tests for field validation across all field types.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Field\Field_Factory;

/**
 * Class Test_Field_Validation
 *
 * Tests for validation methods of all field types.
 */
class Test_Field_Validation extends WP_UnitTestCase {

	/**
	 * Reset Field_Factory between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Field_Factory::reset();
	}

	// =========================================================================
	// Required Field Validation
	// =========================================================================

	/**
	 * Test required field with empty value fails.
	 */
	public function test_required_field_empty_fails(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test',
				'type'     => 'text',
				'required' => true,
			]
		);

		$result = $field->validate( '' );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test required field with value passes.
	 */
	public function test_required_field_with_value_passes(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test',
				'type'     => 'text',
				'required' => true,
			]
		);

		$result = $field->validate( 'has value' );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test optional field with empty value passes.
	 */
	public function test_optional_field_empty_passes(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test',
				'type'     => 'text',
				'required' => false,
			]
		);

		$result = $field->validate( '' );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// Min/Max Length Validation
	// =========================================================================

	/**
	 * Test min length validation fails.
	 */
	public function test_min_length_fails(): void {
		$field = Field_Factory::create(
			[
				'name'       => 'test',
				'type'       => 'text',
				'validation' => [ 'min' => 5 ],
			]
		);

		$result = $field->validate( 'abc' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test min length validation passes.
	 */
	public function test_min_length_passes(): void {
		$field = Field_Factory::create(
			[
				'name'       => 'test',
				'type'       => 'text',
				'validation' => [ 'min' => 5 ],
			]
		);

		$result = $field->validate( 'abcdef' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test max length validation fails.
	 */
	public function test_max_length_fails(): void {
		$field = Field_Factory::create(
			[
				'name'       => 'test',
				'type'       => 'text',
				'validation' => [ 'max' => 5 ],
			]
		);

		$result = $field->validate( 'abcdefgh' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test max length validation passes.
	 */
	public function test_max_length_passes(): void {
		$field = Field_Factory::create(
			[
				'name'       => 'test',
				'type'       => 'text',
				'validation' => [ 'max' => 10 ],
			]
		);

		$result = $field->validate( 'abcde' );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// Pattern Validation
	// =========================================================================

	/**
	 * Test pattern validation fails.
	 */
	public function test_pattern_validation_fails(): void {
		$field = Field_Factory::create(
			[
				'name'       => 'test',
				'type'       => 'text',
				'validation' => [ 'pattern' => '/^[0-9]+$/' ],
			]
		);

		$result = $field->validate( 'abc' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test pattern validation passes.
	 */
	public function test_pattern_validation_passes(): void {
		$field = Field_Factory::create(
			[
				'name'       => 'test',
				'type'       => 'text',
				'validation' => [ 'pattern' => '/^[0-9]+$/' ],
			]
		);

		$result = $field->validate( '12345' );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// Email Validation
	// =========================================================================

	/**
	 * Test email validation passes for valid email.
	 */
	public function test_email_valid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->validate( 'test@example.com' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test email validation fails for invalid email.
	 */
	public function test_email_invalid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->validate( 'not-an-email' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test email with subdomain passes.
	 */
	public function test_email_with_subdomain(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->validate( 'user@mail.example.com' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test email with plus addressing passes.
	 */
	public function test_email_plus_addressing(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'email',
			]
		);

		$result = $field->validate( 'user+tag@example.com' );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// URL Validation
	// =========================================================================

	/**
	 * Test URL validation passes for valid URL.
	 */
	public function test_url_valid_https(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->validate( 'https://example.com' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test URL validation passes for http.
	 */
	public function test_url_valid_http(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->validate( 'http://example.com' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test URL validation fails for invalid URL.
	 */
	public function test_url_invalid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->validate( 'not-a-url' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test URL with path passes.
	 */
	public function test_url_with_path(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'url',
			]
		);

		$result = $field->validate( 'https://example.com/path/to/page' );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// Number Validation
	// =========================================================================

	/**
	 * Test number validation passes for valid number.
	 */
	public function test_number_valid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
			]
		);

		$result = $field->validate( 42 );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test number min validation fails.
	 */
	public function test_number_min_fails(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
				'min'  => 10,
			]
		);

		$result = $field->validate( 5 );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test number max validation fails.
	 */
	public function test_number_max_fails(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
				'max'  => 10,
			]
		);

		$result = $field->validate( 15 );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test number range validation passes.
	 */
	public function test_number_range_passes(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
				'min'  => 0,
				'max'  => 100,
			]
		);

		$result = $field->validate( 50 );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test number with step validation.
	 */
	public function test_number_float(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'number',
				'step' => 0.01,
			]
		);

		$result = $field->validate( 3.14 );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// Date Validation
	// =========================================================================

	/**
	 * Test date validation passes for valid date.
	 */
	public function test_date_valid(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
			]
		);

		$result = $field->validate( '2025-01-15' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test date validation fails for invalid format.
	 */
	public function test_date_invalid_format(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
			]
		);

		$result = $field->validate( '01/15/2025' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test date validation fails for invalid date.
	 */
	public function test_date_invalid_date(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
			]
		);

		// Feb 30 doesn't exist
		$result = $field->validate( '2025-02-30' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test date min validation fails.
	 */
	public function test_date_min_fails(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
				'min'  => '2025-01-01',
			]
		);

		$result = $field->validate( '2024-12-31' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test date max validation fails.
	 */
	public function test_date_max_fails(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'date',
				'max'  => '2025-12-31',
			]
		);

		$result = $field->validate( '2026-01-01' );

		$this->assertFalse( $result['valid'] );
	}

	// =========================================================================
	// Color Validation
	// =========================================================================

	/**
	 * Test color validation passes for valid hex.
	 */
	public function test_color_valid_hex(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$result = $field->validate( '#ff0000' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test color validation passes for uppercase hex.
	 */
	public function test_color_uppercase_hex(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$result = $field->validate( '#FF0000' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test color validation fails for named color.
	 */
	public function test_color_named_fails(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$result = $field->validate( 'red' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test color validation fails for invalid hex.
	 */
	public function test_color_invalid_hex(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'color',
			]
		);

		$result = $field->validate( '#gggggg' );

		$this->assertFalse( $result['valid'] );
	}

	// =========================================================================
	// Select Validation
	// =========================================================================

	/**
	 * Test select validation passes for valid option.
	 */
	public function test_select_valid_option(): void {
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

		$result = $field->validate( 'a' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test select validation fails for invalid option.
	 */
	public function test_select_invalid_option(): void {
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

		$result = $field->validate( 'invalid' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test required select with empty fails.
	 */
	public function test_select_required_empty_fails(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test',
				'type'     => 'select',
				'required' => true,
				'options'  => [
					'a' => 'Option A',
				],
			]
		);

		$result = $field->validate( '' );

		$this->assertFalse( $result['valid'] );
	}

	// =========================================================================
	// Radio Validation
	// =========================================================================

	/**
	 * Test radio validation passes for valid option.
	 */
	public function test_radio_valid_option(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'radio',
				'options' => [
					'yes' => 'Yes',
					'no'  => 'No',
				],
			]
		);

		$result = $field->validate( 'yes' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test radio validation fails for invalid option.
	 */
	public function test_radio_invalid_option(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test',
				'type'    => 'radio',
				'options' => [
					'yes' => 'Yes',
					'no'  => 'No',
				],
			]
		);

		$result = $field->validate( 'maybe' );

		$this->assertFalse( $result['valid'] );
	}

	// =========================================================================
	// Checkbox Validation
	// =========================================================================

	/**
	 * Test checkbox with options validates valid selection.
	 */
	public function test_checkbox_valid_selection(): void {
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

		$result = $field->validate( [ 'a', 'b' ] );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test checkbox with options validates partial selection.
	 */
	public function test_checkbox_partial_selection(): void {
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

		$result = $field->validate( [ 'a' ] );

		$this->assertTrue( $result['valid'] );
	}

	// =========================================================================
	// WYSIWYG Validation
	// =========================================================================

	/**
	 * Test wysiwyg validation passes for content.
	 */
	public function test_wysiwyg_with_content_passes(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'wysiwyg',
			]
		);

		$result = $field->validate( '<p>Hello World</p>' );

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * Test wysiwyg required fails when empty.
	 */
	public function test_wysiwyg_required_empty_fails(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test',
				'type'     => 'wysiwyg',
				'required' => true,
			]
		);

		$result = $field->validate( '' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test wysiwyg min length validation.
	 */
	public function test_wysiwyg_min_length(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'wysiwyg',
				'min'  => 20,
			]
		);

		$result = $field->validate( 'Short' );

		$this->assertFalse( $result['valid'] );
	}

	/**
	 * Test wysiwyg max length validation.
	 */
	public function test_wysiwyg_max_length(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'wysiwyg',
				'max'  => 10,
			]
		);

		$result = $field->validate( 'This content is too long' );

		$this->assertFalse( $result['valid'] );
	}

	// =========================================================================
	// Validation Result Structure
	// =========================================================================

	/**
	 * Test validation result has correct structure.
	 */
	public function test_validation_result_structure(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'text',
			]
		);

		$result = $field->validate( 'value' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'valid', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertIsBool( $result['valid'] );
		$this->assertIsArray( $result['errors'] );
	}

	/**
	 * Test validation errors are descriptive.
	 */
	public function test_validation_errors_descriptive(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test',
				'type'     => 'text',
				'required' => true,
			]
		);

		$result = $field->validate( '' );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'][0] );
		$this->assertIsString( $result['errors'][0] );
	}
}
