<?php
require_once 'bootstrap.php';
Auth::requireAdmin($db);

$redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . strtok($_SERVER['REQUEST_URI'], '?'); // this file's URL, no query string

// --- Step 2: Google redirected back with ?code=...
if (isset($_GET['code'])) {
    $tokens = YouTubeService::exchangeAuthCode($_GET['code'], $redirectUri);
    if ($tokens && isset($tokens['refresh_token'])) {
        // Persist refresh token in DB
        $db->execute(
            "INSERT INTO app_config (`key`, `value`) VALUES ('yt_refresh_token', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$tokens['refresh_token']]
        );
        header('Location: admin-view.php?oauth=success');
        exit;
    }
    // Got an access token but no refresh_token — happens if already authorized before.
    // In that case the user needs to revoke access and re-authorize.
    $error = 'No refresh token returned. Please revoke app access in your Google account and try again.';
}

// --- Step 1: Redirect to Google consent screen
if (isset($_GET['start'])) {
    $params = http_build_query([
        'client_id'             => YT_CLIENT_ID,
        'redirect_uri'          => $redirectUri,
        'response_type'         => 'code',
        'scope'                 => 'https://www.googleapis.com/auth/youtube',
        'access_type'           => 'offline',
        'prompt'                => 'consent', // always ask so we get refresh_token
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <title>YouTube Authorization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body data-theme="dark">
<div id="wrapper">
    <div class="card" style="max-width: 500px; margin: 4rem auto; text-align: center;">
        <h1>🔑 YouTube Authorization</h1>
        <?php if (isset($error)): ?>
            <p style="color: #e74c3c;"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <p>Grant SongGame access to create YouTube playlists on your behalf.</p>
        <?php endif; ?>
        <a href="?start=1" class="btn btn-primary" style="display:inline-block; margin-top: 1rem;">
            Authorize with Google
        </a>
        <br><br>
        <a href="admin-view.php">← Back to Admin</a>
    </div>
</div>
</body>
</html>
