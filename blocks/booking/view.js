/**
 * kwawingu/booking view: load departures -> live quote -> create booking
 * (correct payload) -> start payment -> poll status -> link to portal.
 */
( function () {
	'use strict';

	function money( n ) {
		return 'TZS ' + ( Number( n ) || 0 ).toLocaleString();
	}

	/** ≤30-char idempotency key. */
	function idemKey() {
		return ( 'wp-' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 ) ).slice( 0, 30 );
	}

	function init( form ) {
		var status = form.querySelector( '.kwt-booking__status' );
		var priceEl = form.querySelector( '.kwt-booking__price' );
		var select = form.querySelector( '.kwt-booking__departure' );
		var tourSlug = form.getAttribute( 'data-tour' );

		function pax() {
			return {
				adults: Number( form.adults.value ) || 1,
				children: Number( form.children.value ) || 0,
				infants: Number( form.infants.value ) || 0
			};
		}

		// 1. Load departures for this tour into the select.
		window.kwtProxy.get( '/departures', { tourSlug: tourSlug } ).then( function ( res ) {
			var items = ( res && res.data ) || [];
			items.forEach( function ( d ) {
				var opt = document.createElement( 'option' );
				opt.value = d.id || d.departureId || '';
				var label = ( d.date || d.departureDate || '' );
				if ( d.availableSeats != null ) { label += ' (' + d.availableSeats + ')'; }
				opt.textContent = label;
				select.appendChild( opt );
			} );
		} ).catch( function () { /* leave the select with just the placeholder */ } );

		// 2. Live price when inputs change.
		function refreshPrice() {
			var p = pax();
			var body = { tourSlug: tourSlug, adults: p.adults, children: p.children, infants: p.infants };
			if ( select.value ) { body.departureId = select.value; }
			priceEl.textContent = window.kwtProxy.i18n.loading;
			window.kwtProxy.post( '/quote', body ).then( function ( res ) {
				var data = ( res && res.data ) || res || {};
				var total = data.total || data.perPersonTotal || 0;
				priceEl.textContent = window.kwtProxy.i18n.priceFrom + ' ' + money( total );
			} ).catch( function () { priceEl.textContent = ''; } );
		}
		[ 'change' ].forEach( function ( ev ) {
			select.addEventListener( ev, refreshPrice );
			form.adults.addEventListener( ev, refreshPrice );
			form.children.addEventListener( ev, refreshPrice );
			form.infants.addEventListener( ev, refreshPrice );
		} );

		// 3. Submit: create booking with the REAL payload, then pay + poll.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			status.textContent = window.kwtProxy.i18n.loading;
			var email = form.email.value.trim();
			var phone = form.phone.value.trim();
			var p = pax();
			var payload = {
				tourSlug: tourSlug,
				adults: p.adults,
				children: p.children,
				infants: p.infants,
				guestFirstName: form.firstName.value.trim(),
				guestLastName: form.lastName.value.trim(),
				guestEmail: email,
				guestPhone: phone,
				idempotencyKey: idemKey()
			};
			if ( select.value ) { payload.departureId = select.value; }

			window.kwtProxy.post( '/bookings', payload ).then( function ( res ) {
				var booking = ( res && ( res.booking || ( res.data && res.data.booking ) ) ) || res || {};
				var ref = booking.ref || booking.bookingReference || res.ref;
				var portalUrl = ( res && ( res.portalUrl || ( res.data && res.data.portalUrl ) ) ) || ( booking && booking.portalUrl );
				if ( ! ref ) { throw new Error( window.kwtProxy.i18n.error ); }
				return window.kwtProxy.post( '/payment-intent', { ref: ref, phone: phone } ).then( function () {
					status.textContent = window.kwtProxy.i18n.checkPhone;
					poll( ref, email, portalUrl, 0 );
				} );
			} ).catch( function ( err ) { status.textContent = err.message || window.kwtProxy.i18n.error; } );
		} );

		function poll( ref, email, portalUrl, tries ) {
			if ( tries > 40 ) { return; }
			setTimeout( function () {
				window.kwtProxy.get( '/booking', { ref: ref, email: email } ).then( function ( res ) {
					var data = res && res.data ? res.data : res;
					var st = data && ( data.status || data.paymentStatus );
					if ( st === 'paid' || st === 'confirmed' || st === 'completed' ) {
						status.textContent = '';
						var msg = document.createElement( 'span' );
						msg.textContent = window.kwtProxy.i18n.paymentReceived + ' ';
						status.appendChild( msg );
						if ( portalUrl ) {
							var a = document.createElement( 'a' );
							a.href = portalUrl;
							a.textContent = window.kwtProxy.i18n.manageBooking;
							status.appendChild( a );
						}
					} else {
						poll( ref, email, portalUrl, tries + 1 );
					}
				} ).catch( function () { poll( ref, email, portalUrl, tries + 1 ); } );
			}, 5000 );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-booking' ), init );
	} );
} )();
