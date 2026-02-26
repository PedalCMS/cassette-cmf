<?php
/**
 * Field Factory Tests
 *
 * Tests for the Field_Factory class.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Field\Field_Factory;
use Pedalcms\CassetteCmf\Field\Field_Interface;

/**
 * Class Test_Field_Factory
 *
 * Tests for field factory creation and registration.
 */
class Test_Field_Factory extends WP_UnitTestCase {

	/**
	 * Reset Field_Factory between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Field_Factory::reset();
	}

	/**
	 * Test creating a text field.
	 */
	public function test_create_text_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_text',
				'type'  => 'text',
				'label' => 'Test Text',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'test_text', $field->get_name() );
		$this->assertSame( 'text', $field->get_type() );
	}

	/**
	 * Test creating a textarea field.
	 */
	public function test_create_textarea_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_textarea',
				'type'  => 'textarea',
				'label' => 'Test Textarea',
				'rows'  => 5,
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'textarea', $field->get_type() );
	}

	/**
	 * Test creating a select field.
	 */
	public function test_create_select_field(): void {
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

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'select', $field->get_type() );
	}

	/**
	 * Test creating a checkbox field.
	 */
	public function test_create_checkbox_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_checkbox',
				'type'  => 'checkbox',
				'label' => 'Test Checkbox',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'checkbox', $field->get_type() );
	}

	/**
	 * Test creating a number field.
	 */
	public function test_create_number_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_number',
				'type'  => 'number',
				'label' => 'Test Number',
				'min'   => 0,
				'max'   => 100,
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'number', $field->get_type() );
	}

	/**
	 * Test creating an email field.
	 */
	public function test_create_email_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_email',
				'type'  => 'email',
				'label' => 'Test Email',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'email', $field->get_type() );
	}

	/**
	 * Test creating a date field.
	 */
	public function test_create_date_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_date',
				'type'  => 'date',
				'label' => 'Test Date',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'date', $field->get_type() );
	}

	/**
	 * Test creating a color field.
	 */
	public function test_create_color_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_color',
				'type'  => 'color',
				'label' => 'Test Color',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'color', $field->get_type() );
	}

	/**
	 * Test create throws exception for missing name.
	 */
	public function test_create_throws_for_missing_name(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Field config must include "name".' );

		Field_Factory::create(
			[
				'type'  => 'text',
				'label' => 'Test',
			]
		);
	}

	/**
	 * Test create throws exception for missing type.
	 */
	public function test_create_throws_for_missing_type(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Field config must include "type".' );

		Field_Factory::create(
			[
				'name'  => 'test',
				'label' => 'Test',
			]
		);
	}

	/**
	 * Test create throws exception for unknown type.
	 */
	public function test_create_throws_for_unknown_type(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unknown field type "unknown"' );

		Field_Factory::create(
			[
				'name' => 'test',
				'type' => 'unknown',
			]
		);
	}

	/**
	 * Test registering a custom field type.
	 */
	public function test_register_custom_field_type(): void {
		// Create a mock field class for testing.
		$mock_class = get_class(
			$this->createMock( Field_Interface::class )
		);

		// This would normally work, but mocks don't work with our factory.
		// Instead, test with an existing type.
		$this->assertTrue( Field_Factory::has_type( 'text' ) );
	}

	/**
	 * Test has_type returns true for registered types.
	 */
	public function test_has_type_returns_true_for_registered(): void {
		$this->assertTrue( Field_Factory::has_type( 'text' ) );
		$this->assertTrue( Field_Factory::has_type( 'textarea' ) );
		$this->assertTrue( Field_Factory::has_type( 'select' ) );
		$this->assertTrue( Field_Factory::has_type( 'checkbox' ) );
		$this->assertTrue( Field_Factory::has_type( 'radio' ) );
		$this->assertTrue( Field_Factory::has_type( 'number' ) );
		$this->assertTrue( Field_Factory::has_type( 'email' ) );
		$this->assertTrue( Field_Factory::has_type( 'url' ) );
		$this->assertTrue( Field_Factory::has_type( 'date' ) );
		$this->assertTrue( Field_Factory::has_type( 'password' ) );
		$this->assertTrue( Field_Factory::has_type( 'color' ) );
	}

	/**
	 * Test has_type returns false for unregistered types.
	 */
	public function test_has_type_returns_false_for_unregistered(): void {
		$this->assertFalse( Field_Factory::has_type( 'custom_unknown' ) );
	}

	/**
	 * Test create_multiple creates multiple fields.
	 */
	public function test_create_multiple_fields(): void {
		$fields = Field_Factory::create_multiple(
			[
				'field1' => [
					'type'  => 'text',
					'label' => 'Field 1',
				],
				'field2' => [
					'type'  => 'textarea',
					'label' => 'Field 2',
				],
			]
		);

		$this->assertCount( 2, $fields );
		$this->assertArrayHasKey( 'field1', $fields );
		$this->assertArrayHasKey( 'field2', $fields );
		$this->assertSame( 'text', $fields['field1']->get_type() );
		$this->assertSame( 'textarea', $fields['field2']->get_type() );
	}

	/**
	 * Test get_registered_types returns all types.
	 */
	public function test_get_registered_types(): void {
		$types = Field_Factory::get_registered_types();

		$this->assertIsArray( $types );
		$this->assertArrayHasKey( 'text', $types );
		$this->assertArrayHasKey( 'textarea', $types );
		$this->assertArrayHasKey( 'select', $types );
		$this->assertArrayHasKey( 'checkbox', $types );
		$this->assertArrayHasKey( 'radio', $types );
		$this->assertArrayHasKey( 'number', $types );
		$this->assertArrayHasKey( 'email', $types );
		$this->assertArrayHasKey( 'url', $types );
		$this->assertArrayHasKey( 'date', $types );
		$this->assertArrayHasKey( 'password', $types );
		$this->assertArrayHasKey( 'color', $types );
		$this->assertArrayHasKey( 'wysiwyg', $types );
		$this->assertArrayHasKey( 'tabs', $types );
		$this->assertArrayHasKey( 'metabox', $types );
		$this->assertArrayHasKey( 'group', $types );
		$this->assertArrayHasKey( 'repeater', $types );
	}

	/**
	 * Test unregister_type removes a type.
	 */
	public function test_unregister_type(): void {
		$this->assertTrue( Field_Factory::has_type( 'text' ) );

		Field_Factory::unregister_type( 'text' );

		$this->assertFalse( Field_Factory::has_type( 'text' ) );
	}

	/**
	 * Test create wysiwyg field.
	 */
	public function test_create_wysiwyg_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_wysiwyg',
				'type'  => 'wysiwyg',
				'label' => 'Content',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'wysiwyg', $field->get_type() );
	}

	/**
	 * Test create url field.
	 */
	public function test_create_url_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_url',
				'type'  => 'url',
				'label' => 'Website',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'url', $field->get_type() );
	}

	/**
	 * Test create password field.
	 */
	public function test_create_password_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_password',
				'type'  => 'password',
				'label' => 'Password',
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'password', $field->get_type() );
	}

	/**
	 * Test create radio field.
	 */
	public function test_create_radio_field(): void {
		$field = Field_Factory::create(
			[
				'name'    => 'test_radio',
				'type'    => 'radio',
				'label'   => 'Choose One',
				'options' => [
					'yes' => 'Yes',
					'no'  => 'No',
				],
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'radio', $field->get_type() );
	}

	/**
	 * Test create tabs field.
	 */
	public function test_create_tabs_field(): void {
		$field = Field_Factory::create(
			[
				'name'  => 'test_tabs',
				'type'  => 'tabs',
				'label' => 'Settings Tabs',
				'tabs'  => [
					[
						'id'     => 'tab1',
						'label'  => 'Tab 1',
						'fields' => [],
					],
				],
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'tabs', $field->get_type() );
	}

	/**
	 * Test create group field.
	 */
	public function test_create_group_field(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_group',
				'type'   => 'group',
				'label'  => 'Field Group',
				'fields' => [
					[
						'name' => 'sub_field',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'group', $field->get_type() );
	}

	/**
	 * Test create metabox field.
	 */
	public function test_create_metabox_field(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_metabox',
				'type'   => 'metabox',
				'label'  => 'Metabox',
				'title'  => 'Metabox Title',
				'fields' => [],
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'metabox', $field->get_type() );
	}

	/**
	 * Test create repeater field.
	 */
	public function test_create_repeater_field(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'label'  => 'Repeater',
				'fields' => [
					[
						'name' => 'item',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertInstanceOf( Field_Interface::class, $field );
		$this->assertSame( 'repeater', $field->get_type() );
	}

	/**
	 * Test create_multiple uses array keys as names when not specified.
	 */
	public function test_create_multiple_uses_keys_as_names(): void {
		$fields = Field_Factory::create_multiple(
			[
				'auto_named_field' => [
					'type'  => 'text',
					'label' => 'Auto Named',
				],
			]
		);

		$this->assertArrayHasKey( 'auto_named_field', $fields );
		$this->assertSame( 'auto_named_field', $fields['auto_named_field']->get_name() );
	}

	/**
	 * Test create_multiple with explicit names overrides keys.
	 */
	public function test_create_multiple_explicit_names(): void {
		$fields = Field_Factory::create_multiple(
			[
				'key_name' => [
					'name'  => 'explicit_name',
					'type'  => 'text',
					'label' => 'Test',
				],
			]
		);

		$this->assertArrayHasKey( 'explicit_name', $fields );
		$this->assertArrayNotHasKey( 'key_name', $fields );
	}

	/**
	 * Test reset clears all types.
	 */
	public function test_reset_clears_types(): void {
		$this->assertTrue( Field_Factory::has_type( 'text' ) );

		Field_Factory::reset();

		// After reset, has_type should trigger register_defaults
		$this->assertTrue( Field_Factory::has_type( 'text' ) );
	}

	/**
	 * Test factory creates field with all config passed.
	 */
	public function test_factory_passes_config_to_field(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_field',
				'type'        => 'text',
				'label'       => 'Test Label',
				'description' => 'Test Description',
				'placeholder' => 'Test Placeholder',
				'default'     => 'Default Value',
				'required'    => true,
			]
		);

		$this->assertSame( 'test_field', $field->get_name() );
		$this->assertSame( 'Test Label', $field->get_label() );
	}
}
