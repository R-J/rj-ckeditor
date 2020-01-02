var CKEditors = [];
var editorCounter = 0;

/**
 * Listen to clear event and clear editor if it is the initial editor.
 */
$( document ).on( 'clearCommentForm', function( e ) {
    const bodyBox = e.target.querySelector('.BodyBox.js-bodybox');
    const editorId = bodyBox.getAttribute( 'data-ckeditor-id' );
    if ( editorId == 0 ) {
        CKEditors[ editorId ].setData( '' );
    }
});

/**
 * Ensure there is a BodyBox before trying to init an editor.
 */
$( document ).on( 'contentLoad', function( e ) {
    const el = $( '.BodyBox,.js-bodybox', e.target )[0];
    if ( el === undefined ) {
        return;
    }
    const editorId = el.getAttribute( 'data-ckeditor-id' );
    if ( editorId !== null ) {
        return;
    }
    const editor = createEditor( el );
});

/**
 * Replace Vanillas BodyBox with CKEditor
 */
function createEditor( el ) {
    const container = el;
    const bodyBox = container;
    // Add ID to be able to distinguish editors later on.
    bodyBox.setAttribute( 'data-ckeditor-id', editorCounter );
    return ClassicEditor
        .create( container, {
            language: 'de'
        })
        .then( editor => {
            // Push editor to global array.
            CKEditors[ editorCounter ] = editor;
            editorCounter++;
            editor.setData( bodyBox.value );
            editor.model.document.on( 'change:data', () => {
                bodyBox.value = editor.getData();
            } );
        } )
        .catch( error => {
            console.error( error );
        } );
}


/*
Upload:
POST to https://open.vanillaforums.com/api/v2/media
form-data;
name="file";
filename="...";
 */