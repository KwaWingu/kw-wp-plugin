/**
 * Pure month-grid builder for the availability calendar (Jest-testable).
 */
( function ( root ) {
	'use strict';

	function pad2( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	function depDate( d ) {
		return ( d && ( d.date || d.departureDate ) ) || '';
	}

	/**
	 * @param {Array} departures - each {id, date|departureDate, availableSeats?, status?}
	 * @param {number} year
	 * @param {number} month - 0-based (0 = January)
	 * @returns {{year:number, month:number, weeks:Array}}
	 */
	function buildMonthGrid( departures, year, month ) {
		var first = new Date( Date.UTC( year, month, 1 ) );
		var startDow = first.getUTCDay(); // 0 = Sunday
		var daysInMonth = new Date( Date.UTC( year, month + 1, 0 ) ).getUTCDate();

		// Bucket departures by ISO day within this month.
		var byIso = {};
		( departures || [] ).forEach( function ( d ) {
			var iso = depDate( d ).slice( 0, 10 );
			if ( ! iso ) { return; }
			if ( ! byIso[ iso ] ) { byIso[ iso ] = []; }
			byIso[ iso ].push( d );
		} );

		var weeks = [];
		var cellIndex = 0; // 0-based across the whole grid
		for ( var w = 0; w < 6; w++ ) {
			var row = [];
			for ( var dow = 0; dow < 7; dow++ ) {
				var day = cellIndex - startDow + 1;
				if ( day < 1 || day > daysInMonth ) {
					row.push( null );
				} else {
					var iso = year + '-' + pad2( month + 1 ) + '-' + pad2( day );
					row.push( { day: day, iso: iso, departures: byIso[ iso ] || [] } );
				}
				cellIndex++;
			}
			weeks.push( row );
		}
		return { year: year, month: month, weeks: weeks };
	}

	root.kwtBuildMonthGrid = buildMonthGrid;
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { buildMonthGrid: buildMonthGrid };
	}
}( typeof window !== 'undefined' ? window : this ) );
