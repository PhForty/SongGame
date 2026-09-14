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
    $game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
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
$songs = sg_repair_titles($db, $sessionId, $songs);
$hasOAuthToken = (bool)$db->fetchOne("SELECT `value` FROM app_config WHERE `key` = 'yt_refresh_token'");
$oauthSuccess = isset($_GET['oauth']) && $_GET['oauth'] === 'success';

sg_page_start(__('title_admin'));
?>
    <div id="wrapper">
        <?php
        ob_start();
        sg_share_button();
        sg_header('admin', true, ob_get_clean());
        ?>

        <div class="card">
            <h1>&#9881;&#65039; <?= __('host_settings') ?></h1>

            <?php if (isset($message)): ?>
                <p class="<?= (($messageType ?? 'success') === 'error') ? 'msg-error' : 'msg-ok' ?>"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>
            <?php if ($oauthSuccess): ?>
                <p class="msg-ok">&#9989; <?= __('playlist_auth_success') ?></p>
            <?php endif; ?>

            <form method="POST" class="settings-form">
                <label class="settings-check">
                    <input type="checkbox" name="autoplay" <?= $game['autoplay'] ? 'checked' : '' ?>> <?= __('autoplay') ?>
                </label>
                <div class="settings-grid">
                    <div>
                        <label for="pause_duration"><?= __('pause_duration') ?></label>
                        <input type="number" id="pause_duration" name="pause_duration" value="<?= (int)$game['pause_duration'] ?>" min="0" max="300">
                    </div>
                    <div>
                        <label for="play_duration"><?= __('play_duration') ?></label>
                        <input type="number" id="play_duration" name="play_duration" value="<?= (int)$game['play_duration'] ?>" min="5" max="600">
                    </div>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary"><?= __('save_settings') ?></button>
            </form>

            <hr class="sep">

            <?php /* Closed by default: the host should not see the thumbnails
                     (and spoil the game) just by opening the settings page. */ ?>
            <details class="fold">
                <summary>&#128230; <?= __('song_pot') ?> (<?= count($songs) ?>)</summary>
                <div class="fold-body">
                    <?php if (!$songs): ?>
                        <p class="muted"><?= __('my_songs_none') ?></p>
                    <?php else: ?>
                        <div class="song-grid">
                            <?php foreach ($songs as $song): ?>
                                <div class="song-item">
                                    <?php // Thumbnail URL is derived from the video id — no API quota spent per song. ?>
                                    <img src="<?= htmlspecialchars(sg_thumb_url($song['video_id'])) ?>"
                                         alt="" loading="lazy"
                                         onerror="this.style.visibility='hidden'">
                                    <?php $label = sg_song_label($song); ?>
                                    <div class="song-title" title="<?= htmlspecialchars($label) ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </div>
                                    <?php if (!($song['embeddable'] ?? 1)): ?>
                                        <span class="badge badge-warn" title="<?= htmlspecialchars(__('cannot_play_hint')) ?>"><?= __('not_embeddable') ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 1.5rem;">
                        <form method="POST">
                            <button type="submit" name="clear_songs" class="btn btn-danger"><?= __('clear_all') ?></button>
                        </form>
                    </div>
                </div>
            </details>

            <hr class="sep">

            <h2>&#127925; <?= __('playlist_section') ?></h2>

            <?php if ($game['playlist_id']): ?>
                <?php $playlistUrl = 'https://www.youtube.com/playlist?list=' . htmlspecialchars($game['playlist_id']); ?>
                <p><?= __('playlist_ready') ?></p>
                <div style="background: var(--bg-color); padding: 1rem; border-radius: 6px; word-break: break-all; margin-bottom: 1rem;">
                    <a href="<?= $playlistUrl ?>" target="_blank" rel="noopener" style="color: var(--accent-color);"><?= $playlistUrl ?></a>
                </div>
                <form method="POST">
                    <button type="submit" name="create_playlist" class="btn btn-secondary">&#128260; <?= __('playlist_sync') ?></button>
                </form>
            <?php elseif ($hasOAuthToken): ?>
                <p><?= __('playlist_create_hint') ?></p>
                <form method="POST">
                    <button type="submit" name="create_playlist" class="btn btn-primary">&#127925; <?= __('playlist_create') ?></button>
                </form>
            <?php else: ?>
                <p><?= __('playlist_need_auth') ?></p>
                <a href="oauth.php" class="btn btn-secondary">&#128273; <?= __('playlist_authorize') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php sg_share_modal($gameCode); ?>
<?php sg_page_end(); ?>
