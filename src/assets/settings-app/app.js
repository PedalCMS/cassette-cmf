/**
 * The settings page application.
 *
 * Props (all from the server, see React_Renderer::get_app_config()):
 *   pageId, title, description, restPath, fields (the field tree), values
 *   (current values in REST shape) and classicOnly (labels of fields that
 *   exist only in the classic renderer).
 */

import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Notice, SnackbarList } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

import { FieldTree, FormContext } from './fields';
import { leafNodes, tabPath } from './tree';
import {
	buildPayload,
	conditionContext,
	fieldErrors,
	initialFormValues,
} from './values';

const NOTICE_CONTEXT = 'cassette-cmf-settings';

export default function App( {
	pageId,
	title,
	description,
	restPath,
	fields,
	values: serverValues,
	classicOnly = [],
} ) {
	const [ initial, setInitial ] = useState( () =>
		initialFormValues( fields, serverValues )
	);
	const [ form, setForm ] = useState( initial );
	const [ errors, setErrors ] = useState( {} );
	const [ failure, setFailure ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ tabForce, setTabForce ] = useState( {} );
	const [ focusRequest, setFocusRequest ] = useState( null );
	const rootRef = useRef( null );
	const tabMemory = useRef( {} );

	const { createSuccessNotice, removeNotice } = useDispatch( noticesStore );
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices( NOTICE_CONTEXT )
				.filter( ( notice ) => 'snackbar' === notice.type ),
		[]
	);

	const payload = useMemo(
		() => buildPayload( fields, form, initial ),
		[ fields, form, initial ]
	);
	const dirty = Object.keys( payload ).length > 0;

	// Warn before leaving the page with unsaved changes.
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	// After a failed save, move focus to the first invalid field.
	useEffect( () => {
		if ( ! focusRequest || ! rootRef.current ) {
			return;
		}
		const wrapper = Array.from(
			rootRef.current.querySelectorAll( '[data-cassette-field]' )
		).find( ( item ) => item.dataset.cassetteField === focusRequest.name );
		const target =
			wrapper &&
			wrapper.querySelector( 'input, select, textarea, button' );
		if ( target ) {
			target.focus();
		}
	}, [ focusRequest ] );

	const conditions = useMemo(
		() => conditionContext( fields, form ),
		[ fields, form ]
	);

	const tabs = useMemo(
		() => ( {
			force: tabForce,
			remember: ( name, tab ) => {
				tabMemory.current[ name ] = tab;
			},
		} ),
		[ tabForce ]
	);

	const reveal = ( name ) => {
		const path = tabPath( fields, name ) || [];
		const next = {};
		path.forEach( ( step ) => {
			next[ step.tabs ] = {
				tab: step.tab,
				nonce: ( ( tabForce[ step.tabs ] || {} ).nonce || 0 ) + 1,
			};
		} );
		setTabForce( ( current ) => ( { ...current, ...next } ) );
		setFocusRequest( ( current ) => ( {
			name,
			n: current ? current.n + 1 : 1,
		} ) );
	};

	const save = () => {
		setSaving( true );
		setFailure( '' );
		setErrors( {} );

		return apiFetch( {
			path: restPath,
			method: 'POST',
			data: { values: payload },
		} )
			.then( ( response ) => {
				const next = initialFormValues( fields, response.values );
				setInitial( next );
				setForm( next );
				createSuccessNotice( __( 'Settings saved.', 'cassette-cmf' ), {
					type: 'snackbar',
					context: NOTICE_CONTEXT,
				} );
			} )
			.catch( ( error ) => {
				const found = fieldErrors( error );
				setErrors( found );
				setFailure(
					error && error.message
						? error.message
						: __(
								'The settings could not be saved.',
								'cassette-cmf'
						  )
				);

				const first = leafNodes( fields ).find(
					( node ) => found[ node.name ]
				);
				if ( first ) {
					reveal( first.name );
				}
			} )
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="cassette-cmf-settings-app" ref={ rootRef }>
			<h1 className="wp-heading-inline">{ title }</h1>
			{ description && (
				<p className="cassette-cmf-settings-app__description">
					{ description }
				</p>
			) }

			{ failure && (
				<Notice
					status="error"
					onRemove={ () => setFailure( '' ) }
					className="cassette-cmf-settings-app__notice"
				>
					{ failure }
				</Notice>
			) }

			{ classicOnly.length > 0 && (
				<Notice
					status="info"
					isDismissible={ false }
					className="cassette-cmf-settings-app__notice"
				>
					{ __(
						'Some settings are only editable in classic mode:',
						'cassette-cmf'
					) }{ ' ' }
					{ classicOnly.join( ', ' ) }
				</Notice>
			) }

			<form
				noValidate
				aria-label={ title }
				data-page-id={ pageId }
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( dirty && ! saving ) {
						save();
					}
				} }
			>
				<FormContext.Provider value={ { conditions, tabs } }>
					<FieldTree
						nodes={ fields }
						values={ form }
						errors={ errors }
						onChange={ ( name, value ) =>
							setForm( ( current ) => ( {
								...current,
								[ name ]: value,
							} ) )
						}
					/>
				</FormContext.Provider>

				<p className="submit">
					<Button
						variant="primary"
						type="submit"
						isBusy={ saving }
						disabled={ saving || ! dirty }
					>
						{ __( 'Save Changes', 'cassette-cmf' ) }
					</Button>
				</p>
			</form>

			<SnackbarList
				className="cassette-cmf-settings-app__snackbars"
				notices={ notices }
				onRemove={ ( id ) => removeNotice( id, NOTICE_CONTEXT ) }
			/>
		</div>
	);
}
