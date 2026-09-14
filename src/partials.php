<?php
/**
 * Shared page chrome.
 *
 * Every page goes through sg_page_start()/sg_header() so the theme bootstrap is
 * identical everywhere. That matters: the theme has to be on <html> before the
 * first paint, otherwise reloading flashes a white page before the dark assets
 * apply.
 */

/**
 * Opens the document and emits <head> plus <body>.
 *
 * @param string $title Page title (already translated).
 * @param array  $opts  'body_class' => extra classes on <body>.
 */
function sg_page_start($title, $opts = []) {
    $lang = $_SESSION['lang'] ?? 'de';
    $bodyClass = $opts['body_class'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" href="favicon.ico">
    <?php /* Runs before the stylesheet paints, so there is no white flash on reload. */ ?>
    <script>
    (function () {
        try {
            var t = localStorage.getItem('theme');
            if (t !== 'dark' && t !== 'light') {
                t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
                    ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.style.colorScheme = t;
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>
    <link rel="stylesheet" href="style.css">
</head>
<body<?= $bodyClass ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
<?php
}

/**
 * Language switch + theme toggle. Shown on the start screen too, so the theme
 * can be picked before joining a game.
 */
function sg_theme_tools($extraHtml = '') {
    $lang = $_SESSION['lang'] ?? 'de';
    ?>
    <div class="header-tools">
        <?= $extraHtml ?>
        <div class="lang-switch">
            <a href="?lang=de"<?= $lang === 'de' ? ' style="font-weight: bold;"' : '' ?>>&#127465;&#127466;</a> |
            <a href="?lang=en"<?= $lang === 'en' ? ' style="font-weight: bold;"' : '' ?>>&#127482;&#127480;</a>
        </div>
        <button id="themeToggle" type="button" class="theme-toggle" aria-live="polite">&#127769;</button>
    </div>
    <?php
}

/**
 * Full navigation header for the signed-in pages.
 *
 * @param string $active    One of submit|admin|game.
 * @param bool   $isAdmin   Whether to show the admin link.
 * @param string $extraHtml Extra controls placed left of the language switch.
 */
function sg_header($active, $isAdmin, $extraHtml = '') {
    $cls = function ($name) use ($active) {
        return $active === $name ? ' class="active"' : '';
    };
    ?>
    <header class="zen-hide">
        <ul class="nav-menu">
            <li><a href="eingabe.php"<?= $cls('submit') ?>><?= __('nav_submit') ?></a></li>
            <?php if ($isAdmin): ?>
                <li><a href="admin-view.php"<?= $cls('admin') ?>><?= __('nav_admin') ?></a></li>
            <?php endif; ?>
            <li><a href="viewer.php"<?= $cls('game') ?>><?= __('nav_game') ?></a></li>
            <li><a href="logout.php"><?= __('nav_logout') ?></a></li>
        </ul>
        <?php sg_theme_tools($extraHtml); ?>
    </header>
    <?php
}

/** Closes the document and wires up the theme toggle. */
function sg_page_end() {
    ?>
    <script src="theme.js"></script>
</body>
</html>
<?php
}

/**
 * "Share" button plus the QR dialog it opens. The QR is generated client side
 * (qrcode.js) so nothing leaves the network the game is running on.
 */
function sg_share_modal($gameCode) {
    $joinUrl = sg_join_url($gameCode);
    ?>
    <div id="shareModal" class="modal" hidden>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="shareTitle">
            <h2 id="shareTitle">&#128241; <?= __('share_game') ?></h2>
            <p class="muted"><?= __('share_hint') ?></p>
            <div class="qr-holder" id="shareQr" data-url="<?= htmlspecialchars($joinUrl) ?>"></div>
            <p class="join-code"><?= htmlspecialchars($gameCode) ?></p>
            <p class="join-url"><?= htmlspecialchars($joinUrl) ?></p>
            <p>
                <button type="button" id="shareCopy" class="btn btn-secondary btn-small"
                        data-copied="<?= htmlspecialchars(__('copied')) ?>"><?= __('copy_link') ?></button>
                <button type="button" id="shareClose" class="btn btn-primary btn-small"><?= __('close') ?></button>
            </p>
        </div>
    </div>
    <script src="qrcode.js"></script>
    <script src="share.js"></script>
    <?php
}

/** Button that opens the share dialog; pair it with sg_share_modal(). */
function sg_share_button($class = 'btn btn-secondary btn-small') {
    ?>
    <button type="button" id="shareOpen" class="<?= htmlspecialchars($class) ?>">&#128279; <?= __('share_game') ?></button>
    <?php
}

/**
 * Absolute URL players use to join this game, kept short so the QR code stays
 * a low version and scans easily.
 */
function sg_join_url($gameCode) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/?code=' . rawurlencode($gameCode);
}

/** Legacy sentinel stored before titles could be looked up without an API key. */
const SG_UNKNOWN_TITLE = 'Unknown Title';

/** True when a song row has no usable title yet. */
function sg_title_missing($title) {
    return $title === null || trim($title) === '' || $title === SG_UNKNOWN_TITLE;
}

/** What to show for a song — the video id beats a placeholder. */
function sg_song_label(array $song) {
    return sg_title_missing($song['title'] ?? null) ? $song['video_id'] : $song['title'];
}

/**
 * Fills in titles that were stored before a lookup was possible (for example on
 * installs that never configured YT_API_KEY, where every song became
 * "Unknown Title"). Bounded per request so a page never stalls, and self-healing
 * across reloads. Stops at the first hard failure so an unreachable YouTube
 * costs one timeout rather than $limit of them.
 *
 * @return array The songs with repaired titles applied.
 */
function sg_repair_titles(Database $db, $sessionId, array $songs, $limit = 8) {
    $yt = new YouTubeService(YT_API_KEY);
    $done = 0;

    foreach ($songs as $i => $song) {
        if ($done >= $limit) {
            break;
        }
        if (!sg_title_missing($song['title'] ?? null)) {
            continue;
        }

        $details = $yt->getVideoDetails($song['video_id']);
        if ($details === null) {
            break; // YouTube unreachable or video gone — do not hammer it
        }
        $done++;
        if (empty($details['title'])) {
            continue;
        }

        $embeddable = (int)$details['embeddable'];
        $db->execute(
            "UPDATE songs SET title = ?, embeddable = ? WHERE id = ? AND session_id = ?",
            [$details['title'], $embeddable, $song['id'], $sessionId]
        );
        $songs[$i]['title'] = $details['title'];
        $songs[$i]['embeddable'] = $embeddable;
    }

    return $songs;
}

/** Thumbnail URL for a video id — derived, so listing the pot costs no API quota. */
function sg_thumb_url($videoId) {
    return 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/mqdefault.jpg';
}

/** Watch URL, used whenever a clip refuses to play embedded. */
function sg_watch_url($videoId, $startOffset = 0) {
    $url = 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
    if ($startOffset > 0) {
        $url .= '&t=' . (int)$startOffset;
    }
    return $url;
}
