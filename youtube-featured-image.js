"use strict"

const el = wp.element.createElement;
const withState = wp.compose.withState;
const withSelect = wp.data.withSelect;
const withDispatch = wp.data.withDispatch;
const TextControl = wp.components.TextControl;
 



wp.hooks.addFilter(
    'editor.PostFeaturedImage',
    'enchance-featured-image/disable-featured-image-control',
    wrapPostFeaturedImage
);

function wrapPostFeaturedImage( OriginalComponent ) {
    return function( props ) {
        return (
            el(
                wp.element.Fragment,
                {},
                el(
                    OriginalComponent,
                    props
                ),
                el( YouTube_URL,
						{
							metaKey: 'ai_youtube_featured_url',
							title : 'YouTube url',
						}
					),

            )
        );
    }
}



const YouTube_URL = wp.compose.compose(
	withDispatch( function( dispatch, props ) {
		return {
			setMetaValue: function( metaValue ) {
				dispatch( 'core/editor' ).editPost(
					{ meta: { [ props.metaKey ]: metaValue } }
				);
			}
		}
	} ),
	withSelect( function( select, props ) {
		return {
			metaValue: select( 'core/editor' ).getEditedPostAttribute( 'meta' )[ props.metaKey ],
		}
	} ) )( function( props ) {
		return el( TextControl, {
			label: props.title,
			value: props.metaValue,
			onChange: function( content ) {
				props.setMetaValue( content );
			},
		});
	}
);