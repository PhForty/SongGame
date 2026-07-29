<?php
require_once 'bootstrap.php';
Auth::requireAdmin($db);

$gameCode = $_SESSION['game_code'];
$game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $autoplay = isset($_POST['autoplay']) ? 1 : 0;
    $pause = max(0, min(300, (int)$_POST['pause_duration']));
    $play  = max(5, min(600, (int)$_POST['play_duration']));

    $db->execute("UPDATE sessions SET autoplay = ?, pause_duration = ?, play_duration = ? WHERE id = ?", [$autoplay, $pause, $play, $sessionId]);
    $message = __('settings_updated');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_songs'])) {
    $db->execute("DELETE FROM songs WHERE session_id = ?", [$sessionId]);
    $message = __('songs_removed');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_playlist'])) {
    $refreshToken = $db->fetchOne("SELECT `value` FROM app_config WHERE `key` = 'yt_refresh_token'");
    if (!$refreshToken) {
        $message = __('playlist_no_auth');
        $messageType = 'error';
    } else {
        $accessToken = YouTubeService::refreshAccessToken($refreshToken['value']);
        if (!$accessToken) {
            $message = __('playlist_token_failed');
            $messageType = 'error';
        } else {
            $ytAuth = new YouTubeService(YT_API_KEY, $accessToken);

            if ($game['playlist_id']) {
                // Playlist exists — only add songs not yet in it
                $newSongs = $db->fetchAll(
                    "SELECT video_id FROM songs WHERE session_id = ? AND was_viewed = 1 AND playlist_added = 0",
                    [$sessionId]
                );
                if (empty($newSongs)) {
                    $message = __('playlist_up_to_date');
                    $messageType = 'success';
                } else {
                    $added = 0;
                    foreach ($newSongs as $song) {
                        if ($ytAuth->addVideoToPlaylist($game['playlist_id'], $song['video_id'])) {
                            $db->execute(
                                "UPDATE songs SET playlist_added = 1 WHERE video_id = ? AND session_id = ?",
                                [$song['video_id'], $sessionId]
                            );
                            $added++;
                        }
                    }
                    $message = sprintf(__('playlist_updated'), $added);
                    $messageType = 'success';
                }
            } else {
                // No playlist yet — create one with all viewed songs
                $viewedSongs = $db->fetchAll(
                    "SELECT video_id FROM songs WHERE session_id = ? AND was_viewed = 1",
                    [$sessionId]
                );
                if (empty($viewedSongs)) {
                    $message = __('playlist_no_songs');
                    $messageType = 'error';
                } else {
                    $title = 'SongGame – ' . $gameCode . ' – ' . date('Y-m-d');
                    $videoIds = array_column($viewedSongs, 'video_id');
                    $playlistUrl = $ytAuth->createGamePlaylist($title, $videoIds);
                    if ($playlistUrl) {
                        parse_str(parse_url($playlistUrl, PHP_URL_QUERY), $qs);
                        $playlistId = $qs['list'];
                        $db->execute("UPDATE sessions SET playlist_id = ? WHERE id = ?", [$playlistId, $sessionId]);
                        $db->execute(
                            "UPDATE songs SET playlist_added = 1 WHERE session_id = ? AND was_viewed = 1",
                            [$sessionId]
                        );
                        $game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
                        $message = __('playlist_created');
                        $messageType = 'success';
                    } else {
                        $message = __('playlist_failed');
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

$songs = $db->fetchAll("SELECT * FROM songs WHERE session_id = ? ORDER BY created_at ASC", [$sessionId]);
$yt = new YouTubeService(YT_API_KEY);
$hasOAuthToken = (bool)$db->fetchOne("SELECT `value` FROM app_config WHERE `key` = 'yt_refresh_token'");
$oauthSuccess = isset($_GET['oauth']) && $_GET['oauth'] === 'success';
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('title_admin'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body data-theme="dark"> <!-- Default admin to dark for contrast -->
    <div id="wrapper">
        <header>
            <nav class="nav-menu">
                <li><a href="eingabe.php"><?php echo __('nav_submit'); ?></a></li>
                <li><a href="admin-view.php" style="color: var(--accent-color);"><?php echo __('nav_admin'); ?></a></li>
                <li><a href="viewer.php"><?php echo __('nav_game'); ?></a></li>
                <li><a href="logout.php" class="btn-secondary"><?php echo __('nav_logout'); ?></a></li>
            </nav>
            <div class="lang-switch" style="display: inline-block; margin-left: 10px;">
                <a href="?lang=de" style="<?php echo $_SESSION['lang'] === 'de' ? 'font-weight: bold;' : ''; ?>">🇩🇪</a> | 
                <a href="?lang=en" style="<?php echo $_SESSION['lang'] === 'en' ? 'font-weight: bold;' : ''; ?>">🇺🇸</a>
            </div>
            <button id="themeToggle" class="theme-toggle">🌙</button>
        </header>

        <div class="card">
            <h1>⚙️ <?php echo __('host_settings'); ?></h1>
            
            <?php if (isset($message)): ?>
                <p style="color: <?= (($messageType ?? 'success') === 'error') ? '#e74c3c' : 'green' ?>; font-weight: bold;"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <?php if ($oauthSuccess): ?>
                <p style="color: green; font-weight: bold;">✅ <?= __('playlist_auth_success') ?></p>
            <?php endif; ?>

            <form method="POST" class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <label>
                        <input type="checkbox" name="autoplay" <?php echo $game['autoplay'] ? 'checked' : ''; ?>> <?php echo __('autoplay'); ?>
                    </label>
                    <br><br>
                    <label><?php echo __('pause_duration'); ?></label>
                    <input type="number" name="pause_duration" value="<?php echo $game['pause_duration']; ?>" min="0" max="300">
                </div>
                <div>
                    <label><?php echo __('play_duration'); ?></label>
                    <input type="number" name="play_duration" value="<?php echo $game['play_duration']; ?>" min="5" max="600">
                    <br><br>
                    <button type="submit" name="update_settings" class="btn btn-primary"><?php echo __('save_settings'); ?></button>
                </div>
            </form>

            <hr style="margin: 2rem 0; border: 0; border-top: 1px solid var(--border-color);">

            <h2>📦 <?php echo __('song_pot'); ?></h2>
            <div class="song-grid">
                <?php foreach ($songs as $song): ?>
                    <div class="song-item">
                        <?php 
                            $details = $yt->getVideoDetails($song['video_id']);
                            $thumb = $details ? $details['thumbnail'] : 'https://via.placeholder.com/120x90?text=No+Thumb';
                            $title = $song['title'] ?? ($details ? $details['title'] : 'Unknown');
                        ?>
                        <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Thumbnail">
                        <div style="font-size: 0.8rem; margin-top: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($title); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 2rem;">
                <form method="POST">
                    <button type="submit" name="clear_songs" class="btn btn-secondary" style="background: #d9534f;"><?php echo __('clear_all'); ?></button>
                </form>
            </div>

            <hr style="margin: 2rem 0; border: 0; border-top: 1px solid var(--border-color);">

            <h2>🎵 <?= __('playlist_section') ?></h2>

            <?php if ($game['playlist_id']): ?>
                <?php $playlistUrl = 'https://www.youtube.com/playlist?list=' . htmlspecialchars($game['playlist_id']); ?>
                <p><?= __('playlist_ready') ?></p>
                <div style="background: var(--secondary-color); padding: 1rem; border-radius: 6px; word-break: break-all; margin-bottom: 1rem;">
                    <a href="<?= $playlistUrl ?>" target="_blank" style="color: var(--accent-color);"><?= $playlistUrl ?></a>
                </div>
                <form method="POST">
                    <button type="submit" name="create_playlist" class="btn btn-secondary">🔄 <?= __('playlist_sync') ?></button>
                </form>
            <?php elseif ($hasOAuthToken): ?>
                <p><?= __('playlist_create_hint') ?></p>
                <form method="POST">
                    <button type="submit" name="create_playlist" class="btn btn-primary">🎵 <?= __('playlist_create') ?></button>
                </form>
            <?php else: ?>
                <p><?= __('playlist_need_auth') ?></p>
                <a href="oauth.php" class="btn btn-secondary">🔑 <?= __('playlist_authorize') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const toggle = document.getElementById('themeToggle');
        toggle.addEventListener('click', () => {
            const body = document.body;
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                toggle.textContent = '🌙';
            } else {
                body.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                toggle.textContent = '☀️';
            }
        });

        if (localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            toggle.textContent = '☀️';
        }
    </script>
</body>
</html>
