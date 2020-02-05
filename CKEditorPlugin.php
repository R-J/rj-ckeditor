<?php

namespace RJPlugins;

use Gdn_Plugin;
use Gdn;
use Gdn_Format;
use UsersApiController;
use Garden\Web\Data as Data;
use Vanilla\Formatting\Html\HtmlSanitizer;
use Vanilla\EmbeddedContent\LegacyEmbedReplacer\VanillaHtmlFormatter as VanillaHtmlFormatter;
use HtmlFormatter;
use CKEditorHtmlFormatter;
use Garden\Container\Container as Container;
use Vanilla\Formatting\FormatService;
use Vanilla\Formatting\Formats\HtmlFormat as HtmlFormat;
use MediaModel;
use Vanilla\ImageResizer;


// use RJPlugins\CKEditorHtmlFormat;


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
     *  Create tables and/or new columns.
     *
     *  @return void.
     */
    public function structure() {
        Gdn::config()->saveToConfig('Garden.InputFormatter', 'Html');
        Gdn::config()->saveToConfig('Garden.MobileInputFormatter', 'Html');
    }


    public function base_render_before($sender) {
        $sender->addCssFile('rj-ckeditor.css', 'plugins/rj-ckeditor');
        $sender->addJsFile('ckeditor.js', 'plugins/rj-ckeditor');
        $sender->addJsFile('rj-ckeditor.js', 'plugins/rj-ckeditor');
        $sender->addDefinition(
            'AllowedFileExtensions',
            Gdn::config('Garden.Upload.AllowedFileExtensions', [])
        );
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
    }

    public function vanillaController_cke_create($sender) {
        $args['CommentID'] = 999;
        $args['FormPostValues']['MediaIDs'] = '31,32,33,34,35,36,37';
        $result = $this->commentModel_afterSaveComment_handler($sender, $args);
        decho($result);
    }

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

    private function updateMediaTable($foreignTable, $foreignID, $mediaIDs) {
        $userID = Gdn::session()->UserID;
        $mediaModel = Gdn::getContainer()->get(MediaModel::class);
        $media = $mediaModel->getWhere([
            'MediaID' => $mediaIDs,
            'InsertUserID' => $userID,
            'ForeignID' => $userID,
            'ForeignTable' => 'embed'
        ])->resultArray();

        $thumbSize = Gdn::config('Garden.Thumbnail.Size');
        $imageResizer = Gdn::getContainer()->get(ImageResizer::class);
        $sql = Gdn::sql();
        $tableName = $sql->Database->DatabasePrefix.'Media';
        $query = '';
        foreach ($media as $key => $mediaItem) {
            if (!$mediaItem['ThumbPath']) {
                $media[$key] = $this->attachThumbInfo($mediaItem, $thumbSize, $imageResizer);
            }
            $media[$key]['ForeignID'] = $foreignID;
            $media[$key]['ForeignTable'] = $foreignTable;
            $mediaModel->update(
                $media[$key],
                ['MediaID' => $mediaItem['MediaID']]
            );
        }
    }

    public function discussionModel_afterSaveComment_handler($sender, $args) {
        // Ensure there is anything to do at all.
        if (($args['FormPostValues']['MediaIDs'] ?? false) == false) {
            return;
        }

        $this->updateMediaTable(
            'Discussion',
            $args['DiscussionID'],
            explode(',', $args['FormPostValues']['MediaIDs'])
        );
    }

    public function commentModel_afterSaveComment_handler($sender, $args) {
        // Ensure there is anything to do at all.
        if (($args['FormPostValues']['MediaIDs'] ?? false) == false) {
            return;
        }

        $this->updateMediaTable(
            'Comment',
            $args['CommentID'],
            explode(',', $args['FormPostValues']['MediaIDs'])
        );
    }

    public function conversationMessageModel_afterSave_handler($sender, $args) {
        // Ensure there is anything to do at all.
        if (($args['FormPostValues']['MediaIDs'] ?? false) == false) {
            return;
        }

        $this->updateMediaTable(
            'ConversationMessage',
            $args['Message']['MessageID'],
            explode(',', $args['FormPostValues']['MediaIDs'])
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

    /**
     * Dispatcher.
     *
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function pluginController_CKEditor_create($sender, $args) {
        switch ($args[0]) {
            case 'mention':
                $this->controller_mention($sender, $args);
                break;
            case 'tag':
                $this->controller_tag($sender, $args);
                break;
        }
    }

    /**
     * This endpoint can be used to add user pictures to mentions.
     *
     * @param [type] $sender [description]
     * @param [type] $args [description]
     * @return void.
     */
    public function controller_mention($sender, $args) {
        $query = Gdn::request()->get();
        $query['name'] = ($query['name'] ?? '').'*';
        $usersApiController = Gdn::getContainer()->get(UsersApiController::class);
        $data = $usersApiController->index_byNames($query);

        $nameUnique = Gdn::config('Garden.Registration.NameUnique');
        $users = array_map(
            function($user) {
                $user['id'] = '@'.$user['name'];
                $user['link'] = Gdn::request()->url(
                    '/profile/'.
                    ($nameUnique ? '' : $user['userID'].'/').
                    rawurlencode($user['name'])
                );
                return $user;
            },
            $data->getData()
        );
        $data->setData($users);
        $data->render();
    }

    /**
     * Endpoint for tag.
     *
     * @param PluginController $sender Instance of the calling class.
     * @param mixed $args Request arguments.
     *
     * @return void.
     */
    public function controller_tag($sender, $args) {
        $sender->permission('Garden.SignIn.Allow');

        $search = Gdn::request()->get('name', '').'%';

        $result = Gdn::sql()
            ->select('FullName', "concat('#', %s)", 'id')
            ->select('FullName', '', 'name')
            ->from('Tag')
            ->like('FullName', $search, 'right')
            ->orderBy('CountDiscussions', 'FullName')
            ->get()
            ->resultArray();

        $tags = array_map(
            function($tag) {
                $tag['link'] = Gdn::request()->url(
                    '/discussions/tagged/'.rawurlencode($tag['name'])
                );
                return $tag;
            },
            $result
        );

        $result = new Data();
        $result->setData($tags);
        $result->render();
    }
}
