<?php
/**
 * Field Types Tests
 *
 * Tests for core field types rendering, validation, and sanitization.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Field\Field_Factory;

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
	 * Test text field renders conditional wrapper metadata.
	 */
	public function test_text_field_renders_conditional_metadata(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'ticket_price',
				'type'        => 'text',
				'label'       => 'Ticket Price',
				'conditional' => [
					'relation' => 'AND',
					'rules'    => [
						[
							'field'    => 'is_free',
							'operator' => '==',
							'value'    => '0',
						],
					],
				],
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( 'data-conditional=', $html );
		$this->assertStringContainsString( 'cassette-cmf-field-conditional', $html );
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
	 * Test TextField renders prepend and append adornments.
	 *
	 * Regression test for #52.
	 */
	public function test_text_field_prepend_append(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_price',
				'type'    => 'text',
				'label'   => 'Price',
				'prepend' => '$',
				'append'  => 'USD',
			]
		);

		$html = $field->render( '10' );

		$this->assertStringContainsString( '<span class="cassette-cmf-input-wrapper has-adornment">', $html );
		$this->assertStringContainsString( '<span class="cassette-cmf-field-prepend">$</span>', $html );
		$this->assertStringContainsString( '<span class="cassette-cmf-field-append">USD</span>', $html );
		// Prepend must render before the input, append after.
		$this->assertMatchesRegularExpression(
			'#cassette-cmf-field-prepend">\$</span><input[^>]*><span class="cassette-cmf-field-append">USD</span>#',
			$html
		);
	}

	/**
	 * Test TextField without prepend/append renders a bare wrapper with
	 * no has-adornment modifier, so unaffected fields keep their existing
	 * layout unchanged.
	 */
	public function test_text_field_no_adornment_omits_modifier_class(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_plain',
				'type'  => 'text',
				'label' => 'Plain',
			]
		);

		$html = $field->render( 'value' );

		$this->assertStringContainsString( '<span class="cassette-cmf-input-wrapper">', $html );
		$this->assertStringNotContainsString( 'has-adornment', $html );
		$this->assertStringNotContainsString( 'cassette-cmf-field-prepend', $html );
		$this->assertStringNotContainsString( 'cassette-cmf-field-append', $html );
	}

	/**
	 * Test prepend/append strip unsafe tags but allow safe HTML.
	 */
	public function test_text_field_prepend_append_sanitizes_html(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_html',
				'type'    => 'text',
				'label'   => 'Test',
				'prepend' => '<strong>$</strong><script>alert(1)</script>',
			]
		);

		$html = $field->render( '' );

		$this->assertStringContainsString( '<strong>$</strong>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
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
	 * Test NumberField renders an append adornment (e.g. a unit).
	 *
	 * Regression test for #52.
	 */
	public function test_number_field_append(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_width',
				'type'   => 'number',
				'label'  => 'Width',
				'append' => 'px',
			]
		);

		$html = $field->render( 100 );

		$this->assertStringContainsString( '<span class="cassette-cmf-input-wrapper has-adornment">', $html );
		$this->assertStringContainsString( '<span class="cassette-cmf-field-append">px</span>', $html );
		$this->assertStringNotContainsString( 'cassette-cmf-field-prepend', $html );
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

		// Regression test for #55: input is wrapped in a span hook.
		$this->assertMatchesRegularExpression(
			'#<span class="cassette-cmf-input-wrapper"><input[^>]*type="email"[^>]*/></span>#',
			$html
		);
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

		// Regression test for #55: input is wrapped in a span hook.
		$this->assertMatchesRegularExpression(
			'#<span class="cassette-cmf-input-wrapper"><input[^>]*type="url"[^>]*/></span>#',
			$html
		);
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

	/**
	 * Test single-input field types report they use a label wrapper.
	 *
	 * Regression test for #50: these field types render exactly one
	 * control with an id matching get_field_id(), so a settings-page
	 * title can correctly be wrapped in <label for="...">.
	 */
	public function test_single_input_field_types_use_label_wrapper(): void {
		$types = [ 'text', 'textarea', 'select', 'number', 'email', 'url', 'date', 'password', 'color' ];

		foreach ( $types as $type ) {
			$field = Field_Factory::create(
				[
					'name'  => 'test_' . $type,
					'type'  => $type,
					'label' => 'Test',
				]
			);

			$this->assertTrue(
				$field->uses_label_wrapper(),
				"Expected '{$type}' field to use a label wrapper."
			);
			$this->assertSame( 'cassette-cmf-field-test_' . $type, $field->get_field_id() );
		}
	}

	/**
	 * Test grouped-control field types do not use a label wrapper.
	 *
	 * Regression test for #50: checkbox and radio fields render a group
	 * of controls (or, for a single checkbox, their own inline <label>),
	 * so there is no single control for a settings-page title's
	 * label_for to point at.
	 */
	public function test_grouped_field_types_do_not_use_label_wrapper(): void {
		foreach ( [ 'checkbox', 'radio' ] as $type ) {
			$field = Field_Factory::create(
				[
					'name'  => 'test_' . $type,
					'type'  => $type,
					'label' => 'Test',
				]
			);

			$this->assertFalse(
				$field->uses_label_wrapper(),
				"Expected '{$type}' field to NOT use a label wrapper."
			);
		}
	}
}
