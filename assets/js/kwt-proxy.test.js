/**
 * Covers the stale-nonce recovery: a 403 triggers one /nonce refresh + retry.
 * (wp-scripts runs tests in a jsdom environment, so `window` is available.)
 */

const loadProxy = ( nonce ) => {
	window.kwtProxy = {
		root: '/wp-json/kwawingu/v1',
		nonce,
		i18n: { error: 'err' },
	};
	jest.resetModules();
	require( './kwt-proxy.js' );
	return window.kwtProxy;
};

const jsonResponse = ( ok, status, body ) =>
	Promise.resolve( {
		ok,
		status,
		json: () => Promise.resolve( body ),
	} );

afterEach( () => {
	delete global.fetch;
	delete window.kwtProxy;
} );

test( 'retries once with a fresh nonce after a 403', async () => {
	const calls = [];
	global.fetch = jest.fn( ( url, opts ) => {
		calls.push( {
			url,
			nonce:
				opts && opts.headers ? opts.headers[ 'X-WP-Nonce' ] : undefined,
		} );
		if ( url.indexOf( '/nonce' ) !== -1 ) {
			return jsonResponse( true, 200, { nonce: 'fresh' } );
		}
		if ( calls.length === 1 ) {
			return jsonResponse( false, 403, { message: 'bad nonce' } );
		}
		return jsonResponse( true, 200, { data: 'ok' } );
	} );

	const proxy = loadProxy( 'stale' );
	const out = await proxy.get( '/search', { q: 'x' } );

	expect( out ).toEqual( { data: 'ok' } );
	// 1) original (stale) → 403, 2) /nonce refresh, 3) retry with fresh nonce.
	expect( calls.length ).toBe( 3 );
	expect( calls[ 0 ].nonce ).toBe( 'stale' );
	expect( calls[ 2 ].nonce ).toBe( 'fresh' );
} );

test( 'does not retry a second time (no infinite loop)', async () => {
	global.fetch = jest.fn( ( url ) => {
		if ( url.indexOf( '/nonce' ) !== -1 ) {
			return jsonResponse( true, 200, { nonce: 'fresh' } );
		}
		return jsonResponse( false, 403, { message: 'still bad' } );
	} );

	const proxy = loadProxy( 'stale' );
	await expect( proxy.get( '/search', {} ) ).rejects.toThrow( 'still bad' );
	// original + nonce + one retry = 3; the retry's 403 is NOT retried again.
	expect( global.fetch.mock.calls.length ).toBe( 3 );
} );
