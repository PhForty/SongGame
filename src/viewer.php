<?php
require_once 'bootstrap.php';
Auth::requireAuth($db);

$gameCode = $_SESSION['game_code'];
$game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];
$isAdmin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('title_viewer') ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="wrapper">
        <header>
            <nav class="nav-menu">
                <li><a href="eingabe.php"><?= __('nav_submit') ?></a></li>
                <?php if ($isAdmin): ?>
                    <li><a href="admin-view.php" style="color: var(--accent-color);"><?= __('nav_admin') ?></a></li>
                <?php endif; ?>
                <li><a href="viewer.php" style="color: var(--accent-color);"><?= __('nav_game') ?></a></li>
                <li><a href="logout.php" class="btn-secondary"><?= __('nav_logout') ?></a></li>
            </nav>
            <div class="lang-switch" style="display: inline-block; margin-left: 10px;">
                <a href="?lang=de" style="<?= $_SESSION['lang'] === 'de' ? 'font-weight: bold;' : '' ?>">🇩🇪</a> |
                <a href="?lang=en" style="<?= $_SESSION['lang'] === 'en' ? 'font-weight: bold;' : '' ?>">🇺🇸</a>
            </div>
            <button id="themeToggle" class="btn-secondary">🌙</button>
        </header>

        <!-- Player container -->
        <div id="video-container" style="padding: 2%; height: 70vh;">
            <div id="yt-player"></div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <?php if ($isAdmin): ?>
                <div style="background: var(--secondary-color); padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color);">
                    <strong style="color: var(--text-color);"><?= __('host_settings') ?> / Controls</strong>
                </div>
            <?php endif; ?>

            <div id="info-panel" class="card-body" style="padding: 1rem; text-align: center;">
                <p id="status-text"><?= __('waiting_host') ?></p>
            </div>

            <!-- Admin controls -->
            <?php if ($isAdmin): ?>
                <div style="padding: 0.5rem;text-align:center;">
                    <button id="nextBtn" class="btn btn-primary"><?= __('next_video') ?></button>
                    <button id="stopBtn" class="btn btn-secondary"><?= __('stop_all') ?></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!--[iframe API - loads asynchronously]-->
    <script src="https://www.youtube.com/iframe_api"></script>

    <script>
    (function() {
        'use strict';

        // ===== CONFIG =====
        var POLL_INTERVAL = 2000;  // ms between polls

        // ===== STATE =====
        var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

        /**
         * Player state machine:
         *   'none'     - no player exists yet
         *   'creating' - YT.Player constructor called, waiting for onReady
         *   'idle'     - player ready, no video loaded (or stopped)
         *   'playing'  - player ready and playing a video
         */
        var playerState = 'none';
        var player = null;
        var currentVideoId = null;  // video the player currently has loaded
        var currentStartOffset = 0; // timestamp offset for the current video
        var ytApiReady = false;
        var pollTimer = null;
        var lastState = null;       // last received server state

        function log(tag, msg) { console.log('[SG:' + tag + ']', msg); }

        // ===== POLLING =====

        function startPolling() {
            poll();
            pollTimer = setInterval(poll, POLL_INTERVAL);
        }

        async function poll() {
            try {
                var r = await fetch('game-state.php', { credentials: 'include' });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                var data = await r.json();
                lastState = data;
                applyState(data);
            } catch(e) {
                log('poll-err', e.message);
            }
        }

        // ===== APPLY STATE =====

        function applyState(s) {
            var phase     = s.phase || 'idle';
            var videoId   = s.current_video_id || null;
            var statusEl  = document.getElementById('status-text');
            var container = document.getElementById('video-container');
            var serverNow = s.server_now || Math.floor(Date.now() / 1000);
            var elapsed   = serverNow - s.started_at;

            if (phase === 'paused') {
                ensureStopped();
                var remaining = Math.max(0, s.settings.pause_duration - elapsed);
                if (statusEl) statusEl.textContent = '⏸ Next in ' + remaining + 's…';
                return;
            }

            if (phase !== 'playing' || !videoId) {
                ensureStopped();
                if (statusEl) statusEl.textContent = '<?= __('waiting_host') ?>';
                return;
            }

            // phase === 'playing'
            var startOffset  = s.start_offset || 0;
            var playDuration = s.settings.play_duration;
            var remaining = Math.max(0, playDuration - elapsed);
            if (statusEl) statusEl.textContent = '▶ ' + videoId + ' (' + remaining + 's)';
            if (container) container.style.display = '';

            if (!ytApiReady) {
                log('state', 'YT API not ready, will apply on ready');
                return;
            }

            if (playerState === 'none') {
                createPlayer(videoId, startOffset, elapsed);
            } else if (playerState === 'creating') {
                // Wait for onReady — it will call applyState again via lastState
            } else if (playerState === 'idle' || playerState === 'playing') {
                if (currentVideoId !== videoId) {
                    loadVideo(videoId, startOffset, elapsed);
                } else {
                    syncTime(startOffset + elapsed);
                }
            }
        }

        // ===== PLAYER MANAGEMENT =====

        function createPlayer(videoId, startOffset, elapsed) {
            log('player', 'Creating player for ' + videoId + ' at offset ' + startOffset + '+' + elapsed + 's');
            playerState = 'creating';
            currentStartOffset = startOffset;

            // Show the container, reset div
            var container = document.getElementById('video-container');
            if (container) container.style.display = '';
            var el = document.getElementById('yt-player');
            el.innerHTML = '';

            player = new YT.Player('yt-player', {
                width: '100%',
                height: '100%',
                videoId: videoId,
                playerVars: {
                    autoplay: 1,
                    controls: 1,
                    disablekb: 1,
                    rel: 0,
                    fs: 1,
                    cc_load_policy: 0,
                    cc_lang_pref: 'off',
                    iv_load_policy: 3,
                    start: Math.max(0, Math.floor(startOffset + elapsed))
                },
                events: {
                    onReady: function(ev) {
                        log('player', 'onReady fired');
                        playerState = 'playing';
                        currentVideoId = videoId;
                        ev.target.playVideo();
                        if (lastState) applyState(lastState);
                    },
                    onStateChange: function(ev) {
                        if (ev.data === YT.PlayerState.ENDED) {
                            log('player', 'Video ended');
                            playerState = 'idle';
                        } else if (ev.data === YT.PlayerState.PLAYING) {
                            playerState = 'playing';
                        }
                    },
                    onError: function(ev) {
                        log('player', 'YT error code ' + ev.data);
                        playerState = 'idle';
                        currentVideoId = null;
                    }
                }
            });
        }

        function loadVideo(videoId, startOffset, elapsed) {
            log('player', 'Loading new video: ' + currentVideoId + ' -> ' + videoId);
            playerState = 'playing';
            currentVideoId = videoId;
            currentStartOffset = startOffset;
            try {
                player.loadVideoById({ videoId: videoId, startSeconds: Math.max(0, Math.floor(startOffset + elapsed)) });
            } catch(e) {
                log('load-err', e.message);
                playerState = 'none';
                currentVideoId = null;
                createPlayer(videoId, startOffset, elapsed);
            }
        }

        function ensureStopped() {
            if (playerState === 'playing' || playerState === 'idle') {
                try { player.stopVideo(); } catch(e) {}
                playerState = 'idle';
            }
            var container = document.getElementById('video-container');
            if (container) container.style.display = 'none';
        }

        function syncTime(seekTo) {
            if (playerState !== 'playing') return;
            try {
                var cur = Math.floor(player.getCurrentTime());
                if (Math.abs(cur - seekTo) > 5) {
                    log('sync', cur + 's -> ' + seekTo + 's');
                    player.seekTo(seekTo, true);
                }
            } catch(e) {}
        }

        // ===== ADMIN CONTROLS =====
        if (isAdmin) {
            function sendAction(action) {
                return fetch('game-state.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: new URLSearchParams({ action: action })
                }).then(function(r) {
                    if (r.ok) poll(); // Immediately refresh state after action
                }).catch(function(e) { log('admin-err', e.message); });
            }

            var nBtn = document.getElementById('nextBtn');
            var sBtn = document.getElementById('stopBtn');
            if (nBtn) nBtn.addEventListener('click', function() { sendAction('next'); });
            if (sBtn) sBtn.addEventListener('click', function() { sendAction('stop'); });
        }

        // ===== THEME TOGGLE =====
        var tog = document.getElementById('themeToggle');
        if (tog) {
            tog.addEventListener('click', function() {
                var isDark = document.body.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.body.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    tog.textContent = '🌙';
                } else {
                    document.body.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    tog.textContent = '☀️';
                }
            });
        }
        if (localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            if (tog) tog.textContent = '☀️';
        }

        // ===== INITIALIZATION =====
        // Hide video container initially
        var vc = document.getElementById('video-container');
        if (vc) vc.style.display = 'none';

        function onYTReady() {
            log('init', 'YT API ready');
            ytApiReady = true;
            startPolling();
        }

        if (typeof window.onYouTubeIframeAPIReady !== 'function') {
            window.onYouTubeIframeAPIReady = onYTReady;
        } else {
            var orig = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function() {
                try { orig.call(this); } catch(e) {}
                onYTReady();
            };
        }
    })();
    </script>
</body>
</html>
