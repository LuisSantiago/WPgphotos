<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once GOOGLE_PHOTOS_PLUGIN_DIR . 'includes/GooglePhotosWrapper.php';

class GooglePhotosOptions
{
    public function init(): void
    {
        error_reporting(E_ALL);
        add_action('admin_menu', [$this, 'googlePhotosUploaderRegisterOptionsPage']);
        add_action('admin_init', [$this, 'registerOptions']);
        add_action("wp_ajax_getAlbumsAjax", [$this, "getAlbumsAjax"]);
    }

    public static function hasOptions(): bool
    {
        return self::getClientId() && self::getClientSecret();
    }

    public static function getConnected(): ?string
    {
        return get_option('google_photos_connection')['google_photos_connected'] ?? null;
    }

    public static function getClientId(): ?string
    {
        return get_option('google_photos_options')['google_photos_client_id'] ?? null;
    }

    public static function getClientSecret(): ?string
    {
        return get_option('google_photos_options')['google_photos_client_secret'] ?? null;
    }

    public static function getAlbumId(): ?string
    {
        return get_option('google_photos_options')['google_photos_album_id'] ?? null;
    }

    public static function getRefreshToken(): ?string
    {
        return get_option('google_photos_connection')['google_photos_refresh_token'] ?? null;
    }

    public static function getAccessToken(): ?string
    {
        return get_option('google_photos_access_token') ?? null;
    }

    public static function getCode(): ?string
    {
        return get_option('google_photos_connection')['google_photos_code'] ?? null;
    }

    public static function isDebugMode(): ?string
    {
        return isset(get_option('google_photos_options')['google_photos_debug'])
            && (bool)get_option('google_photos_options')['google_photos_debug'];
    }

    public static function setCredentials(string $code): void
    {
        googlePhotosLog('Conectado a Google Photos');

        $oauth2 = GooglePhotosWrapper::getOAuth();
        $oauth2->setCode($code);
        $authToken = $oauth2->fetchAuthToken();

        googlePhotosLog(print_r($authToken, true));
        $accessToken = $authToken['access_token'];

        if (!isset($authToken['refresh_token'])) {
            echo '<div class="config_error">Ha ocurrido un error al obtener el token. Intenta revocar tu token <a href="https://myaccount.google.com/connections">aquí</a> e inténtalo de nuevo</div>';
            return;

        }

        $refreshToken = $authToken['refresh_token'];
        $options = [
            'google_photos_refresh_token' => $refreshToken,
            'google_photos_code' => $code,
            'google_photos_connected' => true,
        ];

        update_option('google_photos_connection', $options);
        update_option('google_photos_access_token', $accessToken);
    }


    public function googlePhotosUploaderRegisterOptionsPage(): void
    {
        add_menu_page(
            'Configuración de Google Photos',
            'Configuración',
            'manage_options',
            'google_photos_options',
            [$this, 'googlePhotosOptionsPageHtml'],
            "dashicons-google"
        );

        add_submenu_page(
            'google_photos_options',
            'Google Photos Library',
            'Galería',
            'manage_options',
            'google_photos_library',
            [$this, 'googlePhotosGalleryPageHtml'],
        );

        if (self::isDebugMode()) {
            add_submenu_page(
                'google_photos_options',
                'Google Photos Logs',
                'Logs',
                'manage_options',
                'google_photos_logs',
                [$this, 'googlePhotosDebugPageHtml'],
            );
        }
    }

    public function googlePhotosDebugPageHtml()
    {
        if (isset($_POST['deleteLogs'])) {
            googlePhotosLog(print_r($_POST, true));
            file_put_contents(GOOGLE_PHOTOS_PLUGIN_DIR . 'GPLogs.txt', '');
        }
        echo '<div class="container">
            <article data-theme="dark">
            <form method="post">
               <input type="submit" name="deleteLogs" id="submit" class="button button-primary" style="width: fit-content" value="Vaciar logs">
            </form>
            <h3>Logs</h3><textarea rows="54">';
        echo file_get_contents(GOOGLE_PHOTOS_PLUGIN_DIR . 'GPLogs.txt');
        echo "</textarea></div></article></div>";
    }

    public function googlePhotosGalleryPageHtml()
    {
        echo '<div class="container">
            <progress class="google-photos-spinner"></progress>
            <article data-theme="dark">
                <h3>Galería de imágenes asociadas</h3>
                <div class="grid">';

        if (!self::getConnected() || !self::getAlbumId()) {
            echo '<div class="config_error">No tienes imágenes relacionadas, comprueba la configuración del plugin</div>';
            echo "</div></article></div>";
            return;
        }

        $args = array(
            'meta_query' => [[
                'key' => 'google_photo',
                'compare' => 'EXISTS',
            ]]
        );

        $query = new WP_Query($args);

        $albumMedia = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $googlePhoto = get_post_meta($post_id, 'google_photo', true);
                $albumMedia[$post_id] = [
                    'googleLink' => isset($googlePhoto['url']) ? $googlePhoto['url'] : '',
                    'title' => get_the_title(),
                    'link' => get_edit_post_link(),
                    'image' => get_the_post_thumbnail_url($post_id, 'thumbnail'),
                ];
            }
        }

        if (empty($albumMedia)) {
            echo '<div class="config_error">No tienes imágenes relacionadas, comprueba la configuración del plugin</div>';
        }

        foreach ($albumMedia as $media) {
            echo "<div class='gallery_image'><img src='{$media['image']}'/>";
            echo "<div><a href='{$media['link']}'>{$media['title']}</a>";
            echo "<a target='_blank' href='{$media['googleLink']}'>Ver en Google Photos</a></div>";
            echo "</div>";
        }

        echo "</div></article></div>";

    }

    public function googlePhotosOptionsPageHtml(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="container">
            <progress class="google-photos-spinner"></progress>
            <article data-theme="dark">
                <div class="form_card">
                <h3>Configuración de Google Photos</h3>
                <form action="options.php" method="post">';

        if (isset($_GET['settings-updated'])) {
            echo '<div class="config_ok">Configuración guardada</div>';
        }

        if (!GooglePhotosOptions::hasOptions()) {
            echo '<div class="config_error">Debes introducir las credenciales de Google Photos para usar el plugin</div>';
        }

        if (GooglePhotosOptions::hasOptions() && !GooglePhotosOptions::getConnected()) {
            require_once GOOGLE_PHOTOS_PLUGIN_DIR . 'includes/GooglePhotosWrapper.php';
            echo '<div class="config_error">No estás conectado a Google Photos</div>';
            $urlToConnect = GooglePhotosWrapper::getConnectionUrl();
            echo "<a class='custom_button' href='$urlToConnect'>Conectarse a Google Photos</a>";
        }

        if (GooglePhotosOptions::getConnected()) {
            echo '<div class="config_ok">Estás conectado a Google</div>';
        }

        if (isset($_GET['code']) && !self::getRefreshToken()) {
            GooglePhotosOptions::setCredentials($_GET['code']);
        };

        settings_fields('google_photos_options_group');
        do_settings_sections('google_photos_options');
        submit_button('Guardar');

        $disabled = GooglePhotosOptions::getConnected() ? '' : 'disabled';

        if (isset($_POST['create_google_photos_album'])) {
            try {
                GooglePhotosWrapper::createAlbum($_POST['create_google_photos_album']);
                echo '<div class="config_ok">Álbum creado</div>';
            } catch (Throwable $th) {
                googlePhotosLog('Error creando el álbum: ' . $th->getMessage());
            }
        }

        echo "</form>
        </div>
        <div class='form_card'>
        <h3>Crear álbum</h3>
        <form method='post' class='grid'>
                <input
                        placeholder='Nombre del álbum'
                        type='text'
                        required=true
                        id='create_google_photos_album'
                        name='create_google_photos_album'
                        $disabled
                >
               <input type='submit' name='submit' id='submit' class='button button-primary' value='Crear' $disabled>
            </form>
            </div>     
    </article>
     </div>";
    }

    public function getAlbumsAjax(): void
    {
        echo json_encode(GooglePhotosWrapper::getAlbums(), true);
        wp_die(); // ajax call must die to avoid trailing 0 in your response
    }

    public function registerOptions(): void
    {
        register_setting('google_photos_options_group', 'google_photos_options');

        add_settings_section(
            'google_photos_options_sections',
            false,
            false,
            'google_photos_options'
        );

        add_settings_field(
            'google_photos_client_id',
            'Id de cliente',
            function () {
                $value = GooglePhotosOptions::getClientId();
                echo "<input type='text' name='google_photos_options[google_photos_client_id]' placeholder='Id de cliente' value='$value'required>";
            },
            'google_photos_options',
            'google_photos_options_sections',
        );

        add_settings_field(
            'google_photos_client_secret',
            'Secreto de cliente',
            function () {
                $value = GooglePhotosOptions::getClientSecret();
                echo "<input type='text' name='google_photos_options[google_photos_client_secret]' placeholder='Secreto de cliente' required value='$value'>";
            },
            'google_photos_options',
            'google_photos_options_sections',
        );

        if (GooglePhotosOptions::getConnected()) {
            add_settings_field(
                'google_photos_album_id',
                'Álbum donde subir las fotos',
                function () {
                    echo "<fieldset style='grid' id='albumsSelector'>";
                    $value = GooglePhotosOptions::getAlbumId();
                    echo "<input type='text' id='googleAlbum' name='google_photos_options[google_photos_album_id]' value='$value'>";
                    echo '<a href="#" class="custom_button" id="getAlbums">Obtener albums de Google Photos</a>';
                    echo "</fieldset>";
                },
                'google_photos_options',
                'google_photos_options_sections',
            );
        }

        add_settings_field(
            'google_photos_debug',
            'Modo depuración',
            function () {
                $value = GooglePhotosOptions::isDebugMode() ? 'checked' : '';
                echo "<input type='checkbox' name='google_photos_options[google_photos_debug]' $value>";
            },
            'google_photos_options',
            'google_photos_options_sections',
        );
    }
}



