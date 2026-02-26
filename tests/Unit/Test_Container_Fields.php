<?php

/**
 * Container Fields Tests
 *
 * Tests for container field types (GroupField, MetaboxField, TabsField).
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Field\Field_Factory;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;

/**
 * Class Test_Container_Fields
 *
 * Tests for container field types.
 */
class Test_Container_Fields extends WP_UnitTestCase {


	/**
	 * Reset Field_Factory between tests.
	 */
	public function set_up(): void {
		parent::set_up();
		Field_Factory::reset();
	}

	/**
	 * Test GroupField creation.
	 */
	public function test_group_field_creation(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_group',
				'type'   => 'group',
				'label'  => 'Test Group',
				'fields' => [
					[
						'name'  => 'sub_field_1',
						'type'  => 'text',
						'label' => 'Sub Field 1',
					],
					[
						'name'  => 'sub_field_2',
						'type'  => 'textarea',
						'label' => 'Sub Field 2',
					],
				],
			]
		);

		$this->assertInstanceOf( Container_Field_Interface::class, $field );
		$this->assertSame( 'group', $field->get_type() );
		$this->assertTrue( $field->is_container() );
	}

	/**
	 * Test GroupField nested fields extraction.
	 */
	public function test_group_field_nested_fields(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_group',
				'type'   => 'group',
				'label'  => 'Test Group',
				'fields' => [
					[
						'name'  => 'sub_field_1',
						'type'  => 'text',
						'label' => 'Sub Field 1',
					],
					[
						'name'  => 'sub_field_2',
						'type'  => 'textarea',
						'label' => 'Sub Field 2',
					],
				],
			]
		);

		$nested = $field->get_nested_fields();

		$this->assertCount( 2, $nested );
	}

	/**
	 * Test MetaboxField creation.
	 */
	public function test_metabox_field_creation(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_metabox',
				'type'     => 'metabox',
				'label'    => 'Test Metabox',
				'context'  => 'side',
				'priority' => 'high',
				'fields'   => [
					[
						'name'  => 'meta_field_1',
						'type'  => 'text',
						'label' => 'Meta Field 1',
					],
				],
			]
		);

		$this->assertInstanceOf( Container_Field_Interface::class, $field );
		$this->assertSame( 'metabox', $field->get_type() );
		$this->assertTrue( $field->is_container() );
	}

	/**
	 * Test MetaboxField context and priority.
	 */
	public function test_metabox_field_context_priority(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'test_metabox',
				'type'     => 'metabox',
				'label'    => 'Test Metabox',
				'context'  => 'side',
				'priority' => 'high',
				'fields'   => [],
			]
		);

		$this->assertSame( 'side', $field->get_context() );
		$this->assertSame( 'high', $field->get_priority() );
	}

	/**
	 * Test TabsField creation.
	 */
	public function test_tabs_field_creation(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_tabs',
				'type'        => 'tabs',
				'label'       => 'Test Tabs',
				'orientation' => 'horizontal',
				'tabs'        => [
					[
						'id'     => 'tab1',
						'label'  => 'Tab 1',
						'fields' => [
							[
								'name'  => 'tab1_field',
								'type'  => 'text',
								'label' => 'Tab 1 Field',
							],
						],
					],
					[
						'id'     => 'tab2',
						'label'  => 'Tab 2',
						'fields' => [
							[
								'name'  => 'tab2_field',
								'type'  => 'email',
								'label' => 'Tab 2 Field',
							],
						],
					],
				],
			]
		);

		$this->assertInstanceOf( Container_Field_Interface::class, $field );
		$this->assertSame( 'tabs', $field->get_type() );
		$this->assertTrue( $field->is_container() );
	}

	/**
	 * Test TabsField nested fields from multiple tabs.
	 */
	public function test_tabs_field_nested_fields(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_tabs',
				'type' => 'tabs',
				'tabs' => [
					[
						'id'     => 'tab1',
						'label'  => 'Tab 1',
						'fields' => [
							[
								'name' => 'field1',
								'type' => 'text',
							],
							[
								'name' => 'field2',
								'type' => 'text',
							],
						],
					],
					[
						'id'     => 'tab2',
						'label'  => 'Tab 2',
						'fields' => [
							[
								'name' => 'field3',
								'type' => 'text',
							],
						],
					],
				],
			]
		);

		$nested = $field->get_nested_fields();

		$this->assertCount( 3, $nested );
	}

	/**
	 * Test GroupField renders wrapper.
	 */
	public function test_group_field_renders(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_group',
				'type'   => 'group',
				'label'  => 'Test Group',
				'fields' => [
					[
						'name'  => 'sub_field',
						'type'  => 'text',
						'label' => 'Sub Field',
					],
				],
			]
		);

		$html = $field->render( null );

		$this->assertStringContainsString( 'cassette-cmf-group', $html );
	}

	/**
	 * Test MetaboxField renders wrapper.
	 */
	public function test_metabox_field_renders(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'test_metabox',
				'type'   => 'metabox',
				'label'  => 'Test Metabox',
				'fields' => [
					[
						'name'  => 'sub_field',
						'type'  => 'text',
						'label' => 'Sub Field',
					],
				],
			]
		);

		$html = $field->render( null );

		$this->assertStringContainsString( 'cassette-cmf-metabox', $html );
	}
}
