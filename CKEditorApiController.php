<?php

namespace RJPlugins;

use AbstractApiController;
use Gdn;
use MediaApiController;
use MediaModel;
use Vanilla\ImageResizer;
use RJPlugins\CKEditorPlugin;
use Garden\Web\Data;

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
        // TODO: Move resizing after comment/discussion has been posted!
        // Wichtich...
        // 
        $container = Gdn::getContainer();

        // Default API upload.
        $mediaApiController = $container->get(MediaApiController::class);
        $result = $mediaApiController->post($body);

        // Fetch Media to get the path.
        $mediaModel = $container->get(MediaModel::class);
        $mediaPath = $mediaModel->getID($result['mediaID'])->Path;

        // Prepare resizing.
        $sourceFile = PATH_UPLOADS . '/' . $mediaPath;
        $pathInfo = pathinfo($sourceFile);
        $thumbSize = Gdn::config('Garden.Thumbnail.Size');
        $thumbPath = $pathInfo['dirname'] . '/thumbs';

        // Ensure thumb path exists.
        if (!file_exists($thumbPath)) {
            mkdir($thumbPath);
        }
        // Resize image.
        $thumb = Gdn::getContainer()->get(ImageResizer::class)->resize(
            $sourceFile,
            $thumbPath . '/t_' . $pathInfo['basename'],
            [
                'width' => $thumbSize,
                'height' => $thumbSize,
                false
            ]
        );
        // Save thumb info to Media.
        $uploadPathLength = strlen(PATH_UPLOADS);
        $thumbMediaPath = substr($thumb['path'], $uploadPathLength + 1);
        $mediaModel->save([
            'MediaID' => $result['mediaID'],
            'ThumbWidth' => $thumb['width'],
            'ThumbHeight' => $thumb['height'],
            'ThumbPath' => $thumbMediaPath
        ]);

        // Return uploaded file result.
        return $result;

        /*
        stdClass Object
        (
            [url] => https://...de/uploads/584/DML1W5WXYZWU.jpg
            [name] => photo5247031627513702631.jpg
            [type] => image/jpeg
            [size] => 89678
            [width] => 1280
            [height] => 720
            [mediaID] => 25
            [dateInserted] => stdClass Object
                (
                    [date] => 2020-02-04 15:05:06.000000
                    [timezone_type] => 3
                    [timezone] => UTC
                )

            [insertUserID] => 2
            [foreignType] => embed
            [foreignID] => 2
        )
         */
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
