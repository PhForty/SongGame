<?php
require_once 'bootstrap.php';
Auth::requireAuth($db);

$userId = $_SESSION['user_id'];
$gameCode = $_SESSION['game_code'];
$game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];

$message = "";
$messageType = ""; // success or error
$hint = "";        // non-blocking note shown under the message

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_song'])) {
    $url    = trim($_POST['song_url']);
    $parsed = YouTubeService::parseYouTubeUrl($url);
    $videoId = $parsed ? $parsed['video_id'] : null;
    $startOffset = $parsed ? $parsed['start_offset'] : 0;

    if (!$videoId) {
        $message = __('invalid_url');
        $messageType = "error";
    } else {
        // Check if already submitted by this user in this game
        $existing = $db->fetchOne("SELECT id FROM songs WHERE session_id = ? AND user_id = ? AND video_id = ?", [$sessionId, $userId, $videoId]);

        if ($existing) {
            $message = __('already_submitted');
            $messageType = "error";
        } else {
            // Look up the title (Data API when configured, otherwise oEmbed).
            $yt = new YouTubeService(YT_API_KEY);
            $details = $yt->getVideoDetails($videoId);
            // Leave it null when unknown so a later page load can fill it in.
            $title = $details && !empty($details['title']) ? $details['title'] : null;
            // Unknown videos stay playable by default; only a definite "no" blocks the embed.
            $embeddable = $details ? (int)$details['embeddable'] : 1;

            $db->execute(
                "INSERT INTO songs (session_id, user_id, video_id, start_offset, title, embeddable) VALUES (?, ?, ?, ?, ?, ?)",
                [$sessionId, $userId, $videoId, $startOffset, $title, $embeddable]
            );
            $message = __('song_added');
            $messageType = "success";
            if (!$embeddable) {
                $hint = __('not_embeddable_hint');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_song'])) {
    // Scoped to this player and this game, so nobody can delete someone else's pick.
    $removed = $db->execute(
        "DELETE FROM songs WHERE id = ? AND session_id = ? AND user_id = ?",
        [(int)$_POST['delete_song'], $sessionId, $userId]
    );
    $message = $removed ? __('song_deleted') : __('song_delete_failed');
    $messageType = $removed ? 'success' : 'error';
}

// Everything this player put into the pot, newest last.
$mySongs = $db->fetchAll(
    "SELECT id, video_id, title, start_offset, was_viewed, embeddable
     FROM songs WHERE session_id = ? AND user_id = ? ORDER BY created_at ASC",
    [$sessionId, $userId]
);
$mySongs = sg_repair_titles($db, $sessionId, $mySongs);

sg_page_start(__('title_submit'));
?>
    <div id="wrapper">
        <?php sg_header('submit', Auth::isAdmin()); ?>

        <div class="card">
            <h1>&#127925; <?= __('submit_song') ?></h1>
            <p><?= __('game_code') ?>: <strong><?= htmlspecialchars($gameCode) ?></strong></p>

            <?php if ($message): ?>
                <p class="<?= $messageType === 'success' ? 'msg-ok' : 'msg-error' ?>">
                    <?= htmlspecialchars($message) ?>
                </p>
            <?php endif; ?>
            <?php if ($hint): ?>
                <p class="muted"><?= htmlspecialchars($hint) ?></p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="song_url"><?= __('youtube_url') ?></label>
                    <input type="text" id="song_url" name="song_url"
                           placeholder="https://www.youtube.com/watch?v=..." required autocomplete="off">
                </div>
                <button type="submit" name="submit_song" class="btn btn-primary"><?= __('add_to_pot') ?></button>
            </form>

            <hr class="sep">

            <h2 style="text-align: left;"><?= __('my_songs') ?> (<?= count($mySongs) ?>)</h2>
            <?php if (!$mySongs): ?>
                <p class="muted" style="text-align: left;"><?= __('my_songs_none') ?></p>
            <?php else: ?>
                <ul class="song-list">
                    <?php foreach ($mySongs as $song): ?>
                        <li>
                            <img src="<?= htmlspecialchars(sg_thumb_url($song['video_id'])) ?>"
                                 alt="" loading="lazy" width="80" height="45"
                                 onerror="this.style.visibility='hidden'">
                            <?php $label = sg_song_label($song); ?>
                            <span class="song-title" title="<?= htmlspecialchars($label) ?>">
                                <a href="<?= htmlspecialchars(sg_watch_url($song['video_id'], $song['start_offset'])) ?>"
                                   target="_blank" rel="noopener"><?= htmlspecialchars($label) ?></a>
                            </span>
                            <?php if (!($song['embeddable'] ?? 1)): ?>
                                <span class="badge badge-warn" title="<?= htmlspecialchars(__('cannot_play_hint')) ?>"><?= __('not_embeddable') ?></span>
                            <?php endif; ?>
                            <?php if ($song['was_viewed']): ?>
                                <span class="badge"><?= __('already_played') ?></span>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm(<?= htmlspecialchars(json_encode(__('delete_confirm')), ENT_QUOTES) ?>);">
                                <button type="submit" name="delete_song" value="<?= (int)$song['id'] ?>"
                                        class="btn btn-danger btn-small"><?= __('delete_song') ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div style="margin-top: 2rem; text-align: left; font-size: 0.9rem;" class="muted">
                <h3><?= __('how_it_works') ?></h3>
                <ol>
                    <li><?= __('step1') ?></li>
                    <li><?= __('step2') ?></li>
                    <li><?= __('step3') ?></li>
                </ol>
            </div>
        </div>
    </div>
<?php sg_page_end(); ?>
