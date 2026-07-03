/**
 * kwawingu/inquiry-form view: client validation -> honeypot check -> POST /inquiry -> success/error.
 */

/**
 * Build the inquiry payload, returning null if the honeypot is filled.
 *
 * @param {HTMLFormElement} form
 * @returns {Object|null}
 */
function kwtBuildInquiryPayload( form ) {
	// Honeypot — bots fill this; real users don't.
	var hp = form.querySelector( '[name="kwt_hp"]' );
	if ( hp && '' !== hp.value ) {
		return null;
	}

	var tourSlug = ( form.getAttribute( 'data-tour' ) || '' ).trim();
	var payload = {
		name: ( form.name.value || '' ).trim(),
		email: ( form.email.value || '' ).trim(),
		adults: Number( form.adults ? form.adults.value : 2 ) || 2,
		children: Number( form.children ? form.children.value : 0 ) || 0,
	};

	var phone = form.phone ? ( form.phone.value || '' ).trim() : '';
	if ( '' !== phone ) {
		payload.phone = phone;
	}

	var date = form.date ? ( form.date.value || '' ).trim() : '';
	if ( '' !== date ) {
		payload.date = date;
	}

	var message = form.message ? ( form.message.value || '' ).trim() : '';
	if ( '' !== message ) {
		payload.message = message;
	}

	if ( '' !== tourSlug ) {
		payload.tourSlug = tourSlug;
	}

	return payload;
}

( function () {
	'use strict';

	function init( form ) {
		var status = form.querySelector( '.kwt-inquiry__status' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var payload = kwtBuildInquiryPayload( form );

			// Honeypot triggered — silently succeed (don't reward bots with an error).
			if ( null === payload ) {
				status.textContent = window.kwtProxy.i18n.inquirySuccess || 'Thanks — we\'ll get back to you shortly.';
				return;
			}

			// Basic client-side required-field check.
			if ( ! payload.name || ! payload.email ) {
				status.textContent = window.kwtProxy.i18n.error || 'Please fill in all required fields.';
				return;
			}

			var btn = form.querySelector( 'button[type="submit"]' );
			if ( btn ) { btn.disabled = true; }
			status.textContent = window.kwtProxy.i18n.loading || 'Sending…';

			window.kwtProxy.post( '/inquiry', payload )
				.then( function () {
					form.reset();
					status.textContent = window.kwtProxy.i18n.inquirySuccess || 'Thanks — we\'ll get back to you shortly.';
					if ( btn ) { btn.disabled = false; }
				} )
				.catch( function ( err ) {
					status.textContent = ( err && err.message ) || ( window.kwtProxy.i18n.error ) || 'Something went wrong. Please try again.';
					if ( btn ) { btn.disabled = false; }
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-inquiry' ), init );
	} );
} )();

/* Testable exports (ignored in the browser). */
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = {
		buildInquiryPayload: kwtBuildInquiryPayload,
	};
}
