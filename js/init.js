document.addEventListener( 'X-ClearCommentForm', function( e ){
    clearInput( e.target );
}, false);

function clearInput( el ) {
    const ckeditor = el.querySelector( '.container-editor' );
}

$( document ).on( 'contentLoad', function( e ) {
    replaceEditor( e );
});

function replaceEditor( e ) {
    const container = $( '.BodyBox,.js-bodybox', e.target )[0];
    const bodyBox = container;
    ClassicEditor
        .create( container )
        .then( editor => {
            editor.setData( bodyBox.value );
            editor.model.document.on( 'change:data', () => {
                bodyBox.value = editor.getData();
            } );
        } )
        .catch( error => {
            console.error( error );
        } );
}