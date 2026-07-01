/**
 * Editor component for kwawingu/destinations-grid.
 * Server-rendered preview + Inspector controls for the block attributes.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Destinations', 'kwawingu-tours' ) }>
					<RangeControl
						label={ __( 'Number of destinations', 'kwawingu-tours' ) }
						value={ attributes.limit }
						onChange={ ( value ) => setAttributes( { limit: value } ) }
						min={ 1 }
						max={ 48 }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
