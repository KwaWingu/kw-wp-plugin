/**
 * Regression tests for the on-site booking payload — the exact field mapping
 * that was wrong in 1.0 and fixed in 1.1. Guards against a regression to the
 * old {customer, pax} shape.
 */
const {
	buildBookingPayload,
	readBookingRef,
	readPortalUrl,
	idemKey,
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
} );
