<?php
require_once 'bootstrap.php';
Auth::requireAuth($db);

$userId = $_SESSION['user_id'];
$gameCode = $_SESSION['game_code'];
$game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];

$message = "";
$messageType = ""; // success or error

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
            // Get titles via API if possible (optional but nice for the dropdown/admin view)
            $yt = new YouTubeService(YT_API_KEY);
            $details = $yt->getVideoDetails($videoId);
            $title = $details ? $details['title'] : "Unknown Title";

            $db->execute("INSERT INTO songs (session_id, user_id, video_id, start_offset, title) VALUES (?, ?, ?, ?, ?)", [$sessionId, $userId, $videoId, $startOffset, $title]);
            $message = __('song_added');
            $messageType = "success";
        }
    }
}

// Get songs submitted by current user for the dropdown
$mySongs = $db->fetchAll("SELECT video_id, title FROM songs WHERE session_id = ? AND user_id = ?", [$sessionId, $userId]);
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('title_submit'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="wrapper">
        <header>
            <nav class="nav-menu">
                <li><a href="eingabe.php"><?php echo __('nav_submit'); ?></a></li>
                <?php if (Auth::isAdmin()): ?>
                    <li><a href="admin-view.php"><?php echo __('nav_admin'); ?></a></li>
                <?php endif; ?>
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
            <h1>🎵 <?php echo __('submit_song'); ?></h1>
            <p><?php echo __('game_code'); ?>: <strong><?php echo htmlspecialchars($gameCode); ?></strong></p>

            <?php if ($message): ?>
                <p style="color: <?php echo $messageType === 'success' ? 'green' : 'red'; ?>; font-weight: bold;">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="song_url"><?php echo __('youtube_url'); ?></label>
                    <input type="text" id="song_url" name="song_url" placeholder="https://www.youtube.com/watch?v=..." required>
                </div>

                <div class="form-group">
                    <label for="my_songs"><?php echo __('my_songs'); ?></label>
                    <select id="my_songs" name="my_songs">
                        <option value="<?php echo __('see_added'); ?>"><?php echo __('see_added'); ?></option>
                        <?php foreach ($mySongs as $song): ?>
                            <option value="<?php echo htmlspecialchars($song['video_id']); ?>">
                                <?php echo htmlspecialchars($song['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="submit_song" class="btn btn-primary"><?php echo __('add_to_pot'); ?></button>
            </form>

            <div style="margin-top: 2rem; text-align: left; font-size: 0.9rem; color: #666;">
                <h3><?php echo __('how_it_works'); ?></h3>
                <ol>
                    <li><?php echo __('step1'); ?></li>
                    <li><?php echo __('step2'); ?></li>
                    <li><?php echo __('step3'); ?></li>
                </ol>
            </div>
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
