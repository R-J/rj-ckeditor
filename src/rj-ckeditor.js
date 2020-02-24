import ClassicEditor from './ckeditor.js';
import css from './css/rj-ckeditor.css';

// Track created editors.
const CKEditors = [];
var editorCounter = 0;
var users = [];

/**
 * Listen to clear event and clear editor if it is the initial editor.
 *
 * This is needed to clear a comment box after a comment has been posted.
 */
$( document ).on( 'clearCommentForm', function( e ) {
    const editorId = e.target.getElementsByClassName('BodyBox')[0].getAttribute( 'data-ckeditor-id' );
    if ( editorId == 0 ) {
        CKEditors[ editorId ].setData( '' );
    }
});

/**
 * Attach CKE to a BodyBox after page load.
 */
$( document ).on( 'contentLoad', function( e ) {
    const bodyBox = e.target.getElementsByClassName('BodyBox')[0];

    if ( checkPrerequisites( bodyBox ) ) {
        const editor = createEditor( bodyBox );
    }
});

/**
 * Check if editor can be attached to Vanillas BodyBox.
 *
 * Check if BodyBox exists and has format Html.
 */
function checkPrerequisites( bodyBox ) {
    // Break if no BodyBox exists.
    if ( bodyBox === undefined ) {
        return false;
    }
    // Break if format is not Html.
    if (bodyBox.getAttribute('Format') != 'Html') {
        return false;
    }
    // Break if BodyBox is already a ckeditor.
    const editorId = bodyBox.getAttribute( 'data-ckeditor-id' );
    if ( editorId !== null ) {
        return false;
    }
    return true;
}


/**
 * Create CKE at the given element.
 */
function createEditor( el ) {
    const container = el;
    const bodyBox = container;
    // Add ID to be able to distinguish editors later on.
    bodyBox.setAttribute( 'data-ckeditor-id', editorCounter );
    return ClassicEditor
        .create( container, {
            language: 'de',
            vanillaUpload: {
                // The URL the images are uploaded to.
                uploadUrl: gdn.url('/api/v2/media'),
                // uploadUrl: gdn.url('/api/v2/ckeditor/upload'),
                formFields: {
                    transientKey: gdn.definition('TransientKey')
                },
                parentElement: container
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
                    /*
                    {
                        marker: ':',
                        feed: getEmojis,
                        minimumCharacters: 2
                    }
                    */
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
            editor.ui.view.editable.element.classList.add('userContent');
        } )
        .catch( error => {
            console.error( error );
        } );
}

/**
 * Must return an array where the name is prefixed with '@'
 * @param  {[type]} queryText [description]
 * @return {[type]}           [description]
 */
function getMentionedUsers( queryText ) {
    return new Promise( resolve => {
        setTimeout( () => {
            const jqxhr = $.get( gdn.url( '/user/tagsearch/' + queryText ), function( data ) {
                const items = data.map(data => '@' + data.name);
                resolve( items );
            });
        }, 100 );
    } );
}

/**
 * Currently not in us.
 *
 * Could be used to show e.g. avatars in mention list.
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
