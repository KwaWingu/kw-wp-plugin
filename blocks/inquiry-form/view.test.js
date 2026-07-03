/**
 * Unit tests for the inquiry form payload builder (honeypot + field mapping).
 */
const { buildInquiryPayload } = require( './view' );

function makeForm( overrides ) {
	var fields = Object.assign(
		{
			name: { value: 'Jane Doe' },
			email: { value: 'jane@example.com' },
			adults: { value: '2' },
			children: { value: '0' },
			phone: { value: '' },
			date: { value: '' },
			message: { value: '' },
		},
		overrides
	);
	return {
		getAttribute: function () { return 'safari'; },
		querySelector: function ( selector ) {
			if ( selector === '[name="kwt_hp"]' ) {
				return fields.kwt_hp || { value: '' };
			}
			return null;
		},
		name: fields.name,
		email: fields.email,
		adults: fields.adults,
		children: fields.children,
		phone: fields.phone,
		date: fields.date,
		message: fields.message,
	};
}

describe( 'buildInquiryPayload', () => {
	it( 'returns null when the honeypot is filled', () => {
		var form = makeForm( { kwt_hp: { value: 'spam' } } );
		expect( buildInquiryPayload( form ) ).toBeNull();
	} );

	it( 'returns null when honeypot is whitespace', () => {
		var form = makeForm( { kwt_hp: { value: '   ' } } );
		// '   ' is non-empty so honeypot triggers
		expect( buildInquiryPayload( form ) ).toBeNull();
	} );

	it( 'maps required fields correctly', () => {
		var form = makeForm();
		var p = buildInquiryPayload( form );
		expect( p ).not.toBeNull();
		expect( p.name ).toBe( 'Jane Doe' );
		expect( p.email ).toBe( 'jane@example.com' );
		expect( p.adults ).toBe( 2 );
		expect( p.children ).toBe( 0 );
		expect( p.tourSlug ).toBe( 'safari' );
	} );

	it( 'omits phone/date/message when empty', () => {
		var form = makeForm();
		var p = buildInquiryPayload( form );
		expect( p.phone ).toBeUndefined();
		expect( p.date ).toBeUndefined();
		expect( p.message ).toBeUndefined();
	} );

	it( 'includes optional fields when provided', () => {
		var form = makeForm( {
			phone: { value: '255700000000' },
			date: { value: '2026-09-01' },
			message: { value: 'Hello' },
		} );
		var p = buildInquiryPayload( form );
		expect( p.phone ).toBe( '255700000000' );
		expect( p.date ).toBe( '2026-09-01' );
		expect( p.message ).toBe( 'Hello' );
	} );
} );
