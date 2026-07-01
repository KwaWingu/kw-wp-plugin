/**
 * kwawingu/booking view: create booking -> start payment -> poll status.
 */
( function () {
	'use strict';
	function init( form ) {
		var status = form.querySelector( '.kwt-booking__status' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			status.textContent = window.kwtProxy.i18n.loading;
			var email = form.email.value.trim();
			var phone = form.phone.value.trim();
			var payload = {
				tourSlug: form.getAttribute( 'data-tour' ),
				customer: { name: form.name.value.trim(), email: email, phone: phone },
				date: form.date.value,
				pax: Number( form.pax.value ) || 1
			};
			window.kwtProxy.post( '/bookings', payload ).then( function ( res ) {
				var ref = ( res && ( res.ref || ( res.data && res.data.ref ) ) );
				if ( ! ref ) { throw new Error( window.kwtProxy.i18n.error ); }
				return window.kwtProxy.post( '/payment-intent', { ref: ref, phone: phone } ).then( function () {
					status.textContent = window.kwtProxy.i18n.checkPhone;
					poll( ref, email, 0 );
				} );
			} ).catch( function ( err ) { status.textContent = err.message || window.kwtProxy.i18n.error; } );
		} );

		function poll( ref, email, tries ) {
			if ( tries > 40 ) { return; }
			setTimeout( function () {
				window.kwtProxy.get( '/booking', { ref: ref, email: email } ).then( function ( res ) {
					var data = res && res.data ? res.data : res;
					var st = data && ( data.status || data.paymentStatus );
					if ( st === 'paid' || st === 'confirmed' || st === 'completed' ) {
						status.textContent = window.kwtProxy.i18n.paymentReceived;
					} else {
						poll( ref, email, tries + 1 );
					}
				} ).catch( function () { poll( ref, email, tries + 1 ); } );
			}, 5000 );
		}
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-booking' ), init );
	} );
} )();
