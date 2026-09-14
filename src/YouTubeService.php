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
     * Get video metadata.
     *
     * Prefers the Data API (authoritative about embedding), but falls back to
     * YouTube's oEmbed endpoint, which needs no API key. Without that fallback
     * every title reads "Unknown Title" on installs that never configured
     * YT_API_KEY.
     *
     * 'embeddable' is false for clips whose owner only allows playback on
     * youtube.com — those would fail silently in our iframe, so the submitter
     * gets told right away and the viewer can offer a direct link instead.
     * Returns null when the video does not exist or nothing could be reached.
     */
    public function getVideoDetails($videoId) {
        if (self::looksLikeApiKey($this->apiKey)) {
            $details = $this->fetchFromDataApi($videoId);
            if ($details) {
                return $details;
            }
        }
        return self::fetchFromOEmbed($videoId);
    }

    /** A placeholder such as '...' must not cost us a doomed round trip. */
    private static function looksLikeApiKey($key) {
        return is_string($key) && preg_match('/^[A-Za-z0-9_\-]{20,}$/', $key) === 1;
    }

    private function fetchFromDataApi($videoId) {
        $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,status&id="
            . urlencode($videoId) . "&key=" . urlencode($this->apiKey);
        list($status, $body) = self::httpGet($url);
        $response = json_decode((string)$body, true);

        if ($status !== 200 || isset($response['error'])) {
            self::logFailure('YouTube Data API request failed', [
                'video_id' => $videoId,
                'status'   => $status,
                'message'  => $response['error']['message'] ?? substr((string)$body, 0, 200),
            ]);
            return null;
        }
        if (empty($response['items'])) {
            return null;
        }

        $item = $response['items'][0];
        return [
            'title' => $item['snippet']['title'],
            'thumbnail' => $item['snippet']['thumbnails']['default']['url'],
            // Absent 'embeddable' means the API answered without the status
            // part; assume playable rather than blocking a valid song.
            'embeddable' => !isset($item['status']['embeddable']) || (bool)$item['status']['embeddable'],
        ];
    }

    /**
     * Key-free metadata via oEmbed.
     * 200 = fine, 401 = the owner disallows embedding, 404 = no such video.
     */
    private static function fetchFromOEmbed($videoId) {
        $url = 'https://www.youtube.com/oembed?url='
            . urlencode('https://www.youtube.com/watch?v=' . $videoId) . '&format=json';
        list($status, $body) = self::httpGet($url);
        return self::interpretOEmbed($status, $body, $videoId);
    }

    /** Pure status/body -> result mapping, kept separate from the I/O so it is testable. */
    private static function interpretOEmbed($status, $body, $videoId = '') {
        if ($status === 401 || $status === 403) {
            // Embedding is blocked, so oEmbed will not tell us the title.
            return ['title' => null, 'thumbnail' => null, 'embeddable' => false];
        }
        if ($status !== 200) {
            if ($status !== 404) {
                self::logFailure('YouTube oEmbed request failed', [
                    'video_id' => $videoId, 'status' => $status,
                ]);
            }
            return null;
        }

        $data = json_decode((string)$body, true);
        if (!isset($data['title'])) {
            return null;
        }
        return [
            'title' => $data['title'],
            'thumbnail' => $data['thumbnail_url'] ?? null,
            'embeddable' => true,
        ];
    }

    /** Returns [httpStatus, body]; status is 0 when the request never completed. */
    private static function httpGet($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status === 0) {
            // Usually a missing CA bundle or no outbound network — worth logging,
            // because otherwise every title silently degrades to "Unknown Title".
            self::logFailure('HTTP request to YouTube failed', ['url' => $url, 'error' => $error]);
            return [0, null];
        }
        return [$status, $body];
    }

    private static function logFailure($message, $context) {
        if (function_exists('goose_log')) {
            goose_log($message, $context);
        }
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
