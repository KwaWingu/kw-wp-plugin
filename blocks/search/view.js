/**
 * kwawingu/search view: debounced live search via the proxy.
 */
( function () {
	'use strict';
	function init( root ) {
		var input = root.querySelector( '.kwt-search__input' );
		var list = root.querySelector( '.kwt-search__results' );
		var t;
		input.addEventListener( 'input', function () {
			clearTimeout( t );
			var q = input.value.trim();
			if ( q.length < 2 ) { list.textContent = ''; return; }
			t = setTimeout( function () {
				window.kwtProxy.get( '/search', { q: q } ).then( function ( res ) {
					list.textContent = '';
					var items = ( res && res.data ) || [];
					if ( ! items.length ) {
						var li = document.createElement( 'li' );
						li.textContent = window.kwtProxy.i18n.noResults;
						list.appendChild( li );
						return;
					}
					items.forEach( function ( item ) {
						var li = document.createElement( 'li' );
						var a = document.createElement( 'a' );
						a.href = item.url || '#';
						a.textContent = item.title || '';
						li.appendChild( a );
						list.appendChild( li );
					} );
				} ).catch( function () { list.textContent = window.kwtProxy.i18n.error; } );
			}, 250 );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-search' ), init );
	} );
} )();
