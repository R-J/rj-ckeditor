<?php

namespace RJPlugins;

use Gdn;
use Gdn_Plugin;

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
        $sender->addJsFile('ckeditor.js', 'plugins/rj-ckeditor');
        $sender->addJsFile('init.js', 'plugins/rj-ckeditor');
    }

    public function gdn_form_beforeBodyBox_handler($sender, $args) {
        // Check if Format is Html, return if it is not.
        $format = $args['Attributes']['Format'] ?? Gdn_Format::defaultFormat();
        echo '<div class="container-ckeditor"></div>';
        $args['BodyBox'] = str_replace(
            '<div class="',
            '<div class="Hidden ',
            $args['BodyBox']
        );
    }
}