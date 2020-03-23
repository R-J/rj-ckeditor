<?php

namespace RJPlugins;

use Gdn_Plugin;
use Gdn;
use MediaModel;
use Vanilla\ImageResizer;

/*
use Gdn_Format;
use UsersApiController;
use Garden\Web\Data as Data;
use Vanilla\Formatting\Html\HtmlSanitizer;
use Vanilla\EmbeddedContent\LegacyEmbedReplacer\VanillaHtmlFormatter as VanillaHtmlFormatter;
use HtmlFormatter;
use Garden\Container\Container as Container;
use Vanilla\Formatting\FormatService;
use Vanilla\Formatting\Formats\HtmlFormat as HtmlFormat;
*/

class CKEditorPlugin extends Gdn_Plugin {
    /**
     *  Run on startup to init sane config settings and db changes.
     *
     *  @return void.
     */
    public function setup() {
        $this->structure();
    }

    /**
     *  Ensure posting format is Html.
     *
     *  @return void.
     */
    public function structure() {
        Gdn::config()->saveToConfig('Garden.InputFormatter', 'Html');
        Gdn::config()->saveToConfig('Garden.MobileInputFormatter', 'Html');
    }

    /**
     * Pass some configurations to JS.
     *
     * @param Gdn_Controller $sender Instance of the calling class.
     *
     * @return void.
     */
    public function base_render_before($sender) {
        $toolbar = explode(
            ',',
            Gdn::config(
                'Plugins.CKEditor.Toolbar',
                'bold,italic,underline,strikethrough,code,subscript,superscript,removeFormat,|,'.
                'link,imageUpload,|,'.
                'blockQuote,codeBlock,heading,bulletedList,numberedList,alignment,|,'.
                'horizontalLine,insertTable,mediaEmbed,|,'.
                'undo,redo'
            )
        );
        $sender->addDefinition('CKEditorToolbar', $toolbar);
        $sender->addDefinition('CKEditorLanguage', Gdn::locale()->language());
        $sender->addDefinition(
            'AllowedFileExtensions',
            Gdn::config('Garden.Upload.AllowedFileExtensions', [])
        );
    }

    /**
     * Add CSS file.
     *
     * @param AssetModel $sender Instance of the calling class.
     *
     * @return void.
     */
    public function assetModel_styleCss_handler($sender) {
         $sender->addCssFile('rj-ckeditor.css', 'plugins/rj-ckeditor');
    }

    /**
     * Inject CKEditor JS at the end of the page.
     *
     * @param Gdn_controller $sender Instance of the calling class.
     *
     * @return void.
     */
    public function base_afterBody_handler($sender) {
        echo '<script src="'.asset('/plugins/rj-ckeditor/js/rj-ckeditor.js').'"></script>';
    }

    /**
     * Create thumbnail and return info about thumb created.
     *
     * @param array $media Media item.
     * @param int $size The size the thumbnail should be created.
     * @param ImageResizer $imageResizer
     *
     * @return mixed Width, height, path of the thumb.
     */
    private function attachThumbInfo($media, $size, $imageResizer) {
        $sourceFile = PATH_UPLOADS . '/' . $media['Path'];
        $pathInfo = pathinfo($sourceFile);
        $thumbPath = $pathInfo['dirname'] . '/thumbs';
        // Ensure thumb path exists.
        if (!file_exists($thumbPath)) {
            mkdir($thumbPath);
        }
        // Resize image.
        $thumb = $imageResizer->resize(
            $sourceFile,
            $thumbPath . '/t_' . $pathInfo['basename'],
            ['width' => $size, 'height' => $size, false]
        );
        // Save thumb info to Media.
        $uploadPathLength = strlen(PATH_UPLOADS);
        $thumbMediaPath = substr($thumb['path'], $uploadPathLength + 1);
        $media['ThumbWidth'] = $thumb['width'];
        $media['ThumbHeight'] = $thumb['height'];
        $media['ThumbPath'] = $thumbMediaPath;
        return $media;
    }

    /**
     * Update media table info concerning foreign id and thumbnail info.
     *
     * @param string $foreignTable The name of the foreign type table
     * @param int $foreignID The id of the foreign type.
     * @param arrray $mediaIDs Array of MediaIDs contained in the foreign type.
     *
     * @return void.
     */
    private function updateMediaTable($foreignTable, $foreignID, $mediaIDs) {
        // Return if post contains no media.
        if (count($mediaIDs) == 0) {
            return;
        }
        $userID = Gdn::session()->UserID;
        $mediaModel = Gdn::getContainer()->get(MediaModel::class);
        // Fetch info from Media table for all IDs. Must match
        // session user = insert user!
        $media = $mediaModel->getWhere([
            'MediaID' => $mediaIDs,
            'InsertUserID' => $userID,
            'ForeignID' => $userID,
            'ForeignTable' => 'embed'
        ])->resultArray();

        // Create thumbnail for uploads.
        $thumbSize = Gdn::config('Garden.Thumbnail.Size');
        $imageResizer = Gdn::getContainer()->get(ImageResizer::class);
        foreach ($media as $key => $mediaItem) {
            // Attach info about thumbnail to media item.
            if (!$mediaItem['ThumbPath']) {
                $mediaItem = $this->attachThumbInfo($mediaItem, $thumbSize, $imageResizer);
            }
            $mediaItem['ForeignID'] = $foreignID;
            $mediaItem['ForeignTable'] = $foreignTable;
            // Write back updated info to Media table.
            $mediaModel->update(
                $mediaItem,
                ['MediaID' => $mediaItem['MediaID']]
            );
        }
    }

    /**
     * Update media table if discussion contains media.
     *
     * @param DiscussionModel $sender Instance of the calling class.
     * @param mixed $args Event arguments.
     *
     * @return void.
     */
    public function discussionModel_afterSaveComment_handler($sender, $args) {
        $this->updateMediaTable(
            'Discussion',
            $args['DiscussionID'],
            explode(',', $args['FormPostValues']['MediaIDs'] ?? '')
        );
    }

    /**
     * Update media table if comment contains media.
     *
     * @param CommentModel $sender Instance of the calling class.
     * @param mixed $args Event arguments.
     *
     * @return void.
     */
    public function commentModel_afterSaveComment_handler($sender, $args) {
        $this->updateMediaTable(
            'Comment',
            $args['CommentID'],
            explode(',', $args['FormPostValues']['MediaIDs'] ?? '')
        );
    }

    /**
     * Update media table if conversation message contains media.
     *
     * @param ConversationMessageModel $sender Instance of the calling class.
     * @param mixed $args Event arguments.
     *
     * @return void.
     */
    public function conversationMessageModel_afterSave_handler($sender, $args) {
        $this->updateMediaTable(
            'ConversationMessage',
            $args['Message']->MessageID,
            explode(',', $args['FormPostValues']['MediaIDs'] ?? '')
        );
    }

    /**
     * Handle the container init event to register our own html formatter.
     *
     * CKEditor5 inserts a lot of CSS classes which are stripped out by the
     * VanillaHtmlFormatter.
     *
     * @param Container $dic
     */
    /*
    public function container_init(Container $dic) {
        return;
        $dic->rule(Vanilla\Formatting\FormatService::class)->addCall(
            'registerFormat',
            [
                'Html',
                new \Garden\Container\Reference(RJPlugins\CKEditorHtmlFormat::class)
            ]
        );
    }
    */
}
