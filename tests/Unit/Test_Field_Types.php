<?php
/**
 * Field Types Tests
 *
 * Tests for core field types rendering, validation, and sanitization.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Field\Field_Factory;

/**
 * Class Test_Field_Types
 *
 * Tests for all core field types.
 */
class Test_Field_Types extends WP_UnitTestCase {

	/**
	 * Reset Field_Factory between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Field_Factory::reset();
	}

	/**
	 * Test TextField renders correctly.
	 */
	public function test_text_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_text',
				'type'  => 'text',
				'label' => 'Test Text',
			]
		);

		$html = $field->render( 'test value' );

		$this->assertStringContainsString( 'type="text"', $html );
		$this->assertStringContainsString( 'name="test_text"', $html );
		$this->assertStringContainsString( 'value="test value"', $html );
	}

	/**
	 * Test TextField with placeholder.
	 */
	public function test_text_field_with_placeholder(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_text',
				'type'        => 'text',
				'label'       => 'Test Text',
				'placeholder' => 'Enter text here',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'placeholder="Enter text here"', $html );
	}

	/**
	 * Test TextField sanitization strips tags.
	 */
	public function test_text_field_sanitize(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_text',
				'type'  => 'text',
				'label' => 'Test Text',
			]
		);

		// WordPress sanitize_text_field trims whitespace
		$this->assertSame( 'clean text', $field->sanitize( '  clean text  ' ) );
		// WordPress sanitize_text_field strips HTML tags
		$sanitized = $field->sanitize( '<script>no tags</script>' );
		// The content may be stripped differently depending on WP version, check it doesn't contain tags
		$this->assertStringNotContainsString( '<script>', $sanitized );
	}

	/**
	 * Test TextareaField renders correctly.
	 */
	public function test_textarea_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_textarea',
				'type'  => 'textarea',
				'label' => 'Test Textarea',
				'rows'  => 5,
			]
		);

		$html = $field->render( 'test content' );

		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'rows="5"', $html );
		$this->assertStringContainsString( 'test content', $html );
	}

	/**
	 * Test SelectField renders correctly.
	 */
	public function test_select_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_select',
				'type'    => 'select',
				'label'   => 'Test Select',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$html = $field->render( 'b' );

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'value="a"', $html );
		$this->assertStringContainsString( 'value="b" selected', $html );
	}

	/**
	 * Test SelectField validates against options.
	 */
	public function test_select_field_validate(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_select',
				'type'    => 'select',
				'label'   => 'Test Select',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$valid_result = $field->validate( 'a' );
		$this->assertTrue( $valid_result['valid'] );

		$invalid_result = $field->validate( 'invalid' );
		$this->assertFalse( $invalid_result['valid'] );
	}

	/**
	 * Test CheckboxField single checkbox.
	 */
	public function test_checkbox_field_single(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_checkbox',
				'type'  => 'checkbox',
				'label' => 'Test Checkbox',
			]
		);

		$html = $field->render( '1' );

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'checked', $html );
	}

	/**
	 * Test CheckboxField multiple options.
	 */
	public function test_checkbox_field_multiple(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_checkbox',
				'type'    => 'checkbox',
				'label'   => 'Test Checkbox',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$html = $field->render( [ 'a' ] );

		$this->assertStringContainsString( 'value="a"', $html );
		$this->assertStringContainsString( 'value="b"', $html );
	}

	/**
	 * Test RadioField renders correctly.
	 */
	public function test_radio_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_radio',
				'type'    => 'radio',
				'label'   => 'Test Radio',
				'options' => [
					'a' => 'Option A',
					'b' => 'Option B',
				],
			]
		);

		$html = $field->render( 'a' );

		$this->assertStringContainsString( 'type="radio"', $html );
		$this->assertStringContainsString( 'value="a" checked', $html );
	}

	/**
	 * Test NumberField renders correctly.
	 */
	public function test_number_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_number',
				'type'  => 'number',
				'label' => 'Test Number',
				'min'   => 0,
				'max'   => 100,
			]
		);

		$html = $field->render( 50 );

		$this->assertStringContainsString( 'type="number"', $html );
		$this->assertStringContainsString( 'min="0"', $html );
		$this->assertStringContainsString( 'max="100"', $html );
		$this->assertStringContainsString( 'value="50"', $html );
	}

	/**
	 * Test NumberField sanitizes to numeric.
	 */
	public function test_number_field_sanitize(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_number',
				'type'  => 'number',
				'label' => 'Test Number',
			]
		);

		$this->assertSame( 42, $field->sanitize( '42' ) );
		$this->assertSame( 42.5, $field->sanitize( '42.5' ) );
	}

	/**
	 * Test EmailField renders correctly.
	 */
	public function test_email_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_email',
				'type'  => 'email',
				'label' => 'Test Email',
			]
		);

		$html = $field->render( 'test@example.com' );

		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'value="test@example.com"', $html );
	}

	/**
	 * Test EmailField validates email format.
	 */
	public function test_email_field_validate(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_email',
				'type'  => 'email',
				'label' => 'Test Email',
			]
		);

		$valid_result = $field->validate( 'valid@email.com' );
		$this->assertTrue( $valid_result['valid'] );

		$invalid_result = $field->validate( 'invalid-email' );
		$this->assertFalse( $invalid_result['valid'] );
	}

	/**
	 * Test URLField renders correctly.
	 */
	public function test_url_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_url',
				'type'  => 'url',
				'label' => 'Test URL',
			]
		);

		$html = $field->render( 'https://example.com' );

		$this->assertStringContainsString( 'type="url"', $html );
		$this->assertStringContainsString( 'value="https://example.com"', $html );
	}

	/**
	 * Test DateField renders correctly.
	 */
	public function test_date_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_date',
				'type'  => 'date',
				'label' => 'Test Date',
			]
		);

		$html = $field->render( '2025-01-15' );

		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'value="2025-01-15"', $html );
	}

	/**
	 * Test DateField validates date format.
	 */
	public function test_date_field_validate(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_date',
				'type'  => 'date',
				'label' => 'Test Date',
			]
		);

		$valid_result = $field->validate( '2025-01-15' );
		$this->assertTrue( $valid_result['valid'] );

		$invalid_result = $field->validate( 'invalid-date' );
		$this->assertFalse( $invalid_result['valid'] );
	}

	/**
	 * Test PasswordField renders without value.
	 */
	public function test_password_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_password',
				'type'  => 'password',
				'label' => 'Test Password',
			]
		);

		$html = $field->render( 'secret' );

		$this->assertStringContainsString( 'type="password"', $html );
		// Password should not output value for security.
		$this->assertStringNotContainsString( 'value="secret"', $html );
	}

	/**
	 * Test ColorField renders correctly.
	 */
	public function test_color_field_render(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_color',
				'type'  => 'color',
				'label' => 'Test Color',
			]
		);

		$html = $field->render( '#ff0000' );

		$this->assertStringContainsString( 'type="color"', $html );
		$this->assertStringContainsString( 'value="#ff0000"', $html );
	}

	/**
	 * Test ColorField validates hex format.
	 */
	public function test_color_field_validate(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_color',
				'type'  => 'color',
				'label' => 'Test Color',
			]
		);

		$valid_result = $field->validate( '#ff0000' );
		$this->assertTrue( $valid_result['valid'] );

		$invalid_result = $field->validate( 'red' );
		$this->assertFalse( $invalid_result['valid'] );
	}

	/**
	 * Test ColorField sanitizes to valid hex.
	 */
	public function test_color_field_sanitize(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_color',
				'type'  => 'color',
				'label' => 'Test Color',
			]
		);

		// ColorField preserves case but ensures # prefix
		$this->assertSame( '#FF0000', $field->sanitize( '#FF0000' ) );
		$this->assertSame( '#ff0000', $field->sanitize( 'ff0000' ) );
		// Invalid color returns default
		$this->assertSame( '#000000', $field->sanitize( 'invalid' ) );
	}
}
