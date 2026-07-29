<?php
require_once 'bootstrap.php';
Auth::requireAuth($db);

$gameCode = $_SESSION['game_code'];
$game = $db->fetchOne("SELECT * FROM sessions WHERE game_code = ?", [$gameCode]);
$sessionId = $game['id'];

if (!isset($game['playlist_id']) || empty($game['playlist_id'])) {
    echo "Playlist is being prepared...";
} else {
    $playlistUrl = "https://www.youtube.com/playlist?list=" . $game['playlist_id'];
    echo "Your game playlist: <a href='$playlistUrl' target='_blank'>$playlistUrl</a>";
}
?>
