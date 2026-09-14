# SongGame
Kleine mandantenfähige WebApp für das Songspiel. Sammelt anonym Links und spielt Youtube Videos in zufälliger Reihenfolge ab.

# Install
## Docker
```
git clone https://github.com/PhForty/SongGame.git
cd SongGame
docker-compose up
```
Dann im Browser https://localhost öffnen

## Manuell
* Git Repo klonen
* Webserver aufsetzen (z.B. Apache) - `src/` dort reinkopieren
* Datenbank aus `src/schema.sql` aufsetzen und Zugangsdaten in `src/config.local.php` eintragen (s. Konfiguration)

# Konfiguration
Ein YouTube-API-Key ist **optional**: Titel werden sonst über YouTubes oEmbed-Endpunkt geholt, der ohne Key auskommt. Der Key liefert zusätzlich die verlässliche Auskunft, ob ein Video eingebettet werden darf. Für das Erstellen von Playlists wird weiterhin die OAuth2-Anmeldung benötigt.

`src/config.php` enthält nur noch die Docker-Defaults. Echte Zugangsdaten liegen in `src/config.local.php` (gitignored) und überschreiben die Defaults:

```php
<?php
return [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'dXXXXXX',
    'DB_PASS' => '…',
    'DB_NAME' => 'dXXXXXX',
    'YT_API_KEY' => '…',
    'YT_CLIENT_ID' => '…',
    'YT_CLIENT_SECRET' => '…',
];
```

Auf songgame.de wird diese Datei bei jedem Deploy aus GitHub-Secrets erzeugt — von Hand anlegen muss man sie nur bei einer manuellen Installation.

# Deployment
Jeder Push auf `main` deployt nach songgame.de: [.github/workflows/deploy.yml](.github/workflows/deploy.yml).

1. PHP-Syntaxcheck über alle Dateien in `src/`.
2. `src/config.local.php` wird aus den Secrets geschrieben.
3. `src/` wird per FTPS auf den All-Inkl-Webspace gespiegelt (inkrementell, nur geänderte Dateien).
4. **Nur wenn sich `src/schema.sql` im Push geändert hat:** die Datenbank wird daraus neu aufgebaut.

## Datenbank-Reset
`schema.sql` enthält `DROP TABLE` — der Reset **löscht alle laufenden Spiele und Einreichungen**. Er läuft deshalb nur, wenn `schema.sql` im jeweiligen Push wirklich geändert wurde. Für neue Spalten auf einer bestehenden Datenbank stattdessen einen Schritt in `src/Migrations.php` ergänzen; die laufen bei jedem Request und sind zerstörungsfrei.

Ein Reset ohne Schema-Änderung geht über *Actions → Deploy to songgame.de → Run workflow → force_schema*.

Die Pipeline verbindet sich **nie selbst** zur Datenbank: Der Reset läuft in einem PHP-Skript auf dem All-Inkl-Webserver, die MySQL-Verbindung kommt also von localhost. Der Datenbank-Benutzer darf (und soll) deshalb auf `localhost` beschränkt bleiben — Fernzugriff im KAS muss nicht aktiviert werden, und es gibt keine GitHub-Runner-IPs freizuschalten.

Technisch gibt es auf dem Server keinen dauerhaften Deploy-Endpunkt: Die Pipeline erzeugt pro Lauf ein Wegwerf-Skript aus [.github/scripts/apply-schema.php.tpl](.github/scripts/apply-schema.php.tpl) mit zufälligem Dateinamen und zufälligem Token, lädt es hoch, ruft es einmal per HTTPS auf und löscht es wieder. Zusätzlich löscht das Skript sich selbst und verweigert nach 10 Minuten den Dienst.

## Secrets und Variables
Als Repository-Secrets bzw. -Variables unter *Settings → Secrets and variables → Actions* anlegen:

| Secret | Beispiel / Quelle |
| --- | --- |
| `FTP_HOST` | `wXXXXXX.kasserver.com` (KAS → FTP) |
| `FTP_USERNAME` | FTP-Benutzer aus dem KAS |
| `FTP_PASSWORD` | dessen Passwort |
| `DB_HOST` | `localhost` — die Verbindung kommt immer vom Webserver selbst |
| `DB_NAME` | `dXXXXXX` |
| `DB_USER` | `dXXXXXX` |
| `DB_PASSWORD` | Datenbank-Passwort |
| `YT_API_KEY` | optional |
| `YT_CLIENT_ID` | optional, nur für Playlists |
| `YT_CLIENT_SECRET` | optional, nur für Playlists |

Optionale *Variables* (mit Defaults, nur setzen wenn abweichend):

| Variable | Default | Bedeutung |
| --- | --- | --- |
| `FTP_SERVER_DIR` | `/` | Zielverzeichnis des FTP-Users; bei All-Inkl oft `/songgame.de/` o. ä. |
| `SITE_URL` | `https://songgame.de` | Basis-URL für den Aufruf des Wegwerf-Skripts |
| `FTP_PROTOCOL` | `ftps` | `ftp`, falls der Account kein FTPS kann |

## Fehlersuche
**`530 Login incorrect` beim FTP-Schritt** — TLS und Host stimmen dann bereits, nur die Zugangsdaten werden abgelehnt. In dieser Reihenfolge prüfen:

1. Der erste Workflow-Schritt gibt die Länge jedes Secrets aus und bricht ab, wenn eines mit Leerzeichen oder Zeilenumbruch anfängt oder endet. Stimmt die Länge von `FTP_PASSWORD` mit dem echten Passwort überein?
2. Die Zugangsdaten lokal gegenprüfen (zeigt das Wurzelverzeichnis des FTP-Users, praktisch auch für `FTP_SERVER_DIR`):
   ```powershell
   curl.exe -v --ssl-reqd --user "BENUTZER:PASSWORT" --list-only ftp://wXXXXXX.kasserver.com/
   ```
3. Im KAS steht unter *FTP* der exakte Benutzername — nicht die KAS-Kennung und nicht die E-Mail-Adresse. Ein zusätzlicher FTP-Benutzer hat außerdem ein eigenes Passwort, nicht das des KAS-Logins.

# Bedienung
* **Spiel teilen:** Der Host findet im Kopfbereich einen "Spiel teilen"-Button mit QR-Code zum Herumzeigen.
* **Zen-Modus / Vollbild:** In der Spielansicht oben rechts am Video – oder per Tastatur `z` (Zen) bzw. `f` (Vollbild), `Esc` beendet.
* **Untertitel & YouTube-Steuerung:** Beide standardmäßig aus, umschaltbar unter dem Video (wird pro Browser gemerkt).
* **Nachtmodus:** Umschalter auf jeder Seite, auch schon im Startscreen. Ohne eigene Wahl folgt er der Systemeinstellung.

# Todos
* [x] Player controls: Automatic pauses and duration for playing
* [ ] Spotify Embedded Player?
* [x] Togglebarer Nachtmodus

# Quellen
Github Ribbon von [simonwhitaker](https://github.com/simonwhitaker/github-fork-ribbon-css); [MIT License](https://github.com/tholman/github-corners/blob/master/license.md)