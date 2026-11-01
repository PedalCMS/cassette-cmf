<?php
/**
 * Conditional-display fixtures through the PHP evaluator
 *
 * tests/fixtures/conditions.json is shared with the React app's JS evaluator
 * (tests/js/conditions.test.js); both must agree on every case.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Field\Field_Factory;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Condition_Fixtures
 */
class Test_Condition_Fixtures extends CassetteCMF_UnitTestCase {

	/**
	 * Every fixture case.
	 *
	 * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: bool}>
	 */
	public function fixture_cases(): array {
		$path    = dirname( __DIR__ ) . '/fixtures/conditions.json';
		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$cases = [];
		foreach ( $decoded['cases'] as $case ) {
			$cases[ $case['name'] ] = [ $case['conditional'], $case['context'], $case['expected'] ];
		}

		return $cases;
	}

	/**
	 * The PHP evaluator gives the fixture's expected result.
	 *
	 * @dataProvider fixture_cases
	 *
	 * @param mixed                $conditional Raw conditional config.
	 * @param array<string, mixed> $context     Submitted values.
	 * @param bool                 $expected    Expected visibility.
	 */
	public function test_php_evaluator_matches_fixture( $conditional, array $context, bool $expected ): void {
		$field = Field_Factory::create(
			[
				'name'        => 'subject',
				'type'        => 'text',
				'conditional' => $conditional,
			]
		);

		$this->assertSame( $expected, $field->is_condition_met( $context ) );
		$this->assertSame( $expected, $field->should_validate( $context ) );
	}

	/**
	 * The fixture file is non-trivial.
	 */
	public function test_fixture_file_has_cases(): void {
		$this->assertGreaterThan( 50, count( $this->fixture_cases() ) );
	}
}
