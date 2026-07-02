/**
 * kwawingu/availability-calendar view: fetch departures, paint a month grid.
 */
( function () {
	'use strict';
	var MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
	var DOW = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

	function init( root ) {
		var tourSlug = root.getAttribute( 'data-tour' );
		var head = root.querySelector( '.kwt-availcal__head' );
		var gridEl = root.querySelector( '.kwt-availcal__grid' );
		var now = new Date();
		var year = now.getUTCFullYear();
		var month = now.getUTCMonth();
		var departures = [];

		window.kwtProxy.get( '/departures', { tourSlug: tourSlug } ).then( function ( res ) {
			departures = ( res && res.data ) || [];
			render();
		} ).catch( function () { render(); } );

		function header() {
			head.textContent = '';
			var prev = document.createElement( 'button' );
			prev.type = 'button';
			prev.className = 'kwt-availcal__nav';
			prev.textContent = '‹';
			prev.addEventListener( 'click', function () { shift( -1 ); } );
			var title = document.createElement( 'span' );
			title.className = 'kwt-availcal__title';
			title.textContent = MONTHS[ month ] + ' ' + year;
			var next = document.createElement( 'button' );
			next.type = 'button';
			next.className = 'kwt-availcal__nav';
			next.textContent = '›';
			next.addEventListener( 'click', function () { shift( 1 ); } );
			head.appendChild( prev );
			head.appendChild( title );
			head.appendChild( next );
		}

		function shift( delta ) {
			month += delta;
			if ( month < 0 ) { month = 11; year--; }
			if ( month > 11 ) { month = 0; year++; }
			render();
		}

		function render() {
			header();
			gridEl.textContent = '';
			var grid = window.kwtBuildMonthGrid( departures, year, month );
			var table = document.createElement( 'table' );
			table.className = 'kwt-availcal__table';
			var thead = document.createElement( 'tr' );
			DOW.forEach( function ( d ) {
				var th = document.createElement( 'th' );
				th.textContent = d;
				thead.appendChild( th );
			} );
			table.appendChild( thead );
			grid.weeks.forEach( function ( week ) {
				var tr = document.createElement( 'tr' );
				week.forEach( function ( cell ) {
					var td = document.createElement( 'td' );
					if ( cell ) {
						var dayEl = document.createElement( 'span' );
						dayEl.className = 'kwt-availcal__day';
						dayEl.textContent = String( cell.day );
						td.appendChild( dayEl );
						if ( cell.departures.length ) {
							var seats = cell.departures[ 0 ].availableSeats;
							td.className = ( seats === 0 ) ? 'kwt-availcal__cell is-soldout' : 'kwt-availcal__cell is-open';
							var tag = document.createElement( 'span' );
							tag.className = 'kwt-availcal__seats';
							tag.textContent = ( seats === 0 ) ? window.kwtProxy.i18n.soldOut : ( seats != null ? seats + '' : '•' );
							td.appendChild( tag );
						}
					}
					tr.appendChild( td );
				} );
				table.appendChild( tr );
			} );
			gridEl.appendChild( table );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-availcal' ), init );
	} );
}() );
