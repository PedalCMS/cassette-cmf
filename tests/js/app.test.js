import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

import apiFetch from '@wordpress/api-fetch';
import App from '../../src/assets/settings-app/app';
import { baseConfig, field, options } from './helpers';

const renderApp = ( fields, values, extra ) =>
	render( <App { ...baseConfig( fields, values, extra ) } /> );

const saveButton = () => screen.getByRole( 'button', { name: 'Save Changes' } );

beforeEach( () => {
	apiFetch.mockReset();
} );

describe( 'layout', () => {
	test( 'shows the title and description and a disabled save button', () => {
		renderApp( [ field( 'text', 'site_name' ) ], { site_name: 'Acme' } );

		expect(
			screen.getByRole( 'heading', { name: 'Demo settings' } )
		).toBeInTheDocument();
		expect( screen.getByText( 'Intro text.' ) ).toBeInTheDocument();
		expect( saveButton() ).toBeDisabled();
	} );

	test( 'lists classic-only fields in a notice', () => {
		renderApp(
			[ field( 'text', 'a' ) ],
			{ a: '' },
			{ classicOnly: [ 'Legacy flag' ] }
		);
		expect(
			screen.getAllByText( /only editable in classic mode/ )[ 0 ]
		).toHaveTextContent( 'Legacy flag' );
	} );

	test( 'renders tabs, groups and metaboxes', async () => {
		const user = userEvent.setup();
		renderApp(
			[
				field( 'metabox', 'box', {
					title: 'Main box',
					children: [
						field( 'tabs', 'tabs', {
							tabs: [
								{
									id: 'one',
									title: 'First',
									fields: [ field( 'text', 'a' ) ],
								},
								{
									id: 'two',
									title: 'Second',
									fields: [
										field( 'group', 'grp', {
											label: 'Grouped',
											children: [ field( 'text', 'b' ) ],
										} ),
									],
								},
							],
						} ),
					],
				} ),
			],
			{ a: '1', b: '2' }
		);

		expect( screen.getByText( 'Main box' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'A' ) ).toHaveValue( '1' );
		expect( screen.queryByLabelText( 'B' ) ).not.toBeInTheDocument();

		await user.click( screen.getByRole( 'tab', { name: 'Second' } ) );

		expect( screen.getByText( 'Grouped' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'B' ) ).toHaveValue( '2' );
	} );
} );

describe( 'field mapping', () => {
	test( 'text-like fields get the matching input type', () => {
		renderApp(
			[
				field( 'text', 't' ),
				field( 'email', 'e' ),
				field( 'url', 'u' ),
				field( 'password', 'p' ),
				field( 'number', 'n', { min: 1, max: 9, step: 2 } ),
				field( 'date', 'd' ),
			],
			{ t: 'x', e: 'a@b.co', u: 'https://x.co', n: 3, d: '2026-01-02' }
		);

		expect( screen.getByLabelText( 'T' ) ).toHaveAttribute(
			'type',
			'text'
		);
		expect( screen.getByLabelText( 'E' ) ).toHaveAttribute(
			'type',
			'email'
		);
		expect( screen.getByLabelText( 'U' ) ).toHaveAttribute( 'type', 'url' );
		expect( screen.getByLabelText( 'P' ) ).toHaveAttribute(
			'type',
			'password'
		);
		expect( screen.getByLabelText( 'N' ) ).toHaveAttribute(
			'type',
			'number'
		);
		expect( screen.getByLabelText( 'N' ) ).toHaveValue( 3 );
		expect( screen.getByLabelText( 'N' ) ).toHaveAttribute( 'max', '9' );
		expect( screen.getByLabelText( 'D' ) ).toHaveAttribute(
			'type',
			'date'
		);
		expect( screen.getByLabelText( 'D' ) ).toHaveValue( '2026-01-02' );
	} );

	test( 'textarea and wysiwyg are textareas', () => {
		renderApp( [ field( 'textarea', 'ta' ), field( 'wysiwyg', 'wy' ) ], {
			ta: 'one',
			wy: '<p>two</p>',
		} );
		expect( screen.getByLabelText( 'Ta' ).tagName ).toBe( 'TEXTAREA' );
		expect( screen.getByLabelText( 'Wy' ) ).toHaveValue( '<p>two</p>' );
	} );

	test( 'select, multiple select and radio', () => {
		renderApp(
			[
				field( 'select', 's', {
					options: options( { a: 'Alpha', b: 'Beta' } ),
				} ),
				field( 'select', 'm', {
					multiple: true,
					options: options( { a: 'Alpha', b: 'Beta' } ),
				} ),
				field( 'radio', 'r', {
					options: options( { x: 'Ex', y: 'Why' } ),
				} ),
			],
			{ s: 'b', m: [ 'a' ], r: 'y' }
		);

		expect( screen.getByLabelText( 'S' ) ).toHaveValue( 'b' );
		expect( screen.getByLabelText( 'M' ) ).toHaveAttribute( 'multiple' );
		expect( screen.getByRole( 'radio', { name: 'Why' } ) ).toBeChecked();
		expect( screen.getByRole( 'radio', { name: 'Ex' } ) ).not.toBeChecked();
	} );

	test( 'single and multi checkbox', () => {
		renderApp(
			[
				field( 'checkbox', 'one', { label: 'Enable it' } ),
				field( 'checkbox', 'many', {
					label: 'Pick',
					options: options( { a: 'A1', b: 'B1' } ),
				} ),
			],
			{ one: true, many: [ 'b' ] }
		);

		expect(
			screen.getByRole( 'checkbox', { name: 'Enable it' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'A1' } )
		).not.toBeChecked();
		expect( screen.getByRole( 'checkbox', { name: 'B1' } ) ).toBeChecked();
		expect(
			screen.getByRole( 'group', { name: 'Pick' } )
		).toBeInTheDocument();
	} );

	test( 'color has a hex input and a picker toggle', () => {
		renderApp( [ field( 'color', 'c' ) ], { c: '#112233' } );
		expect(
			screen.getByRole( 'textbox', { name: 'Hex value' } )
		).toHaveValue( '#112233' );
		expect(
			screen.getByRole( 'button', { name: /Pick color/ } )
		).toBeInTheDocument();
	} );

	test( 'custom_html renders the sanitized HTML', () => {
		renderApp(
			[
				field( 'custom_html', 'note', {
					html: '<p class="x">Hello <b>there</b></p>',
				} ),
			],
			{}
		);
		expect( screen.getByText( 'there' ).tagName ).toBe( 'B' );
	} );

	test( 'upload shows select and remove, and stores the attachment id', async () => {
		const user = userEvent.setup();
		const frame = {
			handlers: {},
			on( event, callback ) {
				this.handlers[ event ] = callback;
			},
			open() {
				this.handlers.select();
			},
			state: () => ( {
				get: () => ( {
					first: () => ( {
						toJSON: () => ( {
							id: 42,
							url: 'x.png',
							type: 'image',
							filename: 'x.png',
						} ),
					} ),
				} ),
			} ),
		};
		const model = {
			get: () => 'x.png',
			toJSON: () => ( {
				url: 'x.png',
				type: 'image',
				filename: 'x.png',
			} ),
		};
		window.wp = {
			media: Object.assign(
				jest.fn( () => frame ),
				{ attachment: () => model }
			),
		};

		apiFetch.mockResolvedValue( { values: { logo: 42 } } );
		renderApp(
			[
				field( 'upload', 'logo', {
					buttonText: 'Choose',
					removeText: 'Drop',
				} ),
			],
			{ logo: '' }
		);

		expect(
			screen.queryByRole( 'button', { name: 'Drop' } )
		).not.toBeInTheDocument();
		await user.click( screen.getByRole( 'button', { name: 'Choose' } ) );
		expect(
			screen.getByRole( 'button', { name: 'Drop' } )
		).toBeInTheDocument();
		expect( screen.getByRole( 'img' ) ).toHaveAttribute( 'src', 'x.png' );

		await user.click( saveButton() );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( { data: { values: { logo: 42 } } } )
		);
		delete window.wp;
	} );

	test( 'repeater adds, edits, moves and removes rows', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( { values: {} } );
		renderApp(
			[
				field( 'repeater', 'links', {
					buttonLabel: 'Add link',
					minRows: 0,
					maxRows: 3,
					rowLabel: 'Link {{index}}',
					subFields: [ field( 'text', 'title' ) ],
				} ),
			],
			{ links: [ { title: 'first' }, { title: 'second' } ] }
		);

		expect( screen.getByText( 'Link 1' ) ).toBeInTheDocument();
		await user.click(
			screen.getAllByRole( 'button', { name: 'Move row down' } )[ 0 ]
		);
		const titles = screen.getAllByLabelText( 'Title' );
		expect( titles.map( ( input ) => input.value ) ).toEqual( [
			'second',
			'first',
		] );

		await user.click( screen.getByRole( 'button', { name: 'Add link' } ) );
		expect( screen.getAllByLabelText( 'Title' ) ).toHaveLength( 3 );
		expect(
			screen.getByRole( 'button', { name: 'Add link' } )
		).toBeDisabled();

		await user.type( screen.getAllByLabelText( 'Title' )[ 2 ], 'third' );
		await user.click(
			screen.getAllByRole( 'button', { name: 'Remove row' } )[ 0 ]
		);
		await user.click( saveButton() );

		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			values: { links: [ { title: 'first' }, { title: 'third' } ] },
		} );
	} );
} );

describe( 'conditional display', () => {
	test( 'shows and hides a field as its controller changes', async () => {
		const user = userEvent.setup();
		renderApp(
			[
				field( 'select', 'mode', {
					options: options( { basic: 'Basic', pro: 'Pro' } ),
				} ),
				field( 'text', 'license', {
					conditional: {
						relation: 'AND',
						rules: [
							{ field: 'mode', operator: '==', value: 'pro' },
						],
					},
				} ),
			],
			{ mode: 'basic', license: '' }
		);

		expect( screen.queryByLabelText( 'License' ) ).not.toBeInTheDocument();
		await user.selectOptions( screen.getByLabelText( 'Mode' ), 'pro' );
		expect( screen.getByLabelText( 'License' ) ).toBeInTheDocument();
	} );
} );

describe( 'saving', () => {
	test( 'edit and save posts only the changed values', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( {
			values: { site_name: 'Acme 2', retries: 3 },
		} );
		renderApp(
			[
				field( 'text', 'site_name' ),
				field( 'number', 'retries' ),
				field( 'checkbox', 'on' ),
			],
			{ site_name: 'Acme', retries: 3, on: false }
		);

		await user.clear( screen.getByLabelText( 'Site name' ) );
		await user.type( screen.getByLabelText( 'Site name' ), 'Acme 2' );
		expect( saveButton() ).toBeEnabled();
		await user.click( saveButton() );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/cassette-cmf/v1/settings/demo',
			method: 'POST',
			data: { values: { site_name: 'Acme 2' } },
		} );
		expect(
			( await screen.findAllByText( 'Settings saved.' ) ).length
		).toBeGreaterThan( 0 );
		await waitFor( () => expect( saveButton() ).toBeDisabled() );
	} );

	test( 'numbers are sent as numbers and an emptied number as null', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( { values: { a: null, b: 7 } } );
		renderApp( [ field( 'number', 'a' ), field( 'number', 'b' ) ], {
			a: 5,
			b: null,
		} );

		await user.clear( screen.getByLabelText( 'A' ) );
		await user.type( screen.getByLabelText( 'B' ), '7' );
		await user.click( saveButton() );

		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			values: { a: null, b: 7 },
		} );
	} );

	test( 'a single checkbox is sent as a boolean, a multi checkbox as a list', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( { values: {} } );
		renderApp(
			[
				field( 'checkbox', 'one', { label: 'One' } ),
				field( 'checkbox', 'many', {
					options: options( { a: 'A1', b: 'B1' } ),
				} ),
			],
			{ one: false, many: [ 'a' ] }
		);

		await user.click( screen.getByRole( 'checkbox', { name: 'One' } ) );
		await user.click( screen.getByRole( 'checkbox', { name: 'B1' } ) );
		await user.click( saveButton() );

		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			values: { one: true, many: [ 'a', 'b' ] },
		} );
	} );

	test( 'a failed save shows a notice and inline field errors, and focuses the first one', async () => {
		const user = userEvent.setup();
		apiFetch.mockRejectedValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): retries, name',
			data: {
				status: 400,
				params: {
					retries: 'Retries must be at most 10.',
					name: 'Name is too short.',
				},
				details: {
					retries: {
						code: 'rest_invalid_param',
						message: 'Retries must be at most 10.',
						data: { errors: [ 'Retries must be at most 10.' ] },
					},
					name: {
						code: 'rest_invalid_param',
						message: 'Name is too short.',
						data: { errors: [ 'Name is too short.' ] },
					},
				},
			},
		} );
		renderApp( [ field( 'text', 'name' ), field( 'number', 'retries' ) ], {
			name: 'ok',
			retries: 1,
		} );

		await user.type( screen.getByLabelText( 'Retries' ), '0' );
		await user.type( screen.getByLabelText( 'Name' ), 'x' );
		await user.click( saveButton() );

		expect(
			(
				await screen.findAllByText(
					'Invalid parameter(s): retries, name'
				)
			).length
		).toBeGreaterThan( 0 );
		expect(
			screen.getAllByText( 'Retries must be at most 10.' ).length
		).toBeGreaterThan( 0 );
		expect(
			screen.getByLabelText( 'Retries' )
		).toHaveAccessibleDescription( 'Retries must be at most 10.' );
		await waitFor( () =>
			expect( screen.getByLabelText( 'Name' ) ).toHaveFocus()
		);
		// The edits survive a failed save, and the button is usable again.
		expect( screen.getByLabelText( 'Retries' ) ).toHaveValue( 10 );
		expect( saveButton() ).toBeEnabled();
	} );

	test( 'an error in another tab switches to that tab', async () => {
		const user = userEvent.setup();
		apiFetch.mockRejectedValue( {
			message: 'Invalid parameter(s): b',
			data: { status: 400, params: { b: 'B is bad.' } },
		} );
		renderApp(
			[
				field( 'tabs', 'tabs', {
					tabs: [
						{
							id: 'one',
							title: 'First',
							fields: [ field( 'text', 'a' ) ],
						},
						{
							id: 'two',
							title: 'Second',
							fields: [ field( 'text', 'b' ) ],
						},
					],
				} ),
			],
			{ a: '', b: '' }
		);

		await user.type( screen.getByLabelText( 'A' ), 'x' );
		await user.click( saveButton() );

		expect(
			( await screen.findAllByText( 'B is bad.' ) ).length
		).toBeGreaterThan( 0 );
		await waitFor( () =>
			expect( screen.getByLabelText( 'B' ) ).toHaveFocus()
		);
	} );
} );

describe( 'dirty state', () => {
	test( 'enables save and warns before unload only while there are changes', async () => {
		const user = userEvent.setup();
		renderApp( [ field( 'text', 'a' ) ], { a: 'x' } );

		const unload = () => {
			const event = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( event );
			return event.defaultPrevented;
		};

		expect( unload() ).toBe( false );
		await user.type( screen.getByLabelText( 'A' ), 'y' );
		expect( saveButton() ).toBeEnabled();
		expect( unload() ).toBe( true );

		await user.type( screen.getByLabelText( 'A' ), '{Backspace}' );
		expect( saveButton() ).toBeDisabled();
		expect( unload() ).toBe( false );
	} );
} );

describe( 'secret field', () => {
	const secret = () => field( 'secret', 'api_key', { label: 'API key' } );

	test( 'shows the mask as a placeholder and sends nothing when untouched', async () => {
		renderApp( [ secret() ], { api_key: '••••9876' } );
		expect( screen.getByPlaceholderText( '••••9876' ) ).toHaveValue( '' );
		expect( saveButton() ).toBeDisabled();
	} );

	test( 'a typed value replaces the secret', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( { values: { api_key: '••••wxyz' } } );
		renderApp( [ secret() ], { api_key: '••••9876' } );

		await user.type(
			screen.getByPlaceholderText( '••••9876' ),
			'newsecretwxyz'
		);
		await user.click( saveButton() );

		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			values: { api_key: 'newsecretwxyz' },
		} );
		expect( await screen.findByPlaceholderText( '••••wxyz' ) ).toHaveValue(
			''
		);
	} );

	test( 'Clear sends {clear: true}, and can be undone', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( { values: { api_key: '' } } );
		renderApp( [ secret() ], { api_key: '••••9876' } );

		await user.click( screen.getByRole( 'button', { name: 'Clear' } ) );
		expect( saveButton() ).toBeEnabled();
		await user.click(
			screen.getByRole( 'button', { name: 'Undo clear' } )
		);
		expect( saveButton() ).toBeDisabled();

		await user.click( screen.getByRole( 'button', { name: 'Clear' } ) );
		await user.click( saveButton() );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			values: { api_key: { clear: true } },
		} );
		await waitFor( () =>
			expect(
				screen.queryByRole( 'button', { name: 'Clear' } )
			).not.toBeInTheDocument()
		);
	} );

	test( 'an unset secret has no Clear button', () => {
		renderApp( [ secret() ], { api_key: '' } );
		expect(
			screen.queryByRole( 'button', { name: 'Clear' } )
		).not.toBeInTheDocument();
	} );
} );

describe( 'read-only fields', () => {
	test( 'are shown disabled and never sent', async () => {
		const user = userEvent.setup();
		apiFetch.mockResolvedValue( { values: {} } );
		renderApp(
			[ field( 'text', 'a', { readonly: true } ), field( 'text', 'b' ) ],
			{ a: 'ro', b: '' }
		);

		expect( screen.getByLabelText( 'A' ) ).toHaveAttribute( 'readonly' );
		await user.type( screen.getByLabelText( 'B' ), 'x' );
		await user.click( saveButton() );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			values: { b: 'x' },
		} );
	} );
} );
