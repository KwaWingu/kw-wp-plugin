/**
 * Client (editor) registration for kwawingu/search.
 * Front-end output is server-rendered via render.php; this only supplies the
 * editor edit component (save returns null — dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
