<?php
/**
 * Authentication and Session Management
 */
class Auth {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => $cookieParams['path'],
                'domain' => $cookieParams['domain'],
                'secure' => false, // set to true in production HTTPS environments
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login($gameCode, $sessionId) {
        $_SESSION['game_code'] = $gameCode;
        $_SESSION['user_id'] = $sessionId;
        $_SESSION['role'] = 'player';
    }

    public static function setAsHost() {
        $_SESSION['role'] = 'admin';
    }

    public static function isAuthenticated() {
        return isset($_SESSION['game_code']) && isset($_SESSION['user_id']);
    }

    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public static function logout() {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    public static function requireAuth($db) {
        if (!self::isAuthenticated()) {
            header("Location: index.php");
            exit;
        }

        // Verify game exists in DB
        $game = $db->fetchOne("SELECT id FROM sessions WHERE game_code = ?", [$_SESSION['game_code']]);
        if (!$game) {
            self::logout();
        }
    }

    public static function requireAdmin($db) {
        self::requireAuth($db);
        if (!self::isAdmin()) {
            header("Location: eingabe.php");
            exit;
        }
    }
}
?>
