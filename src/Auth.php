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

    /**
     * Alphabet for game codes: uppercase alphanumerics minus every pair that
     * players misread off a screen or a QR dialog. Dropped are 0/O/Q, 1/I/L,
     * 8/B, 5/S, 2/Z, 6/G and U/V — both members of each pair, so a shown code
     * can never be ambiguous rather than merely decodable. 21 symbols still
     * give 21^5 = 4,084,101 codes, roughly four times the old hex range.
     */
    const CODE_ALPHABET = '3479ACDEFHJKMNPRTVWXY';
    const CODE_LENGTH = 5;

    /**
     * A fresh game code that is not yet taken. `game_code` is UNIQUE, so an
     * unchecked INSERT would surface a collision as a blank 500; retrying a few
     * times is cheap and the odds of exhausting the attempts are negligible.
     *
     * @throws Exception when no free code turned up.
     */
    public static function generateGameCode(Database $db, $attempts = 10) {
        for ($i = 0; $i < $attempts; $i++) {
            $code = '';
            for ($c = 0; $c < self::CODE_LENGTH; $c++) {
                // random_int() is uniform over the alphabet; the old
                // substr(md5(...)) was biased to hex digits by construction.
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $taken = $db->fetchOne("SELECT 1 AS present FROM sessions WHERE game_code = ?", [$code]);
            if (!$taken) {
                return $code;
            }
        }
        throw new Exception('Could not allocate a free game code');
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
