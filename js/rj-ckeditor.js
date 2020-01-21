const CKEditors = [];
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
    // Add class to enhance html preview for keystone.
    container.parentElement.classList.add( 'userContent' );
    // Add ID to be able to distinguish editors later on.
    bodyBox.setAttribute( 'data-ckeditor-id', editorCounter );
    return ClassicEditor
        .create( container, {
            language: 'de',
            vanillaUpload: {
                // The URL the images are uploaded to.
                uploadUrl: gdn.url('/api/v2/media'),
                formFields: {
                    transientKey: gdn.definition('TransientKey')
                }
            },
            image: {
                upload: {
                    types: gdn.definition('AllowedFileExtensions')
                }
            },
            mention: {
                feeds: [
                    {
                        marker: '@',
                        feed: getMentionedUsers,
                        // itemRenderer: customMentionedUserRenderer,
                        minimumCharacters: 2
                    },
                    {
                        marker: '#',
                        feed: getTags,
                        minimumCharacters: 2
                    }
                ]
            }
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

/**
 * Must return an array with a field "id" that is the user name prefixed with '@'.
 *
 * Sort order can be configured through config.php. Sort values must be passed
 * as definitions so that they can be used here.
 *
 * /api/v2/users/by-names/?name=r%2A&order=mention&limit=50
 * @param  {[type]} queryText [description]
 * @return {[type]}           [description]
 */
function getMentionedUsers( queryText ) {
    return new Promise( resolve => {
        setTimeout( () => {
            const url = gdn.url( '/plugin/ckeditor/mention/?name=' + queryText );
            const jqxhr = $.get( url, function( data ) {
                resolve( data );
            });
        }, 100 );
    } );
}

/**
 * Currently not needed.
 *
 * Could be used to show avatars in mention list.
 *
 * @param  {[type]} item [description]
 * @return {[type]}      [description]
 */
function customMentionedUserRenderer( item ) {
    const itemElement = document.createElement( 'span' );

    itemElement.classList.add( 'custom-item' );
    itemElement.id = `mention-list-item-id-${ item.id }`;
    itemElement.textContent = `User: ${ item.name } `;

    return itemElement;
}


function getTags( queryText ) {
    return new Promise( resolve => {
        setTimeout( () => {
            const jqxhr = $.get( gdn.url( '/plugin/ckeditor/tag/?name=' + queryText ), function( data ) {
                items = data.map(data => '#' + data.FullName);
                resolve( data );
            });
        }, 100 );
    } );

}
