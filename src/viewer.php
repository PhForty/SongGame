<?php
require_once 'bootstrap.php';
Auth::requireAuth($db);

$gameCode = $_SESSION['game_code'];
$game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];
$isAdmin = Auth::isAdmin();

// Strings the player script needs at runtime.
$js = [
    'waiting'        => __('waiting_host'),
    'paused'         => __('paused_next_in'),
    'remaining'      => __('playing_remaining'),
    'noSongs'        => __('no_songs_left'),
    'cannotPlay'     => __('cannot_play'),
    'cannotPlayHint' => __('cannot_play_hint'),
    'unavailable'    => __('video_unavailable'),
    'openYoutube'    => __('open_on_youtube'),
    'zenEnter'       => __('zen_enter'),
    'zenExit'        => __('zen_exit'),
    'fullscreen'     => __('fullscreen'),
    'fullscreenExit' => __('fullscreen_exit'),
    'play'           => __('next_video'),
];

sg_page_start(__('title_viewer'));
?>
    <div id="wrapper">
        <?php
        ob_start();
        if ($isAdmin) {
            sg_share_button();
        }
        sg_header('game', $isAdmin, ob_get_clean());
        ?>

        <div id="video-stage">
            <div id="video-frame">
                <div id="yt-player"></div>
                <?php /* Blocks stray clicks while the YouTube controls are hidden. */ ?>
                <div id="click-shield"></div>
                <?php /* Black curtain; starts opaque so nothing ever cuts in hard. */ ?>
                <div id="video-fade"></div>
                <div id="stage-message">
                    <div class="stage-headline"><?= __('waiting_host') ?></div>
                    <div class="stage-note"></div>
                    <div class="stage-actions"></div>
                </div>
            </div>
            <div class="stage-bar">
                <button type="button" id="zenBtn" title="<?= htmlspecialchars(__('zen_enter')) ?>">&#128065;&#65039; <?= __('zen_mode') ?></button>
                <button type="button" id="fsBtn" title="<?= htmlspecialchars(__('fullscreen')) ?>">&#9974; <?= __('fullscreen') ?></button>
            </div>
        </div>

        <div class="card zen-hide" style="padding: 0; overflow: hidden;">
            <div id="info-panel" style="padding: 1rem; text-align: center;">
                <p id="status-text" style="margin: 0;"><?= __('waiting_host') ?></p>
            </div>

            <?php if ($isAdmin): ?>
                <div style="padding: 0.5rem; text-align: center;">
                    <button id="nextBtn" class="btn btn-primary"><?= __('next_video') ?></button>
                    <button id="stopBtn" class="btn btn-secondary"><?= __('stop_all') ?></button>
                </div>
            <?php endif; ?>

            <div class="viewer-toggles" style="border-top: 1px solid var(--border-color);">
                <label><input type="checkbox" id="ctrlToggle"> <?= __('show_controls') ?></label>
                <label><input type="checkbox" id="ccToggle"> <?= __('show_captions') ?></label>
            </div>
        </div>
    </div>

    <?php if ($isAdmin) { sg_share_modal($gameCode); } ?>

    <!--[iframe API - loads asynchronously]-->
    <script src="https://www.youtube.com/iframe_api"></script>

    <script>
    (function() {
        'use strict';

        // ===== CONFIG =====
        var POLL_INTERVAL = 2000;   // ms between polls
        var TICK_INTERVAL = 200;    // ms between local countdown/fade checks
        var FADE_MS       = 600;    // curtain duration; pushed into CSS below
        var FADE_LEAD_MS  = 1200;   // start fading out this long before a clip is cut
        var STALL_MS      = 3000;   // if playback has not begun by then, show a manual start

        var T = <?= json_encode($js, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        document.documentElement.style.setProperty('--fade-ms', FADE_MS + 'ms');

        // ===== STATE =====
        var isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

        /**
         * Player state machine:
         *   'none'     - no player exists yet
         *   'creating' - YT.Player constructor called, waiting for onReady
         *   'idle'     - player ready, no video loaded (or stopped)
         *   'playing'  - player ready and has a video loaded
         */
        var playerState = 'none';
        var player = null;
        var currentVideoId = null;  // video the player currently has loaded
        var ytApiReady = false;
        var ytPlaying = false;      // YouTube reports PLAYING right now
        var lastState = null;       // last received server state
        var serverOffset = 0;       // serverNow - Date.now(), in ms
        var curtainUp = true;       // black curtain covering the iframe
        var baseVolume = 100;
        var failedVideoId = null;   // video that errored — never retried
        var failedMessage = null;   // message kept on screen while it is current
        var volTimer = null, loadTimer = null, stallTimer = null;

        var fadeEl    = document.getElementById('video-fade');
        var msgEl     = document.getElementById('stage-message');
        var msgHead   = msgEl.querySelector('.stage-headline');
        var msgNote   = msgEl.querySelector('.stage-note');
        var msgAct    = msgEl.querySelector('.stage-actions');
        var statusEl  = document.getElementById('status-text');

        function log(tag, msg) { console.log('[SG:' + tag + ']', msg); }
        function now() { return Date.now() + serverOffset; }

        // ===== VIEWER PREFERENCES =====
        // Both default to off: hidden controls keep the shared playback in sync
        // (and sidestep YouTube leaving its control bar up until the pointer
        // re-enters the iframe), and captions are off unless asked for.
        function pref(key, dflt) {
            try {
                var v = localStorage.getItem(key);
                return v === null ? dflt : v === '1';
            } catch (e) { return dflt; }
        }
        function setPref(key, on) {
            try { localStorage.setItem(key, on ? '1' : '0'); } catch (e) {}
        }

        var showControls = pref('sg-controls', false);
        var showCaptions = pref('sg-captions', false);
        document.body.classList.toggle('controls-on', showControls);

        // ===== CURTAIN =====
        function setCurtain(up) {
            if (curtainUp === up) return;
            curtainUp = up;
            fadeEl.classList.toggle('clear', !up);
            rampVolume(up ? 0 : baseVolume, FADE_MS);
        }

        function rampVolume(target, ms) {
            if (volTimer) { clearInterval(volTimer); volTimer = null; }
            if (!player || !player.setVolume) return;
            var start = 0;
            try { start = player.getVolume(); } catch (e) { start = target; }
            var t0 = Date.now();
            volTimer = setInterval(function () {
                var p = Math.min(1, (Date.now() - t0) / ms);
                try { player.setVolume(Math.round(start + (target - start) * p)); } catch (e) {}
                if (p >= 1) { clearInterval(volTimer); volTimer = null; }
            }, 40);
        }

        /** Runs fn once the curtain is fully black, so swaps are never visible. */
        function behindCurtain(fn) {
            if (loadTimer) { clearTimeout(loadTimer); loadTimer = null; }
            if (curtainUp) { fn(); return; }
            setCurtain(true);
            loadTimer = setTimeout(function () { loadTimer = null; fn(); }, FADE_MS);
        }

        // ===== STAGE MESSAGE =====
        function showMessage(headline, note, actions) {
            msgHead.textContent = headline || '';
            msgNote.textContent = note || '';
            msgAct.innerHTML = '';
            (actions || []).forEach(function (a) {
                var el;
                if (a.href) {
                    el = document.createElement('a');
                    el.href = a.href;
                    el.target = '_blank';
                    el.rel = 'noopener';
                    el.style.color = '#ff6b6b';
                } else {
                    el = document.createElement('button');
                    el.type = 'button';
                    el.className = 'btn btn-primary btn-small';
                    el.addEventListener('click', a.onClick);
                }
                el.textContent = a.label;
                msgAct.appendChild(el);
            });
            msgEl.hidden = false;
        }

        function hideMessage() { msgEl.hidden = true; }

        // ===== POLLING =====
        function startPolling() {
            poll();
            setInterval(poll, POLL_INTERVAL);
            setInterval(tick, TICK_INTERVAL);
        }

        async function poll() {
            try {
                var r = await fetch('game-state.php', { credentials: 'include' });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                var data = await r.json();
                if (data.server_now) serverOffset = data.server_now * 1000 - Date.now();
                lastState = data;
                applyState(data);
            } catch (e) {
                log('poll-err', e.message);
            }
        }

        /** Seconds the current phase has been running, from the server clock. */
        function elapsedSec(s) {
            return (now() / 1000) - s.started_at;
        }

        // ===== APPLY STATE =====
        function applyState(s) {
            var phase   = s.phase || 'idle';
            var videoId = s.current_video_id || null;

            if (phase === 'paused') {
                ensureStopped();
                return;
            }

            if (phase !== 'playing' || !videoId) {
                ensureStopped();
                showMessage(s.songs_left === 0 ? T.noSongs : T.waiting, '', []);
                if (statusEl) statusEl.textContent = s.songs_left === 0 ? T.noSongs : T.waiting;
                return;
            }

            // The owner blocked embedding — don't even try, just hand out the link.
            if (s.embeddable === false) {
                ensureStopped();
                showMessage(T.cannotPlay, T.cannotPlayHint,
                    [{ label: T.openYoutube, href: s.watch_url }]);
                if (statusEl) statusEl.textContent = T.cannotPlay;
                return;
            }

            // A clip that already errored would fail again on every poll, so keep
            // the explanation up until the host moves on to another song.
            if (videoId === failedVideoId) {
                ensureStopped();
                if (msgEl.hidden && failedMessage) {
                    showMessage(failedMessage.head, failedMessage.note, failedMessage.actions);
                }
                return;
            }
            failedVideoId = null;

            if (!ytApiReady) {
                log('state', 'YT API not ready, will apply on ready');
                return;
            }

            var startOffset = s.start_offset || 0;
            var elapsed = elapsedSec(s);

            if (playerState === 'none') {
                behindCurtain(function () { createPlayer(videoId, startOffset, elapsedSec(s)); });
            } else if (playerState === 'creating') {
                // Wait for onReady — it re-applies lastState.
            } else if (currentVideoId !== videoId) {
                behindCurtain(function () { loadVideo(videoId, startOffset, elapsedSec(s)); });
            } else {
                syncTime(startOffset + elapsed);
            }
        }

        // ===== LOCAL TICK: countdown + pre-emptive fade =====
        function tick() {
            var s = lastState;
            if (!s) return;

            if (s.phase === 'paused') {
                var left = Math.max(0, Math.ceil(s.settings.pause_duration - elapsedSec(s)));
                var txt = T.paused.replace('%d', left);
                showMessage(txt, '', []);
                if (statusEl) statusEl.textContent = txt;
                return;
            }

            if (s.phase !== 'playing' || !s.current_video_id || s.embeddable === false) return;
            if (s.current_video_id === failedVideoId) return; // keep the failure notice up

            var remainingMs = (s.settings.play_duration - elapsedSec(s)) * 1000;
            if (statusEl) {
                statusEl.textContent = T.remaining.replace('%d', Math.max(0, Math.ceil(remainingMs / 1000)));
            }

            if (remainingMs <= FADE_LEAD_MS) {
                // Ease out before the server cuts the clip.
                setCurtain(true);
            } else if (ytPlaying && currentVideoId === s.current_video_id) {
                setCurtain(false);
                hideMessage();
            }
        }

        // ===== PLAYER MANAGEMENT =====
        function playerVars(startSeconds) {
            return {
                autoplay: 1,
                controls: showControls ? 1 : 0,
                disablekb: 1,
                rel: 0,
                fs: 1,
                playsinline: 1,
                cc_load_policy: showCaptions ? 1 : 0,
                iv_load_policy: 3,
                start: Math.max(0, Math.floor(startSeconds))
            };
        }

        function createPlayer(videoId, startOffset, elapsed) {
            log('player', 'Creating player for ' + videoId);
            playerState = 'creating';
            document.getElementById('yt-player').innerHTML = '';

            player = new YT.Player('yt-player', {
                width: '100%',
                height: '100%',
                videoId: videoId,
                playerVars: playerVars(startOffset + elapsed),
                events: {
                    onReady: function (ev) {
                        playerState = 'playing';
                        currentVideoId = videoId;
                        try { baseVolume = ev.target.getVolume() || 100; } catch (e) { baseVolume = 100; }
                        try { ev.target.setVolume(0); } catch (e) {}
                        applyCaptions();
                        ev.target.playVideo();
                        armStallTimer();
                        if (lastState) applyState(lastState);
                    },
                    onStateChange: function (ev) {
                        if (ev.data === YT.PlayerState.PLAYING) {
                            playerState = 'playing';
                            ytPlaying = true;
                            clearStallTimer();
                            hideMessage();
                            applyCaptions();
                            tick(); // clears the curtain as soon as we are allowed to
                        } else {
                            ytPlaying = false;
                            if (ev.data === YT.PlayerState.ENDED) playerState = 'idle';
                        }
                    },
                    onError: function (ev) { handlePlayerError(ev.data); }
                }
            });
        }

        function loadVideo(videoId, startOffset, elapsed) {
            log('player', 'Loading ' + currentVideoId + ' -> ' + videoId);
            playerState = 'playing';
            currentVideoId = videoId;
            ytPlaying = false;
            try {
                player.loadVideoById({
                    videoId: videoId,
                    startSeconds: Math.max(0, Math.floor(startOffset + elapsed))
                });
                applyCaptions();
                armStallTimer();
            } catch (e) {
                log('load-err', e.message);
                playerState = 'none';
                currentVideoId = null;
                createPlayer(videoId, startOffset, elapsed);
            }
        }

        function ensureStopped() {
            clearStallTimer();
            setCurtain(true);
            ytPlaying = false;
            if (playerState === 'playing' || playerState === 'idle') {
                try { player.stopVideo(); } catch (e) {}
                playerState = 'idle';
                currentVideoId = null;
            }
        }

        function syncTime(seekTo) {
            if (playerState !== 'playing') return;
            try {
                var cur = player.getCurrentTime();
                if (Math.abs(cur - seekTo) > 5) {
                    log('sync', Math.floor(cur) + 's -> ' + Math.floor(seekTo) + 's');
                    player.seekTo(seekTo, true);
                }
            } catch (e) {}
        }

        /**
         * 101/150 mean the owner disallowed embedding, 100/5/2 mean the clip is
         * gone or broken. Either way the video will never appear, so surface a
         * link instead of leaving everyone staring at a black rectangle.
         */
        function handlePlayerError(code) {
            log('player', 'YT error ' + code);
            playerState = 'idle';
            ytPlaying = false;
            clearStallTimer();
            setCurtain(true);

            var blocked = (code === 101 || code === 150);
            var url = lastState && lastState.watch_url;
            failedVideoId = (lastState && lastState.current_video_id) || currentVideoId;
            failedMessage = {
                head: blocked ? T.cannotPlay : T.unavailable,
                note: blocked ? T.cannotPlayHint : '',
                actions: url ? [{ label: T.openYoutube, href: url }] : []
            };
            currentVideoId = null;

            showMessage(failedMessage.head, failedMessage.note, failedMessage.actions);
            if (statusEl) statusEl.textContent = failedMessage.head;
        }

        /**
         * Browsers block autoplay until the page has been interacted with. If
         * nothing is playing shortly after a load, offer a manual start rather
         * than sitting behind a black curtain.
         */
        function armStallTimer() {
            clearStallTimer();
            stallTimer = setTimeout(function () {
                if (ytPlaying) return;
                setCurtain(false);
                showMessage('', '', [{
                    label: '▶ ' + T.play,
                    onClick: function () { try { player.playVideo(); } catch (e) {} }
                }]);
            }, STALL_MS);
        }

        function clearStallTimer() {
            if (stallTimer) { clearTimeout(stallTimer); stallTimer = null; }
        }

        // ===== CAPTIONS =====
        function applyCaptions() {
            if (!player) return;
            try {
                if (showCaptions) {
                    player.loadModule('captions');
                    player.loadModule('cc');
                } else {
                    // cc_load_policy alone is not enough once YouTube remembers a
                    // per-account caption preference, so drop the modules too.
                    player.unloadModule('captions');
                    player.unloadModule('cc');
                }
            } catch (e) {}
        }

        // ===== VIEW TOGGLES =====
        var ctrlToggle = document.getElementById('ctrlToggle');
        var ccToggle = document.getElementById('ccToggle');
        ctrlToggle.checked = showControls;
        ccToggle.checked = showCaptions;

        ctrlToggle.addEventListener('change', function () {
            showControls = ctrlToggle.checked;
            setPref('sg-controls', showControls);
            document.body.classList.toggle('controls-on', showControls);
            // playerVars are fixed at construction, so the player has to be rebuilt.
            rebuildPlayer();
        });

        ccToggle.addEventListener('change', function () {
            showCaptions = ccToggle.checked;
            setPref('sg-captions', showCaptions);
            applyCaptions();
        });

        function rebuildPlayer() {
            behindCurtain(function () {
                try { if (player && player.destroy) player.destroy(); } catch (e) {}
                player = null;
                playerState = 'none';
                currentVideoId = null;
                ytPlaying = false;
                if (lastState) applyState(lastState);
            });
        }

        // ===== ZEN / FULLSCREEN =====
        var zenBtn = document.getElementById('zenBtn');
        var fsBtn = document.getElementById('fsBtn');
        var stage = document.getElementById('video-stage');

        function setZen(on) {
            document.body.classList.toggle('zen', on);
            zenBtn.title = on ? T.zenExit : T.zenEnter;
        }

        zenBtn.addEventListener('click', function () {
            setZen(!document.body.classList.contains('zen'));
        });

        fsBtn.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (stage.requestFullscreen) {
                stage.requestFullscreen().catch(function () { setZen(true); });
            } else {
                setZen(true); // no Fullscreen API (older iOS Safari) — zen is the fallback
            }
        });

        document.addEventListener('fullscreenchange', function () {
            var on = !!document.fullscreenElement;
            fsBtn.title = on ? T.fullscreenExit : T.fullscreen;
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.target && /^(INPUT|TEXTAREA|SELECT)$/.test(ev.target.tagName)) return;
            if (ev.key === 'z') setZen(!document.body.classList.contains('zen'));
            else if (ev.key === 'f') fsBtn.click();
            else if (ev.key === 'Escape' && document.body.classList.contains('zen')) setZen(false);
        });

        // ===== ADMIN CONTROLS =====
        if (isAdmin) {
            var sendAction = function (action) {
                return fetch('game-state.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: new URLSearchParams({ action: action })
                }).then(function (r) {
                    if (r.ok) poll(); // Immediately refresh state after action
                }).catch(function (e) { log('admin-err', e.message); });
            };

            var nBtn = document.getElementById('nextBtn');
            var sBtn = document.getElementById('stopBtn');
            if (nBtn) nBtn.addEventListener('click', function () { sendAction('next'); });
            if (sBtn) sBtn.addEventListener('click', function () { sendAction('stop'); });
        }

        // ===== INITIALIZATION =====
        function onYTReady() {
            log('init', 'YT API ready');
            ytApiReady = true;
            if (lastState) applyState(lastState);
        }

        if (typeof window.onYouTubeIframeAPIReady !== 'function') {
            window.onYouTubeIframeAPIReady = onYTReady;
        } else {
            var orig = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                try { orig.call(this); } catch (e) {}
                onYTReady();
            };
        }

        // The API normally signals readiness through that callback, but if it was
        // already loaded by the time this script runs the callback never fires.
        if (window.YT && window.YT.Player) onYTReady();

        startPolling();
    })();
    </script>
<?php sg_page_end(); ?>
