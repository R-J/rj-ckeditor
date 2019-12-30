$( document ).on( 'contentLoad', function( e ) {
    const bodyBox = $( '.BodyBox,.js-bodybox', e.target )[0];
    // $( bodyBox ).addClass('Hidden');
    const container = $( '.container-ckeditor', e.target )[0];

    ClassicEditor
        .create( container )
        .then( editor => {
            console.log( editor );
            editor.setData( bodyBox.value );
            editor.model.document.on( 'change:data', () => {
                bodyBox.value = editor.getData();
            } );
        } )
        .catch( error => {
            console.error( error );
        } );
});
