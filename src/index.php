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

// Scanning the host's QR code lands here with the code already filled in.
$prefill = strtoupper(trim($_GET['code'] ?? ''));

sg_page_start(__('title_join'));
?>
    <div id="wrapper">
        <header>
            <span></span>
            <?php sg_theme_tools(); ?>
        </header>
        <div class="card">
            <h1>&#127925; SongGame</h1>
            <p><?= __('welcome') ?></p>

            <?php if (isset($_GET['error'])): ?>
                <p class="msg-error"><?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>

            <form method="POST" style="margin-bottom: 2rem;">
                <div class="form-group">
                    <input type="text" name="game_code" placeholder="<?= __('enter_code') ?>"
                           maxlength="10" required autocomplete="off"
                           value="<?= htmlspecialchars($prefill) ?>"
                           <?= $prefill === '' ? 'autofocus' : '' ?>>
                </div>
                <button type="submit" name="join" class="btn btn-primary"><?= __('join_game') ?></button>
            </form>

            <div class="muted" style="margin: 1rem 0; text-align: center;"><?= __('or') ?></div>

            <form method="POST">
                <button type="submit" name="create" class="btn btn-secondary"><?= __('create_game') ?></button>
            </form>
        </div>
    </div>
<?php sg_page_end(); ?>
