<?php
/**
 * The sanitizer table and the storage naming rule of docs/STABILITY.md, as
 * executable cases. A change that breaks one of these needs a major version.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Field\Field_Factory;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Stability_Contract
 */
class Test_Stability_Contract extends CassetteCMF_UnitTestCase {

	/**
	 * Sanitizer output per field type.
	 *
	 * @dataProvider provide_sanitizer_outputs
	 *
	 * @param array<string, mixed> $config   Field config (name is "f").
	 * @param mixed                $input    Raw input.
	 * @param mixed                $expected Expected sanitize() result.
	 */
	public function test_sanitizer_output( array $config, $input, $expected ): void {
		$field = Field_Factory::create( array_merge( [ 'name' => 'f' ], $config ) );

		$this->assertSame( $expected, $field->sanitize( $input ) );
		// Every built-in sanitizer is idempotent.
		$this->assertSame( $expected, $field->sanitize( $expected ) );
	}

	/**
	 * Cases.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: mixed}>
	 */
	public function provide_sanitizer_outputs(): array {
		$options = [
			'a' => 'A',
			'b' => 'B',
		];

		return [
			'text strips tags'           => [ [ 'type' => 'text' ], ' <b>x</b>  y ', 'x y' ],
			'text keeps non-strings'     => [ [ 'type' => 'text' ], 5, 5 ],
			'date is text'               => [ [ 'type' => 'date' ], '<i>2026-01-02</i>', '2026-01-02' ],
			'textarea keeps newlines'    => [ [ 'type' => 'textarea' ], "<b>x</b>\ny", "x\ny" ],
			'textarea non-string'        => [ [ 'type' => 'textarea' ], 5, '' ],
			'select in options'          => [
				[
					'type'    => 'select',
					'options' => $options,
				],
				'a',
				'a',
			],
			'select outside options'     => [
				[
					'type'    => 'select',
					'options' => $options,
				],
				'zz',
				'',
			],
			'radio outside options'      => [
				[
					'type'    => 'radio',
					'options' => $options,
				],
				'zz',
				'',
			],
			'checkbox on'                => [ [ 'type' => 'checkbox' ], 'on', '1' ],
			'checkbox off'               => [ [ 'type' => 'checkbox' ], '', '0' ],
			'checkbox group filters'     => [
				[
					'type'    => 'checkbox',
					'options' => $options,
				],
				[ 'a', 'zz' ],
				[ 'a' ],
			],
			'checkbox group non-array'   => [
				[
					'type'    => 'checkbox',
					'options' => $options,
				],
				'a',
				[],
			],
			'number int'                 => [ [ 'type' => 'number' ], '7', 7 ],
			'number float'               => [ [ 'type' => 'number' ], '7.5', 7.5 ],
			'number empty'               => [ [ 'type' => 'number' ], '', '' ],
			'number zero'                => [ [ 'type' => 'number' ], '0', 0 ],
			'email'                      => [ [ 'type' => 'email' ], ' A@B.com ', 'A@B.com' ],
			'email invalid'              => [ [ 'type' => 'email' ], 'nope', '' ],
			'url'                        => [ [ 'type' => 'url' ], 'http://x.com/a b', 'http://x.com/a%20b' ],
			'url bad protocol'           => [ [ 'type' => 'url' ], 'javascript:alert(1)', '' ],
			'color short'                => [ [ 'type' => 'color' ], '#abc', '#abc' ],
			'color adds hash'            => [ [ 'type' => 'color' ], 'ABCDEF', '#ABCDEF' ],
			'color invalid'              => [ [ 'type' => 'color' ], 'red', '#000000' ],
			'color invalid with default' => [
				[
					'type'    => 'color',
					'default' => '#111111',
				],
				'red',
				'#111111',
			],
			'password trims'             => [ [ 'type' => 'password' ], '  p<b>w  ', 'p<b>w' ],
			'wysiwyg kses'               => [ [ 'type' => 'wysiwyg' ], '<b onclick=1>x</b><script>y</script>', '<b>x</b>y' ],
			'upload id'                  => [ [ 'type' => 'upload' ], '12', 12 ],
			'upload empty'               => [ [ 'type' => 'upload' ], '', '' ],
			'upload junk'                => [ [ 'type' => 'upload' ], 'junk', '' ],
			'group stores nothing'       => [ [ 'type' => 'group' ], 'x', [] ],
			'custom_html stores nothing' => [ [ 'type' => 'custom_html' ], 'x', null ],
		];
	}

	/**
	 * Repeater rows are sanitized by their sub-field types.
	 */
	public function test_repeater_rows(): void {
		$field = Field_Factory::create(
			[
				'name'   => 'rows',
				'type'   => 'repeater',
				'fields' => [
					[
						'name' => 'label',
						'type' => 'text',
					],
				],
			]
		);

		$this->assertSame(
			[ [ 'label' => 'Hi' ] ],
			$field->sanitize( [ [ 'label' => '<b>Hi</b>' ], 'not a row' ] )
		);
		$this->assertSame( [], $field->sanitize( 'x' ) );
	}

	/**
	 * The option naming rule.
	 */
	public function test_option_naming_rule(): void {
		$prefixed = Field_Factory::create(
			[
				'name' => 'tagline',
				'type' => 'text',
			]
		);
		$bare     = Field_Factory::create(
			[
				'name'            => 'tagline',
				'type'            => 'text',
				'use_name_prefix' => false,
			]
		);

		$this->assertSame( 'my-page_tagline', $prefixed->get_option_name( 'my-page' ) );
		$this->assertSame( 'tagline', $prefixed->get_option_name( '' ) );
		$this->assertSame( 'tagline', $bare->get_option_name( 'my-page' ) );
	}

	/**
	 * The frozen API surface carries @api tags.
	 */
	public function test_frozen_surface_is_tagged(): void {
		$checks = [
			[ \PedalCMS\CassetteCMF\CassetteCMF::class, [ 'init', 'boot', 'register_from_array', 'register_from_json', 'get_field', 'get_post_field', 'get_term_field', 'get_settings_field', 'get_secret', 'get_registered_field', 'is_field_public', 'register_field_type' ] ],
			[ \PedalCMS\CassetteCMF\Field\Field_Factory::class, [ 'register_type', 'create', 'create_multiple', 'get_registered_types', 'has_type' ] ],
		];

		foreach ( $checks as list( $class, $methods ) ) {
			foreach ( $methods as $method ) {
				$doc = ( new \ReflectionMethod( $class, $method ) )->getDocComment();
				$this->assertStringContainsString( '@api', (string) $doc, $class . '::' . $method );
			}
		}
	}
}
