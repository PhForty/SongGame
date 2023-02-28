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

# Todos
* [ ] Player controls: Automatic pauses and duration for playing
* [ ] Spotify Embedded Player?
* [ ] Togglebarer Nachtmodus
* [ ] Add Confetti on Link submission, with [this package](https://www.npmjs.com/package/canvas-confetti)

# Quellen
Github Ribbon von [simonwhitaker](https://github.com/simonwhitaker/github-fork-ribbon-css); [MIT License](https://github.com/tholman/github-corners/blob/master/license.md)