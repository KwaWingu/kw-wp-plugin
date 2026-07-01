/**
 * Editor component for kwawingu/tours-grid.
 * Server-rendered preview + Inspector controls for the block attributes.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Tours Grid', 'kwawingu-tours' ) }>
					<RangeControl
						label={ __( 'Number of tours', 'kwawingu-tours' ) }
						value={ attributes.limit }
						onChange={ ( value ) => setAttributes( { limit: value } ) }
						min={ 1 }
						max={ 48 }
					/>
					<TextControl
						label={ __( 'Filter by type (slug)', 'kwawingu-tours' ) }
						value={ attributes.type || '' }
						onChange={ ( value ) => setAttributes( { type: value } ) }
						help={ __( 'e.g. safari, trekking — leave blank for all.', 'kwawingu-tours' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
