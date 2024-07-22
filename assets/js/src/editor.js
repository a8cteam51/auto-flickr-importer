import { createHooks } from '@wordpress/hooks';
import domReady from '@wordpress/dom-ready';

window.auto_flickr_importer = window.auto_flickr_importer || {};
window.auto_flickr_importer.hooks = createHooks();

domReady( () => {
	window.auto_flickr_importer.hooks.doAction( 'editor.ready' );
} );
