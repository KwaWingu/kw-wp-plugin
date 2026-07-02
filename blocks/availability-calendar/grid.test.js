const { buildMonthGrid } = require( './grid' );

describe( 'buildMonthGrid', () => {
	it( 'lays out a 6x7 grid with correct leading padding', () => {
		// July 2026: 1 Jul is a Wednesday (day index 3).
		const grid = buildMonthGrid( [], 2026, 6 );
		expect( grid.year ).toBe( 2026 );
		expect( grid.month ).toBe( 6 );
		expect( grid.weeks ).toHaveLength( 6 );
		expect( grid.weeks[ 0 ] ).toHaveLength( 7 );
		// First three cells (Sun,Mon,Tue) are padding, the 4th is the 1st.
		expect( grid.weeks[ 0 ][ 0 ] ).toBeNull();
		expect( grid.weeks[ 0 ][ 2 ] ).toBeNull();
		expect( grid.weeks[ 0 ][ 3 ] ).toEqual(
			expect.objectContaining( { day: 1, iso: '2026-07-01' } )
		);
	} );

	it( 'buckets departures onto their day cell', () => {
		const deps = [
			{ id: 'D1', date: '2026-07-01', availableSeats: 4, status: 'open' },
			{ id: 'D2', departureDate: '2026-07-15', availableSeats: 0, status: 'soldout' },
			{ id: 'D3', date: '2026-08-01', availableSeats: 9 }, // other month — ignored
		];
		const grid = buildMonthGrid( deps, 2026, 6 );
		expect( grid.weeks[ 0 ][ 3 ].departures ).toHaveLength( 1 );
		expect( grid.weeks[ 0 ][ 3 ].departures[ 0 ].id ).toBe( 'D1' );
		// 15 Jul 2026 is a Wednesday in the 3rd week.
		const cell15 = grid.weeks.flat().find( ( c ) => c && c.day === 15 );
		expect( cell15.departures[ 0 ].id ).toBe( 'D2' );
		// No cell holds the August departure.
		const hasAug = grid.weeks.flat().some( ( c ) => c && c.departures.some( ( d ) => d.id === 'D3' ) );
		expect( hasAug ).toBe( false );
	} );
} );
