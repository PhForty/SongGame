<?php
/**
 * YouTube Service providing interactions with Google API
 */
class YouTubeService {
    private $apiKey;
    private $accessToken;

    public function __construct($apiKey, $accessToken = null) {
        $this->apiKey = $apiKey;
        $this->accessToken = $accessToken;
    }

    /**
     * Extracts Video ID from various types of YouTube URLs
     */
    public static function extractVideoId($url) {
        $parsed = self::parseYouTubeUrl($url);
        return $parsed ? $parsed['video_id'] : null;
    }

    /**
     * Returns ['video_id' => string, 'start_offset' => int] or null.
     * Handles t=, start= params and youtu.be URLs with timestamps.
     */
    public static function parseYouTubeUrl($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        if (!preg_match($pattern, $url, $matches)) {
            return null;
        }
        $videoId = $matches[1];

        // Parse timestamp from query string: t=90, t=1m30s, start=90
        $offset = 0;
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            $raw = $params['t'] ?? $params['start'] ?? null;
            if ($raw !== null) {
                $offset = self::parseTimestamp((string)$raw);
            }
        }
        // youtu.be URLs may also embed t in the fragment (#t=90)
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if ($offset === 0 && $fragment && preg_match('/t=(\S+)/', $fragment, $fm)) {
            $offset = self::parseTimestamp($fm[1]);
        }

        return ['video_id' => $videoId, 'start_offset' => $offset];
    }

    /**
     * Parses a YouTube timestamp string into seconds.
     * Supports: 90, 1m30s, 1h2m3s, 1h30m
     */
    private static function parseTimestamp($raw) {
        // Plain integer seconds
        if (ctype_digit($raw)) {
            return (int)$raw;
        }
        // h/m/s notation
        $seconds = 0;
        if (preg_match('/(\d+)h/', $raw, $m)) $seconds += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m/', $raw, $m)) $seconds += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $raw, $m)) $seconds += (int)$m[1];
        return $seconds;
    }

    /**
     * Get video metadata using API Key
     */
    public function getVideoDetails($videoId) {
        $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id=" . urlencode($videoId) . "&key=" . urlencode($this->apiKey);
        $response = $this->makeRequest($url);
        if (!empty($response['items'])) {
            return [
                'title' => $response['items'][0]['snippet']['title'],
                'thumbnail' => $response['items'][0]['snippet']['thumbnails']['default']['url']
            ];
        }
        return null;
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     * Returns ['access_token' => ..., 'refresh_token' => ...] or null.
     */
    public static function exchangeAuthCode(string $code, string $redirectUri): ?array {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'code'          => $code,
            'client_id'     => YT_CLIENT_ID,
            'client_secret' => YT_CLIENT_SECRET,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]));
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return isset($result['access_token']) ? $result : null;
    }

    /**
     * Use a refresh token to get a fresh access token.
     * Returns access token string or null.
     */
    public static function refreshAccessToken(string $refreshToken): ?string {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'refresh_token' => $refreshToken,
            'client_id'     => YT_CLIENT_ID,
            'client_secret' => YT_CLIENT_SECRET,
            'grant_type'    => 'refresh_token',
        ]));
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $result['access_token'] ?? null;
    }

    /**
     * Create an unlisted playlist and add all given video IDs.
     * Returns the playlist URL or null on failure.
     */
    public function createGamePlaylist(string $title, array $videoIds): ?string {
        if (!$this->accessToken) return null;

        $playlistId = $this->createPlaylist($title);
        if (!$playlistId) return null;

        foreach ($videoIds as $videoId) {
            $this->addVideoToPlaylist($playlistId, $videoId);
        }

        return 'https://www.youtube.com/playlist?list=' . $playlistId;
    }

    /**
     * Create an unlisted playlist. REQUIRES ACCESS TOKEN (OAuth2)
     */
    public function createPlaylist($title, $description = "Game Playlist") {
        if (!$this->accessToken) return null;

        $url = "https://www.googleapis.com/youtube/v3/playlists?part=snippet,status";
        $data = [
            'snippet' => [
                'title'       => $title,
                'description' => $description,
            ],
            'status' => ['privacyStatus' => 'unlisted']
        ];

        $response = $this->makeRequest($url, 'POST', $data);
        return $response['id'] ?? null;
    }

    /**
     * Add a video to a playlist. REQUIRES ACCESS TOKEN (OAuth2)
     */
    public function addVideoToPlaylist($playlistId, $videoId) {
        if (!$this->accessToken) return false;

        $url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet";
        $data = [
            'snippet' => [
                'playlistId' => $playlistId,
                'resourceId' => ['kind' => 'youtube#video', 'videoId' => $videoId]
            ]
        ];

        $response = $this->makeRequest($url, 'POST', $data);
        return isset($response['id']);
    }

    private function makeRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken
            ]);
        }

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
}
?>
