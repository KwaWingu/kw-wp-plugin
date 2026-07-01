/**
 * Editor component for kwawingu/booking.
 * Server-rendered preview + Inspector controls for the block attributes.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Booking', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Tour slug', 'kwawingu-tours' ) }
						value={ attributes.tourSlug || '' }
						onChange={ ( value ) => setAttributes( { tourSlug: value } ) }
						help={ __( 'Leave blank to use the current tour', 'kwawingu-tours' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
