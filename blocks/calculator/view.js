/**
 * kwawingu/calculator view: posts inputs to the proxy, shows the total.
 */
( function () {
	'use strict';
	function init( form ) {
		var total = form.querySelector( '.kwt-calculator__total' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			total.textContent = window.kwtProxy.i18n.loading;
			var body = {
				adults: Number( form.adults.value ) || 1,
				children: Number( form.children.value ) || 0,
				nights: Number( form.nights.value ) || 1
			};
			window.kwtProxy.post( '/calculator/estimate', body ).then( function ( res ) {
				var data = ( res && res.data ) || res || {};
				var amount = data.total || data.perPersonTotal || 0;
				total.textContent = 'TZS ' + Number( amount ).toLocaleString();
			} ).catch( function () { total.textContent = window.kwtProxy.i18n.error; } );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-calculator' ), init );
	} );
} )();
