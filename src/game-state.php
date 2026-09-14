<?php
require_once 'bootstrap.php';
Auth::requireAuth($db);

$gameCode  = $_SESSION['game_code'];
$game      = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['status' => 'forbidden']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'next') {
        $videoId = pickNextSong($db, $sessionId);
        if ($videoId) {
            $db->execute(
                "UPDATE sessions SET current_video_id = ?, phase = 'playing', started_at = UNIX_TIMESTAMP() WHERE id = ?",
                [$videoId, $sessionId]
            );
            $db->execute(
                "UPDATE songs SET was_viewed = 1 WHERE video_id = ? AND session_id = ? AND was_viewed = 0",
                [$videoId, $sessionId]
            );
            echo json_encode(['status' => 'success', 'video_id' => $videoId]);
        } else {
            $db->execute(
                "UPDATE sessions SET current_video_id = NULL, phase = 'idle' WHERE id = ?",
                [$sessionId]
            );
            echo json_encode(['status' => 'empty']);
        }
        exit;
    }

    if ($action === 'stop') {
        $db->execute(
            "UPDATE sessions SET current_video_id = NULL, phase = 'idle' WHERE id = ?",
            [$sessionId]
        );
        echo json_encode(['status' => 'success']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'unknown_action']);
    exit;
}

// --- GET: return current state (also drives auto-advance) ---
$state = $db->fetchOne(
    "SELECT s.current_video_id, s.phase, s.started_at, s.autoplay, s.pause_duration, s.play_duration,
            COALESCE(sg.start_offset, 0) AS start_offset,
            sg.title AS title,
            COALESCE(sg.embeddable, 1) AS embeddable
     FROM sessions s
     LEFT JOIN songs sg ON sg.video_id = s.current_video_id AND sg.session_id = s.id
     WHERE s.id = ?",
    [$sessionId]
);

$serverNow    = time();
$phase        = $state['phase'] ?? 'idle';
$videoId      = $state['current_video_id'] ?? null;
$startedAt    = (int)($state['started_at'] ?? 0);
$playDuration = (int)($state['play_duration'] ?? 30);
$pauseDuration= (int)($state['pause_duration'] ?? 5);
$autoplay     = (bool)$state['autoplay'];
$elapsed      = $serverNow - $startedAt;

if ($autoplay) {
    if ($phase === 'playing' && $elapsed >= $playDuration) {
        // Play time expired — enter pause (or idle if pause_duration is 0)
        if ($pauseDuration > 0) {
            $db->execute(
                "UPDATE sessions SET current_video_id = NULL, phase = 'paused', started_at = UNIX_TIMESTAMP() WHERE id = ?",
                [$sessionId]
            );
            $phase     = 'paused';
            $videoId   = null;
            $startedAt = $serverNow;
            $elapsed   = 0;
        } else {
            // No pause — go straight to next song
            $next = pickNextSong($db, $sessionId);
            if ($next) {
                $db->execute(
                    "UPDATE sessions SET current_video_id = ?, phase = 'playing', started_at = UNIX_TIMESTAMP() WHERE id = ?",
                    [$next, $sessionId]
                );
                $db->execute(
                    "UPDATE songs SET was_viewed = 1 WHERE video_id = ? AND session_id = ? AND was_viewed = 0",
                    [$next, $sessionId]
                );
                $phase     = 'playing';
                $videoId   = $next;
                $startedAt = $serverNow;
                $elapsed   = 0;
            } else {
                $db->execute(
                    "UPDATE sessions SET current_video_id = NULL, phase = 'idle' WHERE id = ?",
                    [$sessionId]
                );
                $phase   = 'idle';
                $videoId = null;
            }
        }
    } elseif ($phase === 'paused' && $elapsed >= $pauseDuration) {
        // Pause expired — pick next song and start playing
        $next = pickNextSong($db, $sessionId);
        if ($next) {
            $db->execute(
                "UPDATE sessions SET current_video_id = ?, phase = 'playing', started_at = UNIX_TIMESTAMP() WHERE id = ?",
                [$next, $sessionId]
            );
            $db->execute(
                "UPDATE songs SET was_viewed = 1 WHERE video_id = ? AND session_id = ? AND was_viewed = 0",
                [$next, $sessionId]
            );
            $phase     = 'playing';
            $videoId   = $next;
            $startedAt = $serverNow;
            $elapsed   = 0;
        } else {
            $db->execute(
                "UPDATE sessions SET current_video_id = NULL, phase = 'idle' WHERE id = ?",
                [$sessionId]
            );
            $phase   = 'idle';
            $videoId = null;
        }
    }
}

// Only the 'playing' phase has a current song — never leak one while idle or paused.
if ($phase !== 'playing') {
    $videoId = null;
}

// Re-read the joined song row when auto-advance picked a different video above.
if ($videoId !== null && $videoId !== ($state['current_video_id'] ?? null)) {
    $song = $db->fetchOne(
        "SELECT start_offset, title, embeddable FROM songs WHERE session_id = ? AND video_id = ?",
        [$sessionId, $videoId]
    );
    $state['start_offset'] = $song['start_offset'] ?? 0;
    $state['title']        = $song['title'] ?? null;
    $state['embeddable']   = $song['embeddable'] ?? 1;
}

echo json_encode([
    'current_video_id' => $videoId,
    'title'            => $videoId === null ? null : ($state['title'] ?? null),
    'embeddable'       => $videoId === null ? true : (bool)($state['embeddable'] ?? 1),
    'watch_url'        => $videoId === null ? null : sg_watch_url($videoId, (int)($state['start_offset'] ?? 0)),
    'start_offset'     => $videoId === null ? 0 : (int)($state['start_offset'] ?? 0),
    'phase'            => $phase,
    'started_at'       => $startedAt,
    'server_now'       => $serverNow,
    'songs_left'       => (int)($db->fetchOne(
        "SELECT COUNT(*) AS n FROM songs WHERE session_id = ? AND was_viewed = 0",
        [$sessionId]
    )['n'] ?? 0),
    'settings'         => [
        'autoplay'       => $autoplay,
        'pause_duration' => $pauseDuration,
        'play_duration'  => $playDuration,
    ],
]);

// -----------------------------------------------------------------------
function pickNextSong(Database $db, int $sessionId): ?string {
    $song = $db->fetchOne(
        "SELECT video_id FROM songs WHERE session_id = ? AND was_viewed = 0 ORDER BY RAND() LIMIT 1",
        [$sessionId]
    );
    return $song ? $song['video_id'] : null;
    // No fallback — each song plays only once
}
