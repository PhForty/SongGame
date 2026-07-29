# SongGame Architecture

SongGame is a synchronized music experience where players submit YouTube videos and a host controls the playback for everyone in real-time.

## Technical Stack
- **Backend:** PHP 8+
- **Database:** MySQL/MariaDB
- **Frontend:** HTML, CSS (Custom), JavaScript (Vanilla)
- **APIs:** YouTube Data API v3 (for thumbnails and metadata) & YouTube IFrame API (for synchronized playback)

## File Structure
- `src/config.php`: Global configuration, database credentials, and API keys.
- `src/bootstrap.php`: Initializes the environment, includes dependencies, and starts the session.
- `src/Database.php`: Custom wrapper for MySQLi to provide simplified query execution and fetching.
- `src/Auth.php`: Handles user sessions, game codes, and role-based access control (Admin vs Player).
- `src/YouTubeService.php`: Service class for extracting video IDs and fetching metadata via the YouTube API.
- `src/schema.sql`: Database schema definition.

### Pages
- `index.php`: Entry point. Users can join a game using a code or create a new one.
- `eingabe.php`: Submission page. Players add YouTube links. Shows a dropdown of their own submissions.
- `admin-view.php`: Host control panel. Configure autoplay, pause duration, and play durations per clip. View the "pot" with thumbnails.
- `viewer.php`: The synchronized game view. Uses polling to fetch current state from `game-state.php`.
- `game-state.php`: API endpoint that handles synchronization logic (picking random songs) and returns current playback status.
- `logout.php`: Destroys session and redirects to home.

## Synchronization Logic
1. **The Pot:** Admin triggers "Next Video" in `viewer.php`.
2. **Server Side:** `game-state.php` picks a random unmarked song from the `songs` table, marks it as viewed, and updates the `sessions` table with the `current_video_id` and a timestamp (`started_at`).
3. **Client Side (Polling):** All connected players poll `game-state.php` every 3 seconds.
4. **Sync:** If the current video ID changes or if there is a time drift (>5s), the JavaScript player seeks to the correct position based on `(Now - started_at)`.
5. **Duration Control:** Clients automatically stop playback once `elapsed >= play_duration` from settings.

## Security Implementations
- **Prepared Statements:** All database queries use MySQLi prepared statements to prevent SQL Injection.
- **Server-Side AC:** Access control for Admin pages is verified on the server via `Auth::requireAdmin()`.
- **XSS Prevention:** User-supplied data (titles, video IDs) are escaped using `htmlspecialchars()` before rendering.
- **Session Security:** Sessions use `HttpOnly` and `SameSite=Lax` cookies to mitigate session hijacking and CSRF.

## Future Agents Guide
- To add new features: Update `schema.sql` if needed -> Add logic in `YouTubeService` or `Auth` -> Implement UI changes.
- To change sync frequency: Adjust the `setInterval` value in `viewer.php`.
- To update API keys: Use `src/config.php`.
