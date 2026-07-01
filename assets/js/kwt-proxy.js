/**
 * KwaWingu proxy client. Same-origin fetch to /wp-json/kwawingu/v1/* with the
 * REST nonce. No keys here — the server proxy holds them.
 */
( function () {
	'use strict';
	var cfg = window.kwtProxy || {};

	function req( method, path, dataOrParams ) {
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
		return fetch( url, opts ).then( function ( r ) {
			return r.json().then( function ( body ) {
				if ( ! r.ok ) { throw new Error( ( body && body.message ) || cfg.i18n.error ); }
				return body;
			} );
		} );
	}

	cfg.get = function ( path, params ) { return req( 'GET', path, params ); };
	cfg.post = function ( path, body ) { return req( 'POST', path, body ); };
	window.kwtProxy = cfg;
} )();
