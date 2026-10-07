/**
 * WP Smart SEO: live character counters and search snippet preview.
 */
( function () {
	'use strict';

	var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;

	function counterText( length, limit ) {
		var template = i18n ? i18n.__( '%1$d / %2$d characters', 'wp-smart-seo' ) : '%1$d / %2$d characters';
		return template.replace( '%1$d', length ).replace( '%2$d', limit );
	}

	function bind( field, previewSelector ) {
		var limit = parseInt( field.getAttribute( 'data-wpss-limit' ), 10 );
		var counter = field.parentNode.querySelector( '.wpss-counter' );
		var preview = document.querySelector( previewSelector );

		function update() {
			var value = field.value.trim();
			if ( counter ) {
				counter.textContent = counterText( value.length, limit );
				counter.classList.toggle( 'is-long', value.length > limit );
			}
			if ( preview ) {
				preview.textContent = value || preview.getAttribute( 'data-fallback' ) || '';
			}
		}

		field.addEventListener( 'input', update );
		update();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var title = document.getElementById( 'wpss-title' );
		var description = document.getElementById( 'wpss-description' );
		if ( title ) {
			bind( title, '.wpss-preview__title' );
		}
		if ( description ) {
			bind( description, '.wpss-preview__desc' );
		}
	} );
}() );
