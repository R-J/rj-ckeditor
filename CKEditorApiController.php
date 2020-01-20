<?php

use Garden\Web\Data;

/*
use Garden\SafeCurl\Exception\InvalidURLException;
use Garden\Schema\Schema;
use Garden\Web\Exception\ClientException;
use Garden\Web\Exception\NotFoundException;
use Vanilla\ApiUtils;
use \Vanilla\EmbeddedContent\EmbedService;
use Vanilla\FeatureFlagHelper;
use Vanilla\ImageResizer;
use Vanilla\UploadedFile;
use Vanilla\UploadedFileSchema;
use \Vanilla\EmbeddedContent\AbstractEmbed;
*/

/**
 * Custom API Controller uploads with the CKEditor.
 */
class CKEditorApiController extends AbstractApiController {
    /**
     * Upload a file and store it in GDN_Media against the current user.
     *
     * It's a wrapper around the MediaApiControllers post method which is needed
     * since CKEditor requires special error messages. A CKEditor custom upload
     * adapter would be another/better option, I assume...
     *
     * @param array $body The request body.
     * @return array
     */
    public function post_upload(array $body) {
        // Todo: try Gdn::getContainer()->get(MediaApiController::class)
        $mediaApiController = new MediaApiController(
            Gdn::getContainer()->get(MediaModel::class),
            Gdn::getContainer()->get(\Vanilla\EmbeddedContent\EmbedService::class),
            Gdn::getContainer()->get(\Vanilla\ImageResizer::class),
            Gdn::getContainer()->get(Gdn_Configuration::class)
        );

        $mediaDummy = $mediaApiController->mediaByID(1);

        try {
            $body['file'] = $body['upload'];
            $media = $mediaApiController->post($body);
            return new Data([
                'uploaded' => true,
                'url' => $mediaDummy->Path
            ]);
        } catch (Exception $ex) {
            return new Data([
                'uploaded' => false,
                'message' => $ex->getMessage()
            ]);
        }
    }

    /**
     * @param array $query The query string.
     * @return Data
     */
    public function index(array $query) {
        return new Data([
            'status' => 'bereit'
        ]);
    }
}
