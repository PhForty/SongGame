# SongGame Architecture

SongGame is a synchronized music experience where players submit YouTube videos and a host controls the playback for everyone in real-time.

## Technical Stack
- **Backend:** PHP 8+
- **Database:** MySQL/MariaDB
- **Frontend:** HTML, CSS (Custom), JavaScript (Vanilla)
- **APIs:** YouTube Data API v3 (for thumbnails and metadata) & YouTube IFrame API (for synchronized playback)

## File Structure
- `src/config.php`: Global configuration. It only carries the docker-compose defaults; real credentials come from `src/config.local.php`, which is gitignored and written by the deploy pipeline from GitHub secrets. Every key falls back to the default when the local file is absent or leaves it empty, so a bare checkout still runs under `docker-compose up`.
- `src/partials.php`: Shared page chrome (`<head>`, nav header, share dialog) plus URL helpers. The theme bootstrap lives here — it sets `data-theme` on `<html>` from an inline script *before* the stylesheet, which is what prevents the white flash on reload.
- `src/Migrations.php`: Forward-only schema migrator. `schema.sql` only runs on a fresh database, so new columns are applied here and the applied version is stored in `app_config.schema_version`.
- `src/qrcode.js`: Dependency-free QR encoder (byte mode, ECC level M, versions 1-15) used for the host's share dialog, so the game needs no internet to hand out its join link.
- `src/theme.js` / `src/share.js`: Theme toggle and share dialog behaviour.
- `src/bootstrap.php`: Initializes the environment, includes dependencies, and starts the session.
- `src/Database.php`: Custom wrapper for MySQLi to provide simplified query execution and fetching.
- `src/Auth.php`: Handles user sessions, game codes, and role-based access control (Admin vs Player).
- `src/YouTubeService.php`: Extracts video IDs and fetches metadata. `getVideoDetails()` uses the Data API when `YT_API_KEY` looks like a real key and otherwise falls back to YouTube's **oEmbed** endpoint, which returns titles without any key — that fallback is why titles no longer degrade to "Unknown Title" on installs that never configured one. Failures are written to `app.log` via `goose_log()` instead of being swallowed.
- `src/schema.sql`: Database schema definition. It `DROP`s `sessions` and `songs`, so it is only ever applied to a fresh database or by a deliberate reset — additive changes belong in `Migrations.php`.

### Pages
- `index.php`: Entry point. Users can join a game using a code or create a new one.
- `eingabe.php`: Submission page. Players add YouTube links and see a list of their own submissions with titles, each deletable.
- `admin-view.php`: Host control panel. Configure autoplay, pause duration, and play durations per clip. The "pot" of thumbnails sits in a `<details>` that is collapsed by default so opening the settings does not spoil the game.
- `viewer.php`: The synchronized game view. Uses polling to fetch current state from `game-state.php`. Also owns the fade curtain, zen/fullscreen modes and the controls/captions toggles.
- `game-state.php`: API endpoint that handles synchronization logic (picking random songs) and returns current playback status.
- `logout.php`: Destroys session and redirects to home.

## Synchronization Logic
1. **The Pot:** Admin triggers "Next Video" in `viewer.php`.
2. **Server Side:** `game-state.php` picks a random unmarked song from the `songs` table, marks it as viewed, and updates the `sessions` table with the `current_video_id` and a timestamp (`started_at`).
3. **Client Side (Polling):** All connected players poll `game-state.php` every 3 seconds.
4. **Sync:** If the current video ID changes or if there is a time drift (>5s), the JavaScript player seeks to the correct position based on `(Now - started_at)`.
5. **Duration Control:** Clients automatically stop playback once `elapsed >= play_duration` from settings.
6. **Fades:** A local 200ms ticker raises a black curtain (`#video-fade`) and ramps the volume down `FADE_LEAD_MS` before the server cuts a clip, and every video swap happens behind that curtain. `FADE_MS` in `viewer.php` drives the CSS `--fade-ms` variable, so the two can never drift apart.

## Video Titles
Titles are resolved once at submission time and stored in `songs.title`. A row whose title is missing (NULL, empty, or the legacy `Unknown Title` sentinel) is repaired lazily by `sg_repair_titles()` when `eingabe.php` or `admin-view.php` renders it — at most 8 per request, and it stops at the first unresolvable row so an unreachable YouTube costs one timeout rather than eight. `sg_song_label()` falls back to the video ID rather than showing a placeholder.

## Unplayable Videos
Some clips are restricted to playback on youtube.com. `YouTubeService::getVideoDetails()` reads `status.embeddable` and stores it in `songs.embeddable`; the submitter is warned immediately, the admin pot marks them, and `game-state.php` passes `embeddable` plus a `watch_url` to the viewer, which shows a link instead of attempting the embed. Player errors 101/150 (and 100/5/2) are handled the same way at runtime, and a failed video is never retried until the host moves on.

## Deployment
`.github/workflows/deploy.yml` mirrors `src/` to songgame.de over FTPS on every push to `main` (see README.md for the secrets). Four details matter for future changes:
- **Everything FTP goes through `lftp`, configured once in `~/.lftprc`.** Two of those settings are not defaults and were both arrived at the hard way: `dns:order "inet"` (all-inkl publishes an AAAA record, and an IPv6 control connection leads to an EPSV data channel the runner cannot reach — `ECONNRESET (data socket)`), and `ftp:ssl-force` rather than an `ftps://` URL (in lftp that scheme means *implicit* TLS on port 990; all-inkl speaks explicit FTPS on 21). `cmd:fail-exit true` is what makes a failed transfer fail the step.
- **No permanent deploy endpoint.** Applying `schema.sql` needs a PHP process on the host, but leaving such a script on the server would be a standing "drop the database" URL. Instead the workflow renders `.github/scripts/apply-schema.php.tpl` into a randomly named file with a random per-run token, uploads it, calls it once over HTTPS, and deletes it again. The script also unlinks itself and hard-expires 10 minutes after it was generated, so all three safeguards have to fail together for it to linger.
- **The runner never reaches MySQL.** `DB_HOST` is only a string that gets written into `config.local.php`; the reset connects from PHP running on the all-inkl box, so the database user stays restricted to `localhost` and no runner IP has to be allowlisted. Do not "simplify" this into a `mysql` client step in the workflow — that would require opening the user to the public internet.
- **The reset is gated on a real diff.** The workflow only triggers it when `src/schema.sql` actually changed between `github.event.before` and `github.sha` (or when `force_schema` is set on a manual run), because the reset wipes every live game. Adding a column to an existing install is a `Migrations.php` step, not a schema.sql edit.

## Security Implementations
- **Prepared Statements:** All database queries use MySQLi prepared statements to prevent SQL Injection.
- **Server-Side AC:** Access control for Admin pages is verified on the server via `Auth::requireAdmin()`.
- **XSS Prevention:** User-supplied data (titles, video IDs) are escaped using `htmlspecialchars()` before rendering.
- **Session Security:** Sessions use `HttpOnly` and `SameSite=Lax` cookies to mitigate session hijacking and CSRF.

## Future Agents Guide
- To add new features: add a `Migrations.php` step *and* mirror it into `schema.sql` if a column is needed -> Add logic in `YouTubeService` or `Auth` -> Implement UI changes. Touching `schema.sql` makes the next deploy wipe the production database, so never edit it just to keep the two files in sync.
- To change sync frequency: Adjust the `setInterval` value in `viewer.php`.
- To update API keys: change the `YT_*` GitHub secrets (production) or `src/config.local.php` (local); `src/config.php` only holds defaults.
