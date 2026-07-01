/**
 * Editor component for kwawingu/search.
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
				<PanelBody title={ __( 'Search', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Placeholder text', 'kwawingu-tours' ) }
						value={ attributes.placeholder || '' }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
