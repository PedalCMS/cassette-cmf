/**
 * Conditional display rules.
 *
 * A pure port of the server-side evaluator in
 * src/field/class-abstract-field.php (normalize_conditional_config() and
 * is_condition_met()). Both are tested against the same shared fixtures,
 * tests/fixtures/conditions.json, so they cannot drift apart.
 *
 * The context is a plain object of "submitted" values keyed by field name:
 * strings, numbers, booleans, null, or arrays of those.
 */

const ALIASES = {
	'=': '==',
	eq: '==',
	equals: '==',
	'===': '==',
	neq: '!=',
	not: '!=',
	'!==': '!=',
	includes: 'in',
	contains: 'in',
	excludes: 'not_in',
	between: 'in',
	is_empty: 'empty',
	empty: 'empty',
	not_empty: 'not_empty',
	filled: 'not_empty',
};

const VALID_OPERATORS = [
	'==',
	'!=',
	'>',
	'>=',
	'<',
	'<=',
	'in',
	'not_in',
	'empty',
	'not_empty',
];

const NUMERIC = /^\s*[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?\s*$/;

const isPlainObject = ( value ) =>
	null !== value && 'object' === typeof value && ! Array.isArray( value );

/**
 * PHP's empty() for a raw conditional config value.
 *
 * @param {*} value Raw value.
 * @return {boolean} Whether PHP would call it empty.
 */
function phpEmpty( value ) {
	if ( null === value || undefined === value || false === value ) {
		return true;
	}
	if ( 0 === value || '' === value || '0' === value ) {
		return true;
	}
	if ( Array.isArray( value ) ) {
		return 0 === value.length;
	}
	if ( isPlainObject( value ) ) {
		return 0 === Object.keys( value ).length;
	}
	return false;
}

/**
 * PHP's (string) cast for scalars.
 *
 * @param {*} value Value.
 * @return {string} String form.
 */
function toStr( value ) {
	if ( null === value || undefined === value ) {
		return '';
	}
	if ( 'boolean' === typeof value ) {
		return value ? '1' : '0';
	}
	return String( value );
}

/**
 * PHP's is_numeric().
 *
 * @param {*} value Value.
 * @return {boolean} Whether it is a number or a numeric string.
 */
function isNumeric( value ) {
	if ( 'number' === typeof value ) {
		return Number.isFinite( value );
	}
	return 'string' === typeof value && NUMERIC.test( value );
}

function normalizeOperator( operator ) {
	const lowered = String( operator ).trim().toLowerCase();
	const resolved = Object.prototype.hasOwnProperty.call( ALIASES, lowered )
		? ALIASES[ lowered ]
		: lowered;

	return VALID_OPERATORS.includes( resolved ) ? resolved : '==';
}

function normalizeRule( rule ) {
	const field =
		null !== rule.field && undefined !== rule.field
			? String( rule.field ).trim()
			: '';

	if ( '' === field ) {
		return null;
	}

	let operator = '==';
	if ( null !== rule.operator && undefined !== rule.operator ) {
		operator = rule.operator;
	} else if ( null !== rule.compare && undefined !== rule.compare ) {
		operator = rule.compare;
	}

	const normalized = { field, operator: normalizeOperator( operator ) };

	if ( Object.prototype.hasOwnProperty.call( rule, 'value' ) ) {
		normalized.value = rule.value;
	} else if ( Object.prototype.hasOwnProperty.call( rule, 'values' ) ) {
		normalized.value = rule.values;
	}

	if ( [ 'empty', 'not_empty' ].includes( normalized.operator ) ) {
		delete normalized.value;
	}

	return normalized;
}

/**
 * Normalize a raw `conditional` config.
 *
 * @param {*} raw Raw config: a single rule, or { relation, rules }.
 * @return {{relation: string, rules: Object[]}|null} Normalized config, or null when there is none.
 */
export function normalizeConditional( raw ) {
	if ( phpEmpty( raw ) || ! isPlainObject( raw ) ) {
		return null;
	}

	let config = raw;
	if ( null !== raw.field && undefined !== raw.field ) {
		config = { relation: 'AND', rules: [ raw ] };
	}

	let relation = toStr( config.relation ?? 'AND' ).toUpperCase();
	if ( ! [ 'AND', 'OR' ].includes( relation ) ) {
		relation = 'AND';
	}

	let rawRules = config.rules ?? [];
	if ( isPlainObject( rawRules ) ) {
		rawRules = Object.values( rawRules );
	}

	const rules = [];
	if ( Array.isArray( rawRules ) ) {
		rawRules.forEach( ( rule ) => {
			if ( ! isPlainObject( rule ) ) {
				return;
			}
			const normalized = normalizeRule( rule );
			if ( normalized ) {
				rules.push( normalized );
			}
		} );
	}

	return rules.length ? { relation, rules } : null;
}

function matchesFieldName( submitted, fieldName ) {
	return (
		submitted === fieldName ||
		submitted === fieldName + '[]' ||
		submitted.endsWith( '_' + fieldName ) ||
		submitted.endsWith( '_' + fieldName + '[]' ) ||
		submitted.endsWith( '[' + fieldName + ']' ) ||
		submitted.endsWith( '[' + fieldName + '][]' )
	);
}

function contextValue( fieldName, context ) {
	if ( Object.prototype.hasOwnProperty.call( context, fieldName ) ) {
		return context[ fieldName ];
	}

	const key = Object.keys( context ).find( ( name ) =>
		matchesFieldName( name, fieldName )
	);

	return undefined === key ? null : context[ key ];
}

function isEmptyValue( value ) {
	if ( Array.isArray( value ) ) {
		return 0 === value.filter( ( item ) => ! isEmptyValue( item ) ).length;
	}
	return null === value || undefined === value || '' === value;
}

function valuesMatch( actual, expected ) {
	if ( Array.isArray( actual ) ) {
		return actual.some( ( item ) => valuesMatch( item, expected ) );
	}
	return toStr( actual ) === toStr( expected );
}

function inclusion( actual, expected ) {
	const expectedValues = Array.isArray( expected ) ? expected : [ expected ];

	if ( Array.isArray( actual ) ) {
		return actual.some( ( item ) => inclusion( item, expectedValues ) );
	}

	return expectedValues.some( ( item ) => valuesMatch( actual, item ) );
}

function numeric( actual, expected, operator ) {
	const value = Array.isArray( actual ) ? actual[ 0 ] : actual;

	if ( ! isNumeric( value ) || ! isNumeric( expected ) ) {
		return false;
	}

	const left = Number( value );
	const right = Number( expected );

	switch ( operator ) {
		case '>':
			return left > right;
		case '>=':
			return left >= right;
		case '<':
			return left < right;
		case '<=':
			return left <= right;
	}
	return false;
}

function evaluateRule( rule, actual ) {
	const expected = Object.prototype.hasOwnProperty.call( rule, 'value' )
		? rule.value
		: null;

	switch ( rule.operator ) {
		case 'empty':
			return isEmptyValue( actual );
		case 'not_empty':
			return ! isEmptyValue( actual );
		case 'in':
			return inclusion( actual, expected );
		case 'not_in':
			return ! inclusion( actual, expected );
		case '>':
		case '>=':
		case '<':
		case '<=':
			return numeric( actual, expected, rule.operator );
		case '!=':
			return ! valuesMatch( actual, expected );
		default:
			return valuesMatch( actual, expected );
	}
}

/**
 * Whether a field's conditional rules evaluate to visible.
 *
 * @param {*}      conditional Raw or normalized `conditional` config.
 * @param {Object} context     Values keyed by field name.
 * @return {boolean} True when the field is shown (also when it has no rules).
 */
export function isConditionMet( conditional, context ) {
	const config = normalizeConditional( conditional );

	if ( ! config ) {
		return true;
	}

	const results = config.rules.map( ( rule ) =>
		evaluateRule( rule, contextValue( rule.field, context || {} ) )
	);

	return 'OR' === config.relation
		? results.includes( true )
		: ! results.includes( false );
}
