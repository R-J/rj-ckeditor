<?php

namespace RJPlugins;

use Gdn_Plugin;
use Gdn;
use Gdn_Format;
use UsersApiController;
use Garden\Web\Data as Data;

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
        // $Configuration['Garden']['InputFormatter'] = 'Rich'; // Html, BBCode, Markdown, Text, Rich
        // $Configuration['Garden']['MobileInputFormatter'] = 'Rich';
        Gdn::config()->saveToConfig('Garden.InputFormatter', 'Html');
    }


    public function base_render_before($sender) {
        $sender->addCssFile('rj-ckeditor.css', 'plugins/rj-ckeditor');
        $sender->addJsFile('ckeditor.js', 'plugins/rj-ckeditor');
        $sender->addJsFile('rj-ckeditor.js', 'plugins/rj-ckeditor');
        $sender->addDefinition(
            'AllowedFileExtensions',
            Gdn::config('Garden.Upload.AllowedFileExtensions', [])
        );
    }

    public function gdn_form_beforeBodyBox_handler($sender, $args) {
        // Check if Format is Html, return if it is not.
        $format = $args['Attributes']['Format'] ?? Gdn_Format::defaultFormat();
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
     * @param  [type] $sender [description]
     * @param  [type] $args   [description]
     * @return [type]         [description]
     */
    public function controller_mention($sender, $args) {
        $query = Gdn::request()->get();
        $query['name'] = ($query['name'] ?? '').'*';
        $usersApiController = GDN::getContainer()->get(UsersApiController::class);
        $data = $usersApiController->index_byNames($query);

        $users = array_map(
            function($user) {
                $user['id'] = '@'.$user['name'];
                return $user;
            },
            $data->getData()
        );

        $data->setData($users);
        $data->render();
    }

    public function controller_tag($sender, $args) {
        $sender->permission('Garden.SignIn.Allow');
        $tags = Gdn::sql()
            // ->select('FullName', "concat('#', %s)", 'name')
            ->select('FullName')
            ->from('Tag')
            ->like('FullName', $search, 'right')
            ->orderBy('CountDiscussions', 'FullName')
            ->get()
            ->resultArray();

        header('Content-Type: application/json');
        echo json_encode($tags);
    }
}
