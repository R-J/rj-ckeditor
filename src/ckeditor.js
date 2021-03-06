/**
 * @license Copyright (c) 2003-2020, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */

// The editor creator to use.
import ClassicEditorBase from '@ckeditor/ckeditor5-editor-classic/src/classiceditor';

import Essentials from '@ckeditor/ckeditor5-essentials/src/essentials';
import UploadAdapter from '@ckeditor/ckeditor5-adapter-ckfinder/src/uploadadapter';
import Autoformat from '@ckeditor/ckeditor5-autoformat/src/autoformat';
import Bold from '@ckeditor/ckeditor5-basic-styles/src/bold';
import Italic from '@ckeditor/ckeditor5-basic-styles/src/italic';
import Underline from '@ckeditor/ckeditor5-basic-styles/src/underline';
import Strikethrough from '@ckeditor/ckeditor5-basic-styles/src/strikethrough';
import Code from '@ckeditor/ckeditor5-basic-styles/src/code';
// import Subscript from '@ckeditor/ckeditor5-basic-styles/src/subscript';
// import Superscript from '@ckeditor/ckeditor5-basic-styles/src/superscript';
import BlockQuote from '@ckeditor/ckeditor5-block-quote/src/blockquote';
import CKFinder from '@ckeditor/ckeditor5-ckfinder/src/ckfinder';
// import EasyImage from '@ckeditor/ckeditor5-easy-image/src/easyimage';
import Heading from '@ckeditor/ckeditor5-heading/src/heading';
import Image from '@ckeditor/ckeditor5-image/src/image';
// import ImageCaption from '@ckeditor/ckeditor5-image/src/imagecaption';
// import ImageStyle from '@ckeditor/ckeditor5-image/src/imagestyle';
import ImageToolbar from '@ckeditor/ckeditor5-image/src/imagetoolbar';
import ImageUpload from '@ckeditor/ckeditor5-image/src/imageupload';
// import Indent from '@ckeditor/ckeditor5-indent/src/indent';
import Link from '@ckeditor/ckeditor5-link/src/link';
import List from '@ckeditor/ckeditor5-list/src/list';
import MediaEmbed from '@ckeditor/ckeditor5-media-embed/src/mediaembed';
import Paragraph from '@ckeditor/ckeditor5-paragraph/src/paragraph';
import PasteFromOffice from '@ckeditor/ckeditor5-paste-from-office/src/pastefromoffice';
import Table from '@ckeditor/ckeditor5-table/src/table';
import TableToolbar from '@ckeditor/ckeditor5-table/src/tabletoolbar';

import VanillaUploadAdapter from '../src/upload/vanillauploadadapter';
import Mention from '@ckeditor/ckeditor5-mention/src/mention';
// import CodeBlock from '@ckeditor/ckeditor5-code-block/src/codeblock';
import HorizontalLine from '@ckeditor/ckeditor5-horizontal-line/src/horizontalline';
import RemoveFormat from '@ckeditor/ckeditor5-remove-format/src/removeformat';
// import Alignment from '@ckeditor/ckeditor5-alignment/src/alignment';
// import ImageResize from '@ckeditor/ckeditor5-image/src/imageresize';

export default class ClassicEditor extends ClassicEditorBase {}

// Plugins to include in the build.
ClassicEditor.builtinPlugins = [
    Essentials,
    UploadAdapter,
    Autoformat,
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Code,
    // Subscript,
    // Superscript,
    BlockQuote,
    CKFinder,
    // EasyImage,
    Heading,
    Image,
    // ImageCaption,
    // ImageStyle,
    ImageToolbar,
    ImageUpload,
    // Indent,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    PasteFromOffice,
    Table,
    TableToolbar,
    VanillaUploadAdapter,
    Mention,
    // CodeBlock,
    HorizontalLine,
    RemoveFormat,
    // Alignment,
    // ImageResize
];

// Editor configuration.
ClassicEditor.defaultConfig = {
    toolbar: {
        items: [
            'heading',
            '|',
            'bold',
            'italic',
            'link',
            'bulletedList',
            'numberedList',
            '|',
            // 'indent', 'outdent', '|',
            'imageUpload',
            'blockQuote',
            'insertTable',
            'mediaEmbed',
            'undo',
            'redo'
        ]
    },
    image: {
        toolbar: [
            // 'imageStyle:full',
            // 'imageStyle:side',
            // '|',
            // 'imageTextAlternative'
        ]
    },
    table: {
        contentToolbar: [
            'tableColumn',
            'tableRow',
            'mergeTableCells'
        ]
    }
};

function MentionCustomization( editor ) {
    const emojiClass = 'ck-emoji';
    const mentionClass = 'ck-mention';

    // User mention
    editor.conversion.for( 'upcast' ).elementToAttribute( {
        view: {
            name: 'a',
            key: 'data-mention',
            classes: 'ck-mention',
            attributes: {
                href: true,
                'data-user-id': true
            }
        },
        model: {
            key: 'mention',
            value: viewItem => {
                const mentionAttribute = editor.plugins.get( 'Mention' ).toMentionAttribute( viewItem, {
                    link: viewItem.getAttribute( 'href' ),
                    userId: viewItem.getAttribute( 'data-user-id' )
                } );

                return mentionAttribute;
            }
        },
        converterPriority: 'high'
    } );
    /*
    // Emoji mention
    editor.conversion.for( 'upcast' ).elementToAttribute( {
        view: {
            name: 'a',
            key: 'data-emoji',
            classes: 'ck-emoji',
            attributes: {
                href: true
            }
        },
        model: {
            key: 'mention',
            value: viewItem => {
                const mentionAttribute = editor.plugins.get( 'Mention' ).toMentionAttribute( viewItem, {
                    link: viewItem.getAttribute( 'href' )
                } );

                return mentionAttribute;
            }
        },
        converterPriority: 'high'
    } );
    */

    // Downcast the model 'mention' text attribute to a view <a> element.
    editor.conversion.for( 'downcast' ).attributeToElement( {
        model: 'mention',
        view: ( modelAttributeValue, viewWriter ) => {
            // Do not convert empty attributes (lack of value means no mention).
            if ( !modelAttributeValue ) {
                return;
            }
            if ( modelAttributeValue.id == undefined ) {
                return;
            }
            switch ( modelAttributeValue.id.substr( 0, 1 ) ) {
                case'@':
                    return viewWriter.createAttributeElement( 'a', {
                        class: 'ck-mention',
                        'data-mention': modelAttributeValue.id,
                        'data-user-id': modelAttributeValue.userID,
                        'href': modelAttributeValue.link
                    } );
                    break;
                /*
                case ':':
                    return viewWriter.createAttributeElement( 'a', {
                        class: 'ck-emoji',
                        'data-emoji': modelAttributeValue.emojiID,
                        'href': modelAttributeValue.link
                    } );
                    break;
                */
            }
        },
        converterPriority: 'high'
    } );
}
