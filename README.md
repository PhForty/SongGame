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
* Datenbank aufsetzen (Mit passendem User und Namen, s. `db-connect.php`)

# Konfiguration
Ein YouTube-API-Key (`src/config.php`) ist **optional**: Titel werden sonst über YouTubes oEmbed-Endpunkt geholt, der ohne Key auskommt. Der Key liefert zusätzlich die verlässliche Auskunft, ob ein Video eingebettet werden darf. Für das Erstellen von Playlists wird weiterhin die OAuth2-Anmeldung benötigt.

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