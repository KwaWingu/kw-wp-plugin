/**
 * Editor component for kwawingu/gallery.
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
				<PanelBody title={ __( 'Gallery', 'kwawingu-tours' ) }>
					<TextControl
						label={ __( 'Tour Post ID', 'kwawingu-tours' ) }
						type="number"
						value={ attributes.postId }
						onChange={ ( value ) => setAttributes( { postId: parseInt( value, 10 ) || 0 } ) }
						help={ __( '0 = current tour', 'kwawingu-tours' ) }
					/>
					<RangeControl
						label={ __( 'Columns', 'kwawingu-tours' ) }
						value={ attributes.columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 6 }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	);
}
