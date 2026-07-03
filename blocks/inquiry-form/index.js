/**
 * Client (editor) registration for kwawingu/inquiry-form.
 * Front-end output is server-rendered via render.php; this only supplies the
 * editor edit component (save returns null — dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Inquiry Form', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Heading', 'kwawingu-tours' ) }
						value={ attributes.heading || '' }
						onChange={ ( value ) => setAttributes( { heading: value } ) }
					/>
					<TextControl
						label={ __( 'Tour slug (optional)', 'kwawingu-tours' ) }
						value={ attributes.tourSlug || '' }
						onChange={ ( value ) => setAttributes( { tourSlug: value } ) }
						help={ __( 'Pre-fill the tour for this inquiry. Leave blank to let the visitor indicate their interest.', 'kwawingu-tours' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
