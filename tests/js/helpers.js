/**
 * Shared builders for the settings-app tests.
 */

export const field = ( type, name, extra = {} ) => ( {
	type,
	name,
	label: name.replace( /_/g, ' ' ).replace( /^./, ( c ) => c.toUpperCase() ),
	description: '',
	placeholder: '',
	required: false,
	readonly: false,
	disabled: false,
	conditional: null,
	...extra,
} );

export const options = ( map ) =>
	Object.keys( map ).map( ( value ) => ( { value, label: map[ value ] } ) );

export const baseConfig = ( fields, values = {}, extra = {} ) => ( {
	pageId: 'demo',
	title: 'Demo settings',
	description: 'Intro text.',
	restPath: '/cassette-cmf/v1/settings/demo',
	fields,
	values,
	classicOnly: [],
	...extra,
} );
