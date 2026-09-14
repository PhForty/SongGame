<?php
require_once 'config.php';
require_once 'Database.php';
require_once 'Auth.php';
require_once 'YouTubeService.php';
require_once 'Migrations.php';
require_once 'partials.php';
require_once 'translations.php';

$db = new Database();
Auth::start();

// Language handling
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'] === 'en' ? 'en' : 'de';
    $_SESSION['lang'] = $lang;
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'de';
}

$translations = (include 'translations.php')[$_SESSION['lang']];

function __($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

function goose_log($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message"; 
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    file_put_contents('app.log', $logMessage . PHP_EOL, FILE_APPEND);
}

// Applied here so every entry point gets an up-to-date schema.
Migrations::run($db);

