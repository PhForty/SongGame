<?php
require_once 'bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['join'])) {
        $code = strtoupper(trim($_POST['game_code']));
        $game = $db->fetchOne("SELECT id, is_host_active FROM sessions WHERE game_code = ?", [$code]);
        if ($game) {
            Auth::login($code, session_id());
            if (!$game['is_host_active']) {
                Auth::setAsHost();
                $db->execute("UPDATE sessions SET is_host_active = 1 WHERE id = ?", [$game['id']]);
            }
            header("Location: eingabe.php");
            exit;
        } else {
            header("Location: index.php?error=" . urlencode(__('invalid_code')));
            exit;
        }
    } elseif (isset($_POST['create'])) {
        $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
        $db->execute("INSERT INTO sessions (game_code, is_host_active) VALUES (?, ?)", [$code, 1]);
        Auth::login($code, session_id());
        Auth::setAsHost();
        header("Location: eingabe.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('title_join'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="wrapper">
        <div class="lang-toggle" style="text-align: right; margin-bottom: 1rem;">
            <a href="?lang=de" style="<?php echo $_SESSION['lang'] === 'de' ? 'font-weight: bold;' : ''; ?>">🇩🇪</a> | 
            <a href="?lang=en" style="<?php echo $_SESSION['lang'] === 'en' ? 'font-weight: bold;' : ''; ?>">🇺🇸</a>
        </div>
        <div class="card">
            <h1>🎵 SongGame</h1>
            <p><?php echo __('welcome'); ?></p>
            
            <?php if (isset($_GET['error'])): ?>
                <p style="color: red;"><?php echo htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>

            <form method="POST" style="margin-bottom: 2rem;">
                <div class="form-group">
                    <input type="text" name="game_code" placeholder="<?php echo __('enter_code'); ?>" maxlength="10" required>
                </div>
                <button type="submit" name="join" class="btn btn-primary"><?php echo __('join_game'); ?></button>
            </form>

            <div style="margin: 1rem 0; color: #888; text-align: center;"><?php echo __('or'); ?></div>

            <form method="POST">
                <button type="submit" name="create" class="btn btn-secondary"><?php echo __('create_game'); ?></button>
            </form>
        </div>
    </div>
</body>
</html>
