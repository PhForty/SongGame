<?php
/**
 * Configuration for SongGame rewrite
 */

// Suppress PHP errors in the webapp
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/*
 * Production credentials live in config.local.php, which the deploy pipeline
 * generates from GitHub secrets and uploads alongside the source. It is
 * gitignored, so nothing below ever has to hold a real secret. When the file is
 * absent — a plain `docker-compose up` checkout — the defaults keep working.
 */
$sgLocal  = __DIR__ . '/config.local.php';
$sgConfig = is_file($sgLocal) ? require $sgLocal : [];
if (!is_array($sgConfig)) {
    $sgConfig = [];
}

$sgConfig += [
    // docker-compose defaults; see docker-compose.yml
    'DB_HOST'          => 'database',
    'DB_USER'          => 'root',
    'DB_PASS'          => 'wrjkn422',
    'DB_NAME'          => 'song_game',

    // YouTube API configuration.
    // Optional. Without a valid key SongGame falls back to YouTube's oEmbed
    // endpoint, which supplies video titles without any key; the key only adds
    // the authoritative "is this video embeddable" flag. Creating playlists
    // still needs the OAuth2 client below.
    'YT_API_KEY'       => '...',
    'YT_CLIENT_ID'     => '...',
    'YT_CLIENT_SECRET' => '...',
];

foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME',
          'YT_API_KEY', 'YT_CLIENT_ID', 'YT_CLIENT_SECRET'] as $sgKey) {
    // An empty secret means "not configured" — fall back rather than define ''.
    define($sgKey, $sgConfig[$sgKey] !== '' ? $sgConfig[$sgKey] : '...');
}
unset($sgLocal, $sgConfig, $sgKey);

// General settings
define('SESSION_LIFETIME', 86400);
