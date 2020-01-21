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

    public function gdn_form_beforeBodyBox_handler($sender, $args) {
        // Add Format to form so it can be checked by js.
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
