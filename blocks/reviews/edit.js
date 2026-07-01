/**
 * Editor component for kwawingu/reviews.
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
				<PanelBody title={ __( 'Reviews', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Tour Post ID', 'kwawingu-tours' ) }
						type="number"
						value={ attributes.postId }
						onChange={ ( value ) => setAttributes( { postId: parseInt( value, 10 ) || 0 } ) }
						help={ __( '0 = current tour', 'kwawingu-tours' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
