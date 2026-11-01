/**
 * Conversion between the REST value shape and the shape the form edits.
 *
 * Most fields edit their REST value as is. The exceptions:
 *  - number: edited as a string, so "1." and "" survive typing; sent as a
 *    number, or null when empty.
 *  - secret: { mask, input, clear }. The server never returns the secret, only
 *    its mask. Nothing is sent unless a new value was typed or Clear was
 *    pressed; the unchanged state means "keep".
 *  - repeater: rows are converted field by field.
 */

import { leafNodes } from './tree';

const isMultiple = ( node ) =>
	( 'select' === node.type && node.multiple ) ||
	( 'checkbox' === node.type && ( node.options || [] ).length > 0 );

/**
 * Empty value of a node.
 *
 * @param {Object} node Node.
 * @return {*} Form value.
 */
export function emptyValue( node ) {
	if ( 'secret' === node.type ) {
		return { mask: '', input: '', clear: false };
	}
	if ( 'repeater' === node.type ) {
		return [];
	}
	if ( isMultiple( node ) ) {
		return [];
	}
	if ( 'checkbox' === node.type ) {
		return false;
	}
	return '';
}

/**
 * A new repeater row: every sub-field at its default.
 *
 * @param {Object} node Repeater node.
 * @return {Object} Row.
 */
export function newRow( node ) {
	const row = {};
	leafNodes( node.subFields ).forEach( ( sub ) => {
		row[ sub.name ] = toFormValue(
			sub,
			undefined === sub.default ||
				null === sub.default ||
				'' === sub.default
				? undefined
				: sub.default
		);
	} );
	return row;
}

/**
 * REST value to form value.
 *
 * @param {Object} node Node.
 * @param {*}      rest Value from the server (undefined when absent).
 * @return {*} Form value.
 */
export function toFormValue( node, rest ) {
	if ( 'secret' === node.type ) {
		return {
			mask: 'string' === typeof rest ? rest : '',
			input: '',
			clear: false,
		};
	}

	if ( 'repeater' === node.type ) {
		const rows = Array.isArray( rest )
			? rest.map( ( row ) => rowToForm( node, row ) )
			: [];
		while ( rows.length < ( node.minRows || 0 ) ) {
			rows.push( newRow( node ) );
		}
		return rows;
	}

	if ( undefined === rest || null === rest ) {
		return 'number' === node.type ? '' : emptyValue( node );
	}

	if ( 'number' === node.type ) {
		return String( rest );
	}

	if ( 'checkbox' === node.type && ! isMultiple( node ) ) {
		return Boolean( rest );
	}

	if ( isMultiple( node ) ) {
		return Array.isArray( rest ) ? rest.map( String ) : [];
	}

	return rest;
}

function rowToForm( node, row ) {
	const out = {};
	leafNodes( node.subFields ).forEach( ( sub ) => {
		out[ sub.name ] = toFormValue( sub, row ? row[ sub.name ] : undefined );
	} );
	return out;
}

function rowToRest( node, row ) {
	const out = {};
	leafNodes( node.subFields ).forEach( ( sub ) => {
		if ( 'secret' === sub.type ) {
			return;
		}
		out[ sub.name ] = toRestValue( sub, row[ sub.name ] );
	} );
	return out;
}

/**
 * Form value to the value sent to the server.
 *
 * @param {Object} node Node.
 * @param {*}      form Form value.
 * @return {*} REST value.
 */
export function toRestValue( node, form ) {
	if ( 'number' === node.type ) {
		return '' === form || null === form || undefined === form
			? null
			: Number( form );
	}

	if ( 'repeater' === node.type ) {
		return ( form || [] ).map( ( row ) => rowToRest( node, row ) );
	}

	return form;
}

/**
 * Initial form values for a page.
 *
 * @param {Object[]} nodes  Field tree.
 * @param {Object}   values Values from the server, by field name.
 * @return {Object} Form values by field name.
 */
export function initialFormValues( nodes, values ) {
	const form = {};
	leafNodes( nodes ).forEach( ( node ) => {
		form[ node.name ] = toFormValue( node, ( values || {} )[ node.name ] );
	} );
	return form;
}

/**
 * Whether a field's form value differs from its initial one.
 *
 * @param {Object} node    Node.
 * @param {*}      current Current form value.
 * @param {*}      initial Initial form value.
 * @return {boolean} Changed.
 */
export function isChanged( node, current, initial ) {
	if ( 'secret' === node.type ) {
		return Boolean( current.clear ) || '' !== current.input;
	}
	return JSON.stringify( current ) !== JSON.stringify( initial );
}

/**
 * The write body for the changed, writable fields.
 *
 * @param {Object[]} nodes   Field tree.
 * @param {Object}   current Current form values.
 * @param {Object}   initial Initial form values.
 * @return {Object} Values to send, by field name (empty when nothing changed).
 */
export function buildPayload( nodes, current, initial ) {
	const payload = {};

	leafNodes( nodes ).forEach( ( node ) => {
		if ( node.readonly || node.disabled ) {
			return;
		}
		if ( ! isChanged( node, current[ node.name ], initial[ node.name ] ) ) {
			return;
		}

		const form = current[ node.name ];

		if ( 'secret' === node.type ) {
			payload[ node.name ] = form.clear ? { clear: true } : form.input;
			return;
		}

		payload[ node.name ] = toRestValue( node, form );
	} );

	return payload;
}

/**
 * Condition context: the current values in the shape the classic form submits
 * (a single checkbox is "1" or "0").
 *
 * @param {Object[]} nodes Field tree.
 * @param {Object}   form  Form values.
 * @return {Object} Context by field name.
 */
export function conditionContext( nodes, form ) {
	const context = {};

	leafNodes( nodes ).forEach( ( node ) => {
		const value = form[ node.name ];

		if ( 'secret' === node.type ) {
			context[ node.name ] = value.input;
		} else if ( 'checkbox' === node.type && ! isMultiple( node ) ) {
			context[ node.name ] = value ? '1' : '0';
		} else if ( 'repeater' !== node.type ) {
			context[ node.name ] = value;
		}
	} );

	return context;
}

/**
 * Pick the server's per-field messages out of a failed write.
 *
 * @param {Object} error The apiFetch error ({ code, message, data }).
 * @return {Object} Messages (array of strings) by field name.
 */
export function fieldErrors( error ) {
	const data = ( error && error.data ) || {};
	const out = {};

	Object.keys( data.errors || {} ).forEach( ( name ) => {
		out[ name ] = [].concat( data.errors[ name ] ).map( String );
	} );

	Object.keys( data.details || {} ).forEach( ( name ) => {
		if ( out[ name ] ) {
			return;
		}
		const detail = data.details[ name ] || {};
		const messages =
			( detail.data && detail.data.errors ) ||
			( detail.message ? [ detail.message ] : [] );
		out[ name ] = [].concat( messages ).map( String );
	} );

	Object.keys( data.params || {} ).forEach( ( name ) => {
		if ( ! out[ name ] ) {
			out[ name ] = [ String( data.params[ name ] ) ];
		}
	} );

	return out;
}
