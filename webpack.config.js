/**
 * Custom webpack config: build one editor bundle per block from
 * blocks/<block>/index.js → build/<block>/index.js (+ index.asset.php),
 * keeping the block source colocated in blocks/ (not the default src/).
 *
 * Extends the @wordpress/scripts default so wp.* imports are externalized
 * and a .asset.php dependency manifest is emitted for each entry.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const fs = require( 'fs' );

const BLOCKS = [
	'tours-grid',
	'tour-detail',
	'featured-tours',
	'book-button',
	'reviews',
	'destinations-grid',
	'search',
	'calculator',
	'booking',
	'gallery',
];

const entry = {};
BLOCKS.forEach( function ( block ) {
	const file = path.resolve( __dirname, 'blocks', block, 'index.js' );
	if ( fs.existsSync( file ) ) {
		entry[ block + '/index' ] = file;
	}
} );

module.exports = {
	...defaultConfig,
	entry,
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
