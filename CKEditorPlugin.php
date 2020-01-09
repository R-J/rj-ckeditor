<?php

namespace RJPlugins;

use Gdn_Plugin;
use Gdn;
use Gdn_Format;
use UserModel;

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
        return;
        // No need to allow that to guests...
        $sender->permission('Garden.SignIn.Allow');

        $search = $args[1] ?? '';
        $search = trim(str_replace(['%', '_'], ['\%', '\_'], $search));

        list($order, $direction) = Gdn::getContainer()->get(UserModel::class)->getMentionsSort();

        $users = Gdn::sql()
            ->select('Name, UserID, Photo, Email')
            ->from('User')
            ->like('Name', $search, 'right')
            ->where('Deleted', 0)
            ->orderBy($order, $direction)
            ->limit($limit)
            ->get()
            ->resultArray();


        foreach ($users as $key => $user) {
            if (!$users['Photo']) {
                $users[$key]['Photo'] = userPhotoDefaultUrl($user);
            }
            unset($users[$key]['Email']);
            $users[$key]['name'] = '@'.$user['Name'];
        }
        header('Content-Type: application/json');
        echo json_encode($users);
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
