<?php

use Google\Auth\Credentials\UserRefreshCredentials;
use Google\Auth\OAuth2;
use Google\Photos\Library\V1\PhotosLibraryClient;
use Google\Photos\Library\V1\PhotosLibraryResourceFactory;
use Google\Photos\Types\Album;
use Psr\Http\Message\UriInterface;

if (!defined('ABSPATH')) {
    exit;
}

require_once GOOGLE_PHOTOS_PLUGIN_DIR . 'vendor/autoload.php';

class GooglePhotosWrapper
{
    public static function getOAuth(): ?OAuth2
    {
        if (!GooglePhotosOptions::hasOptions()) {
            return null;
        }

        return new OAuth2([
            'clientId' => GooglePhotosOptions::getClientId(),
            'clientSecret' => GooglePhotosOptions::getClientSecret(),
            'authorizationUri' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'redirectUri' => admin_url('admin.php?page=google_photos_options'),
            'tokenCredentialUri' => 'https://www.googleapis.com/oauth2/v4/token',
            'scope' => self::getScopes(),
        ]);
    }

    private static function getScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/photoslibrary',
            'https://www.googleapis.com/auth/photoslibrary.sharing',
        ];
    }

    public static function getAlbums(): array
    {
        $albums = [];

        /** @var Album $album */
        foreach (self::getPhotosLibrary()->listAlbums()->getIterator() as $album) {
            $title = $album->getIsWriteable() ? $album->getTitle() : 'Sin permiso: ' . $album->getTitle();
            $albums[] = ['id' => $album->getId(), 'title' => $title];
        }

        return $albums;
    }

    public static function createAlbum(string $albumName): void
    {
        $googlePhotosAlbum = PhotosLibraryResourceFactory::album($albumName);
        self::getPhotosLibrary()->createAlbum($googlePhotosAlbum);
    }

    public static function getPhotosLibrary(): ?PhotosLibraryClient
    {
        if (!GooglePhotosOptions::getConnected()) {
            return null;
        }

        self::refreshAccessToken();

        try {
            return new PhotosLibraryClient(['credentials' => self::getUserCredentials()]);
        } catch (Throwable $e) {
            delete_option('google_photos_access_token');
            googlePhotosLog('Error obteniendo el cliente de GooglePhotos');
        }

        return null;
    }

    public static function getConnectionUrl(): UriInterface
    {
        $oauth2 = self::getOAuth();
        return $oauth2->buildFullAuthorizationUri(['access_type' => 'offline']);
    }

    private static function refreshAccessToken(): void
    {
        if (!GooglePhotosOptions::getConnected()) {
            return;
        }

        $oauth2 = self::getOAuth();
        $oauth2->setRefreshToken(GooglePhotosOptions::getRefreshToken());
        if ($oauth2->isExpired()) {
            $authToken = $oauth2->fetchAuthToken();

            $accessToken = $authToken['access_token'];
            update_option('google_photos_access_token', $accessToken);
        }
    }

    private static function getUserCredentials(): UserRefreshCredentials
    {
        return new UserRefreshCredentials(
            self::getScopes(),
            [
                'client_id' => GooglePhotosOptions::getClientId(),
                'client_secret' => GooglePhotosOptions::getClientSecret(),
                'refresh_token' => GooglePhotosOptions::getRefreshToken(),
            ]
        );
    }
}