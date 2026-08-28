/**
 * Regression tests for the on-site booking payload — the exact field mapping
 * that was wrong in 1.0 and fixed in 1.1. Guards against a regression to the
 * old {customer, pax} shape.
 */
const {
	buildBookingPayload,
	readBookingRef,
	readPortalUrl,
	readPortalToken,
	bookingLookupRequest,
	paymentReceived,
	idemKey,
	quoteTotal,
} = require( './view' );

describe( 'buildBookingPayload', () => {
	it( 'maps form values to the real API field names (no customer/pax)', () => {
		const p = buildBookingPayload( {
			tourSlug: 'safari',
			adults: 2,
			children: 1,
			infants: 0,
			firstName: ' Jane ',
			lastName: 'Doe',
			email: 'j@x.com',
			phone: '255700',
			departureId: 'D1',
			idempotencyKey: 'k1',
		} );
		expect( p ).toEqual( {
			tourSlug: 'safari',
			adults: 2,
			children: 1,
			infants: 0,
			guestFirstName: 'Jane',
			guestLastName: 'Doe',
			guestEmail: 'j@x.com',
			guestPhone: '255700',
			idempotencyKey: 'k1',
			departureId: 'D1',
		} );
		expect( p.customer ).toBeUndefined();
		expect( p.pax ).toBeUndefined();
	} );

	it( 'omits departureId when empty and defaults adults to 1', () => {
		const p = buildBookingPayload( {
			tourSlug: 'x',
			firstName: 'A',
			lastName: 'B',
			email: 'e',
			phone: 'p',
		} );
		expect( p.departureId ).toBeUndefined();
		expect( p.adults ).toBe( 1 );
		expect( p.children ).toBe( 0 );
		expect( p.infants ).toBe( 0 );
	} );

	it( 'generates an idempotency key of at most 30 chars', () => {
		const p = buildBookingPayload( { tourSlug: 'x' } );
		expect( typeof p.idempotencyKey ).toBe( 'string' );
		expect( p.idempotencyKey.length ).toBeGreaterThan( 0 );
		expect( p.idempotencyKey.length ).toBeLessThanOrEqual( 30 );
	} );
} );

describe( 'idemKey', () => {
	it( 'is <= 30 chars', () => {
		expect( idemKey().length ).toBeLessThanOrEqual( 30 );
	} );
} );

describe( 'response readers', () => {
	it( 'reads the ref from booking.ref / bookingReference / res.ref', () => {
		expect( readBookingRef( { booking: { ref: 'R1' } } ) ).toBe( 'R1' );
		expect( readBookingRef( { data: { booking: { bookingReference: 'R2' } } } ) ).toBe( 'R2' );
		expect( readBookingRef( { ref: 'R3' } ) ).toBe( 'R3' );
		expect( readBookingRef( {} ) ).toBe( '' );
	} );

	it( 'reads the portal URL from res or booking', () => {
		expect( readPortalUrl( { portalUrl: 'https://p' } ) ).toBe( 'https://p' );
		expect( readPortalUrl( { data: { portalUrl: 'https://d' } } ) ).toBe( 'https://d' );
		expect( readPortalUrl( { booking: { portalUrl: 'https://b' } } ) ).toBe( 'https://b' );
		expect( readPortalUrl( {} ) ).toBe( '' );
	} );

	it( 'reads the portal token from BookingResult.portalToken (top level, data, or booking)', () => {
		expect( readPortalToken( { booking: { ref: 'R1' }, portalToken: 'tok-1' } ) ).toBe( 'tok-1' );
		expect( readPortalToken( { data: { booking: { ref: 'R1' }, portalToken: 'tok-2' } } ) ).toBe( 'tok-2' );
		expect( readPortalToken( { booking: { ref: 'R1', portalToken: 'tok-3' } } ) ).toBe( 'tok-3' );
		expect( readPortalToken( { booking: { ref: 'R1' } } ) ).toBe( '' );
		expect( readPortalToken( undefined ) ).toBe( '' );
	} );
} );

describe( 'bookingLookupRequest (post-booking status poll)', () => {
	it( 'sends the portal token as the X-Portal-Token header and never in the query string', () => {
		const r = bookingLookupRequest( 'KWG-1', 'tok-1', 'g@example.com' );
		expect( r.headers ).toEqual( { 'X-Portal-Token': 'tok-1' } );
		expect( r.params ).toEqual( { ref: 'KWG-1' } );
		expect( JSON.stringify( r.params ) ).not.toMatch( /tok-1|example\.com/ );
	} );

	it( 'falls back to the deprecated ?email= lookup only when no token was issued', () => {
		const r = bookingLookupRequest( 'KWG-1', '', 'g@example.com' );
		expect( r.headers ).toEqual( {} );
		expect( r.params ).toEqual( { ref: 'KWG-1', email: 'g@example.com' } );
	} );
} );

describe( 'quoteTotal', () => {
	it( 'reads totalAmount + currency from the Quote schema (never "TZS 0")', () => {
		expect( quoteTotal( { totalAmount: 4900000, baseAmount: 4900000, currency: 'TZS' } ) )
			.toBe( 'TZS ' + ( 4900000 ).toLocaleString() );
		expect( quoteTotal( { data: { totalAmount: 1200, currency: 'USD' } } ) ).toBe( 'USD ' + ( 1200 ).toLocaleString() );
	} );
} );

describe( 'paymentReceived (when the poll may stop)', () => {
	it( 'is false for a freshly created booking: CONFIRMED with the whole total still due', () => {
		expect( paymentReceived( { status: 'CONFIRMED', totalAmountMinor: 2450000, balanceAmountMinor: 2450000, totalAmount: 2450000, balanceAmount: 2450000 } ) ).toBe( false );
	} );

	it( 'is true once the balance drops below the total (deposit or full payment), on the exact minor units', () => {
		expect( paymentReceived( { status: 'CONFIRMED', totalAmountMinor: 2450000, balanceAmountMinor: 1450000 } ) ).toBe( true );
		expect( paymentReceived( { status: 'CONFIRMED', totalAmountMinor: 2450000, balanceAmountMinor: 0 } ) ).toBe( true );
	} );

	it( 'falls back to the decimal pair, then to a paid/completed status of any case', () => {
		expect( paymentReceived( { status: 'CONFIRMED', totalAmount: 100.5, balanceAmount: 50.25 } ) ).toBe( true );
		expect( paymentReceived( { status: 'COMPLETED' } ) ).toBe( true );
		expect( paymentReceived( { paymentStatus: 'paid' } ) ).toBe( true );
		expect( paymentReceived( { status: 'CONFIRMED' } ) ).toBe( false );
		expect( paymentReceived( undefined ) ).toBe( false );
	} );
} );
