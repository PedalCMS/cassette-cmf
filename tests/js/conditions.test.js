import fixtures from '../fixtures/conditions.json';
import {
	isConditionMet,
	normalizeConditional,
} from '../../src/assets/settings-app/conditions';

describe( 'conditions (shared fixtures with the PHP evaluator)', () => {
	test.each( fixtures.cases.map( ( item ) => [ item.name, item ] ) )(
		'%s',
		( name, item ) => {
			expect( isConditionMet( item.conditional, item.context ) ).toBe(
				item.expected
			);
		}
	);

	test( 'normalizes the single-rule shorthand and aliases', () => {
		expect(
			normalizeConditional( {
				field: ' a ',
				compare: 'eq',
				values: [ 1 ],
			} )
		).toEqual( {
			relation: 'AND',
			rules: [ { field: 'a', operator: '==', value: [ 1 ] } ],
		} );
	} );

	test( 'returns null when there is nothing to evaluate', () => {
		expect( normalizeConditional( [] ) ).toBeNull();
		expect( normalizeConditional( { rules: [] } ) ).toBeNull();
	} );
} );
