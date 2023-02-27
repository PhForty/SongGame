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
* [ ] Output Liste am Ende von allen Links -> Für Interessierte
* [ ] Player controls: Automatic pauses and duration for playing
* [ ] Spotify Embedded Player
* [ ] 3 words instead of 5 letters for room codes (easier to communicate)
* [ ] Togglebarer Nachtmodus
* [ ] Klareren Eingabe-Flow machen, sodass es keine Erklärungen braucht

# Quellen
Github Ribbon von [simonwhitaker](https://github.com/simonwhitaker/github-fork-ribbon-css); [MIT License](https://github.com/tholman/github-corners/blob/master/license.md)