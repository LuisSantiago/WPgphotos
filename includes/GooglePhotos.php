<?php

use Google\ApiCore\ApiException;
use Google\Photos\Library\V1\MediaItemResult;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;

if (!defined('ABSPATH')) {
    exit;
}

class GooglePhotos
{
    private const GOOGLE_PHOTOS_META_KEY = 'google_photo';
    private const CUSTOM_POST_TYPE = 'desaparecido';

    private ?string $uploadedUrl = null;
    private ?WP_Post $post = null;

    public function init()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueScriptsAndStyles']);
        add_action('post_updated', [$this, 'postUpdated'], 15, 3);
        add_filter('update_post_metadata', [$this, 'uploadImageWhenMetadataIsUpdated'], 1, 4);
    }

    public function uploadImageWhenMetadataIsUpdated($check, $object_id, $meta_key, $meta_value)
    {
        if ($meta_key === '_thumbnail_id' && $this->post) {
            if ($this->post->post_type != self::CUSTOM_POST_TYPE) {
                googlePhotosLog("El post es del tipo {$this->post->post_type}, para subir imágenes debe ser del tipo " . self::CUSTOM_POST_TYPE);
                return $check;
            };
            $changedImage = wp_get_attachment_image_url($meta_value, 'large');
            $this->uploadedUrl = $changedImage;
            $this->uploadGooglePhotoOnPostUpdated($this->post->ID, null, $this->post, $changedImage);
        }

        return $check;
    }

    public function enqueueScriptsAndStyles($hook): void
    {
        if (in_array($hook, [
            'toplevel_page_google_photos_options',
            'configuracion_page_google_photos_library',
            'configuracion_page_google_photos_logs',
        ])) {
            wp_enqueue_style('google-photos', plugin_dir_url(__FILE__) . 'assets/google-photos.min.css');
            wp_enqueue_script('google-photos', plugin_dir_url(__FILE__) . 'assets/google-photos.js', ['jquery']);
        }
    }

    public function postUpdated($postId, $postAfter, $postBefore): void
    {
        if (wp_is_post_revision($postId)) return;
        if (wp_is_post_autosave($postId)) return;
        if ($postAfter->post_type != self::CUSTOM_POST_TYPE) {
            googlePhotosLog("El post es del tipo $postAfter->post_type, para subir imágenes debe ser del tipo " . self::CUSTOM_POST_TYPE);
            return;
        }

        $googlePhotoMeta = get_post_meta($postId, self::GOOGLE_PHOTOS_META_KEY, true);
        if (empty($googlePhotoMeta)) {
            $googlePhotoMeta = null;
        }

        if ($postAfter->post_status === 'publish') {
            $this->uploadGooglePhotoOnPostUpdated($postId, $googlePhotoMeta, $postAfter);
        } else {
            $this->removeFromGooglePhotoOnPostDeleted($postId, $googlePhotoMeta, $postAfter);
        }
    }

    public function uploadGooglePhotoOnPostUpdated(int $postId, ?array $currentGooglePhoto, WP_Post $post): void
    {
        $mediaUrl = $this->uploadedUrl ?? get_the_post_thumbnail_url($postId, 'large');
        if (!$mediaUrl) {
            googlePhotosLog("El post $post->post_title se acaba de crear y no tiene imagen asociada todavía");
            $this->post = $post;
            return;
        }

        $library = GooglePhotosWrapper::getPhotosLibrary();
        if (!$library) {
            return;
        }

        if ($currentGooglePhoto) {
            $this->removeFromGooglePhotoOnPostDeleted($postId, $currentGooglePhoto, $post);
        }

        googlePhotosLog("Actualizado el post $post->post_title. Subiendo imagen $mediaUrl");

        try {
            $uploadToken = $library->upload(file_get_contents($mediaUrl));
            $newMediaItems[] = PhotosLibraryResourceFactory::newMediaItemWithDescriptionAndFileName(
                $uploadToken, $post->post_title, $post->post_name,
            );

            $returned = $library->batchCreateMediaItems($newMediaItems,
                ['albumId' => GooglePhotosOptions::getAlbumId()]
            );

            /** @var MediaItemResult $item */
            foreach ($returned->getNewMediaItemResults()->getIterator() as $item) {
                $uploadedItemId = $item->getMediaItem()->getId();
                $url = $item->getMediaItem()->getProductUrl();
                break;
            }
            $meta = [
                'id' => $uploadedItemId,
                'url' => $url,
                'wpUrl' => $mediaUrl,
            ];

            update_post_meta($postId, self::GOOGLE_PHOTOS_META_KEY, $meta);
            googlePhotosLog("Subida imagen del post {$post->post_name} a Google Photos");
        } catch (ApiException $e) {
            googlePhotosLog("Error subiendo la imagen del post {$post->post_name} a Google Photos");
            googlePhotosLog($e->getMessage(), true);
        }
    }

    public function removeFromGooglePhotoOnPostDeleted(int $postId, ?array $googlePhoto, WP_Post $post): void
    {
        if (!isset($googlePhoto['id'])) {
            googlePhotosLog("no se obtiene id");
            return;
        }

        $googlePhotoId = $googlePhoto['id'];
        googlePhotosLog('Intenta borrar la foto del post : ' . $post->post_name);
        if (!$googlePhotoId) {
            return;
        }

        $library = GooglePhotosWrapper::getPhotosLibrary();
        if (!$library) {
            return;
        }

        try {
            googlePhotosLog('Borra la foto del post: ' . $post->post_name);
            $library->batchRemoveMediaItemsFromAlbum([$googlePhotoId], GooglePhotosOptions::getAlbumId());
            delete_post_meta($postId, self::GOOGLE_PHOTOS_META_KEY);
        } catch (ApiException $e) {
            googlePhotosLog("Ha habido un error borrando la foto $googlePhotoId de Google Photos", true);
            googlePhotosLog($e->getMessage(), true);
        }
    }
}


