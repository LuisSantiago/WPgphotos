<?php

/**
 *
 * @link              https://github.com/LuisSantiago
 * @since             1.0.0
 * @package           google-photos
 *
 * @wordpress-plugin
 * Plugin Name:       Google Photos
 * Plugin URI:        https://github.com/LuisSantiago
 * Description:       Sube las fotos de tus posts a Google Photos
 * Version:           1.0.0
 * Author:            Luis Santiago
 * Author URI:        https://github.com/LuisSantiago/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       google-photos
 */

if (!defined('WPINC')) {
    die;
}

if (!defined('ABSPATH')) {
    exit;
}

define('GOOGLE_PHOTOS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once GOOGLE_PHOTOS_PLUGIN_DIR . 'includes/GooglePhotos.php';
require_once GOOGLE_PHOTOS_PLUGIN_DIR . 'includes/GooglePhotosOptions.php';

(new GooglePhotos)->init();
$options = (new GooglePhotosOptions);
$options->init();

function googlePhotosLog(string $message, bool $error = false)
{
    if (!GooglePhotosOptions::isDebugMode()) {
        return;
    }

    $date = "[" . date('Yd/m/y H:i') . "] ";
    $error = $error ? '[ERROR] ' : '';

    file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'GPLogs.txt', $date . $error . $message . PHP_EOL, FILE_APPEND);
}