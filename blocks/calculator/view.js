/**
 * kwawingu/calculator view: posts inputs to the proxy, shows the total.
 */

/**
 * The figure to show for an estimate: `grandTotal` for the whole party, in the
 * API's currency. `perPersonTotal` is a different number (grand total / pax) and
 * was what an earlier version showed as the total, since it fell back to the
 * first numeric key it found.
 *
 * @param {Object} res Proxy response (`{ data: CalculatorEstimate }` or the estimate itself).
 * @return {string} e.g. "TZS 920,000".
 */
function kwtCalculatorTotal( res ) {
	var data = ( res && res.data ) || res || {};
	var amount = data.grandTotal != null ? data.grandTotal : ( data.total || 0 );
	var currency = data.currency || 'TZS';
	return currency + ' ' + ( Number( amount ) || 0 ).toLocaleString();
}

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
				total.textContent = kwtCalculatorTotal( res );
			} ).catch( function ( err ) {
				// The proxy's message is written for the visitor (e.g. "not available at the
				// moment"); only fall back to the generic error when there is none.
				total.textContent = ( err && err.message ) || window.kwtProxy.i18n.error;
			} );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-calculator' ), init );
	} );
} )();

/* Testable exports (ignored in the browser). */
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = { calculatorTotal: kwtCalculatorTotal };
}
