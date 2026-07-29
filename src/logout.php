<?php
require_once 'bootstrap.php';

// If the admin is logging out, mark the session as having no active host
if (Auth::isAuthenticated() && Auth::isAdmin()) {
    $game = $db->fetchOne("SELECT id FROM sessions WHERE game_code = ?", [$_SESSION['game_code']]);
    if ($game) {
        $db->execute("UPDATE sessions SET is_host_active = 0 WHERE id = ?", [$game['id']]);
    }
}

Auth::logout();
