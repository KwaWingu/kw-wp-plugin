/**
 * The calculator must show the party total the API names `grandTotal` — not
 * `perPersonTotal`, which the previous fallback chain picked up because the API
 * has never returned a `total` key (see CalculatorEstimate in the OpenAPI doc).
 */
const { calculatorTotal } = require( './view.js' );

describe( 'calculatorTotal', () => {
	test( 'shows grandTotal in the API currency, never perPersonTotal', () => {
		const out = calculatorTotal( { data: { grandTotal: 920000, perPersonTotal: 460000, currency: 'TZS' } } );
		expect( out ).toBe( 'TZS ' + ( 920000 ).toLocaleString() );
	} );

	test( 'accepts an unwrapped estimate and a legacy total key', () => {
		expect( calculatorTotal( { total: 5, currency: 'USD' } ) ).toBe( 'USD 5' );
	} );

	test( 'renders zero for an empty response', () => {
		expect( calculatorTotal( null ) ).toBe( 'TZS 0' );
	} );
} );
