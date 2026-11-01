/**
 * Upload field: the WordPress media modal (wp.media, loaded by
 * wp_enqueue_media() on the page) plus a preview.
 *
 * Stores exactly what the classic field stores: the attachment ID. A value
 * that is a URL (the classic field accepts one) is previewed and kept as is.
 */

import { useEffect, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import Composite from './composite';
import { __ } from '@wordpress/i18n';

const IMAGE = /\.(jpe?g|png|gif|webp|avif|svg|bmp|ico)(\?.*)?$/i;

const isId = ( value ) =>
	'number' === typeof value ||
	( 'string' === typeof value && /^\d+$/.test( value ) );

function describe( attachment ) {
	const sizes = attachment.sizes || {};
	return {
		url: attachment.url || '',
		thumb:
			( sizes.thumbnail && sizes.thumbnail.url ) || attachment.url || '',
		filename: attachment.filename || '',
		image: 'image' === attachment.type,
	};
}

function useMeta( value ) {
	const [ meta, setMeta ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		const media = window.wp && window.wp.media;

		if ( ! value ) {
			setMeta( null );
		} else if ( ! isId( value ) ) {
			const url = String( value );
			setMeta( {
				url,
				thumb: url,
				filename: url.split( '/' ).pop(),
				image: IMAGE.test( url ),
			} );
		} else if ( media && media.attachment ) {
			const model = media.attachment( Number( value ) );
			if ( model.get( 'url' ) ) {
				setMeta( describe( model.toJSON() ) );
			} else {
				model.fetch().then( () => {
					if ( ! cancelled ) {
						setMeta( describe( model.toJSON() ) );
					}
				} );
			}
		}

		return () => {
			cancelled = true;
		};
	}, [ value ] );

	return meta;
}

export default function MediaControl( { node, value, onChange, help, id } ) {
	const meta = useMeta( value );
	const disabled = Boolean( node.disabled || node.readonly );
	const hasValue = '' !== value && null !== value && undefined !== value;

	const open = () => {
		const media = window.wp && window.wp.media;
		if ( ! media ) {
			return;
		}

		const options = {
			title: node.label || __( 'Select file', 'cassette-cmf' ),
			button: {
				text: node.buttonText || __( 'Select File', 'cassette-cmf' ),
			},
			multiple: false,
		};
		if ( node.libraryType ) {
			options.library = { type: node.libraryType };
		}

		const frame = media( options );
		frame.on( 'select', () => {
			const attachment = frame
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			onChange( attachment.id );
		} );
		frame.open();
	};

	return (
		<Composite id={ id } label={ node.label } help={ help }>
			<div className="cassette-cmf-upload" id={ id }>
				{ hasValue && meta && meta.image && node.preview !== false && (
					<img
						className="cassette-cmf-upload__preview"
						src={ meta.thumb }
						alt={ meta.filename }
					/>
				) }
				{ hasValue && meta && ! meta.image && (
					<p className="cassette-cmf-upload__filename">
						{ meta.filename }
					</p>
				) }
				<div className="cassette-cmf-upload__actions">
					<Button
						variant="secondary"
						onClick={ open }
						disabled={ disabled }
					>
						{ node.buttonText ||
							__( 'Select File', 'cassette-cmf' ) }
					</Button>
					{ hasValue && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => onChange( '' ) }
							disabled={ disabled }
						>
							{ node.removeText ||
								__( 'Remove', 'cassette-cmf' ) }
						</Button>
					) }
				</div>
			</div>
		</Composite>
	);
}
