/**
 * KwaWingu proxy client. Same-origin fetch to /wp-json/kwawingu/v1/* with the
 * REST nonce. No keys here — the server proxy holds them.
 *
 * On a 403 (stale nonce — common on full-page-cached sites where the nonce is
 * baked into cached HTML and expires), it mints a fresh nonce from the public
 * /nonce endpoint and retries the request ONCE. POST /bookings carries an
 * idempotencyKey, so a retried create is deduplicated by the API.
 */
( function () {
	'use strict';
	var cfg = window.kwtProxy || {};

	function build( method, path, dataOrParams ) {
		var url = cfg.root + path;
		var opts = { method: method, headers: { 'X-WP-Nonce': cfg.nonce } };
		if ( 'GET' === method && dataOrParams ) {
			var qs = Object.keys( dataOrParams ).map( function ( k ) {
				return encodeURIComponent( k ) + '=' + encodeURIComponent( dataOrParams[ k ] );
			} ).join( '&' );
			if ( qs ) { url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + qs; }
		} else if ( dataOrParams ) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify( dataOrParams );
		}
		return { url: url, opts: opts };
	}

	function refreshNonce() {
		return fetch( cfg.root + '/nonce' ).then( function ( r ) {
			return r.json();
		} ).then( function ( b ) {
			if ( b && b.nonce ) { cfg.nonce = b.nonce; }
			return cfg.nonce;
		} );
	}

	function req( method, path, dataOrParams, retried ) {
		var r = build( method, path, dataOrParams );
		return fetch( r.url, r.opts ).then( function ( resp ) {
			if ( 403 === resp.status && ! retried ) {
				// Stale nonce (likely a cached page) — mint a fresh one and retry once.
				return refreshNonce().then( function () {
					return req( method, path, dataOrParams, true );
				} );
			}
			return resp.json().then( function ( body ) {
				if ( ! resp.ok ) { throw new Error( ( body && body.message ) || cfg.i18n.error ); }
				return body;
			} );
		} );
	}

	cfg.get = function ( path, params ) { return req( 'GET', path, params, false ); };
	cfg.post = function ( path, body ) { return req( 'POST', path, body, false ); };
	window.kwtProxy = cfg;
} )();
