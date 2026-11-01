/**
 * Wrapper for controls made of several inputs (checkbox group, color, upload,
 * repeater): a fieldset whose legend is the label and whose help text is
 * linked with aria-describedby.
 */

export default function Composite( { id, label, help, children, className } ) {
	return (
		<fieldset
			className={ 'cassette-cmf-composite ' + ( className || '' ) }
			aria-describedby={ help ? id + '__help' : undefined }
		>
			{ label && (
				<legend className="cassette-cmf-composite__label">
					{ label }
				</legend>
			) }
			{ children }
			{ help && (
				<div
					id={ id + '__help' }
					className="cassette-cmf-composite__help components-base-control__help"
				>
					{ help }
				</div>
			) }
		</fieldset>
	);
}
