<?php
/**
 * Settings page fixture that covers every field type.
 *
 * @package PedalCMS\CassetteCMF\Tests\Support
 */

/**
 * Class Rest_Fixture
 */
class Rest_Fixture {

	public const PAGE_ID = 'rest-fixture';

	/**
	 * The fixture page config (settings page + fields).
	 *
	 * @param array<string, mixed> $page_overrides Page-level overrides.
	 * @return array<string, mixed>
	 */
	public static function page( array $page_overrides = [] ): array {
		return array_merge(
			[
				'id'           => self::PAGE_ID,
				'page_title'   => 'REST fixture',
				'menu_title'   => 'REST fixture',
				'capability'   => 'manage_options',
				'show_in_rest' => true,
				'fields'       => self::fields(),
			],
			$page_overrides
		);
	}

	/**
	 * Field configs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(): array {
		return [
			[
				'name'      => 'title',
				'type'      => 'text',
				'label'     => 'Title',
				'maxlength' => 40,
			],
			[
				'name'  => 'bio',
				'type'  => 'textarea',
				'label' => 'Bio',
			],
			[
				'name'  => 'contact_email',
				'type'  => 'email',
				'label' => 'Contact email',
			],
			[
				'name'  => 'site_url',
				'type'  => 'url',
				'label' => 'Site URL',
			],
			[
				'name'  => 'launch_date',
				'type'  => 'date',
				'label' => 'Launch date',
			],
			[
				'name'    => 'brand_color',
				'type'    => 'color',
				'label'   => 'Brand color',
				'default' => '#112233',
			],
			[
				'name'     => 'count',
				'type'     => 'number',
				'label'    => 'Count',
				'min'      => 1,
				'max'      => 10,
				'step'     => 1,
				'required' => true,
			],
			[
				'name'    => 'mode',
				'type'    => 'select',
				'label'   => 'Mode',
				'options' => [
					'a' => 'Alpha',
					'b' => 'Beta',
					'c' => 'Gamma',
				],
			],
			[
				'name'     => 'tags',
				'type'     => 'select',
				'label'    => 'Tags',
				'multiple' => true,
				'options'  => [
					'a' => 'Alpha',
					'b' => 'Beta',
					'c' => 'Gamma',
				],
			],
			[
				'name'     => 'level',
				'type'     => 'radio',
				'label'    => 'Level',
				'required' => true,
				'options'  => [
					'low'  => 'Low',
					'high' => 'High',
				],
			],
			[
				'name'  => 'enabled',
				'type'  => 'checkbox',
				'label' => 'Enabled',
			],
			[
				'name'    => 'features',
				'type'    => 'checkbox',
				'label'   => 'Features',
				'options' => [
					'x' => 'X',
					'y' => 'Y',
					'z' => 'Z',
				],
			],
			[
				'name'  => 'notes',
				'type'  => 'wysiwyg',
				'label' => 'Notes',
			],
			[
				'name'  => 'logo',
				'type'  => 'upload',
				'label' => 'Logo',
			],
			[
				'name'     => 'items',
				'type'     => 'repeater',
				'label'    => 'Items',
				'max_rows' => 5,
				'fields'   => [
					[
						'name'  => 'label',
						'type'  => 'text',
						'label' => 'Label',
					],
					[
						'name'  => 'qty',
						'type'  => 'number',
						'label' => 'Qty',
					],
					[
						'name'  => 'active',
						'type'  => 'checkbox',
						'label' => 'Active',
					],
				],
			],
			[
				'name'  => 'legacy_password',
				'type'  => 'password',
				'label' => 'Legacy password',
			],
			[
				'name'    => 'api_key',
				'type'    => 'secret',
				'label'   => 'API key',
				'encrypt' => true,
			],
			[
				'name'     => 'locked',
				'type'     => 'text',
				'label'    => 'Locked',
				'readonly' => true,
			],
			[
				'name'         => 'classic_only',
				'type'         => 'text',
				'label'        => 'Classic only',
				'show_in_rest' => false,
			],
			[
				'name'    => 'intro',
				'type'    => 'custom_html',
				'content' => '<p>Intro</p>',
			],
			[
				'name'   => 'advanced',
				'type'   => 'group',
				'label'  => 'Advanced',
				'fields' => [
					[
						'name'  => 'timeout',
						'type'  => 'number',
						'label' => 'Timeout',
					],
				],
			],
			[
				'name'   => 'panel',
				'type'   => 'metabox',
				'label'  => 'Panel',
				'fields' => [
					[
						'name' => 'panel_tabs',
						'type' => 'tabs',
						'tabs' => [
							[
								'id'     => 'one',
								'label'  => 'One',
								'fields' => [
									[
										'name'  => 'tabbed',
										'type'  => 'text',
										'label' => 'Tabbed',
									],
								],
							],
						],
					],
				],
			],
		];
	}
}
