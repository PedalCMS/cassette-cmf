/**
 * The field tree: maps every Cassette-CMF field type to a core component.
 *
 * | Field type                      | Component                                  |
 * |---------------------------------|--------------------------------------------|
 * | text, email, url, password      | TextControl (matching type)                |
 * | number, date                    | TextControl type=number / type=date        |
 * | textarea, wysiwyg               | TextareaControl                            |
 * | select                          | SelectControl (multiple when configured)   |
 * | radio                           | RadioControl                               |
 * | checkbox                        | CheckboxControl, or a group of them        |
 * | color                           | TextControl + ColorPicker in a Dropdown    |
 * | upload                          | wp.media modal + preview                   |
 * | custom_html                     | the configured HTML, sanitized server-side |
 * | secret                          | password TextControl + Clear button        |
 * | group / metabox / tabs          | Panel / Card / TabPanel                    |
 * | repeater                        | rows of sub-fields, add/remove/move        |
 */

import { createContext, useContext, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	ColorPicker,
	Dropdown,
	Panel,
	PanelBody,
	RadioControl,
	SelectControl,
	TabPanel,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { isConditionMet } from './conditions';
import Composite from './composite';
import MediaControl from './media-control';
import { newRow } from './values';

/**
 * Shared, read-only state of the form.
 *
 * conditions: values in the shape the condition evaluator reads.
 * tabs:       { force, remember } so a failed save can reveal a hidden tab.
 */
export const FormContext = createContext( {
	conditions: {},
	tabs: { force: {}, remember: () => {} },
} );

function Help( { node, messages } ) {
	if ( ! node.description && ! ( messages && messages.length ) ) {
		return undefined;
	}
	return (
		<>
			{ node.description && (
				<span className="cassette-cmf-field__description">
					{ node.description }
				</span>
			) }
			{ ( messages || [] ).map( ( message, index ) => (
				<span key={ index } className="cassette-cmf-field__error">
					{ message }
				</span>
			) ) }
		</>
	);
}

const helpOf = ( node, messages ) =>
	node.description || ( messages && messages.length ) ? (
		<Help node={ node } messages={ messages } />
	) : undefined;

const toOptions = ( node ) =>
	( node.options || [] ).map( ( option ) => ( {
		value: String( option.value ),
		label: String( option.label ),
	} ) );

const TEXT_TYPES = {
	text: 'text',
	email: 'email',
	url: 'url',
	password: 'password',
	number: 'number',
	date: 'date',
};

function TextLike( { node, value, onChange, help } ) {
	const props = {
		label: node.label,
		value: undefined === value || null === value ? '' : value,
		onChange,
		help,
		type: TEXT_TYPES[ node.type ],
		placeholder: node.placeholder || undefined,
		required: node.required || undefined,
		disabled: node.disabled || undefined,
		readOnly: node.readonly || undefined,
		__nextHasNoMarginBottom: true,
		__next40pxDefaultSize: true,
	};

	if ( 'number' === node.type ) {
		props.min = node.min;
		props.max = node.max;
		props.step = node.step;
	} else if ( 'date' === node.type ) {
		props.min = node.min || undefined;
		props.max = node.max || undefined;
	} else if ( node.maxlength ) {
		props.maxLength = node.maxlength;
	}

	return <TextControl { ...props } />;
}

function ColorControl( { node, value, onChange, help, id } ) {
	const disabled = Boolean( node.disabled || node.readonly );

	return (
		<Composite id={ id } label={ node.label } help={ help }>
			<div className="cassette-cmf-color">
				<TextControl
					label={ __( 'Hex value', 'cassette-cmf' ) }
					hideLabelFromVision
					value={ value || '' }
					onChange={ onChange }
					disabled={ disabled }
					placeholder="#000000"
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<Dropdown
					popoverProps={ { placement: 'bottom-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="secondary"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							disabled={ disabled }
						>
							<span
								className="cassette-cmf-color__swatch"
								style={ { background: value || 'transparent' } }
							/>
							{ __( 'Pick color', 'cassette-cmf' ) }
						</Button>
					) }
					renderContent={ () => (
						<ColorPicker
							color={ value || undefined }
							onChange={ onChange }
							enableAlpha={ false }
							copyFormat="hex"
						/>
					) }
				/>
			</div>
		</Composite>
	);
}

function SecretControl( { node, value, onChange, help, id } ) {
	const hasStored = '' !== value.mask;
	const placeholder = value.clear
		? __( 'Will be cleared when you save', 'cassette-cmf' )
		: value.mask || node.placeholder || '';

	return (
		<Composite id={ id } label={ node.label } help={ help }>
			<div className="cassette-cmf-secret">
				<TextControl
					label={ node.label }
					hideLabelFromVision
					type="password"
					autoComplete="new-password"
					value={ value.input }
					placeholder={ placeholder }
					disabled={ value.clear || node.disabled || node.readonly }
					onChange={ ( input ) => onChange( { ...value, input } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				{ ( hasStored || value.clear ) && (
					<Button
						variant="tertiary"
						isDestructive={ ! value.clear }
						onClick={ () =>
							onChange( {
								...value,
								input: '',
								clear: ! value.clear,
							} )
						}
					>
						{ value.clear
							? __( 'Undo clear', 'cassette-cmf' )
							: __( 'Clear', 'cassette-cmf' ) }
					</Button>
				) }
			</div>
		</Composite>
	);
}

function CheckboxGroup( { node, value, onChange, help, id } ) {
	const toggle = ( key, checked ) =>
		onChange(
			checked
				? [ ...value.filter( ( item ) => item !== key ), key ]
				: value.filter( ( item ) => item !== key )
		);

	return (
		<Composite id={ id } label={ node.label } help={ help }>
			{ toOptions( node ).map( ( option ) => (
				<CheckboxControl
					key={ option.value }
					label={ option.label }
					checked={ value.includes( option.value ) }
					disabled={ node.disabled || node.readonly }
					onChange={ ( checked ) => toggle( option.value, checked ) }
					__nextHasNoMarginBottom
				/>
			) ) }
		</Composite>
	);
}

function SelectField( { node, value, onChange, help } ) {
	let options = toOptions( node );

	if (
		! node.multiple &&
		! options.some( ( option ) => option.value === value )
	) {
		options = [
			{ value: '', label: __( '— Select —', 'cassette-cmf' ) },
			...options.filter( ( option ) => '' !== option.value ),
		];
	}

	return (
		<SelectControl
			label={ node.label }
			value={ value }
			options={ options }
			multiple={ node.multiple || undefined }
			onChange={ onChange }
			help={ help }
			required={ node.required || undefined }
			disabled={ node.disabled || node.readonly || undefined }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

function rowTitle( node, row, index ) {
	const source = node.rowLabelField ? row[ node.rowLabelField ] : '';
	if ( source && 'string' === typeof source ) {
		return source;
	}
	return ( node.rowLabel || 'Row {{index}}' ).replace(
		'{{index}}',
		String( index + 1 )
	);
}

function RepeaterControl( { node, value, onChange, help, id } ) {
	const rows = value;
	const max = node.maxRows || 0;
	const min = node.minRows || 0;
	const locked = Boolean( node.disabled || node.readonly );
	const [ announcement, setAnnouncement ] = useState( '' );

	const move = ( from, to ) => {
		const next = [ ...rows ];
		next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
		onChange( next );
		setAnnouncement(
			sprintf(
				/* translators: 1: row number, 2: new position */
				__( 'Row %1$d moved to position %2$d.', 'cassette-cmf' ),
				from + 1,
				to + 1
			)
		);
	};

	return (
		<Composite id={ id } label={ node.label } help={ help }>
			<div className="cassette-cmf-repeater">
				{ rows.map( ( row, index ) => (
					<Card
						key={ index }
						size="small"
						className="cassette-cmf-repeater__row"
					>
						<CardHeader>
							<strong>{ rowTitle( node, row, index ) }</strong>
							<span className="cassette-cmf-repeater__buttons">
								<Button
									icon="arrow-up-alt2"
									label={ __(
										'Move row up',
										'cassette-cmf'
									) }
									disabled={ locked || 0 === index }
									onClick={ () => move( index, index - 1 ) }
								/>
								<Button
									icon="arrow-down-alt2"
									label={ __(
										'Move row down',
										'cassette-cmf'
									) }
									disabled={
										locked || index === rows.length - 1
									}
									onClick={ () => move( index, index + 1 ) }
								/>
								<Button
									icon="trash"
									isDestructive
									label={ __( 'Remove row', 'cassette-cmf' ) }
									disabled={ locked || rows.length <= min }
									onClick={ () =>
										onChange(
											rows.filter(
												( _, i ) => i !== index
											)
										)
									}
								/>
							</span>
						</CardHeader>
						<CardBody>
							<FieldTree
								nodes={ node.subFields || [] }
								values={ row }
								errors={ {} }
								onChange={ ( name, next ) =>
									onChange(
										rows.map( ( item, i ) =>
											i === index
												? { ...item, [ name ]: next }
												: item
										)
									)
								}
								idPrefix={ id + '-' + index + '-' }
							/>
						</CardBody>
					</Card>
				) ) }
				<Button
					variant="secondary"
					disabled={ locked || ( max > 0 && rows.length >= max ) }
					onClick={ () => onChange( [ ...rows, newRow( node ) ] ) }
				>
					{ node.buttonLabel || __( 'Add Row', 'cassette-cmf' ) }
				</Button>
				<div className="screen-reader-text" aria-live="polite">
					{ announcement }
				</div>
			</div>
		</Composite>
	);
}

/**
 * One value-storing field.
 *
 * @param {Object}   props
 * @param {Object}   props.node     Field node.
 * @param {*}        props.value    Form value.
 * @param {Function} props.onChange Called with the new form value.
 * @param {string[]} props.messages Server errors for this field.
 * @param {string}   props.id       Unique DOM id.
 */
export function FieldControl( { node, value, onChange, messages, id } ) {
	const help = helpOf( node, messages );
	const common = { node, value, onChange, help, id };

	switch ( node.type ) {
		case 'textarea':
		case 'wysiwyg':
			return (
				<TextareaControl
					label={ node.label }
					value={ value }
					onChange={ onChange }
					help={ help }
					rows={ node.rows || 5 }
					placeholder={ node.placeholder || undefined }
					required={ node.required || undefined }
					disabled={ node.disabled || undefined }
					readOnly={ node.readonly || undefined }
					__nextHasNoMarginBottom
				/>
			);
		case 'select':
			return <SelectField { ...common } />;
		case 'radio':
			return (
				<RadioControl
					label={ node.label }
					selected={ value }
					options={ toOptions( node ) }
					onChange={ onChange }
					help={ help }
					disabled={ node.disabled || node.readonly || undefined }
				/>
			);
		case 'checkbox':
			if ( ( node.options || [] ).length ) {
				return <CheckboxGroup { ...common } />;
			}
			return (
				<CheckboxControl
					label={ node.label }
					checked={ Boolean( value ) }
					onChange={ onChange }
					help={ help }
					disabled={ node.disabled || node.readonly || undefined }
					__nextHasNoMarginBottom
				/>
			);
		case 'color':
			return <ColorControl { ...common } />;
		case 'upload':
			return <MediaControl { ...common } />;
		case 'secret':
			return <SecretControl { ...common } />;
		case 'repeater':
			return <RepeaterControl { ...common } />;
		case 'text':
		case 'email':
		case 'url':
		case 'password':
		case 'number':
		case 'date':
		default:
			return <TextLike { ...common } />;
	}
}

function CustomHtml( { node } ) {
	return (
		<div className="cassette-cmf-custom-html">
			{ node.label && <strong>{ node.label }</strong> }
			<div
				className="cassette-cmf-custom-html-content"
				// Sanitized on the server exactly like the classic renderer.
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={ { __html: node.html || '' } }
			/>
			{ node.description && (
				<p className="description">{ node.description }</p>
			) }
		</div>
	);
}

function Tabs( { node, values, errors, onChange, idPrefix } ) {
	const { tabs } = useContext( FormContext );
	const forced = tabs.force[ node.name ];
	const tabList = ( node.tabs || [] ).map( ( tab ) => ( {
		name: tab.id,
		title: tab.title,
	} ) );

	if ( ! tabList.length ) {
		return null;
	}

	return (
		<TabPanel
			key={ forced ? forced.nonce : 'tabs' }
			className="cassette-cmf-tabs"
			orientation={ node.orientation || 'horizontal' }
			tabs={ tabList }
			initialTabName={
				( forced && forced.tab ) || node.defaultTab || undefined
			}
			onSelect={ ( name ) => tabs.remember( node.name, name ) }
		>
			{ ( tab ) => {
				const definition = node.tabs.find(
					( item ) => item.id === tab.name
				);
				return (
					<FieldTree
						nodes={ ( definition && definition.fields ) || [] }
						values={ values }
						errors={ errors }
						onChange={ onChange }
						idPrefix={ idPrefix }
					/>
				);
			} }
		</TabPanel>
	);
}

/**
 * Render a list of nodes, honouring conditional display.
 *
 * @param {Object}   props
 * @param {Object[]} props.nodes    Nodes.
 * @param {Object}   props.values   Form values by field name.
 * @param {Object}   props.errors   Messages by field name.
 * @param {Function} props.onChange Called with (name, value).
 * @param {string}   props.idPrefix Prefix for DOM ids.
 */
export function FieldTree( {
	nodes,
	values,
	errors,
	onChange,
	idPrefix = 'cassette-cmf-',
} ) {
	const { conditions } = useContext( FormContext );

	return (
		<>
			{ nodes.map( ( node, index ) => {
				if (
					node.conditional &&
					! isConditionMet( node.conditional, conditions )
				) {
					return null;
				}

				const key = node.name + ':' + index;

				if ( 'group' === node.type ) {
					return (
						<Panel key={ key } className="cassette-cmf-group">
							<PanelBody
								title={ node.label || node.name }
								initialOpen
							>
								{ node.description && (
									<p className="description">
										{ node.description }
									</p>
								) }
								<FieldTree
									nodes={ node.children || [] }
									values={ values }
									errors={ errors }
									onChange={ onChange }
									idPrefix={ idPrefix }
								/>
							</PanelBody>
						</Panel>
					);
				}

				if ( 'metabox' === node.type ) {
					return (
						<Card key={ key } className="cassette-cmf-metabox">
							<CardHeader>
								<h2 className="cassette-cmf-metabox__title">
									{ node.title || node.label || node.name }
								</h2>
							</CardHeader>
							<CardBody>
								<FieldTree
									nodes={ node.children || [] }
									values={ values }
									errors={ errors }
									onChange={ onChange }
									idPrefix={ idPrefix }
								/>
							</CardBody>
						</Card>
					);
				}

				if ( 'tabs' === node.type ) {
					return (
						<Tabs
							key={ key }
							node={ node }
							values={ values }
							errors={ errors }
							onChange={ onChange }
							idPrefix={ idPrefix }
						/>
					);
				}

				if ( 'custom_html' === node.type ) {
					return <CustomHtml key={ key } node={ node } />;
				}

				const messages = errors[ node.name ];

				return (
					<div
						key={ key }
						className={
							'cassette-cmf-field cassette-cmf-field--' +
							node.type +
							( messages && messages.length ? ' has-error' : '' )
						}
						data-cassette-field={ node.name }
					>
						<FieldControl
							node={ node }
							value={ values[ node.name ] }
							messages={ messages }
							id={ idPrefix + node.name }
							onChange={ ( next ) => onChange( node.name, next ) }
						/>
					</div>
				);
			} ) }
		</>
	);
}
