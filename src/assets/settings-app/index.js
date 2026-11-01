/**
 * Entry point: mounts the settings app into every
 * `.cassette-cmf-settings-app-root` element. The element's
 * `data-config-global` names the window object holding the page's config
 * (set by an inline script that React_Renderer prints before this file).
 */

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import App from './app';
import './settings-app.scss';

export function mount( element, config ) {
	const root = createRoot( element );
	root.render( <App { ...config } /> );
	return root;
}

domReady( () => {
	document
		.querySelectorAll( '.cassette-cmf-settings-app-root' )
		.forEach( ( element ) => {
			const config = window[ element.dataset.configGlobal ];
			if ( config ) {
				mount( element, config );
			}
		} );
} );
