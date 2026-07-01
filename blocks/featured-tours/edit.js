/**
 * Editor component for kwawingu/featured-tours.
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
				<PanelBody title={ __( 'Featured Tours', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Heading', 'kwawingu-tours' ) }
						value={ attributes.heading || '' }
						onChange={ ( value ) => setAttributes( { heading: value } ) }
					/>
					<RangeControl
						label={ __( 'Number of tours', 'kwawingu-tours' ) }
						value={ attributes.limit }
						onChange={ ( value ) => setAttributes( { limit: value } ) }
						min={ 1 }
						max={ 12 }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
