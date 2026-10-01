<?php

/**
 * Container Fields Tests
 *
 * Tests for container field types (GroupField, MetaboxField, TabsField).
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Container_Field_Interface;

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
	 * Test TabsField renders the WAI-ARIA tabs pattern.
	 *
	 * Regression test for #73.
	 */
	public function test_tabs_field_renders_aria_markup(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_tabs',
				'type'        => 'tabs',
				'label'       => 'Settings',
				'orientation' => 'horizontal',
				'tabs'        => [
					[
						'id'     => 'general',
						'label'  => 'General',
						'fields' => [],
					],
					[
						'id'     => 'advanced',
						'label'  => 'Advanced',
						'fields' => [],
					],
				],
			]
		);

		$html = $field->render();

		// Tablist container.
		$this->assertStringContainsString( 'role="tablist"', $html );
		$this->assertStringContainsString( 'aria-label="Settings"', $html );

		// Active (first/default) tab: selected, in the tab order.
		$this->assertStringContainsString( 'id="cassette-cmf-field-test_tabs-tab-general"', $html );
		$this->assertStringContainsString( 'aria-controls="cassette-cmf-field-test_tabs-panel-general"', $html );
		$this->assertMatchesRegularExpression(
			'/data-tab="general"[^>]*role="tab"[^>]*aria-selected="true"[^>]*tabindex="0"/',
			$html
		);

		// Inactive tab: not selected, removed from the tab order.
		$this->assertMatchesRegularExpression(
			'/data-tab="advanced"[^>]*role="tab"[^>]*aria-selected="false"[^>]*tabindex="-1"/',
			$html
		);

		// Panels: role, id, and aria-labelledby linkage back to their tab.
		$this->assertMatchesRegularExpression(
			'/data-tab="general"[^>]*role="tabpanel"[^>]*id="cassette-cmf-field-test_tabs-panel-general"[^>]*aria-labelledby="cassette-cmf-field-test_tabs-tab-general"/',
			$html
		);
	}

	/**
	 * Test TabsField renders identical ARIA markup for the vertical
	 * orientation, since both layouts share the same render path.
	 *
	 * Regression test for #73.
	 */
	public function test_tabs_field_vertical_renders_aria_markup(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_tabs',
				'type'        => 'tabs',
				'orientation' => 'vertical',
				'tabs'        => [
					[
						'id'     => 'general',
						'label'  => 'General',
						'fields' => [],
					],
				],
			]
		);

		$html = $field->render();

		$this->assertStringContainsString( 'cassette-cmf-tabs-vertical', $html );
		$this->assertStringContainsString( 'role="tablist"', $html );
		$this->assertMatchesRegularExpression(
			'/data-tab="general"[^>]*role="tab"[^>]*aria-selected="true"[^>]*tabindex="0"/',
			$html
		);
	}

	/**
	 * Test an explicitly configured default_tab is honored and selected.
	 *
	 * Regression test for #73.
	 */
	public function test_tabs_field_explicit_default_tab(): void {
		$field = Field_Factory::create(
			[
				'name'        => 'test_tabs',
				'type'        => 'tabs',
				'default_tab' => 'advanced',
				'tabs'        => [
					[
						'id'     => 'general',
						'label'  => 'General',
						'fields' => [],
					],
					[
						'id'     => 'advanced',
						'label'  => 'Advanced',
						'fields' => [],
					],
				],
			]
		);

		$html = $field->render();

		$this->assertMatchesRegularExpression(
			'/data-tab="advanced"[^>]*role="tab"[^>]*aria-selected="true"[^>]*tabindex="0"/',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/data-tab="general"[^>]*role="tab"[^>]*aria-selected="false"[^>]*tabindex="-1"/',
			$html
		);
	}

	/**
	 * Test a first tab with no 'id' renders, selecting its 'tab-0' fallback.
	 *
	 * Previously default_tab resolved to null here and render() fatally errored.
	 */
	public function test_tabs_field_first_tab_without_id(): void {
		$field = Field_Factory::create(
			[
				'name' => 'test_tabs',
				'type' => 'tabs',
				'tabs' => [
					[
						'label'  => 'General',
						'fields' => [],
					],
					[
						'id'     => 'advanced',
						'label'  => 'Advanced',
						'fields' => [],
					],
				],
			]
		);

		$html = $field->render();

		$this->assertMatchesRegularExpression(
			'/data-tab="tab-0"[^>]*role="tab"[^>]*aria-selected="true"[^>]*tabindex="0"/',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/data-tab="advanced"[^>]*role="tab"[^>]*aria-selected="false"/',
			$html
		);
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
	 * Test repeater rejects conditional sub-fields.
	 */
	public function test_repeater_conditional_sub_field_is_not_supported(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Conditional fields are not supported inside repeaters.' );

		Field_Factory::create(
			[
				'name'         => 'variations',
				'type'         => 'repeater',
				'label'        => 'Variations',
				'button_label' => 'Add Variation',
				'fields'       => [
					[
						'name'    => 'variant_type',
						'type'    => 'select',
						'label'   => 'Variant Type',
						'options' => [
							'simple' => 'Simple',
							'custom' => 'Custom',
						],
					],
					[
						'name'        => 'custom_variant_label',
						'type'        => 'text',
						'label'       => 'Custom Variant Label',
						'required'    => true,
						'conditional' => [
							'rules' => [
								[
									'field'    => 'variant_type',
									'operator' => '==',
									'value'    => 'custom',
								],
							],
						],
					],
				],
			]
		);
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

	/**
	 * Test container field types do not use a label wrapper.
	 *
	 * Regression test for #50: container fields have no single focusable
	 * control of their own, so a settings-page title has nothing for
	 * label_for to point at.
	 */
	public function test_container_field_types_do_not_use_label_wrapper(): void {
		$configs = [
			'tabs'     => [
				'name'  => 'test_tabs',
				'type'  => 'tabs',
				'label' => 'Test Tabs',
				'tabs'  => [
					[
						'id'     => 'tab1',
						'label'  => 'Tab 1',
						'fields' => [],
					],
				],
			],
			'metabox'  => [
				'name'   => 'test_metabox',
				'type'   => 'metabox',
				'label'  => 'Test Metabox',
				'fields' => [],
			],
			'group'    => [
				'name'   => 'test_group',
				'type'   => 'group',
				'label'  => 'Test Group',
				'fields' => [],
			],
			'repeater' => [
				'name'   => 'test_repeater',
				'type'   => 'repeater',
				'label'  => 'Test Repeater',
				'fields' => [],
			],
		];

		foreach ( $configs as $type => $config ) {
			$field = Field_Factory::create( $config );

			$this->assertFalse(
				$field->uses_label_wrapper(),
				"Expected '{$type}' field to NOT use a label wrapper."
			);
		}
	}
}
