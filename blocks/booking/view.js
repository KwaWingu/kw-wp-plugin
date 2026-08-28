/**
 * kwawingu/booking view: load departures -> live quote -> create booking
 * (correct payload) -> start payment -> poll status -> link to portal.
 */
function kwtMoney( n, currency ) {
	return ( currency || 'TZS' ) + ' ' + ( Number( n ) || 0 ).toLocaleString();
}

/**
 * The party total of a POST /quote response, formatted in its own currency.
 *
 * The Quote schema names it `totalAmount` (with `currency`); the previous
 * `total || perPersonTotal` chain matched nothing and always showed "TZS 0".
 *
 * @param {Object} res Proxy response (`{ data: Quote }` or the quote itself).
 * @return {string} e.g. "TZS 4,900,000".
 */
function kwtQuoteTotal( res ) {
	var data = ( res && res.data ) || res || {};
	var amount = data.totalAmount != null ? data.totalAmount : ( data.total || 0 );
	return kwtMoney( amount, data.currency );
}

/** ≤30-char idempotency key. */
function kwtIdemKey() {
	return ( 'wp-' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 ) ).slice( 0, 30 );
}

/**
 * Build the create-booking payload in the EXACT shape the KwaWingu API expects.
 * @param {Object} v - collected form values (tourSlug, adults, children, infants,
 *                      firstName, lastName, email, phone, departureId, idempotencyKey?).
 */
function kwtBuildBookingPayload( v ) {
	var payload = {
		tourSlug: v.tourSlug,
		adults: Number( v.adults ) || 1,
		children: Number( v.children ) || 0,
		infants: Number( v.infants ) || 0,
		guestFirstName: ( v.firstName || '' ).trim(),
		guestLastName: ( v.lastName || '' ).trim(),
		guestEmail: ( v.email || '' ).trim(),
		guestPhone: ( v.phone || '' ).trim(),
		idempotencyKey: v.idempotencyKey || kwtIdemKey()
	};
	if ( v.departureId ) {
		payload.departureId = v.departureId;
	}
	return payload;
}

/** Extract the booking ref from the create-booking response (shape varies). */
function kwtReadBookingRef( res ) {
	var booking = ( res && ( res.booking || ( res.data && res.data.booking ) ) ) || res || {};
	return booking.ref || booking.bookingReference || ( res && res.ref ) || '';
}

/** Extract the guest portal URL from the create-booking response. */
function kwtReadPortalUrl( res ) {
	var booking = ( res && ( res.booking || ( res.data && res.data.booking ) ) ) || {};
	return ( res && ( res.portalUrl || ( res.data && res.data.portalUrl ) ) ) || booking.portalUrl || '';
}

/**
 * Extract the guest portal token from the create-booking response
 * (`BookingResult.portalToken` — a per-booking secret, not recoverable later).
 */
function kwtReadPortalToken( res ) {
	var booking = ( res && ( res.booking || ( res.data && res.data.booking ) ) ) || {};
	return ( res && ( res.portalToken || ( res.data && res.data.portalToken ) ) ) || booking.portalToken || '';
}

/**
 * Whether a BookingDetail shows that money has arrived.
 *
 * The API's `status` is the booking lifecycle (INQUIRY, QUOTE, CONFIRMED, ON_TRIP,
 * COMPLETED, CANCELLED — upper case) and a booking is CONFIRMED the moment it is
 * created, before any payment, so it cannot mean "paid". What can: the balance
 * dropping below the total. `*Minor` are exact integers and are preferred; the
 * decimal pair is the fallback for an older payload; a lower-cased status of
 * paid/completed is the last resort when no amounts are present at all.
 *
 * @param {Object} data BookingDetail (the `data` of a lookup response, or the response).
 * @return {boolean}
 */
function kwtPaymentReceived( data ) {
	data = data || {};
	var pairs = [ [ data.totalAmountMinor, data.balanceAmountMinor ], [ data.totalAmount, data.balanceAmount ] ];
	for ( var i = 0; i < pairs.length; i++ ) {
		var total = Number( pairs[ i ][ 0 ] ), balance = Number( pairs[ i ][ 1 ] );
		if ( pairs[ i ][ 0 ] != null && pairs[ i ][ 1 ] != null && ! isNaN( total ) && ! isNaN( balance ) ) {
			return total > 0 && balance < total;
		}
	}
	var st = String( data.paymentStatus || data.status || '' ).toLowerCase();
	return st === 'paid' || st === 'completed';
}

/**
 * The proxy request that looks a booking up while polling for payment.
 *
 * With a portal token the guest is identified by the `X-Portal-Token` header
 * (the API's preferred, non-logged form). Without one — a response that did not
 * carry a token — it falls back to the deprecated `?email=` lookup, which the
 * API retires on 2027-07-01.
 *
 * @param {string} ref   Booking reference.
 * @param {string} token Portal token, or '' when none was issued.
 * @param {string} email Lead guest email (fallback only).
 * @return {{params: Object, headers: Object}} Arguments for kwtProxy.get('/booking', …).
 */
function kwtBookingLookupRequest( ref, token, email ) {
	if ( token ) {
		return { params: { ref: ref }, headers: { 'X-Portal-Token': token } };
	}
	return { params: { ref: ref, email: email }, headers: {} };
}

( function () {
	'use strict';

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
				priceEl.textContent = window.kwtProxy.i18n.priceFrom + ' ' + kwtQuoteTotal( res );
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
			var payload = kwtBuildBookingPayload( {
				tourSlug: tourSlug,
				adults: p.adults,
				children: p.children,
				infants: p.infants,
				firstName: form.firstName.value,
				lastName: form.lastName.value,
				email: email,
				phone: phone,
				departureId: select.value || ''
			} );

			window.kwtProxy.post( '/bookings', payload ).then( function ( res ) {
				var ref = kwtReadBookingRef( res );
				var portalUrl = kwtReadPortalUrl( res );
				var portalToken = kwtReadPortalToken( res );
				if ( ! ref ) { throw new Error( window.kwtProxy.i18n.error ); }
				return window.kwtProxy.post( '/payment-intent', { ref: ref, phone: phone } ).then( function () {
					status.textContent = window.kwtProxy.i18n.checkPhone;
					poll( ref, { token: portalToken, email: email }, portalUrl, 0 );
				} );
			} ).catch( function ( err ) { status.textContent = err.message || window.kwtProxy.i18n.error; } );
		} );

		function poll( ref, guest, portalUrl, tries ) {
			if ( tries > 40 ) { return; }
			setTimeout( function () {
				var lookup = kwtBookingLookupRequest( ref, guest.token, guest.email );
				window.kwtProxy.get( '/booking', lookup.params, lookup.headers ).then( function ( res ) {
					var data = res && res.data ? res.data : res;
					if ( kwtPaymentReceived( data ) ) {
						status.textContent = '';
						var msg = document.createElement( 'span' );
						msg.textContent = window.kwtProxy.i18n.paymentReceived + ' ';
						status.appendChild( msg );
						// Only link https URLs — never a javascript:/data: URI, even if the API is compromised.
						if ( portalUrl && /^https:\/\//i.test( portalUrl ) ) {
							var a = document.createElement( 'a' );
							a.href = portalUrl;
							a.textContent = window.kwtProxy.i18n.manageBooking;
							status.appendChild( a );
						}
					} else {
						poll( ref, guest, portalUrl, tries + 1 );
					}
				} ).catch( function () { poll( ref, guest, portalUrl, tries + 1 ); } );
			}, 5000 );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-booking' ), init );
	} );
} )();

/* Testable exports (ignored in the browser). */
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = {
		buildBookingPayload: kwtBuildBookingPayload,
		readBookingRef: kwtReadBookingRef,
		readPortalUrl: kwtReadPortalUrl,
		readPortalToken: kwtReadPortalToken,
		bookingLookupRequest: kwtBookingLookupRequest,
		paymentReceived: kwtPaymentReceived,
		idemKey: kwtIdemKey,
		money: kwtMoney,
		quoteTotal: kwtQuoteTotal
	};
}
