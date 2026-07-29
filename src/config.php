<?php
/**
 * Configuration for SongGame rewrite
 */

// Suppress PHP errors in the webapp
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Database configuration
define('DB_HOST', 'database');
define('DB_USER', 'root');
define('DB_PASS', 'wrjkn422');
define('DB_NAME', 'song_game');

// YouTube API configuration
// Note: Creating playlists requires OAuth2 access tokens. 
// For a simple rewrite, we'll store the token and refresh token here or in DB.
define('YT_API_KEY', '...'); // Used for public data (thumbnails)
define('YT_CLIENT_ID', '...');
define('YT_CLIENT_SECRET', '...');

// General settings
define('SESSION_LIFETIME', 86400);
?>
