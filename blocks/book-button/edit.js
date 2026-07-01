/**
 * Editor component for kwawingu/book-button.
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
				<PanelBody title={ __( 'Book Button', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Label', 'kwawingu-tours' ) }
						value={ attributes.label || '' }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
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
